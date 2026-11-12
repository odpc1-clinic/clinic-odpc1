<?php

namespace App\Services;

use App\Models\Lab;
use App\Models\LabSub;
use App\Models\Visit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * สั่ง/ถอดรายการตรวจ Lab ในคลินิก (lab_subs) ของใบ Lab หนึ่งใบ — กติกากลางที่เดียว (แยกออกมา 2569-10-02)
 *
 * ใช้ 2 แบบ:
 *  - syncFull()   หน้าสั่ง Lab เต็ม (LabSubController@saveConfig) ส่ง "ทั้งใบ" มา
 *  - applyDelta() บัตร TB 01 / หน้าตรวจ TB ส่ง "เฉพาะที่ติ๊กเพิ่ม/ติ๊กออก" มา (ไม่ลบของที่อีกห้องเพิ่งสั่ง)
 *
 * กติกาเดียวกันทั้งสองแบบ:
 *  - ของที่ยังสั่งอยู่ = คงแถวเดิม (id เดิม ผลเดิม) — billing_items ของค่าแล็บผูกกับ id นี้
 *  - ถอดออก = ลบเฉพาะแถวที่ยังไม่มีผล · แถวที่ห้อง Lab ลงผลแล้วถอดไม่ได้ (ผู้ใช้ตัดสินใจ 2569-09-10)
 *  - เคสจริงที่ทำให้ต้องเทียบรายการ: visit 12319 (2569-09-09) ถูกลบ-สร้างใหม่ 4 รอบ ผลหายเกลี้ยง
 */
class LabSubOrder
{
    /** กุญแจระบุว่าแถวสองแถวคือ "รายการเดียวกัน" ไหม */
    public static function key($method, $specimenFrom, $specimenFromOther, $methodOther): string
    {
        return implode('|', [
            (string) $method,
            (string) $specimenFrom,
            trim((string) $specimenFromOther),
            trim((string) $methodOther),
        ]);
    }

    public static function keyOf(LabSub $s): string
    {
        return self::key($s->method, $s->specimen_from, $s->specimen_from_other, $s->method_other);
    }

    /**
     * ทั้งใบ: $items = [[attrs, result|null, remark|null], ...] ตามลำดับที่ติ๊กในฟอร์ม
     * คืนชื่อรายการที่ถอดไม่ได้เพราะมีผลแล้ว
     */
    public static function syncFull(int $labId, array $items): array
    {
        $existing = LabSub::where('lab_id', $labId)->get();
        $pool = [];                       // key -> คิวของแถวเดิมที่ยังไม่ถูกจับคู่
        foreach ($existing as $e) { $pool[self::keyOf($e)][] = $e; }
        $keptIds = [];

        foreach ($items as [$attrs, $incomingResult, $incomingRemark]) {
            $key = self::key($attrs['method'] ?? null, $attrs['specimen_from'] ?? null,
                             $attrs['specimen_from_other'] ?? null, $attrs['method_other'] ?? null);

            if (! empty($pool[$key])) {
                $row = array_shift($pool[$key]);
                $keptIds[] = $row->id;
                // แถวเดิมที่มีผลแล้ว = ไม่แตะอะไรเลย กันผลถูกทับด้วยค่าว่างจากฟอร์มที่เปิดค้างไว้
                if (trim((string) $row->result) === '') {
                    if ($incomingResult !== null) { $row->result = $incomingResult; }
                    if ($incomingRemark !== null) { $row->remark = $incomingRemark; }
                    $row->save();
                }
                continue;
            }

            $row = LabSub::create($attrs + ['lab_id' => $labId, 'result' => $incomingResult, 'remark' => $incomingRemark]);
            $keptIds[] = $row->id;
        }

        $blocked = [];
        foreach ($existing as $e) {
            if (in_array($e->id, $keptIds, true)) { continue; }
            if (trim((string) $e->result) !== '') { $blocked[] = $e->method(); continue; }
            $e->delete();
        }
        return $blocked;
    }

    /**
     * เฉพาะส่วนต่าง: $add / $remove = [attrs, ...] ที่ผู้ใช้ติ๊กเพิ่ม/ติ๊กออกเทียบกับตอนเปิดหน้า
     * ของที่ไม่ได้แตะ (รวมถึงที่อีกห้องเพิ่งสั่งระหว่างนั้น) ไม่ถูกยุ่ง
     * คืน ['added' => int, 'removed' => int, 'blocked' => [ชื่อ...]]
     */
    public static function applyDelta(int $labId, array $add, array $remove): array
    {
        $existing = LabSub::where('lab_id', $labId)->get();
        $pool = [];
        foreach ($existing as $e) { $pool[self::keyOf($e)][] = $e; }

        $added = 0; $removed = 0; $blocked = [];
        foreach ($remove as $attrs) {
            $key = self::key($attrs['method'] ?? null, $attrs['specimen_from'] ?? null,
                             $attrs['specimen_from_other'] ?? null, $attrs['method_other'] ?? null);
            foreach ($pool[$key] ?? [] as $i => $row) {
                if (trim((string) $row->result) !== '') { $blocked[] = $row->method(); continue; }
                $row->delete();
                unset($pool[$key][$i]);
                $removed++;
            }
        }
        foreach ($add as $attrs) {
            $key = self::key($attrs['method'] ?? null, $attrs['specimen_from'] ?? null,
                             $attrs['specimen_from_other'] ?? null, $attrs['method_other'] ?? null);
            if (! empty($pool[$key])) { continue; }   // มีอยู่แล้ว (อีกห้องเพิ่งสั่ง) — ไม่สร้างซ้ำ
            $pool[$key][] = LabSub::create($attrs + ['lab_id' => $labId, 'result' => null, 'remark' => null]);
            $added++;
        }
        return ['added' => $added, 'removed' => $removed, 'blocked' => array_values(array_unique($blocked))];
    }

    /** ลงชื่อผู้ส่ง Lab ใน visit — เหมือนหน้าสั่ง Lab เต็ม (คนที่กดบันทึก = ผู้ส่ง/ผู้แตะล่าสุด) */
    public static function stampVisit(Lab $lab): void
    {
        if (! Auth::check()) { return; }
        $visit = Visit::find($lab->visit_id);
        if ($visit) {
            $visit->lab_by     = Auth::id();
            $visit->handled_by = Auth::id();
            $visit->save();
        }
    }

    /**
     * ใบ Lab ของ visit — ปกติเปิด visit แล้วระบบสร้างให้เอง (PatientController / เปิด visit คลินิกอื่น)
     * visit เก่าบางตัวไม่มี -> สร้างแบบเดียวกับตอนเปิด visit (แค่ patient_id + visit_id)
     */
    public static function labFor(Visit $visit): Lab
    {
        // ต้อง fresh() — แถวที่เพิ่งสร้างยังไม่มีคีย์ status ในตัวโมเดล แล้ว Lab มีเมธอดชื่อ status()
        // การอ่าน $lab->status จะวนเข้าเมธอดนั้นแทนคอลัมน์ (Undefined property)
        return Lab::where('visit_id', $visit->id)->first()
            ?: Lab::create(['patient_id' => $visit->patient_id, 'visit_id' => $visit->id])->fresh();
    }

    /**
     * สั่งจากบัตร TB 01 / หน้าตรวจ TB — ใช้ applyDelta แล้วส่งใบเข้าห้อง Lab
     *
     * หน้าสั่ง Lab เต็มให้พยาบาลเลือกสถานะเอง (ค่าตั้งต้น "ส่ง Lab" + วัน/เวลาเก็บ = ตอนนี้) แล้วกดบันทึก
     * ที่นี่ไม่มีช่องสถานะ จึงตั้งค่าเท่ากับค่าตั้งต้นของหน้านั้นให้เมื่อมีการสั่งเพิ่ม:
     * ใบยังไม่ส่ง หรือปิดไปแล้ว (เสร็จ) -> "ส่ง Lab" ห้อง Lab จะเห็นงานใหม่ (หน้าห้อง Lab ซ่อนใบที่สถานะว่าง/เสร็จ)
     */
    public static function orderFromTb(Visit $visit, array $add, array $remove): array
    {
        return DB::transaction(function () use ($visit, $add, $remove) {
            $lab = self::labFor($visit);
            $res = self::applyDelta($lab->id, $add, $remove);
            // ตรวจข้างเตียง (DTX) พยาบาลลงผลเอง — สั่งแค่นั้นไม่ต้องขึ้นคิวห้อง Lab (LabSub::$pocMethods)
            $labWork = collect($add)->contains(fn ($a) => ! \App\Models\LabSub::isPoc($a['method'] ?? null));

            if ($res['added'] > 0 && $labWork) {
                if (! $lab->status || \App\Enums\LabStatus::Done->is($lab->status)) { $lab->status = \App\Enums\LabStatus::Sent->value; }
                if (! $lab->collected_date) { $lab->collected_date = \App\Models\VisitAppointment::todayBe(); }
                if (! $lab->collected_at)   { $lab->collected_at = now()->format('H:i:s'); }
                $lab->save();
            }
            if ($res['added'] > 0 || $res['removed'] > 0) {
                self::stampVisit($lab);
            }
            $res['lab_id'] = $lab->id;
            return $res;
        });
    }
}
