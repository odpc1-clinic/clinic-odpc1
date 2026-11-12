<?php

namespace App\Http\Controllers;

use App\Models\TbDepressionScreen;
use App\Models\TbMonitorCheck;
use App\Models\TbRegistration;
use App\Models\TbVisionTest;
use App\Services\TbMonitor\DepressionScale;
use App\Services\TbMonitor\TbMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * แท็บ "ติดตาม" ของบัตร TB 01 — ตารางติดตามการรักษา / ตรวจสายตา / คัดกรองซึมเศร้า (2569-10-05)
 *
 * แยกจาก TbController (ไฟล์ใหญ่และมีคนแก้พร้อมกันหลายงาน)
 * ทุกคำขอเป็น AJAX ตอบ JSON + HTML ของกล่องที่เปลี่ยน ให้หน้าบัตรสลับในที่ — ไม่โหลดทั้งหน้า
 * เพราะในบัตรมีฟอร์มอื่น (เสมหะ/ข้อมูลบัตร) ที่อาจกรอกค้างอยู่
 * ตารางใหม่ยังไม่ขึ้น (รอ migration) = ตอบ 503 "รอเปิดใช้" ไม่พัง
 */
class TbMonitorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /** GET HTML ของทุกกล่อง (ใช้รีเฟรชหลังสั่งแล็บจากตัวเลือก "สั่งแล็บ") */
    public function html($id)
    {
        return response()->json(['ok' => true, 'html' => $this->boxes($this->reg($id))]);
    }

    /** POST ติ๊กมือหนึ่งช่องในตาราง (รายการที่ไม่มีที่เก็บผลในระบบ หรือไปตรวจที่อื่น) */
    public function check(Request $request, $id)
    {
        if (! TbMonitorCheck::ready()) { return $this->notReady(); }
        $reg = $this->reg($id);
        [$check, $point] = $this->validCell($reg, $request);
        if (! $check) { return response()->json(['message' => 'ไม่พบช่องนี้ในตารางของบัตรนี้ (ตารางอาจเปลี่ยนตามสูตรยา) — โหลดหน้าใหม่'], 422); }

        $d = $this->beDate($request->input('done_date'));
        if (! $d['ok']) { return response()->json(['message' => 'วันที่ตรวจ: ' . $d['error']], 422); }
        $date = $d['be'];

        $note = trim((string) $request->input('note'));
        // EKG: ช่องตัวเลข QTcF (2569-10-22) — เก็บรวมในหมายเหตุ "QTcF 430 ms · …" ตารางกับ popup อ่านกลับจากรูปแบบนี้
        if ($check === 'qtcf') {
            $q = trim((string) $request->input('qtcf'));
            if (! is_numeric($q) || (float) $q < 200 || (float) $q > 800) {
                return response()->json(['message' => 'กรอก QTcF เป็นตัวเลข 200–800 ms'], 422);
            }
            $note = 'QTcF ' . (int) round((float) $q) . ' ms' . ($note !== '' ? ' · ' . $note : '');
        }

        $row = TbMonitorCheck::firstOrNew(['tb_registration_id' => $reg->id, 'check_key' => $check, 'point_key' => $point]);
        $row->done_date = $date;
        $row->note = mb_substr($note, 0, 255) ?: null;
        $row->user_id = Auth::id();
        $row->save();

        return $this->ok($reg, 'บันทึกแล้ว');
    }

    /**
     * POST บันทึกตารางที่แพทย์กำหนดเอง — months + cells[checkKey][] = pointKey (2569-10-22)
     * รับเฉพาะรายการ/คอลัมน์ที่มีในแคตตาล็อก · ไม่ติ๊กเลย = ใช้ตารางมาตรฐานเหมือนเดิม
     */
    public function savePlan(Request $request, $id)
    {
        $reg = $this->reg($id);
        if (! \Illuminate\Support\Facades\Schema::hasColumn('tb_registrations', 'monitor_plan')) { return $this->notReady(); }
        $months = max(1, min(24, (int) $request->input('months', 6)));
        $cat = \App\Support\TbMonitorDefinitions::checkCatalogue();
        $points = array_column(\App\Support\TbMonitorDefinitions::pointCatalogue($months), 0);
        $cells = [];
        foreach ((array) $request->input('cells', []) as $ck => $pks) {
            if (! isset($cat[$ck])) { continue; }
            $pks = array_values(array_intersect($points, array_map('strval', (array) $pks)));
            if ($pks) { $cells[$ck] = $pks; }
        }
        if (! $cells) { return response()->json(['message' => 'ยังไม่ได้ติ๊กช่องไหนเลย — ถ้าจะใช้ตารางมาตรฐาน กด "ใช้ตารางมาตรฐาน"'], 422); }
        $reg->update(['monitor_plan' => ['months' => $months, 'cells' => $cells, 'by' => Auth::id(), 'at' => now()->toDateTimeString()]]);
        return $this->ok($reg->fresh(['patient', 'sputumExams', 'xrays', 'monthlyDoses', 'susceptibilities']), 'บันทึกตารางที่กำหนดเองแล้ว');
    }

    /** DELETE กลับไปใช้ตารางมาตรฐาน (ล้างตารางกำหนดเอง) */
    public function resetPlan($id)
    {
        $reg = $this->reg($id);
        if (\Illuminate\Support\Facades\Schema::hasColumn('tb_registrations', 'monitor_plan')) { $reg->update(['monitor_plan' => null]); }
        return $this->ok($reg->fresh(['patient', 'sputumExams', 'xrays', 'monthlyDoses', 'susceptibilities']), 'กลับไปใช้ตารางมาตรฐานแล้ว');
    }

    /** DELETE เอาติ๊กมือออก */
    public function uncheck(Request $request, $id)
    {
        if (! TbMonitorCheck::ready()) { return $this->notReady(); }
        $reg = $this->reg($id);
        $row = TbMonitorCheck::where('tb_registration_id', $reg->id)
            ->where('check_key', (string) $request->input('check'))->where('point_key', (string) $request->input('point'))->first();
        if ($row) { $row->delete(); }
        return $this->ok($reg, 'เอาออกแล้ว');
    }

    public function vision(Request $request, $id)
    {
        if (! TbVisionTest::ready()) { return $this->notReady(); }
        $reg = $this->reg($id);
        $d = $this->beDate($request->input('test_date'));
        if (! $d['ok'] || ! $d['be']) { return response()->json(['message' => 'วันที่ตรวจ: ' . ($d['error'] ?: 'กรุณากรอกวันที่ (วว/ดด/ปปปป พ.ศ.)')], 422); }
        $date = $d['be'];

        $vaR = mb_substr(trim((string) $request->input('va_right')), 0, 20) ?: null;
        $vaL = mb_substr(trim((string) $request->input('va_left')), 0, 20) ?: null;
        $color = in_array($request->input('color_result'), ['normal', 'abnormal'], true) ? $request->input('color_result') : null;
        $ok = $request->filled('color_plates_correct') ? max(0, min(255, (int) $request->input('color_plates_correct'))) : null;
        $tot = $request->filled('color_plates_total') ? max(0, min(255, (int) $request->input('color_plates_total'))) : null;
        if ($ok !== null && $tot !== null && $ok > $tot) { return response()->json(['message' => 'จำนวนแผ่นที่อ่านถูกมากกว่าจำนวนแผ่นทั้งหมด'], 422); }
        if (! $vaR && ! $vaL && ! $color) { return response()->json(['message' => 'ยังไม่ได้กรอกผล (VA หรือ ตาบอดสี)'], 422); }

        $tv = TbController::todayTbVisit($reg);
        TbVisionTest::create([
            'tb_registration_id' => $reg->id,
            'visit_id' => $tv && \App\Models\VisitAppointment::todayBe() === $date ? $tv->id : null,
            'test_date' => $date, 'va_right' => $vaR, 'va_left' => $vaL, 'color_result' => $color,
            'color_plates_correct' => $ok, 'color_plates_total' => $tot,
            'note' => mb_substr(trim((string) $request->input('note')), 0, 255) ?: null,
            'user_id' => Auth::id(),
        ]);
        return $this->ok($reg, 'บันทึกผลตรวจสายตาแล้ว');
    }

    public function deleteVision($id, $rowId)
    {
        if (! TbVisionTest::ready()) { return $this->notReady(); }
        $reg = $this->reg($id);
        TbVisionTest::where('tb_registration_id', $reg->id)->findOrFail($rowId)->delete();
        return $this->ok($reg, 'ลบแล้ว');
    }

    public function depression(Request $request, $id)
    {
        if (! TbDepressionScreen::ready()) { return $this->notReady(); }
        $reg = $this->reg($id);
        $d = $this->beDate($request->input('screen_date'));
        if (! $d['ok'] || ! $d['be']) { return response()->json(['message' => 'วันที่ประเมิน: ' . ($d['error'] ?: 'กรุณากรอกวันที่ (วว/ดด/ปปปป พ.ศ.)')], 422); }
        $date = $d['be'];

        $q2 = [];
        foreach (DepressionScale::Q2 as $k => $_) {
            $v = $request->input("q2.$k");
            if ($v === null || $v === '') { return response()->json(['message' => '2Q ตอบให้ครบทั้ง 2 ข้อ'], 422); }
            $q2[$k] = (int) $v ? 1 : 0;
        }
        // 9Q / 8Q เก็บเฉพาะเมื่อขั้นก่อนหน้าบอกว่าต้องทำ — กันคำตอบค้างจากการติ๊กแล้วเปลี่ยนใจ
        $q9 = null;
        if ($q2[1] || $q2[2]) {
            $q9 = [];
            foreach (DepressionScale::Q9 as $k => $_) {
                $v = $request->input("q9.$k");
                if ($v === null || $v === '') { return response()->json(['message' => '2Q บวก — ต้องตอบ 9Q ให้ครบ 9 ข้อ'], 422); }
                $q9[$k] = max(0, min(3, (int) $v));
            }
        }
        $pre = DepressionScale::evaluate($q2, $q9);
        $q8 = null;
        if ($pre['need_8q'] && $request->has('q8')) {
            $q8 = [];
            foreach (DepressionScale::Q8 as $k => $_) {
                if ($k === '3a') { continue; }
                $v = $request->input("q8.$k");
                if ($v === null || $v === '') { return response()->json(['message' => '8Q ตอบให้ครบทุกข้อ'], 422); }
                $q8[$k] = (int) $v ? 1 : 0;
            }
            if ($q8['3']) {
                $v = $request->input('q8.3a');
                if ($v === null || $v === '') { return response()->json(['message' => '8Q ข้อ 3 ตอบว่ามี — ต้องตอบข้อ 3a (ควบคุมได้หรือไม่)'], 422); }
                $q8['3a'] = (int) $v ? 1 : 0;
            }
        }
        $r = DepressionScale::evaluate($q2, $q9, $q8);
        $interp = $r['interpretation'];
        if ($r['need_8q'] && $q8 === null) { $interp .= ' · ยังไม่ได้ทำ 8Q'; }

        $tv = TbController::todayTbVisit($reg);
        TbDepressionScreen::create([
            'tb_registration_id' => $reg->id,
            'visit_id' => $tv && \App\Models\VisitAppointment::todayBe() === $date ? $tv->id : null,
            'screen_date' => $date,
            'q2' => $q2, 'q2_positive' => $r['q2_positive'],
            'q9' => $q9, 'q9_score' => $r['q9_score'],
            'q8' => $q8, 'q8_score' => $r['q8_score'],
            'interpretation' => mb_substr($interp, 0, 255),
            'referred' => $request->boolean('referred'),
            'note' => mb_substr(trim((string) $request->input('note')), 0, 255) ?: null,
            'user_id' => Auth::id(),
        ]);
        return $this->ok($reg, 'บันทึกผลประเมินแล้ว — ' . $interp);
    }

    public function deleteDepression($id, $rowId)
    {
        if (! TbDepressionScreen::ready()) { return $this->notReady(); }
        $reg = $this->reg($id);
        TbDepressionScreen::where('tb_registration_id', $reg->id)->findOrFail($rowId)->delete();
        return $this->ok($reg, 'ลบแล้ว');
    }

    // ------------------------------------------------------------------

    private function reg($id): TbRegistration
    {
        return TbRegistration::with(['patient', 'sputumExams', 'xrays', 'monthlyDoses', 'susceptibilities'])->findOrFail($id);
    }

    /** check/point ต้องเป็นช่องที่มีอยู่จริงในตารางของบัตรนี้ */
    private function validCell(TbRegistration $reg, Request $request): array
    {
        $def = TbMonitor::scheduleFor($reg);
        $check = (string) $request->input('check');
        $point = (string) $request->input('point');
        if (! $def) { return [null, null]; }
        $checks = array_column($def['checks'], 'key');
        $points = array_column($def['points'], 0);
        return in_array($check, $checks, true) && in_array($point, $points, true) ? [$check, $point] : [null, null];
    }

    /**
     * วว/ดด/ปปปป -> ผลของ App\Support\ThaiDate::parse (ตัวอ่านกลางของงาน TB 2569-10-05)
     * ผลตรวจสายตา/ประเมินซึมเศร้า/ติ๊กตาราง เป็นเหตุการณ์ที่เกิดแล้ว จึงห้ามวันในอนาคต
     * ว่าง = ok + be null (ผู้เรียกตัดสินเองว่าช่องนั้นบังคับไหม)
     */
    private function beDate($v): array
    {
        return \App\Support\ThaiDate::parse($v, ['allowEmpty' => true, 'notFuture' => true]);
    }

    private function boxes(TbRegistration $reg): array
    {
        TbMonitor::forget();
        return [
            'grid'       => view('tb._monitor_grid', ['reg' => $reg])->render(),
            'extra'      => view('tb._monitor_extra', ['reg' => $reg])->render(),
            'header'     => view('tb._monitor_header', ['reg' => $reg])->render(),
        ];
    }

    private function ok(TbRegistration $reg, string $msg)
    {
        return response()->json(['ok' => true, 'message' => $msg, 'html' => $this->boxes($reg)]);
    }

    private function notReady()
    {
        return response()->json(['message' => 'ยังไม่เปิดใช้ — รอผู้ดูแลระบบสร้างตาราง (migration) ก่อน'], 503);
    }
}
