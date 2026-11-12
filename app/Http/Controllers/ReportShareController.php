<?php

namespace App\Http\Controllers;

use App\Models\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * หน้าคุมลิงก์ลับของแดชบอร์ดสถิติ — เปิดก่อนประชุม ปิดหลังประชุม
 *
 * ค่าที่คุม (เก็บใน configs):
 *   public_report_link_enabled     สวิตช์หลัก เปิด/ปิด
 *   public_report_link_expires_at  วันเวลาหมดอายุ (เว้นว่าง = ไม่หมดอายุ)
 *   public_report_token            ตัวลิงก์ลับเอง กดสร้างใหม่ได้ ลิงก์เก่าจะตายทันที
 */
class ReportShareController extends Controller
{
    /** ตัวเลือกระยะเวลาที่ให้กดเลือกหน้าจอ (ชั่วโมง) — null = ไม่หมดอายุ */
    public const DURATIONS = [
        '3'   => 'อีก 3 ชั่วโมง',
        '8'   => 'วันนี้ทั้งวัน (8 ชั่วโมง)',
        '24'  => 'อีก 1 วัน',
        '72'  => 'อีก 3 วัน',
        '168' => 'อีก 7 วัน',
        ''    => 'ไม่กำหนด (เปิดจนกว่าจะกดปิดเอง)',
    ];

    public function index()
    {
        $token   = trim((string) Config::where('name', 'public_report_token')->value('value'));
        $enabled = Config::isEnabled('public_report_link_enabled');
        $expires = trim((string) Config::where('name', 'public_report_link_expires_at')->value('value'));

        $expiresAt = $expires !== '' ? \Carbon\Carbon::parse($expires) : null;
        $isExpired = $expiresAt && now()->greaterThan($expiresAt);
        $liveUrl   = $token !== '' ? route('report.yearly') . '?key=' . $token : null;

        return view('report.share', [
            'token'     => $token,
            'enabled'   => $enabled,
            'expiresAt' => $expiresAt,
            'isExpired' => $isExpired,
            'isLive'    => $enabled && !$isExpired && $token !== '',
            'liveUrl'   => $liveUrl,
            'durations' => self::DURATIONS,
        ]);
    }

    public function update(Request $request)
    {
        $action = $request->input('action');

        if ($action === 'off') {
            Config::setValue('public_report_link_enabled', '0');
            return back()->with('flash_message', 'ปิดลิงก์เรียบร้อยแล้ว — ลิงก์เดิมเปิดไม่ได้อีก');
        }

        if ($action === 'regenerate') {
            Config::setValue('public_report_token', Str::random(40));
            Config::setValue('public_report_link_enabled', '0');   // สร้างใหม่แล้วปิดไว้ก่อน ให้กดเปิดเองอีกที
            return back()->with('flash_message', 'สร้างลิงก์ใหม่แล้ว ลิงก์เก่าใช้ไม่ได้อีก — กดเปิดใช้งานเมื่อพร้อม');
        }

        // action = on
        if (trim((string) Config::where('name', 'public_report_token')->value('value')) === '') {
            Config::setValue('public_report_token', Str::random(40));
        }

        $hours = (string) $request->input('hours', '3');
        $expiresAt = ($hours !== '' && is_numeric($hours))
            ? now()->addHours((int) $hours)->format('Y-m-d H:i:s')
            : '';

        Config::setValue('public_report_link_expires_at', $expiresAt);
        Config::setValue('public_report_link_enabled', '1');

        $msg = $expiresAt !== ''
            ? 'เปิดลิงก์แล้ว จะปิดเองอัตโนมัติเวลา ' . \Carbon\Carbon::parse($expiresAt)->format('H:i น. วันที่ d/m/') . (\Carbon\Carbon::parse($expiresAt)->year + 543)
            : 'เปิดลิงก์แล้ว (ไม่กำหนดวันหมดอายุ — อย่าลืมกลับมากดปิด)';

        return back()->with('flash_message', $msg);
    }
}
