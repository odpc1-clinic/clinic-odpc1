<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array|string|null
     */
    protected $proxies;

    /**
     * server ใหม่ (CloudPanel) รับ 443 ด้วย nginx แล้วส่งต่อให้ nginx อีกตัวที่พอร์ต 8080 (2569-10-21)
     * ถ้าไม่เชื่อ 127.0.0.1 ทุกคำขอจะดูเหมือนมาจาก 127.0.0.1 → ตัวจำกัดการเดารหัสผ่านต่อ IP ล็อกทุกคนพร้อมกัน
     * และ log ความปลอดภัยไม่รู้ว่าใครเข้า · ตั้ง TRUSTED_PROXIES=127.0.0.1 ใน .env ของ server นั้น
     * เครื่องเดิม (XAMPP) ไม่มีตัวกลาง — ไม่ตั้ง = ไม่เชื่อใคร (ใครก็ปลอม X-Forwarded-For ไม่ได้)
     */
    public function handle(Request $request, \Closure $next)
    {
        $list = array_values(array_filter(array_map('trim', explode(',', (string) config('app.trusted_proxies')))));
        if ($list) { $this->proxies = $list; }
        return parent::handle($request, $next);
    }

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    // Laravel 9+ ใช้ตัวในเฟรมเวิร์กแทน fideloper/proxy (2569-10-20) — HEADER_X_FORWARDED_ALL ถูกเอาออกจาก Symfony แล้ว
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
