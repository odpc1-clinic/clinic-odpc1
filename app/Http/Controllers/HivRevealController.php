<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Lab;
use App\Models\LabSub;
use App\Models\Patient;
use App\Models\TbRegistration;
use App\Models\Visit;
use Illuminate\Http\Request;

/**
 * ผล HIV ที่ถูกปิดไว้ในบัตร TB 01 และหน้าตรวจ TB (คลินิกขอ 2569-10-06)
 *
 * ทำไมต้องแยก endpoint: ถ้าผลอยู่ใน HTML อยู่แล้วแค่ซ่อนด้วย CSS ใครก็อ่านได้จาก view-source / ชี้เมาส์
 * จึงไม่วาดผลลงหน้าเลย ให้ขอผ่าน POST นี้ (มี CSRF) แล้วบันทึกลง activity_logs ทุกครั้งว่าใครดูเมื่อไร
 *
 * กติกา log: เก็บแค่ "ดู/พิมพ์" กับหน้าที่ดู — ห้ามเก็บค่าผลลงใน changes (หน้าประวัติการแก้ไขมีคนดูได้หลายคน)
 * ยังไม่จำกัดสิทธิ์ตามบทบาท (ทุกคนที่ล็อกอิน) — ใช้ log ตรวจย้อนหลังแทน
 */
class HivRevealController extends Controller
{
    /** ส่งผล HIV ทั้งหมดของคนไข้ให้หน้าเปิดทุกจุดที่ปิดไว้พร้อมกัน (เปิดครั้งเดียวต่อการโหลดหน้า) */
    public function reveal(Request $request, $id)
    {
        $patient = Patient::findOrFail((int) $id);
        $data = $request->validate([
            'reg'   => ['nullable', 'integer'],
            'visit' => ['nullable', 'integer'],
            'where' => ['nullable', 'string', 'max:60'],
        ]);

        // ผลในบัตร (ช่อง "ผลเลือด HIV") — ทุกบัตรของคนไข้ (คนไข้หนึ่งคนมีได้หลายคอร์ส)
        $regs = [];
        foreach (TbRegistration::where('patient_id', $patient->id)->get(['id', 'hiv_result']) as $r) {
            $v = $r->hiv_result !== null ? (int) $r->hiv_result : null;
            $regs[$r->id] = [
                'value'    => $v,
                'text'     => $v ? (TbRegistration::$hivResultOption[$v] ?? '') : '',
                'positive' => $v === 2,
            ];
        }

        // ผล Anti-HIV ในแล็บเรา — เฉพาะใบที่ห้อง Lab อนุมัติแล้ว (Lab::isResultVisible) กติกาเดียวกับทั้งระบบ
        // ส่งเป็นรายการเรียงใหม่ -> เก่า (ถ้าส่งเป็น object คีย์ตัวเลข JS จะเรียงคีย์ใหม่ให้เอง ลำดับหาย)
        $subs = [];
        foreach (self::visibleHivSubs($patient->id) as [$sub, $lab, $visit]) {
            $pos = self::isPositive($sub->result);
            $subs[] = [
                'id'       => $sub->id,
                'result'   => trim((string) $sub->result),
                'remark'   => trim((string) $sub->remark),
                'date'     => self::visitDateTh($visit),
                'visit_id' => $lab->visit_id,
                'positive' => $pos,
                'print'    => route('hiv.print', $sub->id),
                // ใบ refer ใช้เมื่อผลบวกเท่านั้น (ส่งต่อตรวจ CD4 / เข้ารักษา)
                'refer'    => $pos && $lab->visit_id ? route('hiv.refer', $lab->visit_id) : null,
            ];
        }

        $reg = ! empty($data['reg']) ? TbRegistration::where('patient_id', $patient->id)->find((int) $data['reg']) : null;
        $visitId = ! empty($data['visit']) ? Visit::where('patient_id', $patient->id)->whereKey((int) $data['visit'])->value('id') : null;

        self::log($patient->id, $reg ? TbRegistration::class : Patient::class, $reg ? $reg->id : $patient->id,
            $visitId, 'เปิดดูผล HIV', ['หน้า' => ['new' => $data['where'] ?? '']]);

        return response()->json(['ok' => true, 'regs' => (object) $regs, 'subs' => $subs]);
    }

    /**
     * ใบรายงานผล Anti-HIV หนึ่งรายการ — หน้าตาเดียวกับ "Print ผลที่เลือก" ของห้อง Lab (lab-sub/table)
     * ทำฝั่งเซิร์ฟเวอร์เพราะหน้าที่ปิดผลไว้ไม่มีค่าผลให้ JS เอาไปประกอบใบ และจะได้บันทึกว่าใครพิมพ์
     */
    public function printResult($sub)
    {
        $sub = LabSub::findOrFail((int) $sub);
        abort_unless((int) $sub->method === 6, 404);
        $lab = Lab::findOrFail($sub->lab_id);
        // ยังไม่อนุมัติ = ยังไม่ใช่ผลที่ยืนยันแล้ว ห้ามพิมพ์ออกไป
        abort_unless($lab->isResultVisible() && trim((string) $sub->result) !== '', 403, 'ผลยังไม่ผ่านการอนุมัติจากห้อง Lab');

        $visit = $lab->visit_id ? Visit::find($lab->visit_id) : null;
        $patientId = $lab->patient_id ?: optional($visit)->patient_id;
        $patient = $patientId ? Patient::find($patientId) : null;

        self::log($patientId, LabSub::class, $sub->id, $lab->visit_id, 'พิมพ์ผล HIV',
            ['พิมพ์' => ['new' => 'ใบรายงานผล Anti-HIV' . ($lab->LN ? ' LN ' . $lab->LN : '')]]);

        return view('lab-sub.hiv_print', ['sub' => $sub, 'lab' => $lab, 'patient' => $patient, 'visit' => $visit]);
    }

    /** ใบ refer (บันทึกข้อความส่งต่อ) — บันทึกการเปิดแล้วส่งต่อไปหน้าเดิม visit.referForm */
    public function refer(Request $request, $visit)
    {
        $visit = Visit::findOrFail((int) $visit);
        self::log($visit->patient_id, Visit::class, $visit->id, $visit->id, 'พิมพ์ใบ refer (ผล HIV)',
            ['พิมพ์' => ['new' => 'ใบส่งต่อ (บันทึกข้อความ)']]);

        return redirect()->route('visit.referForm', array_merge(['id' => $visit->id], $request->only('hospital', 'reason')));
    }

    /** [LabSub, Lab, Visit|null] ของ Anti-HIV ที่มีผลและอนุมัติแล้ว ใหม่ -> เก่า */
    public static function visibleHivSubs(int $patientId): array
    {
        $labs = Lab::where('patient_id', $patientId)->get()->keyBy('id');
        if ($labs->isEmpty()) { return []; }
        $visits = Visit::whereIn('id', $labs->pluck('visit_id')->filter())->get(['id', 'date', 'patient_id'])->keyBy('id');

        $out = [];
        foreach (LabSub::whereIn('lab_id', $labs->keys())->where('method', 6)->orderByDesc('id')->get() as $s) {
            $lab = $labs->get($s->lab_id);
            if (! $lab || ! $lab->isResultVisible() || trim((string) $s->result) === '') { continue; }
            $out[] = [$s, $lab, $visits->get($lab->visit_id)];
        }
        return $out;
    }

    public static function isPositive(?string $result): bool
    {
        $r = strtolower((string) $result);
        return str_contains($r, 'positive') || (str_contains($r, 'reactive') && ! str_contains($r, 'non'));
    }

    /** visits.date เป็น พ.ศ. แต่แถวยุคแรกเป็น ค.ศ. (CLAUDE.md ข้อ 4) */
    private static function visitDateTh(?Visit $visit): string
    {
        $d = optional($visit)->date;
        if (! $d) { return ''; }
        $y = (int) substr($d, 0, 4);
        return \App\Utillity::db2thDate(($y < 2500 ? $y + 543 : $y) . substr($d, 4));
    }

    /** บันทึกไม่สำเร็จ = ไม่ให้ดูผล (หัวใจของการเปิดดูคือต้องตรวจย้อนได้ว่าใครดู) */
    private static function log(?int $patientId, string $type, $subjectId, ?int $visitId, string $label, array $changes): void
    {
        try {
            ActivityLog::create([
                'user_id'       => auth()->id(),
                'user_name'     => optional(auth()->user())->name,
                'action'        => 'viewed',
                'subject_type'  => $type,
                'subject_id'    => $subjectId,
                'patient_id'    => $patientId,
                'visit_id'      => $visitId,
                'subject_label' => $label,
                'changes'       => $changes,   // ห้ามใส่ค่าผล HIV ตรงนี้
                'ip'            => optional(request())->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
            abort(500, 'บันทึกการเปิดดูผล HIV ไม่สำเร็จ — ลองใหม่อีกครั้ง');
        }
    }
}
