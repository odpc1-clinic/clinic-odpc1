<?php

namespace App\Http\Controllers;

use App\Support\HealthAlert;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * /up — ระบบยังทำงานไหม (2569-10-20)
 *
 * clinic:health-watch เรียกทุกนาทีแล้วแจ้ง LINE ผู้ดูแลเมื่อล่ม · ใช้กับเครื่องมือเฝ้าระบบของกลุ่ม IT ได้ด้วย
 * เปิดได้โดยไม่ต้องล็อกอิน จึงตอบแค่ชื่อจุดที่ตรวจกับ ok/warn/fail — ไม่มีข้อความ error ไม่มีค่าตั้งระบบ
 * fail (ฐานข้อมูลหลัก / เขียนไฟล์ไม่ได้) = 503 ระบบใช้งานไม่ได้ · warn = ใช้งานได้แต่มีจุดที่ควรดู (200)
 */
class HealthController extends Controller
{
    public function up()
    {
        $checks = [];
        $try = function (string $name, callable $fn, bool $critical) use (&$checks) {
            try {
                $r = $fn();
                $checks[$name] = $r === true ? 'ok' : 'warn: ' . $r;
            } catch (\Throwable $e) {
                Log::warning("health /up: {$name} — " . $e->getMessage());
                $checks[$name] = $critical ? 'fail' : 'warn: ใช้งานไม่ได้';
            }
        };

        $try('database', fn () => (bool) DB::select('select 1'), true);
        $try('storage', fn () => is_writable(storage_path('framework/sessions')) ?: throw new \RuntimeException('เขียนไม่ได้'), true);
        $try('queue_database', fn () => (bool) DB::connection('queue_system')->select('select 1'), false);
        if (Realtime::enabled()) {
            $try('realtime', function () {
                $o = config('broadcasting.connections.reverb.options');
                $s = @fsockopen($o['host'], (int) $o['port'], $no, $err, 0.5);
                if (! $s) { return 'Reverb ไม่ทำงาน (จอใช้รีเฟรชตามรอบแทน)'; }
                fclose($s);
                return true;
            }, false);
        }
        // ดิสก์เหลือน้อยกว่า 5% — MySQL/Windows เริ่มมีปัญหา
        $drives = PHP_OS_FAMILY === 'Windows' ? array_unique([strtoupper(substr(base_path(), 0, 3)), 'C:\\']) : ['/'];
        foreach ($drives as $d) {
            $try('disk ' . rtrim($d, '\\'), function () use ($d) {
                $free = @disk_free_space($d); $total = @disk_total_space($d);
                if (! $total) { return true; }
                $pct = $free / $total * 100;
                return $pct < 5 ? sprintf('เหลือ %.1f%% (%.0f GB)', $pct, $free / 1073741824) : true;
            }, false);
        }

        $fail = in_array('fail', $checks, true);
        $warn = (bool) array_filter($checks, fn ($v) => str_starts_with($v, 'warn'));
        return response()->json([
            'status' => $fail ? 'down' : ($warn ? 'degraded' : 'ok'),
            'checks' => $checks,
            'time' => now()->toDateTimeString(),
        ], $fail ? 503 : 200)->header('Cache-Control', 'no-store');
    }

    // ---- จัดการผู้รับแจ้งเตือน (หน้า Dashboard งานพัฒนา · Admin) ----

    public function linkCode()
    {
        return back()->with('health_code', HealthAlert::newCode());
    }

    public function test()
    {
        $n = HealthAlert::send('🔔 ทดสอบแจ้งเตือนระบบคลินิก (' . now()->format('H:i') . ')' . "\n" . 'ถ้าได้รับข้อความนี้ ระบบจะแจ้งที่ LINE นี้เมื่อระบบล่มและเมื่อกลับมาใช้งานได้');
        return back()->with('health_msg', $n ? "ส่งข้อความทดสอบแล้ว {$n} คน" : 'ส่งไม่ได้ — ยังไม่มีผู้รับ หรือไม่ได้ตั้ง token LINE ในระบบนี้');
    }

    public function remove(Request $request)
    {
        HealthAlert::remove((string) $request->input('id'));
        return back()->with('health_msg', 'เอาผู้รับออกแล้ว');
    }
}
