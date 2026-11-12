<?php

namespace App\Http\Middleware;

use App\Models\Config;
use Closure;
use Illuminate\Http\Request;

/**
 * เปิดหน้ารายงานให้ดูได้โดยไม่ต้อง login ถ้าถือ "ลิงก์ลับ" ที่ถูกต้อง (ใช้ฉายในห้องประชุม)
 *
 * เงื่อนไขผ่าน: login อยู่แล้ว หรือ ?key= ตรงกับ configs.public_report_token
 * token เก็บใน configs เพื่อให้เปลี่ยนได้ทันทีเมื่อลิงก์หลุด (ลิงก์เก่าจะใช้ไม่ได้ทันที)
 * ตั้งใจไม่ให้เข้าถึงหน้าอื่นนอกจาก route ที่ผูก middleware นี้ไว้เท่านั้น
 */
class ReportPublicLink
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            return $next($request);
        }

        // สวิตช์หลัก: ปิดอยู่ = ลิงก์ลับใช้ไม่ได้ ไม่ว่าจะถือ key ถูกหรือไม่
        if (!Config::isEnabled('public_report_link_enabled')) {
            return redirect()->route('login');
        }

        // หมดอายุแล้วก็ใช้ไม่ได้ (เว้นว่าง = ไม่ตั้งวันหมดอายุ)
        $expires = trim((string) Config::where('name', 'public_report_link_expires_at')->value('value'));
        if ($expires !== '' && now()->greaterThan(\Carbon\Carbon::parse($expires))) {
            return redirect()->route('login');
        }

        $token = trim((string) Config::where('name', 'public_report_token')->value('value'));
        $given = trim((string) $request->query('key', ''));

        // ต้องตั้ง token ไว้ก่อน ถ้ายังว่าง = ปิดลิงก์ลับ (กันเผลอเปิดหน้าให้ทุกคนตอน config หาย)
        if ($token !== '' && $given !== '' && hash_equals($token, $given)) {
            // โหมดลิงก์ลับ: ให้หน้ารู้ตัวว่ากำลังฉายแบบไม่ได้ login (ซ่อนเมนู/ปุ่มที่ไม่เกี่ยว)
            $request->attributes->set('public_report_link', true);
            return $next($request);
        }

        return redirect()->route('login');
    }
}
