<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\TbRegistration;
use App\Models\Visit;
use App\Models\VisitAppointment;
use App\Services\TbMonitor\TbMonitor;
use App\Utillity;
use Carbon\Carbon;

/**
 * ติดตามหลังจบการรักษา (คลินิกขอ 2569-10-27) — ลงผลการรักษาปิดบัตรแล้ว คนไข้ยังต้องมาตรวจ (X-ray ฯลฯ) ตามรอบ
 *
 * รอบ = คอลัมน์ "หลังจบ n ด." ของตารางติดตามตามสูตร (มาตรฐานของ TB ที่ใส่ไว้ใน TbMonitorDefinitions)
 *   BPaL/BPaLM 6·12 · ดื้อยาระยะสั้น 6·12·18·24 · ยาป้องกัน (TPT) 6·12·18·24 · ไวต่อยา (DS) ไม่มีในตารางมาตรฐาน
 *   ตารางที่แพทย์กำหนดเอง = คอลัมน์หลังจบที่แพทย์ติ๊กไว้
 * วันครบนับจากวันที่ลงผลการรักษา (outcome_date) — วันจบจริง ไม่ใช่วันที่คาดไว้ตอนเริ่มยา
 * การมาติดตามเป็น visit ธรรมดาของคลินิกวัณโรค (คลินิกตกลง) ไม่ได้ลงอะไรในบัตร — มีนัด / มี visit ในช่วง ±30 วัน = ถือว่าตามแล้ว
 */
class TbPostFollowup
{
    /** ผลการรักษาที่ต้องติดตามหลังจบ: หาย · ครบ */
    public const OUTCOMES = [1, 2];

    /** ช่วงที่นับว่าตรงรอบ (วัน) — เท่ากับช่วง post ของตารางติดตาม */
    public const WINDOW = 30;

    /** เดือนหลังจบที่ต้องนัดตามสูตรของบัตร */
    public static function months(TbRegistration $reg): array
    {
        try { $def = TbMonitor::scheduleFor($reg); } catch (\Throwable $e) { return []; }
        $out = [];
        foreach ((array) ($def['points'] ?? []) as $p) {
            if (($p[2] ?? '') === 'post') { $out[] = (int) $p[3]; }
        }
        sort($out);
        return array_values(array_unique($out));
    }

    public static function eligible(TbRegistration $reg): bool
    {
        return in_array((int) $reg->outcome, self::OUTCOMES, true) && TbRegistration::beDate($reg->outcome_date) && self::months($reg);
    }

    /**
     * รอบติดตามทั้งหมด: [n, label, due (พ.ศ.), status, appt (นัดที่ตรง|null), visit (visit ที่มา|null)]
     * status: done = มาแล้ว · booked = นัดไว้แล้ว · due = ถึงช่วงแล้วยังไม่นัด · upcoming = ยังไม่ถึง · missed = เลยช่วงแล้วไม่ได้มา
     */
    public static function plan(TbRegistration $reg): array
    {
        if (! self::eligible($reg)) { return []; }
        $end   = Carbon::parse(Utillity::BE2AD(TbRegistration::beDate($reg->outcome_date)));
        $today = Carbon::today();
        $visits = Visit::where('patient_id', $reg->patient_id)->where('clinic', 2)->get(['id', 'date'])
            ->map(fn ($v) => ['id' => $v->id, 'date' => TbRegistration::beDate($v->getRawOriginal('date'))]);
        $appts = VisitAppointment::where('patient_id', $reg->patient_id)->whereNull('closed_at')->get();

        $out = [];
        foreach (self::months($reg) as $n) {
            $dueAd = $end->copy()->addMonthsNoOverflow($n);
            $from  = self::be($dueAd->copy()->subDays(self::WINDOW));
            $to    = self::be($dueAd->copy()->addDays(self::WINDOW));
            $visit = $visits->first(fn ($v) => $v['date'] && $v['date'] >= $from && $v['date'] <= $to);
            $appt  = $appts->first(fn ($a) => $a->appointment_date >= $from && $a->appointment_date <= $to);
            if ($visit) { $st = 'done'; }
            elseif ($appt) { $st = 'booked'; }
            elseif ($today->gt($dueAd->copy()->addDays(self::WINDOW))) { $st = 'missed'; }
            elseif ($today->gte($dueAd->copy()->subDays(self::WINDOW))) { $st = 'due'; }
            else { $st = 'upcoming'; }
            $out[] = ['n' => $n, 'label' => 'หลังจบ ' . $n . ' เดือน', 'due' => self::be($dueAd), 'status' => $st, 'appt' => $appt, 'visit' => $visit];
        }
        return $out;
    }

    /** ยังอยู่ในช่วงติดตาม = มีรอบที่ยังไม่มาและยังไม่เลยช่วง */
    public static function active(TbRegistration $reg): bool
    {
        foreach (self::plan($reg) as $p) { if (in_array($p['status'], ['booked', 'due', 'upcoming'], true)) { return true; } }
        return false;
    }

    /** บัตรที่ปิดแล้วของคนไข้ที่ยังติดตามหลังรักษาอยู่ (ล่าสุด) — หน้าตรวจใช้โชว์ลิงก์บัตร */
    public static function activeCardFor(int $patientId): ?TbRegistration
    {
        foreach (TbRegistration::where('patient_id', $patientId)->whereIn('outcome', self::OUTCOMES)->orderByDesc('id')->get() as $r) {
            if (self::active($r)) { return $r; }
        }
        return null;
    }

    /**
     * ลงนัดรอบที่ยังไม่มีนัด/ยังไม่มา (เฉพาะที่ยังไม่เลยช่วง) — ผูกกับ visit คลินิกวัณโรคล่าสุดของคนไข้
     * วันหยุด/เสาร์-อาทิตย์เลื่อนไปวันทำการถัดไป · คืนจำนวนนัดที่ลง
     */
    public static function book(TbRegistration $reg): int
    {
        $visit = Visit::where('patient_id', $reg->patient_id)->where('clinic', 2)->orderByDesc('date')->orderByDesc('id')->first();
        if (! $visit) { return 0; }
        $n = 0;
        foreach (self::plan($reg) as $p) {
            if (! in_array($p['status'], ['due', 'upcoming'], true)) { continue; }
            $ad = Carbon::parse(Utillity::BE2AD($p['due']));
            if ($ad->lt(Carbon::today())) { $ad = Carbon::today()->addDay(); }
            for ($i = 0; $i < 30 && Holiday::isClinicClosed($ad); $i++) { $ad->addDay(); }
            VisitAppointment::create([
                'visit_id'         => $visit->id,
                'patient_id'       => $reg->patient_id,
                'appointment_date' => self::be($ad),
                'reason'           => 'ติดตามหลังรักษาวัณโรค ' . $p['label'],
                'pre_visit'        => 'CXR',
                'made_by'          => auth()->id(),
            ]);
            $n++;
        }
        if ($n) { VisitAppointment::syncMirror($visit->id); }
        return $n;
    }

    private static function be(Carbon $ad): string
    {
        return ($ad->year + 543) . $ad->format('-m-d');
    }
}
