<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

/**
 * บันทึกการเข้าระบบลง storage/logs/security-*.log (2569-10-20 · เตรียมรับการทดสอบเจาะระบบของกรม)
 *
 * ใครเข้า/ออก เข้าไม่สำเร็จกี่ครั้ง ถูกล็อกเพราะเดารหัสผ่าน — จาก IP ไหน เมื่อไร
 * ไม่บันทึกรหัสผ่านที่พิมพ์ผิด (บันทึกแค่ชื่อบัญชีที่พยายามเข้า) · เก็บ 180 วัน (config/logging.php ช่อง security)
 */
class LogAuthEvents
{
    public function handle(object $event): void
    {
        $log = Log::channel('security');
        $ip = request()->ip();
        match (true) {
            $event instanceof Login => $log->info('login', ['user_id' => $event->user->id, 'ip' => $ip]),
            $event instanceof Logout => $log->info('logout', ['user_id' => $event->user?->id, 'ip' => $ip]),
            $event instanceof Failed => $log->warning('login failed', [
                'account' => mb_substr((string) ($event->credentials['email'] ?? ''), 0, 100),
                'known_account' => (bool) $event->user, 'ip' => $ip,
            ]),
            $event instanceof Lockout => $log->warning('lockout', [
                'account' => mb_substr((string) $event->request->input('email'), 0, 100), 'ip' => $ip,
            ]),
            default => null,
        };
    }
}
