<?php

namespace App\Support;

/**
 * วิธีใช้ยา (Sig) แบบเลือกจากตัวเลือก — หน้าสั่งยาของคลินิกหลัก (คลินิกขอ 2569-09-29)
 *
 * ช่อง dose / route เดิมเป็นข้อความอิสระ ("1*2", "Oral pc") ห้องยาและคนไข้อ่านต่างกันไปคนละแบบ
 * และห้องยาไม่เห็นวิธีใช้เลย จึงเก็บวิธีใช้แยกเป็นรหัส แล้วแปลเป็นภาษาคนไข้ตอนแสดง
 * ช่อง dose / route เดิมยังอยู่ครบ เพราะ export ทุกตัวและใบพิมพ์ใช้อยู่
 *
 * รูปแบบที่เก็บ: {"qty":"1","unit":"เม็ด","freq":"BID","route":"PO","timing":"pc","days":"7"}
 * บัตร TB 01 มีชุดของตัวเองใน TbRegistration (ยาวัณโรคใช้แค่ PO/IM/IV และไม่มีจำนวนวัน)
 */
class DrugSig
{
    // หลอด/ขวด (ยาทา ยาน้ำ) เพิ่ม 2569-10-27 — เดิมไม่มี กล่องสั่งยาวัณโรคส่ง "หลอด" มาแล้วหน่วยหายจากรายการยา
    public static $unit = ['เม็ด', 'แคปซูล', 'ซอง', 'มล.', 'ช้อนชา', 'หยด', 'หลอด', 'ขวด', 'vial', 'ampule'];

    /** ความถี่: รหัส => [ข้อความบนฉลาก, ครั้งต่อวัน] — null = คิดจำนวนต่อวันไม่ได้ */
    public static $freq = [
        'STAT' => ['ครั้งเดียว', null],
        'OD'   => ['วันละ 1 ครั้ง', 1],
        'BID'  => ['วันละ 2 ครั้ง', 2],
        'TID'  => ['วันละ 3 ครั้ง', 3],
        'QID'  => ['วันละ 4 ครั้ง', 4],
        'QW'   => ['สัปดาห์ละ 1 ครั้ง', 1 / 7],
        'TIW'  => ['สัปดาห์ละ 3 ครั้ง', 3 / 7],
        'PRN'  => ['เมื่อมีอาการ', null],
    ];

    public static $route = [
        'PO'  => 'รับประทาน',
        'IM'  => 'ฉีดเข้ากล้ามเนื้อ',
        'IV'  => 'ฉีดเข้าหลอดเลือดดำ',
        'SC'  => 'ฉีดใต้ผิวหนัง',
        'ID'  => 'ฉีดเข้าชั้นผิวหนัง',
        'TOP' => 'ทาบริเวณที่เป็น',
        'PV'  => 'สอดช่องคลอด',
        'EYE' => 'ใช้กับตา',
    ];

    public static $timing = [
        'ac_bf' => 'ก่อนอาหารเช้า (ท้องว่าง)',
        'ac'    => 'ก่อนอาหาร',
        'pc_bf' => 'หลังอาหารเช้า',
        'pc'    => 'หลังอาหาร',
        'pc_imm' => 'หลังอาหารทันที',   // ยากัดกระเพาะ เช่น Ibuprofen (2569-10-01)
        'cc'    => 'พร้อมอาหาร',
        'hs'    => 'ก่อนนอน',
    ];

    /**
     * ช่วงเวลาที่กิน (ติ๊กได้หลายช่อง) — คลินิกขอ 2569-09-30 เพราะ "วันละ 2 ครั้ง" อย่างเดียว
     * คนไข้ไม่รู้ว่าเช้า-เย็น หรือเช้า-กลางวัน · ใช้ร่วมกับบัตร TB 01
     */
    public static $period = ['m' => 'เช้า', 'n' => 'กลางวัน', 'e' => 'เย็น', 'b' => 'ก่อนนอน'];

    /** ช่องพิมพ์เพิ่มเติมเอง เช่น "ทุก 8 ชั่วโมง" "ห้ามกินพร้อมนม" */
    public const NOTE_MAX = 100;

    /** เก็บเฉพาะช่วงเวลาที่อยู่ในตัวเลือก เรียงตามเวลาจริง */
    public static function cleanPeriods($v): array
    {
        $v = array_map('strval', (array) $v);
        return array_values(array_filter(array_keys(self::$period), fn ($k) => in_array($k, $v, true)));
    }

    public static function cleanNote($v): string
    {
        // ไม่ใช้ strip_tags — "ถ้าน้ำตาล <70 ให้หยุดยา" จะถูกตัดเหลือ "ถ้าน้ำตาล" (รีวิว 2569-10-01)
        // ปลอดภัยอยู่แล้วเพราะทุกที่แสดงผลผ่าน Blade {{ }} ซึ่ง escape ให้
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $v)), 0, self::NOTE_MAX);
    }

    /**
     * "เช้า-เย็น" + ข้อความเพิ่มเติม — ต่อท้ายข้อความวิธีใช้ทั้งของคลินิกหลักและบัตร TB
     * $timing = รหัสเวลาเทียบมื้ออาหาร ถ้าเลือก "ก่อนนอน" ไว้แล้ว ไม่ต้องพูดซ้ำ
     */
    public static function extraText(?array $sig, string $timing = ''): string
    {
        $p = self::cleanPeriods($sig['period'] ?? []);
        if ($timing === 'hs') { $p = array_values(array_diff($p, ['b'])); }
        $out = implode('-', array_map(fn ($k) => self::$period[$k], $p));
        $note = self::cleanNote($sig['note'] ?? '');
        return trim($out . ($note !== '' ? ' ' . $note : ''));
    }

    /** รับค่าจากฟอร์ม เก็บเฉพาะค่าที่อยู่ในตัวเลือก — ไม่ได้เลือกความถี่ = ยังไม่ตั้ง คืน null */
    public static function clean($row): ?array
    {
        if (! is_array($row)) {
            return null;
        }
        $num  = fn ($v) => preg_match('/^\d+(\.\d+)?$/', $v = trim(str_replace(',', '.', (string) $v))) ? $v : '';
        // หน้าจอแนะนำให้พิมพ์ "1/2" (ครึ่งเม็ด) และ "7-14" (วัน) ได้ — เดิมเซิร์ฟเวอร์ตัดทิ้ง ข้อความที่เก็บเลยต่างจากที่เห็น (รีวิว 2569-09-30)
        $qtyIn  = fn ($v) => preg_match('#^\d+/\d+$#', $v = preg_replace('/\s+/', '', (string) $v)) ? $v : $num($v);
        $daysIn = fn ($v) => preg_match('/^\d+-\d+$/', $v = preg_replace('/\s+/', '', (string) $v)) ? $v : $num($v);
        $pick = fn ($v, array $opts) => array_key_exists((string) $v, $opts) ? (string) $v : '';

        $sig = [
            'qty'    => $qtyIn($row['qty'] ?? ''),
            'unit'   => in_array((string) ($row['unit'] ?? ''), self::$unit, true) ? (string) $row['unit'] : '',
            'freq'   => $pick($row['freq'] ?? '', self::$freq),
            'route'  => $pick($row['route'] ?? '', self::$route),
            'timing' => $pick($row['timing'] ?? '', self::$timing),
            'days'   => $daysIn($row['days'] ?? ''),
            'period' => self::cleanPeriods($row['period'] ?? []),
            'note'   => self::cleanNote($row['note'] ?? ''),
        ];

        // ไม่ได้เลือกความถี่ แต่พิมพ์วิธีใช้เอง/ติ๊กช่วงเวลาไว้ ก็เก็บ (ยาบางตัวอธิบายด้วยตัวเลือกไม่ได้)
        // เดิมติ๊ก เช้า-เย็น แล้วลบความถี่ออก วิธีใช้ทั้งชุดหายตอนบันทึก (รีวิว 2569-09-30)
        return $sig['freq'] === '' && $sig['note'] === '' && ! $sig['period'] ? null : $sig;
    }

    /** "รับประทานครั้งละ 1 เม็ด วันละ 2 ครั้ง หลังอาหาร นาน 7 วัน" — ว่างถ้ายังไม่ตั้ง */
    /** "1/2" หรือ "1.5" → ตัวเลข · อ่านไม่ออก = 0 */
    public static function qtyNum($q): float
    {
        $q = trim((string) $q);
        return preg_match('#^(\d+)/(\d+)$#', $q, $f) && (int) $f[2] > 0 ? (int) $f[1] / (int) $f[2] : (float) $q;
    }

    /**
     * จำนวนต่อครั้งบนฉลาก (คลินิกขอ 2569-10-12): เม็ดครึ่ง เขียนแบบคนอ่าน — 0.5 = "ครึ่งเม็ด" · 1.5 = "1 เม็ดครึ่ง"
     * หน่วยอื่น/จำนวนเต็ม = "2 เม็ด" แบบเดิม
     */
    public static function qtyText($qty, ?string $unit): string
    {
        $q = trim((string) $qty);
        $unit = trim((string) $unit);
        if ($q === '') { return ''; }
        $n = self::qtyNum($q);
        if ($unit === 'เม็ด' && $n > 0 && abs($n * 2 - round($n * 2)) < 1e-9 && abs($n - round($n)) > 1e-9) {
            $whole = (int) floor($n);
            return $whole === 0 ? 'ครึ่งเม็ด' : $whole . ' เม็ดครึ่ง';
        }
        return trim($q . ' ' . $unit);
    }

    /**
     * ต่อครั้งใช้กี่เม็ดจากคลัง — ยาเม็ดที่สั่งเศษ (1.5 / ครึ่งเม็ด) หักครึ่งที่เหลือทิ้ง จึงตัดสต๊อกเต็มเม็ด (ปัดขึ้น)
     * คลินิกขอ 2569-10-12: ฉลาก 1 เม็ดครึ่ง แต่ตัดสต๊อก 2 เม็ด · หน่วยอื่น (มล. ฯลฯ) ใช้ตามจริง
     */
    public static function dosePerDispense(float $qty, ?string $unit): float
    {
        return trim((string) $unit) === 'เม็ด' ? (float) ceil($qty - 1e-9) : $qty;
    }

    public static function text(?array $sig): string
    {
        $freq  = self::$freq[$sig['freq'] ?? ''][0] ?? '';
        $extra = self::extraText($sig, (string) ($sig['timing'] ?? ''));
        if ($freq === '' && $extra === '') {
            return '';
        }
        $route = self::$route[$sig['route'] ?? ''] ?? '';
        $qty   = trim((string) ($sig['qty'] ?? ''));
        $time  = self::$timing[$sig['timing'] ?? ''] ?? '';
        $days  = trim((string) ($sig['days'] ?? ''));

        $parts = array_filter([
            $route,
            $qty !== '' ? 'ครั้งละ ' . self::qtyText($qty, $sig['unit'] ?? '') : '',
            $freq,
            $time,
            $extra,
            // ครั้งเดียวไม่ต้องบอกจำนวนวัน
            $days !== '' && ($sig['freq'] ?? '') !== 'STAT' ? 'นาน ' . $days . ' วัน' : '',
        ], fn ($s) => trim($s) !== '');

        return trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)));
    }

    /**
     * จำนวนที่ต้องจ่าย = ครั้งละ × ครั้งต่อวัน × วัน — null ถ้าคิดไม่ได้ (เมื่อมีอาการ / ไม่ได้กรอก)
     * ยาสัปดาห์ละครั้งปัดจำนวนครั้งขึ้น (จ่าย 30 วัน = 5 ครั้ง) กันยาขาดก่อนนัด
     */
    public static function total(?array $sig): ?float
    {
        // "1/2" = ครึ่งเม็ด · (float) "1/2" ได้ 1 ซึ่งผิด
        // ยาเม็ดสั่งเศษ: คิดจำนวนจ่ายเป็นเม็ดเต็มต่อครั้ง (หักครึ่งที่เหลือทิ้ง) — ดู dosePerDispense
        $qty = self::dosePerDispense(self::qtyNum($sig['qty'] ?? ''), $sig['unit'] ?? '');
        $code = $sig['freq'] ?? '';
        if ($qty <= 0 || ! isset(self::$freq[$code])) {
            return null;
        }
        if ($code === 'STAT') {
            return $qty;
        }
        $per  = self::$freq[$code][1];
        $days = (int) ($sig['days'] ?? 0);
        if (! $per || $days < 1) {
            return null;
        }
        $times = $per >= 1 ? $per * $days : (int) ceil($per * $days - 1e-9);

        return $qty * $times;
    }

    /**
     * เดาวิธีใช้จากข้อความเดิมในคลังยา ("1*2" + "Oral pc" + "7days") ไว้เติมให้ตอนเลือกยา
     * เป็นแค่ค่าตั้งต้น หมอต้องดูเองอีกที — อ่านไม่ออกก็ปล่อยว่าง ไม่เดามั่ว
     */
    public static function guess(?string $dose, ?string $route, ?string $amount, ?string $unit = null): array
    {
        $dose   = strtolower(trim((string) $dose));
        $route  = strtolower(trim((string) $route));
        $amount = strtolower(trim((string) $amount));
        $all    = $dose . ' ' . $route;
        $sig    = ['qty' => '', 'unit' => '', 'freq' => '', 'route' => '', 'timing' => '', 'days' => ''];

        $sig['unit'] = in_array((string) $unit, self::$unit, true) ? (string) $unit : '';

        // "1*2" = ครั้งละ 1 วันละ 2 ครั้ง · "2*1*1" คือ PrEP แบบ 2-1-1 ไม่ใช่รายวัน ไม่เดา
        if (preg_match('/^(\d+(?:\.\d+)?)\s*\*\s*(\d)\b(?!\s*\*)/', $dose, $m)) {
            $sig['qty']  = $m[1];
            $sig['freq'] = [1 => 'OD', 2 => 'BID', 3 => 'TID', 4 => 'QID'][(int) $m[2]] ?? '';
        } elseif (preg_match('/^(\d+(?:\.\d+)?)\s*(tab|tabs|cap|caps)\b/', $dose, $m)) {
            $sig['qty']  = $m[1];
            $sig['unit'] = $m[2][0] === 'c' ? 'แคปซูล' : 'เม็ด';
        }

        // "stat" เขียนไว้ชัด = ครั้งเดียวเสมอ · จำนวน "1 ครั้ง" ถือเป็นครั้งเดียวเฉพาะเมื่อยังอ่านความถี่ไม่ได้
        // จำนวนต้องเป็น "1 ครั้ง" ทั้งช่องเท่านั้น — "11 ครั้ง", "วันละ 1 ครั้ง", "1 ครั้ง/วัน" ไม่ใช่ครั้งเดียว (รีวิว 2569-09-30, 10-01)
        if (preg_match('/\bstat\b/', $all)
            || ($sig['freq'] === '' && preg_match('/^\s*1\s*ครั้ง\s*$/u', $amount))) { $sig['freq'] = 'STAT'; }
        if (preg_match('/\bprn\b|on demand/', $all)) { $sig['freq'] = 'PRN'; }

        if (preg_match('/\bim\b/', $route))            { $sig['route'] = 'IM'; }
        elseif (preg_match('/\biv\b/', $route))        { $sig['route'] = 'IV'; }
        elseif (preg_match('/\bsc\b/', $route))        { $sig['route'] = 'SC'; }
        elseif (preg_match('/\bid\b/', $route))        { $sig['route'] = 'ID'; }
        elseif (preg_match('/vg|vagin/', $route))      { $sig['route'] = 'PV'; }
        elseif (preg_match('/eye/', $route))           { $sig['route'] = 'EYE'; }
        elseif (preg_match('/apply/', $route))         { $sig['route'] = 'TOP'; }
        elseif (preg_match('/oral|\bo\b|\bpo\b|รับประทาน/u', $all)) { $sig['route'] = 'PO'; }

        if (preg_match('/\bac\b/', $all))     { $sig['timing'] = 'ac'; }
        elseif (preg_match('/\bpc\b/', $all)) { $sig['timing'] = 'pc'; }
        if (preg_match('/\bhs\b/', $all) && $sig['timing'] === '') { $sig['timing'] = 'hs'; }

        // "7days" / "3 days" / "5-7days" (เอาค่าน้อยไว้ก่อน หมอแก้เพิ่มเอง)
        if (preg_match('/(\d+)(?:\s*-\s*\d+)?\s*(?:days?|วัน)/u', $amount, $m)) { $sig['days'] = $m[1]; }
        // "14 tabs" กับ 1*2 → ย้อนคิดได้ว่า 7 วัน (เฉพาะที่หารลงตัว)
        elseif (preg_match('/^(\d+)\s*(tab|tabs|caps?|เม็ด)?$/u', $amount, $m) && $sig['qty'] !== ''
                && ($per = self::$freq[$sig['freq']][1] ?? null) >= 1) {
            $d = (float) $m[1] / ((float) $sig['qty'] * $per);
            if ($d >= 1 && abs($d - round($d)) < 1e-9) { $sig['days'] = (string) (int) round($d); }
        }

        // ยังไม่รู้วิธีให้ยา → เดาจากหน่วยในคลัง (คลินิกขอ 2569-10-12: ยากินก็กิน ยาฉีดก็ฉีด)
        // เม็ด/แคปซูล/ซอง/ขวด(ยาน้ำ)/มล./ช้อนชา = รับประทาน · vial/ampule = ฉีดเข้ากล้าม · หลอด/หยด/dose ไม่เดา
        if ($sig['route'] === '') {
            $u = trim((string) $unit);
            if (in_array($u, ['เม็ด', 'แคปซูล', 'ซอง', 'ขวด', 'มล.', 'ช้อนชา'], true)) { $sig['route'] = 'PO'; }
            elseif (in_array(strtolower($u), ['vial', 'ampule', 'amp'], true)) { $sig['route'] = 'IM'; }
        }

        // คลังยาไม่ได้ระบุหน่วยเลย ถึงจะถือว่าเป็นเม็ด (ยาน้ำหน่วยเป็น "ขวด" ห้ามกลายเป็นเม็ด)
        if ($sig['unit'] === '' && trim((string) $unit) === '' && $sig['route'] === 'PO' && $sig['qty'] !== '') { $sig['unit'] = 'เม็ด'; }

        return $sig;
    }
}
