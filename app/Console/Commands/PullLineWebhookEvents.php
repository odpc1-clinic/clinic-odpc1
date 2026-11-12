<?php

namespace App\Console\Commands;

use App\Services\LineMessagingService;
use App\Services\LineWebhookEvents;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ดึงเหตุการณ์ LINE (เพิ่มเพื่อน / บล็อก / พิมพ์ข้อความ) จากที่พัก Google Apps Script (2569-10-31)
 *
 * ทำไม: ระบบจริงย้ายขึ้น server วงในของกรม LINE ยิง webhook เข้าไม่ถึง และ IT เปิดทางเข้าให้ไม่ได้
 * จึงตั้ง webhook ของ LINE เป็นที่อยู่ Apps Script (docs/line-link-relay.gs ตัวเดียวกับฟอร์ม LIFF)
 * แล้ว server ออกไปดึงทุกนาที — ไม่ต้องเปิดพอร์ตเข้า ไม่ต้องพึ่งเครื่องอื่น
 *
 * Apps Script อ่าน header ลายเซ็นของ LINE ไม่ได้ ใครรู้ที่อยู่ก็ยิงเหตุการณ์ปลอมเข้าที่พักได้
 * จึงตรวจกับ LINE เองก่อนทำอะไร:
 *   - destination ต้องเป็นบัญชี LINE ของเรา
 *   - follow / ข้อความ: userId ต้องเป็นเพื่อนกับบัญชีจริง (userId แต่งเองได้ 404)
 *   - unfollow: ต้อง "ไม่ใช่เพื่อนแล้ว" จริง (กันคนแกล้งยกเลิกการผูกของคนไข้)
 * ต่อ LINE ไม่ได้ = ยังไม่ทำ รอรอบหน้า (ที่พักเก็บไว้ 15 นาที)
 */
class PullLineWebhookEvents extends Command
{
    protected $signature = 'line:pull-webhook';

    protected $description = 'ดึงเหตุการณ์ LINE (เพิ่มเพื่อน/บล็อก/ข้อความ) จากที่พัก Apps Script';

    public function handle(LineMessagingService $line, LineWebhookEvents $events): int
    {
        $url    = config('services.line.link.relay_url');
        $secret = config('services.line.link.relay_secret');
        if (! $url || ! $secret) {
            return 0;   // ยังไม่ได้ตั้งที่พัก = ยังไม่เปิดใช้
        }

        try {
            $res = Http::timeout(30)->get($url, ['secret' => $secret, 'action' => 'wlist']);
        } catch (\Throwable $e) {
            $this->error('ต่อที่พักข้อมูลไม่ได้: ' . $e->getMessage());
            return 1;
        }
        $data = $res->json();
        if (! $res->ok() || ! is_array($data) || empty($data['ok'])) {
            // สคริปต์รุ่นเก่ายังไม่มี wlist — ตอบ items ของฟอร์มแทน ต้องไม่เอามาทำเป็นเหตุการณ์
            $this->error('ที่พักข้อมูลปฏิเสธ: ' . ($data['error'] ?? 'HTTP ' . $res->status()));
            return 1;
        }
        if (! array_key_exists('events', $data)) {
            $this->warn('สคริปต์ที่พักยังเป็นรุ่นเก่า (ไม่มี webhook) — อัปเดต docs/line-link-relay.gs');
            return 1;
        }
        @file_put_contents(storage_path('app/line-webhook-last-pull.txt'), now()->toDateTimeString());

        $items = (array) $data['events'];
        if (! $items) {
            return 0;
        }

        $bot = $line->botUserId();
        $done = [];
        foreach ($items as $item) {
            $relayId = (string) ($item['id'] ?? '');
            $event   = (array) ($item['event'] ?? []);
            if ($relayId === '') { continue; }

            // ส่งซ้ำ (LINE redelivery / ดึงซ้ำก่อนสั่งลบ) — ทำไปแล้ว
            $eid = (string) ($event['webhookEventId'] ?? $relayId);
            if (Cache::has('line_wh_' . $eid)) { $done[] = $relayId; continue; }

            $verdict = $this->check($line, $bot, (string) ($item['destination'] ?? ''), $event);
            if ($verdict === null) {
                continue;   // ต่อ LINE ไม่ได้ — ไว้รอบหน้า
            }
            if ($verdict === false) {
                Log::channel('security')->warning('line:pull-webhook ทิ้งเหตุการณ์ที่ตรวจไม่ผ่าน', [
                    'relay_id' => $relayId, 'type' => $event['type'] ?? null,
                ]);
                $done[] = $relayId;
                continue;
            }

            Cache::put('line_wh_' . $eid, 1, 86400);
            try {
                $events->handle($event, true);
                $this->line("  {$relayId}: " . ($event['type'] ?? '?'));
            } catch (\Throwable $e) {
                Log::error('line:pull-webhook จัดการเหตุการณ์ไม่สำเร็จ', ['relay_id' => $relayId, 'error' => $e->getMessage()]);
            }
            $done[] = $relayId;
        }

        if ($done) {
            try {
                Http::timeout(30)->get($url, ['secret' => $secret, 'action' => 'wdone', 'ids' => implode(',', $done)]);
            } catch (\Throwable $e) {
                // ลบไม่สำเร็จ รอบหน้าดึงซ้ำ — กันซ้ำด้วย webhookEventId แล้ว
            }
            $this->info('จัดการ ' . count($done) . ' เหตุการณ์');
        }
        return 0;
    }

    /** true = ของจริง ทำได้ · false = ปลอม/ไม่ใช่ของเรา ทิ้ง · null = ตรวจไม่ได้ตอนนี้ */
    private function check(LineMessagingService $line, ?string $bot, string $destination, array $event): ?bool
    {
        if ($bot === null) { return null; }
        if ($destination === '' || ! hash_equals($bot, $destination)) { return false; }

        $type   = $event['type'] ?? '';
        $userId = (string) ($event['source']['userId'] ?? '');
        if ($userId === '' || ! in_array($type, ['follow', 'unfollow', 'message'], true)) { return false; }

        $friend = $line->friendStatus($userId);
        if ($friend === null) { return null; }

        return $type === 'unfollow' ? $friend === false : $friend === true;
    }
}
