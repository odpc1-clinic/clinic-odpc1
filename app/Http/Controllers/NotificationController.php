<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * แถบแจ้งเตือนความเคลื่อนไหวคนไข้ (เลื่อนลงมาใต้เมนู แล้วหายเอง)
 *
 * ไม่ได้อ่านจาก activity_logs เพราะ Visit ไม่ได้เปิด audit ไว้ (และถ้าเปิด log จะบวมจากการ Save ปกติ)
 * อ่านจากสถานะจริงของ visits แทน: created_at = คนไข้ใหม่ · room_entered_at = ย้ายห้อง
 * ทั้งสองคอลัมน์ระบบเขียนอยู่แล้ว และมี index (migration 2026_08_03_090000)
 *
 * ความลับคนไข้: ส่งออกแค่ HN + เลขคิว + ชื่อห้อง — ไม่มีชื่อ-สกุล (คลินิก STI/HIV มีคนเดินผ่านหน้าจอ)
 */
class NotificationController extends Controller
{
    /** ดูย้อนหลังไกลสุดเท่านี้ กันเปิดหน้าทิ้งไว้ข้ามคืนแล้วเด้งรัวตอนกลับมา */
    private const MAX_LOOKBACK_MIN = 10;

    public function recent(Request $request)
    {
        // ครั้งแรกที่เปิดหน้า ไม่ต้องเด้งย้อนหลัง — ส่ง cursor ปัจจุบันกลับไปเฉยๆ
        $since = trim((string) $request->query('since', ''));
        if ($since === '') {
            return response()->json(['now' => now()->toDateTimeString(), 'events' => []]);
        }

        try {
            $sinceAt = \Carbon\Carbon::parse($since);
        } catch (\Throwable $e) {
            return response()->json(['now' => now()->toDateTimeString(), 'events' => []]);
        }

        $floor = now()->subMinutes(self::MAX_LOOKBACK_MIN);
        if ($sinceAt->lt($floor)) { $sinceAt = $floor; }

        $rows = DB::table('visits as v')
            ->join('patients as p', 'p.id', '=', 'v.patient_id')
            ->where(function ($q) use ($sinceAt) {
                $q->where('v.created_at', '>', $sinceAt)
                  ->orWhere('v.room_entered_at', '>', $sinceAt);
            })
            ->orderBy(DB::raw('GREATEST(COALESCE(v.room_entered_at, v.created_at), v.created_at)'))
            ->limit(12)
            ->get(['v.id', 'v.queue', 'v.status', 'v.clinic', 'v.created_at', 'v.room_entered_at', 'p.code']);

        // ไม่มีป็อปอัพ "ผลแล็บออกแล้ว" อีกแล้ว (ผู้ใช้ขอเอาออก 2569-08-11)
        // พยาบาลอาวุโสมองป็อปอัพที่เด้งแล้วหายไม่ทัน — ย้ายไปเป็นป้ายค้างหลังชื่อคนไข้ในหน้า Room แทน
        // (VisitController::room() -> withExists('labs as lab_approved'), room.blade.php)
        $events = [];

        foreach ($rows as $r) {
            // visit เพิ่งถูกสร้าง = คนไข้ใหม่ (ถือว่า "ใหม่" เมื่อ created_at เป็นตัวที่ขยับ)
            $isNew  = $r->created_at && \Carbon\Carbon::parse($r->created_at)->gt($sinceAt);
            $status = (int) $r->status;
            $room   = Visit::$statusOption[$status] ?? 'ห้องตรวจ';

            // ไม่แจ้ง "กลับบ้านแล้ว" (ผู้ใช้ขอเอาออก 2569-08-06)
            // เป็นการปิดเคส ไม่มีใครต้องทำอะไรต่อ แจ้งไปก็มีแต่รบกวน
            if (!$isNew && $status === \App\Enums\VisitStatus::Home->value) { continue; }

            $type = $isNew ? 'new' : 'move';

            $events[] = [
                'id'    => substr($type, 0, 1) . $r->id . '-' . strtotime((string) ($isNew ? $r->created_at : $r->room_entered_at)),
                'type'  => $type,
                // ชื่อห้องขึ้นก่อน ให้ห้องที่ต้องรับคนไข้มองแวบเดียวรู้ว่าเป็นของตัวเอง (คลินิกขอ 2569-11-02)
                // เดิมขึ้นคิว/HN ก่อน ชื่อห้องอยู่ท้ายสุด ข้อความยาวแล้วโดนตัดเป็น "…" พอดีตรงชื่อห้อง
                'room'  => $room,
                'text'  => $room . ' ← ' . ($isNew ? 'คนไข้ใหม่ ' : '') . $this->who($r),
                'clinic' => Visit::$ClinicShortName[(int) $r->clinic] ?? '',
                'color'  => Visit::$ClinicColor[(int) $r->clinic] ?? '#0d6efd',
                'url'    => route('visit.edit', ['visit' => $r->id, 'page' => 2]),
            ];
        }

        // ไม่มีป็อปอัพ "รอนาน" แล้ว (ผู้ใช้ขอเอาออก 2569-08-07)
        // คนไข้ที่รอนานเป็น "สถานะค้าง" ไม่ใช่เหตุการณ์ ณ วินาทีใด ป็อปอัพจึงเด้งซ้ำเรื่อยๆ กวนสายตา
        // ย้ายไปแสดงเป็นสีแถว + ป้ายจำนวนนาทีในหน้า Room (room.blade.php) แทน
        // ซึ่งเห็นตลอดเวลาและไล่สีเองตามเวลาจริงทุกครั้งที่ตารางรีเฟรช
        return response()->json([
            'now'    => now()->toDateTimeString(),
            'events' => $events,
        ]);
    }

    /** ระบุตัวคนไข้แบบไม่เปิดเผยชื่อ */
    private function who($r): string
    {
        $q = trim((string) $r->queue);
        return ($q !== '' ? 'คิว ' . $q . ' · ' : '') . 'HN ' . $r->code;
    }
}
