<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ประวัติวัคซีนที่นำเข้าจาก Medivel มีราว 25% ที่ทะเบียนเก่าไม่ได้บันทึกวันที่ไว้
 * ยังต้องเก็บไว้ (รู้ว่าเคยฉีดอะไร มี lot อะไร) แค่คำนวณเข็มถัดไปไม่ได้
 * จึงยอมให้ vaccinated_date ว่างได้ แทนที่จะทิ้งประวัติหรือกรอกวันที่มั่ว
 */
return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE `visit_vaccines` MODIFY `vaccinated_date` DATE NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE `visit_vaccines` MODIFY `vaccinated_date` DATE NOT NULL");
    }
};
