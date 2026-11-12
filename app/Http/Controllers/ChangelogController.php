<?php

namespace App\Http\Controllers;

/**
 * หน้า "มีอะไรใหม่" — บันทึกการเปลี่ยนแปลงระบบให้พยาบาลตามทัน
 * เนื้อหาอยู่ที่ resources/changelog.php (ดูวิธีเพิ่มในหัวไฟล์นั้น)
 */
class ChangelogController extends Controller
{
    /** อ่านไฟล์ changelog แล้วเรียงใหม่ -> เก่า ; คืน [] ถ้าไฟล์หาย จะได้ไม่ทำหน้าเว็บพัง */
    public static function entries(): array
    {
        $path = resource_path('changelog.php');
        if (!is_file($path)) { return []; }

        $rows = require $path;
        if (!is_array($rows)) { return []; }

        usort($rows, fn ($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
        return $rows;
    }

    /** วันที่ของรายการล่าสุด ใช้เทียบกับที่ผู้ใช้เคยอ่าน (เก็บใน localStorage ของเครื่องนั้น) */
    public static function latestDate(): string
    {
        return self::entries()[0]['date'] ?? '';
    }

    public function index()
    {
        return view('changelog.index', [
            'entries'    => self::entries(),
            'latestDate' => self::latestDate(),
        ]);
    }
}
