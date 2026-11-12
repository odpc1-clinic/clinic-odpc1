<?php

namespace App\Services\TbMonitor;

use App\Models\ExternalLabTest;
use App\Models\LabCatalogItem;
use App\Models\TbDepressionScreen;
use App\Models\TbMonitorCheck;
use App\Models\TbRegistration;
use App\Models\TbVisionTest;
use App\Models\VisitAppointment;
use App\Support\TbMonitorDefinitions;
use Illuminate\Support\Facades\DB;

/**
 * ตารางติดตามการรักษาของบัตร TB 01 หนึ่งใบ — ดึงข้อมูลจริงจากที่ต่าง ๆ แล้วส่งให้ TbMonitorEngine คำนวณ
 *
 * อ่านอย่างเดียว ไม่เขียนอะไรลงฐาน · ตารางใหม่ (ติ๊กมือ/สายตา/ซึมเศร้า) ยังไม่ขึ้น = ข้ามส่วนนั้นไปเฉย ๆ
 * เรียกหลายที่ในหน้าเดียว (หัวบัตร / กล่องสั่งตรวจ / แท็บติดตาม) จึงจำผลไว้ทั้ง request
 *
 * ที่มาของ "ตรวจแล้ว" (ดู TbMonitorDefinitions สำหรับรายการต่อแถว)
 *   ตารางเสมหะ (tb_sputum_exams)  จับคู่ด้วยป้ายเดือนที่พยาบาลกรอก (0 / 2wk / 1 / 2 ...) ไม่ใช่วันที่เก็บ
 *                                เพราะช่องวันที่ในข้อมูลจริงพิมพ์ผิดหลายแถว (ปี 2570-2572, ค.ศ. ปน) — ป้ายเชื่อถือได้กว่า
 *                                แถวป้ายอิสระ (เช่น 6wk) ถึงใช้วันที่เก็บ
 *   ใบสั่งตรวจวัณโรค (tb_screenings ของ visit) · X-ray · DST · Lab ในคลินิก · Lab นอก · น้ำหนัก · visit คลินิก TB
 */
class TbMonitor
{
    private static array $cache = [];

    public static function for(TbRegistration $reg): array
    {
        return self::$cache[$reg->id] ??= self::build($reg);
    }

    /**
     * ใช้ในหน้าจอ — คำนวณพังต้องไม่ทำให้บัตร TB ทั้งใบเปิดไม่ได้ (ระบบใช้กับคนไข้จริงทุกวัน)
     * พัง = คืน null ให้กล่องโชว์ข้อความแทน แล้วจดลง log ไว้ไล่ทีหลัง
     */
    public static function safe(TbRegistration $reg): ?array
    {
        try {
            return self::for($reg);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    public static function forget(): void
    {
        self::$cache = [];
    }

    /** เลือกชุดตารางตามประเภททะเบียน/สูตรยา — คืน null = ไม่มีตาราง */
    public static function scheduleFor(TbRegistration $reg): ?array
    {
        $rt = (int) $reg->registry_type;
        $text = '';
        try { $text = (string) ($reg->currentRegimenText() ?: $reg->regimen); } catch (\Throwable $e) { $text = (string) $reg->regimen; }
        $key = self::scheduleKeyFor($rt, $text);
        if ($key === 'tpt') { return TbMonitorDefinitions::tptFor($text); }
        // สูตรกำหนดเอง (Individualized) + แพทย์กำหนดตารางไว้ = ใช้ตารางนั้น · ยังไม่กำหนด = ตารางดื้อยามาตรฐานไปก่อน (คลินิกตกลง 2569-10-22)
        if (self::isCustomRegimen($text) && is_array($plan = self::planOf($reg)) && ($def = TbMonitorDefinitions::custom($plan))) {
            return $def;
        }
        return $key ? TbMonitorDefinitions::get($key) : null;
    }

    /** สูตรที่ไม่ใช่สูตรมาตรฐาน = แพทย์กำหนดเอง (Individualized หรือชื่อสูตรที่พิมพ์เอง) */
    public static function isCustomRegimen(string $regimen): bool
    {
        if (preg_match('/Individuali[sz]ed/i', $regimen)) { return true; }
        return trim($regimen) !== ''
            && ! preg_match('/BPaL|Shorter|HRZE|\bH\s*R\b|\d\s*HR\b|3HP|1HP|INH|^\s*\d+\s*Lfx|4FDC|2FDC|RZE|B6\b|^\s*\d+\s*H?R\b|^\s*\d+(\s*-\s*\d+)?\s*H\b/i', $regimen);
    }

    /** ตารางกำหนดเองของบัตร — ยังไม่รัน migration (ไม่มีคอลัมน์) = null */
    public static function planOf(TbRegistration $reg): ?array
    {
        if (! array_key_exists('monitor_plan', $reg->getAttributes())) { return null; }
        $p = $reg->monitor_plan;
        return is_array($p) && ! empty($p['cells']) ? $p : null;
    }

    /**
     * วันเริ่มนับ "เดือนที่" ของบัตร (พ.ศ. Y-m-d) — ตัวเดียวกับ ด.0 ของตารางติดตาม (คลินิกขอ 2569-10-26)
     * เดิมทุกที่นับจากวันเริ่มรักษาของบัตร คนที่เปลี่ยนเป็นสูตรดื้อยากลางคอร์สจึงขึ้น "เดือนที่ 19" ทั้งที่เพิ่งเริ่ม BPaL 4 เดือน
     * และพยาบาลลงเสมหะเป็นเดือน 18 ตามที่ระบบเติมให้
     */
    public static function countStartBe(TbRegistration $reg): ?string
    {
        $ad = $reg->id ? self::scheduleStartAd($reg, ['count' => true]) : null;
        // วันเริ่มสูตรที่ลงเป็นอนาคต (พิมพ์ปีผิด) ไม่เอามานับ — ใช้วันเริ่มรักษาของบัตรแทน
        if ($ad && $ad > now()->format('Y-m-d')) { $ad = null; }
        return $ad ? self::toBe($ad) : ($reg->treatment_start_date ?: null);
    }

    /**
     * วันเริ่มของ "ช่วงสูตรกลุ่มเดียวกับตารางที่ใช้อยู่" (ค.ศ.) — ไล่ประวัติสูตรจากล่าสุดย้อนไป ตราบที่ยังเป็นกลุ่มเดียวกัน (ds/dr/tpt)
     * เปลี่ยนสูตรภายในกลุ่ม (BPaL → BPaLM) = นับต่อจากช่วงแรกของกลุ่ม · ไม่มีประวัติสูตร/ทะเบียนดื้อยาทั้งคอร์ส = null (ใช้วันเริ่มรักษาของบัตร)
     */
    private static function scheduleStartAd(TbRegistration $reg, ?array $def): ?string
    {
        if (! $def) { return null; }
        // ต้นช่วงนับรอบล่าสุด — ตัวเดียวกับที่ใบสรุปการรักษาแบ่งรอบ (TbRegistration::doseSegments)
        // บัตรดื้อยาก็แยกกลุ่มตามชื่อสูตร ไม่ใช่ประเภททะเบียน — ไม่งั้นช่วง 2HRZE เดิมถูกนับเป็นกลุ่มดื้อยาด้วย
        // แล้ว ด.0 ถอยไปวันเริ่มคอร์สแรก (เคยเจอ: เปลี่ยนบัตรเป็นทะเบียนดื้อยาแล้วตารางไปนับจากวันเริ่มคอร์สแรก)
        try { $segs = $reg->doseSegments(); } catch (\Throwable $e) { return null; }
        $found = $segs[count($segs) - 1]['start'] ?? null;
        $ad = $found ? self::toAd($found) : null;
        $base = self::toAd($reg->treatment_start_date);
        return $ad && (! $base || $ad > $base) ? $ad : null;
    }

    /**
     * กติกาเลือกตาราง (PHP ล้วน ทดสอบได้)
     *   ทะเบียนวัณโรคแฝง หรือสูตรยาป้องกัน -> tpt
     *   ทะเบียนดื้อยา หรือสูตรมียาแนวสอง (Bdq, BPaL, Lzd, Cfz, Km, Eto ...) -> dr
     *   ที่เหลือ (รวม Hr-TB 6RZE-Lfx ซึ่งติดตามแบบไวต่อยา) -> ds
     */
    public static function scheduleKeyFor(int $registryType, string $regimen): ?string
    {
        // BPaL / BPaLM มีตารางของตัวเอง (BPaL(M)-07 · 6 เดือน) ไม่ใช่สูตรระยะสั้น 9 เดือน
        if (preg_match('/BPaL/i', $regimen)) { return 'bpal'; }
        if ($registryType === TbRegistration::TYPE_MDR) { return 'dr'; }
        // บัตรวัณโรค (ไม่ใช่ LTBI): สูตร HR ล้วน (4HR, 4HR (FDC), 10HR) = ระยะต่อเนื่องของสูตรไวต่อยา ไม่ใช่ TPT
        // เดิมเข้าเงื่อนไข TPT ข้างล่าง (3HR ก็เป็นสูตร TPT) → ขึ้นช่วงนับรอบใหม่ตอนลดยาเหลือ 4HR (คลินิกแจ้ง 2569-10-28 บัตร #39)
        if ($registryType === TbRegistration::TYPE_TB && preg_match('/^\s*\d+\s*HR\b/i', $regimen)) { return 'ds'; }
        if ($registryType === TbRegistration::TYPE_LTBI
            // สูตร LTBI ตามแนวทางกองวัณโรค: 3HP 1HP 4R 3HR 6-9H (+ 6Lfx สำหรับผู้สัมผัสดื้อยาที่แพทย์สั่ง)
            || preg_match('/^\s*(3HP|1HP|6\s*Lfx|\d+(\s*-\s*\d+)?\s*INH|3-4\s*H?R\b|\d+\s*H?R\b|\d+(\s*-\s*\d+)?\s*H\b|B6\b)/i', $regimen)) {
            return 'tpt';
        }
        if (preg_match('/Bdq|BPaL|Lzd|Shorter\s*DR|Individuali[sz]ed|MDR|\bKm|Cfz|Pto|Eto|\bCs\b|PAS/i', $regimen)) { return 'dr'; }
        return 'ds';
    }

    /** พ.ศ./ค.ศ. 'Y-m-d' (หรือ d/m/Y) -> ค.ศ. 'Y-m-d' · ข้อมูลเก่าบางแถวเป็น ค.ศ. อยู่แล้ว (ปี < 2400 ไม่ลบ) */
    public static function toAd(?string $v): ?string
    {
        $v = trim((string) $v);
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m)) { $v = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]); }
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) { return null; }
        $y = (int) $m[1];
        if ($y > 2400) { $y -= 543; }
        if (! checkdate((int) $m[2], (int) $m[3], $y)) { return null; }
        return sprintf('%04d-%s-%s', $y, $m[2], $m[3]);
    }

    public static function toBe(?string $ad): ?string
    {
        if (! $ad || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ad, $m)) { return null; }
        return sprintf('%04d-%s-%s', (int) $m[1] + 543, $m[2], $m[3]);
    }

    /** วันที่สั้นสำหรับช่องตาราง: 8/9/69 */
    public static function shortTh(?string $ad): string
    {
        if (! $ad || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ad, $m)) { return ''; }
        return (int) $m[3] . '/' . (int) $m[2] . '/' . substr((string) ((int) $m[1] + 543), 2);
    }

    /**
     * ป้ายแถวตารางเสมหะ -> วันที่ควรตรวจ (ค.ศ.) นับจากวันเริ่มยา · ป้ายอ่านไม่ออก = null
     * "0" = วันเริ่ม · "2wk" = +14 วัน · "3" = +3 เดือน · "2 (ซ้ำ)" = เดือน 2
     */
    public static function labelDate(?string $label, ?string $startAd): ?string
    {
        $start = TbMonitorEngine::date($startAd);
        if (! $start) { return null; }
        $l = trim((string) $label);
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(wk|w|สัปดาห์|สป\.?)$/iu', $l, $m)) {
            return $start->modify('+' . (int) round(7 * (float) $m[1]) . ' days')->format('Y-m-d');
        }
        if (preg_match('/^(\d{1,2})(\s*\(.*\))?$/u', $l, $m)) {
            return TbMonitorEngine::addMonths($start, (int) $m[1])->format('Y-m-d');
        }
        return null;
    }

    // ------------------------------------------------------------------

    private static function build(TbRegistration $reg): array
    {
        $def = self::scheduleFor($reg);
        $todayAd = self::toAd(VisitAppointment::todayBe()) ?? date('Y-m-d');
        // วันที่นับ ด.0: ถ้าเปลี่ยนจากสูตรไวต่อยาไปสูตรดื้อยา (หรือกลับกัน) ระหว่างคอร์ส ตารางต้องนับจากวันเริ่มสูตรกลุ่มนั้น
        // ไม่ใช่วันเริ่มรักษาของบัตร (คลินิกแจ้ง 2569-10-22: BPaL เริ่ม 2/6/69 แต่ ด.0 ขึ้น 13/3/68 วันเริ่ม 2HRZE)
        // หมอเลือกได้ว่าจะนับต่อหรือเริ่มนับใหม่ — แก้ "วันที่เริ่ม" ของช่วงสูตรในประวัติสูตรยา (ท้ายแท็บยาของบัตร)
        $startAd = self::scheduleStartAd($reg, $def) ?? self::toAd($reg->treatment_start_date);

        $out = [
            'ready'  => [
                'manual'     => TbMonitorCheck::ready(),
                'vision'     => TbVisionTest::ready(),
                'depression' => TbDepressionScreen::ready(),
            ],
            'grid'   => null,
            'vision' => null,
            'depression' => null,
            'presetIds' => [],
        ];

        $pt = $reg->patient;
        $visits = self::visits($reg, $startAd);
        $ctx = self::context($reg, $pt, $startAd, $todayAd);

        if ($def) {
            $events = self::collectEvents($reg, $def, $startAd, $visits, $todayAd);
            $ctx['baseline_dst'] = self::hasBaselineDst($events, $startAd);
            $manual = self::manual($reg);
            $out['grid'] = TbMonitorEngine::build($def, $startAd, $todayAd, $events, $manual, $ctx);
            $out['presetIds'] = self::presetIds(self::reminderItems($out['grid'])['presetTargets']);
        }

        $out['vision'] = self::visionAdvice($reg, $startAd, $todayAd);
        $out['depression'] = self::depressionAdvice($reg, $startAd, $todayAd);
        return $out;
    }

    /** visit ของคนไข้ในช่วงคอร์ส (ย้อนก่อนเริ่มยา 120 วัน — ผลตอนวินิจฉัย) — [id => ['date' => AD, 'clinic', 'weight']] */
    private static function visits(TbRegistration $reg, ?string $startAd): array
    {
        $from = $startAd ? (new \DateTimeImmutable($startAd))->modify('-120 days')->format('Y-m-d') : date('Y-m-d', strtotime('-365 days'));
        $fromBe = self::toBe($from);
        $out = [];
        // qtcf = EKG ที่คลินิกทำเอง ลงในสัญญาณชีพ (migration 2026_10_22_120000 — ยังไม่รันก็ไม่ดึง)
        $hasQt = \Illuminate\Support\Facades\Schema::hasColumn('visits', 'qtcf');
        foreach (DB::table('visits')->where('patient_id', $reg->patient_id)->where('date', '>=', $fromBe)
                     ->select(array_merge(['id', 'date', 'clinic', 'weight'], $hasQt ? ['qtcf'] : []))->orderBy('date')->get() as $v) {
            $ad = self::toAd($v->date);
            if ($ad) { $out[$v->id] = ['date' => $ad, 'clinic' => (int) $v->clinic, 'weight' => $v->weight, 'qtcf' => $v->qtcf ?? null]; }
        }
        return $out;
    }

    private static function context(TbRegistration $reg, $pt, ?string $startAd, string $todayAd): array
    {
        $age = null;
        $birth = $pt ? self::toAd($pt->getRawOriginal('birth_date')) : null;
        if ($birth) { $age = (int) (new \DateTimeImmutable($birth))->diff(new \DateTimeImmutable($todayAd))->y; }

        $hiv = (int) $reg->hiv_result;
        $disease = mb_strtolower((string) $reg->other_disease . ' ' . ($pt->congenitaldisease ?? ''));
        $hasDm = DB::table('tb_screenings')->where('patient_id', $reg->patient_id)->where('has_dm', 1)->exists();

        $liver = []; $kidney = [];
        if ($age !== null && $age > 60) { $liver[] = 'อายุ ' . $age . ' ปี'; $kidney[] = 'อายุ ' . $age . ' ปี'; }
        // ไม่เขียนคำว่า HIV ตรงนี้ — ข้อความนี้โชว์ในตาราง/แจ้งเตือนทั้งที่ผล HIV ถูกปิดไว้ (2569-10-06)
        if ($hiv === 2) { $liver[] = 'ผลเลือดในบัตร (กดดูผลในแท็บข้อมูลบัตร)'; }
        if (preg_match('/ตับ|liver|hepat|hbv|hcv|cirrho|สุรา|เหล้า|alcohol/u', $disease)) { $liver[] = 'โรคตับ/ดื่มสุรา (ในบัตร)'; }
        if (preg_match('/ไต|kidney|ckd|renal/u', $disease)) { $kidney[] = 'โรคไต (ในบัตร)'; }
        if ($hasDm || preg_match('/เบาหวาน|\bdm\b|diabet/u', $disease)) { $kidney[] = 'เบาหวาน'; }

        return [
            'female'  => $pt && (int) $pt->sex === 2 ? true : ($pt && (int) $pt->sex === 1 ? false : null),
            'hiv_pos' => $hiv === 2 ? true : ($hiv === 1 ? false : null),
            'age'     => $age,
            'risk'    => ['liver' => $liver, 'kidney' => $kidney],
            'active'  => $reg->isActive(),
        ];
    }

    /** DST ตอนเริ่มรักษา: มีแถว DST ส่งภายใน 30 วันหลังเริ่มยา หรือ Molecular แรกรับที่บอกผล RIF */
    private static function hasBaselineDst(array $events, ?string $startAd): bool
    {
        if (! $startAd) { return false; }
        $limit = (new \DateTimeImmutable($startAd))->modify('+30 days')->format('Y-m-d');
        foreach ($events['__dst'] ?? [] as $ev) {
            if (($ev['match'] ?? $ev['date']) && ($ev['match'] ?? $ev['date']) <= $limit) { return true; }
        }
        foreach ($events['__molecular'] ?? [] as $ev) {
            $d = $ev['match'] ?? $ev['date'];
            if ($d && $d <= $limit && preg_match('/RIF|rifampicin/i', (string) $ev['text'])) { return true; }
        }
        return false;
    }

    /** ผลตรวจทั้งหมดของคอร์ส แยกตามแหล่ง แล้วแจกให้แต่ละแถวตาม sources */
    private static function collectEvents(TbRegistration $reg, array $def, ?string $startAd, array $visits, string $todayAd): array
    {
        $src = [];   // sourceKey => [event...]
        $ev = fn ($date, $match, $text, $pos, $pending, $from) =>
            ['date' => $date, 'match' => $match, 'text' => $text, 'pos' => $pos, 'pending' => $pending, 'src' => $from];

        // ---- ตารางเสมหะ ----
        foreach ($reg->sputumExams as $r) {
            $label = (string) ($r->label !== null && $r->label !== '' ? $r->label : $r->month_no);
            $date  = self::toAd($r->collected_date);
            // เก็บก่อนช่วงสูตรปัจจุบัน = ป้ายเดือนเป็นของช่วงก่อน (2HRZE เดือน 2 ไม่ใช่ BPaL เดือน 2) — จับคู่ด้วยวันที่แทน
            $match = ($date && $startAd && $date < $startAd) ? $date : (self::labelDate($label, $startAd) ?? $date);
            // N/A = ไม่ได้ส่งตรวจ — ไม่นับเป็นผล (2569-10-27)
            $smear = array_filter([$r->spot, $r->collect, $r->collect2 ?? null], fn ($v) => trim((string) $v) !== '' && ! \App\Models\TbScreening::afbNotSent($v));
            $allNa = ! $smear && collect([$r->spot, $r->collect, $r->collect2 ?? null])->contains(fn ($v) => \App\Models\TbScreening::afbNotSent($v));
            if ($smear) {
                $parts = [];
                foreach (['Spot' => $r->spot, 'C1' => $r->collect, 'C2' => $r->collect2 ?? null] as $k => $v) {
                    if (trim((string) $v) !== '') { $parts[] = $k . ' ' . trim($v); }
                }
                $src['sputum_afb'][] = $ev($date, $match, implode(' · ', $parts), TbMonitorEngine::smearPositive($smear), false, 'ตารางเสมหะ ' . $label);
            } elseif (! $allNa && ($date || trim((string) $r->lab_serial) !== '')) {
                // ส่งเสมหะแล้วแต่ยังไม่มีผล (มีเลข Lab / วันที่เก็บ)
                $src['sputum_afb'][] = $ev($date, $match, 'ส่งแล้ว รอผล', null, true, 'ตารางเสมหะ ' . $label);
            }
            if (trim((string) $r->molecular) !== '') {
                $src['sputum_molecular'][] = $ev($date, $match, trim($r->molecular), TbMonitorEngine::molecularPositive($r->molecular), false, 'ตารางเสมหะ ' . $label);
            }
            if (trim((string) $r->culture) !== '') {
                $pend = (bool) preg_match('/pending/i', $r->culture);
                $src['sputum_culture'][] = $ev($date, $match, $pend ? 'ส่งแล้ว รอผล' : trim($r->culture), TbMonitorEngine::culturePositive($r->culture), $pend, 'ตารางเสมหะ ' . $label);
            }
        }

        // ---- ใบสั่งตรวจวัณโรคของแต่ละ visit (สั่งจากหน้าตรวจ/บัตร) — สั่งแล้วถือว่าทำแล้ว ไม่ต้องเตือนซ้ำ ----
        if ($visits) {
            $afbMap = \App\Models\TbScreening::$afbResultOption;
            foreach (DB::table('tb_screenings')->whereIn('visit_id', array_keys($visits))
                         ->select('visit_id', 'lab_methods', 'lab_afb_result', 'lab_molecular_result', 'lab_culture_result', 'cxr_result', 'cxr_results', 'afb_status')->get() as $s) {
                $d = $visits[$s->visit_id]['date'];
                $m = (array) (json_decode((string) $s->lab_methods, true) ?: []);
                if (in_array('afb', $m, true)) {
                    $res = $s->lab_afb_result;
                    $pos = $res ? ($res === 'N' ? false : true) : ($s->afb_status ? (int) $s->afb_status === 1 : null);
                    $src['sputum_afb'][] = $ev($d, $d, $res ? ($afbMap[$res] ?? $res) : 'สั่งแล้ว รอผล', $pos, $pos === null, 'ใบสั่งตรวจ visit #' . $s->visit_id);
                }
                if (in_array('molecular', $m, true)) {
                    $r = trim((string) $s->lab_molecular_result);
                    $src['sputum_molecular'][] = $ev($d, $d, $r ?: 'สั่งแล้ว รอผล', TbMonitorEngine::molecularPositive($r), $r === '', 'ใบสั่งตรวจ visit #' . $s->visit_id);
                }
                if (in_array('culture_dst', $m, true)) {
                    $r = trim((string) $s->lab_culture_result);
                    $src['sputum_culture'][] = $ev($d, $d, $r ?: 'สั่งแล้ว รอผล', TbMonitorEngine::culturePositive($r), $r === '', 'ใบสั่งตรวจ visit #' . $s->visit_id);
                }
                if (trim((string) $s->cxr_result) !== '' || ! empty(json_decode((string) $s->cxr_results, true))) {
                    $src['xray'][] = $ev($d, $d, trim((string) $s->cxr_result) ?: 'มีผลในใบตรวจ', null, false, 'ใบตรวจ visit #' . $s->visit_id);
                }
            }
        }

        // ---- X-ray ของบัตร ----
        foreach ($reg->xrays as $x) {
            $d = self::toAd($x->xray_date);
            if ($d) { $src['xray'][] = $ev($d, $d, trim((string) $x->result) ?: 'ถ่ายแล้ว รออ่านผล', null, trim((string) $x->result) === '', 'X-ray ในบัตร'); }
        }

        // ---- DST ----
        foreach ($reg->susceptibilities as $s) {
            $d = self::toAd($s->sent_date) ?? self::toAd($s->result_date);
            if (! $d) { continue; }
            $res = collect((array) $s->results)->map(fn ($v, $k) => $k . ':' . $v)->implode(' ');
            $pending = ! $s->result_date && ! $res;
            $src['dst'][] = $ev($d, $d, trim(\App\Models\TbDrugSusceptibility::methodName($s->method) . ' ' . $res) ?: 'ส่งแล้ว', null, $pending, 'DST ในบัตร');
        }

        // ---- visit คลินิก TB = ได้ประเมินอาการ · น้ำหนัก ----
        foreach ($visits as $id => $v) {
            if ($v['clinic'] === 2) { $src['tb_visit'][] = $ev($v['date'], $v['date'], 'มาตรวจ visit #' . $id, null, false, 'visit'); }
            if ((float) $v['weight'] > 0) { $src['weight'][] = $ev($v['date'], $v['date'], rtrim(rtrim((string) $v['weight'], '0'), '.') . ' กก.', null, false, 'หน้าตรวจ'); }
            if ((int) ($v['qtcf'] ?? 0) > 0) { $src['visit_qtcf'][] = $ev($v['date'], $v['date'], 'QTcF ' . (int) $v['qtcf'] . ' ms', null, false, 'หน้าตรวจ'); }
        }
        foreach ($reg->monthlyDoses as $r) {
            if (! $r->weight) { continue; }
            $date = self::toAd($r->dispensed_date);
            $label = (string) ($r->label !== null && $r->label !== '' ? $r->label : $r->month_no);
            $src['weight'][] = $ev($date, self::labelDate($label, $startAd) ?? $date, rtrim(rtrim((string) $r->weight, '0'), '.') . ' กก.', null, false, 'ตารางขนาดยา ' . $label);
        }

        // ---- HIV ที่กรอกในบัตร ----
        if (in_array((int) $reg->hiv_result, [1, 2], true)) {
            $d = self::toAd($reg->blood_test_date) ?? self::toAd($reg->counsel_date);
            // ผล HIV ถูกปิดไว้ในหน้าบัตร — ตารางติดตามบอกแค่ว่ามีผลแล้ว ไม่บอกค่า และไม่ติดป้าย "บวก" (2569-10-06)
            $src['reg_hiv'][] = $ev($d, $d ?? $startAd, 'บันทึกผลในบัตรแล้ว (ดูผลที่ช่องผลเลือด HIV)', null, false, 'บัตร TB');
        }

        // ---- Lab ในคลินิก / Lab นอก ของ visit ในช่วงคอร์ส ----
        $methods = []; $codes = []; $names = [];
        foreach ($def['checks'] as $c) {
            foreach ($c['sources'] ?? [] as $s) {
                if (str_starts_with($s, 'inhouse:')) { $methods[(int) substr($s, 8)] = true; }
                if (str_starts_with($s, 'ext:')) { $codes[substr($s, 4)] = true; }
                if (str_starts_with($s, 'extname:')) { $names[substr($s, 8)] = true; }
            }
        }
        if ($visits && $methods) {
            foreach (DB::table('lab_subs')->join('labs', 'labs.id', '=', 'lab_subs.lab_id')
                         ->whereIn('labs.visit_id', array_keys($visits))->whereIn('lab_subs.method', array_keys($methods))
                         ->select('labs.visit_id', 'lab_subs.method', 'lab_subs.result')->get() as $l) {
                $d = $visits[$l->visit_id]['date'];
                // ไม่โชว์ตัวผลในตาราง — ผลแล็บในคลินิกต้องผ่านการอนุมัติก่อน (Lab::isResultVisible) ดูผลที่ประวัติ Lab
                $has = trim((string) $l->result) !== '';
                // ตรวจข้างเตียง (DTX/EKG) พยาบาลลงเอง ไม่มีขั้นอนุมัติ — โชว์ตัวเลขได้เลย
                $poc = \App\Models\LabSub::$pocMethods[(int) $l->method] ?? null;
                $txt = $has ? ($poc ? ((int) $l->method === 16 ? 'QTcF ' : '') . trim($l->result) . ' ' . $poc[0] : 'มีผลแล้ว') : 'สั่งแล้ว รอผล';
                $src['inhouse:' . $l->method][] = $ev($d, $d, $txt, null, ! $has, 'Lab ในคลินิก');
            }
        }
        if ($visits && ($codes || $names)) {
            foreach (DB::table('visit_external_labs')->whereIn('visit_id', array_keys($visits))
                         ->select('visit_id', 'code', 'name', 'result', 'sent_date')->get() as $x) {
                $d = self::toAd($x->sent_date) ?? $visits[$x->visit_id]['date'];
                $has = trim((string) $x->result) !== '';
                $e = $ev($d, $d, $has ? \Illuminate\Support\Str::limit(trim($x->result), 40) : 'ส่งแล้ว รอผล', null, ! $has, 'Lab นอก: ' . $x->name);
                if (isset($codes[$x->code])) { $src['ext:' . $x->code][] = $e; }
                foreach (array_keys($names) as $rx) {
                    if (@preg_match('/' . $rx . '/i', (string) $x->name)) { $src['extname:' . $rx][] = $e; }
                }
            }
        }

        // ---- ตรวจสายตา ----
        // ประเมินซึมเศร้า 2Q/9Q (แถว "ประเมินสุขภาพจิต" ของตาราง BPaL) — 2569-10-22
        if (TbDepressionScreen::ready()) {
            foreach (TbDepressionScreen::where('tb_registration_id', $reg->id)->get() as $t) {
                $d = self::toAd($t->screen_date);
                if ($d) { $src['depression'][] = $ev($d, $d, trim('2Q ' . ($t->q2_positive ? 'บวก' : 'ลบ') . ($t->q9_score !== null ? ' · 9Q ' . $t->q9_score : '')), null, false, 'ประเมินซึมเศร้า'); }
            }
        }

        if (TbVisionTest::ready()) {
            foreach (TbVisionTest::where('tb_registration_id', $reg->id)->get() as $t) {
                $d = self::toAd($t->test_date);
                if ($d) { $src['vision'][] = $ev($d, $d, 'VA R ' . ($t->va_right ?: '-') . ' / L ' . ($t->va_left ?: '-') . ' · สี ' . $t->colorText(), null, false, 'ตรวจสายตา'); }
            }
        }

        // วันที่ในอนาคต = พิมพ์ผิด (ข้อมูลจริงมีปี 2570-2572 หลายแถว) — ผลตรวจมาจากอนาคตไม่ได้
        // แถวที่จับคู่ด้วยป้ายเดือนยังใช้ได้ แค่ไม่โชว์วันที่ผิด ๆ · แถวที่อาศัยวันที่อย่างเดียว ทิ้ง
        $tomorrow = (new \DateTimeImmutable($todayAd))->modify('+1 day')->format('Y-m-d');
        foreach ($src as $k => $list) {
            $src[$k] = array_values(array_filter(array_map(function ($e) use ($tomorrow) {
                if ($e['date'] && $e['date'] > $tomorrow) {
                    if ($e['match'] && $e['match'] !== $e['date'] && $e['match'] <= $tomorrow) { $e['date'] = null; $e['badDate'] = true; return $e; }
                    return null;
                }
                if ($e['match'] && $e['match'] > $tomorrow && ! $e['date']) { return null; }
                return $e;
            }, $list)));
        }

        // แจกให้แต่ละแถวตาม sources
        $out = [];
        foreach ($def['checks'] as $c) {
            $out[$c['key']] = [];
            foreach ($c['sources'] ?? [] as $s) { foreach ($src[$s] ?? [] as $e) { $out[$c['key']][] = $e; } }
        }
        $out['__dst'] = $src['dst'] ?? [];
        $out['__molecular'] = $src['sputum_molecular'] ?? [];
        return $out;
    }

    private static function manual(TbRegistration $reg): array
    {
        if (! TbMonitorCheck::ready()) { return []; }
        $out = [];
        foreach (TbMonitorCheck::where('tb_registration_id', $reg->id)->get() as $m) {
            $out[$m->check_key][$m->point_key] = ['date' => self::toAd($m->done_date), 'note' => $m->note, 'id' => $m->id];
        }
        return $out;
    }

    // ------------------------------------------------------------------
    //  ข้อความเตือน "เดือนนี้ต้องตรวจ"
    // ------------------------------------------------------------------

    /**
     * รายการที่ต้องเตือน: ถึงกำหนดตอนนี้ + เลยกำหนดไม่เกิน 45 วัน (ค้างเก่ากว่านั้นนับรวมเป็นตัวเลข ดูในตาราง)
     * สั่งย้อนหลังนานเกินไปไม่มีประโยชน์ทางคลินิก และจะทำให้ข้อความยาวจนพยาบาลไม่อ่าน
     */
    public static function reminderItems(?array $grid): array
    {
        $out = ['due' => [], 'late' => [], 'oldLate' => 0, 'presetTargets' => [], 'needsXray' => false, 'pointLabel' => null];
        if (! $grid) { return $out; }
        $out['pointLabel'] = $grid['current'] ? $grid['points'][$grid['current']]['label'] : null;
        $cut = (new \DateTimeImmutable($grid['today']))->modify('-45 days')->format('Y-m-d');
        foreach ($grid['due'] as $i) { $out['due'][] = $i; }
        foreach ($grid['overdue'] as $i) {
            if ($i['expected'] && $i['expected'] >= $cut) { $out['late'][] = $i; } else { $out['oldLate']++; }
        }
        foreach (array_merge($out['due'], $out['late']) as $i) {
            foreach ($i['preset'] as $t) { $out['presetTargets'][$t] = true; }
            if ($i['check'] === 'cxr') { $out['needsXray'] = true; }
        }
        $out['presetTargets'] = array_keys($out['presetTargets']);
        return $out;
    }

    /** เป้าหมายในแคตตาล็อก (tb:afb / ext:A010 ...) -> id ของ lab_catalog_items ที่คลินิก TB สั่งได้ */
    public static function presetIds(array $targets): array
    {
        if (! $targets || ! LabCatalogItem::availableFor(2)) { return []; }
        $real = [];
        $codes = [];
        foreach ($targets as $t) {
            if (str_starts_with($t, 'ext:')) { $codes[] = substr($t, 4); } else { $real[] = $t; }
        }
        if ($codes) {
            foreach (ExternalLabTest::whereIn('code', $codes)->pluck('id', 'code') as $code => $id) { $real[] = 'external:' . $id; }
        }
        $ids = LabCatalogItem::query()->forClinic(2)->whereIn('target', $real)->pluck('id', 'target');
        // คงลำดับตามที่ขอ (ตามแถวในตาราง)
        $out = [];
        foreach ($real as $t) { if (isset($ids[$t])) { $out[] = (int) $ids[$t]; } }
        return array_values(array_unique($out));
    }

    // ------------------------------------------------------------------
    //  ตรวจสายตา / ซึมเศร้า — คำแนะนำ ไม่บังคับ
    // ------------------------------------------------------------------

    /** ได้ยาที่มีพิษต่อตา (Ethambutol / Linezolid) อยู่ตอนนี้ไหม — คืนชื่อยา หรือ [] */
    public static function eyeToxicDrugsNow(TbRegistration $reg, string $todayAd): array
    {
        try { $keys = $reg->doseDrugKeys(); } catch (\Throwable $e) { return []; }
        $out = [];
        if (in_array('Lzd', $keys, true)) { $out[] = 'Linezolid'; }
        if (array_intersect(['E', '4FDC'], $keys)) {
            // สูตรครบคอร์สแบบเม็ดรวม (4FDC + 2FDC) มี E แค่ระยะเข้มข้น — ดูระยะจากการสั่งยา ไม่รู้ = 2 เดือนแรก
            $twoPhase = in_array('2FDC', $keys, true) || (in_array('E', $keys, true) && preg_match('/\/\s*\d*\s*HR/i', (string) $reg->currentRegimenText()));
            $onE = true;
            if ($twoPhase) {
                $phase = null;
                try { $phase = $reg->orderPhaseOn((string) self::toBe($todayAd)); } catch (\Throwable $e) {}
                $onE = $phase !== null ? $phase === 1 : $reg->monthsOnTreatment() < 2;
            }
            if ($onE) { $out[] = 'Ethambutol'; }
        }
        return $out;
    }

    private static function visionAdvice(TbRegistration $reg, ?string $startAd, string $todayAd): array
    {
        $tests = TbVisionTest::ready()
            ? TbVisionTest::with('user')->where('tb_registration_id', $reg->id)->orderByDesc('test_date')->orderByDesc('id')->get()
            : collect();
        $drugs = $reg->isActive() ? self::eyeToxicDrugsNow($reg, $todayAd) : [];
        $last = $tests->first();
        $lastAd = $last ? self::toAd($last->test_date) : null;

        $msg = null;
        if ($reg->isActive() && ! $last) {
            $msg = 'ยังไม่เคยตรวจสายตา — คลินิกตรวจทุกรายก่อนเริ่มยา';
        } elseif ($drugs && $lastAd && $lastAd < (new \DateTimeImmutable($todayAd))->modify('-30 days')->format('Y-m-d')) {
            $msg = 'ได้ ' . implode(' / ', $drugs) . ' อยู่ — ควรตรวจสายตาทุกเดือน (ครั้งล่าสุด ' . self::shortTh($lastAd) . ')';
        }
        // บัตร LTBI ไม่ใช้ตรวจสายตา/ซึมเศร้า (คลินิกขอ 2569-10-27) — ไม่เตือน
        if ($reg->isLtbi()) { $msg = null; }
        return ['tests' => $tests, 'drugs' => $drugs, 'advice' => $msg];
    }

    /**
     * ควรประเมินซึมเศร้าไหม (กติกาคลินิก): ตอนเริ่มรักษา · สัปดาห์ที่ 2-4 · ทุกครั้งที่มารับยา (ทุก 1-2 เดือน) · และเมื่อมีสัญญาณเตือน
     *   ก่อนวันที่ 14     ต้องมีผลตั้งแต่ 30 วันก่อนเริ่มยา
     *   วันที่ 14-35       ต้องมีผลตั้งแต่วันที่ 14
     *   หลังจากนั้น        ต้องมีผลภายใน 30 วันล่าสุด (ถือว่าทุกรอบรับยา)
     */
    public static function depressionWindow(?string $startAd, string $todayAd): array
    {
        if (! $startAd) { return ['from' => (new \DateTimeImmutable($todayAd))->modify('-30 days')->format('Y-m-d'), 'label' => 'แรกรับ']; }
        $s = new \DateTimeImmutable($startAd);
        $day = TbMonitorEngine::days($s, new \DateTimeImmutable($todayAd));
        if ($day < 14)  { return ['from' => $s->modify('-30 days')->format('Y-m-d'), 'label' => 'ตอนเริ่มรักษา']; }
        if ($day <= 35) { return ['from' => $s->modify('+14 days')->format('Y-m-d'), 'label' => 'สัปดาห์ที่ 2-4 ของการรักษา']; }
        return ['from' => (new \DateTimeImmutable($todayAd))->modify('-30 days')->format('Y-m-d'), 'label' => 'รอบมารับยา (ทุก 1-2 เดือน)'];
    }

    private static function depressionAdvice(TbRegistration $reg, ?string $startAd, string $todayAd): array
    {
        $rows = TbDepressionScreen::ready()
            ? TbDepressionScreen::with('user')->where('tb_registration_id', $reg->id)->orderByDesc('screen_date')->orderByDesc('id')->get()
            : collect();
        $w = self::depressionWindow($startAd, $todayAd);
        $hit = $rows->first(fn ($r) => ($d = self::toAd($r->screen_date)) && $d >= $w['from'] && $d <= $todayAd);
        $msg = null;
        if ($reg->isActive() && ! $hit) {
            $msg = 'ควรประเมินซึมเศร้า (2Q) — ' . $w['label'];
        }
        if ($reg->isLtbi()) { $msg = null; }   // บัตร LTBI ไม่ใช้ (2569-10-27)
        return ['rows' => $rows, 'advice' => $msg, 'window' => $w];
    }
}
