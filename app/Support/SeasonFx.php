<?php

namespace App\Support;

use App\Models\Config;
use Carbon\Carbon;

/**
 * ของประดับตามเทศกาล/ฤดู บนแถบเมนู (ผู้ใช้ขอ 2569-11-03)
 *
 * ตั้งใจให้ "เบา": ไอคอนเล็กข้างชื่อระบบตลอดช่วง + ของลอยจาง ๆ เฉพาะใกล้วันเทศกาล
 * เพราะหน้าจอนี้ใช้ตรวจคนไข้ทั้งวัน และพยาบาลเคยบอกว่าหน้าจอเปลี่ยนบ่อยจนตาลาย
 *
 * แอดมินปรับได้ที่หน้า "ตั้งค่าเอฟเฟกต์เทศกาล" (/backend/season-fx) — เก็บในตาราง configs ชื่อขึ้นต้น sfx_
 * ไม่มีค่าในตาราง = ใช้ค่าตั้งต้นในไฟล์นี้
 * เทศกาลที่วันเปลี่ยนทุกปีตามจันทรคติ (ลอยกระทง ตรุษจีน) เก็บเป็นรายการวัน ค.ศ. — ปีที่ไม่มีในรายการ = ไม่ขึ้น (ไม่เดา)
 */
class SeasonFx
{
    /** ค่าตั้งต้นวันลอยกระทง (ขึ้น 15 ค่ำ เดือน 12) — ใส่จากความจำ ควรเทียบปฏิทินราชการ แก้ได้ในหน้าตั้งค่า */
    public const LOY_KRATHONG = ['2026-11-24', '2027-11-13', '2028-11-02', '2029-11-21', '2030-11-10'];

    /** ค่าตั้งต้นวันตรุษจีน (วันชิวอิด) */
    public const CNY = ['2027-02-06', '2028-01-26', '2029-02-13', '2030-02-03'];

    /** ทุกอย่างที่เปิด/ปิดแยกได้ ตามลำดับที่แสดงในหน้าตั้งค่า */
    public const ITEMS = [
        'loy'      => ['ลอยกระทง · ยี่เป็ง', '🪔', 'lantern', 'โคมลอยขึ้น'],
        'xmas'     => ['คริสต์มาส', '🎄', 'snow', 'หิมะ'],
        'newyear'  => ['สวัสดีปีใหม่', '🎉', 'confetti', 'กระดาษสี'],
        'cny'      => ['ตรุษจีน', '🧧', 'petal', 'กลีบดอกสีแดง'],
        'songkran' => ['สงกรานต์', '💦', 'drop', 'หยดน้ำ'],
        // ฤดูมีของลอยด้วย (ผู้ใช้ขอ 2569-11-03) — เปิด/ปิดแยกจากเทศกาลด้วยสวิตช์ "ของลอยตามฤดู"
        'cool'     => ['ฤดูหนาว', '❄️', 'snow', 'หิมะ'],
        'hot'      => ['ฤดูร้อน', '☀️', 'cassia', 'ดอกคูน (ราชพฤกษ์) ร่วง'],
        'rain'     => ['ฤดูฝน', '☔', 'rain', 'สายฝน'],
    ];

    /** ค่าตั้งต้นของตัวเลข: [ค่าตั้งต้น, ต่ำสุด, สูงสุด] */
    public const NUMS = [
        'opacity' => [55, 20, 90],    // ความเข้มของลอย (%)
        'seconds' => [10, 3, 30],     // ลอยนานกี่วินาที
        'gap'     => [30, 0, 240],    // ขึ้นซ้ำในเครื่องเดิมได้ทุกกี่นาที
    ];

    private static ?array $cache = null;

    /** ค่าตั้งค่าทั้งหมด (อ่านครั้งเดียวต่อคำขอ) */
    public static function settings(): array
    {
        if (self::$cache !== null) { return self::$cache; }
        $raw = [];
        try {
            $raw = Config::where('name', 'like', 'sfx\_%')->pluck('value', 'name')->all();
        } catch (\Throwable $e) {
            // ตาราง configs มีปัญหา ไม่ควรทำให้ทุกหน้าพัง — ใช้ค่าตั้งต้น
        }
        $bool = fn ($k, $d) => array_key_exists($k, $raw) ? in_array((string) $raw[$k], ['1', 'on', 'true'], true) : $d;
        $list = fn ($k, $d) => array_key_exists($k, $raw)
            ? array_values(array_filter(array_map('trim', explode(',', (string) $raw[$k]))))
            : $d;
        $num = function ($k) use ($raw) {
            [$def, $min, $max] = self::NUMS[$k];
            $v = $raw['sfx_' . $k] ?? null;
            return is_numeric($v) ? max($min, min($max, (int) $v)) : $def;
        };

        return self::$cache = [
            'enabled'   => $bool('sfx_enabled', true),
            'particles' => $bool('sfx_particles', true),
            'season_particles' => $bool('sfx_season_particles', true),
            'disabled'  => $list('sfx_disabled', []),
            'loy'       => $list('sfx_loy', self::LOY_KRATHONG),
            'cny'       => $list('sfx_cny', self::CNY),
            'opacity'   => $num('opacity'),
            'seconds'   => $num('seconds'),
            'gap'       => $num('gap'),
        ];
    }

    public static function forgetCache(): void { self::$cache = null; }

    /**
     * เทศกาลทั้งหมดของปี: [key, ช่วงประดับ [จาก, ถึง], ช่วงมีของลอย [จาก, ถึง], วันจริง]
     * ของลอยแค่ไม่กี่วันรอบวันจริง ที่เหลือมีแต่ไอคอน
     */
    public static function festivals(int $year): array
    {
        $s = self::settings();
        $d = fn (string $v) => Carbon::parse($v)->startOfDay();
        $out = [];
        foreach ($s['loy'] as $v) {
            if ((int) substr($v, 0, 4) !== $year) { continue; }
            $day = $d($v);
            $out[] = ['loy', [$day->copy()->subDays(7), $day->copy()->addDay()], [$day->copy()->subDays(3), $day->copy()->addDay()], $day];
        }
        foreach ($s['cny'] as $v) {
            if ((int) substr($v, 0, 4) !== $year) { continue; }
            $day = $d($v);
            $out[] = ['cny', [$day->copy()->subDays(5), $day->copy()->addDays(3)], [$day->copy()->subDays(2), $day->copy()->addDay()], $day];
        }
        $out[] = ['xmas', [$d("$year-12-18"), $d("$year-12-26")], [$d("$year-12-23"), $d("$year-12-26")], $d("$year-12-25")];
        // ปีใหม่ข้ามปี — ต้นมกราของปีนี้มาจากรอบของปีก่อน
        $out[] = ['newyear', [$d(($year - 1) . '-12-27'), $d("$year-01-03")], [$d(($year - 1) . '-12-30'), $d("$year-01-02")], $d("$year-01-01")];
        $out[] = ['newyear', [$d("$year-12-27"), $d(($year + 1) . '-01-03')], [$d("$year-12-30"), $d(($year + 1) . '-01-02')], $d(($year + 1) . '-01-01')];
        $out[] = ['songkran', [$d("$year-04-08"), $d("$year-04-16")], [$d("$year-04-12"), $d("$year-04-15")], $d("$year-04-13")];
        return $out;
    }

    /** ฤดูของไทยแบบกรมอุตุฯ: ร้อน กลาง ก.พ.–กลาง พ.ค. · ฝน กลาง พ.ค.–กลาง ต.ค. · หนาว กลาง ต.ค.–กลาง ก.พ. */
    private static function seasonKey(Carbon $day): string
    {
        $md = (int) $day->format('md');
        if ($md >= 215 && $md <= 515) { return 'hot'; }
        if ($md >= 516 && $md <= 1015) { return 'rain'; }
        return 'cool';
    }

    private static function pack(string $key, ?string $fx): array
    {
        $s = self::settings();
        [$label, $icon] = self::ITEMS[$key];
        return ['key' => $key, 'label' => $label, 'icon' => $icon, 'fx' => $s['particles'] ? $fx : null,
                'opacity' => $s['opacity'], 'seconds' => $s['seconds'], 'gap' => $s['gap']];
    }

    /**
     * ของวันนี้ — null = ไม่แสดงอะไรเลย (ปิดทั้งระบบ หรือปิดฤดูนั้นไว้)
     * $force = ดูตัวอย่างล่วงหน้า (?sfx=loy) ไม่ต้องรอถึงวันจริง ใช้ได้แม้ปิดไว้ เพื่อให้แอดมินลองก่อนเปิด
     */
    public static function today(?Carbon $day = null, ?string $force = null): ?array
    {
        $day = ($day ?? now())->copy()->startOfDay();
        $s = self::settings();

        if ($force && isset(self::ITEMS[$force])) {
            return ['fx' => self::ITEMS[$force][2]] + self::pack($force, self::ITEMS[$force][2]);
        }
        if (! $s['enabled']) { return null; }

        foreach (self::festivals((int) $day->year) as [$key, $deco, $float]) {
            if (in_array($key, $s['disabled'], true)) { continue; }
            if ($day->betweenIncluded($deco[0], $deco[1])) {
                return self::pack($key, $day->betweenIncluded($float[0], $float[1]) ? self::ITEMS[$key][2] : null);
            }
        }
        $key = self::seasonKey($day);
        if (in_array($key, $s['disabled'], true)) { return null; }
        return self::pack($key, $s['season_particles'] ? self::ITEMS[$key][2] : null);
    }

    /** เทศกาลที่กำลังจะมาถึง (ไว้โชว์ในหน้าตั้งค่า) — [key, วันจริง, ช่วงประดับ, ช่วงลอย] */
    public static function upcoming(int $count = 6): array
    {
        $today = now()->startOfDay();
        $all = array_merge(self::festivals($today->year), self::festivals($today->year + 1));
        $all = array_filter($all, fn ($f) => $f[1][1]->gte($today));
        usort($all, fn ($a, $b) => $a[1][0] <=> $b[1][0]);
        $seen = []; $out = [];
        foreach ($all as $f) {
            $id = $f[0] . $f[3]->toDateString();
            if (isset($seen[$id])) { continue; }
            $seen[$id] = true; $out[] = $f;
            if (count($out) >= $count) { break; }
        }
        return $out;
    }
}
