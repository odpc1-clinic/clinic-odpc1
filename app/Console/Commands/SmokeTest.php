<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Visit;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ทดสอบเปิดหน้าหลักของระบบทุกหน้าแบบอัตโนมัติ (กลุ่ม IT ขอ 2569-10-19: ทดสอบโค้ดรุ่นเก่า-ใหม่)
 *
 * รันได้ทั้งระบบทดสอบ (ก่อนอัปเดต) และระบบจริง (หลังอัปเดต) แล้วเทียบผลกัน
 * ตรวจ: หน้าเปิดได้ (ไม่ใช่ 5xx) · ฟอร์มไม่ซ้อนกัน (CLAUDE.md ข้อ 1) · ไม่มีข้อความ error ของ PHP/Blade หลุดมาในหน้า
 * อ่านอย่างเดียว — ทุกคำขอเป็น GET และครอบด้วย transaction ที่ rollBack ทิ้ง (บางหน้าบันทึก log ตอนเปิด)
 * ผลเก็บที่ storage/app/smoke-test.json ให้หน้า Dashboard งานพัฒนาอ่าน · มีหน้าพัง = exit code 1
 */
class SmokeTest extends Command
{
    protected $signature = 'clinic:smoke-test {--user= : id ผู้ใช้ที่ใช้ล็อกอิน (ค่าเริ่มต้น = Admin คนแรก)}';
    protected $description = 'เปิดหน้าหลักของระบบทุกหน้า ตรวจว่าไม่พัง แล้วเก็บผลให้หน้า Dashboard งานพัฒนา';

    public function handle(): int
    {
        // ค่าเริ่มต้น = Admin ที่มีสิทธิ์มากที่สุด (เปิดหน้าจัดการผู้ใช้ได้) — Admin บางบัญชีถูกถอดสิทธิ์ย่อยไว้ หน้าจะโดนข้าม
        $user = $this->option('user') ? User::find($this->option('user'))
            : (User::role('Admin')->get()->first(fn ($u) => $u->can('view_users')) ?? User::role('Admin')->first());
        if (! $user) { $this->error('ไม่พบผู้ใช้สำหรับล็อกอิน'); return 1; }
        Auth::login($user);

        $pages = [
            '/home', '/patient', '/visit/today', '/visit/appointments', '/finance', '/pharmacy', '/lab/room',
            '/medicine', '/tb', '/tb/dot-board', '/tb/report', '/report/yearly-dashboard', '/report/dashboard',
            '/whats-new', '/holiday', '/contact-sti', '/rihis-vct', '/r506', '/vaccine-schedule', '/standard-price',
            '/backend/users', '/activity-log',
        ];
        // หน้าตรวจของ visit ล่าสุดแต่ละคลินิก + บัตร TB ล่าสุด + ข้อมูลคนไข้ล่าสุด
        foreach ([1, 2, 3] as $clinic) {
            $id = Visit::where('clinic', $clinic)->orderByDesc('id')->value('id');
            if ($id) { $pages[] = "/visit/{$id}/edit?page=2"; }
        }
        if ($tb = DB::table('tb_registrations')->orderByDesc('id')->value('id')) { $pages[] = "/tb/{$tb}"; }
        if ($pt = DB::table('patients')->orderByDesc('id')->value('id')) { $pages[] = "/patient/{$pt}/edit"; }

        $results = [];
        $failed = 0;

        // รหัสห้อง (VisitStatus) ต้องตรงกับรายการห้องที่แสดงผล — เพิ่มห้องใหม่แล้วลืมเพิ่มอีกฝั่ง = เตือนตรงนี้
        $pairs = [
            'VisitStatus = Visit::$statusOption' => [\App\Enums\VisitStatus::class, Visit::$statusOption],
            'PatientRiskGroup = Patient::$statusOption' => [\App\Enums\PatientRiskGroup::class, \App\Models\Patient::$statusOption],
            'LabStatus = Lab::$statusOption' => [\App\Enums\LabStatus::class, \App\Models\Lab::$statusOption],
        ];
        foreach ($pairs as $name => [$enum, $opts]) {
            $enumKeys = array_map(fn ($c) => $c->value, $enum::cases());
            $optKeys = array_values(array_filter(array_keys($opts), fn ($k) => $k !== ''));   // ตัวเลือกว่าง "" ของฟอร์ม ไม่ใช่รหัส
            sort($enumKeys); sort($optKeys);
            if ($enumKeys !== $optKeys) {
                $failed++;
                $this->error("พัง: {$name} ไม่ตรงกัน — enum [" . implode(',', $enumKeys) . '] / รายการ [' . implode(',', $optKeys) . ']');
                $results[] = ['url' => $name, 'status' => 500, 'ms' => 0, 'nested' => false, 'leak' => false, 'ok' => false, 'skipped' => false];
            }
        }
        foreach ($pages as $url) {
            $t0 = microtime(true);
            DB::beginTransaction();
            try {
                $res = app()->handle(Request::create($url, 'GET'));
                $status = $res->getStatusCode();
                $html = (string) $res->getContent();
            } catch (\Throwable $e) {
                $status = 500;
                $html = $e->getMessage();
            } finally {
                DB::rollBack();
            }
            $ms = (int) round((microtime(true) - $t0) * 1000);
            $nested = $this->maxFormDepth($html) > 1;
            $leak = (bool) preg_match('/(ErrorException|Undefined (variable|index|array key)|Whoops, looks like|SQLSTATE\[)/', $html);
            $ok = $status < 500 && ! $nested && ! $leak;
            $skipped = in_array($status, [401, 403], true);   // ผู้ใช้ที่ใช้ทดสอบไม่มีสิทธิ์หน้านี้ — ไม่นับว่าผ่าน/พัง
            if (! $ok) { $failed++; }
            $results[] = compact('url', 'status', 'ms', 'nested', 'leak', 'ok', 'skipped');
            $this->line(sprintf('%s %3d %5dms %s%s', ! $ok ? 'พัง' : ($skipped ? 'ข้าม' : 'ผ่าน'), $status, $ms, $url, $nested ? ' [ฟอร์มซ้อน]' : ($leak ? ' [มี error ในหน้า]' : '')));
        }

        $out = [
            'run_at' => now()->toDateTimeString(), 'env' => app()->environment(),
            'db' => DB::getDatabaseName(), 'commit' => self::commit(),
            'total' => count($results), 'failed' => $failed,
            'skipped' => count(array_filter($results, fn ($r) => $r['skipped'])), 'results' => $results,
        ];
        file_put_contents(storage_path('app/smoke-test.json'), json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->info("ผ่าน " . (count($results) - $failed) . " / " . count($results) . " หน้า");
        return $failed ? 1 : 0;
    }

    /** ความลึกของ <form> ซ้อน — ตัดเนื้อใน <script> และคอมเมนต์ออกก่อน (CLAUDE.md ข้อ 1: เคยนับผิดเพราะคำว่า <form> ในคอมเมนต์) */
    private function maxFormDepth(string $html): int
    {
        $html = preg_replace(['#<script\b.*?</script>#is', '#<!--.*?-->#s', '#<template\b.*?</template>#is'], '', $html);
        $depth = 0; $max = 0;
        preg_match_all('#<(/?)form\b#i', $html, $m);
        foreach ($m[1] as $close) {
            $depth += $close === '/' ? -1 : 1;
            $max = max($max, $depth);
        }
        return $max;
    }

    /** commit ที่ระบบนี้รันอยู่ (อ่านจากโฟลเดอร์ .git ตรง ๆ ไม่เรียกโปรแกรม git) */
    public static function commit(): ?string
    {
        $git = base_path('.git');
        $head = @file_get_contents($git . '/HEAD');
        if (! $head) { return null; }
        $head = trim($head);
        if (! str_starts_with($head, 'ref:')) { return substr($head, 0, 7); }
        $ref = trim(substr($head, 4));
        $hash = @file_get_contents($git . '/' . $ref);
        if (! $hash && is_file($git . '/packed-refs')) {
            foreach (file($git . '/packed-refs') as $l) {
                if (str_ends_with(trim($l), $ref)) { $hash = strtok($l, ' '); break; }
            }
        }
        return $hash ? substr(trim($hash), 0, 7) : null;
    }
}
