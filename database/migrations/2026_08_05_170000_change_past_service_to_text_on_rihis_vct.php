<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ประวัติการตรวจ HIV: เปลี่ยนจากรหัสตัวเลข (dropdown) เป็นข้อความ
 * พี่อิ๋วขอให้ดึงเป็นข้อความมาเลย ไม่ต้องเลือกจาก dropdown (2569-08-05)
 *
 * ตอนส่งออก Excel ยังแปลงกลับเป็นรหัส 0/1/2 ให้ตามฟอร์มส่วนกลางเหมือนเดิม
 * (ดู RihisVctController::pastServiceCode)
 */
class ChangePastServiceToTextOnRihisVct extends Migration
{
    public function up()
    {
        // ใช้ SQL ตรง ๆ เพราะ doctrine/dbal ไม่ได้ติดตั้งในโปรเจกต์นี้ ($table->change() จึงใช้ไม่ได้)
        DB::statement('ALTER TABLE rihis_vct MODIFY past_service VARCHAR(60) NULL');

        // แปลงค่าที่ดึงไว้แล้วให้เป็นข้อความ ไม่งั้นจะเหลือเลข 0/1/2 ลอย ๆ อ่านไม่รู้เรื่อง
        foreach ([
            '0' => 'ไม่เคยตรวจในชีวิต',
            '1' => 'ใหม่ (ครั้งแรกในรอบปี)',
            '2' => 'เก่า (ครั้งที่ 2 ขึ้นไปในรอบปี)',
        ] as $code => $text) {
            DB::table('rihis_vct')->where('past_service', $code)->update(['past_service' => $text]);
        }
    }

    public function down()
    {
        foreach ([
            'ไม่เคยตรวจในชีวิต'          => 0,
            'ใหม่ (ครั้งแรกในรอบปี)'       => 1,
            'เก่า (ครั้งที่ 2 ขึ้นไปในรอบปี)' => 2,
        ] as $text => $code) {
            DB::table('rihis_vct')->where('past_service', $text)->update(['past_service' => $code]);
        }

        DB::statement('ALTER TABLE rihis_vct MODIFY past_service TINYINT UNSIGNED NULL');
    }
}
