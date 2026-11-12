<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Rules\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * เปลี่ยนรหัสผ่านตามหนังสือ สธ 0418.17/719 ลว. 27 ส.ค. 2569
 * (สกมช. แจ้งเตือนความเสี่ยงชื่อผู้ใช้/รหัสผ่านของระบบในสังกัด สธ.)
 *
 * ทำเป็นหน้าแยกที่มีแค่ช่องรหัสผ่าน ไม่ใช่ไปใช้หน้าแก้ไขผู้ใช้เดิม เพราะ:
 *   - หน้าเดิมมีช่อง Roles อยู่ในฟอร์มเดียวกัน พอยื่นคีย์บอร์ดให้เจ้าตัวพิมพ์
 *     เผลอกดโดนแล้วสิทธิ์เปลี่ยนโดยไม่มีใครรู้ (roles เป็น required ด้วย)
 *   - หน้าเดิมไม่ตรวจเกณฑ์รหัสผ่านเลย พิมพ์ 1234 ก็ผ่าน
 *
 * ไม่เก็บตัวรหัสผ่านลง log เด็ดขาด เก็บแค่ "ใครเปลี่ยนเมื่อไหร่"
 */
class PasswordPolicyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /** ข้อมูลของผู้ใช้ที่เอาไปห้ามใช้เป็นรหัสผ่าน */
    private function personalOf(User $u): array
    {
        return [(string) $u->name, (string) $u->email];
    }

    private function log(User $target, string $how): void
    {
        try {
            ActivityLog::create([
                'user_id'       => Auth::id(),
                'user_name'     => Auth::user()->name ?? '',
                'action'        => 'password',
                'subject_type'  => User::class,
                'subject_id'    => $target->id,
                'subject_label' => $target->name . ' (' . $target->email . ')',
                // เก็บแค่วิธีการ ไม่เก็บรหัส — ห้ามใส่ค่ารหัสผ่านลงตรงนี้เด็ดขาด
                'changes'       => ['how' => $how],
                'ip'            => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            // การบันทึก log ต้องไม่ทำให้การเปลี่ยนรหัสล้มเหลว
        }
    }

    /** บันทึกรหัสใหม่ + ล้างธงบังคับเปลี่ยน */
    private function apply(User $u, string $plain): void
    {
        $u->forceFill([
            'password'             => Hash::make($plain),
            'password_changed_at'  => now(),
            'must_change_password' => false,
        ])->save();
    }

    // ================= ผู้ใช้เปลี่ยนรหัสของตัวเอง =================

    /** คนที่ยังจำรหัสเดิมได้ เปลี่ยนเองได้เลย ไม่ต้องต่อคิวที่โต๊ะแอดมิน */
    public function mine()
    {
        return view('password.mine', ['hint' => StrongPassword::hint()]);
    }

    public function updateMine(Request $request)
    {
        $user = Auth::user();

        $v = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'current_password' => 'required',
            'password'         => ['required', 'confirmed',
                                   new StrongPassword($user->password, $this->personalOf($user))],
        ], [
            'current_password.required' => 'กรุณากรอกรหัสผ่านเดิม',
            'password.confirmed'        => 'รหัสผ่านใหม่สองช่องไม่ตรงกัน',
        ]);
        if (! $v->fails() && ! Hash::check($request->input('current_password'), $user->password)) {
            $v->errors()->add('current_password', 'รหัสผ่านเดิมไม่ถูกต้อง (ช่องบนสุดต้องเป็นรหัสที่ใช้เข้าระบบอยู่ตอนนี้)');
        }
        if ($v->errors()->any() || $v->fails()) {
            // บันทึก "เหตุผล" ที่ไม่ผ่าน (ไม่มีตัวรหัส) — 2569-11-02 เจ้าหน้าที่กดแล้วไม่ผ่านโดยไม่รู้ตัว
            // แล้วไปเข้าด้วยรหัสใหม่ที่ไม่ได้บันทึก ทำให้เข้าไม่ได้ — ต้องรู้ว่าติดเกณฑ์ข้อไหนบ่อย
            \Illuminate\Support\Facades\Log::channel('security')->info('password change rejected', [
                'user_id' => $user->id, 'reasons' => $v->errors()->all(),
            ]);
            return back()->withErrors($v)->with('pw_failed', true);
        }

        $this->apply($user, $request->input('password'));
        $this->log($user, 'self');

        return redirect()->route('password.mine')->with('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
    }

    // ================= แอดมินเปิดหน้าให้คนที่ลืมรหัส =================

    /**
     * แอดมินเปิดหน้านี้ให้ แล้ว "ยื่นคีย์บอร์ดให้เจ้าตัวพิมพ์เอง"
     * แอดมินจึงไม่เห็นรหัส ตรงกับข้อ 3 ของหนังสือที่ห้ามบอกรหัสแก่ผู้อื่น
     */
    public function forUser($id)
    {
        $this->authorizeAdmin();
        $user = User::findOrFail($id);

        return view('password.for-user', ['user' => $user, 'hint' => StrongPassword::hint()]);
    }

    public function updateForUser(Request $request, $id)
    {
        $this->authorizeAdmin();
        $user = User::findOrFail($id);

        $request->validate([
            'password' => ['required', 'confirmed',
                           new StrongPassword($user->password, $this->personalOf($user))],
        ], [
            'password.confirmed' => 'รหัสผ่านใหม่สองช่องไม่ตรงกัน',
        ]);

        $this->apply($user, $request->input('password'));
        $this->log($user, 'admin-assisted');

        return redirect()->route('password.status')
            ->with('success', 'เปลี่ยนรหัสผ่านของ ' . $user->name . ' เรียบร้อยแล้ว');
    }

    // ================= หน้าสรุปไว้ตอบหนังสือ =================

    /**
     * ใครเปลี่ยนแล้วบ้างนับจากวันที่หนังสือสั่ง — เอาไปแนบหนังสือตอบได้
     * ค่าเริ่มต้นนับจาก 2569-08-25 (วันที่หนังสือ สธ 0434.02/247)
     */
    public function status(Request $request)
    {
        $this->authorizeAdmin();

        $since = $request->get('since', '2026-08-25');   // ค.ศ. เพราะ timestamp เก็บเป็น ค.ศ.

        $users = User::with('roles')->withCount('permissions')->orderBy('id')
            ->get(['id', 'name', 'email', 'password_changed_at', 'must_change_password']);

        // บัญชีที่ไม่มีบทบาท/สิทธิ์ = ปิดใช้งาน (เข้าระบบไม่ได้ — BlockUsersWithoutRole) ไม่นับเป็น "ยังไม่เปลี่ยน"
        // เช่น บัญชีตั้งต้น admin@admin.com และบัญชีซ้ำที่ปิดไป 2569-11-02 — รายงานแนบหนังสือจะได้ตรงความจริง
        foreach ($users as $u) {
            $u->is_disabled = $u->roles->isEmpty() && (int) $u->permissions_count === 0;
            $u->is_done = $u->password_changed_at && $u->password_changed_at >= $since;
        }
        $active = $users->where('is_disabled', false);

        return view('password.status', [
            'users'    => $users,
            'done'     => $active->where('is_done', true)->count(),
            'total'    => $active->count(),
            'forced'   => $active->where('is_done', false)->where('must_change_password', true)->count(),
            'disabled' => $users->where('is_disabled', true)->count(),
            'since'    => $since,
        ]);
    }

    private function authorizeAdmin(): void
    {
        $u = Auth::user();
        if (! $u || ! method_exists($u, 'hasRole') || ! $u->hasRole('Admin')) {
            abort(403, 'เฉพาะผู้ดูแลระบบเท่านั้น');
        }
    }
}
