<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\Request;

/**
 * สมุดเหลือง "ครั้งนี้" ของ visit TMC (คลินิกขอ 2569-11-04)
 *
 * เลือกครั้งเดียวว่าครั้งนี้ทำอะไรกับสมุด แล้วระบบอัปเดตเลขเล่มปัจจุบัน + ประวัติเล่มเก่าของคนไข้ให้เอง
 * แทนช่องเลขเล่ม + ปุ่ม "ออกเล่มใหม่" แบบเดิมที่พยาบาลต้องจำเองว่ากรณีไหนกดอะไร
 * กล่องอยู่ในฟอร์มหลักของหน้าตรวจ จึงบันทึกด้วย fetch (ห้ามซ้อนฟอร์ม) — ตอบ JSON
 */
class TmcYellowBookController extends Controller
{
    public const ACTIONS = [
        'old'     => 'ใช้เล่มเดิม',
        'new'     => 'ออกเล่มใหม่ (ยังไม่เคยมีเล่ม)',
        // ชำรุด กับ หาย แยกปุ่ม (คลินิกขอ 2569-11-04) — ชำรุดมีเล่มอยู่ในมือ ต้องส่งทำลาย, หายไม่มี
        // visit เก่าที่บันทึก 'replace' ก่อนแยก อาจเป็นหายก็ได้ — รายงานดูจากประวัติเล่มเก่าประกอบ
        'replace' => 'สมุดเสีย/ชำรุด — ออกใหม่ทดแทน',   // ชื่อปุ่มตามที่คลินิกขอ 2569-11-05
        'lost'    => 'ออกทดแทน — สมุดหาย',
        'spoiled' => 'สมุดเสีย (เล่มเปล่าของเราเขียนผิด) — ออกเล่มใหม่แทน',
        'none'    => 'ไม่ใช้สมุดครั้งนี้',
    ];

    public function save(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        if ((int) $visit->clinic !== 3) { return response()->json(['ok' => false, 'message' => 'ใช้ได้เฉพาะ visit คลินิก TMC'], 422); }
        $patient = Patient::findOrFail($visit->patient_id);

        // ขีดซ้อน "2411--019074" (ขีดที่ระบบเติม + พิมพ์ขีดเอง) ยุบเหลือขีดเดียว — กันหลุดมาจากเครื่องที่ยังไม่ได้ F5 (2569-11-05)
        $clean = fn ($k, $len) => mb_substr(preg_replace('/-{2,}/', '-', trim((string) $request->input($k))), 0, $len);
        $action = (string) $request->input('action');
        $no     = $clean('no', 50);
        $oldNo  = $clean('old_no', 50);
        $src    = in_array($request->input('old_src'), ['ours', 'other'], true) ? $request->input('old_src') : null;
        // เล่มเปล่าที่เขียนเสียไปก่อนได้เล่มที่ให้คนไข้ (คลินิกเจอ 2569-11-06 — ออกเล่มแรกแต่เขียนผิด 2 เล่ม)
        // พิมพ์หลายเลขได้ คั่นเว้นวรรค/จุลภาค/ขึ้นบรรทัด · ทุกเล่มเข้ารายการส่งทำลาย
        // ใช้เฉพาะปุ่ม "สมุดเสีย/ชำรุด — ออกใหม่ทดแทน" (คลินิกขอ 2569-11-06: ออกเล่มใหม่/สมุดหาย ไม่ต้องมี)
        // หน้าจอส่งมาเป็นเล่มละช่อง: เล่มแรก = เลขเล่มที่เสีย/ชำรุด (old_no เดิม) · เล่มถัดไป = เล่มเสียเพิ่ม
        // ช่องเดียวพิมพ์ 2 เลขเว้นวรรค (รุ่นก่อน — เจอจริง visit 13280) ก็แยกให้แบบเดียวกัน
        $spoiledNos = [];
        if ($action === 'replace') {
            $all = [];
            foreach (preg_split('/[\s,;]+/u', $oldNo . ' ' . (string) $request->input('spoiled_nos')) as $x) {
                $x = mb_substr(preg_replace('/-{2,}/', '-', trim($x)), 0, 30);
                if ($x !== '' && ! in_array($x, $all, true)) { $all[] = $x; }
            }
            $oldNo = (string) array_shift($all);
            $spoiledNos = Visit::bookSpoiledReady() ? $all : [];
        }
        // สมุดหาย: คนไข้จำ/มีรูปเลขเล่มเดิม หรือหาเลขไม่ได้เลย
        $lostNoKnown = $request->input('lost_known') !== '0';
        if ($action === 'lost' && ! $lostNoKnown) { $oldNo = ''; }

        if ($action !== '' && ! isset(self::ACTIONS[$action])) { return $this->fail('เลือกรายการสมุดเหลืองไม่ถูกต้อง'); }
        $need = [
            'old'     => ['no' => 'เลขเล่มที่ใช้', 'src' => 'เล่มนี้ออกโดยใคร'],
            'new'     => ['no' => 'เลขเล่มใหม่'],
            'replace' => ['no' => 'เลขเล่มใหม่ที่ออกให้', 'old_no' => 'เลขเล่มที่เสีย/ชำรุด (ใช้ลงรายการส่งทำลาย)', 'src' => 'เล่มเดิมออกโดยใคร'],
            'lost'    => array_merge(['no' => 'เลขเล่มใหม่ที่ออกให้'], $lostNoKnown ? ['old_no' => 'เลขเล่มที่หาย (หรือเลือก "หาเลขเดิมไม่ได้")'] : []),
            'spoiled' => ['no' => 'เลขเล่มใหม่ที่ออกให้', 'old_no' => 'เลขเล่มที่เสีย'],
        ][$action] ?? [];
        foreach ($need as $f => $label) {
            $v = ['no' => $no, 'old_no' => $oldNo, 'src' => $src][$f];
            if ($v === '' || $v === null) { return $this->fail('กรุณากรอก: ' . $label); }
        }
        if (in_array($action, ['new', 'replace', 'lost', 'spoiled'], true) && $oldNo !== '' && $oldNo === $no) {
            return $this->fail('เลขเล่มใหม่ซ้ำกับเลขเล่มเดิม');
        }
        if (in_array($no, $spoiledNos, true)) { return $this->fail('เลขเล่มที่ให้คนไข้ ซ้ำกับเล่มที่เขียนเสีย'); }
        if (mb_strlen(implode(',', $spoiledNos)) > 250) { return $this->fail('เล่มที่เขียนเสียยาวเกินไป'); }

        // วันที่ออก/ใช้เล่ม (ลงย้อนหลังได้ — คลินิกขอ 2569-11-04) · ว่าง = วันที่ของ visit นี้ · ห้ามเป็นวันอนาคต
        $dateBe = (string) $visit->getRawOriginal('date');
        if (trim((string) $request->input('date')) !== '') {
            $r = \App\Support\ThaiDate::parse($request->input('date'), ['notFuture' => true]);
            if (! $r['ok']) { return $this->fail('วันที่: ' . $r['error']); }
            $dateBe = $r['be'];
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateBe)) { $dateBe = \App\Support\ThaiDate::todayBe(); }

        $cur  = trim((string) $patient->yellow_book_no);
        $list = self::histList($patient->yellow_book_history);

        // ---- ยกเลิกผลของการบันทึกรอบก่อนใน visit เดียวกัน (เลือกผิดแล้วเปลี่ยนข้อ — คลินิกเจอ 2569-11-04) ----
        // ไม่ยกเลิก = กด "ทดแทน" แล้วเปลี่ยนเป็น "สมุดเสีย" ประวัติขึ้นทั้งสองอย่างของเล่มเดียวกัน
        $pa = (string) $visit->tmc_book_action; $pn = (string) $visit->tmc_book_no; $po = (string) $visit->tmc_book_old_no;
        // เล่มเขียนเสียของรอบก่อน — เอาออกจากประวัติ แล้วค่อยใส่ใหม่ตามที่กรอกรอบนี้
        if (Visit::bookSpoiledReady()) {
            foreach (array_filter(explode(',', (string) $visit->tmc_book_spoiled_nos)) as $ps) {
                $list = array_values(array_filter($list, fn ($x) => ! str_starts_with($x, 'เล่ม ' . $ps . ' — เสีย')));
            }
        }
        if (in_array($pa, ['replace', 'lost'], true)) {
            // หายแบบหาเลขไม่ได้ เขียนไว้เป็น "เล่มเก่า ไม่ทราบเลข"
            $pk = $po !== '' ? $po : 'ไม่ทราบเลข';
            $list = array_values(array_filter($list, fn ($x) => ! (str_starts_with($x, 'เล่มเก่า ' . $pk . ' ') && preg_match('/— (ชำรุด|หาย)/u', $x))));
            if ($cur === $pn && $po !== '') { $cur = $po; }
        } elseif ($pa === 'spoiled' && $po !== '') {
            $list = array_values(array_filter($list, fn ($x) => ! str_starts_with($x, 'เล่ม ' . $po . ' — เสีย')));
            if ($cur === $pn) { $cur = $po; }
        } elseif (in_array($pa, ['old', 'new'], true) && $cur === $pn && $list) {
            // รอบก่อนเปลี่ยนเลขเล่ม → เก็บเลขเก่าไว้ท้ายประวัติแบบ "เล่มเก่า X (วันที่)" (ไม่มีเหตุผล) — ดึงกลับมา
            $last = end($list);
            if (preg_match('/^เล่มเก่า (\S+) \(\d{2}\/\d{2}\/\d{4}\)$/u', $last, $m)) { array_pop($list); $cur = $m[1]; }
        }

        // ---- บันทึกที่ visit (รายงานนับจากตรงนี้) ----
        // ชื่อสถานที่ออกเล่มเอาออกแล้ว — คนไข้มักบอกไม่ได้ว่าเล่มมาจากไหน (คลินิกขอ 2569-11-04)
        $visit->forceFill([
            'tmc_book_action'    => $action ?: null,
            'tmc_book_no'        => in_array($action, ['old', 'new', 'replace', 'lost', 'spoiled'], true) ? $no : null,
            'tmc_book_old_no'    => in_array($action, ['replace', 'lost', 'spoiled'], true) ? ($oldNo ?: null) : null,
            'tmc_book_old_src'   => in_array($action, ['old', 'replace', 'lost'], true) ? $src : ($action === 'spoiled' ? 'ours' : null),
            'tmc_book_old_place' => null,
        ] + (Visit::bookSpoiledReady() ? ['tmc_book_spoiled_nos' => $spoiledNos ? implode(',', $spoiledNos) : null] : []))->save();

        // ---- เลขเล่มปัจจุบัน + ประวัติของคนไข้ ----
        $date = '(' . \App\Support\ThaiDate::show($dateBe) . ')';
        $add = function (string $entry) use (&$list) {
            if ($entry !== '' && ! in_array($entry, $list, true)) { $list[] = $entry; }
        };
        $from = $src === 'other' ? ' (เล่มจากที่อื่น)' : '';
        if ($action === 'replace') {
            $add('เล่มเก่า ' . ($oldNo ?: ($cur ?: 'ไม่ทราบเลข')) . $from . ' — ชำรุด ' . $date);
        } elseif ($action === 'lost') {
            // หาเลขไม่ได้ = ไม่เอาเลขเล่มปัจจุบันในระบบมาใส่แทน — ถ้าในระบบมีเลขอยู่ คนไข้น่าจะบอกเลขได้ ให้พยาบาลเลือกเอง
            $add('เล่มเก่า ' . ($oldNo ?: 'ไม่ทราบเลข') . $from . ' — หาย ' . $date);
        } elseif ($action === 'spoiled') {
            $add('เล่ม ' . $oldNo . ' — เสีย ' . $date);
        } elseif (in_array($action, ['old', 'new'], true) && $cur !== '' && $cur !== $no) {
            $add('เล่มเก่า ' . $cur . ' ' . $date);
        }
        foreach ($spoiledNos as $sx) { $add('เล่ม ' . $sx . ' — เสีย ' . $date); }
        if (in_array($action, ['old', 'new', 'replace', 'lost', 'spoiled'], true)) { $cur = $no; }

        $patient->yellow_book_no = $cur !== '' ? $cur : null;
        $patient->yellow_book_history = $list ? implode(' | ', $list) : null;
        if ($patient->isDirty()) { $patient->save(); }

        return response()->json([
            'ok'      => true,
            'no'      => $patient->yellow_book_no,
            'history' => $patient->yellow_book_history,
            'message' => $action && $action !== 'none' ? 'บันทึกแล้ว: ' . self::ACTIONS[$action]
                : ($pa && $pa !== 'none' ? 'ยกเลิกรายการเดิมของครั้งนี้แล้ว (' . (self::ACTIONS[$pa] ?? $pa) . ')' : 'บันทึกแล้ว: ไม่ใช้สมุดครั้งนี้'),
        ]);
    }

    /**
     * ลบประวัติเล่มเก่าทีละรายการ (ลงเกิน/ผิด — คลินิกขอ 2569-11-04)
     * ส่งข้อความของรายการมาเทียบตรงตัว กันลบผิดรายการถ้ามีคนแก้ประวัติไปพร้อมกัน
     */
    public function deleteHistory(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $patient = Patient::findOrFail($visit->patient_id);
        $entry = trim((string) $request->input('entry'));
        $list = self::histList($patient->yellow_book_history);
        $i = array_search($entry, $list, true);
        if ($entry === '' || $i === false) { return $this->fail('ไม่พบรายการนี้ในประวัติ (อาจถูกแก้ไปแล้ว) — กด F5 แล้วลองใหม่'); }
        array_splice($list, $i, 1);
        $patient->yellow_book_history = $list ? implode(' | ', $list) : null;
        $patient->save();
        return response()->json(['ok' => true, 'no' => $patient->yellow_book_no, 'history' => $patient->yellow_book_history, 'message' => 'ลบรายการประวัติแล้ว']);
    }

    /** ประวัติเล่มเก่าเก็บเป็นข้อความคั่น " | " → รายการ */
    public static function histList($hist): array
    {
        return array_values(array_filter(array_map('trim', explode('|', (string) $hist)), fn ($x) => $x !== ''));
    }

    private function fail(string $msg)
    {
        return response()->json(['ok' => false, 'message' => $msg], 422);
    }
}
