<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ผลตรวจบางรายการส่งไป Lab ภายนอก แล้วพยาบาลเป็นคนคีย์ผลกลับเข้าระบบเอง
 * ผลแบบนี้ไม่ต้องรอห้อง Lab กดอนุมัติ (เพราะห้อง Lab ไม่ได้เป็นคนตรวจ)
 * แต่ยังต้องรู้ว่าผลไหนมาจากภายนอก จึงเก็บทั้งธงและชื่อแล็บต้นทาง
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('lab_subs', function (Blueprint $table) {
            $table->boolean('external')->default(false)->after('result');
            $table->string('external_source')->nullable()->after('external');
        });
    }

    public function down()
    {
        Schema::table('lab_subs', function (Blueprint $table) {
            $table->dropColumn(['external', 'external_source']);
        });
    }
};
