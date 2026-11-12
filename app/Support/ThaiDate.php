<?php

namespace App\Support;

/**
 * ตัวอ่านวันที่แบบเข้มงวดของงานวัณโรค (บัตร TB 01 / ส่วน TB ในหน้าตรวจ) — 2569-10-05
 *
 * ทำไมไม่ใช้ Utillity::th2dbDate: มันใช้ DateTime::createFromFormat('d/m/Y') ซึ่ง "ล้น" วันที่ผิดให้เงียบ ๆ
 * (08/23/2027 กลายเป็น 2028-11-08, 31/02 กลายเป็นต้นมีนาคม) และรับปีอะไรก็ได้
 * บัตร TB จึงมีวันที่ขยะ เช่น 2026-01-09 (ปี ค.ศ.), 2572-04-08 (อนาคต) ทำให้รายงานเพี้ยน
 * คลินิกขอให้บังคับรูปแบบเดียว: วว/ดด/ปปปป พ.ศ. · พิมพ์ ค.ศ. 4 หลักมา = แปลงให้อัตโนมัติ
 *
 * ไม่แตะ th2dbDate (ส่วนอื่นของระบบ STI/TMC ยังพึ่งพฤติกรรมเดิม) — ใช้ตัวนี้เฉพาะเส้นทางบันทึกของงาน TB
 * กติกาเดียวกันมีฝั่งเบราว์เซอร์ใน resources/views/tb/_date_input_js.blade.php — แก้ที่หนึ่งต้องแก้อีกที่
 */
class ThaiDate
{
    /** ปีที่รับได้: ย้อนหลัง 30 ปี (ประวัติรักษาเดิม) ถึงล่วงหน้า 2 ปี (นัด/สิ้นสุดสูตร) นับจากปี พ.ศ. ปัจจุบัน */
    public const YEARS_BACK  = 30;
    public const YEARS_AHEAD = 2;

    /**
     * @param string|null $input ข้อความที่พิมพ์มา
     * @param array $opts allowEmpty (bool, ค่าเริ่ม false) · notFuture (bool) · today ('Y-m-d' พ.ศ. ใช้ตอนทดสอบ)
     * @return array ['ok'=>bool, 'be'=>'Y-m-d' พ.ศ.|null, 'error'=>ข้อความไทย|null, 'converted'=>bool ปี ค.ศ. ถูกแปลง]
     */
    public static function parse($input, array $opts = []): array
    {
        $raw = trim((string) $input);
        $fail = fn (string $msg) => ['ok' => false, 'be' => null, 'error' => $msg, 'converted' => false];

        if ($raw === '') {
            return ! empty($opts['allowEmpty'])
                ? ['ok' => true, 'be' => null, 'error' => null, 'converted' => false]
                : $fail('กรุณากรอกวันที่ (วว/ดด/ปปปป พ.ศ.)');
        }

        $today = (string) ($opts['today'] ?? self::todayBe());
        $nowY  = (int) substr($today, 0, 4);
        $example = 'เช่น ' . substr($today, 8, 2) . '/' . substr($today, 5, 2) . '/' . $nowY;

        // ตัวคั่น / - . และเว้นวรรครอบตัวคั่น — พยาบาลคัดลอกจาก Excel/ใบแล็บมาหลายแบบ
        if (! preg_match('#^(\d{1,2})\s*[/\-.]\s*(\d{1,2})\s*[/\-.]\s*(\d+)$#', $raw, $m)) {
            return $fail('"' . $raw . '" ไม่ใช่รูปแบบ วว/ดด/ปปปป ' . $example);
        }
        [$d, $mo, $yStr] = [(int) $m[1], (int) $m[2], $m[3]];

        // ปี 2 หลัก เดาไม่ได้ว่า 69 = 2569 หรือ 1969 / 2069 — ให้พิมพ์ครบ
        if (strlen($yStr) !== 4) {
            return $fail('"' . $raw . '" ปีต้องพิมพ์ครบ 4 หลัก (พ.ศ.) ' . $example);
        }
        $y = (int) $yStr;
        $converted = false;
        if ($y >= 2400) {
            // พ.ศ. อยู่แล้ว
        } elseif ($y >= 1900) {
            $y += 543;              // พิมพ์ ค.ศ. มา — คลินิกขอให้แปลงให้เอง
            $converted = true;
        } else {
            return $fail('"' . $raw . '" ปี ' . $yStr . ' ไม่ถูกต้อง ' . $example);
        }

        if ($mo < 1 || $mo > 12) {
            // เดือนเกิน 12 ส่วนใหญ่มาจากปฏิทินที่ออกเป็น ดด/วว (แบบอเมริกัน)
            return $fail('"' . $raw . '" เดือนต้องเป็น 1-12 (พิมพ์ ' . $mo . ' มา — อาจสลับวันกับเดือน)');
        }
        // ตรวจด้วยปี ค.ศ. — ปีอธิกสุรทินนับตาม ค.ศ. (checkdate(2, 29, 2567) จะว่าไม่มี ทั้งที่ 29/02/2567 มีจริง)
        if (! checkdate($mo, $d, $y - 543)) {
            return $fail('"' . $raw . '" ไม่ใช่วันที่ที่มีจริง (เดือน ' . $mo . ' ไม่มีวันที่ ' . $d . ')');
        }
        if ($y < $nowY - self::YEARS_BACK || $y > $nowY + self::YEARS_AHEAD) {
            return $fail('"' . $raw . '" ปี ' . $y . ' อยู่นอกช่วงที่รับได้ (' . ($nowY - self::YEARS_BACK) . '-' . ($nowY + self::YEARS_AHEAD) . ')');
        }

        $be = sprintf('%04d-%02d-%02d', $y, $mo, $d);

        // วันที่ผล/เก็บตัวอย่าง/จ่ายยา เป็นเหตุการณ์ที่เกิดแล้ว — ผ่อนให้ 1 วัน (เครื่องลูกข่ายนาฬิกาเพี้ยน/คาบเที่ยงคืน)
        if (! empty($opts['notFuture'])) {
            $limit = self::addDaysBe($today, 1);
            if ($be > $limit) {
                return $fail('"' . $raw . '" เป็นวันในอนาคต (' . self::show($be) . ') — ช่องนี้ต้องเป็นวันที่เกิดขึ้นแล้ว');
            }
        }

        return ['ok' => true, 'be' => $be, 'error' => null, 'converted' => $converted];
    }

    /** 'Y-m-d' (พ.ศ. หรือ ค.ศ. เก่า) -> วว/ดด/ปปปป พ.ศ. สำหรับแสดงผล — ปี < 2500 บวก 543 ก่อน (ข้อมูลยุคแรก) */
    public static function show($ymd): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $ymd, $m)) { return (string) $ymd; }
        $y = (int) $m[1];
        return $m[3] . '/' . $m[2] . '/' . ($y < 2500 ? $y + 543 : $y);
    }

    public static function todayBe(): string
    {
        return (now()->year + 543) . now()->format('-m-d');
    }

    private static function addDaysBe(string $be, int $days): string
    {
        $y = (int) substr($be, 0, 4) - 543;
        $ad = \Carbon\Carbon::create($y, (int) substr($be, 5, 2), (int) substr($be, 8, 2))->addDays($days);
        return ($ad->year + 543) . $ad->format('-m-d');
    }
}
