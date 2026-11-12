<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Utillity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * นัดหนึ่งวันของการมาตรวจหนึ่งครั้ง — หนึ่ง visit มีได้หลายแถว
 *
 * appointment_date เก็บเป็น พ.ศ. ในคอลัมน์ DATE (เหมือน visits.appointment
 * และ holidays.holiday_date) ห้ามโยนเข้า Carbon::parse() ตรง ๆ
 *
 * visits.appointment ยังอยู่ และยังเป็นของจริงสำหรับโค้ดเก่าทั้ง 81 จุด
 * โดยถือเป็น "เงา" ของนัดที่ใกล้ที่สุดที่ยังมาไม่ถึง — ดู syncMirror()
 */
class VisitAppointment extends Model
{
    use Auditable;

    protected $table = 'visit_appointments';

    protected $guarded = ['id'];

    protected $casts = [
        'reminder_sent_at' => 'datetime',
        'confirm_sent_at'  => 'datetime',
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function auditLabel(): string
    {
        return 'นัด ' . $this->appointment_date . ($this->reason ? ' (' . $this->reason . ')' : '');
    }

    /**
     * โน้ตส่งเวร (ช่องเทา) ที่เขียนกำกับนัดซึ่ง visit นี้มาตามนัด — ให้เห็นบนแถบส่งเวรของหน้าตรวจ + หน้าการเงิน
     * เดิมโน้ตที่มีวันนัดไปอยู่แค่ในประวัติส่งเวร ไม่ขึ้นแถบ (เคส "ค้างชำระค่าออกเอกสาร 200" นัด 13/11/69 · TMC แจ้ง 2569-11-11)
     * นัดที่ "visit นี้มาตาม" = นัดจาก visit อื่นของคนไข้ วันนัดอยู่ช่วง 30 วันก่อน ถึง 14 วันหลังวันที่มา (มาช้า/มาก่อนนัด)
     * ไม่ต้องกดปิด — พ้นช่วงแล้วหายจากแถบเอง (ยังอยู่ในประวัติส่งเวร)
     */
    public static function notesForVisit($visit)
    {
        // หน้าตรวจแปลง $visit->date เป็น d/m/Y ไปแล้ว — ใช้ค่าดิบจากฐานก่อน
        $raw = method_exists($visit, 'getRawOriginal') ? ($visit->getRawOriginal('date') ?? $visit->date) : $visit->date;
        if (str_contains((string) $raw, '/')) { $raw = \App\Utillity::th2dbDate($raw); }
        $ad = \App\Utillity::BE2AD((string) $raw);
        if (! $visit->patient_id || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $ad)) { return collect(); }
        $d = \Carbon\Carbon::createFromFormat('Y-m-d', $ad);
        $from = \App\Utillity::AD2BE($d->copy()->subDays(30)->format('Y-m-d'));
        $to   = \App\Utillity::AD2BE($d->copy()->addDays(14)->format('Y-m-d'));

        return self::where('patient_id', $visit->patient_id)
            ->where('visit_id', '<>', $visit->id)
            ->whereNotNull('staff_note')->where('staff_note', '<>', '')
            ->whereBetween('appointment_date', [$from, $to])
            ->orderBy('appointment_date')->get();
    }

    /**
     * นัดที่ยัง "มีผล" — ยังไม่ถูกปิด
     *
     * ทุกที่ที่ถามว่า "คนไข้ยังต้องมาไหม" ต้องผ่าน scope นี้เสมอ:
     * ตัวส่ง LINE · กล่องเตือนห้องบัตร · ใบนัด · เงาใน visits.appointment
     * ถ้าลืมที่ใดที่หนึ่ง นัดที่พยาบาลปิดไปแล้วจะกลับมาหลอกอีก
     */
    public function scopeOpen($q)
    {
        return $q->whereNull('closed_at');
    }

    /**
     * ปิดนัด — ไม่ลบทิ้ง เก็บไว้ตรวจย้อนหลังได้ว่าใครปิดเมื่อไหร่เพราะอะไร
     * $byVisitId = การมาครั้งที่ทำให้ปิด (เคส "มาก่อนนัด")
     */
    public function close(string $reason, ?int $byVisitId = null): void
    {
        $this->forceFill([
            'closed_at'       => now(),
            'closed_reason'   => $reason,
            'closed_by'       => auth()->id(),
            'closed_visit_id' => $byVisitId,
        ])->save();

        self::syncMirror($this->visit_id);
    }

    /** คำอธิบายเหตุผลปิด แบบที่พยาบาลอ่านรู้เรื่อง */
    public static function closedReasonText(?string $r): string
    {
        return [
            'done_early' => 'มาทำก่อนกำหนดแล้ว',
            'cancelled'  => 'ยกเลิกนัด',
            'no_show'    => 'ไม่มาตามนัด',
        ][$r] ?? 'ปิดนัด';
    }

    /**
     * นัดที่ยังค้างของคนไข้ ที่ไม่ได้เกิดจากการมาครั้งนี้
     * ใช้ในหน้าตรวจ เพื่อถามพยาบาลว่า "นัดเก่าอันนี้ ทำวันนี้แล้วหรือยัง"
     */
    public static function pendingFromOtherVisits(int $patientId, int $exceptVisitId)
    {
        return self::open()
            ->where('patient_id', $patientId)
            ->where('visit_id', '<>', $exceptVisitId)
            ->where('appointment_date', '>=', self::todayBe())
            ->orderBy('appointment_date')->orderBy('id')
            ->get();
    }

    /** วันนี้ในรูป พ.ศ. Y-m-d — ใช้เทียบกับคอลัมน์ตรง ๆ ได้เลย */
    public static function todayBe(): string
    {
        return Utillity::AD2BE(now()->format('Y-m-d'));
    }

    /**
     * ปรับ visits.appointment / appointment_reason / appointment_staff_note
     * ให้เท่ากับ "นัดที่ใกล้ที่สุดที่ยังมาไม่ถึง" ของ visit นั้น
     *
     * ต้องเรียกทุกครั้งที่เพิ่ม/แก้/ลบนัด ไม่งั้นหน้าจอเก่ากับหน้าจอใหม่จะไม่ตรงกัน
     * ซึ่งพยาบาลจะเชื่อหน้าจอเก่า (เปิดบ่อยกว่า) แล้วนัดคนไข้ผิดวัน
     *
     * ถ้านัดทั้งหมดผ่านไปแล้ว ให้ใช้นัดล่าสุดที่ผ่านมาแทน — ไม่ล้างเป็น null
     * เพราะรายงานย้อนหลังหลายตัวอ่านช่องนี้เพื่อดูว่า "ครั้งนั้นนัดวันไหน"
     * ถ้าล้างทิ้ง รายงานเก่าจะกลายเป็นว่างหมด
     */
    /** เหตุผลนัดที่ระบบลงให้จากแผนวัคซีน — ใช้แยกแถวที่ระบบดูแลออกจากนัดที่พยาบาลลงเอง */
    public const PLAN_PREFIX = 'ฉีดวัคซีน: ';

    /**
     * แผนนัดวัคซีน (หมอวาง) → ลงเป็นนัดในตารางวันนัดของ visit นี้ด้วย (คลินิกขอ 2569-10-28)
     * รายชื่อนัดรวม / LINE เตือนนัด / ใบนัด อ่านจากตารางนัด — เดิมแผนไม่ลงตาราง นัดวัคซีนเลยไม่โผล่ในรายชื่อนัด
     * แถวที่ระบบดูแล = เหตุผลขึ้นต้น PLAN_PREFIX: แก้แผน → แก้ตาม · เอาวันออกจากแผน → ลบนัดนั้น (เฉพาะที่ยังไม่ปิด)
     * วันเดียวกันที่พยาบาลลงนัดเองไว้แล้ว (เหตุผลอื่น) ไม่ไปทับ · คืนจำนวนแถวที่เปลี่ยน
     */
    public static function syncVaccinePlan(Visit $visit): int
    {
        $want = [];
        foreach (VisitVaccine::normalizePlan($visit->tmc_vaccine_plan) as $r) {
            if (trim((string) ($r['date'] ?? '')) === '') { continue; }
            $be = Utillity::th2dbDate($r['date']);
            if (! $be || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $be)) { continue; }
            // วันที่ผ่านไปแล้วไม่ลงเป็นนัด — จะกลายเป็น "นัดค้าง" เตือนในหน้าตรวจครั้งถัดไปทั้งที่ไม่ได้ตั้งใจ
            if ($be < self::todayBe()) { continue; }
            $txt = (VisitVaccine::planItemsText($r['items'] ?? []) ?: 'ตามแผน') . VisitVaccine::planSiteText($r);
            $want[$be] = isset($want[$be]) ? $want[$be] . ', ' . $txt : $txt;
        }
        $rows = self::where('visit_id', $visit->id)->get();
        $n = 0;
        foreach ($want as $be => $txt) {
            $reason = mb_substr(self::PLAN_PREFIX . $txt, 0, 255);
            $row = $rows->first(fn ($a) => (string) $a->appointment_date === $be && $a->closed_at === null);
            if (! $row) {
                self::create(['visit_id' => $visit->id, 'patient_id' => $visit->patient_id, 'appointment_date' => $be,
                              'reason' => $reason, 'made_by' => auth()->id()]);
                $n++;
            } elseif (trim((string) $row->reason) === '' || str_starts_with((string) $row->reason, self::PLAN_PREFIX)) {
                if ((string) $row->reason !== $reason) { $row->update(['reason' => $reason]); $n++; }
            }
        }
        foreach ($rows as $a) {
            if ($a->closed_at === null && str_starts_with((string) $a->reason, self::PLAN_PREFIX)
                && (string) $a->appointment_date >= self::todayBe() && ! isset($want[(string) $a->appointment_date])) {
                $a->delete();
                $n++;
            }
        }
        if ($n) { self::syncMirror($visit->id); }
        return $n;
    }

    public static function syncMirror(int $visitId): void
    {
        $today = self::todayBe();

        $next = self::open()->where('visit_id', $visitId)
            ->where('appointment_date', '>=', $today)
            ->orderBy('appointment_date')->orderBy('id')
            ->first();

        if (! $next) {
            // ไม่มีนัดที่ยังเปิดอยู่ -> ถอยไปใช้นัดล่าสุดของ visit นี้ "ไม่ว่าจะปิดไปแล้วหรือไม่"
            //
            // ช่องนี้คือบันทึกประวัติว่า "ครั้งนั้นนัดวันไหน" หน้า Visit History และรายงาน
            // ย้อนหลังอ่านจากตรงนี้ ถ้าล้างเป็นว่าง ประวัติจะหายไปต่อหน้าพยาบาล
            //
            // เคยเขียนเป็น '<' $today เพื่อกันนัดที่ยกเลิกไปโผล่ในรายชื่อนัด
            // แต่พลาด: นัดที่ปิดแล้วและตรงกับ "วันนี้พอดี" ตกร่องทั้งสองเงื่อนไข
            // -> เงากลายเป็นว่าง ประวัติของ visit ก่อนหน้าหายเกลี้ยง (พยาบาลเจอจริง 2569-08-26)
            // การกันไม่ให้นัดที่ปิดแล้วโผล่ในรายชื่อนัด ย้ายไปทำที่ตัวรายชื่อนัดเองแทน
            // (VisitController::appointment/export) ซึ่งเป็นที่ที่ควรกรองตั้งแต่แรก
            $next = self::where('visit_id', $visitId)
                ->orderByDesc('appointment_date')->orderByDesc('id')
                ->first();
        }

        // อัปเดตตรง ๆ ด้วย query builder ไม่ผ่าน Eloquent
        // เพราะ Visit มี model event ที่ไปซิงก์คิวกลาง/ส่ง LINE ต่อ
        // ถ้าปลุกทุกครั้งที่ขยับนัด จะยิงซ้ำโดยไม่ตั้งใจ
        DB::table('visits')->where('id', $visitId)->update([
            'appointment'            => $next->appointment_date ?? null,
            'appointment_reason'     => $next->reason ?? null,
            'appointment_staff_note' => $next->staff_note ?? null,
        ]);
    }

    /** นัดที่ยังมาไม่ถึงของคนไข้คนหนึ่ง เรียงจากใกล้ไปไกล */
    public static function upcomingFor(int $patientId, int $limit = 10)
    {
        return self::open()
            ->where('patient_id', $patientId)
            ->where('appointment_date', '>=', self::todayBe())
            ->orderBy('appointment_date')->orderBy('id')
            ->limit($limit)
            ->get();
    }
}
