<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * patients.id_card_number เป็น NOT NULL + UNIQUE
 * คนไข้ TMC ส่วนใหญ่เป็นชาวต่างชาติที่ไม่มีเลขบัตรประชาชนไทย จึงลงค่าว่างไม่ได้เกิน 1 คน
 * (unique index มองค่าว่างเป็นค่าซ้ำ แต่ยอมให้ NULL ซ้ำกันได้)
 *
 * เปลี่ยนเป็น nullable แทน — ยังกันเลขบัตรซ้ำเหมือนเดิมสำหรับคนที่มีเลขบัตร
 * ใช้ raw SQL เพราะ Laravel 8 ต้องพึ่ง doctrine/dbal ในการ ->change()
 */
return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE `patients` MODIFY `id_card_number` VARCHAR(255) NULL");
        // แถวที่เคยเป็นค่าว่างให้เป็น NULL เพื่อไม่ให้กินโควตา unique
        DB::table('patients')->where('id_card_number', '')->update(['id_card_number' => null]);
    }

    public function down()
    {
        // ย้อนกลับได้ก็ต่อเมื่อมีคนไข้ที่ไม่มีเลขบัตรไม่เกิน 1 คน
        // (ถ้ามากกว่านั้น unique index จะไม่ยอมให้ค่าว่างซ้ำ)
        $n = DB::table('patients')->whereNull('id_card_number')->count();
        if ($n > 1) {
            throw new \RuntimeException("ย้อนกลับไม่ได้: มีคนไข้ $n คนที่ไม่มีเลขบัตร ต้องจัดการก่อน");
        }
        DB::table('patients')->whereNull('id_card_number')->update(['id_card_number' => '']);
        DB::statement("ALTER TABLE `patients` MODIFY `id_card_number` VARCHAR(255) NOT NULL");
    }
};
