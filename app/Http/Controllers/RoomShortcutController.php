<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use Illuminate\Http\Request;

/**
 * หน้ารวมปุ่มลัดเข้าห้องต่างๆ — ทำไว้ให้ติดตั้งที่เครื่องลูกข่าย
 * เปิดหน้านี้หน้าเดียวแล้วกดเข้าห้องที่เครื่องนั้นรับผิดชอบ หรือโหลดไฟล์ .url ไปวางบนเดสก์ท็อปก็ได้
 */
class RoomShortcutController extends Controller
{
    /**
     * รายชื่อห้องทั้งหมด — แหล่งเดียว ใช้ทั้งหน้าปุ่มและไฟล์ shortcut
     * status = สถานะใน visits (ใช้นับคิวค้าง) ถ้าเป็น null คือหน้าที่ไม่ได้ผูกกับคิว
     */
    public static function rooms(): array
    {
        return [
            'register' => [
                'name' => 'ลงทะเบียน #1', 'icon' => 'fa-user-plus', 'color' => '#0d6efd',
                'url' => route('patient.index'), 'status' => null,
            ],
            'male' => [
                'name' => 'ห้องตรวจชาย #4', 'icon' => 'fa-stethoscope', 'color' => '#1d4ed8',
                'url' => route('visit.room', ['status' => 2]), 'status' => 2,
            ],
            'female' => [
                'name' => 'ห้องตรวจหญิง #5', 'icon' => 'fa-user-doctor', 'color' => '#be185d',
                'url' => route('visit.room', ['status' => 8]), 'status' => 8,
            ],
            'blood' => [
                'name' => 'ห้องเจาะเลือด/ฉีดยา #6', 'icon' => 'fa-syringe', 'color' => '#b91c1c',
                'url' => route('visit.room', ['status' => 3]), 'status' => 3,
            ],
            'tb' => [
                'name' => 'ห้องตรวจวัณโรค #2', 'icon' => 'fa-lungs', 'color' => '#b45309',
                'url' => route('visit.room', ['status' => 10]), 'status' => 10,
            ],
            'tmc' => [
                'name' => 'ห้องตรวจ 7 (TMC)', 'icon' => 'fa-plane-departure', 'color' => '#6d28d9',
                'url' => route('visit.room', ['status' => 7]), 'status' => 7,
            ],
            'finance' => [
                'name' => 'ห้องการเงิน', 'icon' => 'fa-cash-register', 'color' => '#a16207',
                'url' => route('finance.index'), 'status' => 9,
            ],
            'pharmacy' => [
                'name' => 'ห้องยา', 'icon' => 'fa-pills', 'color' => '#047857',
                'url' => route('pharmacy.index'), 'status' => 5,
            ],
            'lab' => [
                'name' => 'ห้อง Lab', 'icon' => 'fa-flask', 'color' => '#0891b2',
                'url' => route('lab.index'), 'status' => null,
            ],
            'queue' => [
                'name' => 'จอเรียกคิว (Terminal)', 'icon' => 'fa-tv', 'color' => '#334155',
                'url' => url('/queue/terminal.php'), 'status' => null,
            ],
            'dashboard' => [
                'name' => 'แดชบอร์ดสถิติ', 'icon' => 'fa-chart-column', 'color' => '#7c3aed',
                'url' => route('report.yearly'), 'status' => null,
            ],
        ];
    }

    public function index()
    {
        $rooms = self::rooms();

        // นับคิวค้างของแต่ละห้อง ให้เห็นตั้งแต่หน้าปุ่มว่าห้องไหนมีงานรอ
        foreach ($rooms as $key => $room) {
            $rooms[$key]['count'] = $room['status'] !== null ? Visit::count($room['status']) : null;
        }

        return view('rooms.index', compact('rooms'));
    }

    /**
     * โหลดไฟล์ .url ของห้องนั้นไปวางบนเดสก์ท็อปเครื่องลูกข่าย
     * ใช้ URL ตามโฮสต์ที่เปิดหน้านี้อยู่ เครื่องลูกข่ายจึงได้ที่อยู่เดียวกับที่ใช้งานจริง
     */
    public function download($key)
    {
        $rooms = self::rooms();
        abort_unless(isset($rooms[$key]), 404);

        $room = $rooms[$key];
        $content = "[InternetShortcut]\r\nURL=" . $room['url'] . "\r\nIconIndex=0\r\n";

        // ชื่อไฟล์เป็นภาษาไทยได้ แต่ต้อง encode ตาม RFC 5987 ไม่งั้นบางเบราว์เซอร์ได้ชื่อเพี้ยน
        $filename = $room['name'] . '.url';

        return response($content, 200, [
            'Content-Type' => 'application/internet-shortcut',
            'Content-Disposition' => "attachment; filename=\"{$key}.url\"; filename*=UTF-8''" . rawurlencode($filename),
        ]);
    }
}
