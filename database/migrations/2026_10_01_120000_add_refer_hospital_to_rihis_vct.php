<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ชื่อโรงพยาบาลที่ส่งต่อไป (เช่น รพ.มหาราช) — พยาบาลพิมพ์เอง
 *
 * ฟอร์มส่วนกลางมีคอลัมน์ AV "ระบุ สถานที่ส่งต่อไปรับบริการ" อยู่แล้ว แต่ระบบเดิมไม่มีที่เก็บ
 * เป็นข้อความอิสระเพราะรายชื่อโรงพยาบาลที่ส่งจริงไม่ตายตัว
 */
class AddReferHospitalToRihisVct extends Migration
{
    public function up()
    {
        Schema::table('rihis_vct', function (Blueprint $table) {
            $table->string('refer_hospital', 150)->nullable()->after('refer_other');
        });
    }

    public function down()
    {
        Schema::table('rihis_vct', function (Blueprint $table) {
            $table->dropColumn('refer_hospital');
        });
    }
}
