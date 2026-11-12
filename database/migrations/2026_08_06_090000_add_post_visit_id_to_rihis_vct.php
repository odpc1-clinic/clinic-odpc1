<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เก็บ visit ของ "การมาฟังผล" ที่ถูกยุบรวมเข้ากับแถวนี้
 *
 * จำเป็นตอนคู่ pre/post ข้ามปีงบ: แถวจะไปอยู่ปีงบของวันที่มาตรวจ
 * พอ sync ปีงบถัดไป ต้องรู้ว่า visit ฟังผลตัวนั้นถูกนับไปแล้ว จะได้ไม่นับซ้ำ
 */
class AddPostVisitIdToRihisVct extends Migration
{
    public function up()
    {
        Schema::table('rihis_vct', function (Blueprint $table) {
            $table->unsignedBigInteger('post_visit_id')->nullable()->after('visit_id');
            $table->index('post_visit_id');
        });
    }

    public function down()
    {
        Schema::table('rihis_vct', function (Blueprint $table) {
            $table->dropIndex(['post_visit_id']);
            $table->dropColumn('post_visit_id');
        });
    }
}
