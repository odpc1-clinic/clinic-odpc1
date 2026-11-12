<?php

namespace App\Support;

use App\Models\Patient;
use App\Models\User;

/**
 * ใบผล Lab เลือกภาษาไทย / อังกฤษได้ (ห้อง Lab ขอ 2569-10-16)
 *
 * th = แบบเดิมทุกอย่าง (ชื่อไทย · วันที่ พ.ศ.)
 * en = ชื่อคนไข้ภาษาอังกฤษจากทะเบียน (patients.name_en) · ชื่อเจ้าหน้าที่ภาษาอังกฤษ (users.name_en) · วันที่ ค.ศ.
 * ไม่มีชื่ออังกฤษ = ใช้ชื่อไทยแทน แล้วบอกในรายการ missing() ให้หน้าจอเตือนก่อนพิมพ์
 */
class LabReportLang
{
    /** คำนำหน้าภาษาอังกฤษตามรหัส patients.prefix · ไม่มีในรายการ = ไม่ใส่ · พิมพ์ใหญ่ทั้งหมดเหมือนหน้าลงทะเบียน (2569-11-10) */
    public const PREFIX_EN = [
        1 => 'MR.', 2 => 'MRS.', 3 => 'MISS', 4 => 'MR.', 5 => 'MRS.', 6 => 'MISS', 7 => 'MS.',
        8 => 'MASTER', 9 => 'MISS', 10 => 'VEN.', 11 => 'NOVICE', 12 => 'MAE CHEE', 13 => 'MASTER', 14 => 'MX.',
        15 => 'DR.', 16 => 'DR.',
    ];

    public static function lang(?string $lang): string
    {
        return $lang === 'en' ? 'en' : 'th';
    }

    public static function hasEnglishName($patient): bool
    {
        return $patient && trim((string) ($patient->name_en ?? '')) !== '';
    }

    public static function patientName($patient, string $lang): string
    {
        if (! $patient) { return ''; }
        if ($lang === 'en' && self::hasEnglishName($patient)) {
            $pfx = self::PREFIX_EN[(int) ($patient->prefix ?? 0)] ?? '';
            return trim($pfx . ' ' . trim($patient->name_en . ' ' . ($patient->surname_en ?? '')));
        }
        return trim(Patient::prefixLabel($patient) . ' ' . ($patient->name ?? '') . ' ' . ($patient->surname ?? ''));
    }

    public static function staffName($userId, string $lang): string
    {
        $u = $userId ? User::find($userId) : null;
        if (! $u) { return ''; }
        if ($lang === 'en' && trim((string) ($u->name_en ?? '')) !== '') { return trim($u->name_en); }
        return (string) $u->name;
    }

    /** วันที่ในฐาน (พ.ศ. Y-m-d · บางแถวเป็น d/m/Y หรือ ค.ศ.) → th: วว/ดด/ปปปป พ.ศ. · en: dd/mm/yyyy ค.ศ. */
    public static function date($value, string $lang, ?string $time = null): string
    {
        $v = trim((string) $value);
        if ($v === '' || $v === '0000-00-00') { return ''; }
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $v, $m)) { [$y, $mo, $d] = [(int) $m[1], $m[2], $m[3]]; }
        elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $v, $m)) { [$y, $mo, $d] = [(int) $m[3], sprintf('%02d', $m[2]), sprintf('%02d', $m[1])]; }
        else { return $v . ($time ? ' ' . $time : ''); }
        if ($lang === 'en' && $y > 2400) { $y -= 543; }
        if ($lang === 'th' && $y < 2400) { $y += 543; }
        $t = trim((string) $time);
        return $d . '/' . $mo . '/' . $y . ($t !== '' ? ' ' . substr($t, 0, 5) : '');
    }

    public static function age($patient, string $lang): string
    {
        if (! $patient) { return ''; }
        $a = (string) $patient->age();
        return $lang === 'en' && preg_match('/\d+/', $a, $m) ? $m[0] . ' years' : $a;
    }

    /** สิ่งที่ยังไม่มีภาษาอังกฤษ (ใบอังกฤษจะใช้ภาษาไทยแทน) — ให้หน้าจอเตือน */
    public static function missing($patient, array $userIds): array
    {
        $out = [];
        if ($patient && ! self::hasEnglishName($patient)) { $out[] = 'ชื่อคนไข้'; }
        foreach (array_unique(array_filter($userIds)) as $id) {
            $u = User::find($id);
            if ($u && trim((string) ($u->name_en ?? '')) === '') { $out[] = 'ชื่อ ' . $u->name; }
        }
        return $out;
    }
}
