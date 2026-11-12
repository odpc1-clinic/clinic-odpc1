<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * บัญชีที่ติดธง must_change_password ต้องตั้งรหัสผ่านใหม่ก่อน จึงจะใช้ระบบส่วนอื่นได้ (2569-11-02)
 *
 * ต้นเรื่อง: หนังสือ สธ 0418.17/719 ลว. 27 ส.ค. 2569 (สกมช. แจ้งเตือนความเสี่ยงรหัสผ่านของระบบในสังกัด สธ.)
 * ตรวจ 2569-11-02 พบหลายบัญชียังใช้รหัสเดาง่ายและไม่เคยเปลี่ยน — ธงมีอยู่แล้วแต่ยังไม่มีตัวบังคับ
 * ไม่ล็อกใครออกจากระบบ: login ได้ตามปกติ แต่ทุกหน้าพาไปหน้าตั้งรหัสใหม่ จนกว่าจะตั้งสำเร็จ
 * เจ้าตัวพิมพ์รหัสใหม่เอง — ผู้ดูแลไม่เห็นรหัส (ข้อ 3 ของหนังสือ ห้ามบอกรหัสแก่ผู้อื่น)
 */
class ForcePasswordChange
{
    /** หน้าที่ต้องเข้าได้ระหว่างถูกบังคับ — หน้าเปลี่ยนรหัส และออกจากระบบ */
    private const ALLOW = ['password.mine', 'password.mine.update', 'logout'];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }
        if ($request->routeIs(...self::ALLOW)) {
            return $next($request);
        }
        // ตัวดึงข้อมูลเบื้องหลัง (AJAX/จอคิว) ไม่ต้องพาไปหน้าไหน — ตอบว่าต้องเปลี่ยนรหัสก่อน
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => 'ต้องเปลี่ยนรหัสผ่านก่อนใช้งาน', 'redirect' => route('password.mine')], 423);
        }
        return redirect()->route('password.mine');
    }
}
