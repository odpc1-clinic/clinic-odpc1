<?php

namespace App\Console\Commands;

use App\Models\Patient;
use Illuminate\Console\Command;

/**
 * ล้างการผูก LINE ของคนไข้ทั้งหมด — ใช้ตอน "ย้าย LINE OA"
 *
 * จำเป็นเพราะ LINE ออก User ID แยกคนละชุดต่อ OA:
 * ID ที่ผูกไว้กับ OA เดิม ใช้ส่งข้อความผ่าน OA ใหม่ไม่ได้เลย (ส่งไปก็ error/เงียบหาย)
 * ถ้าไม่ล้าง ระบบจะคิดว่าคนไข้ยังผูกอยู่ แล้วพยายามส่งไปยัง ID ที่ใช้ไม่ได้ตลอดไป
 *
 * รันหลังเปลี่ยน LINE_CHANNEL_ACCESS_TOKEN/SECRET ใน .env แล้วเท่านั้น
 */
class LineResetLinks extends Command
{
    protected $signature = 'line:reset-links {--force : ข้ามการถามยืนยัน}';

    protected $description = 'ล้างการผูกบัญชี LINE ของคนไข้ทั้งหมด (ใช้ตอนย้าย LINE OA — คนไข้ต้องผูกใหม่)';

    public function handle()
    {
        $linked = Patient::whereNotNull('line_user_id')->count();

        if ($linked === 0) {
            $this->info('ไม่มีคนไข้ที่ผูก LINE อยู่ — ไม่ต้องล้างอะไร');
            return 0;
        }

        $this->warn("มีคนไข้ผูก LINE อยู่ {$linked} ราย");
        $this->line('การล้างจะทำให้ทุกคนต้องขอรหัสจากเจ้าหน้าที่แล้วผูกใหม่');

        if (!$this->option('force') && !$this->confirm('ยืนยันล้างการผูกทั้งหมด?')) {
            $this->info('ยกเลิก');
            return 0;
        }

        // เคลียร์รหัสผูกที่ค้างด้วย กันคนไข้เอารหัสเก่าที่ยังไม่หมดอายุมาใช้ข้าม OA
        $n = Patient::whereNotNull('line_user_id')->update([
            'line_user_id'   => null,
            'line_linked_at' => null,
        ]);
        Patient::whereNotNull('line_link_code')->update([
            'line_link_code'             => null,
            'line_link_code_expires_at'  => null,
        ]);

        $this->info("ล้างแล้ว {$n} ราย — แจ้งเจ้าหน้าที่ให้ออกรหัสผูกใหม่ให้คนไข้ได้เลย");
        return 0;
    }
}
