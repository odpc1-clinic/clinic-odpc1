<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // แจ้งเตือนนัดหมายทาง LINE เฉพาะคนไข้ที่มีนัดพรุ่งนี้และผูก LINE ไว้แล้ว
        // เช็คซ้ำทุก 30 นาทีตลอดเวลาทำการ (08:30-16:30) แทนที่จะยิงครั้งเดียวตอน 09:00 ตรง
        // เพราะเซิร์ฟเวอร์ปิดตอนกลางคืน ถ้าเปิดเครื่องช้ากว่า 09:00 บางวัน จะได้ไม่พลาดแจ้งเตือนวันนั้นไปเลย
        // (คำสั่งเช็ค reminder_sent_at ก่อนส่งอยู่แล้ว รันซ้ำได้ปลอดภัย ไม่ส่งซ้ำ)
        $schedule->command('reminders:send-appointments')->between('08:30', '16:30')->everyThirtyMinutes();

        // ชีพจรของตัวตั้งเวลา: แตะไฟล์ทุกนาทีเพื่อพิสูจน์ว่า Windows Task ยังเรียก schedule:run อยู่จริง
        // ต้องมีอันนี้เพราะหน้า /line/health เคยเดาจาก "ข้อความล่าสุดที่ส่ง" ซึ่งผิด —
        // ไม่มีคนไข้ผูก LINE ก็ไม่มีข้อความ ทั้งที่ตัวตั้งเวลาทำงานปกติ (เคยขึ้นแดงหลอกมาแล้ว)
        $schedule->call(function () {
            @file_put_contents(storage_path('app/scheduler-heartbeat.txt'), now()->toDateTimeString());
        })->everyMinute()->name('scheduler-heartbeat')->withoutOverlapping();

        // ตรวจช่องโหว่ไลบรารีสัปดาห์ละครั้ง ให้หน้า Dashboard งานพัฒนาแสดงผลล่าสุด (กลุ่ม IT ขอ 2569-10-19)
        // การ์ดเครื่องเซิร์ฟเวอร์ในหน้า /pulse (CPU แรม ดิสก์) — ถ่ายค่าทุกนาที (pulse:check ปกติรันค้าง แต่ Windows ใช้ตัวตั้งเวลาง่ายกว่า)
        // เฝ้าระบบ — ล่ม 2 นาทีติด แจ้ง LINE ผู้ดูแล (ไม่ใช้ฐานข้อมูล ส่งได้แม้ MySQL ดับ)
        $schedule->command('clinic:health-watch')->everyMinute()->withoutOverlapping(5);
        $schedule->command('pulse:check --once')->everyMinute()->withoutOverlapping();
        $schedule->command('clinic:security-audit')->weeklyOn(1, '08:45')->withoutOverlapping();

        // คำขอผูก LINE จากฟอร์มใน LINE — server อยู่วงใน ต้องออกไปดึงจากที่พัก Apps Script เอง
        // (ยังไม่ตั้ง LINE_LINK_RELAY_URL = คำสั่งจบทันทีเงียบ ๆ)
        $schedule->command('line:pull-links')->everyMinute()->withoutOverlapping(5);
        // เหตุการณ์ LINE (เพิ่มเพื่อน/บล็อก/พิมพ์รหัสในแชท) ผ่านที่พักเดียวกัน — แทนทางเชื่อม Cloudflare (2569-10-31)
        // สคริปต์ที่พักรุ่นเก่า (ยังไม่มี webhook) = คำสั่งเตือนแล้วจบ ไม่ทำอะไร
        $schedule->command('line:pull-webhook')->everyMinute()->withoutOverlapping(5);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
