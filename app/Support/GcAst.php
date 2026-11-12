<?php

namespace App\Support;

/**
 * เกณฑ์แปลผลความไวต่อยาของ Neisseria gonorrhoeae (GC AST) — ตามแผ่นเกณฑ์ลายมือที่ห้อง Lab ให้มา 2569-09-30
 * คลินิกยืนยันให้ยึดตามลายมือทุกตัว แม้ใบรายงานเก่าจะออกต่างไป (เช่น Ciprofloxacin 28 mm = Intermediate)
 * (CLSI M100 2026) · ระบบเสนอผลแปลให้อัตโนมัติ ห้อง Lab เปลี่ยนเองได้ก่อนบันทึก
 *
 * แก้เกณฑ์ที่นี่ที่เดียว — ผลที่ออกไปแล้วเก็บผลแปลไว้กับรายการ ไม่ถูกคิดใหม่ตามเกณฑ์ใหม่
 *
 * MIC (ug/ml, E-test) ค่ามาเป็นขั้น 0.016 / 0.032 / 0.064 / 0.125 ... จึงเทียบแบบ "ไม่เกิน" ของแต่ละขั้น
 * Zone (mm, disc diffusion)
 */
class GcAst
{
    public const RESULTS = ['Growth', 'GC Growth'];   // ผล Culture ที่ต้องลง AST

    public const INTERP = [
        'S'   => 'Susceptible',
        'I'   => 'Intermediate',
        'R'   => 'Resistance',
        'WT'  => 'Wild type',
        'NWT' => 'Non wild type',
        'PPNG'    => 'PPNG',
        'NonPPNG' => 'Non PPNG',
    ];

    /**
     * alert_at = ค่า MIC (ug/ml) ที่ถึงแล้วต้องเตือน (มากกว่าหรือเท่ากับ) — ห้อง Lab กำหนด 2569-10-01
     *   Cefixime >= 0.25 · Ceftriaxone >= 0.5 · เตือนตามค่าที่วัดได้ แม้ช่องผลแปลจะเลือกเป็นอย่างอื่น
     * ยาแต่ละตัว: type = mic | zone | bl (beta-lactamase) · range = ข้อความเกณฑ์บนใบรายงาน · alert = ดื้อแล้วต้องแจ้ง
     */
    public const DRUGS = [
        'cefixime'      => ['label' => 'Cefixime',       'type' => 'mic',  'range' => '<= 0.064',  'alert' => true, 'alert_at' => 0.25],
        'ceftriaxone'   => ['label' => 'Ceftriaxone',    'type' => 'mic',  'range' => '<= 0.125',  'alert' => true, 'alert_at' => 0.5],
        'azithromycin'  => ['label' => 'Azithromycin',   'type' => 'mic',  'range' => '<= 1',      'alert' => false],
        'tetracycline'  => ['label' => 'Tetracycline',   'type' => 'mic',  'range' => '<= 0.25',   'alert' => false],
        'betalactamase' => ['label' => 'Beta lactamase', 'type' => 'bl',   'range' => '',          'alert' => false],
        'penicillin'    => ['label' => 'Penicillin',     'type' => 'zone', 'range' => '>= 47',     'alert' => false],
        'spectinomycin' => ['label' => 'Spectinomycin',  'type' => 'zone', 'range' => '>= 18',     'alert' => false],
        'ciprofloxacin' => ['label' => 'Ciprofloxacin',  'type' => 'zone', 'range' => '>= 41',     'alert' => false],
    ];

    public const DEFAULT_COMMENT = 'Reference for CLSI M100 (2026)';

    /** ข้อความ Detection Method ตามใบรายงานของห้อง Lab (แก้ได้ต่อใบ) */
    public const DEFAULT_METHOD = 'Colony suspension from 20–24 hours growth from chocolate agar incubated at 36°C; 5% CO2; equivalent but CLSI used GC agar base';

    /** ผลแปลที่ระบบเสนอ — คืน null ถ้าค่าอ่านไม่ออก */
    public static function interpret(string $drug, $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') { return null; }

        if ($drug === 'betalactamase') {
            $v = mb_strtolower($raw);
            if (str_starts_with($v, 'pos') || $v === '+') { return 'PPNG'; }
            if (str_starts_with($v, 'neg') || $v === '-') { return 'NonPPNG'; }
            return null;
        }

        // รับ "<=0.016" / ">256" แบบที่เครื่องพิมพ์มา — ใช้ตัวเลขข้างใน
        $v = self::number($raw);
        if ($v === null) { return null; }

        switch ($drug) {
            case 'cefixime':     return $v <= 0.064 ? 'S' : ($v < 0.25 ? 'I' : 'R');
            case 'ceftriaxone':  return $v <= 0.125 ? 'S' : ($v < 0.5 ? 'I' : 'R');
            // แผ่นเกณฑ์ลายมือของห้อง Lab: <= 1 = Susceptible · > 1 = Non wild type (ยืนยันให้ยึดตามลายมือ 2569-09-30)
            case 'azithromycin': return $v <= 1 ? 'S' : 'NWT';
            case 'tetracycline': return $v <= 0.25 ? 'S' : ($v < 2 ? 'I' : 'R');
            case 'penicillin':   return $v >= 47 ? 'S' : ($v >= 27 ? 'I' : 'R');
            case 'spectinomycin':return $v >= 18 ? 'S' : ($v >= 15 ? 'I' : 'R');
            case 'ciprofloxacin':return $v >= 41 ? 'S' : ($v >= 28 ? 'I' : 'R');
        }
        return null;
    }

    /**
     * ตัวเลขในช่องค่า — รับ ".064" และ "0,5" (คีย์บอร์ดบางเครื่องใช้จุลภาค) ด้วย
     * เดิมอ่าน ".064" เป็น 64 = ดื้อยาปลอม · "0,5" เป็น 0 = S ทั้งที่ดื้อ (รีวิว 2569-09-30)
     */
    public static function number($raw): ?float
    {
        $s = str_replace(',', '.', (string) $raw);
        return preg_match('/(\d*\.\d+|\d+)/', $s, $m) ? (float) $m[1] : null;
    }

    /**
     * AST บันทึก/แก้หลังจาก Lab approve ใบนั้นไปแล้ว — ยังไม่ถือเป็นผลที่ approve
     * ต้องกด Approve ใหม่ก่อน พยาบาล/หมอถึงจะเห็น และใบพิมพ์ถึงจะขึ้น FINAL (รีวิว 2569-09-30)
     */
    public static function pendingApproval($lab, ?array $ast): bool
    {
        if (empty($ast['at']) || empty($lab->approve_by) || empty($lab->approve_date)) { return false; }
        $y  = (int) substr((string) $lab->approve_date, 0, 4);
        $ad = ($y > 2400 ? $y - 543 : $y) . substr((string) $lab->approve_date, 4, 6);
        try {
            return \Carbon\Carbon::parse($ad . ' ' . ($lab->approve_at ?: '00:00:00'))
                ->lt(\Carbon\Carbon::parse($ast['at']));
        } catch (\Throwable $e) {
            return true;   // อ่านเวลาไม่ออก = ถือว่ายังไม่ approve ไว้ก่อน ปลอดภัยกว่าโชว์ผลที่ไม่มีใครรับรอง
        }
    }

    /** ยาตัวนี้ต้องเตือนไหม — ค่า MIC ถึงเกณฑ์ alert_at หรือผลแปลเป็น Resistance */
    public static function isAlert(string $drug, $value, $interp = ''): bool
    {
        $def = self::DRUGS[$drug] ?? null;
        if (! $def || ! $def['alert']) { return false; }
        if ((string) $interp === 'R') { return true; }
        $v = trim((string) $value) === '' ? null : self::number($value);
        return $v !== null && isset($def['alert_at']) && $v >= $def['alert_at'];
    }

    /** ยาที่ต้องแจ้งเตือน (ดื้อ Cefixime / Ceftriaxone) */
    public static function alerts(?array $ast): array
    {
        $out = [];
        foreach ((array) ($ast['drugs'] ?? []) as $k => $d) {
            if (self::isAlert($k, $d['v'] ?? '', $d['i'] ?? '')) { $out[] = self::DRUGS[$k]['label']; }
        }
        return $out;
    }

    /** ข้อความสั้นใต้ผล Culture: "Cefixime 0.016 S · Tetracycline 8 R · Beta lactamase PPNG" */
    public static function summary(?array $ast): string
    {
        $parts = [];
        foreach (self::DRUGS as $k => $def) {
            $d = $ast['drugs'][$k] ?? null;
            if (! $d || (($d['v'] ?? '') === '' && ($d['i'] ?? '') === '')) { continue; }
            $parts[] = $def['label'] . ' ' . ($def['type'] === 'bl' ? '' : ($d['v'] ?? '') . ' ') . ($d['i'] ?? '');
        }
        return implode(' · ', $parts);
    }
}
