<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\TbDotSubmission;
use App\Models\TbRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ดึงคลิป/รูป DOT ที่คนไข้ส่งเข้า LINE มาเก็บในเครื่อง
 *
 * ทำไมต้องดึงแทนที่จะรอ LINE ยิงมา: เครื่องคลินิกเปิด จ.-ศ. 8.30-16.30
 * และต้อง login captive portal ก่อนออกเน็ต ของที่ส่งนอกเวลาจึงตกหล่น
 * Apps Script รับแทน 24 ชม. แล้วเครื่องคลินิกมาดึงตอนเปิด (ดู docs/tb-dot-line-relay.gs)
 *
 * ไฟล์เก็บแบบเข้ารหัสใน private/tb_dot เหมือนรูปผังครอบครัว
 * ดึงสำเร็จแล้วสั่งลบไฟล์ฝั่ง Drive ทันที ข้อมูลคนไข้จะได้ไม่ค้างอยู่ข้างนอก
 */
class PullTbDotSubmissions extends Command
{
    protected $signature = 'tb:pull-dot {--dry-run : ดูอย่างเดียว ไม่บันทึกและไม่ลบของฝั่ง Drive}';

    protected $description = 'ดึงหลักฐานการกินยา (Video DOT) จากที่พักข้อมูล Apps Script มาเก็บในเครื่อง';

    public function handle(): int
    {
        $url    = config('services.tb_dot_relay.url');
        $secret = config('services.tb_dot_relay.secret');

        if (! $url || ! $secret) {
            $this->error('ยังไม่ได้ตั้ง TB_DOT_RELAY_URL / TB_DOT_RELAY_SECRET ใน .env');
            return 1;
        }

        $list = $this->call_($url, ['secret' => $secret, 'action' => 'list']);
        if ($list === null) { return 1; }

        $items = $list['items'] ?? [];
        if (! $items) {
            $this->info('ไม่มีรายการใหม่');
            return 0;
        }

        $this->info('พบ ' . count($items) . ' รายการ');
        $done = [];
        $saved = $skipped = 0;

        foreach ($items as $item) {
            $messageId = (string) ($item['messageId'] ?? '');
            if ($messageId === '') { continue; }

            // เคยนำเข้าแล้ว (ดึงซ้ำ/เน็ตหลุดกลางทาง) — ถือว่าเสร็จ ไม่ต้องบันทึกซ้ำ
            if (TbDotSubmission::where('line_message_id', $messageId)->exists()) {
                $done[] = $messageId;
                $skipped++;
                continue;
            }

            $patient = Patient::where('line_user_id', $item['userId'] ?? '')->first();

            // ข้อความธรรมดา (เช่น รหัสผูก 6 หลัก) ไม่ใช่หลักฐาน DOT — ปล่อยให้ webhook ปกติจัดการ
            if (! in_array($item['type'] ?? '', ['image', 'video'], true)) {
                $done[] = $messageId;
                $skipped++;
                continue;
            }

            if (! $patient) {
                $this->warn("  ข้าม {$messageId}: ยังไม่รู้ว่าเป็นคนไข้คนไหน (LINE ยังไม่ได้ผูก HN)");
                Log::warning('tb:pull-dot ไม่พบคนไข้ของ LINE user', ['message_id' => $messageId]);
                continue;   // ไม่ mark done ไว้ดึงใหม่หลังพยาบาลผูก LINE ให้แล้ว
            }

            $reg = TbRegistration::where('patient_id', $patient->id)
                ->whereNull('outcome')->orderByDesc('id')->first();

            if ($this->option('dry-run')) {
                $this->line("  [dry-run] {$messageId} · {$patient->code} {$patient->name} · " . ($item['type'] ?? ''));
                continue;
            }

            $file = $this->call_($url, ['secret' => $secret, 'action' => 'file', 'id' => $item['fileId'] ?? '']);
            if (! $file || empty($file['ok'])) {
                $this->warn("  โหลดไฟล์ของ {$messageId} ไม่ได้");
                continue;
            }

            $raw  = base64_decode($file['base64'] ?? '', true);
            if ($raw === false || $raw === '') {
                $this->warn("  ไฟล์ของ {$messageId} เสียหาย");
                continue;
            }

            $name = $messageId . ($item['type'] === 'image' ? '.jpg' : '.mp4');
            Storage::disk('local')->put("private/tb_dot/{$name}", Crypt::encrypt($raw));

            // เวลาที่คนไข้ส่ง (LINE ส่งมาเป็น epoch มิลลิวินาที) ใช้ตัดสินว่าเป็น DOT ของวันไหน
            $sentAt = isset($item['timestamp'])
                ? \Carbon\Carbon::createFromTimestampMs((int) $item['timestamp'])
                : now();

            TbDotSubmission::create([
                'tb_registration_id' => $reg->id ?? null,
                'patient_id'      => $patient->id,
                'dot_date'        => \App\Utillity::AD2BE($sentAt->format('Y-m-d')),
                'received_at'     => $sentAt,
                'line_user_id'    => $item['userId'] ?? null,
                'line_message_id' => $messageId,
                'kind'            => $item['type'] === 'image' ? 'image' : 'video',
                'file'            => $name,
                'mime'            => $file['mime'] ?? null,
                'size'            => strlen($raw),
                'source'          => 'line',
                'ai_status'       => 'pending',
            ]);

            $this->line("  เก็บแล้ว {$messageId} · {$patient->code} {$patient->name} · " . $sentAt->format('d/m H:i'));
            $done[] = $messageId;
            $saved++;
        }

        if ($done && ! $this->option('dry-run')) {
            $this->call_($url, ['secret' => $secret, 'action' => 'done', 'ids' => implode(',', $done)]);
        }

        $this->info("บันทึก {$saved} รายการ · ข้าม {$skipped} รายการ");

        return 0;
    }

    /** ยิงไปที่ Apps Script — ถ้าเน็ตยังไม่ผ่าน captive portal จะล้มที่นี่ ไม่ทำให้ข้อมูลเสีย */
    private function call_(string $url, array $query): ?array
    {
        try {
            $res = Http::timeout(60)->get($url, $query);
        } catch (\Throwable $e) {
            $this->error('ต่อกับที่พักข้อมูลไม่ได้: ' . $e->getMessage() . ' (ยัง login เน็ตอยู่หรือเปล่า)');
            return null;
        }

        if (! $res->ok()) {
            $this->error('ที่พักข้อมูลตอบ HTTP ' . $res->status());
            return null;
        }

        $data = $res->json();
        if (! is_array($data) || (isset($data['ok']) && $data['ok'] === false)) {
            $this->error('ที่พักข้อมูลปฏิเสธ: ' . ($data['error'] ?? 'ไม่ทราบสาเหตุ') . ' (รหัสลับตรงกันหรือเปล่า)');
            return null;
        }

        return $data;
    }
}
