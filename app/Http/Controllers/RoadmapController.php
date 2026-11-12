<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * หน้า "เส้นทางระบบคลินิก" — สรุปพัฒนาการของระบบสำหรับเผยแพร่
 *
 * ตัวเลขทั้งหมดดึงสดจากฐานข้อมูลทุกครั้งที่เปิด จะได้ไม่ต้องตามแก้เอกสารเวลาข้อมูลขยับ
 * เปิดสาธารณะได้เพราะเป็นตัวเลขรวมล้วน — ส่วนที่อ้างถึงคนไข้รายบุคคล (นัดชนวันหยุด)
 * ถูกกันไว้ให้เห็นเฉพาะเจ้าหน้าที่ที่ล็อกอินแล้วเท่านั้น (ดู @auth ในไฟล์ blade)
 */
class RoadmapController extends Controller
{
    public function index()
    {
        // เริ่มใช้งานวันแรก = visit แรกที่บันทึกเข้าระบบ
        $firstVisitAt = DB::table('visits')->min('created_at');
        $start = $firstVisitAt ? \Carbon\Carbon::parse($firstVisitAt) : now();
        $diff = $start->diff(now());
        $runningText = $diff->y . ' ปี ' . $diff->m . ' ด.';

        $totalPatients = DB::table('patients')->count();
        $totalVisits   = DB::table('visits')->count();

        // ปริมาณงานรายปี — นับตามเวลาที่บันทึกเข้าระบบจริง (created_at) แปลงเป็น พ.ศ.
        $rows = DB::table('visits')
            ->select(DB::raw('YEAR(created_at)+543 as y'), DB::raw('count(*) as n'))
            ->groupBy('y')->orderBy('y')->get();
        $peak = max(1, (int) $rows->max('n'));
        $thisYearBe = (int) date('Y') + 543;
        $years = $rows->map(fn ($r) => [
            'year'    => (int) $r->y,
            'count'   => (int) $r->n,
            'width'   => round($r->n * 100 / $peak, 1),
            'partial' => (int) $r->y === $thisYearBe,   // ปีปัจจุบันยังไม่ครบปี ทำแถบลายไว้ให้รู้
        ])->values();

        $lineLinked = Schema::hasColumn('patients', 'line_user_id')
            ? DB::table('patients')->whereNotNull('line_user_id')->where('line_user_id', '!=', '')->count()
            : 0;
        $lineMessages = Schema::hasTable('line_message_logs') ? DB::table('line_message_logs')->count() : 0;
        $holidays     = Schema::hasTable('holidays') ? DB::table('holidays')->count() : 0;

        // นัดล่วงหน้าที่ยังไม่ถึงกำหนดและยังไม่ปิด (วันที่ในระบบเก็บเป็น พ.ศ.)
        $todayBe = (date('Y') + 543) . date('-m-d');
        $upcoming = 0;
        $holidayClashes = collect();
        if (Schema::hasTable('visit_appointments')) {
            $upcoming = DB::table('visit_appointments')
                ->where('appointment_date', '>=', $todayBe)->whereNull('closed_at')->count();

            if (Schema::hasTable('holidays')) {
                $closedDays = DB::table('holidays')->where('is_closed', 1)
                    ->pluck('holiday_date')->map(fn ($d) => substr((string) $d, 0, 10))->all();
                if ($closedDays) {
                    $holidayClashes = DB::table('visit_appointments')
                        ->whereIn('appointment_date', $closedDays)
                        ->where('appointment_date', '>=', $todayBe)
                        ->whereNull('closed_at')
                        ->get(['appointment_date', 'reason']);
                }
            }
        }

        // จำนวนรายการพัฒนาปีนี้ นับจากไฟล์บันทึกการเปลี่ยนแปลงเดียวกับหน้า "มีอะไรใหม่"
        $changeCount = 0;
        $changelogPath = resource_path('changelog.php');
        if (is_file($changelogPath)) {
            foreach ((array) require $changelogPath as $entry) {
                if (str_starts_with((string) ($entry['date'] ?? ''), (string) $thisYearBe)) {
                    $changeCount += count($entry['items'] ?? []);
                }
            }
        }

        $clinics = Schema::hasColumn('visits', 'clinic')
            ? DB::table('visits')->whereNotNull('clinic')->distinct()->count('clinic')
            : 3;

        // วันที่แบบไทย เช่น "8 ก.ย. 2569" — ใช้บนหัวข้อ "วันนี้" และท้ายเอกสาร
        $thMonths = \App\Utillity::TH_MONTH_SHORT;
        $thMonthsFull = \App\Utillity::TH_MONTH_FULL;
        $today     = (int) date('j') . ' ' . $thMonths[(int) date('n')] . ' ' . $thisYearBe;
        $todayFull = (int) date('j') . ' ' . $thMonthsFull[(int) date('n')] . ' ' . $thisYearBe;

        return view('roadmap', compact(
            'totalPatients', 'totalVisits', 'runningText', 'changeCount', 'clinics',
            'years', 'lineLinked', 'lineMessages', 'holidays', 'upcoming', 'holidayClashes',
            'today', 'todayFull', 'thMonthsFull'
        ));
    }
}
