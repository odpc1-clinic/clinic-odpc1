<?php

namespace App\Support;

/**
 * ตารางติดตามการรักษาวัณโรค ("ตารางติดตามการรักษา" ในบัตร TB 01) — นิยามล้วน ไม่มี logic
 *
 * แยกไฟล์นี้ออกมาให้แก้ได้ง่ายเมื่อแนวทางเปลี่ยน (เพิ่ม/ตัดช่องตรวจ เลื่อนเดือน) โดยไม่ต้องแตะตัวคำนวณ
 * (App\Services\TbMonitor\TbMonitorEngine) และไม่ต้องแตะที่ดึงข้อมูล (TbMonitor)
 *
 * แต่ละช่องในตาราง (แถว = รายการตรวจ, คอลัมน์ = จุดเวลา) ใส่ได้ว่า
 *   'req'              ต้องตรวจ
 *   'consider'         พิจารณาตรวจ (ไม่ขึ้นเตือนค้าง)
 *   'advice:liver'     ตรวจถ้ามีปัจจัยเสี่ยง (liver / kidney / eye) — มีปัจจัยในบัตร = ต้องตรวจ, ไม่มี = แค่คำแนะนำ
 *   ['cond' => [...], 'text' => '...']   ตรวจเมื่อเงื่อนไขเป็นจริง
 *       ['if' => 'pos', 'check' => 'afb', 'at' => 'm2']   ผลของรายการนั้น ณ จุดนั้นเป็นบวก ('at' => 'self' = จุดเดียวกัน)
 *       ['if' => 'no_baseline_dst'] · ['if' => 'female'] · ['if' => 'hiv_pos']
 *       ['if' => 'manual']   ระบบตัดสินเองไม่ได้ (ขยายระยะเข้มข้น / เมื่อมีข้อบ่งชี้) — ติ๊กมือเท่านั้น
 *       ['all' => [cond, cond]]   ทุกข้อต้องจริง
 *
 * sources = ที่ระบบไปหาว่า "ตรวจแล้ว" (ดู TbMonitor::collectEvents)
 *   sputum_afb · sputum_molecular · sputum_culture · dst · xray · weight · tb_visit · vision
 *   inhouse:<method>   lab_subs.method (6 = Anti-HIV)
 *   ext:<code>         visit_external_labs.code (รหัส N Health เช่น A010 = CBC)
 *   extname:<regex>    ชื่อรายการแล็บนอก (ใช้กับแล็บอื่นที่ไม่มีรหัส เช่น CD4)
 *   reg_hiv            ผล HIV ที่กรอกไว้ในบัตร (ช่องผลเลือด)
 *   visit_qtcf         QTcF ที่กรอกในสัญญาณชีพหน้าตรวจ (EKG ที่คลินิกทำเอง 2569-10-22)
 *   ไม่มี sources = ติ๊กมือในตาราง (tb_monitor_checks) อย่างเดียว
 *
 * preset = รายการในแคตตาล็อก "สั่งแล็บ" ที่ติ๊กให้ล่วงหน้าตอนกดปุ่มสั่งตรวจที่ค้าง
 *   'tb:afb' / 'in_house:6' = lab_catalog_items.target ตรง ๆ · 'ext:A010' = แล็บนอกรหัสนั้น (หา id ตอนใช้งาน
 *   เพราะ target ของแล็บนอกผูกกับ id ของ external_lab_tests ซึ่งอาจต่างกันในแต่ละเครื่อง)
 */
class TbMonitorDefinitions
{
    /**
     * ช่วงที่ยอมให้นับว่า "ตรวจตรงจุดนั้น" (วัน ก่อน / หลัง วันที่ควรตรวจ)
     * base: ผลก่อนเริ่มยามักตรวจตอนวินิจฉัย ซึ่งอาจก่อนวันเริ่มยาหลายสัปดาห์ จึงย้อนได้ 60 วัน
     * week: ±7 วัน (จุดรายสัปดาห์ห่างกัน 7 วัน ช่วงทับกัน -> ผลตรวจหนึ่งครั้งไปลงจุดที่ใกล้ที่สุดจุดเดียว)
     * month: ±14 วัน · post: หลังจบการรักษา นัดห่าง ยอม ±30 วัน
     */
    public const WINDOWS = [
        'base'  => [60, 7],
        'week'  => [7, 7],
        'month' => [14, 14],
        'post'  => [30, 30],
    ];

    /** สูตรที่ต้องดูการมองเห็นทุกเดือน (ยาที่มีพิษต่อเส้นประสาทตา) */
    public const EYE_TOXIC_DRUGS = ['E', 'Lzd', '4FDC'];

    public static function schedules(): array
    {
        return [
            'ds'   => self::ds(),
            'dr'   => self::drBdq(),
            'bpal' => self::bpal(),
            'tpt'  => self::tpt(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  BPaL / BPaLM (6 เดือน) — กองวัณโรค แบบฟอร์ม BPaL(M)-07 (คลินิกส่งมา 2569-10-22)          */
    /*  ฐาน · สัปดาห์ที่ 2 · ทุกเดือน · สิ้นสุดการรักษา (ด.6) · ติดตามหลังจบ 6 และ 12 เดือน         */
    /* ------------------------------------------------------------------ */
    private static function bpal(): array
    {
        $points = [['d0', 'ด.0', 'base', 0], ['w2', 'สป.2', 'week', 2]];
        for ($m = 1; $m <= 5; $m++) { $points[] = ['m' . $m, 'ด.' . $m, 'month', $m]; }
        $points[] = ['m6', 'ด.6 (จบ)', 'month', 6];
        $points[] = ['p6', 'หลังจบ 6 ด.', 'post', 6];
        $points[] = ['p12', 'หลังจบ 12 ด.', 'post', 12];

        $only  = fn (array $ks) => array_fill_keys($ks, 'req');
        $month = ['m1', 'm2', 'm3', 'm4', 'm5', 'm6'];
        $all   = $only(array_column($points, 0));                                   // ทุกคอลัมน์
        $onTx  = $only(array_merge(['d0', 'w2'], $month));                          // ฐาน + สป.2 + ทุกเดือน + จบ (ไม่รวมหลังจบ)
        $sput  = $only(array_merge(['d0'], $month, ['p6', 'p12']));                 // ฐาน + ทุกเดือน + จบ + หลังจบ (ไม่มี สป.2)
        $dst   = ['d0' => 'req'];
        foreach (array_merge($month, ['p6', 'p12']) as $k) {
            $dst[$k] = ['cond' => ['if' => 'pos', 'check' => 'culture', 'at' => 'self'], 'text' => 'ถ้า Culture ขึ้นเชื้อ'];
        }

        return [
            'key'    => 'bpal',
            'title'  => 'วัณโรคดื้อยา สูตร BPaL / BPaLM (6 เดือน) — แบบฟอร์ม BPaL(M)-07',
            'treat_months' => 6,
            'points' => $points,
            'checks' => [
                ['key' => 'clinical',   'label' => 'อาการทางคลินิก', 'sources' => ['tb_visit'], 'remind' => false, 'points' => $all],
                ['key' => 'mental',     'label' => 'ประเมินสุขภาพจิต', 'sub' => 'แบบคัดกรองซึมเศร้า 2Q/9Q', 'sources' => ['depression'], 'points' => $all],
                ['key' => 'perf',       'label' => 'ประสิทธิภาพของผู้ป่วย', 'sub' => 'Performance status', 'sources' => [], 'points' => $only(['d0'])],
                ['key' => 'weight',     'label' => 'น้ำหนัก / BMI', 'sources' => ['weight'], 'remind' => false, 'points' => $all],
                ['key' => 'neuro',      'label' => 'คัดกรองปลายประสาทส่วนปลาย', 'sub' => 'Linezolid', 'sources' => [], 'points' => $onTx],
                ['key' => 'vision',     'label' => 'ตรวจสายตา VA + ตาบอดสี', 'sources' => ['vision'], 'points' => $onTx],
                ['key' => 'adr',        'label' => 'ติดตามอาการไม่พึงประสงค์ (ADR)', 'sources' => ['tb_visit'], 'remind' => false, 'points' => $all],
                ['key' => 'outcome',    'label' => 'ประเมินผลการรักษา', 'sources' => [], 'points' => $only(['m6', 'p6', 'p12'])],
                ['key' => 'molecular',  'label' => 'Molecular (Xpert/LPA)', 'sources' => ['sputum_molecular'], 'preset' => ['tb:mol:xpert'], 'points' => $only(['d0'])],
                ['key' => 'afb',        'label' => 'Sputum AFB', 'sources' => ['sputum_afb'], 'preset' => ['tb:afb'], 'points' => $sput],
                ['key' => 'culture',    'label' => 'Sputum Culture', 'sources' => ['sputum_culture'], 'preset' => ['tb:culture_dst'], 'points' => $sput],
                ['key' => 'dst_sl',     'label' => 'DST', 'sub' => 'แรกรับ + เมื่อเพาะเชื้อขึ้น', 'sources' => ['dst'], 'preset' => ['tb:culture_dst'], 'points' => $dst],
                ['key' => 'cxr',        'label' => 'CXR', 'sources' => ['xray'], 'points' => $only(['d0', 'm6', 'p6', 'p12'])],
                ['key' => 'qtcf',       'label' => 'EKG (QTcF)', 'sources' => ['visit_qtcf', 'inhouse:16'], 'preset' => ['in_house:16'], 'points' => $onTx],
                ['key' => 'cbc',        'label' => 'CBC', 'sub' => 'Linezolid กดไขกระดูก', 'sources' => ['ext:A010'], 'preset' => ['ext:A010'], 'points' => $onTx],
                ['key' => 'lft',        'label' => 'LFTs', 'sub' => 'TB DB AST ALT ALP', 'sources' => ['ext:C071', 'ext:C600', 'ext:C610', 'ext:C560', 'ext:C580'],
                 'preset' => ['ext:C071'], 'points' => $onTx],
                ['key' => 'lyte',       'label' => 'Electrolytes', 'sub' => 'K / Mg / Ca', 'sources' => ['ext:C004', 'ext:C510', 'ext:C340', 'ext:C100'],
                 'preset' => ['ext:C004', 'ext:C340', 'ext:C100'], 'points' => $only(['d0'])],
                ['key' => 'cr',         'label' => 'การทำงานของไต (Cr / eGFR)', 'sources' => ['ext:C520'], 'preset' => ['ext:C520'], 'points' => $only(['d0'])],
                ['key' => 'fbs',        'label' => 'น้ำตาลในเลือด (FBS / DTX)', 'sources' => ['ext:C040', 'inhouse:15'], 'preset' => ['ext:C040'], 'points' => $only(['d0'])],
                ['key' => 'hiv',        'label' => 'HIV / ไวรัสตับอักเสบ', 'sources' => ['inhouse:6', 'reg_hiv', 'inhouse:11', 'inhouse:12'], 'preset' => ['in_house:6'], 'points' => $only(['d0'])],
                ['key' => 'preg',       'label' => 'Pregnancy test', 'sources' => ['ext:D120'], 'preset' => ['ext:D120'],
                 'points' => ['d0' => ['cond' => ['if' => 'female'], 'text' => 'ผู้หญิง']]],
                ['key' => 'other_spec', 'label' => 'สิ่งส่งตรวจอื่น (smear/culture/DST)', 'sub' => 'นอกปอด · เมื่อไม่ตอบสนองการรักษา', 'sources' => [],
                 'points' => ['d0' => 'consider']],
            ],
            'notes' => [
                'ตามแบบฟอร์ม BPaL(M)-07 กองวัณโรค · ด.6 = สิ้นสุดการรักษา (BPaL/BPaLM 26 สัปดาห์) · หลังจบนับจากวันเริ่มยา + 6 เดือน',
                'แถวที่ไม่มีที่เก็บผลในระบบ (performance status, ปลายประสาท, ประเมินผลการรักษา) — กดช่องแล้วติ๊กเอง',
            ],
        ];
    }

    /**
     * รายการตรวจทั้งหมดที่เลือกได้ตอน "กำหนดตารางเอง" (สูตร Individualized) — รวมจากทุกตารางมาตรฐาน คีย์ซ้ำเอาตัวแรก
     * ใช้นิยามที่มีอยู่ (ที่มาของผล / ปุ่มสั่งตรวจ) ต่างกันแค่ช่องไหนต้องตรวจ
     */
    public static function checkCatalogue(): array
    {
        $out = [];
        foreach (['bpal', 'dr', 'ds'] as $k) {
            foreach (self::get($k)['checks'] as $c) {
                if (! isset($out[$c['key']])) { unset($c['points']); $out[$c['key']] = $c; }
            }
        }
        return $out;
    }

    /** คอลัมน์ที่เลือกได้ในตารางกำหนดเอง: ด.0 · สป.1-4 · ด.1-24 · หลังจบ 6/12/18/24 */
    public static function pointCatalogue(int $months): array
    {
        $p = [['d0', 'ด.0', 'base', 0]];
        for ($w = 1; $w <= 4; $w++) { $p[] = ['w' . $w, 'สป.' . $w, 'week', $w]; }
        for ($m = 1; $m <= $months; $m++) { $p[] = ['m' . $m, 'ด.' . $m . ($m === $months ? ' (จบ)' : ''), 'month', $m]; }
        foreach ([6, 12, 18, 24] as $n) { $p[] = ['p' . $n, 'หลังจบ ' . $n . ' ด.', 'post', $n]; }
        return $p;
    }

    /**
     * ตารางที่แพทย์กำหนดเองของบัตรนี้ (tb_registrations.monitor_plan) — สูตร Individualized
     * plan = ['months' => n, 'cells' => [checkKey => [pointKey, ...]]] · คอลัมน์ที่ไม่มีแถวไหนติ๊กเลย = ไม่แสดง
     */
    public static function custom(array $plan): ?array
    {
        $months = max(1, min(24, (int) ($plan['months'] ?? 6)));
        $cat = self::checkCatalogue();
        $cells = (array) ($plan['cells'] ?? []);
        $points = self::pointCatalogue($months);
        $used = [];
        $checks = [];
        foreach ($cells as $ck => $pks) {
            if (! isset($cat[$ck]) || ! is_array($pks) || ! $pks) { continue; }
            $c = $cat[$ck];
            $c['points'] = array_fill_keys(array_map('strval', $pks), 'req');
            $checks[] = $c;
            foreach ($pks as $pk) { $used[(string) $pk] = true; }
        }
        if (! $checks) { return null; }
        // คอลัมน์ฐาน (ด.0) โชว์เสมอ · ที่เหลือโชว์เฉพาะที่มีการตรวจ
        $points = array_values(array_filter($points, fn ($p) => $p[0] === 'd0' || isset($used[$p[0]])));
        // เรียงแถวตามลำดับในแคตตาล็อก (อ่านง่าย ไม่ขึ้นกับลำดับที่ติ๊ก)
        $order = array_flip(array_keys($cat));
        usort($checks, fn ($a, $b) => $order[$a['key']] <=> $order[$b['key']]);

        return [
            'key'    => 'custom',
            'title'  => 'ตารางที่แพทย์กำหนดเอง (สูตร Individualized · ' . $months . ' เดือน)',
            'treat_months' => $months,
            'points' => $points,
            'checks' => $checks,
            'notes'  => ['กำหนดเองสำหรับบัตรนี้ — แก้ได้ที่ปุ่ม "กำหนดตารางเอง" · หลังจบนับจากวันเริ่มยา + ' . $months . ' เดือน'],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::schedules()[$key] ?? null;
    }

    /* ------------------------------------------------------------------ */
    /*  วัณโรคไวต่อยา — แนวทางควบคุมวัณโรคประเทศไทย 2564 ตาราง 5.3          */
    /* ------------------------------------------------------------------ */
    private static function ds(): array
    {
        $points = [['base', 'ก่อนเริ่ม', 'base', 0]];
        for ($m = 1; $m <= 6; $m++) { $points[] = ['m' . $m, 'สิ้น ด.' . $m, 'month', $m]; }
        $all = array_fill_keys(array_column($points, 0), 'req');

        $afbPos = fn ($at) => ['if' => 'pos', 'check' => 'afb', 'at' => $at];

        return [
            'key'    => 'ds',
            'title'  => 'วัณโรคไวต่อยา (DS-TB) — แนวทางฯ 2564 ตาราง 5.3',
            'treat_months' => 6,
            'points' => $points,
            'checks' => [
                ['key' => 'clinical', 'label' => 'อาการทางคลินิก', 'sources' => ['tb_visit'], 'remind' => false, 'points' => $all],
                ['key' => 'weight',   'label' => 'น้ำหนัก',        'sources' => ['weight'],   'remind' => false, 'points' => $all],
                ['key' => 'afb', 'label' => 'AFB smear', 'sources' => ['sputum_afb'], 'preset' => ['tb:afb'],
                 'points' => ['base' => 'req', 'm2' => 'req', 'm5' => 'req', 'm6' => 'req',
                              'm3' => ['cond' => $afbPos('m2'), 'text' => 'ถ้า AFB ด.2 บวก']]],
                ['key' => 'molecular', 'label' => 'Molecular (Xpert/LPA)', 'sources' => ['sputum_molecular'], 'preset' => ['tb:mol:xpert'],
                 'points' => ['base' => 'req',
                              'm2' => ['cond' => ['all' => [$afbPos('m2'), ['if' => 'no_baseline_dst']]], 'text' => 'ถ้า AFB ด.2 บวก และไม่มี DST แรกรับ'],
                              'm5' => ['cond' => $afbPos('m5'), 'text' => 'ถ้า AFB ด.5 บวก']]],
                ['key' => 'culture', 'label' => 'Culture / DST', 'sub' => 'phenotypic', 'sources' => ['sputum_culture', 'dst'], 'preset' => ['tb:culture_dst'],
                 'points' => ['base' => 'consider',
                              'm2' => ['cond' => $afbPos('m2'), 'text' => 'ถ้า AFB ด.2 บวก'],
                              'm5' => ['cond' => $afbPos('m5'), 'text' => 'ถ้า AFB ด.5 บวก']]],
                ['key' => 'cxr', 'label' => 'CXR', 'sources' => ['xray'], 'points' => ['base' => 'req', 'm2' => 'req', 'm6' => 'req']],
                ['key' => 'hiv', 'label' => 'HIV test', 'sources' => ['inhouse:6', 'reg_hiv'], 'preset' => ['in_house:6'], 'points' => ['base' => 'req']],
                ['key' => 'lft', 'label' => 'LFT', 'sub' => 'ถ้ามีปัจจัยเสี่ยงตับ*', 'sources' => ['ext:C071', 'ext:C600', 'ext:C610', 'ext:C560'],
                 'preset' => ['ext:C071'], 'points' => ['base' => 'advice:liver']],
                ['key' => 'bun_cr', 'label' => 'BUN / Cr', 'sub' => 'ถ้ามีปัจจัยเสี่ยงไต**', 'sources' => ['ext:C520', 'ext:C530'],
                 'preset' => ['ext:C530', 'ext:C520'], 'points' => ['base' => 'advice:kidney']],
                // แนวทางให้ตรวจเฉพาะผู้สูงอายุ/มีปัญหาตา แต่คลินิกตรวจสายตาทุกราย (คลินิกกำหนด 2569-10)
                ['key' => 'vision', 'label' => 'ตรวจสายตา (VA + ตาบอดสี)', 'sub' => 'คลินิกตรวจทุกราย', 'sources' => ['vision'], 'points' => ['base' => 'req']],
            ],
            'notes' => [
                '* ปัจจัยเสี่ยงตับ: อายุ > 60 ปี, ดื่มสุรา, โรคตับ/ตับอักเสบเรื้อรัง, HIV, ขาดสารอาหาร, ตั้งครรภ์',
                '** ปัจจัยเสี่ยงไต: โรคไต เบาหวาน ความดันโลหิตสูง ผู้สูงอายุ',
                'AFB ด.3 / Molecular / Culture ด.2, ด.5 ตรวจเมื่อ AFB เดือนนั้นยังบวก — ระบบดูผลจากตารางเสมหะให้เอง',
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  วัณโรคดื้อยา สูตรระยะสั้นชนิดรับประทาน (Bedaquiline) — ตามแบบฟอร์มของคลินิก */
    /* ------------------------------------------------------------------ */
    private static function drBdq(): array
    {
        $points = [
            ['d0', 'ด.0', 'base', 0],
            ['w1', 'สป.1', 'week', 1], ['w2', 'สป.2', 'week', 2], ['w3', 'สป.3', 'week', 3],
            // สป.4 = ครบ 1 เดือน — หัวตารางเขียน "ด.1" ให้ต่อกับ ด.2 ด.3 (คลินิกขอ 2569-10-22 อ่านแล้วงง) · คีย์/ช่วงวันยังเป็นรายสัปดาห์เหมือนเดิม
            ['w4', 'ด.1', 'week', 4],
            ['m2', 'ด.2', 'month', 2], ['m3', 'ด.3', 'month', 3], ['m4', 'ด.4*', 'month', 4],
            // ขยายระยะเข้มข้น 1-2 เดือน: ระบบรู้เองไม่ได้ — ช่องนี้ติ๊กมือ ไม่เอาผลตรวจอัตโนมัติมาลง
            // (ถ้าเอามาลง ผลเดือนที่ 5 จะถูกแย่งไปลงช่องขยาย แทนที่จะลง ด.5)
            ['ext1', 'ขยาย 1', 'month', 5, 'ext'], ['ext2', 'ขยาย 2', 'month', 6, 'ext'],
            ['m5', 'ด.5', 'month', 5], ['m6', 'ด.6', 'month', 6], ['m7', 'ด.7', 'month', 7], ['m8', 'ด.8', 'month', 8], ['m9', 'ด.9', 'month', 9],
            ['p6', 'หลัง 6 ด.', 'post', 6], ['p12', 'หลัง 12 ด.', 'post', 12], ['p18', 'หลัง 18 ด.', 'post', 18], ['p24', 'หลัง 24 ด.', 'post', 24],
        ];
        $keys = array_column($points, 0);
        $only = fn (array $ks) => array_fill_keys($ks, 'req');
        $ext  = ['cond' => ['if' => 'manual'], 'text' => 'ถ้าขยายระยะเข้มข้น'];
        $withExt = function (array $ks) use ($ext) {
            $out = array_fill_keys($ks, 'req');
            foreach (['ext1', 'ext2'] as $e) { if (isset($out[$e])) { $out[$e] = $ext; } }
            return $out;
        };

        $post = ['p6', 'p12', 'p18', 'p24'];
        $treat = ['d0', 'w4', 'm2', 'm3', 'm4', 'ext1', 'ext2', 'm5', 'm6', 'm7', 'm8', 'm9'];
        $wt   = array_values(array_diff($keys, ['w3']));                // ตามแบบฟอร์ม: ไม่มี สป.3
        $sput = array_merge($treat, $post);

        // DST ยาแนวสอง: แรกรับ + ทุกครั้งที่ culture ขึ้นเชื้อ (ระหว่าง/หลังการรักษา)
        $dst = ['d0' => 'req'];
        foreach ($sput as $k) {
            if ($k === 'd0') { continue; }
            $dst[$k] = ['cond' => ['if' => 'pos', 'check' => 'culture', 'at' => 'self'], 'text' => 'ถ้า Culture growth'];
        }

        return [
            'key'    => 'dr',
            'title'  => 'วัณโรคดื้อยา สูตรระยะสั้นชนิดรับประทาน (มี Bedaquiline)',
            'treat_months' => 9,
            'points' => $points,
            'checks' => [
                ['key' => 'consent',  'label' => 'Inform consent', 'sources' => [], 'points' => $only(['d0'])],
                ['key' => 'weight',   'label' => 'น้ำหนัก / ส่วนสูง', 'sources' => ['weight'], 'remind' => false, 'points' => $withExt($wt)],
                ['key' => 'clinical', 'label' => 'อาการทางคลินิก', 'sources' => ['tb_visit'], 'remind' => false, 'points' => $withExt($wt)],
                ['key' => 'afb',      'label' => 'Sputum AFB', 'sources' => ['sputum_afb'], 'preset' => ['tb:afb'], 'points' => $withExt($sput)],
                ['key' => 'culture',  'label' => 'Sputum C/S', 'sources' => ['sputum_culture'], 'preset' => ['tb:culture_dst'], 'points' => $withExt($sput)],
                ['key' => 'dst_sl',   'label' => 'DST ยาแนวสอง', 'sub' => 'phenotypic / genotypic', 'sources' => ['dst'], 'preset' => ['tb:culture_dst'], 'points' => $dst],
                ['key' => 'cxr',      'label' => 'CXR', 'sources' => ['xray'], 'points' => $only(['d0', 'm4', 'p6', 'p12', 'p18', 'p24'])],
                ['key' => 'cbc',      'label' => 'CBC', 'sub' => 'RBC Hb Hct WBC diff plt', 'sources' => ['ext:A010'], 'preset' => ['ext:A010'], 'points' => $only(['d0', 'w4', 'm4'])],
                ['key' => 'cr',       'label' => 'Serum Cr', 'sources' => ['ext:C520'], 'preset' => ['ext:C520'], 'points' => $only(['d0', 'w4', 'm4'])],
                // N Health รายงาน eGFR มากับ Creatinine — สั่ง Cr ตัวเดียวได้ทั้งสองแถว
                ['key' => 'egfr',     'label' => 'eGFR', 'sub' => 'มากับ Creatinine', 'sources' => ['ext:C520'], 'preset' => ['ext:C520'], 'points' => $only(['d0', 'w4', 'm4'])],
                ['key' => 'lyte',     'label' => 'Electrolytes', 'sub' => 'K / Mg / Ca · หลังจากนี้เมื่อมีข้อบ่งชี้',
                 'sources' => ['ext:C004', 'ext:C510', 'ext:C340', 'ext:C100'], 'preset' => ['ext:C004', 'ext:C340', 'ext:C100'], 'points' => $only(['d0'])],
                ['key' => 'fbs',      'label' => 'FBS / DTX', 'sources' => ['ext:C040', 'inhouse:15'], 'preset' => ['ext:C040'], 'points' => $only(['d0'])],
                ['key' => 'tsh',      'label' => 'TSH', 'sources' => ['ext:N010'], 'preset' => ['ext:N010'], 'points' => $only(['d0'])],
                ['key' => 'lft',      'label' => 'LFTs', 'sub' => 'TB DB AST ALT ALP', 'sources' => ['ext:C071', 'ext:C600', 'ext:C610', 'ext:C560', 'ext:C580'],
                 'preset' => ['ext:C071'], 'points' => $withExt(['d0', 'w4', 'm2', 'm3', 'm4', 'ext1', 'ext2'])],
                ['key' => 'qtcf',     'label' => 'EKG (QTcF)', 'sources' => ['visit_qtcf', 'inhouse:16'], 'preset' => ['in_house:16'], 'points' => $withExt(['d0', 'w2', 'w4', 'm2', 'm3', 'm4', 'ext1', 'ext2', 'm5', 'm6'])],
                ['key' => 'preg',     'label' => 'Pregnancy test', 'sources' => ['ext:D120'], 'preset' => ['ext:D120'],
                 'points' => ['d0' => ['cond' => ['if' => 'female'], 'text' => 'ผู้หญิง']]],
                ['key' => 'hiv',      'label' => 'HIV test', 'sources' => ['inhouse:6', 'reg_hiv'], 'preset' => ['in_house:6'], 'points' => $only(['d0'])],
                ['key' => 'cd4',      'label' => 'CD4', 'sub' => 'ถ้า HIV บวก', 'sources' => ['extname:CD4'],
                 'points' => ['d0' => ['cond' => ['if' => 'hiv_pos'], 'text' => 'ถ้า HIV บวก']]],
                ['key' => 'vision',   'label' => 'ตรวจสายตา VA + ตาบอดสี', 'sources' => ['vision'], 'points' => $only(['d0'])],
            ],
            'notes' => [
                '* ถ้าขยายระยะเข้มข้น 1-2 เดือน ให้ตรวจเหมือนเดือนที่ 4 ในแต่ละเดือนที่ขยาย (ช่อง "ขยาย 1/2" ติ๊กเองเมื่อขยาย)',
                'CBC = RBC, Hb, Hct, WBC, differential, platelet · LFT = total/direct bilirubin, AST, ALT, ALP',
                'หลังจบการรักษา 6/12/18/24 เดือน นับจากวันเริ่มยา + 9 เดือน (ยังไม่รวมเดือนที่ขยาย)',
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  ยาป้องกัน (TPT / LTBI) — กองวัณโรค "การบริหารจัดการค้นหาและรักษาการติดเชื้อวัณโรคระยะแฝง   */
    /*  สำหรับผู้สัมผัสวัณโรค" (คลินิกส่งมา 2569-10-22) · แทนฉบับร่างเดิมที่รอแพทย์ยืนยัน             */
    /*   ก่อนเริ่ม: TST/IGRA ยืนยัน · แยกวัณโรค active (อาการ + CXR) · ห้ามตั้งครรภ์/ให้นม · AST/ALT     */
    /*   ระหว่างรักษา: อาการไม่พึงประสงค์ทุกเดือน (ตับ ผื่น flu-like ชาปลายมือเท้า) · ประเมินผลเมื่อจบ  */
    /*   หลังจบ: ซักอาการ + CXR ทุก 6 เดือน อย่างน้อย 2 ปี                                           */
    /* ------------------------------------------------------------------ */
    private static function tpt(int $months = 3): array
    {
        $points = [['base', 'ก่อนเริ่ม', 'base', 0]];
        for ($m = 1; $m <= $months; $m++) { $points[] = ['m' . $m, 'ด.' . $m . ($m === $months ? ' (จบ)' : ''), 'month', $m]; }
        foreach ([6, 12, 18, 24] as $n) { $points[] = ['p' . $n, 'หลังจบ ' . $n . ' ด.', 'post', $n]; }

        $only  = fn (array $ks) => array_fill_keys($ks, 'req');
        $tx    = array_map(fn ($m) => 'm' . $m, range(1, $months));
        $post  = ['p6', 'p12', 'p18', 'p24'];
        $end   = 'm' . $months;

        return [
            'key'    => 'tpt',
            'title'  => 'ยาป้องกันวัณโรค (TPT) ' . $months . ' เดือน — แนวทาง LTBI ในผู้สัมผัส กองวัณโรค',
            'treat_months' => $months,
            'points' => $points,
            'checks' => [
                ['key' => 'ltbi_test', 'label' => 'TST / IGRA ยืนยันติดเชื้อระยะแฝง', 'sources' => [], 'points' => $only(['base'])],
                ['key' => 'clinical',  'label' => 'ซักอาการ / แยกวัณโรค active', 'sub' => 'ระหว่างรักษา: อาการไม่พึงประสงค์ (ตับ ผื่น ไข้ ชาปลายมือเท้า)',
                 'sources' => ['tb_visit'], 'remind' => false, 'points' => $only(array_merge(['base'], $tx, $post))],
                ['key' => 'cxr',       'label' => 'CXR', 'sub' => 'ก่อนเริ่ม + ทุก 6 เดือนหลังจบ อย่างน้อย 2 ปี', 'sources' => ['xray'],
                 'points' => $only(array_merge(['base'], $post))],
                ['key' => 'weight',    'label' => 'น้ำหนัก', 'sub' => 'คิดขนาดยา', 'sources' => ['weight'], 'remind' => false, 'points' => $only(array_merge(['base'], $tx))],
                ['key' => 'lft',       'label' => 'LFT (AST / ALT)', 'sub' => 'ก่อนเริ่มถ้ามีปัจจัยเสี่ยงตับ · ห้ามให้ยาถ้า > 5 เท่าค่าปกติ',
                 'sources' => ['ext:C071', 'ext:C600', 'ext:C610'], 'preset' => ['ext:C071'], 'points' => ['base' => 'advice:liver']],
                ['key' => 'preg',      'label' => 'ตั้งครรภ์ / ให้นมบุตร', 'sub' => 'เป็นข้อห้าม — ถามก่อนเริ่ม', 'sources' => ['ext:D120'], 'preset' => ['ext:D120'],
                 'points' => ['base' => ['cond' => ['if' => 'female'], 'text' => 'ผู้หญิง']]],
                ['key' => 'outcome',   'label' => 'ประเมินผลการรักษา', 'sub' => 'รักษาครบ / หยุดการรักษา', 'sources' => [], 'points' => $only([$end])],
            ],
            'notes' => [
                'ตามแนวทาง "การบริหารจัดการค้นหาและรักษาการติดเชื้อวัณโรคระยะแฝงสำหรับผู้สัมผัสวัณโรค" กองวัณโรค',
                'สูตร: 3HP (สัปดาห์ละครั้ง 12 ครั้ง · แนะนำกินยาที่หน่วยบริการ) · 1HP · 4R · 3HR · 6-9H — จำนวนเดือนอ่านจากชื่อสูตร',
                'ผู้สัมผัส MDR/Pre-XDR/XDR-TB ไม่แนะนำให้ยาป้องกัน — ติดตามอาการอย่างน้อย 2 ปี',
                'ชาปลายมือเท้าจาก INH ไม่ต้องหยุดยา ให้วิตามินบี 6 · พิษต่อตับ/แพ้รุนแรง หยุดยาที่สงสัยแล้วปรึกษาแพทย์',
            ],
        ];
    }

    /** TPT ยาวกี่เดือน — อ่านจากชื่อสูตร (3HP=3, 1HP=1, 6Lfx=6, "6-9 INH"=6) ไม่รู้ = 3 */
    public static function tptFor(?string $regimen): array
    {
        $n = preg_match('/(\d+)/', (string) $regimen, $m) ? (int) $m[1] : 3;
        $n = max(1, min(12, $n));
        $def = self::tpt($n);
        return $def;
    }

    /** ป้ายภาษาไทยของสถานะ — ใช้ร่วมกันทั้งตารางและข้อความเตือน */
    public const STATUS_LABELS = [
        'done'     => 'ตรวจแล้ว',
        'due'      => 'ถึงกำหนด',
        'overdue'  => 'เกินกำหนด',
        'upcoming' => 'ยังไม่ถึง',
        'optional' => 'พิจารณา',
        'cond'     => 'มีเงื่อนไข',
        'na'       => '',
    ];
}
