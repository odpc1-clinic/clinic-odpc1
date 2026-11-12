<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\TbRegistration;
use App\Models\TbSputumExam;
use App\Support\ThaiDate;
use App\Utillity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ทะเบียนวัณโรค (บัตร TB 01) — คลินิก TB
 *
 * แยกจากหน้าตรวจ (visits) โดยตั้งใจ: การรักษาวัณโรคหนึ่งคอร์สยาว 6 เดือน–2 ปี
 * พยาบาลทำงานกับ "บัตรของคนไข้" ไม่ใช่ "การมาครั้งนี้" — เปิดบัตรค้างไว้แล้วกรอกทีละเดือน
 *
 * เลขทะเบียน (tb_no) พยาบาลคีย์เองจาก NTIP ระบบไม่ได้เชื่อมกับ NTIP
 */
class TbController extends Controller
{
    // กล่อง "สั่งยาครั้งนี้" ในหน้าตรวจ OPD — ใช้ตัวช่วยของกล่องในบัตรร่วมกัน (2569-10-05)
    use Concerns\TbVisitOrder;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * หน้ารวมคนไข้ DOT รายวัน — ไม่ต้องเปิด visit
     *
     * พยาบาลเปิดหน้านี้วันละครั้ง เห็นรายชื่อคนไข้ที่ยังต้องกำกับกินยา พร้อมสูตรยา
     * แล้วติ๊กว่าวันนี้ใครกินยาแล้ว · เลือกวันย้อนหลังได้ (เสาร์-อาทิตย์เซิร์ฟเวอร์ปิด
     * พอเปิดวันจันทร์ต้องลงย้อนให้ครบ) — คลินิกขอ 2569-09-28
     */
    public function dotBoard(Request $request)
    {
        $date = $request->filled('date')
            ? $this->dotDate($request->input('date'))
            : \App\Models\VisitAppointment::todayBe();
        if (! $date) { $date = \App\Models\VisitAppointment::todayBe(); }

        $view = $request->input('view') === 'off' ? 'off' : 'on';   // on = กำลัง DOT · off = พักไว้

        $all = TbRegistration::with('patient')
            ->whereNull('outcome')
            ->where('dot_active', $view === 'off' ? 0 : 1)
            ->get();
        // DOT โดย อสม./รพ.สต. ไม่แสดงในหน้านี้ — ดูในบัตร TB 01 (คลินิกขอ 2569-09-30)
        $communityCount = $all->filter->isCommunityDot()->count();
        $rows = $all->reject->isCommunityDot()
            ->sortBy(fn ($r) => (optional($r->patient)->name ?? '') . (optional($r->patient)->surname ?? ''))
            ->values();
        TbRegistration::preloadLatestDoses($rows);

        // สัญลักษณ์ของวันที่เลือก (ช่องเช้าอย่างเดียว slot 0 — MDR เช้า/เย็นค่อยเปิดทีหลัง)
        $marks = \App\Models\TbDotDay::whereIn('tb_registration_id', $rows->pluck('id'))
            ->where('dot_date', $date)->where('slot', 0)
            ->get()->keyBy('tb_registration_id');

        // หลักฐานที่คนไข้ส่งเข้ามาของวันนั้น (Video DOT) — พยาบาลดูแล้วค่อยติ๊ก
        // ดึงของคนไข้ที่ยังรักษาอยู่ "ทุกคน" ไม่ใช่เฉพาะที่อยู่ในตารางหน้านี้
        // เพราะคนที่พัก DOT ไว้ก็ยังส่งคลิปเข้ามาได้ ถ้าไม่โชว์ไว้พยาบาลจะไม่รู้เลย (2569-09-29)
        $activeIds = TbRegistration::whereNull('outcome')->pluck('id');
        $subs = \App\Models\TbDotSubmission::whereIn('tb_registration_id', $activeIds)
            ->where('dot_date', $date)
            ->orderBy('received_at')
            ->get()->groupBy('tb_registration_id');

        // คนที่พัก DOT ไว้แต่ส่งหลักฐานมา — ขึ้นเตือนไว้ที่หัวตาราง
        $offButSent = TbRegistration::with('patient')
            ->whereNull('outcome')->where('dot_active', 0)
            ->whereIn('id', $subs->keys())
            ->get();

        // แท็บที่เปิดอยู่นับจาก $all ที่โหลดแล้ว ไม่ต้องดึงซ้ำ
        $other  = TbRegistration::whereNull('outcome')->where('dot_active', $view === 'off' ? 1 : 0)->get()->reject->isCommunityDot()->count();
        $counts = [
            'on'  => $view === 'off' ? $other : $rows->count(),
            'off' => $view === 'off' ? $rows->count() : $other,
        ];

        // แยกสองกลุ่มตามตารางที่พยาบาลใช้: กินยาทุกวัน (TB) กับสัปดาห์ละครั้ง (TPT 3HP)
        $groups = [
            TbRegistration::DOT_DAILY  => $rows->where('dot_frequency', '!=', TbRegistration::DOT_WEEKLY)->values(),
            TbRegistration::DOT_WEEKLY => $rows->where('dot_frequency', TbRegistration::DOT_WEEKLY)->values(),
        ];

        // เรื่องส่งเวรที่ยังไม่สิ้นสุด — โชว์ทุกครั้งที่เปิดหน้า ไม่ขึ้นกับวันที่เลือก (2569-09-30)
        $handovers = \App\Models\TbDotHandover::with('creator')
            ->whereIn('tb_registration_id', $rows->pluck('id'))->whereNull('resolved_at')
            ->orderBy('created_at')->get()->groupBy('tb_registration_id');

        // วันแรกที่ลง DOT ในระบบของแต่ละคน — ก่อนหน้านั้นไม่เตือนว่า "ไม่ได้บันทึก" (ตรงกับปฏิทินในบัตร)
        $dotSince = \App\Models\TbDotDay::whereIn('tb_registration_id', $rows->pluck('id'))->whereNotNull('mark')
            ->where('dot_date', '>=', '2500-01-01')
            ->groupBy('tb_registration_id')->selectRaw('tb_registration_id, MIN(dot_date) AS since')
            ->pluck('since', 'tb_registration_id');

        // นัดครั้งต่อไปของคลินิกวัณโรค + ต้องตรวจอะไรก่อนพบแพทย์ (พยาบาลขอ 2569-09-30)
        // เอานัดที่ยังเปิดอยู่และยังไม่ถึง นับจากวันที่เปิดดู — เรียงวันแล้วเอาตัวแรกของแต่ละคน
        $nextAppts = \App\Models\VisitAppointment::query()
            ->join('visits', 'visits.id', '=', 'visit_appointments.visit_id')
            ->where('visits.clinic', 2)
            ->whereIn('visit_appointments.patient_id', $rows->pluck('patient_id'))
            ->whereNull('visit_appointments.closed_at')
            ->where('visit_appointments.appointment_date', '>=', $date)
            ->orderBy('visit_appointments.appointment_date')->orderBy('visit_appointments.appt_time')
            ->get(['visit_appointments.*'])
            ->groupBy('patient_id')->map->first();

        // รายชื่อเจ้าหน้าที่ ไว้เลือกเป็นผู้รับผิดชอบเคส (พิมพ์ชื่ออื่นได้)
        $staffNames = \App\Models\User::orderBy('name')->pluck('name')->filter()->unique()->values();

        // วันในสัปดาห์ของวันที่เลือก (1 = จันทร์) ใช้ไฮไลต์ว่ากลุ่มรายสัปดาห์ถึงคิวหรือยัง
        $weekday = (int) date('N', strtotime(Utillity::BE2AD($date)));

        return view('tb.dot_board', compact('rows', 'groups', 'marks', 'subs', 'offButSent',
                                            'date', 'view', 'counts', 'weekday', 'handovers', 'staffNames', 'dotSince', 'nextAppts', 'communityCount'));
    }

    /** หมายเหตุ DOT — ว่าง = null · ห้ามใช้ ?: เพราะ "0" ถูกมองเป็นค่าว่างแล้วหาย (รีวิว 2569-09-30) */
    private static function dotNote($v): ?string
    {
        $v = mb_substr(trim((string) $v), 0, 255);
        return $v === '' ? null : $v;
    }

    /** บันทึกการติ๊กทั้งหน้าในครั้งเดียว + ปิด/เปิด DOT ของคนไข้ */
    public function saveDotBoard(Request $request)
    {
        $date = $this->dotDate($request->input('date', ''));
        if (! $date) {
            flash('วันที่ไม่ถูกต้อง', 'danger');
            return redirect()->route('tb.dotBoard');
        }

        $marks = (array) $request->input('mark', []);
        $notes = (array) $request->input('note', []);
        $valid = array_keys(\App\Models\TbDotDay::$markOption);
        $saved = $cleared = 0;

        // วนตามทุกคนที่ฟอร์มส่งมา ไม่ว่าจะส่งสัญลักษณ์หรือหมายเหตุ
        foreach (array_unique(array_merge(array_keys($marks), array_keys($notes))) as $regId) {
            $reg = TbRegistration::find((int) $regId);
            if (! $reg) { continue; }

            $mark = trim((string) ($marks[$regId] ?? ''));
            if (! in_array($mark, $valid, true)) { $mark = ''; }
            $row  = \App\Models\TbDotDay::where('tb_registration_id', $reg->id)
                ->where('dot_date', $date)->where('slot', 0)->first();

            // หมายเหตุ (2569-09-29) — ฟอร์มไม่ได้ส่งช่องนี้มา = คงของเดิม ไม่ล้างทิ้ง
            $note = array_key_exists($regId, $notes)
                ? self::dotNote($notes[$regId])
                : optional($row)->note;

            // ไม่มีทั้งสัญลักษณ์และหมายเหตุ = ไม่เหลืออะไรของวันนั้น ลบแถวทิ้ง
            // มีแค่หมายเหตุ = เก็บแถวไว้โดยไม่มีสัญลักษณ์ ไม่งั้นหมายเหตุที่พยาบาลพิมพ์จะหาย
            if ($mark === '' && $note === null) {
                if ($row) { $row->delete(); $cleared++; }
                continue;
            }

            // ระยะ/เหตุผล DOT ของวันนั้น เก็บไว้ออกรายงาน — ถ้าแก้ช่องเหตุผลในรอบบันทึกเดียวกัน ใช้ค่าใหม่
            // หน้าจอส่ง reason มาเฉพาะตอนแก้จริง (JS ตัดช่องที่ไม่เปลี่ยนทิ้ง) · วันที่บันทึกไว้แล้วคงเหตุผลเดิมของวันนั้น
            // ไม่งั้นแค่แก้ติ๊กย้อนหลังก็ทับเหตุผลของวันเก่าด้วยค่าปัจจุบัน รายงานเพี้ยน (รีวิว 2569-09-30)
            $reason = array_key_exists('reason', (array) $request->input("plan.$regId", []))
                ? (trim((string) $request->input("plan.$regId.reason")) ?: null)
                : (optional($row)->dot_reason ?: ($reg->dot_reason ?: null));
            $fields = ['mark' => $mark ?: null, 'note' => $note, 'dot_reason' => $reason];
            if ($row) {
                $row->fill($fields);
                if ($row->isDirty()) { $row->save(); }
            } else {
                \App\Models\TbDotDay::create([
                    'tb_registration_id' => $reg->id,
                    'dot_date' => $date,
                    'phase'    => $reg->dotPhaseFor($date),
                    'slot'     => 0,
                ] + $fields);
            }
            if ($mark === '') { continue; }
            // ติ๊กว่ากินยา = พยาบาลดูหลักฐานของวันนั้นแล้ว — ลงชื่อผู้ยืนยันไว้เป็นหลักฐานว่าใครตรวจ
            if ($mark === 'taken') {
                \App\Models\TbDotSubmission::where('tb_registration_id', $reg->id)
                    ->where('dot_date', $date)->whereNull('confirmed_at')
                    ->update(['confirmed_by' => Auth::id(), 'confirmed_at' => now()]);
            }

            $saved++;
        }

        // แผน DOT ของแต่ละคน (เหตุผล · ช่องทาง · เวลานัด · ความถี่ · สูตรยาที่เขียนในตาราง)
        // แก้ตรงหน้าทะเบียนได้เลย เพราะพยาบาลดูแลตารางนี้เป็นรายเดือน
        $plans = 0;
        foreach ((array) $request->input('plan', []) as $regId => $p) {
            $reg = TbRegistration::find((int) $regId);
            if (! $reg) { continue; }

            // เขียนเฉพาะช่องที่ส่งมา — หน้าจอตัดช่องที่ไม่ได้แก้ทิ้งก่อนส่ง (เหมือน _touched ของหน้าตรวจ)
            // แท็บที่เปิดค้างไว้จะได้ไม่ทับช่องทาง/เหตุผลที่อีกเครื่องเพิ่งแก้ (รีวิว 2569-09-30)
            // JS พัง = ส่งมาครบทุกช่อง = เซฟทั้งแถวแบบเดิม
            $p = (array) $p;
            $upd = [];
            foreach (['reason' => 'dot_reason', 'channel' => 'dot_channel', 'time' => 'dot_time', 'regimen_text' => 'dot_regimen_text'] as $in => $col) {
                if (array_key_exists($in, $p)) { $upd[$col] = trim((string) $p[$in]) ?: null; }
            }
            if (array_key_exists('owner', $p)) { $upd['dot_owner'] = mb_substr(trim((string) $p['owner']), 0, 100) ?: null; }
            if (array_key_exists('frequency', $p)) {
                $weekly = (int) $p['frequency'] === TbRegistration::DOT_WEEKLY;
                $upd['dot_frequency'] = $weekly ? TbRegistration::DOT_WEEKLY : TbRegistration::DOT_DAILY;
                $upd['dot_weekday']   = $weekly ? ((int) ($p['weekday'] ?? $reg->dot_weekday) ?: null) : null;
            } elseif (array_key_exists('weekday', $p)) {
                $upd['dot_weekday'] = $reg->isWeeklyDot() ? ((int) $p['weekday'] ?: null) : null;
            }
            if (! $upd) { continue; }
            $reg->fill($upd);
            if ($reg->isDirty()) { $reg->save(); $plans++; }
        }

        // เปิด/ปิด DOT ย้ายไปทำที่หน้าตรวจ (saveDotStatus) — หน้านี้ไม่รับ off[] / back[] แล้ว (2569-09-30)

        flash('บันทึก DOT วันที่ ' . Utillity::db2thDate($date) . ' แล้ว — ติ๊ก ' . $saved . ' ราย'
            . ($cleared ? " · ล้าง {$cleared} ราย" : ''), 'success');

        return redirect()->route('tb.dotBoard', [
            'date' => Utillity::db2thDate($date),
            'view' => $request->input('view') === 'off' ? 'off' : 'on',
        ]);
    }
    /** รายงานประจำเดือนคลินิกวัณโรค — ตัวเลขคิดใน App\Services\TbMonthlyReport */
    public function monthlyReport(Request $request)
    {
        $today = \App\Models\VisitAppointment::todayBe();
        $ym    = (string) $request->input('month', substr($today, 0, 7));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym)) { $ym = substr($today, 0, 7); }

        // ตัวเลือกเดือน: ย้อนหลัง 24 เดือน
        $thMonth = \App\Utillity::TH_MONTH_SHORT;
        [$y, $m] = array_map('intval', explode('-', substr($today, 0, 7)));
        $months = [];
        for ($i = 0; $i < 24; $i++) {
            $months[sprintf('%04d-%02d', $y, $m)] = $thMonth[$m] . ' ' . $y;
            if (--$m === 0) { $m = 12; $y--; }
        }

        $report   = (new \App\Services\TbMonthlyReport())->build($ym);
        $ids      = collect(array_merge($report['esm']['rows'], $report['month']['rows']))->pluck('patients')->flatten()->unique()->filter();
        $patients = \App\Models\Patient::whereIn('id', $ids)->get(['id', 'code', 'name'])->keyBy('id');

        return view('tb.report', compact('report', 'ym', 'months', 'patients'));
    }

    /**
     * วันที่ของหน้า DOT (วว/ดด/ปปปป พ.ศ.) -> Y-m-d พ.ศ.
     * พิมพ์ปีเป็น ค.ศ. มา (เช่น 2026) แปลงเป็น พ.ศ. ให้ — เคยมีแถวหลุดเป็น 2026-09-28 แล้ว
     * รูปแบบผิดคืน null (th2dbDate เดิมพังทั้งหน้าถ้าพิมพ์มั่ว)
     */
    private function dotDate($value): ?string
    {
        // ใช้ตัวอ่านกลางของงาน TB (2569-10-05) — กติกาเดียวกับบัตร: ค.ศ. แปลงให้ · วันไม่มีจริง/ปีนอกช่วง = null
        // ไม่ใส่ notFuture: หน้า DOT เลื่อนดูวันถัดไปได้ และหมายเหตุล่วงหน้า (เช่น "ไปต่างจังหวัด") เป็นงานปกติ
        $r = ThaiDate::parse($value);
        return $r['ok'] ? $r['be'] : null;
    }

    /**
     * หมายเหตุรายวันของหน้าติดตาม DOT — บันทึกทีละช่องทันทีที่พิมพ์ (2569-09-30)
     *
     * พยาบาลแจ้งว่าไม่มั่นใจว่าพิมพ์แล้วต้องกดบันทึกไหม และพิมพ์ไว้แล้วออกไปดูประวัติ กลับมาข้อความหาย
     * จึงให้ช่องนี้บันทึกเองแยกจากปุ่มบันทึกทั้งหน้า · แตะเฉพาะ note ไม่ยุ่งกับสัญลักษณ์ของวันนั้น
     */
    public function saveDotNote(Request $request)
    {
        $reg  = TbRegistration::find((int) $request->input('reg'));
        $date = $this->dotDate($request->input('date', ''));
        if (! $reg || ! $date) {
            return response()->json(['ok' => false, 'message' => 'ข้อมูลไม่ครบ'], 422);
        }
        $note = self::dotNote($request->input('note', ''));

        $row = \App\Models\TbDotDay::where('tb_registration_id', $reg->id)
            ->where('dot_date', $date)->where('slot', 0)->first();

        if ($row) {
            // ลบหมายเหตุจนว่าง และวันนั้นไม่มีสัญลักษณ์ = ไม่เหลืออะไร ลบแถวทิ้ง
            if ($note === null && ! $row->mark) { $row->delete(); }
            else { $row->update(['note' => $note]); }
        } elseif ($note !== null) {
            \App\Models\TbDotDay::create([
                'tb_registration_id' => $reg->id,
                'dot_date' => $date,
                'phase'    => $reg->dotPhaseFor($date),
                'slot'     => 0,
                'mark'     => null,
                'note'     => $note,
                'dot_reason' => $reg->dot_reason ?: null,
            ]);
        }

        return response()->json(['ok' => true, 'saved_at' => now()->format('H:i')]);
    }

    /**
     * ติ๊กสัญลักษณ์ของวันนั้นในหน้า DOT — บันทึกเองทันทีที่กด ไม่ต้องรอปุ่มบันทึก (พยาบาลขอ 2569-10-01)
     * กติกาเดียวกับ saveDotBoard: หมายเหตุเดิมคงไว้ · ไม่มีทั้งสัญลักษณ์และหมายเหตุ = ลบแถว
     * แถวเดิมคงระยะ DOT ของวันนั้น · แถวใหม่ใช้ระยะปัจจุบันของบัตร · ติ๊ก ✓ = ลงชื่อยืนยันหลักฐานที่คนไข้ส่งมา
     */
    public function saveDotMark(Request $request)
    {
        $reg  = TbRegistration::find((int) $request->input('reg'));
        $date = $this->dotDate($request->input('date', ''));
        if (! $reg || ! $date) {
            return response()->json(['ok' => false, 'message' => 'ข้อมูลไม่ครบ'], 422);
        }
        $mark = trim((string) $request->input('mark', ''));
        if (! array_key_exists($mark, \App\Models\TbDotDay::$markOption)) { $mark = ''; }

        $row = \App\Models\TbDotDay::where('tb_registration_id', $reg->id)
            ->where('dot_date', $date)->where('slot', 0)->first();

        if ($mark === '' && (! $row || $row->note === null || $row->note === '')) {
            if ($row) { $row->delete(); }
        } elseif ($row) {
            $row->update(['mark' => $mark ?: null]);
        } else {
            \App\Models\TbDotDay::create([
                'tb_registration_id' => $reg->id,
                'dot_date'   => $date,
                'phase'      => $reg->dotPhaseFor($date),
                'slot'       => 0,
                'mark'       => $mark,
                'dot_reason' => $reg->dot_reason ?: null,
            ]);
        }
        if ($mark === 'taken') {
            \App\Models\TbDotSubmission::where('tb_registration_id', $reg->id)
                ->where('dot_date', $date)->whereNull('confirmed_at')
                ->update(['confirmed_by' => Auth::id(), 'confirmed_at' => now()]);
        }

        return response()->json(['ok' => true, 'saved_at' => now()->format('H:i')]);
    }

    /** เพิ่มเรื่องส่งเวร — เรียกจากหน้า DOT ด้วย fetch (อยู่ในฟอร์มบันทึกทั้งหน้า ห้ามฟอร์มซ้อน) */
    public function addDotHandover(Request $request)
    {
        $reg  = TbRegistration::find((int) $request->input('reg'));
        $note = mb_substr(trim((string) $request->input('note', '')), 0, 500);
        if (! $reg || $note === '') {
            return response()->json(['ok' => false, 'message' => 'พิมพ์เรื่องที่จะส่งเวรก่อน'], 422);
        }
        // วันที่ของหน้าที่เปิดอยู่ (เปิดย้อนดูวันที่ 29 แล้วส่งเวร = เรื่องของวันที่ 29 · คลินิกแจ้ง 2569-10-30) · ไม่ส่งมา/ผิด = วันนี้
        $date = $this->dotDate($request->input('date', '')) ?: \App\Models\VisitAppointment::todayBe();
        $h = \App\Models\TbDotHandover::create([
            'tb_registration_id' => $reg->id, 'dot_date' => $date, 'note' => $note, 'created_by' => Auth::id(),
        ]);

        return response()->json(['ok' => true, 'id' => $h->id, 'note' => $h->note, 'for' => $h->fresh()->forDateText(),
            'by' => optional(Auth::user())->name, 'at' => now()->format('d/m H:i'),
            'resolve_url' => route('tb.dotHandover.resolve', $h->id), 'update_url' => route('tb.dotHandover.update', $h->id)]);
    }

    /** แก้ข้อความส่งเวร — พิมพ์ผิดตอนรีบกดส่ง (พยาบาลขอ 2569-10-07) · ประวัติการแก้อยู่ใน activity log */
    public function updateDotHandover(Request $request, $id)
    {
        $h = \App\Models\TbDotHandover::findOrFail($id);
        $note = mb_substr(trim((string) $request->input('note', '')), 0, 500);
        if ($note === '') {
            return response()->json(['ok' => false, 'message' => 'ข้อความว่างไม่ได้ — ถ้าเรื่องนี้จบแล้วให้กด "ปัญหาสิ้นสุด" แทน'], 422);
        }
        if ($note !== $h->note) {
            $h->update(['note' => $note]);
        }

        return response()->json(['ok' => true, 'note' => $h->note]);
    }

    /** ติ๊ก "ปัญหาสิ้นสุด" — เก็บไว้เป็นประวัติ ไม่ลบ */
    public function resolveDotHandover($id)
    {
        $h = \App\Models\TbDotHandover::findOrFail($id);
        if (! $h->resolved_at) {
            $h->update(['resolved_at' => now(), 'resolved_by' => Auth::id()]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * เปิด/ปิด DOT ของคนไข้ — จากแถบบัตร TB 01 ในหน้าตรวจ (2569-09-30)
     * เดิมอยู่ที่หน้าติดตาม DOT คลินิกขอให้คนกำหนดคือคนตรวจรักษาเท่านั้น
     */
    public function saveDotStatus(Request $request, $id)
    {
        $reg = TbRegistration::findOrFail($id);
        $on  = (string) $request->input('dot_active') === '1';

        // วันที่ปิด DOT ย้อนหลังได้ (คลินิกขอ 2569-10-26) — ปิดจากหน้าตรวจของวันก่อน ๆ เดิมได้วันนี้เสมอ
        // ไม่ส่งมา (หน้าเก่า/JS พัง) = วันนี้แบบเดิม · ผิดรูปแบบ/อนาคต = ไม่บันทึกอะไรเลย บอกเหตุผล
        $offDate = \App\Models\VisitAppointment::todayBe();
        $offSent = ! $on && trim((string) $request->input('dot_off_date', '')) !== '';
        if ($offSent) {
            $p = \App\Support\ThaiDate::parse($request->input('dot_off_date'), ['notFuture' => true]);
            if (! $p['ok']) {
                flash('ยังไม่ได้บันทึก DOT — วันที่ปิด ' . $p['error'], 'danger');
                return redirect()->back();
            }
            $offDate = min($p['be'], $offDate);
        }

        // ช่องทาง DOT แก้ที่นี่ได้ด้วย — คนไข้ที่ DOT โดย อสม./รพ.สต. ไม่อยู่ในหน้า DOT แล้วจะไม่มีที่อื่นให้แก้
        if ($request->has('dot_channel')) {
            $ch = mb_substr(trim((string) $request->input('dot_channel')), 0, 255) ?: null;
            if ($ch !== $reg->dot_channel) {
                $reg->update(['dot_channel' => $ch]);
                flash('บันทึกช่องทาง DOT แล้ว' . ($reg->isCommunityDot() ? ' — DOT โดย อสม./รพ.สต. จะไม่แสดงในหน้า DOT (ดูในบัตร TB 01)' : ''), 'success');
            }
        }
        // เหตุผลที่ DOT + ผู้รับผิดชอบ (กล่อง DOT ใต้สั่งยาในหน้าตรวจ 2569-10-14) — ช่องเดียวกับหน้าทะเบียน DOT
        $upd = [];
        if ($request->has('dot_reason')) { $upd['dot_reason'] = mb_substr(trim((string) $request->input('dot_reason')), 0, 255) ?: null; }
        if ($request->has('dot_owner'))  { $upd['dot_owner']  = mb_substr(trim((string) $request->input('dot_owner')), 0, 100) ?: null; }
        $upd = array_filter($upd, fn ($v, $k) => $v !== $reg->$k, ARRAY_FILTER_USE_BOTH);
        if ($upd) {
            $reg->update($upd);
            flash('บันทึก' . implode(' / ', array_map(fn ($k) => $k === 'dot_reason' ? 'เหตุผลที่ DOT' : 'ผู้รับผิดชอบ', array_keys($upd))) . ' แล้ว', 'success');
        }
        // ปิด DOT อยู่แล้ว แก้เหตุผล / วันที่ปิด
        if (! $on && ! $reg->dot_active) {
            $fix = [];
            if ($request->has('dot_off_reason')) { $fix['dot_off_reason'] = mb_substr(trim((string) $request->input('dot_off_reason')), 0, 255) ?: null; }
            if ($offSent) { $fix['dot_off_date'] = $offDate; }
            $fix = array_filter($fix, fn ($v, $k) => $v !== $reg->$k, ARRAY_FILTER_USE_BOTH);
            if ($fix) {
                $reg->update($fix);
                flash('บันทึก' . implode(' / ', array_map(fn ($k) => $k === 'dot_off_date' ? 'วันที่ปิด DOT (' . \App\Support\ThaiDate::show($offDate) . ')' : 'เหตุผลที่ปิด DOT', array_keys($fix))) . ' แล้ว', 'success');
            }
        }

        if ($on && ! $reg->dot_active) {
            $reg->update(['dot_active' => 1, 'dot_off_date' => null, 'dot_off_reason' => null, 'dot_off_by' => null]);
            flash('เปิด DOT แล้ว — คนไข้ขึ้นในหน้าติดตาม DOT', 'success');
        } elseif (! $on && $reg->dot_active) {
            $reg->update([
                'dot_active'     => 0,
                'dot_off_date'   => $offDate,
                'dot_off_reason' => mb_substr(trim((string) $request->input('dot_off_reason', '')), 0, 255) ?: null,
                'dot_off_by'     => Auth::id(),
            ]);
            flash('ปิด DOT แล้ว (ตั้งแต่ ' . \App\Support\ThaiDate::show($offDate) . ') — ย้ายไปอยู่แท็บ "พัก DOT ไว้" ยังนัด F/U ได้ตามปกติ', 'success');
        }

        return redirect()->back();
    }

    /** ทะเบียนรวม — ค่าเริ่มต้นแสดงเฉพาะที่ยังรักษาอยู่ เพราะเป็นงานประจำวัน */
    public function index(Request $request)
    {
        $type   = (string) $request->input('type', '');        // '' = ทุกประเภท
        $status = (string) $request->input('status', 'active'); // active | done | all
        $search = trim((string) $request->input('search', ''));

        $q = TbRegistration::with('patient')->orderByDesc('id');

        if ($type !== '') {
            $q->where('registry_type', (int) $type);
        }
        if ($status === 'active') {
            $q->whereNull('outcome');
        } elseif ($status === 'done') {
            $q->whereNotNull('outcome');
        }
        if ($search !== '') {
            // ค้นจากเลขทะเบียน TB, HN หรือชื่อ-สกุลคนไข้
            $q->where(function ($w) use ($search) {
                $w->where('tb_no', 'like', "%{$search}%")
                  ->orWhereIn('patient_id', Patient::where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('surname', 'like', "%{$search}%")
                        ->pluck('id'));
            });
        }

        $rows = $q->paginate(30)->withQueryString();

        // วันนัดครั้งถัดไปของคนไข้ในหน้านี้ — ดึงทีเดียวทั้งหน้า ไม่ยิงทีละแถว
        // ใช้เฉพาะนัดที่ยังไม่ถูกปิด (scope open) ถ้าไม่มีนัดข้างหน้าแล้ว ให้โชว์นัดที่เลยมาแล้วเป็นตัวเตือน
        $today    = \App\Models\VisitAppointment::todayBe();
        $byPatient = \App\Models\VisitAppointment::open()
            ->whereIn('patient_id', $rows->pluck('patient_id')->unique())
            ->orderBy('appointment_date')
            ->get()->groupBy('patient_id');

        $nextAppt = $byPatient->map(function ($list) use ($today) {
            return $list->first(fn ($a) => $a->appointment_date >= $today) ?: $list->last();
        });

        // วันนัดครั้งถัดไปของคนไข้ในหน้านี้ — ดึงทีเดียวทั้งหน้า ไม่ยิงทีละแถว
        // ใช้เฉพาะนัดที่ยังไม่ถูกปิด (scope open) ถ้าไม่มีนัดข้างหน้าแล้ว ให้โชว์นัดที่เลยมาแล้วเป็นตัวเตือน
        $today    = \App\Models\VisitAppointment::todayBe();
        $byPatient = \App\Models\VisitAppointment::open()
            ->whereIn('patient_id', $rows->pluck('patient_id')->unique())
            ->orderBy('appointment_date')
            ->get()->groupBy('patient_id');

        $nextAppt = $byPatient->map(function ($list) use ($today) {
            return $list->first(fn ($a) => $a->appointment_date >= $today) ?: $list->last();
        });

        // ตัวเลขบนหัวข้อ — นับจากทะเบียนที่ยังรักษาอยู่ ให้ตรงกับที่พยาบาลนับในมือ
        $counts = [
            'all'  => TbRegistration::whereNull('outcome')->count(),
            1      => TbRegistration::whereNull('outcome')->where('registry_type', TbRegistration::TYPE_TB)->count(),
            2      => TbRegistration::whereNull('outcome')->where('registry_type', TbRegistration::TYPE_LTBI)->count(),
            3      => TbRegistration::whereNull('outcome')->where('registry_type', TbRegistration::TYPE_MDR)->count(),
            'done' => TbRegistration::whereNotNull('outcome')->count(),
        ];

        return view('tb.index', compact('rows', 'counts', 'type', 'status', 'search', 'nextAppt', 'today'));
    }

    /** เปิดบัตรใหม่ — มาจากหน้าคนไข้ (มี patient_id) หรือมาจากทะเบียนแล้วค้นคนไข้เอง */
    public function create(Request $request)
    {
        $patient = $request->filled('patient_id') ? Patient::find($request->input('patient_id')) : null;

        // ค้นคนไข้จากหน้านี้เลย (HN / ชื่อ / สกุล / เลขบัตร) — ไม่ต้องวิ่งไปหน้าทะเบียนคนไข้ก่อน
        $found = collect();
        $q = trim((string) $request->input('q', ''));
        if (! $patient && $q !== '') {
            $found = Patient::where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('surname', 'like', "%{$q}%")
                ->orWhere('id_card_number', 'like', "%{$q}%")
                ->orderByDesc('id')->limit(20)->get();
        }

        // ถ้าคนไข้รายนี้มีบัตรที่ยังรักษาอยู่ ให้เตือน จะได้ไม่เปิดซ้ำโดยไม่ตั้งใจ
        $openOne = $patient
            ? TbRegistration::where('patient_id', $patient->id)->whereNull('outcome')->first()
            : null;

        // มาจากปุ่ม "เปิดบัตร" ในใบคัดกรอง -> เติมข้อมูลที่กรอกไว้แล้วให้เลย พยาบาลจะได้ไม่พิมพ์ซ้ำ
        $screening = null;
        $prefill = new TbRegistration;
        if ($request->filled('from_screening')) {
            $screening = \App\Models\TbScreening::where('visit_id', $request->input('from_screening'))->first();
            if ($screening && $patient && (int) $screening->patient_id === (int) $patient->id) {
                $prefill->registry_type  = $screening->suggestedRegistryType();
                // impression 5 = วัณโรคนอกปอด · ที่เหลือถือเป็นวัณโรคปอด
                $prefill->classification = (int) $screening->impression === 5 ? 2
                    : ((int) $screening->impression === 2 || (int) $prefill->registry_type === TbRegistration::TYPE_LTBI ? 3 : 1);
                $prefill->extrapulmonary_site = $screening->impression_site;
                // ประวัติรักษา/สัมผัส/แพ้ยา/โรคประจำตัว แปลงจากตัวเลือกในใบคัดกรอง + ทะเบียนคนไข้ (2569-10-19 — เดิมมาแค่โรคประจำตัว)
                $__opd = TbRegistration::opdCardText($patient->id, (int) $screening->visit_id);
                $prefill->contact_history     = $__opd['contact_history'] ?: null;
                $prefill->previous_tb_history = $__opd['previous_tb_history'] ?: null;
                $prefill->drug_allergy        = $__opd['drug_allergy'] ?: null;
                // วันที่ให้คำปรึกษา/ส่งตรวจเลือด HIV จาก OPD ครั้งแรก (2569-10-12) — พยาบาลเห็นในฟอร์มก่อนบันทึก แก้ได้
                $__hivd = TbRegistration::opdHivDates($patient->id);
                $prefill->counsel_date    = $__hivd['counsel'];
                $prefill->blood_test_date = $__hivd['blood'];
                // โรคประจำตัวย้ายไปใช้ช่องของทะเบียนคนไข้หลักแล้ว (2569-09-29) — ใบคัดกรองเก่าเป็นทางถอย
                $__congen = trim((string) optional(\App\Models\Patient::find($screening->patient_id))->congenitaldisease);
                $prefill->other_disease       = $__congen !== '' ? $__congen : $screening->underlying_disease;

                // อาการที่ติ๊กในใบคัดกรอง -> อาการแรกรับในบัตร (ชื่อช่องคนละชุด จับคู่เท่าที่ตรงกัน)
                $map = [
                    'sym_cough_over2wk'  => 'cough',
                    'sym_cough_under2wk' => 'cough',
                    'sym_hemoptysis'     => 'hemoptysis',
                    'sym_fever'          => 'fever',
                ];
                $symptoms = [];
                foreach ($map as $from => $to) {
                    if ($screening->$from && ! isset($symptoms[$to])) {
                        $symptoms[$to] = ['key' => $to, 'duration' => null];
                    }
                }
                $prefill->symptoms = array_values($symptoms) ?: null;
                // ใบคัดกรองผู้สัมผัสระบุผู้ป่วยต้นเหตุไว้ → บัตรใหม่ (มักเป็น LTBI) ผูก index ให้เลย (2569-10-22)
                if (\App\Services\TbContacts::ready() && $screening->index_registration_id) {
                    $prefill->index_registration_id = $screening->index_registration_id;
                }
            } else {
                $screening = null;
            }
        }

        return view('tb.create', compact('patient', 'openOne', 'found', 'q', 'screening', 'prefill'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $data['patient_id'] = (int) $request->input('patient_id');
        if (! Patient::find($data['patient_id'])) {
            return back()->withInput()->with('flash_message', 'ไม่พบคนไข้รายนี้ กรุณาเลือกคนไข้ก่อน');
        }
        $data['created_by'] = Auth::id();

        // กันบัตรซ้ำ (2569-10-22: หน้า DOT ขึ้นชื่อซ้ำ 5 ราย — มีคู่ที่สร้างในวินาทีเดียวกัน = กดเบิ้ล)
        // บัตรที่ยังรักษาอยู่ของคนไข้คนนี้ สร้างไม่ถึง 2 นาที = คำขอซ้ำ พาไปบัตรนั้นเลย ไม่สร้างใหม่
        $open = TbRegistration::where('patient_id', $data['patient_id'])->whereNull('outcome')->orderByDesc('id')->first();
        if ($open && $open->created_at && $open->created_at->gt(now()->subMinutes(2))) {
            flash('บัตรนี้เพิ่งเปิดไปแล้ว — ไม่ได้สร้างซ้ำ', 'info');
            return redirect()->route('tb.show', $open->id);
        }
        // มีบัตรที่ยังรักษาอยู่ = ต้องติ๊กยืนยันว่าเป็นคนละคอร์สจริง (หน้าเปิดบัตรขึ้นช่องให้ติ๊ก)
        if ($open && ! $request->boolean('confirm_second')) {
            return back()->withInput()->with('flash_message',
                'คนไข้รายนี้มีบัตรที่ยังรักษาอยู่ (' . ($open->tb_no ?: 'ไม่มีเลขทะเบียน') . ') — ถ้าเป็นคนละคอร์สจริงให้ติ๊กยืนยันก่อนกดเปิดบัตร');
        }

        $reg = TbRegistration::create($data);
        $reg->ensureInitialRegimenPeriod();   // บัตรใหม่ได้ช่วงสูตรแรกทันทีจากสูตรที่เลือกไว้

        // เปิดมาจากใบคัดกรอง -> ผูกกลับให้เดินไปมาได้ และกันเปิดซ้ำจากใบเดิม
        if ($request->filled('from_screening')) {
            \App\Models\TbScreening::where('visit_id', $request->input('from_screening'))
                ->where('patient_id', $reg->patient_id)
                ->update(['tb_registration_id' => $reg->id]);
        }

        // ผลเสมหะที่ลงไว้ตอนคัดกรอง -> รอบ 0 ของตารางเสมหะ (ถ้ารอบ 0 ยังว่าง) ไม่ต้องกรอกซ้ำในบัตร (2569-10-05)
        \App\Models\TbSputumExam::seedRoundZero($reg, \App\Models\TbSputumExam::screeningForSeed($reg, (int) $request->input('from_screening') ?: null));

        // รหัส ICD-10 เก็บคนละตาราง เพราะเลือกได้หลายรหัส (หลัก + รหัสรองดื้อยา U84.x)
        if ($request->has('reg_icd10')) {
            \App\Models\Icd10Code::syncFor(\App\Models\TbRegistration::icd10Owner(), $reg->id, (array) $request->input('reg_icd10', []));
        }

        flash('เปิดบัตร TB 01 ให้คนไข้แล้ว', 'success');

        return redirect()->route('tb.show', $reg->id);
    }

    /** บัตร TB 01 ของคนไข้หนึ่งราย */
    public function show($id)
    {
        $reg = TbRegistration::with(['patient', 'sputumExams', 'xrays', 'monthlyDoses', 'susceptibilities'])
            ->findOrFail($id);

        // แถวเดือนที่จะแสดงในตารางเสมหะ: 0-9 ตามบัตร แต่ถ้ารักษามานานกว่านั้น (เคสดื้อยา) ให้ยืดตามจริง
        $maxMonth = max(9, $reg->monthsOnTreatment(), (int) $reg->sputumExams->max('month_no'));
        $maxMonth = min($maxMonth, 31);   // ตารางในฐานรองรับถึงเดือนที่ 31

        $sputumByMonth = $reg->sputumExams->keyBy('month_no');
        $doseByMonth   = $reg->monthlyDoses->keyBy('month_no');
        $doseRows      = $this->doseRows($reg, $maxMonth);

        // สูตรยาที่ใช้อยู่ + ประวัติการเปลี่ยนสูตร และยาที่ต้องมีช่องกรอกในตารางขนาดยา
        $regimenChanges = $reg->regimenChanges()->get();
        $drugKeys       = $reg->doseDrugKeys();
        $drugNames      = \App\Models\TbDrug::whereNotNull('code')->get()
            ->mapWithKeys(function ($d) {
                $short = preg_match('/\(([^)]+)\)/', $d->name, $m) ? $m[1] : $d->name;
                return [$short => $d->name];
            });
        $regimenOptions = \App\Models\TbRegimen::where('active', true)->orderBy('sort')->get();
        $drugGroups     = \App\Models\TbDrug::groupedForPicker();   // ติ๊กยาตอนบันทึกสูตร แยกตามกลุ่ม

        // ปฏิทินกินยา: ทำทีละเดือน · ตัวเลือกเดือนไล่จากวันเริ่มกินยาถึงเดือนปัจจุบัน (อย่างน้อย 1 เดือน)
        $months = $this->dotMonthOptions($reg);
        $dotMonth = (string) request('dot_month', '');
        if (! isset($months[$dotMonth])) { $dotMonth = array_key_last($months); }

        $dotDays = \App\Models\TbDotDay::where('tb_registration_id', $reg->id)
            ->where('dot_date', 'like', $dotMonth . '-%')
            ->where('slot', 0)
            ->get()->keyBy('dot_date');

        // ประวัติ DOT ทั้งหมดของบัตร (ส่งเวร + หมายเหตุรายวัน) — การ์ด "ประวัติการกำกับกินยา" (2569-09-30)
        $dotHandovers = \App\Models\TbDotHandover::with(['creator', 'resolver'])
            ->where('tb_registration_id', $reg->id)->orderByDesc('created_at')->get();
        $dotNotesAll  = \App\Models\TbDotDay::where('tb_registration_id', $reg->id)
            ->whereNotNull('note')->where('note', '!=', '')->orderByDesc('dot_date')->limit(200)->get();

        // วันแรกที่ลง DOT ในระบบ — วันก่อนหน้านั้นไม่นับว่า "ไม่ได้บันทึก" (ช่วงก่อนเริ่มใช้ระบบ)
        $dotSince = \App\Models\TbDotDay::where('tb_registration_id', $reg->id)->whereNotNull('mark')
            ->where('dot_date', '>=', '2500-01-01')->min('dot_date');   // แถวที่หลุดเป็น ค.ศ. จะทำให้ทุกวันขึ้นแดง

        // น้ำหนักตั้งต้นของตัวคำนวณขนาดยา (หน้าตรวจ -> ตารางขนาดยา)
        [$lastWeight, $weightSource] = $reg->lastKnownWeight();

        // กลุ่มเกณฑ์ตามประเภททะเบียน: TB -> DS · MDR -> Resist · LTBI -> TPT
        $doseGroup = \App\Models\TbRegimen::drugGroupFor((int) $reg->registry_type);

        // เด็ก/ผู้ใหญ่ตามอายุ: 18 ปีขึ้นไป = ผู้ใหญ่ (คลินิกขอ 2569-10-05 · เดิมต่ำกว่า 15 = เด็กและสลับเองได้)
        [$doseType, $doseAge] = self::doseTypeFor($reg->patient);

        // เกณฑ์ขนาดยา ส่งไปให้หน้าจอคำนวณฝั่งเบราว์เซอร์ (แก้ตัวเลขน้ำหนักแล้วขนาดยาขยับทันที)
        // ส่งไปทั้งผู้ใหญ่และเด็กของกลุ่มนั้น เพราะหน้าจอสลับได้โดยไม่ต้องโหลดหน้าใหม่
        $doseRules = \App\Models\TbDoseRule::where('active', true)
            ->where('group', $doseGroup)
            ->orderBy('sort')->get()
            ->groupBy('patient_type')
            ->map(fn ($rows) => $rows
            ->groupBy('drug_key')
            ->map(function ($rows) {
                $f = $rows->first();
                return [
                    'label'     => $f->drug_label,
                    'strength'  => $f->strength,
                    'note'      => $f->note,
                    'dose_text' => $f->dose_text,
                    'max_mg'    => $f->max_mg !== null ? (int) $f->max_mg : null,
                    'mgkg_min'  => (float) $f->mgkg_min,
                    'mgkg_max'  => (float) $f->mgkg_max,
                    'bands'     => $rows->map(fn ($r) => [
                        'min'  => $r->weight_min !== null ? (float) $r->weight_min : null,
                        'max'  => $r->weight_max !== null ? (float) $r->weight_max : null,
                        'dose' => $r->dose_mg !== null ? (int) $r->dose_mg : null,
                        // ยาที่ระบุเป็นข้อความรายช่วงน้ำหนัก เช่น จำนวนเม็ดของยาเม็ดรวม
                        'text' => $r->dose_text,
                    ])->values(),
                ];
            }));

        // คอลัมน์ยาของ "ใบสรุปการรักษา" = ยาทุกตัวที่เคยสั่งในบัตรนี้ + ยาของสูตรที่ใช้อยู่ (คลินิกขอ 2569-10-02)
        // เดิมตามสูตรปัจจุบันอย่างเดียว พอเปลี่ยน 4FDC → 2FDC คอลัมน์ 4FDC ของเดือนก่อน ๆ หายไปจากตาราง
        $summaryKeys = self::summaryDrugKeys($reg, $drugKeys);
        $doseUnits   = self::doseUnitsFor($summaryKeys);

        // กล่อง "สั่งยาครั้งนี้"
        $order = $this->orderBoxData($reg, $doseRows, $maxMonth, $lastWeight, $weightSource, $doseType);
        $order['doseAge'] = $doseAge;   // ข้อความ "เกณฑ์ผู้ใหญ่ (อายุ 45)" ในกล่อง

        // ระยะ DOT ของเดือนที่เปิดดู — มาจากการสั่งยา แสดงอย่างเดียว (ไม่ให้เลือกเองแล้ว 2569-10-02)
        $dotPhase = $this->dotPhaseSummary($reg, $dotMonth);

        return view('tb.show', compact('reg', 'maxMonth', 'sputumByMonth', 'doseByMonth', 'doseRows', 'doseUnits',
                                       'dotMonth', 'dotDays', 'dotSince', 'dotHandovers', 'dotNotesAll', 'months', 'lastWeight', 'weightSource', 'doseRules',
                                       'doseGroup', 'doseType', 'regimenChanges', 'drugKeys', 'summaryKeys', 'order', 'dotPhase',
                                       'drugNames', 'drugGroups', 'regimenOptions'));
    }

    /**
     * คอลัมน์ยาของตารางรอบสั่งยา: ยาที่เคยมีขนาดในทุกรอบ ∪ ยาของสูตรที่ใช้อยู่
     * เรียงตามลำดับการรักษา: ยาตามลำดับที่เริ่มใช้ (H R Z E ก่อน · 4FDC ก่อน 2FDC) → ยาอื่นที่ไม่ใช่ยาวัณโรค → B6 ท้ายสุด
     */
    private static function summaryDrugKeys(TbRegistration $reg, array $currentKeys): array
    {
        $seen = [];
        foreach ($reg->monthlyDoses->sortBy(fn ($r) => [(int) $r->sort, $r->id]) as $r) {
            foreach ((array) $r->doses as $k => $v) {
                if (trim((string) $v) !== '') { $seen[(string) $k] = true; }
            }
        }
        foreach ($currentKeys as $k) { $seen[(string) $k] = true; }
        $keys = array_keys($seen);

        $rank = array_flip(\App\Models\TbDrug::$drugOrder);
        $pos  = array_flip($keys);
        usort($keys, function ($a, $b) use ($rank, $pos) {
            $grp = fn ($k) => $k === 'B6' ? 3 : (TbRegistration::isTbDrugKey($k) ? 1 : 2);
            $r = fn ($k) => $k === '4FDC' ? -2 : ($k === '2FDC' ? -1 : ($rank[$k] ?? 100 + $pos[$k]));
            return [$grp($a), $r($a)] <=> [$grp($b), $r($b)];
        });

        return $keys;
    }

    /**
     * หน่วยที่หัวคอลัมน์ "4FDC (เม็ด/วัน)" — ในช่องกรอกแค่ตัวเลข (คลินิกขอ 2569-10-02)
     * ยาเม็ดรวม/B6 = เม็ด/วัน · ยาวัณโรคเม็ดเดี่ยว = มก./วัน · ยาอื่น (Para ฯลฯ) เก็บหน่วยไว้ในช่องเอง ไม่ใส่หน่วยที่หัว
     */
    private static function doseUnitsFor(array $keys): array
    {
        $tabletKeys = self::tabletDrugKeys();
        // ยาเม็ดเดี่ยวความแรงเดียว (B6 50 mg) บอกความแรงไว้ในหัวคอลัมน์ด้วย "B6 (50 mg.) (เม็ด/วัน)"
        $strengthOf = \App\Models\TbDoseRule::whereIn('drug_key', $keys)->get(['drug_key', 'strength'])
            ->filter(fn ($r) => preg_match('/^\s*\d+(\.\d+)?\s*mg\.?\s*$/i', (string) $r->strength))
            ->mapWithKeys(fn ($r) => [$r->drug_key => rtrim(trim($r->strength), '.') . '.']);

        return collect($keys)->mapWithKeys(fn ($k) => [$k => in_array($k, $tabletKeys, true)
            ? (isset($strengthOf[$k]) ? '(' . $strengthOf[$k] . ') (เม็ด/วัน)' : '(เม็ด/วัน)')
            : (TbRegistration::isTbDrugKey($k) ? '(มก./วัน)' : '')])->all();
    }

    /**
     * ระยะ DOT ของเดือนหนึ่งสำหรับป้ายบนปฏิทิน — ตามการสั่งยาของแต่ละวัน
     * คืน ['segments' => [[วันแรก, วันสุดท้าย, ระยะ, order|month]], 'last' => วันสุดท้ายของเดือน]
     * เปลี่ยนระยะกลางเดือนจะได้หลายช่วง · month = การสั่งยาบอกไม่ได้ ใช้กติกาเดิม (2 เดือนแรก = เข้มข้น)
     */
    private function dotPhaseSummary(TbRegistration $reg, string $month): array
    {
        [$y, $m] = array_map('intval', array_pad(explode('-', $month), 2, 1));
        $last = (int) date('t', mktime(0, 0, 0, $m, 1, $y - 543));
        $rcs  = \App\Models\TbRegimenChange::where('tb_registration_id', $reg->id)->get();
        $rds  = \App\Models\TbMonthlyDose::where('tb_registration_id', $reg->id)->whereNotNull('doses')->get();
        $fallback = null;
        $segs = [];
        for ($d = 1; $d <= $last; $d++) {
            $date = sprintf('%s-%02d', $month, $d);
            $p = $reg->orderPhaseOn($date, $rcs, $rds);
            $src = $p ? 'order' : 'month';
            if (! $p) { $p = $fallback = $fallback ?? $reg->dotPhaseOn($date); }
            $n = count($segs);
            if ($n && $segs[$n - 1][2] === $p && $segs[$n - 1][3] === $src) { $segs[$n - 1][1] = $d; }
            else { $segs[] = [$d, $d, $p, $src]; }
        }

        return ['segments' => $segs, 'last' => $last];
    }

    /**
     * เดือนที่ให้เลือกในปฏิทินกินยา — จากเดือนที่เริ่มกินยา ถึงเดือนปัจจุบัน
     * คีย์เป็น พ.ศ. รูปแบบ Y-m ให้ตรงกับที่เก็บใน tb_dot_days.dot_date
     */
    private function dotMonthOptions(TbRegistration $reg): array
    {
        $thMonths = \App\Utillity::TH_MONTH_SHORT;

        $nowBe = \App\Models\VisitAppointment::todayBe();            // 2569-09-18
        $end   = substr($nowBe, 0, 7);
        $start = $reg->treatment_start_date ? substr($reg->treatment_start_date, 0, 7) : $end;

        // ย้อนหลังได้ 6 เดือนก่อนวันเริ่มยา (เคส refer มา คนไข้กินยามาก่อนวันที่เราขึ้นทะเบียน)
        // และถ้ามีวันที่เคยบันทึกไว้เก่ากว่านั้น ก็ต้องเปิดให้ย้อนไปถึงเดือนนั้น (2569-09-24)
        [$sy, $sm] = array_map('intval', explode('-', $start));
        $sm -= 6;
        while ($sm < 1) { $sm += 12; $sy--; }
        $start = sprintf('%04d-%02d', $sy, $sm);

        $oldest = \App\Models\TbDotDay::where('tb_registration_id', $reg->id)->min('dot_date');
        if ($oldest && substr($oldest, 0, 7) < $start) { $start = substr($oldest, 0, 7); }

        if ($start > $end) { $end = $start; }

        [$y, $m] = array_map('intval', explode('-', $start));
        [$ey, $em] = array_map('intval', explode('-', $end));

        $out = [];
        $guard = 0;
        while (($y < $ey || ($y === $ey && $m <= $em)) && $guard++ < 60) {
            $key = sprintf('%04d-%02d', $y, $m);
            $out[$key] = $thMonths[$m] . ' ' . $y;
            if (++$m > 12) { $m = 1; $y++; }
        }

        return $out ?: [substr($nowBe, 0, 7) => 'เดือนนี้'];
    }

    /**
     * กลับไปที่กล่องที่เพิ่งกดบันทึก แทนที่จะเด้งขึ้นหัวหน้าทุกครั้ง
     *
     * ใช้หน้าเดิมที่กดมา (referer) เป็นหลัก — บัตรถูกฝังอยู่ในหน้าตรวจด้วย
     * กดบันทึกจากหน้าตรวจจึงต้องกลับไปหน้าตรวจ ไม่ใช่กระเด็นไปหน้าบัตร (2569-09-24)
     */
    /**
     * วันที่จากฟอร์มงานวัณโรค -> พ.ศ. ในฐานข้อมูล
     *
     * พยาบาลบางคนพิมพ์ปีเป็น ค.ศ. (คัดลอกจากใบแล็บ/Excel) ถ้าปล่อยไว้ปีจะเพี้ยนไป 543 ปี
     * เคยเจอทั้งในบัตร TB 01 และในตาราง DST จึงรวมไว้ที่เดียว (2569-09-29)
     */
    private function tbDate($value, bool $notFuture = false): ?string
    {
        // ตัวอ่านแบบเข้มงวด (2569-10-05) — th2dbDate เดิมล้นวันที่ผิดเงียบ ๆ (08/23/2027 -> 2028-11-08)
        // ผู้เรียกต้องตรวจด้วย rowDateErrors() ก่อน ตรงนี้จึงเจอแต่ค่าที่ผ่านแล้ว · ผิด = null กันพลาด
        $r = ThaiDate::parse($value, ['allowEmpty' => true, 'notFuture' => $notFuture]);

        return $r['ok'] ? $r['be'] : null;
    }

    /**
     * ตรวจช่องวันที่ของทุกแถวในตารางก่อนเขียนอะไรลงฐาน (2569-10-05)
     * ผิดแม้ช่องเดียว = ไม่บันทึกทั้งตาราง แล้วบอกว่าแถวไหนช่องไหน — ไม่ปล่อยเป็นว่าง/วันที่เพี้ยนเงียบ ๆ
     * $fields = [ชื่อช่อง => [ชื่อที่คนอ่าน, notFuture]] · $rowName($row, ลำดับแถว 1..n) = ชื่อแถวในข้อความ
     */
    private static function rowDateErrors(array $rows, array $fields, callable $rowName): array
    {
        $errs = [];
        $pos = 0;
        foreach ($rows as $i => $row) {
            $row = (array) $row;
            $pos++;
            foreach ($fields as $f => [$label, $notFuture]) {
                if (! array_key_exists($f, $row)) { continue; }
                $r = ThaiDate::parse($row[$f], ['allowEmpty' => true, 'notFuture' => $notFuture]);
                if (! $r['ok']) {
                    $errs["date_{$f}_{$i}"] = $rowName($row, $pos) . ' · ' . $label . ': ' . $r['error'];
                }
            }
        }
        return $errs;
    }

    /** กลับไปกล่องเดิมพร้อมข้อความวันที่ผิด — ไม่มีอะไรถูกบันทึกในรอบนี้ */
    private function dateFail(TbRegistration $reg, string $anchor, array $errs)
    {
        return $this->backToSection($reg, $anchor)->withInput()
            ->withErrors(['date' => 'ยังไม่ได้บันทึก — วันที่ไม่ถูกต้อง (ใช้รูปแบบ วว/ดด/ปปปป พ.ศ.) แก้แล้วกดบันทึกใหม่'] + $errs);
    }
    private function backToSection(TbRegistration $reg, string $anchor, array $query = [])
    {
        $base = route('tb.show', $reg->id);
        $ref  = (string) request()->headers->get('referer');
        if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === request()->getHost()) {
            $base = strtok($ref, '#');
        }

        if ($query) {
            $parts = parse_url($base);
            parse_str($parts['query'] ?? '', $q);
            $q = array_merge($q, $query);
            $base = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '')
                  . (isset($parts['port']) ? ':' . $parts['port'] : '')
                  . ($parts['path'] ?? '') . ($q ? '?' . http_build_query($q) : '');
        }

        return redirect()->to($base . '#' . $anchor);
    }

    public function update(Request $request, $id)
    {
        $reg = TbRegistration::findOrFail($id);
        $reg->update($this->validated($request));
        $reg->ensureInitialRegimenPeriod();   // บัตรที่ยังไม่มีประวัติสูตร ให้มีช่วงแรกไว้ปิดตอนเปลี่ยนสูตร

        // มีช่องนี้ในฟอร์มค่อยซิงก์ — ฟอร์มย่อยอื่นที่ยิงมาที่ update แต่ไม่มีช่อง ICD จะได้ไม่ลบรหัสทิ้ง
        if ($request->has('reg_icd10')) {
            \App\Models\Icd10Code::syncFor(\App\Models\TbRegistration::icd10Owner(), $reg->id, (array) $request->input('reg_icd10', []));
        }

        flash('บันทึกบัตร TB 01 แล้ว', 'success');
        // เพิ่งลงผลการรักษา (หาย/ครบ) — บอกว่าต้องนัดติดตามหลังจบเมื่อไร กดลงนัดได้ที่กล่องด้านบนของบัตร
        if (\App\Services\TbPostFollowup::eligible($reg->fresh()) && ($p = \App\Services\TbPostFollowup::plan($reg->fresh()))) {
            flash('ติดตามหลังรักษาตามมาตรฐาน: ' . implode(' · ', array_map(fn ($x) => $x['label'] . ' (' . \App\Utillity::db2thDate($x['due']) . ')', $p))
                . ' — กด "ลงนัดติดตามทั้งหมด" ที่กล่องด้านบนของบัตร', 'info');
        }

        return $this->backToSection($reg, 'tb-info');
    }

    /**
     * จำหน่าย (ลงผลการรักษา) จากหน้าตรวจ OPD — ปุ่มข้างปุ่มเปิดบัตร (คลินิกขอ 2569-10-27)
     * ยิงด้วย fetch (อยู่ในฟอร์มหลักของหน้าตรวจ ห้ามฟอร์มซ้อน) · แก้/ยกเลิกได้ที่บัตรเหมือนเดิม
     */
    public function dischargeFromVisit(Request $request, $id)
    {
        $reg = TbRegistration::findOrFail($id);
        $fail = fn ($m) => response()->json(['ok' => false, 'message' => $m], 422);
        if (! $reg->isActive()) { return $fail('บัตรนี้จำหน่ายไปแล้ว (' . $reg->outcomeName() . ') — แก้ที่บัตร'); }

        $opts = TbRegistration::outcomeOptionsFor($reg->registry_type);
        $outcome = (int) $request->input('outcome');
        if (! isset($opts[$outcome]) || $outcome === TbRegistration::OUTCOME_VOID) { return $fail('เลือกผลการรักษาก่อน'); }
        $d = ThaiDate::parse($request->input('outcome_date'), ['allowEmpty' => false, 'notFuture' => true]);
        if (! $d['ok'] || ! $d['be']) { return $fail('วันที่จำหน่าย: ' . ($d['error'] ?: 'กรอกวันที่ (วว/ดด/ปปปป พ.ศ.)')); }
        $start = (string) ($reg->getRawOriginal('treatment_start_date') ?: $reg->getRawOriginal('register_date'));
        if ($start !== '' && $d['be'] < $start) { return $fail('วันที่จำหน่ายอยู่ก่อนวันเริ่มรักษา (' . \App\Utillity::db2thDate($start) . ')'); }

        $reg->update(['outcome' => $outcome, 'outcome_date' => $d['be']]);

        $msg = 'จำหน่ายแล้ว: ' . $opts[$outcome] . ' วันที่ ' . \App\Utillity::db2thDate($d['be']);
        $fresh = $reg->fresh();
        if (\App\Services\TbPostFollowup::eligible($fresh) && ($p = \App\Services\TbPostFollowup::plan($fresh))) {
            $msg .= ' · ติดตามหลังรักษา: ' . implode(' · ', array_map(fn ($x) => $x['label'] . ' (' . \App\Utillity::db2thDate($x['due']) . ')', $p))
                . ' — ลงนัดได้ที่กล่องด้านบนของบัตร';
        }
        return response()->json(['ok' => true, 'message' => $msg]);
    }

    /** ลงนัดติดตามหลังจบการรักษาทุกรอบที่ยังไม่มีนัด (กล่องบนบัตร TB 01) — 2569-10-27 */
    public function bookPostFollowup($id)
    {
        $reg = TbRegistration::findOrFail($id);
        $n = \App\Services\TbPostFollowup::book($reg);
        flash($n ? 'ลงนัดติดตามหลังรักษาแล้ว ' . $n . ' นัด (ผูกกับ visit วัณโรคล่าสุด · เตรียม CXR) — แก้วัน/ลบนัดได้ที่หน้าตรวจ' : 'ไม่มีรอบที่ต้องลงนัดเพิ่ม (นัดไว้แล้ว หรือเลยช่วงไปแล้ว)', $n ? 'success' : 'warning');
        return redirect()->route('tb.show', $reg->id);
    }

    /**
     * บันทึกตารางผลเสมหะทั้งตารางในครั้งเดียว
     *
     * เขียนเฉพาะแถวที่มีข้อมูลจริง และลบแถวที่ถูกล้างค่าจนว่าง
     * (พยาบาลกรอกทีละเดือน ตารางส่วนใหญ่จึงว่าง ถ้าสร้างแถวเปล่าไว้ทุกเดือนจะรกฐานข้อมูลเปล่า ๆ)
     */
    public function saveSputum(Request $request, $id)
    {
        $reg = TbRegistration::findOrFail($id);

        // หนึ่งแถว = การตรวจหนึ่งครั้ง (ไม่ใช่หนึ่งเดือน) เพราะมีรอบ 2wk และบางเดือนตรวจซ้ำ
        // ฟอร์มส่ง id มาด้วย ถ้าเป็นแถวใหม่จะว่าง — ล้างจนว่างทั้งแถว = ลบทิ้งเหมือนเดิม
        $rows = (array) $request->input('sputum', []);
        $errs = self::rowDateErrors($rows, ['collected_date' => ['วันที่เก็บเสมหะ', true]],
            fn ($r, $n) => 'ตารางเสมหะ รอบ ' . (trim((string) ($r['label'] ?? '')) ?: ($r['month_no'] ?? $n)));
        if ($errs) { return $this->dateFail($reg, 'tb-sputum', $errs); }
        $saved = 0;
        $removed = 0;
        $keep = [];
        $sort = 0;

        foreach ($rows as $row) {
            $label   = trim((string) ($row['label'] ?? ''));
            $monthNo = isset($row['month_no']) && $row['month_no'] !== '' ? (int) $row['month_no'] : null;

            // เดาเลขเดือนจากป้ายกำกับ ถ้าฟอร์มไม่ได้ส่งมา (แถวที่พยาบาลกดเพิ่มเอง)
            // "3" = เดือนที่ 3 · "2wk" = สัปดาห์ที่ 2 (ยังอยู่ในเดือน 0) · "10wk" = เดือนที่ 2
            if ($monthNo === null) {
                if (preg_match('/^\s*(\d+)\s*$/', $label, $m)) {
                    $monthNo = (int) $m[1];
                } elseif (preg_match('/^\s*(\d+)\s*wk\s*$/i', $label, $m)) {
                    $monthNo = (int) floor(((int) $m[1]) / 4.345);
                } else {
                    $monthNo = 0;
                }
            }
            if ($monthNo < 0 || $monthNo > 31) { continue; }

            $values = [
                'label'          => $label ?: (string) $monthNo,
                'month_no'       => $monthNo,
                'sort'           => $sort++,
                'collected_date' => $this->tbDate($row['collected_date'] ?? null, true),
                'spot'           => trim((string) ($row['spot'] ?? '')) ?: null,
                'collect'        => trim((string) ($row['collect'] ?? '')) ?: null,
                'molecular'      => trim((string) ($row['molecular'] ?? '')) ?: null,
                'culture'        => trim((string) ($row['culture'] ?? '')) ?: null,
                'lab_serial'     => trim((string) ($row['lab_serial'] ?? '')) ?: null,
                'appearance'     => isset(\App\Models\TbSputumExam::$appearanceOption[$row['appearance'] ?? '']) ? (int) $row['appearance'] : null,
                'specimen'       => trim((string) ($row['specimen'] ?? '')) ?: null,
                'note'           => trim((string) ($row['note'] ?? '')) ?: null,
            ];

            // Collect ครั้งที่ 2 — เขียนเฉพาะเมื่อมีคอลัมน์แล้วและฟอร์มส่งมา (ฟอร์มเก่าไม่มีช่องนี้ = ไม่ทับเป็นว่าง)
            if (\App\Models\TbSputumExam::hasCollect2() && array_key_exists('collect2', $row)) {
                $values['collect2'] = trim((string) $row['collect2']) ?: null;
            }

            // เลขที่ตลับ / ครั้งที่ เอาออกจากตารางแล้ว (คลินิกขอ 2569-10-02) — เขียนเฉพาะเมื่อฟอร์มส่งมา
            // ฟอร์มเก่าที่เปิดค้างไว้ยังส่งสองช่องนี้อยู่ ก็ยังบันทึกได้เหมือนเดิม · ฟอร์มใหม่ไม่ส่ง = ไม่ทับค่าเดิมเป็นว่าง
            foreach (['sample_no', 'round_no'] as $k) {
                if (array_key_exists($k, $row)) { $values[$k] = trim((string) $row[$k]) ?: null; }
            }

            $existing = ! empty($row['id'])
                ? TbSputumExam::where('tb_registration_id', $reg->id)->find((int) $row['id'])
                : null;

            // กดปุ่มลบแถว (2569-10-09) — ลบตรง ๆ ไม่ต้องล้างทุกช่อง (ช่องที่ซ่อนจากจอทำให้ล้างให้ว่างไม่ได้อยู่แล้ว)
            if (! empty($row['_delete'])) {
                if ($existing) { $existing->delete(); $removed++; }
                continue;
            }

            // ช่องที่พยาบาลกรอกจริง ๆ (ไม่นับป้ายกำกับ/เลขเดือน/ลำดับ ซึ่งฟอร์มใส่มาให้เสมอ)
            // ช่องที่ซ่อนจากหน้าจอแล้วแต่ยังมีค่าในฐาน นับเป็นข้อมูลด้วย — ไม่งั้นแถวที่เหลือแค่เลขตลับจะถูกลบทิ้งเงียบ ๆ
            $hidden = $existing ? array_diff_key($existing->only(['sample_no', 'round_no']), $values) : [];
            $filled = array_filter(
                array_diff_key($values + $hidden, array_flip(['label', 'month_no', 'sort'])),
                fn ($v) => $v !== null && $v !== ''
            );

            if (! $filled) {
                if ($existing) { $existing->delete(); $removed++; }
                continue;
            }

            if ($existing) { $existing->update($values); }
            else { $existing = TbSputumExam::create($values + ['tb_registration_id' => $reg->id]); }

            $keep[] = $existing->id;
            $saved++;
        }

        flash('บันทึกผลตรวจเสมหะแล้ว ' . $saved . ' รายการ' . ($removed ? " · ล้างออก {$removed} รายการ" : ''), 'success');

        return $this->backToSection($reg, 'tb-sputum');
    }

    /**
     * ผล X-ray — ตารางเพิ่ม/แก้/ลบได้อิสระ (บัตรกระดาษมี 3 ช่อง แต่ของจริงถ่ายมากกว่านั้น)
     * แถวที่ล้างจนว่างทั้งแถว = ลบทิ้ง เหมือนตารางเสมหะ
     */
    public function saveXrays(Request $request, $id)
    {
        $reg = TbRegistration::findOrFail($id);
        $keep = [];

        $errs = self::rowDateErrors((array) $request->input('xray', []),
            ['xray_date' => ['วันที่ถ่าย', true], 'read_date' => ['วันที่อ่านผล', true]],
            fn ($r, $n) => 'X-ray แถวที่ ' . $n);
        if ($errs) { return $this->dateFail($reg, 'tb-xray', $errs); }

        foreach ((array) $request->input('xray', []) as $row) {
            $values = [
                'xray_date'  => $this->tbDate($row['xray_date'] ?? null, true),
                'views'      => array_values(array_intersect(
                                    (array) ($row['views'] ?? []),
                                    array_keys(\App\Models\TbXray::$viewOption)
                                )) ?: null,
                'other_view' => trim((string) ($row['other_view'] ?? '')) ?: null,
                'result'     => trim((string) ($row['result'] ?? '')) ?: null,
                'read_date'  => $this->tbDate($row['read_date'] ?? null, true),
                'reader'     => trim((string) ($row['reader'] ?? '')) ?: null,
            ];

            // X-ray No. และช่องติ๊ก CD สองช่อง เอาออกจากหน้าจอแล้ว (คลินิกขอ 2569-10-02)
            // ฟอร์มใหม่ไม่ส่งมา = ไม่แตะค่าเดิม · ฟอร์มเก่าที่เปิดค้างไว้ยังมีช่อง X-ray No. (ส่งมาเสมอแม้ว่าง)
            // ใช้ช่องนั้นเป็นตัวบอกว่าเป็นฟอร์มเก่า แล้วบันทึกทั้งสามช่องแบบเดิม (ช่องติ๊กที่ไม่ติ๊กจะไม่ถูกส่งมา)
            // XN เอาออกจากหน้าจอ (คลินิกขอ 2569-10-05) — เขียนเฉพาะเมื่อฟอร์มเก่าที่เปิดค้างส่งมา ไม่ทับค่าเดิมเป็นว่าง
            if (array_key_exists('xn', $row)) {
                $values['xn'] = trim((string) $row['xn']) ?: null;
            }
            // หมายเหตุ (แทน XN) — เขียนเมื่อมีคอลัมน์แล้วและฟอร์มส่งมา
            if (\App\Models\TbXray::hasNote() && array_key_exists('note', $row)) {
                $values['note'] = trim((string) $row['note']) ?: null;
            }
            if (array_key_exists('xray_no', $row)) {
                $values['xray_no']    = trim((string) $row['xray_no']) ?: null;
                $values['cd_to_pacs'] = ! empty($row['cd_to_pacs']);
                $values['cd_write']   = ! empty($row['cd_write']);
            }

            $rowId = (int) ($row['id'] ?? 0);
            $x = $rowId ? \App\Models\TbXray::where('tb_registration_id', $reg->id)->find($rowId) : null;

            // แถวว่าง = ไม่เก็บ (ช่องติ๊ก CD อย่างเดียวไม่ถือว่ามีข้อมูล)
            // X-ray No. ที่ซ่อนแล้วแต่ยังมีค่าในฐาน นับเป็นข้อมูล — แถวจะได้ไม่ถูกลบทิ้งทั้งที่ยังมีเลขอยู่
            $hidden = $x && ! array_key_exists('xray_no', $values) ? ['xray_no' => $x->xray_no] : [];
            $filled = array_filter(
                array_diff_key($values + $hidden, array_flip(['cd_to_pacs', 'cd_write'])),
                fn ($v) => $v !== null && $v !== '' && $v !== []
            );
            if (! $filled) { continue; }

            if ($x) { $x->update($values); }
            else { $x = \App\Models\TbXray::create($values + ['tb_registration_id' => $reg->id]); }

            $keep[] = $x->id;
        }

        \App\Models\TbXray::where('tb_registration_id', $reg->id)->whereNotIn('id', $keep ?: [0])->delete();

        flash('บันทึกผล X-ray แล้ว', 'success');
        return $this->backToSection($reg, 'tb-xray');
    }

    /**
     * แถวของตารางขนาดยา: แถวที่บันทึกไว้ + แถวมาตรฐาน (0 · 2wk · 1 ...) ที่ยังไม่มี เรียงตามเวลา
     * visible = แสดงตั้งต้น — แถวที่มีข้อมูล + แถวมาตรฐานถัดไปหนึ่งแถว ที่เหลือพับไว้ (คลินิกขอ 2569-10-01 ไม่ให้ต้อง scroll)
     * seg = ช่วงนับรอบของแถว (TbRegistration::doseSegments) — เลขรอบซ้ำกันได้ข้ามช่วง (2569-10-26)
     * $seg = ช่วงที่จะเติมแถวมาตรฐานที่ยังว่างให้ (ค่าตั้งต้น = ช่วงล่าสุด · visit ย้อนหลัง = ช่วงของวันนั้น)
     */
    private function doseRows(TbRegistration $reg, int $maxMonth, ?int $seg = null): array
    {
        $D = \App\Models\TbMonthlyDose::class;
        $segs = $reg->exists ? $reg->doseSegments() : [['idx' => 0, 'start' => null, 'end' => null, 'regimen' => '', 'group' => null]];
        $seg = $seg ?? count($segs) - 1;
        $rows = [];
        $have = [];
        foreach ($reg->monthlyDoses as $r) {
            $label = trim((string) $r->label) !== '' ? (string) $r->label : (string) $r->month_no;
            $rs = $reg->exists ? $reg->segmentIndexFor($r->dispensed_date, $segs) : 0;
            if ($rs === $seg) { $have[] = $label; }
            $rows[] = ['label' => $label, 'month_no' => (int) $r->month_no, 'row' => $r, 'standard' => in_array($label, $D::standardLabels($maxMonth), true),
                       'pos' => $D::position($label, (int) $r->month_no), 'sort' => (int) $r->sort, 'seg' => $rs];
        }
        foreach ($D::standardLabels($maxMonth) as $label) {
            if (in_array($label, $have, true)) { continue; }
            $month = $label === '2wk' ? 0 : (int) $label;
            $rows[] = ['label' => $label, 'month_no' => $month, 'row' => null, 'standard' => true,
                       'pos' => $D::position($label, $month), 'sort' => 0, 'seg' => $seg];
        }
        // เรียงตามช่วงก่อน · เวลาเท่ากัน: แถวมาตรฐานก่อน แล้วตามลำดับที่บันทึกไว้
        usort($rows, fn ($a, $b) => [$a['seg'], $a['pos'], $a['standard'] ? 0 : 1, $a['sort']] <=> [$b['seg'], $b['pos'], $b['standard'] ? 0 : 1, $b['sort']]);

        // แถวที่สั่งแล้ว (มีวันที่จ่าย) ต้องเรียงตามวันที่จริง — แถวชื่อเอง เช่น "ปรับยา" เดือน 1 เดิมไปต่อท้ายรอบ 1
        // ทั้งที่จ่ายก่อน (22/10 อยู่หลัง 29/10 · คลินิกแจ้ง 2569-10-30) → สลับเฉพาะแถวที่มีวันที่ ในช่องเดิมของมัน
        // แถวมาตรฐานที่ยังว่างอยู่ที่เดิม (ตำแหน่งตามรอบ) ไม่กระทบแถวถัดไปที่ให้กรอกต่อ
        $slots = [];
        foreach ($rows as $i => $r) {
            if ($r['row'] && TbRegistration::beDate($r['row']->dispensed_date)) { $slots[$r['seg']][] = $i; }
        }
        foreach ($slots as $idx) {
            $dated = array_map(fn ($i) => $rows[$i], $idx);
            usort($dated, fn ($a, $b) => [TbRegistration::beDate($a['row']->dispensed_date), $a['pos'], $a['sort'], $a['row']->id]
                                     <=> [TbRegistration::beDate($b['row']->dispensed_date), $b['pos'], $b['sort'], $b['row']->id]);
            foreach ($idx as $k => $i) { $rows[$i] = $dated[$k]; }
        }

        // แถวว่างถัดจากแถวที่มีข้อมูลแถวสุดท้ายของช่วงนั้น (ไม่มีข้อมูลเลย = แถว 0) แสดงไว้ให้กรอกต่อได้ทันที
        $lastFilled = -1;
        foreach ($rows as $i => $r) { if ($r['row'] && $r['seg'] === $seg) { $lastFilled = $i; } }
        if ($lastFilled < 0) { foreach ($rows as $i => $r) { if ($r['seg'] < $seg) { $lastFilled = $i; } } }
        $next = null;
        foreach ($rows as $i => $r) {
            if ($i > $lastFilled && ! $r['row'] && $r['standard']) { $next = $i; break; }
        }
        foreach ($rows as $i => &$r) {
            $r['visible'] = (bool) $r['row'] || $i === $next;
            $r['text']    = $D::labelText($r['label'], $r['month_no']);
        }
        unset($r);

        return $rows;
    }

    /**
     * น้ำหนัก + ขนาดยา (บัตร TB 01 หน้า 2) — หนึ่งแถว = การสั่งยาหนึ่งครั้ง
     * แถวรายเดือน · 2 สัปดาห์ · นอกรอบ (2569-10-01) จับคู่กับของเดิมด้วย id ที่ฟอร์มส่งมา
     */
    public function saveDoses(Request $request, $id)
    {
        $reg = TbRegistration::findOrFail($id);
        $saved = $removed = 0;

        // 4FDC (เดือน 1-2) กับ 2FDC (เดือน 3-6) ใช้คนละระยะ — รอบเดียวกันมีได้ตัวเดียว (คลินิกแจ้ง 2569-10-02)
        // หน้าจอกันไว้แล้ว ตรงนี้กันฟอร์มที่เปิดค้าง/JS พัง ไม่ให้บันทึกยาซ้อนสองตัว
        foreach ((array) $request->input('dose', []) as $row) {
            $d = (array) (((array) $row)['doses'] ?? []);
            if (trim((string) ($d['4FDC'] ?? '')) !== '' && trim((string) ($d['2FDC'] ?? '')) !== '') {
                return back()->withInput()->withErrors(['dose' => 'รอบเดียวกันใส่ได้ทั้ง 4FDC หรือ 2FDC อย่างใดอย่างหนึ่งเท่านั้น (4FDC = เดือน 1-2 · 2FDC = เดือน 3-6)']);
            }
        }
        $errs = self::rowDateErrors((array) $request->input('dose', []), ['dispensed_date' => ['วันที่จ่ายยา', true]],
            fn ($r, $n) => 'ตารางขนาดยา รอบ ' . (trim((string) ($r['label'] ?? '')) ?: ($r['month_no'] ?? $n)));
        if ($errs) { return $this->dateFail($reg, 'tb-dose', $errs); }

        foreach ((array) $request->input('dose', []) as $key => $row) {
            $row = (array) $row;
            // ฟอร์มรุ่นเก่าที่เปิดค้าง: คีย์คือเลขเดือน ไม่มี id/label
            $legacy  = ! array_key_exists('label', $row);
            $monthNo = $legacy ? (int) $key : (int) ($row['month_no'] ?? 0);
            if ($monthNo < 0 || $monthNo > 31) { continue; }
            $label = $legacy ? (string) $monthNo : mb_substr(trim((string) $row['label']), 0, 30);
            if ($label === '') { $label = (string) $monthNo; }

            $values = [
                'weight'         => trim((string) ($row['weight'] ?? '')) !== '' ? (float) $row['weight'] : null,
                'dispensed_date' => $this->tbDate($row['dispensed_date'] ?? null, true),
                'dispensed_days' => trim((string) ($row['dispensed_days'] ?? '')) !== '' ? (int) $row['dispensed_days'] : null,
            ];

            // ขนาดยาเก็บเป็น JSON ตามรหัสยา (ช่องยาเปลี่ยนได้ตามสูตรที่ใช้)
            $doses = [];
            foreach ((array) ($row['doses'] ?? []) as $key => $val) {
                $val = trim((string) $val);
                if ($val !== '') { $doses[$key] = $val; }
            }
            $values['doses'] = $doses ?: null;
            $values['note']  = trim((string) ($row['note'] ?? '')) ?: null;

            $existing = ! empty($row['id'])
                ? \App\Models\TbMonthlyDose::where('tb_registration_id', $reg->id)->find((int) $row['id'])
                : ($legacy ? \App\Models\TbMonthlyDose::where('tb_registration_id', $reg->id)->where('month_no', $monthNo)
                        ->where(fn ($q) => $q->whereNull('label')->orWhere('label', (string) $monthNo))->first() : null);
            $isEmpty = ! array_filter($values, fn ($v) => $v !== null && $v !== '' && $v !== []);

            if ($isEmpty) {
                if ($existing) { $existing->delete(); $removed++; }
                continue;
            }
            // sort คิดจากป้ายเสมอ ไม่ว่าฟอร์มรุ่นไหนส่งมา ค่าจะได้เทียบกันได้ (ตัวเดียวกับกล่องสั่งยา)
            self::writeDoseRow($reg, $existing, $values, $label, $monthNo);
            $saved++;
        }

        flash('บันทึกน้ำหนัก/ขนาดยาแล้ว ' . $saved . ' แถว' . ($removed ? " · ล้างออก {$removed} แถว" : ''), 'success');
        return $this->backToSection($reg, 'tb-dose');
    }

    /** ผลทดสอบความไวต่อยา (DST) — หนึ่งแถวคือการส่งตรวจหนึ่งครั้ง */
    public function saveDst(Request $request, $id)
    {
        $reg = TbRegistration::findOrFail($id);
        $keep = [];

        $errs = self::rowDateErrors((array) $request->input('dst', []),
            ['sent_date' => ['วันที่ส่งตรวจ', true], 'result_date' => ['วันที่รายงานผล', true]],
            fn ($r, $n) => 'DST แถวที่ ' . $n);
        if ($errs) { return $this->dateFail($reg, 'tb-dst', $errs); }

        foreach ((array) $request->input('dst', []) as $row) {
            $sent    = $this->tbDate($row['sent_date'] ?? null, true);
            $result  = $this->tbDate($row['result_date'] ?? null, true);
            $method  = ($row['method'] ?? '') !== '' ? (int) $row['method'] : null;
            $csNo    = trim((string) ($row['cs_no'] ?? '')) ?: null;
            $note    = trim((string) ($row['note'] ?? '')) ?: null;
            $rowId   = (int) ($row['id'] ?? 0);

            // ผลรายยา: เก็บเฉพาะยาที่กรอกผลไว้จริง
            $results = [];
            foreach ((array) ($row['results'] ?? []) as $drug => $val) {
                $val = trim((string) $val);
                if ($val !== '' && in_array($drug, \App\Models\TbDrugSusceptibility::$drugs, true)) {
                    $results[$drug] = $val;
                }
            }

            if (! $sent && ! $result && ! $csNo && ! $note && ! $results) { continue; }

            $d = $rowId ? \App\Models\TbDrugSusceptibility::where('tb_registration_id', $reg->id)->find($rowId) : null;
            $values = [
                'sent_date' => $sent, 'result_date' => $result, 'method' => $method,
                'cs_no' => $csNo, 'results' => $results ?: null, 'note' => $note,
            ];
            if ($d) { $d->update($values); }
            else { $d = \App\Models\TbDrugSusceptibility::create($values + ['tb_registration_id' => $reg->id]); }
            $keep[] = $d->id;
        }

        \App\Models\TbDrugSusceptibility::where('tb_registration_id', $reg->id)->whereNotIn('id', $keep ?: [0])->delete();

        flash('บันทึกผลความไวต่อยาแล้ว', 'success');
        return $this->backToSection($reg, 'tb-dst');
    }

    /**
     * ปฏิทินกินยา — บันทึกทีละเดือน
     *
     * ส่งมาเฉพาะวันที่มีสัญลักษณ์ ส่วนวันที่ถูกล้างให้ลบแถวทิ้ง
     * (เดือนหนึ่งมี 31 ช่อง ถ้าเก็บทุกช่องแม้ยังไม่ติ๊ก ตารางจะบวมเปล่า ๆ)
     */
    public function saveDot(Request $request, $id)
    {
        $reg = TbRegistration::findOrFail($id);

        $month = trim((string) $request->input('month', ''));   // พ.ศ. รูปแบบ Y-m
        // เดือนต้องเป็น พ.ศ. และอยู่ในช่วงเดียวกับตัวอ่านวันที่ — กันปฏิทินเดือนปี ค.ศ. (เช่น 2026-09) หลุดลงฐาน
        $monthOk = preg_match('/^(\d{4})-(\d{2})$/', $month, $m)
            && ThaiDate::parse('01/' . $m[2] . '/' . $m[1])['ok'] && (int) $m[1] >= 2400;
        if (! $monthOk) {
            flash('เดือนไม่ถูกต้อง', 'danger');
            return $this->backToSection($reg, 'tb-dot');
        }
        // ระยะ DOT มาจากการสั่งยาของวันนั้น (ช่วงสูตร/รอบสั่งยา) ไม่รับจากฟอร์มแล้ว (คลินิกขอ 2569-10-02)
        // เดิมเป็นช่องเลือกที่แก้ได้แม้ปฏิทินล็อก แล้วระยะในรายงานไม่ตรงกับยาที่สั่งจริง · หน้าที่เปิดค้างส่ง phase มาก็ไม่ใช้
        $rcs   = \App\Models\TbRegimenChange::where('tb_registration_id', $reg->id)->get();
        $rds   = \App\Models\TbMonthlyDose::where('tb_registration_id', $reg->id)->whereNotNull('doses')->get();
        $fallbackPhase = null;
        $phaseOf = function (string $date) use ($reg, $rcs, $rds, &$fallbackPhase) {
            $p = $reg->orderPhaseOn($date, $rcs, $rds);
            if ($p) { return $p; }
            return $fallbackPhase = $fallbackPhase ?? $reg->dotPhaseOn($date);   // กติกาเดิมขึ้นกับเดือน — ทั้งเดือนค่าเดียว
        };
        $marks = (array) $request->input('mark', []);
        // orig = สัญลักษณ์ตอนเปิดหน้า · วันที่ไม่ได้กดเปลี่ยนไม่ต้องแตะ
        // ไม่งั้นบัตรที่เปิดค้างไว้จะลบ/ทับสิ่งที่หน้า DOT เพิ่งติ๊กไป (รีวิว 2569-09-30)
        // ฟอร์มรุ่นเก่าที่ไม่มี orig = บันทึกทั้งเดือนแบบเดิม
        $orig  = $request->has('orig') ? (array) $request->input('orig', []) : null;
        $valid = array_keys(\App\Models\TbDotDay::$markOption);

        $saved = $removed = 0;
        for ($day = 1; $day <= 31; $day++) {
            $date = sprintf('%s-%02d', $month, $day);
            $mark = trim((string) ($marks[$day] ?? ''));
            if ($orig !== null && $mark === trim((string) ($orig[$day] ?? ''))) { continue; }

            $row = \App\Models\TbDotDay::where('tb_registration_id', $reg->id)
                ->where('dot_date', $date)->where('slot', 0)->first();

            if ($mark === '' || ! in_array($mark, $valid, true)) {
                // วันที่มีหมายเหตุจากหน้า DOT ล้างแค่สัญลักษณ์ หมายเหตุเก็บไว้ (หน้านี้ไม่มีช่องแก้หมายเหตุ)
                if ($row && trim((string) $row->note) !== '') {
                    if ($row->mark !== null) { $row->update(['mark' => null]); $removed++; }
                } elseif ($row) { $row->delete(); $removed++; }
                continue;
            }
            if ($row) {
                // ระยะ DOT: เติมเฉพาะแถวที่ยังไม่มี — กดบันทึกทั้งเดือนต้องไม่ทับระยะของวันเก่าที่อาจเปลี่ยนไปแล้ว
                $row->update(['mark' => $mark, 'phase' => $phaseOf($date)] + ($row->dot_reason ? [] : ['dot_reason' => $reg->dot_reason ?: null]));
            } else {
                \App\Models\TbDotDay::create([
                    'tb_registration_id' => $reg->id, 'dot_date' => $date,
                    'phase' => $phaseOf($date), 'slot' => 0, 'mark' => $mark,
                    'dot_reason' => $reg->dot_reason ?: null,
                ]);
            }
            $saved++;
        }

        flash('บันทึกปฏิทินกินยาเดือน ' . $month . ' แล้ว (' . $saved . ' วัน' . ($removed ? " · ล้าง {$removed} วัน" : '') . ')', 'success');
        return $this->backToSection($reg, 'tb-dot', ['dot_month' => $month]);
    }

    /**
     * กล่อง "สั่งตรวจครั้งนี้" ในบัตร TB 01 — สั่ง/ลงผลส่วนส่งตรวจทางห้องปฏิบัติการ ให้ visit TB ของวันนี้ (2569-10-02)
     *
     * เคส F/U ไม่ต้องกลับไปหน้าตรวจ · เขียนลงใบคัดกรองใบเดียวกับหน้าตรวจ ผ่าน TbScreening::saveLabSection ตัวเดียวกัน
     * (หน้าตรวจเรียกตัวนี้จาก VisitController@update) ค่าจึงตีความเหมือนกันทุกช่อง
     * แตะเฉพาะช่องของส่วนส่งตรวจ ไม่ยุ่งประวัติ/ตรวจร่างกาย/CXR/Impression ที่อีกห้องอาจกำลังแก้
     *
     * ไม่ปั๊มเวลา/ผู้บันทึกของ visit และไม่แตะคิว — หน้าตรวจทำสิ่งพวกนั้นตอนกด Save ทั้งหน้า ไม่ใช่เพราะสั่งแล็บ
     * (เหมือน Lab ส่งตรวจภายนอกที่บันทึกแยกจากฟอร์มหลักอยู่แล้ว) ส่วนนี้ไม่มีค่าใช้จ่ายเข้าบิล
     */
    public function saveLabOrder(Request $request, $id)
    {
        $reg = TbRegistration::findOrFail($id);

        // ต้องเป็น visit TB ของคนไข้คนนี้ และเป็นของวันนี้ — กันกดจากหน้าที่เปิดค้างข้ามวัน แล้วไปลงผิด visit
        $visit = \App\Models\Visit::where('id', (int) $request->input('visit_id'))
            ->where('patient_id', $reg->patient_id)->where('clinic', 2)->first();
        if (! $visit || $visit->date !== \App\Models\VisitAppointment::todayBe()) {
            $msg = 'ยังไม่ได้บันทึก — ไม่พบ visit TB ของวันนี้ (หน้าอาจเปิดค้างไว้ข้ามวัน) โหลดหน้าใหม่แล้วลองอีกครั้ง';
            if ($request->wantsJson()) { return response()->json(['message' => $msg], 409); }
            flash($msg, 'danger');
            return $this->backToSection($reg, 'tb-lab-order');
        }

        $thDate = function ($attribute, $value, $fail) {
            // ตัวอ่านกลางของงาน TB (2569-10-05) — ปี ค.ศ. 4 หลักแปลงให้ตอนบันทึก (cleanSpecimens ใช้ตัวเดียวกัน)
            $r = ThaiDate::parse($value, ['allowEmpty' => true, 'notFuture' => true]);
            if (! $r['ok']) { $fail('วันที่ส่ง: ' . $r['error']); }
        };
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'tb_lab'                         => ['nullable', 'array'],
            'tb_lab.lab_specimens'           => ['nullable', 'array', 'max:20'],
            'tb_lab.lab_specimens.*.other'   => ['nullable', 'string', 'max:255'],
            'tb_lab.lab_specimens.*.date'    => ['nullable', $thDate],
            'tb_lab.lab_molecular_result'    => ['nullable', 'string', 'max:255'],
            'tb_lab.lab_culture_result'      => ['nullable', 'string', 'max:255'],
            'tb_lab.tst_mm'                  => ['nullable', 'numeric', 'min:0', 'max:99'],
            'tb_lab.cytb_mm'                 => ['nullable', 'numeric', 'min:0', 'max:99'],
            'tb_lab.skin_other_mm'           => ['nullable', 'numeric', 'min:0', 'max:99'],
            'tb_lab.skin_other_name'         => ['nullable', 'string', 'max:60'],
            'tb_lab.labs_other'              => ['nullable', 'string', 'max:255'],
        ], [
            'numeric'     => ':attribute ต้องเป็นตัวเลข',
            // Laravel 8 ไม่อ่านคีย์แบบ max.numeric จากข้อความที่ส่งเข้ามา — ใช้ข้อความเดียวต่อกฎ
            'min'         => ':attribute ต้องไม่ต่ำกว่า :min',
            'max'         => ':attribute เกินกำหนด (ไม่เกิน :max)',
            'array'       => 'ข้อมูลที่ส่งมาไม่ถูกต้อง โหลดหน้าใหม่แล้วลองอีกครั้ง',
        ], [
            'tb_lab.lab_specimens.*.other' => 'สิ่งส่งตรวจ (ระบุ)',
            'tb_lab.lab_specimens.*.date'  => 'วันที่ส่ง',
            'tb_lab.lab_molecular_result'  => 'ผล Molecular',
            'tb_lab.lab_culture_result'    => 'ผล Culture / DST',
            'tb_lab.tst_mm'                => 'ผล TST (mm)',
            'tb_lab.cytb_mm'               => 'ผล Cy-TB (mm)',
            'tb_lab.skin_other_mm'         => 'ผล skin test อื่น (mm)',
            'tb_lab.skin_other_name'       => 'ชื่อ skin test อื่น',
            'tb_lab.labs_other'            => 'การตรวจอื่น ๆ',
        ]);
        if ($validator->fails()) {
            // popup ในบัตร (fetch) -> 422 พร้อม error รายช่อง ให้หน้าจอวางไว้ใต้ช่องนั้น popup ไม่ปิด
            if ($request->wantsJson()) {
                return response()->json(['message' => 'ยังไม่ได้บันทึก — แก้ช่องที่มีกรอบแดงก่อน',
                                         'errors'  => $validator->errors()], 422);
            }
            return $this->backToSection($reg, 'tb-lab-order')->withErrors($validator, 'tbLab')->withInput();
        }

        // ไม่มี tb_lab เลย = หน้าจอตัดทุกกลุ่มทิ้งเพราะไม่ได้แก้อะไร
        $in = (array) $request->input('tb_lab', []);
        if ($in) {
            \App\Models\TbScreening::saveLabSection($visit, $in);
        }
        $msg = $in ? 'บันทึกแล้ว — หน้าตรวจ OPD ของวันนี้เห็นค่าเดียวกัน' : 'ไม่มีอะไรเปลี่ยน';

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $msg, 'html' => $this->labOrderSummaryHtml($reg)]);
        }
        flash($msg, $in ? 'success' : 'info');
        return $this->backToSection($reg, 'tb-lab-order');
    }

    /**
     * visit TB ของวันนี้ของคนไข้ในบัตร (clinic=2, วันที่ พ.ศ. วันนี้, ถ้ามีหลายตัวเอาตัวล่าสุด)
     * ตัวเดียวกับที่หน้าตรวจใช้ — สั่งจากที่ไหนก็ลง visit เดียวกัน · ไม่มี = null (ไม่เปิด visit ให้เอง)
     */
    public static function todayTbVisit(TbRegistration $reg): ?\App\Models\Visit
    {
        return \App\Models\Visit::where('patient_id', $reg->patient_id)
            ->where('clinic', 2)
            ->where('date', \App\Models\VisitAppointment::todayBe())
            ->orderByDesc('id')->first();
    }

    /** เนื้อในกล่อง "สั่งตรวจครั้งนี้" (ไม่มี <script>) — ใช้ทั้งตอนวาดหน้าและตอนสลับกล่องหลังบันทึกใน popup */
    private function labOrderSummaryHtml(TbRegistration $reg): string
    {
        return view('tb._lab_order_summary', ['reg' => $reg, 'tv' => self::todayTbVisit($reg)])->render();
    }

    /** GET กล่องสรุป (สลับในที่หลังบันทึก Lab ภายนอก) */
    public function labOrderSummary($id)
    {
        return response($this->labOrderSummaryHtml(TbRegistration::findOrFail($id)));
    }

    /**
     * GET เนื้อใน popup — โหลดใหม่ทุกครั้งที่เปิด ค่าในช่องจะเป็นของล่าสุดในฐาน (อีกห้องอาจเพิ่งแก้)
     * ไม่มี <script> ในเนื้อหา (innerHTML ไม่รันสคริปต์) ตัวจัดการอยู่ใน tb/_lab_order_js ของหน้าบัตร
     */
    public function labOrderPart(Request $request, $id, string $part)
    {
        $reg = TbRegistration::findOrFail($id);
        $tv  = self::todayTbVisit($reg);
        if (! $tv) {
            return response('<div class="alert alert-warning mb-0">ยังไม่ได้ลงทะเบียนวันนี้ — ลงทะเบียนเปิด visit ก่อนจึงจะสั่งตรวจได้</div>', 409);
        }

        if ($part === 'external') {
            return response(view('tb._lab_order_external', ['reg' => $reg, 'tv' => $tv])->render());
        }
        if ($part === 'inhouse') {
            return response(view('tb._inhouse_lab_panel', ['visit' => $tv])->render());
        }

        $sc = \App\Models\TbScreening::where('visit_id', $tv->id)->first() ?: new \App\Models\TbScreening;
        return response(view('tb._lab_order_form_body', ['reg' => $reg, 'tv' => $tv, 'sc' => $sc])->render());
    }

    /**
     * รูปผังครอบครัวของใบคัดกรอง (Form 2 ข้อ 6)
     * เก็บเข้ารหัสไว้นอก public เหมือนรูปคนไข้ จึงต้องผ่าน route นี้เท่านั้น
     */
    public function familyTree($visitId)
    {
        $sc = \App\Models\TbScreening::where('visit_id', $visitId)->firstOrFail();
        $path = "private/tb_family_tree/{$sc->family_tree_photo}";

        if (! $sc->family_tree_photo || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $raw = \Illuminate\Support\Facades\Crypt::decrypt(\Illuminate\Support\Facades\Storage::disk('local')->get($path));

        return response($raw)->header('Content-Type', 'image/jpeg');
    }

    /** ใบส่งตรวจเอกซเรย์ — ติ๊กท่าจากแถวล่าสุดในบัตรแล้วพิมพ์ให้คนไข้ถือไปห้องเอกซเรย์ */
    public function xrayRequest(Request $request, $id)
    {
        $reg = TbRegistration::with('patient')->findOrFail($id);

        $row = $request->filled('row')
            ? \App\Models\TbXray::where('tb_registration_id', $reg->id)->find($request->input('row'))
            : \App\Models\TbXray::where('tb_registration_id', $reg->id)->orderByDesc('id')->first();

        return view('tb.xray_request', compact('reg', 'row'));
    }

    /**
     * สติ๊กเกอร์ยาวัณโรคของเดือนที่เลือก (ใบสั่งยาที่พิมพ์เป็นสติ๊กเกอร์)
     *
     * ยาวัณโรคเบิกจากกองวัณโรค ไม่ได้ตัดสต็อกคลินิกและไม่คิดเงิน
     * หน้านี้จึงสร้างจากตารางขนาดยาในบัตรโดยตรง ไม่ผ่านคลังยา/ใบเสร็จ (2569-09-28)
     */
    /**
     * ใบกินยาแบบมีรูป — สำหรับคนไข้ที่อ่านหนังสือไม่ได้ (คลินิกขอ 2569-10-01)
     * แบ่งตามช่วงเวลา เช้า / กลางวัน / เย็น / ก่อนนอน / สัปดาห์ละครั้ง / เมื่อมีอาการ
     * จำนวนเม็ด: ตามที่ตั้งในวิธีใช้ ถ้าไม่ได้ตั้ง (ยาวัณโรค) คิดจากขนาดยาที่สั่งล่าสุด ÷ จำนวนครั้งต่อวัน
     */
    public function medSheet($id)
    {
        $reg  = TbRegistration::with('patient')->findOrFail($id);
        $dose = \App\Models\TbMonthlyDose::where('tb_registration_id', $reg->id)
            ->whereNotNull('doses')->orderByDesc('sort')->orderByDesc('id')->first();
        // ชื่อเต็ม: รายการยา TB ก่อน แล้วค่อยชื่อในเกณฑ์ขนาดยา (เช่น Pyridoxine (B6) ไม่มีรหัสในรายการยา)
        $names = \App\Models\TbDoseRule::whereNotNull('drug_label')->pluck('drug_label', 'drug_key')
            ->merge(\App\Models\TbDrug::whereNotNull('code')->get()->mapWithKeys(function ($d) {
                return [(preg_match('/\(([^)]+)\)/', $d->name, $m) ? $m[1] : $d->name) => $d->name];
            }));
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');

        $slots = ['m' => [], 'n' => [], 'e' => [], 'b' => [], 'week' => [], 'prn' => []];
        // ยาของรอบล่าสุดด้วย — ยาอื่นที่หมอสั่งในกล่องสั่งยา (Para ฯลฯ) ไม่ได้อยู่ในสูตร แต่คนไข้ต้องกิน (2569-10-02)
        $keys = array_values(array_unique(array_merge($reg->doseDrugKeys(), array_map('strval', array_keys((array) optional($dose)->doses)))));
        foreach ($keys as $key) {
            $sig  = $reg->sigFor($key);
            $freq = $sig['freq'] ?? '';
            $amt  = trim((string) (((array) optional($dose)->doses)[$key] ?? ''));
            if ($freq === '' && empty($sig['period']) && $amt === '') { continue; }   // ไม่ได้ตั้งและไม่ได้สั่ง = ไม่มีในใบ

            $per = TbRegistration::$sigFreq[$freq][1] ?? 1;
            $qty = trim((string) ($sig['qty'] ?? ''));
            $unit = $sig['unit'] ?? 'เม็ด';
            if ($qty === '' && $amt !== '') {
                $dt = $reg->doseTablets($key, $amt);
                if ($dt['kind'] === 'tab' && $dt['tabs']) { $qty = $fmt($per >= 1 ? $dt['tabs'] / $per : $dt['tabs']); $unit = 'เม็ด'; }
            }
            $item = [
                'key'    => $key,
                'name'   => $names[$key] ?? $key,
                'qty'    => $qty,
                'unit'   => $unit,
                'timing' => $sig['timing'] ?? '',
                'note'   => trim((string) ($sig['note'] ?? '')),
                'route'  => $sig['route'] ?? 'PO',
            ];

            if ($freq === 'PRN') { $slots['prn'][] = $item; continue; }
            if (in_array($freq, ['QW', 'TIW'], true)) { $slots['week'][] = $item + ['freq' => $freq]; continue; }

            // ช่วงเวลาที่ติ๊กไว้มาก่อน · ไม่ได้ติ๊กแต่ตั้งความถี่ = เดาจากความถี่/เวลา
            $periods = \App\Support\DrugSig::cleanPeriods($sig['period'] ?? []);
            if (! $periods) {
                $periods = [
                    'OD'  => [($sig['timing'] ?? '') === 'hs' ? 'b' : 'm'],
                    'BID' => ['m', 'e'],
                    'TID' => ['m', 'n', 'e'],
                    'QID' => ['m', 'n', 'e', 'b'],
                ][$freq] ?? ['m'];
            }
            foreach ($periods as $p) { $slots[$p][] = $item; }
        }

        return view('tb.med_sheet', compact('reg', 'slots'));
    }

    public function labels(Request $request, $id)
    {
        $reg = TbRegistration::with('patient')->findOrFail($id);

        // เลือกแถวด้วย id (มีแถว 2 สัปดาห์/นอกรอบ หลายแถวต่อเดือน) · ลิงก์เก่าที่ส่งเดือนมา = แถวล่าสุดของเดือนนั้น
        $allRows = \App\Models\TbMonthlyDose::where('tb_registration_id', $reg->id)
            ->orderBy('sort')->orderBy('month_no')->orderBy('id')->get();
        $dose = $request->filled('row') ? $allRows->firstWhere('id', (int) $request->input('row')) : null;
        if (! $dose) {
            $want = (int) $request->input('month', $reg->monthsOnTreatment());
            $dose = $allRows->where('month_no', $want)->last();
        }
        $month = $dose ? (int) $dose->month_no : (int) $request->input('month', $reg->monthsOnTreatment());
        $rowLabel = $dose ? $dose->labelForHumans() : 'เดือนที่ ' . $month;

        // ตั้งต้น: ตามที่กรอกในแถว · รอบ 2 สัปดาห์ = 14 วัน · อื่น ๆ 28 วัน
        $days = (int) $request->input('days', $dose->dispensed_days ?? (($dose->label ?? '') === '2wk' ? 14 : 28));
        if ($days < 1 || $days > 120) { $days = 28; }

        // ชื่อเต็ม + ความแรงของยาแต่ละตัว ไว้พิมพ์บนสติ๊กเกอร์ให้คนไข้อ่านออก
        $catalogue = \App\Models\TbDrug::where('active', true)->get();
        $items = [];

        foreach ((array) ($dose->doses ?? []) as $key => $amount) {
            $amount = trim((string) $amount);
            if ($amount === '') { continue; }

            // ชื่อยาส่วนใหญ่เก็บรหัสไว้ในวงเล็บ เช่น "Rifampicin (R)"
            // ส่วนยาเม็ดรวมไม่มีวงเล็บรหัส เช่น "4-FDC (Rimstar) I75-..." จึงเทียบชื่อแบบตัดขีด/ช่องว่างด้วย
            $norm = fn ($s) => strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $s));
            // รหัสในตาราง -> รหัสในรายการยา (H = "Isoniazid (INH)" ด้วย ไม่งั้นสติกเกอร์ INH ไม่มีความแรง) — ชุดเดียวกับ doseTablets
            $codes = ['H' => ['h', 'inh'], 'INH' => ['h', 'inh'], 'H-3HP' => ['h', 'inh'], 'H-1HP' => ['h', 'inh'], 'P-1HP' => ['p']][$key] ?? [strtolower($key)];
            $hits = $catalogue->filter(function ($d) use ($key, $norm, $codes) {
                if (preg_match('/\(([^)]+)\)/', (string) $d->name, $m)
                    && in_array(strtolower(rtrim($m[1], '.')), $codes, true)) {
                    return true;
                }
                return str_starts_with($norm($d->name), $norm($key));
            });
            // ความแรงบนสติกเกอร์ต้องเป็นเม็ดที่จ่ายจริง (R 450 ไม่ใช่ 300 ตัวแรกในรายการ) — 2569-10-02
            $__str = $reg->doseTablets($key, $amount)['strength'] ?? null;
            $drug = ($__str ? $hits->first(fn ($d) => preg_match('/^\s*(\d+(?:\.\d+)?)/', (string) $d->dose, $mm) && (float) $mm[1] === (float) $__str) : null)
                ?: ($hits->first(fn ($d) => $d->dose) ?: $hits->first());

            // "4 เม็ด" -> คูณจำนวนวันได้ · "300" (มก.) -> บอกเป็นขนาดต่อวัน ไม่คูณ
            $isTablet = (bool) preg_match('/เม็ด|tab/iu', $amount);
            $number   = preg_match('/\d+(\.\d+)?/', $amount, $n) ? (float) $n[0] : null;

            // วิธีใช้ที่ตั้งไว้ในบัตร (2569-09-29) — มีแล้วใช้คิดข้อความและจำนวนจ่ายแทนแบบเดิม
            // แบบเดิมถือว่าทุกตัวกินวันละ 1 ครั้งหลังอาหารเช้า ซึ่งผิดกับยาที่ต้องกินตอนท้องว่าง
            // และสูตรที่กินสัปดาห์ละครั้ง
            $sig      = $reg->sigFor($key);
            $dispense = trim((string) ($sig['dispense'] ?? ''));
            $fmt      = fn ($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

            // จำนวนเม็ดต่อครั้งมาจากขนาดยาของเดือนนี้ ไม่ใช่ตัวเลขที่ตั้งไว้ในวิธีใช้ (รีวิว 2569-10-01)
            // น้ำหนักขึ้นแล้วขนาดยาเปลี่ยน สติกเกอร์ต้องเปลี่ยนตาม · วิธีใช้ให้แค่ความถี่/เวลา/route
            // ยาอื่นที่ไม่ใช่ยาวัณโรค (หมอเพิ่มเอง เช่น Para, Brufen — 2569-10-01) ไม่มีในรายการยา TB
            // จึงไม่รู้ความแรงต่อเม็ด → ใช้ "ครั้งละ" ตามที่ตั้งในวิธีใช้ตรง ๆ ไม่แปลงจากขนาดในตาราง
            $isTbDrug = (bool) $drug || TbRegistration::isTbDrugKey($key);
            $dt  = $reg->doseTablets($key, $amount);
            $per = \App\Models\TbRegistration::$sigFreq[$sig['freq'] ?? ''][1] ?? null;
            if (! $isTbDrug && trim((string) ($sig['qty'] ?? '')) !== '') {
                // คงตามวิธีใช้
            } elseif ($dt['kind'] === 'tab' && $dt['tabs'] && ($sig['freq'] ?? '') !== '') {
                // ยารายวัน: ขนาดยาในตารางคือต่อวัน -> หารจำนวนครั้ง · รายสัปดาห์ (3HP): ขนาดในตารางคือต่อครั้ง
                $perTime = $per >= 1 ? $dt['tabs'] / $per : $dt['tabs'];
                $sig['qty']  = $fmt($perTime);
                $sig['unit'] = 'เม็ด';
            } elseif (in_array($dt['kind'], ['mg', 'inj'], true) && $dt['mg'] && ($sig['freq'] ?? '') !== '') {
                // แปลงเป็นเม็ดไม่ได้ — บอกเป็น มก. ต่อครั้ง และไม่คิดยอดจ่าย (ให้ห้องยาคิดเอง)
                $sig['qty']  = $fmt($per >= 1 ? $dt['mg'] / $per : $dt['mg']);
                $sig['unit'] = 'มก.';
            }
            $sigText  = \App\Models\TbRegistration::sigText($sig);
            $sigTotal = ($sig['unit'] ?? '') === 'มก.' ? null : \App\Models\TbRegistration::sigTotal($sig, $days);
            if (! $isTbDrug && $dispense !== '') { $sigTotal = (float) $dispense; }   // ยาอื่น: จ่ายตามที่กรอก ไม่คิดจากวัน
            // ยาอื่นใช้หน่วยตามวิธีใช้ (เม็ด/ขวด/ซอง) — ยอดจ่ายไม่ต้องอิงจำนวนเม็ดในตาราง
            $isTablet = $dt['kind'] === 'tab';
            $number   = $dt['tabs'];

            $items[] = [
                'key'      => $key,
                'name'     => $drug->name ?? $key,
                'strength' => $drug->dose ?? null,
                'amount'   => $amount,
                'per_day'  => $isTablet && $number ? $fmt($number) . ' เม็ด' : $amount . ' มก.',
                'sig'      => $sigText,
                'total'    => $sigTotal !== null
                    ? $fmt($sigTotal) . ' ' . ($sig['unit'] ?? 'เม็ด')
                    // ไม่มีวิธีใช้ = ถือว่ากินทุกวัน (ข้อความแบบเดิม) · มีวิธีใช้แต่คิดไม่ได้ = ไม่เดายอด
                    : ($isTablet && $number && empty($sig['freq']) ? $fmt($number * $days) . ' เม็ด' : null),
                'note'     => $drug->note ?? null,
                'is_tb'    => $isTbDrug,
            ];
        }

        // ใบสรุปรายการยากลับบ้าน/ส่งต่อ จากรอบสั่งยาในบัตร (คลินิกขอ 2569-10-06) — รายการ/วิธีใช้/ยอดจ่ายชุดเดียวกับสติกเกอร์
        // หน้าบัตรสั่งยาแล้วไม่ได้ลงรายการยาของ visit เสมอไป จึงคิดจากรอบในบัตรตรง ๆ
        if ($request->input('format') === 'summary') {
            $p  = $reg->patient;
            $dd = $dose->dispensed_date ?? null;
            $thaiMonths = Utillity::TH_MONTH_FULL;
            $dateText = $dd && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $dd, $m)
                ? ((int) $m[3]) . ' ' . $thaiMonths[(int) $m[2]] . ' ' . ((int) $m[1] < 2500 ? (int) $m[1] + 543 : (int) $m[1])
                : $rowLabel;
            return view('visit.drug_summary', [
                'patient'   => $p,
                'name'      => trim(preg_replace('/([A-Za-z.])$/', '$1 ', Patient::prefixLabel($p)) . ($p->name ?? '') . ' ' . ($p->surname ?? '')),
                'age'       => $p ? $p->age() : '',
                'allergy'   => trim((string) ($p->drug_allergy ?? '')) ?: 'ไม่มีข้อมูล',
                'visitDate' => $dateText . ' (' . $rowLabel . ' · ' . $days . ' วัน)',
                'clinic'    => 'TB Clinic · TB No. ' . ($reg->tb_no ?: '-'),
                'rows'      => collect($items)->map(fn ($it) => [
                    'name'   => trim($it['name'] . ' ' . ($it['strength'] ?? '')),
                    'amount' => $it['total'] ?? $it['per_day'],
                    'how'    => $it['sig'] ?: ('วันละ ' . $it['per_day']),
                    'advice' => \App\Http\Controllers\DrugSummaryController::adviceFor($it['name']),
                ]),
            ]);
        }

        return view('tb.labels', compact('reg', 'dose', 'month', 'days', 'items', 'allRows', 'rowLabel'));
    }

    /**
     * คลิป/รูป DOT ที่คนไข้ส่งเข้ามา — เก็บเข้ารหัสในเครื่อง ไม่ได้วางไว้ใน public
     * (ทำแบบเดียวกับรูปผังครอบครัวและรูปคนไข้)
     */
    public function dotMedia($id)
    {
        $sub = \App\Models\TbDotSubmission::findOrFail($id);
        $path = $sub->storagePath();

        if (! $path || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $raw = \Illuminate\Support\Facades\Crypt::decrypt(
            \Illuminate\Support\Facades\Storage::disk('local')->get($path)
        );

        return response($raw)
            ->header('Content-Type', $sub->mime ?: ($sub->kind === 'image' ? 'image/jpeg' : 'video/mp4'))
            ->header('Content-Disposition', 'inline');
    }

    /** ตารางเกณฑ์ขนาดยา — แก้ได้เฉพาะผู้ดูแลระบบ เพราะกระทบการสั่งยาของทุกเคส */
    public function doseRules()
    {
        abort_unless(Auth::user()->hasRole('Admin'), 403);

        // กรองทีละกลุ่ม จะได้แก้ทีละชุด ไม่ปนกัน (ค่าเริ่มต้น = DS ผู้ใหญ่ ซึ่งใช้บ่อยสุด)
        $group = (string) request('group', 'DS');
        $type  = (string) request('type', 'adult');

        $rules = \App\Models\TbDoseRule::where('group', $group)->where('patient_type', $type)
            ->orderBy('sort')->orderBy('id')->get();

        $counts = \App\Models\TbDoseRule::selectRaw('`group`, patient_type, COUNT(*) n')
            ->groupBy('group', 'patient_type')->get();

        return view('tb.dose_rules', compact('rules', 'group', 'type', 'counts'));
    }

    public function saveDoseRules(Request $request)
    {
        abort_unless(Auth::user()->hasRole('Admin'), 403);

        $group = (string) $request->input('group', 'DS');
        $type  = (string) $request->input('type', 'adult');

        $keep = [];
        foreach ((array) $request->input('rules', []) as $row) {
            $key = trim((string) ($row['drug_key'] ?? ''));
            if ($key === '') { continue; }   // ไม่มีชื่อยา = แถวว่าง/ตั้งใจลบ

            $num = fn ($k) => isset($row[$k]) && trim((string) $row[$k]) !== '' ? $row[$k] : null;

            $values = [
                'group'        => $group,
                'patient_type' => $type,
                'drug_key'     => $key,
                'drug_label'   => trim((string) ($row['drug_label'] ?? $key)),
                'strength'     => trim((string) ($row['strength'] ?? '')) ?: null,
                'weight_min'   => $num('weight_min'),
                'weight_max'   => $num('weight_max'),
                'dose_mg'      => $num('dose_mg'),
                'max_mg'       => $num('max_mg'),
                'dose_text'    => trim((string) ($row['dose_text'] ?? '')) ?: null,
                'mgkg_min'     => $num('mgkg_min'),
                'mgkg_max'     => $num('mgkg_max'),
                'note'         => trim((string) ($row['note'] ?? '')) ?: null,
                'active'       => ! empty($row['active']),
                'sort'         => count($keep) + 1,
            ];

            $id = (int) ($row['id'] ?? 0);
            $rule = $id ? \App\Models\TbDoseRule::find($id) : null;
            if ($rule) { $rule->update($values); }
            else { $rule = \App\Models\TbDoseRule::create($values); }
            $keep[] = $rule->id;
        }

        \App\Models\TbDoseRule::where('group', $group)->where('patient_type', $type)
            ->whereNotIn('id', $keep ?: [0])->delete();

        flash('บันทึกเกณฑ์ขนาดยา (' . $group . ' · ' . $type . ') แล้ว ' . count($keep) . ' แถว', 'success');

        return redirect()->route('tb.doseRules', ['group' => $group, 'type' => $type]);
    }

    /**
     * บันทึกวิธีใช้ยาแต่ละตัว (Sig) ของบัตร — พิมพ์ลงสติกเกอร์ยา (คลินิกขอ 2569-09-29)
     *
     * รับเฉพาะค่าที่อยู่ในตัวเลือก ค่าอื่นทิ้ง ฉลากที่คนไข้ถือกลับบ้านต้องไม่มีข้อความแปลก ๆ
     * ยาที่ไม่ได้ส่งมาในรอบนี้ (เช่นเคยใช้แล้วเลิก) เก็บวิธีใช้เดิมไว้ ไม่ลบ
     * เผื่อกลับมาใช้ยาตัวนั้นอีกจะได้ไม่ต้องตั้งใหม่
     */
    public function saveSigs(Request $request, $id)
    {
        $reg = TbRegistration::findOrFail($id);
        $all = (array) ($reg->drug_sigs ?? []);

        foreach ((array) $request->input('sig', []) as $key => $row) {
            $key = trim((string) $key);
            if ($key === '' || ! is_array($row) || mb_strlen($key) > 20) { continue; }

            $sig = self::cleanTbSig($row);
            // ไม่ได้เลือกความถี่และไม่ได้พิมพ์อะไร = ยังไม่ได้ตั้งวิธีใช้ ไม่ต้องเก็บ (ฉลากจะใช้ข้อความแบบเดิม)
            if (! $sig) { unset($all[$key]); continue; }
            // ฟอร์มรุ่นเก่าไม่มีช่องความแรง — คงความแรงที่กล่องสั่งยาตั้งไว้ ไม่ให้หายตอนกดบันทึกวิธีใช้
            if (! isset($sig['strength']) && isset($all[$key]['strength'])) { $sig['strength'] = $all[$key]['strength']; }
            $all[$key] = $sig;
        }

        $reg->drug_sigs = $all ?: null;
        $reg->save();

        flash('บันทึกวิธีใช้ยาแล้ว — สติกเกอร์ยาจะพิมพ์ตามนี้', 'success');

        // กล่องวิธีใช้ยารวมเข้ากล่อง "สั่งยาครั้งนี้" แล้ว (2569-10-02) — หน้าที่เปิดค้างกดมาก็กลับไปที่กล่องใหม่
        return $this->backToSection($reg, 'tb-order');
    }

    /**
     * ทำความสะอาดวิธีใช้ยาหนึ่งตัว — ใช้ทั้ง saveSigs (ฟอร์มเดิม) และ saveOrder (กล่องสั่งยาครั้งนี้)
     * ชุดเดียวกับหน้าสั่งยาคลินิกหลัก (รับ "1/2" · ช่วงเวลาอย่างเดียวก็เก็บ) แล้วจำกัดตัวเลือกให้เหลือของบัตร TB
     * (หน่วยมี ขวด/หลอด · ไม่มีจำนวนวัน เพราะบัตร TB จ่ายตามรอบนัด) · คืน null = ยังไม่ได้ตั้ง
     */
    private static function cleanTbSig(array $row): ?array
    {
        $pick = fn ($v, array $opts) => array_key_exists((string) $v, $opts) ? (string) $v : '';
        $sig = \App\Support\DrugSig::clean([
            'freq' => $pick($row['freq'] ?? '', TbRegistration::$sigFreq),
        ] + $row);
        if (! $sig) { return null; }

        $sig['unit']   = in_array((string) ($row['unit'] ?? ''), TbRegistration::$sigUnit, true) ? (string) $row['unit'] : 'เม็ด';
        $sig['route']  = $pick($row['route'] ?? '', TbRegistration::$sigRoute) ?: 'PO';
        $sig['timing'] = $pick($row['timing'] ?? '', TbRegistration::$sigTiming);
        unset($sig['days']);
        // ยาอื่นที่ไม่ใช่ยาวัณโรค: จำนวนจ่ายทั้งหมด (เช่น Para 10 เม็ด) — ใช้บนสติกเกอร์แทนการคิดจากจำนวนวัน
        $disp = trim(str_replace(',', '.', (string) ($row['dispense'] ?? '')));
        if (preg_match('/^\d+(\.\d+)?$/', $disp)) { $sig['dispense'] = $disp; }
        // ความแรงต่อเม็ดที่เลือกในกล่องสั่งยา — ให้สติกเกอร์/หน้า DOT แปลง มก. เป็นเม็ดตรงกับที่สั่ง
        $str = trim((string) ($row['strength'] ?? ''));
        if (preg_match('/^\d+(\.\d+)?$/', $str)) { $sig['strength'] = $str; }

        return $sig;
    }

    /* ======================================================================
     * กล่อง "สั่งยาครั้งนี้" (คลินิกขอ 2569-10-02)
     * รวม สูตรยา · น้ำหนัก/ขนาดยา · วิธีใช้ยา ที่เคยกระจายอยู่ 4 กล่อง ให้เหลือกล่องเดียวทำทีละขั้น
     * กดบันทึกครั้งเดียว = (ถ้าเปลี่ยนสูตร/ระยะ) ขึ้นช่วงสูตรใหม่ + สร้างแถวรอบสั่งยา + บันทึกวิธีใช้
     * ====================================================================== */

    /** แปลงจำนวนต่อครั้ง ("1", "1.5", "1/2") เป็นตัวเลข — อ่านไม่ออก = null */
    private static function qtyNumber($v): ?float
    {
        $v = preg_replace('/\s+/', '', str_replace(',', '.', (string) $v));
        if (preg_match('#^(\d+)/(\d+)$#', $v, $m)) { return (int) $m[2] > 0 ? (int) $m[1] / (int) $m[2] : null; }
        return preg_match('/^\d+(\.\d+)?$/', $v) ? (float) $v : null;
    }

    private static function fmtNum($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    }

    /** รหัสยาที่ตารางขนาดยานับเป็น "เม็ด/วัน" (ยาเม็ดรวม + B6) — เกณฑ์เขียนเป็น "N เม็ด/วัน" */
    private static function tabletDrugKeys(): array
    {
        static $keys = null;
        return $keys = $keys ?? \App\Models\TbDoseRule::whereNotNull('dose_text')->get(['drug_key', 'dose_text'])
            ->filter(fn ($r) => preg_match('#^\s*[\d.]+\s*เม็ด/วัน\s*$#u', $r->dose_text))
            ->pluck('drug_key')->push('B6')->unique()->values()->all();
    }

    /**
     * ยาหนึ่งตัวในตารางสั่งยา กรอกจำนวนแบบไหน
     *   tab      = นับเม็ด (ยาเม็ดรวม / B6) → ช่องในตารางเก็บ "เม็ด/วัน"
     *   strength = ยาเม็ดเดี่ยวที่รู้ความแรง → กรอกเป็นเม็ด แล้วเก็บในตารางเป็น มก./วัน (ตามข้อมูลเดิม)
     *   mg       = ยาฉีด / ไม่รู้ความแรง → กรอกเป็น มก. ตรง ๆ
     */
    private static function orderDrugKind(TbRegistration $reg, string $key): array
    {
        if (in_array($key, self::tabletDrugKeys(), true) || preg_match('/fdc/i', $key)) {
            return ['kind' => 'tab', 'strengths' => [], 'prefer' => null];
        }
        $ts = $reg->tabletStrengths($key);
        if ($ts['inj'] || ! $ts['strengths']) {
            return ['kind' => 'mg', 'strengths' => [], 'prefer' => null, 'inj' => $ts['inj']];
        }
        return ['kind' => 'strength', 'strengths' => $ts['strengths'], 'prefer' => $ts['prefer']];
    }

    /**
     * ตัวเลือกสูตรยาในกล่องสั่งยา — จากสูตรมาตรฐานที่ผูกรายการยาไว้ + Individualized (เลือกยาเอง)
     * สูตรเม็ดรวมที่มีทั้ง 4FDC และ 2FDC แยกเป็นสองตัวเลือกตามระยะ ให้ยาถูกเลือกจากตัวเลือก ไม่ต้องติ๊กเอง
     * (เดิมติ๊กผิดแล้วได้ทั้งสองตัวในรอบเดียว — คลินิกแจ้ง 2569-10-02)
     */
    private function regimenOrderOptions(TbRegistration $reg): array
    {
        $groupOf = function ($r) {
            $c = (string) $r->category . ' ' . $r->name;
            if (preg_match('/ป้องกัน|TPT/iu', $c)) { return 'TPT'; }
            if (preg_match('/ดื้อ|MDR|XDR|DR\b/iu', $c)) { return 'Resist'; }
            return 'DS';
        };
        $opts = [];
        foreach (\App\Models\TbRegimen::where('active', true)->orderBy('sort')->orderBy('id')->get() as $r) {
            if (! $r->drugs) { continue; }   // สูตรที่ไม่ได้ผูกยา สร้างแถวสั่งยาไม่ได้ — ใช้ Individualized แทน
            // B6 เติมให้เองตามกติกาเดิม (TPT ไม่ใช้) ไม่ต้องอยู่ในตัวเลือก
            $drugs = array_values(array_diff(array_keys($r->drugs), ['B6']));
            $g = $groupOf($r);
            if (in_array('4FDC', $drugs, true) && in_array('2FDC', $drugs, true)) {
                $opts['r' . $r->id . ':1'] = ['text' => $r->regimen . ' — ระยะเข้มข้น (4FDC)', 'regimen' => $r->regimen,
                    'category' => $r->category, 'drugs' => array_values(array_diff($drugs, ['2FDC'])), 'group' => $g, 'phase' => 1, 'split' => true];
                $opts['r' . $r->id . ':2'] = ['text' => $r->regimen . ' — ระยะต่อเนื่อง (2FDC)', 'regimen' => $r->regimen,
                    'category' => $r->category, 'drugs' => array_values(array_diff($drugs, ['4FDC'])), 'group' => $g, 'phase' => 2, 'split' => true];
                continue;
            }
            $opts['r' . $r->id] = ['text' => $r->regimen . ($r->category ? ' · ' . $r->category : ''), 'regimen' => $r->regimen,
                'category' => $r->category, 'drugs' => $drugs, 'group' => $g,
                'phase' => TbRegistration::phaseOfDrugs($drugs, $r->regimen), 'split' => false];
        }
        // สูตรเดี่ยวที่ยาเหมือนตัวเลือกแยกระยะทุกตัว (2HRZE (FDC) = ระยะเข้มข้นของ 2HRZE/4HR (FDC)) ซ่อนไว้ ไม่ให้ซ้ำ
        $splitSets = collect($opts)->where('split', true)->map(fn ($o) => implode(',', collect($o['drugs'])->sort()->all()))->all();
        foreach ($opts as $k => $o) {
            if (! $o['split'] && in_array(implode(',', collect($o['drugs'])->sort()->all()), $splitSets, true)) { unset($opts[$k]); }
        }
        $opts['custom'] = ['text' => 'Individualized — เลือกยาเอง', 'regimen' => 'Individualized', 'category' => null,
            'drugs' => [], 'group' => \App\Models\TbRegimen::drugGroupFor((int) $reg->registry_type), 'phase' => null, 'split' => false];

        return $opts;
    }

    /** ยาวัณโรคของสูตรที่ใช้อยู่ (ไม่รวม B6 และยาอื่นที่ไม่ใช่ยาวัณโรค) — ใช้เทียบว่าเปลี่ยนสูตร/ระยะหรือไม่ */
    private static function tbDrugSet(array $keys): array
    {
        $out = array_values(array_unique(array_filter(array_map('strval', $keys),
            fn ($k) => $k !== '' && $k !== 'B6' && TbRegistration::isTbDrugKey($k))));
        sort($out);
        return $out;
    }

    /** สูตรที่ใช้อยู่ตอนนี้ + ตัวเลือกที่ตรงกับมัน (เลือกไว้ให้ตอนเปิดกล่อง) */
    private function currentOrderOption(TbRegistration $reg, array $opts, ?\App\Models\TbRegimenChange $period = null): array
    {
        $setKey  = fn ($d) => implode(',', self::tbDrugSet($d));
        if ($period) {
            // visit ย้อนหลังที่อยู่ในช่วงสูตรเก่า (2569-10-26) — สูตร/ตัวยาของช่วงนั้น ไม่ใช่สูตรที่ใช้อยู่ตอนนี้
            $curText = trim((string) $period->regimen);
            $drugs = (array) ($period->drugs ?? []);
            if (! $drugs) { foreach ($opts as $k => $o) { if ($k !== 'custom' && $o['regimen'] === $curText) { $drugs = $o['drugs']; break; } } }
            $curSet = self::tbDrugSet($drugs);
        } else {
            $cur     = \App\Models\TbRegimenChange::currentFor($reg->id);
            $curText = trim((string) ($cur->regimen ?? $reg->regimen));
            $curSet  = self::tbDrugSet($reg->doseDrugKeys());
        }

        // ระยะของสูตรเม็ดรวมที่ยังรวมสองระยะไว้: ดูจากรอบสั่งยาล่าสุดก่อน แล้วค่อยดูเดือนที่รักษา
        $lastQ = $reg->monthlyDoses()->whereNotNull('doses')->reorder()->orderByDesc('sort')->orderByDesc('id');
        if ($period) {   // ช่วงเก่า: รอบล่าสุดภายในช่วงนั้น · ยังไม่มีรอบ = ระยะเข้มข้น
            $lastQ->where('dispensed_date', '>=', (string) $period->start_date);
            if ($period->end_date) { $lastQ->where('dispensed_date', '<=', (string) $period->end_date); }
        }
        $last = $lastQ->first();
        $lastKeys = array_keys(array_filter((array) optional($last)->doses, fn ($v) => trim((string) $v) !== ''));
        $phase = (in_array('4FDC', $lastKeys, true) xor in_array('2FDC', $lastKeys, true))
            ? (in_array('4FDC', $lastKeys, true) ? 1 : 2)
            : ($period ? (in_array('H', $lastKeys, true) && ! in_array('Z', $lastKeys, true) ? 2 : 1) : ($reg->monthsOnTreatment() < 2 ? 1 : 2));
        if ($period && ! $curSet) {
            // ช่วงเก่าที่ชื่อสูตรไม่มีในแคตตาล็อกตรง ๆ เช่น "2HRZE/4HR" — เลือกส่วนของระยะนั้น (2HRZE = เข้มข้น · 4HR = ต่อเนื่อง)
            $parts = array_values(array_filter(array_map('trim', explode('/', $curText))));
            $part  = $parts ? $parts[min($phase, count($parts)) - 1] : null;
            foreach ($opts as $k => $o) {
                if ($k !== 'custom' && $part && $o['regimen'] === $part) { $curSet = self::tbDrugSet($o['drugs']); $curText = $part; break; }
            }
        }

        // บัตรที่ยังไม่ระบุสูตรเลย — ตัวยาที่เห็นเป็นแค่ชุดตั้งต้น H R Z E ห้ามเลือกให้ ต้องให้หมอเลือกเอง
        if ($curText === '') {
            return ['key' => '', 'curText' => '', 'curSet' => $curSet, 'customDrugs' => [], 'customText' => 'Individualized'];
        }

        $pick = null;
        foreach ($opts as $k => $o) {   // 1) ชื่อสูตรและตัวยาตรงกันพอดี
            if ($k !== 'custom' && $o['regimen'] === $curText && $setKey($o['drugs']) === implode(',', $curSet)) { $pick = $k; break; }
        }
        if (! $pick && in_array('4FDC', $curSet, true) && in_array('2FDC', $curSet, true)) {
            foreach ($opts as $k => $o) {   // 2) สูตรเม็ดรวมสองระยะ → เลือกระยะ
                if ($o['split'] && ($o['phase'] ?? null) === $phase && ($o['regimen'] === $curText || ! $pick)) { $pick = $k; }
            }
        }
        if (! $pick) {
            foreach ($opts as $k => $o) {   // 3) ตัวยาตรงกัน
                if ($k !== 'custom' && $curSet && $setKey($o['drugs']) === implode(',', $curSet)) { $pick = $k; break; }
            }
        }

        return [
            'key'     => $pick ?: ($curSet ? 'custom' : ''),
            'curText' => $curText,
            'curSet'  => $curSet,
            // Individualized: ยาที่ใช้อยู่ติ๊กไว้ให้ · ชื่อสูตรคงตามเดิม
            'customDrugs' => $pick ? [] : $curSet,
            'customText'  => $pick ? 'Individualized' : ($curText ?: 'Individualized'),
        ];
    }

    /** ข้อมูลทั้งหมดของกล่อง "สั่งยาครั้งนี้" — คิดฝั่งเซิร์ฟเวอร์ครั้งเดียวตอนเปิดหน้า */
    private function orderBoxData(TbRegistration $reg, array $doseRows, int $maxMonth, $lastWeight, ?string $weightSource, string $doseType, ?string $asOf = null): array
    {
        // $asOf = วันที่ของ visit ที่ลงย้อนหลัง (หน้าตรวจ) — รอบยา/วันนัดคิดจากวันนั้นแทนวันนี้
        $today = $asOf ?: \App\Models\VisitAppointment::todayBe();
        $todayAd = \Carbon\Carbon::createFromFormat('Y-m-d', Utillity::BE2AD($today))->startOfDay();

        // แถวที่สั่งไปแล้ววันนี้ — กดบันทึกซ้ำ = แก้แถวเดิม ไม่สร้างรอบซ้อน
        $todayRow = $reg->monthlyDoses->filter(fn ($r) => TbRegistration::beDate($r->dispensed_date) === $today)->sortBy('id')->last();

        // ช่วงนับรอบของวันนี้ (หรือวันของ visit ย้อนหลัง) — อยู่ช่วงสูตรเก่า = ใช้สูตร/รอบของช่วงนั้น ไม่แตะสูตรปัจจุบัน (2569-10-26)
        $segs   = $reg->exists ? $reg->doseSegments() : null;
        $segIdx = $segs ? $reg->segmentIndexFor($today, $segs) : 0;
        $pastSeg = $segs && $segIdx < count($segs) - 1 ? $segs[$segIdx] : null;
        $period = $pastSeg ? \App\Models\TbRegimenChange::coveringFor($reg->id, $today) : null;
        $inSeg  = fn ($r) => ! $segs || $reg->segmentIndexFor($r->dispensed_date, $segs) === $segIdx;

        // วันนัดครั้งหน้า: นัดคลินิกวัณโรคที่ยังเปิดอยู่และยังมาไม่ถึง (ใกล้สุด) · ไม่มีนัด = 28 วัน
        // ใช้ whereNull เต็มชื่อแทน scope open() — ตอน join กับ visits ชื่อ closed_at ซ้ำกันสองตาราง
        $appt = \App\Models\VisitAppointment::query()
            ->whereNull('visit_appointments.closed_at')
            ->join('visits', 'visits.id', '=', 'visit_appointments.visit_id')
            ->where('visits.clinic', 2)
            ->where('visit_appointments.patient_id', $reg->patient_id)
            ->where('visit_appointments.appointment_date', '>', $today)
            ->orderBy('visit_appointments.appointment_date')->orderBy('visit_appointments.id')
            ->first(['visit_appointments.*']);
        if ($todayRow && $todayRow->dispensed_days) {
            $days = (int) $todayRow->dispensed_days;
            $dateSource = 'ตามที่สั่งไว้วันนี้ (' . $days . ' วัน)';
        } elseif ($appt && ($d = (int) $todayAd->diffInDays(\Carbon\Carbon::createFromFormat('Y-m-d', Utillity::BE2AD($appt->appointment_date))->startOfDay(), false)) <= 120) {
            $days = $d;
            $dateSource = 'จากนัดในระบบ ' . Utillity::db2thDate($appt->appointment_date) . ($appt->appt_time ? ' ' . $appt->appt_time . ' น.' : '');
        } elseif ($appt) {
            // visit ย้อนหลังนาน ๆ — นัดถัดไปในระบบอยู่อีกเป็นปี ไม่ใช่นัดของรอบนั้น (เคยได้ 120 วันจากนัดปัจจุบัน 2569-10-26)
            $days = 28;
            $dateSource = 'ลงย้อนหลัง — ตั้งไว้ 28 วัน แก้ตามที่จ่ายจริง';
        } else {
            $days = 28;
            $dateSource = 'ยังไม่มีนัดครั้งหน้าในระบบ — ตั้งไว้ 28 วัน';
        }
        $days = max(1, min(120, $days ?: 28));
        $nextDate = Utillity::AD2BE($todayAd->copy()->addDays($days)->format('Y-m-d'));

        // รอบ: แถวมาตรฐานที่ยังว่าง (ตั้งต้น = แถวถัดจากรอบล่าสุด แบบเดียวกับตารางเดิม) · มีแถววันนี้ = รอบของแถวนั้น
        $labels = [];
        $default = null;
        foreach ($doseRows as $r) {
            if (! $r['row'] && $r['standard']) {
                $labels[$r['label']] = $r['text'];
                if ($default === null && $r['visible']) { $default = $r['label']; }
            }
        }
        if ($todayRow) {
            $tl = trim((string) $todayRow->label) !== '' ? (string) $todayRow->label : (string) $todayRow->month_no;
            $labels = [$tl => $todayRow->labelForHumans()] + $labels;
            $default = $tl;
        }
        $default = $default ?? array_key_first($labels);
        // visit ย้อนหลัง: รอบตั้งต้นคิดจากวันของ visit เทียบต้นช่วงนับรอบ (แบบตารางเสมหะ) ไม่ใช่ "แถวถัดจากรอบล่าสุด"
        // ลงไม่เรียงวัน (01/04 ก่อน แล้วค่อยย้อนไป 13/03) เคยได้รอบ 1 ทั้งที่เป็นวันเริ่มยา (คลินิกแจ้ง 2569-10-27)
        if ($asOf && ! $todayRow && $segs && ($st = $segs[$segIdx]['start'] ?? null)) {
            $dd = (int) \Carbon\Carbon::parse(Utillity::BE2AD($st))->diffInDays($todayAd, false);
            $want = $dd <= 7 ? '0' : ($dd <= 21 ? '2wk' : (string) max(1, (int) round($dd / 30.44)));
            if (isset($labels[$want])) { $default = $want; }
        }

        $opts = $this->regimenOrderOptions($reg);
        $cur  = $this->currentOrderOption($reg, $opts, $period);
        // ช่วงสูตรเก่าที่สั่งไว้แล้ววันนั้น: สูตรในกล่องต้องเป็นตัวยาที่บันทึกไว้ ไม่ใช่ชื่อสูตรของช่วง
        // (ช่วงเก่าไม่ขึ้นช่วงสูตรใหม่ตอนบันทึก — เดิมเปิดกล่องใหม่แล้วกลับเป็น 2HRZE ทั้งที่บันทึก RZE-Lfx ไป ดูเหมือนบันทึกไม่ติด 2569-10-26)
        if ($pastSeg && $todayRow) {
            $rowSet = self::tbDrugSet(array_keys(array_filter((array) $todayRow->doses, fn ($v) => trim((string) $v) !== '')));
            if ($rowSet && $rowSet !== $cur['curSet']) {
                $pick = null;
                foreach ($opts as $k => $op) { if ($k !== 'custom' && self::tbDrugSet($op['drugs']) === $rowSet) { $pick = $k; break; } }
                $cur = ['key' => $pick ?: 'custom', 'curText' => $pick ? $opts[$pick]['regimen'] : 'Individualized', 'curSet' => $rowSet,
                        'customDrugs' => $pick ? [] : $rowSet, 'customText' => 'Individualized'];
            }
        }
        $groups = \App\Models\TbDrug::groupedForPicker();

        // กลุ่มยาของ Individualized = กลุ่มที่ยาที่ใช้อยู่ตรงมากสุด · เสมอกันใช้กลุ่มตามประเภททะเบียน
        $activeGroup = \App\Models\TbRegimen::drugGroupFor((int) $reg->registry_type);
        $score = collect($groups)->map(fn ($d) => count(array_intersect(array_keys($d), $cur['curSet'])));
        if ($score->max() > 0 && ($score[$activeGroup] ?? 0) < $score->max()) { $activeGroup = $score->search($score->max()); }

        $ltbi = (int) $reg->registry_type === TbRegistration::TYPE_LTBI;

        // ทุกยาที่อาจขึ้นในตารางสั่งยา (ซ่อนไว้ แสดงตามสูตรที่เลือก) — หน้าจอสลับสูตรได้โดยไม่ต้องโหลดใหม่
        $universe = [];
        foreach ($opts as $o) { foreach ($o['drugs'] as $k) { $universe[$k] = true; } }
        foreach ($groups as $g => $list) { foreach (array_keys($list) as $k) { $universe[$k] = true; } }
        foreach ($cur['curSet'] as $k) { $universe[$k] = true; }
        $todayDoses = (array) optional($todayRow)->doses;
        foreach (array_keys($todayDoses) as $k) { if (TbRegistration::isTbDrugKey($k)) { $universe[$k] = true; } }
        unset($universe['B6']);   // B6 ต่อท้ายเสมอ · ขึ้นเฉพาะสูตรที่มียาตาม TbRegistration::$b6With (หน้าจอซ่อน/โชว์เอง)
        $universe = array_keys($universe);
        $universe[] = 'B6';

        $names = \App\Models\TbDrug::where('active', true)->orderBy('sort')->get()
            ->groupBy(fn ($d) => $d->shortKey())->map(fn ($l) => $l->first()->name)
            ->union(\App\Models\TbDoseRule::whereNotNull('drug_label')->pluck('drug_label', 'drug_key'));

        // รอบล่าสุดก่อนวันนี้ — ค่าตั้งต้นของยาที่ไม่มีเกณฑ์ตามน้ำหนัก
        $prev = $reg->monthlyDoses->filter(fn ($r) => $r->doses && (! $todayRow || $r->id !== $todayRow->id) && $inSeg($r))
            ->sortBy(fn ($r) => [(int) $r->sort, $r->id])->last();

        $rows = [];
        foreach ($universe as $k) {
            $kind = self::orderDrugKind($reg, $k);
            $sig  = $reg->sigFor($k);
            // 2FDC ยังไม่เคยตั้งวิธีใช้ (เพิ่งเข้าระยะต่อเนื่อง) — ใช้เวลากินยาเดิมของ 4FDC ตั้งต้น และกลับกัน
            $pair = ['4FDC' => '2FDC', '2FDC' => '4FDC'][$k] ?? null;
            if (! $sig && $pair && $reg->sigFor($pair)) { $sig = array_diff_key($reg->sigFor($pair), ['qty' => 1, 'strength' => 1, 'dispense' => 1]); }
            $per  = TbRegistration::$sigFreq[$sig['freq'] ?? 'OD'][1] ?? 1;
            $perMul = $per >= 1 ? $per : 1;

            // จำนวนต่อครั้งจากขนาดยาในแถว (ต่อวัน) — ใช้กับแถววันนี้และรอบก่อน
            $fromDose = function ($amount) use ($reg, $k, $kind, $perMul, $sig) {
                $amount = trim((string) $amount);
                if ($amount === '') { return [null, null]; }
                $dt = $reg->doseTablets($k, $amount);
                if ($kind['kind'] === 'tab') { return $dt['tabs'] ? [self::fmtNum($dt['tabs'] / $perMul), null] : [null, null]; }
                if ($kind['kind'] === 'strength') {
                    return $dt['kind'] === 'tab' && $dt['strength'] ? [self::fmtNum($dt['tabs'] / $perMul), $dt['strength']] : [null, null];
                }
                return $dt['mg'] ? [self::fmtNum($dt['mg'] / $perMul), null] : [null, null];
            };
            [$qtyToday, $strToday] = $fromDose($todayDoses[$k] ?? '');
            [$qtyPrev, $strPrev]   = $fromDose(((array) optional($prev)->doses)[$k] ?? '');

            $strength = $strToday ?? (isset($sig['strength']) ? (float) $sig['strength'] : null) ?? $strPrev ?? $kind['prefer'];
            if ($kind['kind'] === 'strength' && ! in_array((float) $strength, $kind['strengths'], true)) {
                $strength = $kind['prefer'] && in_array($kind['prefer'], $kind['strengths'], true) ? $kind['prefer'] : end($kind['strengths']);
            }

            $rows[$k] = $kind + [
                'key'      => $k,
                'name'     => $names[$k] ?? $k,
                'strength' => $strength,
                'qty'      => $qtyToday,     // มีค่า = แถวที่สั่งไว้แล้ววันนี้ หน้าจอไม่คิดทับ
                'fallback' => $qtyPrev,      // ยาที่ไม่มีเกณฑ์ ใช้จำนวนรอบก่อน
                'sig'      => $sig + ['freq' => 'OD', 'route' => 'PO'],
            ];
        }

        // ยาอื่นที่ไม่ใช่ยาวัณโรค (Para, Brufen ...) — แสดงเฉพาะที่สั่งไว้วันนี้ (แก้รอบเดิม) ไม่ยกมาจากรอบก่อน
        $others = [];
        foreach ($todayDoses as $k => $amt) {
            if (TbRegistration::isTbDrugKey((string) $k) || trim((string) $amt) === '') { continue; }
            $sig = $reg->sigFor((string) $k);
            // ไม่มีวิธีใช้ในบัตร (ลงย้อนหลังในช่วงสูตรเก่าไม่เขียนวิธีใช้ลงบัตร) — อ่านจากข้อความขนาดยาที่บันทึกไว้
            // "1 หลอด เมื่อมีอาการ" / "2 เม็ด/วัน" / "1 ซอง/ครั้ง" — ไม่งั้นเปิดกล่องใหม่แล้วกลายเป็นเม็ด/วันละครั้ง (2569-10-27)
            if (! $sig && preg_match('/^\s*([\d.\/]+)\s*(\S+?)\s*(เมื่อมีอาการ|\/วัน|\/ครั้ง)?\s*$/u', (string) $amt, $mm)) {
                $sig = ['qty' => $mm[1], 'unit' => in_array($mm[2], TbRegistration::$sigUnit, true) ? $mm[2] : 'เม็ด',
                        'freq' => ($mm[3] ?? '') === 'เมื่อมีอาการ' ? 'PRN' : 'OD'];
            }
            $others[] = ['name' => (string) $k, 'qty' => $sig['qty'] ?? '', 'sig' => $sig + ['freq' => 'OD', 'route' => 'PO', 'unit' => 'เม็ด']];
        }

        // เกณฑ์ขนาดยาตามน้ำหนัก ทุกกลุ่ม (กลุ่มตามประเภททะเบียนมาก่อน) — หน้าจอคิดใหม่ทุกครั้งที่แก้น้ำหนัก
        $doseGroup = \App\Models\TbRegimen::drugGroupFor((int) $reg->registry_type);
        $rules = ['adult' => [], 'ped' => []];
        $all = \App\Models\TbDoseRule::where('active', true)->orderBy('sort')->get()
            ->sortBy(fn ($r) => $r->group === $doseGroup ? 0 : 1);
        foreach ($all->groupBy(fn ($r) => $r->group . '|' . $r->patient_type . '|' . $r->drug_key) as $list) {
            $f = $list->first();
            if (isset($rules[$f->patient_type][$f->drug_key])) { continue; }
            $rules[$f->patient_type][$f->drug_key] = [
                'label' => $f->drug_label, 'dose_text' => $f->dose_text,
                // ยา TPT ตัดอายุตามสูตร — หน้าจอเลือกแถวเด็ก/ผู้ใหญ่จากอายุนี้แทน 18 ปี
                'age_cut' => $f->group === 'TPT' ? (\App\Models\TbDoseRule::TPT_AGE_CUT[$f->drug_key] ?? null) : null,
                'max_mg' => $f->max_mg !== null ? (int) $f->max_mg : null,
                'mgkg_min' => (float) $f->mgkg_min, 'mgkg_max' => (float) $f->mgkg_max,
                'bands' => $list->map(fn ($r) => [
                    'min' => $r->weight_min !== null ? (float) $r->weight_min : null,
                    'max' => $r->weight_max !== null ? (float) $r->weight_max : null,
                    'dose' => $r->dose_mg !== null ? (int) $r->dose_mg : null,
                    'text' => $r->dose_text,
                ])->values(),
            ];
        }

        $latest = $reg->monthlyDoses->filter(fn ($r) => $r->doses && $inSeg($r))->sortBy(fn ($r) => [(int) $r->sort, $r->id])->last();

        return [
            // ไม่ใช่ null = visit ย้อนหลังที่อยู่ในช่วงสูตรเก่า — กล่องบอกพยาบาล และตอนบันทึกไม่แตะสูตร/วิธีใช้ปัจจุบัน
            'pastSeg' => $pastSeg,
            'today' => $today, 'todayRow' => $todayRow, 'appt' => $appt,
            'days' => $days, 'nextDate' => $nextDate, 'dateSource' => $dateSource,
            'weight' => $todayRow && $todayRow->weight ? (float) $todayRow->weight : ($lastWeight ? (float) $lastWeight : null),
            'weightSource' => $todayRow && $todayRow->weight ? 'ที่บันทึกไว้วันนี้' : $weightSource,
            'doseType' => $doseType,
            'labels' => $labels, 'defaultLabel' => $default, 'maxMonth' => $maxMonth,
            'opts' => $opts, 'cur' => $cur, 'groups' => $groups, 'activeGroup' => $activeGroup,
            'rows' => $rows, 'others' => $others, 'rules' => $rules, 'ltbi' => $ltbi,
            'latest' => $latest,
        ];
    }

    /**
     * แทรกช่วงสูตรย้อนหลัง (visit ย้อนหลังที่อยู่ในช่วงสูตรเก่า และจ่ายยาต่างจากสูตรของช่วงนั้น) — 2569-10-26
     * ตัดช่วงที่ครอบวันนั้นออกเป็นสองท่อน: ช่วงเดิมจบวันนั้น · ช่วงใหม่เริ่มวันนั้นถึงวันจบเดิม
     * ไม่แตะช่วงที่ใช้อยู่ตอนนี้ และไม่เปลี่ยนช่อง regimen ของบัตร (ต่างจาก openRegimenPeriod)
     * ช่วงที่เริ่มวันนั้นพอดี (บันทึกซ้ำ) = แก้ชื่อ/ตัวยาของช่วงนั้นแทนการแทรกเพิ่ม
     */
    private function insertPastRegimenPeriod(TbRegistration $reg, string $regimen, ?string $category, ?int $startMonth,
                                             string $date, string $reason, array $drugs): void
    {
        $cov = \App\Models\TbRegimenChange::coveringFor($reg->id, $date);
        $vals = ['regimen' => trim($regimen), 'category' => $category, 'drugs' => $drugs ?: null];
        if ($cov && TbRegistration::beDate($cov->start_date) === $date) { $cov->update($vals); return; }

        $next = \App\Models\TbRegimenChange::where('tb_registration_id', $reg->id)->whereNotNull('start_date')
            ->where('start_date', '>', $date)->orderBy('start_date')->orderBy('id')->first();
        $end = ($cov && $cov->end_date && $cov->end_date > $date) ? $cov->end_date : ($next->start_date ?? null);
        if ($cov) { $cov->update(['end_date' => $date]); }
        \App\Models\TbRegimenChange::create($vals + [
            'tb_registration_id' => $reg->id, 'start_month' => $startMonth, 'start_date' => $date, 'end_date' => $end,
            'reason' => mb_substr($reason, 0, 255), 'created_by' => Auth::id(),
        ]);
    }

    /** ช่วงสูตรยาใหม่ — ปิดช่วงที่ใช้อยู่ (ไม่ลบ) แล้วขึ้นช่วงใหม่ · ใช้ทั้ง saveRegimen และกล่องสั่งยา */
    private function openRegimenPeriod(TbRegistration $reg, string $regimen, ?string $category, ?int $startMonth,
                                       ?string $startDate, ?string $reason, array $drugs): \App\Models\TbRegimenChange
    {
        $prev = \App\Models\TbRegimenChange::currentFor($reg->id);
        if ($prev) {
            $prev->update(['end_date' => $startDate ?: \App\Models\VisitAppointment::todayBe()]);
        }

        $rc = \App\Models\TbRegimenChange::create([
            'tb_registration_id' => $reg->id,
            'regimen'     => trim($regimen),
            'category'    => $category,
            'start_month' => $startMonth,
            'start_date'  => $startDate,
            'reason'      => $reason,
            'drugs'       => $drugs ?: null,
            'created_by'  => Auth::id(),
        ]);

        // ช่อง regimen ของบัตรเก็บ "สูตรที่ใช้อยู่" ไว้ให้หน้าอื่นที่อ่านช่องนี้เห็นค่าล่าสุด
        $reg->update(['regimen' => trim($regimen)]);

        return $rc;
    }

    /** เดือนของแถวจากป้าย: "3" = เดือน 3 · "2wk" = เดือน 0 · ป้ายอื่นใช้ค่าที่ส่งมา */
    private static function monthOfLabel(string $label, int $fallback): int
    {
        if (preg_match('/^\d+$/', $label)) { return (int) $label; }
        if (preg_match('/^(\d+)\s*wk$/i', $label, $m)) { return (int) floor(((int) $m[1]) / 4.345); }
        return $fallback;
    }

    /** เขียนแถวรอบสั่งยา (สร้าง/แก้) — sort คิดจากป้ายเสมอ ใช้ร่วมกับ saveDoses */
    private static function writeDoseRow(TbRegistration $reg, ?\App\Models\TbMonthlyDose $existing, array $values, string $label, int $monthNo): \App\Models\TbMonthlyDose
    {
        // sort = ตำแหน่งตามเวลา × 100 (เดือน 1 = 100 · 2 สัปดาห์ = 46) ใช้หา "สั่งยาครั้งล่าสุด"
        $values += ['label' => $label, 'month_no' => $monthNo,
                    'sort' => \App\Models\TbMonthlyDose::sortValue($label, $monthNo)];
        if ($existing) { $existing->update($values); return $existing; }
        return \App\Models\TbMonthlyDose::create($values + ['tb_registration_id' => $reg->id]);
    }

    /**
     * บันทึกกล่อง "สั่งยาครั้งนี้" — ทำทั้งหมดในทรานแซกชันเดียว
     *   1) สูตร/ระยะเปลี่ยนจากที่ใช้อยู่ → ขึ้นช่วงสูตรใหม่ (ปิดช่วงเดิม) เหมือน saveRegimen
     *   2) แถวรอบสั่งยา: วันนี้มีแล้ว = แก้แถวนั้น · ยังไม่มี = สร้างรอบใหม่ (น้ำหนัก วันที่ จำนวนวัน ขนาดยา)
     *   3) วิธีใช้ยาแต่ละตัว (ที่เดียวกับ saveSigs) ให้สติกเกอร์/ใบกินยาใช้
     * ขนาดยาในแถวคิดฝั่งเซิร์ฟเวอร์จากจำนวนต่อครั้ง × ความถี่ ไม่เชื่อตัวเลขรวมจากหน้าจอ
     * หน่วยในตารางคงแบบเดิม: ยาเม็ดรวม/B6 = เม็ด/วัน · ยาเม็ดเดี่ยว = มก./วัน · ยารายสัปดาห์ = ต่อครั้ง
     */
    public function saveOrder(Request $request, $id)
    {
        $reg = TbRegistration::with('monthlyDoses')->findOrFail($id);

        $o = $this->buildOrder($request, $reg);
        if (is_string($o)) {
            return $this->backToSection($reg, 'tb-order')->withInput()->withErrors(['order' => $o]);
        }
        $row = $this->persistOrder($reg, $o, $request);

        flash('บันทึกการสั่งยา ' . $row->labelForHumans() . ' แล้ว (' . (int) $request->input('days') . ' วัน)'
            . ($o['changed'] ? ' · ' . (! empty($o['restart']) ? 'Off สูตรเดิม เริ่มนับรอบใหม่ สูตร ' : 'เปลี่ยนสูตรเป็น ') . $o['regText'] . ' (สูตรเดิมเก็บไว้ในประวัติ)' : '')
            . ' — พิมพ์สติกเกอร์/ใบกินยาได้จากกล่องสั่งยา', 'success');

        return $this->backToSection($reg, 'tb-order')->with('tb_order_row', $row->id);
    }

    /**
     * อ่านค่าจากกล่อง "สั่งยาครั้งนี้" → สิ่งที่จะบันทึก (ยังไม่เขียนอะไรลงฐาน) · คืน string = ข้อความแจ้งผู้ใช้
     * แยกออกมา (2569-10-05) ให้กล่องเดียวกันในหน้าตรวจ OPD (Concerns\TbVisitOrder) ใช้ตรรกะชุดเดียวกับบัตร
     * $reg ที่ยังไม่มีในฐาน (คนไข้ไม่มีบัตร) = ไม่มีรอบ/ประวัติสูตร · TPT หรือไม่ดูจากสูตรที่เลือกแทนประเภททะเบียน
     */
    private function buildOrder(Request $request, TbRegistration $reg, ?string $asOf = null)
    {
        // $asOf: visit ที่ลงย้อนหลัง — รอบในบัตรลงวันที่จ่าย = วันของ visit (ไม่ใช่วันที่กดบันทึก)
        $today = $asOf ?: \App\Models\VisitAppointment::todayBe();

        $request->validate([
            'weight' => ['nullable', 'numeric', 'min:1', 'max:250'],
            'days'   => ['required', 'integer', 'min:1', 'max:120'],
            'label'  => ['required', 'string', 'max:30'],
            'label_custom' => ['nullable', 'string', 'max:30'],
            'month_no' => ['nullable', 'integer', 'min:0', 'max:31'],
            'regimen_opt' => ['required', 'string', 'max:40'],
            'regimen_text' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
            'drugs'  => ['nullable', 'array'],
            'items'  => ['nullable', 'array'],
            'other'  => ['nullable', 'array'],
        ], [
            'days.required' => 'กรุณาระบุจำนวนวันที่จ่ายยา', 'days.*' => 'จำนวนวันที่จ่ายต้องเป็น 1-120 วัน',
            'weight.*' => 'น้ำหนักไม่ถูกต้อง', 'regimen_opt.required' => 'กรุณาเลือกสูตรยา',
        ]);
        $back = fn ($msg) => $msg;   // ผู้เรียกแปลงเป็น redirect (บัตร) หรือ JSON (หน้าตรวจ) เอง

        // ---- สูตรยาที่เลือก ----
        $opts = $this->regimenOrderOptions($reg);
        $key  = (string) $request->input('regimen_opt');
        if (! isset($opts[$key])) { return $back('ไม่พบสูตรยาที่เลือก โหลดหน้าใหม่แล้วลองอีกครั้ง'); }
        $opt = $opts[$key];
        if ($key === 'custom') {
            $drugs = collect((array) $request->input('drugs', []))->map(fn ($k) => trim((string) $k))
                ->filter(fn ($k) => preg_match('/^[A-Za-z0-9\-]{1,20}$/', $k) && $k !== 'B6')->unique()->values()->all();
            if (! $drugs) { return $back('Individualized — ติ๊กยาอย่างน้อย 1 ตัว'); }
            $regText = trim((string) $request->input('regimen_text')) ?: 'Individualized';
        } else {
            $drugs = $opt['drugs'];
            $regText = $opt['regimen'];
        }
        // ไม่มีบัตร (กล่องในหน้าตรวจ) ไม่มีประเภททะเบียน — ดูจากกลุ่มของสูตรที่เลือก
        $ltbi = $reg->exists ? (int) $reg->registry_type === TbRegistration::TYPE_LTBI : ($opt['group'] ?? '') === 'TPT';
        // B6 เฉพาะสูตรที่มี Isoniazid / Cycloserine ตามมาตรฐาน (2569-10-12 · เดิมทุกสูตรยกเว้น TPT)
        $tbKeys = TbRegistration::needsB6($drugs) ? array_merge($drugs, ['B6']) : $drugs;

        // ---- ยาแต่ละตัว: จำนวนต่อครั้ง → ขนาดในตาราง + วิธีใช้ ----
        $items = (array) $request->input('items', []);
        $doses = [];
        $sigs  = [];
        foreach ($tbKeys as $k) {
            $row = (array) ($items[$k] ?? []);
            $qty = self::qtyNumber($row['qty'] ?? '');
            $kind = self::orderDrugKind($reg, $k);
            $freq = array_key_exists((string) ($row['freq'] ?? ''), TbRegistration::$sigFreq) ? (string) $row['freq'] : 'OD';
            $per  = TbRegistration::$sigFreq[$freq][1];
            $mul  = $per !== null && $per >= 1 ? $per : 1;   // รายสัปดาห์/เมื่อมีอาการ: ตารางเก็บขนาดต่อครั้ง

            if ($qty !== null && $qty > 0) {
                if ($kind['kind'] === 'strength') {
                    $str = (float) ($row['strength'] ?? 0);
                    if (! in_array($str, $kind['strengths'], true)) { $str = (float) ($kind['prefer'] && in_array($kind['prefer'], $kind['strengths'], true) ? $kind['prefer'] : end($kind['strengths'])); }
                    $doses[$k] = self::fmtNum($qty * $str * $mul);
                    $row['strength'] = self::fmtNum($str);
                } else {
                    $doses[$k] = self::fmtNum($qty * $mul);
                    unset($row['strength']);
                }
            }
            // ยาฉีด/ไม่รู้ความแรง กรอกเป็น มก. — ไม่เก็บจำนวนลงวิธีใช้ ไม่งั้นฉลากขึ้น "ครั้งละ 200 เม็ด"
            if ($kind['kind'] === 'mg') { $row['qty'] = ''; }
            $row['unit'] = 'เม็ด';
            $row['freq'] = $freq;
            $sig = self::cleanTbSig($row);
            if ($sig) { $sigs[$k] = $sig; }
        }
        if (! empty($doses['4FDC']) && ! empty($doses['2FDC'])) {
            return $back('รอบเดียวกันใส่ได้ทั้ง 4FDC หรือ 2FDC อย่างใดอย่างหนึ่งเท่านั้น (4FDC = เดือน 1-2 · 2FDC = เดือน 3-6)');
        }

        // ยาอื่นที่ไม่ใช่ยาวัณโรค — เก็บในแถวเป็นข้อความพร้อมหน่วย (เช่น "2 เม็ด/วัน") + วิธีใช้ + จำนวนจ่าย
        // ชื่อเต็มเก็บแยกไว้จับคู่กับคลังยา (หน้าตรวจ) — คีย์ในตารางขนาดยาของบัตรยังตัดที่ 20 ตัวอักษรเหมือนเดิม
        $otherNames = [];
        foreach ((array) $request->input('other', []) as $row) {
            $row  = (array) $row;
            $full = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($row['name'] ?? ''))), 0, 100);
            $name = mb_substr($full, 0, 20);
            $qty  = self::qtyNumber($row['qty'] ?? '');
            // ช่อง "ครั้งละ" โชว์เลข 1 จาง ๆ (placeholder) — เลือกยาแล้วไม่ได้พิมพ์ = ครั้งละ 1 ไม่ใช่ทิ้งแถว (ยาทาหายเงียบ 2569-10-27)
            if ($name !== '' && $qty === null && trim((string) ($row['qty'] ?? '')) === '') { $qty = 1.0; }
            if ($name === '' || ! $qty) { continue; }
            if (isset($doses[$name]) || in_array($name, $tbKeys, true)) { return $back('ยาอื่น "' . $name . '" ซ้ำกับยาในสูตร'); }
            $unit = in_array((string) ($row['unit'] ?? ''), TbRegistration::$sigUnit, true) ? (string) $row['unit'] : 'เม็ด';
            $freq = array_key_exists((string) ($row['freq'] ?? ''), TbRegistration::$sigFreq) ? (string) $row['freq'] : 'OD';
            $per  = TbRegistration::$sigFreq[$freq][1];
            $doses[$name] = $per === null ? self::fmtNum($qty) . ' ' . $unit . ' เมื่อมีอาการ'
                : ($per >= 1 ? self::fmtNum($qty * $per) . ' ' . $unit . '/วัน' : self::fmtNum($qty) . ' ' . $unit . '/ครั้ง');
            $row['unit'] = $unit;
            $row['freq'] = $freq;
            $sig = self::cleanTbSig($row);
            if ($sig) { $sigs[$name] = $sig; }
            $otherNames[$name] = $full;
        }
        if (! $doses) { return $back('ยังไม่มีจำนวนยาเลย — กรอกจำนวนต่อครั้งของยาอย่างน้อย 1 ตัว'); }

        // ---- รอบ ----
        $label = trim((string) $request->input('label'));
        if ($label === '__custom') { $label = trim((string) $request->input('label_custom')); }
        if ($label === '') { return $back('กรุณาตั้งชื่อรอบ'); }
        $monthNo = self::monthOfLabel($label, (int) $request->input('month_no', $reg->monthsOnTreatment()));
        $monthNo = max(0, min(31, $monthNo));

        $todayRow = $reg->monthlyDoses->filter(fn ($r) => TbRegistration::beDate($r->dispensed_date) === $today)->sortBy('id')->last();
        // เลขรอบห้ามซ้ำเฉพาะในช่วงนับรอบเดียวกัน — 2HRZE รอบ 0 กับ BPaL รอบ 0 อยู่คนละช่วง (2569-10-26)
        $segs   = $reg->exists ? $reg->doseSegments() : null;
        $segIdx = $segs ? $reg->segmentIndexFor($today, $segs) : 0;
        $pastSeg = $segs && $segIdx < count($segs) - 1;
        $restart = ! $pastSeg && $reg->exists && $request->boolean('restart');
        $clash = $reg->monthlyDoses->first(fn ($r) => (trim((string) $r->label) !== '' ? (string) $r->label : (string) $r->month_no) === $label
            && (! $todayRow || $r->id !== $todayRow->id)
            // เริ่มนับใหม่ = ช่วงใหม่ รอบเดิมทั้งหมดอยู่ช่วงก่อน ไม่ชนกัน
            && ! $restart && (! $segs || $reg->segmentIndexFor($r->dispensed_date, $segs) === $segIdx));
        if ($clash && preg_match('/^(\d+|\d+wk)$/i', $label)) {
            return $back('รอบ "' . $clash->labelForHumans() . '" มีการสั่งยาไว้แล้ว (' . ($clash->dispensed_date ? Utillity::db2thDate($clash->dispensed_date) : 'ไม่ระบุวันที่')
                . ') — เลือกรอบอื่น หรือแก้แถวนั้นในใบสรุปการรักษา');
        }

        // ---- เปลี่ยนสูตร/ระยะ? เทียบตัวยาวัณโรคกับสูตรที่ใช้อยู่ ----
        $cur = $this->currentOrderOption($reg, $opts);
        $newSet = self::tbDrugSet($drugs);
        // ชื่อสูตรต่างแต่ตัวยาเหมือนเดิม (เช่น บัตรเก่าเขียน "2HRZE/4HR" แล้วกล่องเลือก "2HRZE" ให้) ไม่นับว่าเปลี่ยน
        // ไม่งั้นแค่กดบันทึกการสั่งยาก็มีช่วงสูตรใหม่โผล่ในประวัติ · Individualized ที่แก้ชื่อสูตรเอง = เปลี่ยน
        // บัตรที่ยังไม่มีสูตรเลย = สั่งครั้งแรก บันทึกสูตรลงประวัติด้วย
        $changed = $newSet !== $cur['curSet'] || ($key === 'custom' && $regText !== $cur['curText']) || $cur['curText'] === '';
        // visit ย้อนหลังในช่วงสูตรเก่า: ลงเป็นรอบของช่วงนั้นอย่างเดียว ไม่ขึ้นช่วงสูตรใหม่ ไม่ทับวิธีใช้ยาปัจจุบัน
        // (เคยลง 2HRZE ปี 68 แล้วระบบปิดช่วง BPaL ที่ใช้อยู่ — แก้ประวัติสูตรทำที่บัตร TB 01)
        // แต่ถ้าวันนั้นใช้สูตรต่างจากชื่อช่วงที่บันทึกไว้ (เช่น ช่วง 2HRZE แต่จ่าย RZE-Lfx) = แทรกช่วงสูตรของวันนั้นลงประวัติ
        // ภายในช่วงเก่าเท่านั้น (persistOrder) — คลินิกแจ้ง 2569-10-26 ลงยาย้อนหลังแล้วประวัติสูตรยาไม่มีวันนั้น
        $pastChange = false;
        if ($pastSeg) {
            $cov = \App\Models\TbRegimenChange::coveringFor($reg->id, $today);
            $covOpt = $cov ? $this->currentOrderOption($reg, $opts, $cov) : null;
            $covSet = $cov && $cov->drugs ? self::tbDrugSet((array) $cov->drugs) : ($covOpt['curSet'] ?? []);
            $pastChange = $cov && ($newSet !== $covSet || ($key === 'custom' && $regText !== trim((string) $cov->regimen)));
            $changed = false;
        }
        // หยุดสูตรเดิมแล้วเริ่มนับรอบใหม่ (ขาดยา/กลับมารักษาใหม่ สูตรเดียวกัน) = ขึ้นช่วงสูตรใหม่เสมอ
        if ($restart) { $changed = true; }

        return compact('key', 'opt', 'drugs', 'regText', 'ltbi', 'tbKeys', 'doses', 'sigs', 'otherNames',
                       'label', 'monthNo', 'todayRow', 'changed', 'today', 'pastSeg', 'restart', 'pastChange');
    }

    /**
     * เขียนส่วนของบัตร TB 01 จากผลของ buildOrder — ทรานแซกชันเดียว (ซ้อนในทรานแซกชันของหน้าตรวจได้ = savepoint)
     *   1) สูตร/ระยะเปลี่ยน → ขึ้นช่วงสูตรใหม่ · 2) แถวรอบสั่งยา (วันนี้มีแล้ว = แก้แถวเดิม) · 3) วิธีใช้ยา
     */
    private function persistOrder(TbRegistration $reg, array $o, Request $request): \App\Models\TbMonthlyDose
    {
        // บัตรจำลองของคนไข้ที่ไม่มีบัตร (id 0) ห้ามหลุดมาถึงตรงนี้ ไม่งั้นได้แถวกำพร้าในตารางของบัตร
        if (! $reg->exists) { throw new \LogicException('persistOrder ใช้ได้กับบัตร TB 01 ที่มีอยู่จริงเท่านั้น'); }
        ['changed' => $changed, 'regText' => $regText, 'opt' => $opt, 'drugs' => $drugs, 'ltbi' => $ltbi, 'label' => $label,
         'monthNo' => $monthNo, 'todayRow' => $todayRow, 'doses' => $doses, 'sigs' => $sigs, 'today' => $today] = $o;
        $pastSeg = ! empty($o['pastSeg']);
        $restart = ! empty($o['restart']);
        $pastChange = ! empty($o['pastChange']);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($reg, $request, $changed, $regText, $opt, $drugs, $ltbi, $label, $monthNo, $todayRow, $doses, $sigs, $today, $pastSeg, $restart, $pastChange) {
            if ($pastChange) {
                $this->insertPastRegimenPeriod($reg, $regText, $opt['category'] ?? null, $monthNo, $today,
                    trim((string) $request->input('reason')) ?: 'ลงย้อนหลัง · สั่งยา',
                    TbRegistration::needsB6($drugs) ? array_values(array_unique(array_merge($drugs, ['B6']))) : $drugs);
            }
            if ($changed) {
                $phase = TbRegistration::phaseOfDrugs($drugs, $regText);
                $reason = trim((string) $request->input('reason'))
                    ?: ('สั่งยา' . ($phase ? ' · ' . (\App\Models\TbDotDay::$phaseOption[$phase] ?? '') : ''));
                // เครื่องหมายนี้ทำให้ doseSegments ตัดช่วงนับรอบใหม่ที่ตรงนี้
                if ($restart) { $reason = TbRegistration::RESTART_MARK . ' ' . $reason; }
                $this->openRegimenPeriod($reg, $regText, $opt['category'] ?? null, $monthNo, $today, mb_substr($reason, 0, 255),
                    TbRegistration::needsB6($drugs) ? array_values(array_unique(array_merge($drugs, ['B6']))) : $drugs);
            }

            $values = [
                'weight'         => $request->filled('weight') ? (float) $request->input('weight') : null,
                'dispensed_date' => $today,
                'dispensed_days' => (int) $request->input('days'),
                'doses'          => $doses,
            ];
            $saved = self::writeDoseRow($reg, $todayRow, $values, $label, $monthNo);

            // ช่วงสูตรเก่า: วิธีใช้ยาในบัตรเป็นของสูตรปัจจุบัน (สติกเกอร์/ใบกินยา/DOT ใช้) ห้ามทับด้วยของปีก่อน
            if (! $pastSeg) {
                $all = (array) ($reg->drug_sigs ?? []);
                foreach ($sigs as $k => $s) { $all[$k] = $s; }
                $reg->drug_sigs = $all ?: null;
                $reg->save();
            }

            return $saved;
        });
    }

    /**
     * บันทึกช่วงสูตรยา — เปลี่ยนสูตรกลางคอร์สแล้วของเดิมต้องยังอยู่
     * ช่วงก่อนหน้าจะถูกปิดวันสิ้นสุดให้อัตโนมัติ (ไม่ลบ) เพื่อให้ย้อนดูได้ว่าเดือนไหนใช้สูตรอะไร
     */
    public function saveRegimen(Request $request, $id)
    {
        $reg = TbRegistration::findOrFail($id);

        $data = $request->validate([
            'regimen'     => ['required', 'string', 'max:255'],
            'category'    => ['nullable', 'string', 'max:50'],
            'start_month' => ['nullable', 'integer', 'min:0', 'max:31'],
            'start_date'  => ['nullable', 'string', 'max:20'],
            'reason'      => ['nullable', 'string', 'max:255'],
            'drugs'       => ['nullable', 'array'],
        ]);

        // ยาตัวเดียวกันมีช่องติ๊กได้หลายกลุ่ม จึงต้องกันค่าซ้ำก่อนเก็บ
        $drugs = array_values(array_unique(array_filter(array_map('trim', (array) ($data['drugs'] ?? [])))));

        $startMonth = $data['start_month'] ?? null;
        // วันเริ่มสูตรเป็นวันล่วงหน้าได้ (นัดเริ่มยา) จึงไม่ใส่ notFuture — แต่ต้องเป็นวันที่มีจริงในช่วงปีที่รับได้
        $sd = ThaiDate::parse($data['start_date'] ?? '', ['allowEmpty' => true]);
        if (! $sd['ok']) { return $this->dateFail($reg, 'tb-regimen', ['start_date' => 'วันที่เริ่มสูตร: ' . $sd['error']]); }
        $startDate  = $sd['be'];

        // ปิดช่วงก่อนหน้าที่ยังเปิดอยู่ แล้วขึ้นช่วงใหม่ (ตัวเดียวกับกล่องสั่งยาครั้งนี้)
        // ฟอร์มนี้ไม่อยู่ในหน้าจอแล้ว (2569-10-02) แต่ยังรับจากหน้าที่เปิดค้างไว้
        $this->openRegimenPeriod($reg, $data['regimen'], $data['category'] ?? null,
            $startMonth !== null ? (int) $startMonth : null, $startDate, $data['reason'] ?? null, $drugs);

        flash('บันทึกสูตรยาแล้ว · สูตรเดิมถูกเก็บไว้ในประวัติ', 'success');

        return $this->backToSection($reg, 'tb-regimen');
    }

    /**
     * แก้ช่วงสูตรยาที่บันทึกไว้แล้ว — ให้พยาบาลเก็บกวาดข้อมูลที่คนเพิ่งหัดใช้กรอกผิด (คลินิกขอ 2569-10-02)
     * แก้เฉพาะแถวนี้ ไม่ปิด/เปิดช่วงอื่นให้เอง (ต่างจากการเพิ่มสูตรใหม่) เพราะคนแก้ตั้งใจจัดทีละแถว
     */
    public function updateRegimen(Request $request, $id, $changeId)
    {
        $reg = TbRegistration::findOrFail($id);
        // แถวต้องเป็นของบัตรนี้เท่านั้น — กันแก้ข้ามบัตรด้วยการเปลี่ยนเลขใน URL
        $row = \App\Models\TbRegimenChange::where('tb_registration_id', $reg->id)->findOrFail($changeId);

        $data = $request->validate([
            'regimen'     => ['required', 'string', 'max:255'],
            'start_month' => ['nullable', 'integer', 'min:0', 'max:31'],
            'start_date'  => ['nullable', 'string', 'max:20'],
            'end_date'    => ['nullable', 'string', 'max:20'],
            'reason'      => ['nullable', 'string', 'max:255'],
            'drugs'       => ['nullable', 'array'],
        ]);

        // ตัวอ่านกลางของงาน TB (2569-10-05): ค.ศ. แปลงให้ — ช่องนี้ใช้แก้แถวที่เคยเก็บเป็น ค.ศ. ด้วย
        // วันไม่มีจริง/ปีนอกช่วง ห้ามเก็บ เพราะข้อความวันที่ใช้เรียงลำดับช่วงสูตร · วันล่วงหน้าได้ (วางแผนสิ้นสุดสูตร)
        $parsed = [];
        $errs = [];
        foreach (['start_date' => 'วันที่เริ่ม', 'end_date' => 'วันที่สิ้นสุด'] as $f => $label) {
            $r = ThaiDate::parse($data[$f] ?? '', ['allowEmpty' => true]);
            if (! $r['ok']) { $errs[$f] = 'ช่วงสูตร ' . trim($data['regimen']) . ' · ' . $label . ': ' . $r['error']; }
            $parsed[$f] = $r['be'];
        }
        if ($errs) { return $this->dateFail($reg, 'tb-regimen', $errs); }

        $drugs = array_values(array_unique(array_filter(array_map('trim', (array) ($data['drugs'] ?? [])))));

        // update() ผ่าน Eloquent → trait Auditable บันทึกค่าก่อน/หลังลงประวัติการแก้ไขให้เอง
        $row->update([
            'regimen'     => trim($data['regimen']),
            'start_month' => $data['start_month'] ?? null,
            'start_date'  => $parsed['start_date'],
            'end_date'    => $parsed['end_date'],
            'reason'      => $data['reason'] ?? null,
            'drugs'       => $drugs ?: null,
        ]);

        // ช่อง regimen ของบัตร = สูตรที่ใช้อยู่ ต้องตามช่วงที่ยังเปิดอยู่หลังแก้
        $current = \App\Models\TbRegimenChange::currentFor($reg->id);
        if ($current && $reg->regimen !== $current->regimen) {
            $reg->update(['regimen' => $current->regimen]);
        }

        flash('แก้ไขช่วงสูตรยาแล้ว', 'success');

        return $this->backToSection($reg, 'tb-regimen');
    }

    /** ลบช่วงสูตรยาที่บันทึกผิด (เฉพาะแถวที่เลือก ไม่กระทบช่วงอื่น) */
    public function deleteRegimen($id, $changeId)
    {
        $reg = TbRegistration::findOrFail($id);

        $row = \App\Models\TbRegimenChange::where('tb_registration_id', $reg->id)->findOrFail($changeId);
        $row->delete();

        // ช่วงล่าสุดที่เหลือต้องกลับมาเป็น "ยังใช้อยู่"
        $last = \App\Models\TbRegimenChange::where('tb_registration_id', $reg->id)
            ->orderByDesc('start_month')->orderByDesc('id')->first();
        if ($last) {
            $last->update(['end_date' => null]);
            $reg->update(['regimen' => $last->regimen]);
        }

        flash('ลบช่วงสูตรยาแล้ว', 'success');

        return $this->backToSection($reg, 'tb-regimen');
    }

    /** ลบบัตร — เฉพาะ Admin และต้องยังไม่มีผลเสมหะ/ผลการรักษา กันลบทับงานที่ทำมาทั้งคอร์ส */
    public function destroy($id)
    {
        $reg = TbRegistration::findOrFail($id);

        if (! Auth::user()->hasRole('Admin')) {
            flash('ลบบัตรได้เฉพาะผู้ดูแลระบบ', 'danger');
            return redirect()->route('tb.show', $reg->id);
        }
        if ($reg->sputumExams()->exists() || $reg->outcome) {
            flash('บัตรนี้มีผลตรวจหรือผลการรักษาบันทึกไว้แล้ว ลบไม่ได้ — ถ้าเปิดผิดให้แก้ข้อมูลแทน', 'danger');
            return redirect()->route('tb.show', $reg->id);
        }

        $reg->delete();
        flash('ลบบัตรแล้ว', 'success');

        return redirect()->route('tb.index');
    }

    /** ช่องที่รับจากฟอร์มบัตร TB 01 — วันที่ทุกช่องแปลง d/m/Y (พ.ศ.) -> Y-m-d (พ.ศ.) */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'registry_type'           => ['required', 'integer', 'in:1,2,3'],
            'tb_no'                   => ['nullable', 'string', 'max:50'],
            'register_date'           => ['nullable', 'string', 'max:20'],
            'treatment_start_date'    => ['nullable', 'string', 'max:20'],
            'classification'          => ['nullable', 'integer', 'in:1,2,3'],
            'extrapulmonary_site'     => ['nullable', 'string', 'max:255'],
            'register_type'           => ['nullable', 'integer', 'between:1,6'],
            'register_type_other'     => ['nullable', 'string', 'max:255'],
            'sputum_schedule'         => ['nullable', 'integer', 'in:1,2'],
            'regimen'                 => ['nullable', 'string', 'max:255'],
            'height'                  => ['nullable', 'numeric', 'between:50,250'],
            'dot'                     => ['nullable', 'boolean'],
            'dot_by'                  => ['nullable', 'integer', 'between:1,5'],
            'dot_by_other'            => ['nullable', 'string', 'max:255'],
            'previous_tb_history'     => ['nullable', 'string'],
            'contact_history'         => ['nullable', 'string'],
            'other_disease'           => ['nullable', 'string'],
            'drug_allergy'            => ['nullable', 'string'],
            'contact_age0_5'            => ['nullable', 'integer', 'min:0', 'max:99'],
            'contact_age0_5_screened'   => ['nullable', 'integer', 'min:0', 'max:99'],
            'contact_age6_17'           => ['nullable', 'integer', 'min:0', 'max:99'],
            'contact_age6_17_screened'  => ['nullable', 'integer', 'min:0', 'max:99'],
            'contact_age18up'           => ['nullable', 'integer', 'min:0', 'max:99'],
            'contact_age18up_screened'  => ['nullable', 'integer', 'min:0', 'max:99'],
            'no_household_contact'      => ['nullable', 'boolean'],
            'bcg'                     => ['nullable', 'integer', 'between:1,3'],
            'counsel_date'            => ['nullable', 'string', 'max:20'],
            'blood_test_date'         => ['nullable', 'string', 'max:20'],
            'hiv_result'              => ['nullable', 'integer', 'between:1,3'],
            'hiv_test_place'          => ['nullable', 'string', 'max:150'],
            'hiv_counselor'           => ['nullable', 'string', 'max:150'],
            'arv'                     => ['nullable', 'string', 'max:255'],
            'cpt'                     => ['nullable', 'string', 'max:255'],
            'outcome'                 => ['nullable', 'integer', 'between:1,10'],
            // บัตร LTBI (2569-10-27)
            'tpt_target'              => ['nullable', 'integer', 'between:1,9'],
            'tpt_target_other'        => ['nullable', 'string', 'max:255'],
            'ltbi_test'               => ['nullable', 'integer', 'between:1,4'],
            'ltbi_test_date'          => ['nullable', 'string', 'max:20'],
            'ltbi_test_result'        => ['nullable', 'integer', 'between:1,3'],
            'tst_mm'                  => ['nullable', 'numeric', 'between:0,99'],
            'cxr_date'                => ['nullable', 'string', 'max:20'],
            'cxr_result'              => ['nullable', 'integer', 'between:1,3'],
            'cxr_note'                => ['nullable', 'string', 'max:255'],
            'outcome_date'            => ['nullable', 'string', 'max:20'],
            'note'                    => ['nullable', 'string'],
            'symptoms'                => ['nullable', 'array'],
            'index_ref'               => ['nullable', 'string', 'max:30'],
        ]);

        // ผู้ป่วยต้นเหตุ (index case) — HN / เลขทะเบียน TB → บัตรของ index (2569-10-22) · ฟอร์มที่ไม่มีช่องนี้ไม่แตะค่าเดิม
        unset($data['index_ref']);
        if (\App\Services\TbContacts::ready() && $request->has('index_ref')) {
            $ref = trim((string) $request->input('index_ref'));
            if ($ref === '') {
                $data['index_registration_id'] = null;
            } else {
                $self = $request->route('id') ? TbRegistration::find($request->route('id')) : null;
                $pid  = (int) ($request->input('patient_id') ?: optional($self)->patient_id);
                $idx  = \App\Services\TbContacts::resolveIndex($ref, $pid ?: null);
                if (! $idx) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'index_ref' => 'ผู้ป่วยต้นเหตุ: ไม่พบบัตรวัณโรคของ "' . $ref . '" — ใส่ HN หรือเลขทะเบียน TB ของผู้ป่วยวัณโรค (ไม่ใช่ของคนไข้คนนี้เอง)',
                    ]);
                }
                $data['index_registration_id'] = $idx->id;
            }
        }

        // ยังไม่รัน migration 2026_10_14_100000 = ไม่มีคอลัมน์ (ฟอร์มก็ไม่โชว์ช่อง) — กันหน้าเก่าที่ส่งมา
        if (! TbRegistration::hivPlaceReady()) { unset($data['hiv_test_place'], $data['hiv_counselor']); }

        // บัตร LTBI: ช่องเฉพาะ LTBI มีเมื่อรัน migration แล้ว · การจำแนกเป็น LTBI เสมอ · ไม่มีตารางเสมหะ
        $ltbiKeys = ['tpt_target', 'tpt_target_other', 'ltbi_test', 'ltbi_test_date', 'ltbi_test_result', 'tst_mm', 'cxr_date', 'cxr_result', 'cxr_note'];
        if (! TbRegistration::ltbiReady()) {
            foreach ($ltbiKeys as $k) { unset($data[$k]); }
        } elseif (! $request->has('tpt_target')) {
            // ฟอร์มที่ไม่มีกล่อง LTBI (บัตร TB) ไม่แตะค่า
            foreach ($ltbiKeys as $k) { unset($data[$k]); }
        }
        if ((int) $data['registry_type'] === TbRegistration::TYPE_LTBI) {
            $data['classification'] = 3;
            $data['sputum_schedule'] = null;
            if (isset($data['outcome']) && $data['outcome'] !== null && ! isset(TbRegistration::$ltbiOutcomeOption[(int) $data['outcome']])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['outcome' => 'ผลการรักษาไม่ใช่ตัวเลือกของบัตรวัณโรคแฝง — เลือกใหม่']);
            }
        } elseif (in_array((int) ($data['outcome'] ?? 0), [TbRegistration::OUTCOME_DEV_TB, TbRegistration::OUTCOME_ADR], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['outcome' => 'ผลการรักษานี้ใช้กับบัตรวัณโรคแฝงเท่านั้น']);
        }

        // ช่องติ๊กที่ไม่ได้ติ๊กจะไม่ถูกส่งมาเลย ต้องเคลียร์ให้เองไม่งั้นค่าเดิมค้าง
        // null = ยังไม่ได้ถาม · 1 = ถามแล้วยืนยันว่าไม่มีผู้สัมผัสร่วมบ้าน
        $data['no_household_contact'] = $request->boolean('no_household_contact') ? 1 : null;

        // ทุกวันที่ในระบบเก็บเป็น พ.ศ. แต่พยาบาลบางคนพิมพ์ปี ค.ศ. มาจากตาราง Excel (เช่น 06/03/2026)
        // ถ้าปล่อยไว้ "เดือนที่รักษา" จะเพี้ยนเป็นหลักพัน — แปลงให้อัตโนมัติแล้วบอกให้รู้ (2569-09-28)
        // ตัวอ่านแบบเข้มงวด (2569-10-05) — วันที่ผิดไม่บันทึกทั้งฟอร์ม แจ้งช่องที่ผิด (เดิม th2dbDate ล้นวันที่เงียบ ๆ)
        // วันเริ่มรักษาเป็นวันล่วงหน้าได้ (นัดเริ่มยา) · ช่องอื่นเป็นเหตุการณ์ที่เกิดแล้ว
        $fixed = [];
        $errs = [];
        $dateFields = [
            'register_date'        => ['วันที่ขึ้นทะเบียน', true],
            'treatment_start_date' => ['วันเริ่มรักษา', false],
            'counsel_date'         => ['วันที่ให้คำปรึกษา HIV', true],
            'blood_test_date'      => ['วันที่เจาะเลือด HIV', true],
            'outcome_date'         => ['วันที่สรุปผลการรักษา', true],
            'ltbi_test_date'       => ['วันที่ตรวจ TST/IGRA', true],
            'cxr_date'             => ['วันที่ถ่าย CXR', true],
        ];
        foreach ($dateFields as $f => [$label, $notFuture]) {
            if (! array_key_exists($f, $data)) { continue; }   // ช่อง LTBI ที่ไม่ได้ส่งมา
            $r = ThaiDate::parse($data[$f] ?? '', ['allowEmpty' => true, 'notFuture' => $notFuture]);
            if (! $r['ok']) { $errs[$f] = 'บัตร TB 01 · ' . $label . ': ' . $r['error']; continue; }
            $data[$f] = $r['be'];
            if ($r['converted']) { $fixed[] = ThaiDate::show($r['be']); }
        }
        if ($errs) {
            throw \Illuminate\Validation\ValidationException::withMessages($errs);
        }
        if ($fixed) {
            flash('พบวันที่กรอกเป็น ค.ศ. ระบบแปลงเป็น พ.ศ. ให้แล้ว: ' . implode(' · ', $fixed), 'warning');
        }

        // อาการแรกรับ: เก็บเฉพาะอาการที่ติ๊ก พร้อมระยะเวลาที่พิมพ์ในแถวเดียวกัน
        $symptoms = [];
        foreach ((array) $request->input('symptoms', []) as $key => $row) {
            if (empty($row['on'])) { continue; }
            if (! isset(TbRegistration::$symptomOption[$key])) { continue; }
            $unit = (string) ($row['unit'] ?? 'month');
            $symptoms[] = [
                'key'      => $key,
                'duration' => trim((string) ($row['duration'] ?? '')) ?: null,
                'unit'     => isset(TbRegistration::$durationUnitOption[$unit]) ? $unit : 'month',
            ] + (isset(TbRegistration::$symptomDetail[$key]) && trim((string) ($row['detail'] ?? '')) !== ''
                ? ['detail' => mb_substr(trim((string) $row['detail']), 0, 100)] : []);
        }
        $data['symptoms'] = $symptoms ?: null;

        return $data;
    }
}
