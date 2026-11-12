<?php

namespace App\Console\Commands;

use App\Models\LineLinkRequest;
use App\Services\LineAccountLinker;
use App\Services\LineLinkCrypto;
use App\Services\LineMessagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ดึงรหัสผูก LINE ที่คนไข้กรอกในฟอร์มใน LINE (LIFF) มาจากที่พัก Apps Script
 *
 * ทำไมต้องดึง: server อยู่วงใน LINE/มือถือคนไข้ยิงเข้ามาไม่ได้ (2569-10-22)
 * การพิมพ์รหัสในแชทต้องพึ่ง webhook ขาเข้า ซึ่ง server ใหม่ไม่มี — ฟอร์มจึงเป็นทางผูกหลัก
 * ฟอร์มเข้ารหัสแล้วฝากไว้ที่ Google — เครื่องนี้มาดึงขาออก ถอดรหัส แล้วสั่งลบฝั่ง Google
 *
 * ผูกได้เฉพาะรหัส 6 หลักที่เจ้าหน้าที่ออกให้เท่านั้น (ไม่รับกรอกชื่อ/เบอร์ ตัดสินใจ 2569-10-22)
 */
class PullLineLinkRequests extends Command
{
    protected $signature = 'line:pull-links';

    protected $description = 'ดึงรหัสผูก LINE จากฟอร์มใน LINE (ผ่านที่พัก Apps Script)';

    public function handle(LineAccountLinker $linker, LineMessagingService $line): int
    {
        $url    = config('services.line.link.relay_url');
        $secret = config('services.line.link.relay_secret');

        // ยังไม่ได้ตั้งค่า = ยังไม่เปิดใช้ ไม่ต้องร้องทุกนาที
        if (! $url || ! $secret) {
            return 0;
        }
        if (! LineLinkCrypto::exists()) {
            $this->error('ยังไม่มีกุญแจ — รัน php artisan line:link-setup');
            return 1;
        }

        try {
            $res = Http::timeout(30)->get($url, ['secret' => $secret, 'action' => 'list']);
        } catch (\Throwable $e) {
            $this->error('ต่อที่พักข้อมูลไม่ได้: ' . $e->getMessage());
            return 1;
        }

        $data = $res->json();
        if (! $res->ok() || ! is_array($data) || empty($data['ok'])) {
            $this->error('ที่พักข้อมูลปฏิเสธ: ' . ($data['error'] ?? 'HTTP ' . $res->status()));
            return 1;
        }

        // ชีพจรของตัวดึง — หน้า /line/health ใช้บอกว่าฟอร์มใน LINE ยังทำงาน (ต่อ Google ได้ล่าสุดเมื่อไร)
        @file_put_contents(storage_path('app/line-link-last-pull.txt'), now()->toDateTimeString());

        $done = [];
        foreach ($data['items'] ?? [] as $item) {
            $relayId = (string) ($item['id'] ?? '');
            $userId  = (string) ($item['userId'] ?? '');
            if ($relayId === '') {
                continue;
            }

            // ดึงซ้ำ (เน็ตหลุดก่อนสั่งลบ) — ทำไปแล้ว แค่สั่งลบใหม่
            if (LineLinkRequest::where('relay_id', $relayId)->exists()) {
                $done[] = $relayId;
                continue;
            }

            $form = LineLinkCrypto::decrypt((array) ($item['box'] ?? []));
            if (! $form || $userId === '') {
                Log::warning('line:pull-links ถอดรหัสไม่ได้', ['relay_id' => $relayId]);
                $this->warn("  {$relayId}: ถอดรหัสไม่ได้ (กุญแจไม่ตรงกับหน้าฟอร์ม?)");
                $done[] = $relayId;   // เก็บไว้ก็ถอดไม่ได้อยู่ดี
                continue;
            }

            $code = preg_replace('/\D/', '', (string) ($form['code'] ?? ''));
            $patient = $linker->patientForCode($code);

            LineLinkRequest::create([
                'relay_id'          => $relayId,
                'line_user_id'      => $userId,
                'line_display_name' => self::clip($form['displayName'] ?? null, 190),
                'code'              => mb_substr($code, 0, 10) ?: null,
                'status'            => $patient ? 'linked' : 'failed',
                'patient_id'        => $patient->id ?? null,
                'submitted_at'      => isset($item['ts']) ? \Carbon\Carbon::createFromTimestampMs((int) $item['ts']) : now(),
            ]);

            if ($patient) {
                $linker->link($patient, $userId);
                $line->push($userId, $linker->successMessage($patient),
                    ['patient_id' => $patient->id, 'purpose' => 'link_success']);
                $this->line("  {$relayId}: ผูกแล้ว → {$patient->code}");
            } else {
                $line->push($userId, LineAccountLinker::failedCodeMessage(), ['purpose' => 'link_failed']);
                $this->line("  {$relayId}: รหัสผิด/หมดอายุ");
            }

            $done[] = $relayId;
        }

        if ($done) {
            try {
                Http::timeout(30)->get($url, ['secret' => $secret, 'action' => 'done', 'ids' => implode(',', $done)]);
            } catch (\Throwable $e) {
                // ลบไม่สำเร็จรอบนี้ รอบหน้าดึงซ้ำแล้วเจอ relay_id เดิม จะสั่งลบอีกที
            }
            $this->info('จัดการ ' . count($done) . ' รายการ');
        }

        return 0;
    }

    private static function clip($v, int $len): ?string
    {
        // ฐานเป็น utf8 (3 ไบต์) — อิโมจิในชื่อ LINE ทำให้ insert ล้ม ต้องตัดทิ้งก่อน
        $v = trim(preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', (string) $v) ?? '');
        return $v === '' ? null : mb_substr($v, 0, $len);
    }
}
