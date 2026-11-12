<?php

namespace App\Http\Controllers;

use App\Models\TbRegistration;
use App\Support\ThaiDate;
use Illuminate\Support\Facades\DB;

/**
 * หน้าตรวจวันที่ผิดรูปแบบในบัตร TB 01 (2569-10-05) — อ่านอย่างเดียว
 *
 * ทำไมมี: ช่วงเปิดใช้บัตรใหม่ ๆ ปฏิทินบางเครื่องออกเป็น ดด/วว/ค.ศ. และตัวอ่านวันที่เดิมล้นวันที่ผิดเงียบ ๆ
 * จึงมีวันที่ขยะ (ปี ค.ศ. / ปีอนาคต) ค้างอยู่ ทำให้รายงานและตารางติดตามเพี้ยน
 * ตอนนี้กันขาเข้าแล้ว (App\Support\ThaiDate) ส่วนของเดิมระบบเดาไม่ได้ว่าตั้งใจกรอกวันอะไร → พยาบาลแก้เองทีละแถว
 * (ผู้ใช้กำหนด: ข้อมูลผิดปกติให้พยาบาลจัดการ ระบบแค่ชี้)
 */
class TbDateCheckController extends Controller
{
    /** [ตาราง, ช่อง, ชื่อที่คนอ่าน, กล่องในบัตร (anchor), เป็นเหตุการณ์ที่เกิดแล้ว = วันอนาคตผิด] */
    private const FIELDS = [
        ['tb_sputum_exams',          'collected_date',  'ผลเสมหะ · วันที่เก็บ',         'tb-sputum',  true],
        ['tb_xrays',                 'xray_date',       'X-ray · วันที่ถ่าย',            'tb-xray',    true],
        ['tb_xrays',                 'read_date',       'X-ray · วันที่อ่านผล',          'tb-xray',    true],
        ['tb_drug_susceptibilities', 'sent_date',       'DST · วันที่ส่งตรวจ',           'tb-dst',     true],
        ['tb_drug_susceptibilities', 'result_date',     'DST · วันที่รายงานผล',         'tb-dst',     true],
        ['tb_monthly_doses',         'dispensed_date',  'ใบสรุปการรักษา · วันที่สั่งยา', 'tb-dose',    true],
        ['tb_regimen_changes',       'start_date',      'ประวัติสูตรยา · วันที่เริ่ม',   'tb-regimen', false],
        ['tb_regimen_changes',       'end_date',        'ประวัติสูตรยา · วันที่ใช้ถึง',  'tb-regimen', false],
    ];

    public static function problems(): array
    {
        $today = ThaiDate::todayBe();
        $maxAhead = (substr($today, 0, 4) + ThaiDate::YEARS_AHEAD) . substr($today, 4);
        $out = [];
        foreach (self::FIELDS as [$table, $col, $label, $anchor, $past]) {
            $rows = DB::table($table)->whereNotNull($col)->get(['id', 'tb_registration_id', $col]);
            foreach ($rows as $r) {
                $v = (string) $r->{$col};
                $why = null;
                if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) {
                    $why = 'รูปแบบไม่ใช่วันที่';
                } elseif ((int) $m[1] < 2500) {
                    $why = 'เก็บเป็นปี ค.ศ.';
                } elseif ($past && substr($v, 0, 10) > $today) {
                    $why = 'เป็นวันในอนาคต';
                } elseif (substr($v, 0, 10) > $maxAhead) {
                    $why = 'ปีล่วงหน้าเกิน ' . ThaiDate::YEARS_AHEAD . ' ปี';
                }
                if ($why) {
                    $out[] = ['reg_id' => $r->tb_registration_id, 'label' => $label, 'anchor' => $anchor,
                              'value' => $v, 'shown' => ThaiDate::show($v), 'why' => $why];
                }
            }
        }
        return $out;
    }

    public static function count(): int
    {
        try { return count(self::problems()); } catch (\Throwable $e) { return 0; }
    }

    public function index()
    {
        $items = collect(self::problems());
        $regs = TbRegistration::with('patient')->whereIn('id', $items->pluck('reg_id')->unique())->get()->keyBy('id');

        return view('tb.date_check', ['groups' => $items->groupBy('reg_id'), 'regs' => $regs, 'total' => $items->count()]);
    }
}
