<?php

namespace App\Support;

use App\Events\ClinicSignal;
use Illuminate\Support\Facades\Log;

/**
 * ส่งสัญญาณให้จอที่เปิดอยู่รีเฟรชทันที (Laravel Reverb) — 2569-10-20
 *
 * ระบบต้องทำงานได้เหมือนเดิมแม้ Reverb ดับ: จอทุกจอยัง poll ตามรอบเดิมเป็นตัวสำรอง
 * ส่งหลังตอบหน้าเว็บเสร็จ (defer) และกลืน error ทั้งหมด — การบันทึกคนไข้ต้องไม่ล้มหรือช้าเพราะส่งสัญญาณไม่ได้
 * รวมสัญญาณซ้ำในคำขอเดียว (บันทึกหน้าตรวจครั้งเดียวอาจ save visit หลายรอบ) ส่งครั้งเดียวต่อ key
 */
class Realtime
{
    private static array $sent = [];

    public static function enabled(): bool
    {
        return config('broadcasting.default') === 'reverb' && config('broadcasting.connections.reverb.key');
    }

    public static function signal(string $type, array $data = []): void
    {
        if (! self::enabled()) { return; }
        $key = $type . ':' . json_encode($data);
        if (isset(self::$sent[$key])) { return; }
        self::$sent[$key] = true;

        $send = function () use ($type, $data) {
            try {
                broadcast(new ClinicSignal($type, $data));
            } catch (\Throwable $e) {
                Log::debug('realtime: ส่งสัญญาณไม่ได้ (จอจะ poll ตามรอบเดิม) — ' . $e->getMessage());
            }
        };
        // คำสั่ง artisan ไม่มีรอบ "หลังตอบหน้าเว็บ" — ส่งเลย
        app()->runningInConsole() ? $send() : defer($send);
    }
}
