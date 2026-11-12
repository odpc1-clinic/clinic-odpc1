<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * สลับหน้าที่ 2 ช่อง ตามที่พยาบาลขอ
 *
 * ตอนแรกทำไว้กลับด้าน: appointment_reason = เจ้าหน้าที่, ช่องใหม่ = คนไข้
 * แต่ของจริงคนละอย่าง — พยาบาลใช้ appointment_reason คู่กับตัวเลือกสำเร็จรูป
 * (ติดตามผลการรักษา / รับยาตามนัด / ฟังผลเลือด) ซึ่งคนไข้อ่านได้อยู่แล้ว
 * ส่วนช่องใหม่ที่เพิ่งเปิดให้ พยาบาลกลับเอาไปเขียนส่งเวรกันทันทีภายในไม่กี่ชั่วโมง
 * ("รบกวนส่ง HBsAb ให้หน่อยค่ะ คนไข้ชำระเงินไว้แล้ว")
 *
 * เอาตามที่คนใช้งานจริงทำ ดีกว่าดันให้เขาพิมพ์ของเดิมใหม่ทั้งหมด 7,515 ใบ
 * -> appointment_reason      = คนไข้เห็น (ใบนัด + LINE)
 * -> appointment_staff_note  = เจ้าหน้าที่เท่านั้น
 *
 * เปลี่ยนชื่อ ไม่ใช่ลบทิ้งแล้วสร้างใหม่ เพราะ 2 แถวที่มีอยู่เป็นข้อความเจ้าหน้าที่
 * พอเปลี่ยนชื่อก็ไปอยู่ถูกช่องพอดี ไม่ต้องย้ายข้อมูลเอง
 * ใช้ SQL ตรงๆ เพราะ renameColumn ของ Laravel 8 ต้องลง doctrine/dbal เพิ่ม
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('visits', 'appointment_patient_note')
            && !Schema::hasColumn('visits', 'appointment_staff_note')) {
            DB::statement('ALTER TABLE `visits`
                CHANGE `appointment_patient_note` `appointment_staff_note` VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('visits', 'appointment_staff_note')
            && !Schema::hasColumn('visits', 'appointment_patient_note')) {
            DB::statement('ALTER TABLE `visits`
                CHANGE `appointment_staff_note` `appointment_patient_note` VARCHAR(255) NULL');
        }
    }
};
