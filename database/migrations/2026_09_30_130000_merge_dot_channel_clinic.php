<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ช่องทาง DOT "ศูนย์บริการฯ" กับ "มาที่คลินิก" คือที่เดียวกัน (ศูนย์บริการเวชศาสตร์ป้องกัน)
 * คลินิกขอให้เหลือตัวเดียว (2569-09-30) — ย้ายค่าเดิมเป็น "มาที่คลินิก"
 */
class MergeDotChannelClinic extends Migration
{
    public function up()
    {
        DB::table('tb_registrations')->where('dot_channel', 'ศูนย์บริการฯ')->update(['dot_channel' => 'มาที่คลินิก']);
    }

    public function down()
    {
        // ย้อนไม่ได้ — ไม่รู้ว่าแถวไหนเดิมเป็นคำไหน และความหมายเหมือนกันอยู่แล้ว
    }
}
