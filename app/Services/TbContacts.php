<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\TbHouseholdMember;
use App\Models\TbRegistration;
use App\Models\TbScreening;
use Illuminate\Support\Facades\Schema;

/**
 * ผู้สัมผัส ↔ ผู้ป่วยต้นเหตุ (index case) — 2569-10-22
 *
 * คลินิกขอ: บัตร LTBI แยก และลิงก์ไปบัตร index ได้ · บัตร index ลิงก์ไปหาผู้สัมผัส (ทั้งที่เป็น LTBI และไม่เป็น)
 * ผูกได้ 3 ทาง แล้วรวมเป็นรายชื่อเดียวในบัตร index:
 *   1. ตารางสมาชิกร่วมบ้านในใบคัดกรองของ index (tb_household_members — มี HN = ผูกคนไข้แล้ว)
 *   2. ใบคัดกรองของผู้สัมผัส ที่ระบุผู้ป่วยต้นเหตุ (tb_screenings.index_registration_id)
 *   3. บัตรของผู้สัมผัส (LTBI หรือป่วยเอง) ที่ระบุผู้ป่วยต้นเหตุ (tb_registrations.index_registration_id)
 */
class TbContacts
{
    public static function ready(): bool
    {
        static $r = null;
        return $r ??= Schema::hasColumn('tb_registrations', 'index_registration_id')
            && Schema::hasColumn('tb_screenings', 'index_registration_id');
    }

    /**
     * หาบัตรของผู้ป่วยต้นเหตุจาก HN หรือเลขทะเบียน TB (หรือเลขบัตรประชาชน)
     * เอาบัตรวัณโรค / ดื้อยาก่อน (ไม่ใช่ LTBI) · ที่ยังรักษาอยู่ก่อน · ใหม่สุดก่อน · ไม่นับบัตรยกเลิก
     */
    public static function resolveIndex(?string $ref, ?int $notPatient = null): ?TbRegistration
    {
        $ref = trim((string) $ref);
        if ($ref === '') { return null; }

        $q = TbRegistration::query()
            ->where(fn ($w) => $w->whereNull('outcome')->orWhere('outcome', '!=', TbRegistration::OUTCOME_VOID))
            ->whereIn('registry_type', [TbRegistration::TYPE_TB, TbRegistration::TYPE_MDR]);
        if ($notPatient) { $q->where('patient_id', '!=', $notPatient); }

        $byNo = (clone $q)->where('tb_no', $ref)->orderByRaw('outcome IS NULL DESC')->orderByDesc('id')->first();
        if ($byNo) { return $byNo; }

        $digits = preg_replace('/\D/', '', $ref);
        $pid = Patient::where('code', $ref)->value('id')
            ?? (strlen($digits) === 13 ? Patient::where('id_card_number', $digits)->value('id') : null);
        if (! $pid) { return null; }

        return (clone $q)->where('patient_id', $pid)->orderByRaw('outcome IS NULL DESC')->orderByDesc('id')->first();
    }

    /** ข้อความสั้นของบัตร index สำหรับลิงก์ */
    public static function label(?TbRegistration $r): string
    {
        if (! $r) { return '-'; }
        $p = $r->patient;
        return trim(($p->name ?? '') . ' ' . ($p->surname ?? '')) . ' · HN ' . ($p->code ?? '-')
            . ($r->tb_no ? ' · ' . $r->tb_no : '');
    }

    /**
     * รายชื่อผู้สัมผัสทั้งหมดของบัตร index — รวมจาก 3 ทาง ไม่ซ้ำคน
     * @return array<int, array{name:string, hn:?string, patient_id:?int, relation:?string, sources:array, cards:\Illuminate\Support\Collection, screening:?TbScreening}>
     */
    public static function forIndex(TbRegistration $index): array
    {
        $rows = [];
        $key = fn ($pid, $name) => $pid ? 'p' . $pid : 'n' . mb_strtolower(trim((string) $name));
        $add = function ($pid, $name, $hn, $relation, $source) use (&$rows, $key) {
            $k = $key($pid, $name);
            if (! isset($rows[$k])) {
                $rows[$k] = ['name' => (string) $name, 'hn' => $hn, 'patient_id' => $pid, 'relation' => $relation, 'sources' => []];
            }
            $rows[$k]['sources'][$source] = true;
            if (! $rows[$k]['relation'] && $relation) { $rows[$k]['relation'] = $relation; }
            if (! $rows[$k]['hn'] && $hn) { $rows[$k]['hn'] = $hn; }
        };

        // 1) สมาชิกร่วมบ้าน (ใบคัดกรองทุกใบของคนไข้ index)
        $scIds = TbScreening::where('patient_id', $index->patient_id)->pluck('id');
        foreach (TbHouseholdMember::whereIn('tb_screening_id', $scIds)->orderBy('id')->get() as $m) {
            $add($m->patient_id, $m->name, $m->hn, $m->relation, 'ร่วมบ้าน');
        }

        if (self::ready()) {
            // 2) ใบคัดกรองของผู้สัมผัสที่ระบุ index นี้
            foreach (TbScreening::with('patient')->where('index_registration_id', $index->id)->get() as $s) {
                $p = $s->patient;
                $add($s->patient_id, trim(($p->name ?? '') . ' ' . ($p->surname ?? '')), $p->code ?? null, null, 'คัดกรองผู้สัมผัส');
            }
            // 3) บัตรของผู้สัมผัส (LTBI / ป่วยเอง) ที่ระบุ index นี้
            foreach (TbRegistration::with('patient')->where('index_registration_id', $index->id)->get() as $r) {
                $p = $r->patient;
                $add($r->patient_id, trim(($p->name ?? '') . ' ' . ($p->surname ?? '')), $p->code ?? null, null, 'บัตร' . ($r->registry_type == TbRegistration::TYPE_LTBI ? ' LTBI' : ''));
            }
        }

        // สถานะของแต่ละคน: บัตรทั้งหมด (ยกเว้นบัตรยกเลิก) + ใบคัดกรองล่าสุด
        $pids = array_values(array_filter(array_column($rows, 'patient_id')));
        $cards = TbRegistration::whereIn('patient_id', $pids)
            ->where(fn ($w) => $w->whereNull('outcome')->orWhere('outcome', '!=', TbRegistration::OUTCOME_VOID))
            ->where('id', '!=', $index->id)->orderByDesc('id')->get()->groupBy('patient_id');
        $screens = TbScreening::with('visit')->whereIn('patient_id', $pids)->orderByDesc('id')->get()->groupBy('patient_id');
        foreach ($rows as &$r) {
            $r['cards'] = $r['patient_id'] ? ($cards[$r['patient_id']] ?? collect()) : collect();
            $r['screening'] = $r['patient_id'] ? optional($screens[$r['patient_id']] ?? null)->first() : null;
        }
        unset($r);

        return array_values($rows);
    }
}
