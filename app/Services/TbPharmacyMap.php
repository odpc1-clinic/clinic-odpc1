<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\TbDrug;

/**
 * จับคู่ "รหัสยาวัณโรค" ในกล่องสั่งยา (H R Z E 4FDC B6 Lfx ...) กับยาในคลังยา (ตาราง medicines)
 * เพื่อให้กล่องสั่งยาครั้งนี้ในหน้าตรวจลงรายการยาของ visit ได้ — การเงินคิดเงิน ห้องยาจ่าย/ตัดสต๊อก FEFO (2569-10-05)
 *
 * หลักคือ "ไม่เดา" — ยาเกี่ยวกับเงินและสต๊อก ถ้าจับคู่ผิดคนไข้ได้ยาผิดตัวหรือถูกคิดเงินผิด
 *   - ตรงกันตัวเดียวเท่านั้นถึงนับว่าจับคู่ได้ · เจอหลายตัวที่น่าจะใช่ = ไม่จับ (หน้าจอเตือนให้ห้องยาเพิ่มเอง)
 *   - รหัสตัวอักษรเดียว (H R Z E P) ไม่ใช้จับชื่อตรง ๆ เพราะไปชนชื่ออื่นง่าย (เช่น "Vitamin E") ใช้ชื่อสามัญจากรายการยา TB แทน
 *   - ระบุความแรงแล้ว ยาในคลังที่เขียนความแรงอื่นไว้ = ไม่ใช่ตัวนี้
 *   - ตั้งคู่เองได้ที่ config/tb_pharmacy.php (มาก่อนการจับอัตโนมัติเสมอ)
 */
class TbPharmacyMap
{
    /** @var \Illuminate\Support\Collection|null */
    private static $meds = null;

    private static function meds()
    {
        return self::$meds = self::$meds ?? Medicine::orderBy('id')->get(['id', 'name', 'dose', 'unit', 'price']);
    }

    /** ล้างแคช (ใช้ในสคริปต์ทดสอบ) */
    public static function flush(): void
    {
        self::$meds = null;
    }

    private static function words(string $s): array
    {
        return array_values(array_filter(preg_split('/[^a-z0-9]+/', strtolower($s))));
    }

    private static function collapse(string $s): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]/', '', $s));
    }

    /** ความแรงที่เขียนไว้ในชื่อ/ขนาดของยาในคลัง เช่น "Acyclovir(400mg)" · dose "400 mg" */
    private static function strengthsOf(Medicine $m): array
    {
        $txt = $m->name . ' ' . $m->dose;
        preg_match_all('/(\d+(?:\.\d+)?)\s*(?:mg|มก)/iu', $txt, $a);
        preg_match_all('/\((\d+(?:\.\d+)?)\)/', $txt, $b);
        return array_values(array_unique(array_map('floatval', array_merge($a[1], $b[1]))));
    }

    /** คำที่ใช้ค้นยาในคลังของรหัสหนึ่ง: [คำตรงตัว, คำขึ้นต้น(ชื่อสามัญ), คำที่อยู่ในชื่อรวม(ยาเม็ดรวม)] */
    private static function tokensFor(string $key): array
    {
        static $catalog = null;
        $catalog = $catalog ?? TbDrug::where('active', true)->get();

        // รหัสในตารางขนาดยาที่เป็นยาตัวเดียวกันคนละสูตร
        $base = ['H-3HP' => 'H', 'H-1HP' => 'H', 'P-1HP' => 'P', 'INH' => 'H', 'Hhd' => 'H'][$key] ?? $key;
        $exact = [];
        $prefix = [];
        $contains = [];

        if (strlen($base) >= 2) { $exact[] = strtolower($base); }
        if ($base === 'H') { $exact[] = 'inh'; }

        if (preg_match('/^(\d)fdc$/i', $base, $m)) {
            $contains[] = $m[1] . 'fdc';
        }

        foreach ($catalog as $d) {
            $code = preg_match('/\(([^)]+)\)/', (string) $d->name, $mm) ? rtrim(trim($mm[1]), '.') : '';
            $short = $d->shortKey();
            $hit = strcasecmp($short, $base) === 0 || strcasecmp($code, $base) === 0
                || ($base === 'H' && strcasecmp($code, 'INH') === 0);
            if (! $hit) { continue; }
            $generic = strtolower(preg_replace('/[^A-Za-z]/', '', preg_split('/[\s(]+/', trim((string) $d->name))[0] ?? ''));
            // "Isoniazid high-dose" = ยาเม็ด INH ตัวเดียวกัน · ชื่อสั้นเกินไปไม่ใช้ขึ้นต้น (ชนง่าย)
            if (strlen($generic) >= 5) { $prefix[] = $generic; }
            // ยี่ห้อยาเม็ดรวมในวงเล็บ (Rimstar / Rimactazid / Rifanah)
            if (preg_match('/fdc/i', $base) && $code !== '') {
                foreach (preg_split('/[^A-Za-z]+/', $code) as $brand) {
                    if (strlen($brand) >= 5) { $contains[] = strtolower($brand); }
                }
            }
        }

        return [array_unique($exact), array_unique($prefix), array_unique($contains)];
    }

    /**
     * ยาในคลังของรหัสยาวัณโรค + ความแรง (มก. ต่อเม็ด) — คืน Medicine หรือ null (ไม่มี/ไม่แน่ใจ)
     * $why ได้เหตุผลกลับไปบอกผู้ใช้
     */
    public static function resolve(string $key, ?float $strength = null, ?string &$why = null): ?Medicine
    {
        $why = null;
        $map = (array) config('tb_pharmacy.map', []);
        $sKey = $strength ? $key . '@' . rtrim(rtrim(number_format($strength, 2, '.', ''), '0'), '.') : null;
        $pinned = ($sKey && isset($map[$sKey])) ? $map[$sKey] : ($map[$key] ?? null);
        if ($pinned) {
            $m = self::meds()->firstWhere('id', (int) $pinned);
            if ($m) { return $m; }
            $why = 'config/tb_pharmacy.php ชี้ไปยา #' . (int) $pinned . ' ซึ่งไม่มีในคลังยา';
            return null;
        }

        [$exact, $prefix, $contains] = self::tokensFor($key);
        $cands = self::meds()->filter(function ($m) use ($exact, $prefix, $contains) {
            $w = self::words((string) $m->name);
            $c = self::collapse((string) $m->name);
            foreach ($exact as $t) { if (in_array($t, $w, true) || $c === $t) { return true; } }
            foreach ($prefix as $t) { foreach ($w as $x) { if (str_starts_with($x, $t)) { return true; } } }
            foreach ($contains as $t) { if (str_contains($c, $t)) { return true; } }
            return false;
        });

        if ($strength) {
            // เขียนความแรงอื่นไว้ = คนละตัว · ไม่เขียนความแรงเลย = อาจใช่ (อันดับรอง)
            $cands = $cands->reject(fn ($m) => ($s = self::strengthsOf($m)) && ! in_array((float) $strength, $s, true));
            $exactStr = $cands->filter(fn ($m) => in_array((float) $strength, self::strengthsOf($m), true));
            if ($exactStr->isNotEmpty()) { $cands = $exactStr; }
        }

        if ($cands->count() > 1) {
            // ชื่อในคลังตรงกับรหัสพอดี (เช่น ยาชื่อ "B6") ถือว่าตั้งใจให้เป็นตัวนี้
            $same = $cands->filter(fn ($m) => self::collapse((string) $m->name) === self::collapse($key));
            if ($same->count() === 1) { return $same->first(); }
            $why = 'คลังยามีหลายตัวที่ชื่อใกล้กัน (' . $cands->pluck('name')->implode(', ') . ') — ตั้งคู่ใน config/tb_pharmacy.php';
            return null;
        }
        if ($cands->isEmpty()) {
            $why = 'ยังไม่มีในคลังยา' . ($strength ? ' (ความแรง ' . rtrim(rtrim(number_format($strength, 2, '.', ''), '0'), '.') . ' มก.)' : '');
            return null;
        }

        return $cands->first();
    }

    /**
     * ยาอื่นที่ไม่ใช่ยาวัณโรค (Para, Brufen ...) — พิมพ์ชื่อเอง/เลือกจากรายการ
     * ต้องตรงกับชื่อในคลังยา (ไม่สนตัวพิมพ์/ช่องว่าง/วงเล็บ) · ชื่อซ้ำในคลัง (เช่น Brown mixture 2 แถว) = ยาเดียวกัน ใช้แถวที่ตั้งราคาไว้ก่อน
     */
    public static function resolveName(string $name): ?Medicine
    {
        $c = self::collapse($name);
        if ($c === '') { return null; }
        $hits = self::meds()->filter(fn ($m) => self::collapse((string) $m->name) === $c);
        return $hits->sortBy(fn ($m) => [(float) $m->price > 0 ? 0 : 1, $m->id])->first();
    }

    /** รายชื่อยาในคลังสำหรับ datalist ของช่อง "ยาอื่น" */
    public static function names(): array
    {
        return self::meds()->pluck('name')->filter()->unique()->values()->all();
    }
}
