<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHolidaysTable extends Migration
{
    public function up()
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();

            // เก็บเป็น พ.ศ. ในคอลัมน์ DATE ให้ตรงกับ visits.appointment เป๊ะๆ
            // จะได้เทียบตรงๆ ได้โดยไม่ต้องแปลงปีทุกครั้ง (เทียบผิดครั้งเดียว = เตือนคนไข้ผิดวัน)
            // ห้ามโยนค่านี้เข้า Carbon::parse() ตรงๆ เพราะจะได้ ค.ศ. 2569
            $table->date('holiday_date')->unique();

            $table->string('name');

            // annual = วันหยุดราชการประจำปี / substitution = วันหยุดชดเชย
            // special = วันหยุดพิเศษตามมติ ครม. / gov = เฉพาะหน่วยงานราชการ (ธนาคารไม่หยุด)
            $table->string('type', 20)->default('annual');

            $table->string('note')->nullable();

            // วันหยุดราชการบางวันคลินิกอาจยังเปิด (เช่นวันที่เฉพาะราชการหยุด)
            // แยกไว้ให้พยาบาลปิดสวิตช์เองได้ ไม่ต้องลบทิ้งแล้วมาเพิ่มใหม่ทุกปี
            $table->boolean('is_closed')->default(true);

            $table->timestamps();

            $table->index(['holiday_date', 'is_closed']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('holidays');
    }
}
