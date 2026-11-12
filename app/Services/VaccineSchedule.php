<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\Medicine;
use App\Models\Visit;
use App\Models\VisitVaccine;
use Carbon\Carbon;

/**
 * นัดวัคซีนเข็มถัดไปอัตโนมัติ (คลินิก TMC ขอ 2569-09-30)
 *
 * ระยะห่างของแต่ละวัคซีนเก็บที่ medicines.vaccine_intervals เช่น "0, 1m, 6m" (นับจากเข็มแรก)
 *   หน่วย: d = วัน · w = สัปดาห์ · m = เดือน · y = ปี · มีตัวเดียว ("0") = เข็มเดียว ไม่นัดต่อ
 *   แก้ได้ที่หน้า "ตารางนัดวัคซีน" (/vaccine-schedule) — หมอยืนยันตารางเอง ระบบไม่เดาเอง
 *
 * นับว่าเป็นเข็มที่เท่าไหร่จากประวัติในแท็บฉีดวัคซีน รวมที่ฉีดมาจากที่อื่น (คลินิกยืนยัน)
 * วันนัดตรงเสาร์-อาทิตย์/วันหยุดราชการ เลื่อนไปวันถัดไปที่คลินิกเปิด (คลินิกยืนยัน)
 */
class VaccineSchedule
{
    public const UNITS = ['d' => 'วัน', 'w' => 'สัปดาห์', 'm' => 'เดือน', 'y' => 'ปี'];

    /** "0, 1m, 6m" -> [[0,'d'], [1,'m'], [6,'m']] · รูปแบบผิดคืน [] */
    public static function parse(?string $spec): array
    {
        $out = [];
        foreach (preg_split('/[,\s]+/', trim((string) $spec), -1, PREG_SPLIT_NO_EMPTY) as $tok) {
            if (! preg_match('/^(\d{1,3})([dwmy]?)$/i', $tok, $m)) { return []; }
            $out[] = [(int) $m[1], strtolower($m[2] ?: 'd')];
        }
        return $out;
    }

    /** ข้อความให้คนอ่าน: "เข็ม 1 → +1 เดือน → +6 เดือน" */
    public static function describe(?string $spec): string
    {
        $p = self::parse($spec);
        if (! $p) { return '—'; }
        if (count($p) === 1) { return 'เข็มเดียว'; }
        return implode(' → ', array_map(fn ($o, $i) => $i === 0 ? 'เข็ม 1' : '+' . $o[0] . ' ' . self::UNITS[$o[1]], $p, array_keys($p)));
    }

    /**
     * เข็มถัดไปของคนไข้คนนี้ สำหรับวัคซีนตัวนี้
     *
     * @param int|null $excludeVisitId visit ปัจจุบัน — เข็มที่ลงไว้ใน visit นี้แล้วไม่นับเป็น "ประวัติก่อนหน้า"
     * @return array{dose_now:int, next_dose:?int, date:?Carbon, total:int}
     */
    public static function next(int $patientId, Medicine $med, ?int $excludeVisitId = null, ?Carbon $today = null): array
    {
        $today   = ($today ?? now())->copy()->startOfDay();
        $offsets = self::parse($med->vaccine_intervals);

        // นับรวมทุกยี่ห้อของวัคซีนชนิดเดียวกัน — เปลี่ยนยี่ห้อกลางคอร์ส (Hep B, HPV4→HPV9) ต้องนับเข็มต่อ
        // ไม่ใช่กลับไปเริ่มเข็ม 1 (รีวิว 2569-09-30)
        $family = self::family($med->name);
        $prior = VisitVaccine::where('patient_id', $patientId)
            ->when($excludeVisitId, fn ($q) => $q->where(fn ($w) => $w->whereNull('visit_id')->orWhere('visit_id', '!=', $excludeVisitId)))
            ->orderByRaw('vaccinated_date IS NULL, vaccinated_date')
            ->get(['medicine_id', 'name', 'vaccinated_date'])
            ->filter(fn ($v) => (int) $v->medicine_id === (int) $med->id || $v->name === $med->name
                || ($family !== null && self::family($v->name) === $family))
            ->values();

        $doseNow = $prior->count() + 1;
        $res = ['dose_now' => $doseNow, 'next_dose' => null, 'date' => null, 'total' => count($offsets)];
        if (! $offsets || $doseNow >= count($offsets)) {
            return $res;                                   // ไม่มีตาราง / เข็มเดียว / ครบคอร์สแล้ว
        }

        // นับจากวันฉีดเข็มแรก (ถ้ารู้) ตามตารางมาตรฐาน · เข็มแรกคือวันนี้ = นับจากวันนี้
        $first = optional($prior->first())->vaccinated_date;
        $base  = $first ? Carbon::parse($first)->startOfDay() : $today;
        $date  = self::add($base, $offsets[$doseNow]);

        // ต้องห่างจากเข็มที่ฉีดวันนี้ไม่น้อยกว่าระยะระหว่างเข็มตามตาราง — เอาวันที่ช้ากว่า
        // เข็มก่อนหน้าฉีดช้า (Hep B: เข็ม 1 5 ม.ค. เข็ม 2 1 มิ.ย.) เดิมนัดเข็ม 3 5 ก.ค. ห่างแค่ 5 สัปดาห์
        // ที่ถูกคือ 1 พ.ย. (ห่าง 5 เดือนตามตาราง) (รีวิว 2569-09-30) · ครอบกรณีประวัติเก่ามากด้วย ไม่นัดย้อนหลัง
        [$a, $u] = $offsets[$doseNow];
        [$b, $v] = $offsets[$doseNow - 1];
        $gap = self::add(self::add($today, [$a, $u]), [-$b, $v]);
        if ($gap->gt($date)) { $date = $gap; }
        if ($date->lte($today)) { $date = $today->copy()->addDay(); }

        $res['next_dose'] = $doseNow + 1;
        $res['date']      = self::nextOpenDay($date);

        return $res;
    }

    /**
     * ชนิดวัคซีน (ไม่สนยี่ห้อ) ใช้นับเข็มข้ามยี่ห้อ — null = ไม่รู้ชนิด นับเฉพาะตัวเดียวกัน
     * Twinrix (A+B รวม) ตารางคนละแบบ ไม่นับรวมกับ Hep A/Hep B เดี่ยว ให้หมอตัดสินเอง
     */
    public static function family(?string $name): ?string
    {
        $n = mb_strtolower((string) $name);
        if (str_contains($n, 'twinrix')) { return null; }
        $map = [
            'hepb'   => '/hepatitis\s*b|engerix|hep\s*b\b/u',
            'hepa'   => '/hepatitis\s*a|havrix|hep\s*a\b/u',
            'hpv'    => '/hpv|gardasil|มะเร็งปากมดลูก/u',
            'rabies' => '/rabies|พิษสุนัขบ้า|verorab|speeda|chirorab/u',
            'je'     => '/\bje\b|ไข้สมองอักเสบ|imojev/u',
            'menb'   => '/bexsero|group\s*b|type\s*b/u',
        ];
        foreach ($map as $k => $re) {
            if (preg_match($re, $n)) { return $k; }
        }
        return null;
    }

    /** วันนัดตรงวันที่คลินิกปิด (เสาร์-อาทิตย์/วันหยุดราชการ) เลื่อนไปวันถัดไปที่เปิด */
    public static function nextOpenDay(Carbon $date): Carbon
    {
        $d = $date->copy();
        for ($i = 0; $i < 30 && Holiday::isClinicClosed($d); $i++) { $d->addDay(); }
        return $d;
    }

    private static function add(Carbon $d, array $o): Carbon
    {
        [$n, $u] = $o;
        $d = $d->copy();
        switch ($u) {
            case 'w': return $d->addWeeks($n);
            case 'm': return $d->addMonthsNoOverflow($n);
            case 'y': return $d->addYearsNoOverflow($n);
            default:  return $d->addDays($n);
        }
    }

    /**
     * ใส่นัดเข็มถัดไปลงแผนนัดฉีดวัคซีนของ visit (หมอสั่งวัคซีนในหน้าสั่งยา)
     * ไม่ซ้ำของเดิม · วันเดียวกันรวมเป็นนัดเดียว · เต็มแผนแล้วไม่เพิ่ม (หมอจัดเอง) — คืนข้อความสรุปหรือ null
     */
    public static function addToPlan(Visit $visit, Medicine $med): ?string
    {
        $n = self::next((int) $visit->patient_id, $med, (int) $visit->id);
        if (! $n['date']) { return null; }

        $dateTh = $n['date']->format('d/m/') . ($n['date']->year + 543);
        $dose   = 'Dose ' . $n['next_dose'];
        $plan   = VisitVaccine::normalizePlan($visit->tmc_vaccine_plan);

        foreach ($plan as $row) {
            foreach ($row['items'] as $it) {
                if ($it['name'] === $med->name && $it['dose'] === $dose) { return null; }   // มีแล้ว
            }
        }

        $placed = false;
        foreach ($plan as &$row) {
            if ($row['date'] === $dateTh && count($row['items']) < VisitVaccine::PLAN_ITEMS_PER_VISIT) {
                $row['items'][] = ['name' => $med->name, 'dose' => $dose];
                $placed = true;
                break;
            }
        }
        unset($row);
        if (! $placed) {
            if (count($plan) >= VisitVaccine::PLAN_MAX_ROWS) { return null; }
            $plan[] = ['date' => $dateTh, 'items' => [['name' => $med->name, 'dose' => $dose]]];
        }

        // เรียงนัดตามวันที่ ใบนัดจะอ่านง่าย (แถวที่ยังไม่มีวันที่ไว้ท้าย)
        $key = function ($r) {
            return preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $r['date'], $m)
                ? sprintf('%04d%02d%02d', $m[3], $m[2], $m[1]) : '99999999';
        };
        usort($plan, fn ($a, $b) => strcmp($key($a), $key($b)));

        $visit->tmc_vaccine_plan = $plan;
        $visit->save();

        return "{$med->name} {$dose} นัด {$dateTh}";
    }

    /**
     * ลบ/เปลี่ยนใบสั่งวัคซีน → เอานัดเข็มถัดไปที่ addToPlan() ใส่ไว้ออกจากแผน (รีวิว 2569-09-30)
     * เอาออกเฉพาะรายการที่ชื่อ + เข็มตรงกับที่ระบบคิดได้ (หมอแก้เองแล้วไม่แตะ)
     * และเฉพาะเมื่อ visit นี้ไม่มีใบสั่งวัคซีนตัวเดียวกันเหลืออยู่ · นัดที่ว่างลงเพราะเหตุนี้ลบทั้งแถว
     */
    public static function removeFromPlan(Visit $visit, Medicine $med): ?string
    {
        if (\App\Models\VisitMedicine::where('visit_id', $visit->id)->where('medicine_id', $med->id)->exists()) {
            return null;
        }
        $n = self::next((int) $visit->patient_id, $med, (int) $visit->id);
        if (! $n['next_dose']) { return null; }
        $dose = 'Dose ' . $n['next_dose'];

        $plan = VisitVaccine::normalizePlan($visit->tmc_vaccine_plan);
        $removed = false;
        foreach ($plan as $i => $row) {
            $keep = array_values(array_filter($row['items'], fn ($it) => ! ($it['name'] === $med->name && $it['dose'] === $dose)));
            if (count($keep) === count($row['items'])) { continue; }
            $removed = true;
            if ($keep) { $plan[$i]['items'] = $keep; } else { unset($plan[$i]); }
        }
        if (! $removed) { return null; }

        $visit->tmc_vaccine_plan = array_values($plan);
        $visit->save();

        return "{$med->name} {$dose}";
    }
}
