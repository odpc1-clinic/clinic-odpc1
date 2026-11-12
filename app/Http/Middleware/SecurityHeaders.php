<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * header ความปลอดภัยพื้นฐาน (2569-10-20 · เตรียมรับการทดสอบเจาะระบบของกรม)
 *
 * เลือกเฉพาะตัวที่ไม่ทำให้หน้าเดิมพัง:
 * - nosniff: เบราว์เซอร์ไม่เดาชนิดไฟล์เอง (กันไฟล์อัปโหลดถูกตีความเป็นสคริปต์)
 * - SAMEORIGIN + frame-ancestors 'self': ห้ามเว็บอื่นฝังหน้าเราใน iframe (clickjacking) — popup ในระบบเป็นเว็บเดียวกัน ใช้ได้ปกติ
 * - object-src 'none' / base-uri 'self': ปิดช่อง Flash/plugin และการแก้ <base> — ไม่จำกัดสคริปต์ (หน้าเดิมใช้ inline + CDN เยอะ)
 * - Referrer-Policy: ลิงก์ออกไปเว็บอื่นไม่พา URL ภายใน (มีเลข visit/คนไข้) ไปด้วย
 * - Permissions-Policy: ปิดไมค์/ตำแหน่ง · กล้องให้เฉพาะเว็บเราเอง
 * - ถอด X-Powered-By (บอกรุ่น PHP)
 * ไม่ใส่ HSTS: ใบรับรองตอนนี้เป็นแบบออกเอง ถ้าเครื่องไหนยังไม่ได้ติดตั้งใบรับรอง HSTS จะทำให้กดผ่านหน้าเตือนไม่ได้ = เข้าระบบไม่ได้
 * (ใส่ได้ตอนย้าย server ที่มีใบรับรองจริง)
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (function_exists('header_remove')) { @header_remove('X-Powered-By'); }
        $h = $response->headers;
        $h->remove('X-Powered-By');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()');
        if (! $h->has('Content-Security-Policy')) {
            $h->set('Content-Security-Policy', "frame-ancestors 'self'; object-src 'none'; base-uri 'self'");
        }
        return $response;
    }
}
