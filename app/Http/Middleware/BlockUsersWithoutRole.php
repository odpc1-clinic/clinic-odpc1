<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * บัญชีที่ยังไม่ได้รับบทบาท (ไม่มี role และไม่มีสิทธิ์รายตัว) ห้ามใช้ระบบ (2569-10-20)
 *
 * เดิมหน้าส่วนใหญ่เช็คแค่ "ล็อกอินแล้ว" — บัญชีที่สมัครเองผ่าน /register (ปิดไปแล้ว) เปิดเวชระเบียน/ผล RIHIS ได้ทันที
 * ตอนนี้ล็อกอินได้ แต่ถูกพากลับหน้าเข้าระบบพร้อมข้อความ ให้ติดต่อผู้ดูแลกำหนดบทบาทก่อน
 */
class BlockUsersWithoutRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && method_exists($user, 'roles') && ! $user->roles()->exists() && ! $user->permissions()->exists()) {
            Log::channel('security')->warning('blocked: account without role', ['user_id' => $user->id]);
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors([
                'email' => 'บัญชีนี้ยังไม่ได้รับสิทธิ์ใช้งาน — ติดต่อผู้ดูแลระบบให้กำหนดบทบาทก่อน',
            ]);
        }
        return $next($request);
    }
}
