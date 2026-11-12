<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * สัญญาณ "มีอะไรเปลี่ยน" ส่งให้ทุกจอที่เปิดอยู่ผ่าน Reverb (2569-10-20)
 *
 * ส่งแค่รหัส (visit id / ห้อง / lab id) ไม่มีชื่อหรือผลตรวจ — จอที่ได้รับไปดึงข้อมูลเองผ่านหน้าเดิมที่มีสิทธิ์ตรวจอยู่แล้ว
 * ช่องทางเป็น private ต้องล็อกอินก่อนถึงฟังได้ (routes/channels.php)
 */
class ClinicSignal implements ShouldBroadcastNow
{
    public function __construct(public string $type, public array $data = [])
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('clinic');
    }

    public function broadcastAs(): string
    {
        return 'signal';
    }
}
