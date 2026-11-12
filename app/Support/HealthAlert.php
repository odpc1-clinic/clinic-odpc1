<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * แจ้งเตือนผู้ดูแลทาง LINE เมื่อระบบล่ม (2569-10-20)
 *
 * เก็บทุกอย่างเป็นไฟล์ใน storage/app ไม่ใช้ฐานข้อมูล — ต้องส่งได้แม้ MySQL ดับ (กรณีที่ต้องแจ้งมากที่สุด)
 * ส่งตรงไป LINE API ไม่ผ่าน LineMessagingService (ตัวนั้นเขียน log ลงฐาน ถ้าฐานล่มจะส่งไม่ออก)
 * โควตา LINE 300 ข้อความ/เดือน ใช้ร่วมกับแจ้งนัดคนไข้ — ส่งเฉพาะตอน "เพิ่งล่ม" กับ "กลับมาแล้ว" เท่านั้น
 *
 * ผูกผู้รับ: Admin กดสร้างรหัสในหน้า Dashboard งานพัฒนา → พิมพ์ #รหัส 8 หลักในแชท LINE OA ของคลินิก (หมดอายุ 10 นาที)
 * ใช้ # นำหน้าและ 8 หลัก ให้ต่างจากรหัสผูกบัญชีคนไข้ (6 หลัก) ชัดเจน
 */
class HealthAlert
{
    private const FILE = 'health-alert.json';

    public static function data(): array
    {
        $j = json_decode((string) @file_get_contents(storage_path('app/' . self::FILE)), true);
        return (is_array($j) ? $j : []) + ['recipients' => [], 'code' => null, 'code_expires' => null];
    }

    private static function save(array $d): void
    {
        file_put_contents(storage_path('app/' . self::FILE), json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    public static function newCode(): string
    {
        $d = self::data();
        $d['code'] = (string) random_int(10000000, 99999999);
        $d['code_expires'] = now()->addMinutes(10)->timestamp;
        self::save($d);
        return $d['code'];
    }

    /** null = ข้อความนี้ไม่ใช่รหัสผูกแจ้งเตือนระบบ (ให้ webhook ทำงานต่อตามเดิม) · true = ผูกแล้ว · false = รหัสผิด/หมดอายุ */
    public static function tryLink(string $text, string $userId): ?bool
    {
        // รับทั้งมี/ไม่มี # (ผู้ใช้พิมพ์แค่ตัวเลขแล้วบอทเงียบ — 2569-10-20) · # เต็มความกว้าง · เว้นวรรค/ขีดคั่น
        // เลข 8 หลักไม่ชนกับรหัสผูกบัญชีคนไข้ (6 หลัก)
        $t = preg_replace('/[\s\-]+/u', '', trim($text));
        if (! preg_match('/^[#＃]?(\d{8})$/u', $t, $m)) { return null; }
        $d = self::data();
        if (! $d['code'] || (int) $d['code_expires'] < time() || ! hash_equals((string) $d['code'], $m[1])) { return false; }

        $d['recipients'] = array_values(array_filter($d['recipients'], fn ($r) => $r['id'] !== $userId));
        $d['recipients'][] = ['id' => $userId, 'name' => self::profileName($userId), 'linked_at' => now()->toDateTimeString()];
        $d['code'] = $d['code_expires'] = null;
        self::save($d);
        return true;
    }

    public static function remove(string $userId): void
    {
        $d = self::data();
        $d['recipients'] = array_values(array_filter($d['recipients'], fn ($r) => $r['id'] !== $userId));
        self::save($d);
    }

    /** ส่งถึงผู้รับทุกคน คืนจำนวนที่ส่งสำเร็จ */
    public static function send(string $text): int
    {
        $token = config('services.line.channel_access_token');
        $to = array_column(self::data()['recipients'], 'id');
        if (! $token || ! $to) {
            Log::warning('health-alert: ไม่ได้ส่ง (ไม่มี token LINE หรือยังไม่มีผู้รับ) — ' . $text);
            return 0;
        }
        $ok = 0;
        foreach ($to as $id) {
            try {
                $r = Http::withToken($token)->timeout(10)->post('https://api.line.me/v2/bot/message/push', [
                    'to' => $id, 'messages' => [['type' => 'text', 'text' => $text]],
                ]);
                $r->successful() ? $ok++ : Log::error('health-alert: LINE ตอบ ' . $r->status() . ' ' . $r->body());
            } catch (\Throwable $e) {
                Log::error('health-alert: ส่ง LINE ไม่ได้ — ' . $e->getMessage());
            }
        }
        return $ok;
    }

    private static function profileName(string $userId): string
    {
        try {
            $r = Http::withToken((string) config('services.line.channel_access_token'))->timeout(5)
                ->get('https://api.line.me/v2/bot/profile/' . urlencode($userId));
            return (string) ($r->json('displayName') ?: 'LINE ' . substr($userId, -6));
        } catch (\Throwable $e) {
            return 'LINE ' . substr($userId, -6);
        }
    }
}
