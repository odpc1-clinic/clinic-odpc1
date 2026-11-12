<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * คลินิกวัณโรคขอ 2569-10-14
 * - tb_screenings.nursing_note: บล็อก "บันทึกทางการพยาบาล" ใต้ Plan (วัณโรค) ในหน้าตรวจ
 * - tb_registrations.hiv_counselor: ผู้ให้คำปรึกษา HIV (พิมพ์เอง)
 * - tb_registrations.hiv_test_place: ตรวจ HIV ที่ไหน — ว่าง = คลินิกนี้ · มีค่า = ชื่อ รพ./ที่อื่น (ลงวันที่/ผลเองในบัตร ไม่ต้องสั่ง Lab)
 */
class AddNursingNoteAndHivPlace extends Migration
{
    public function up()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            if (! Schema::hasColumn('tb_screenings', 'nursing_note')) {
                $table->text('nursing_note')->nullable();
            }
        });
        Schema::table('tb_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('tb_registrations', 'hiv_counselor')) {
                $table->string('hiv_counselor', 150)->nullable();
            }
            if (! Schema::hasColumn('tb_registrations', 'hiv_test_place')) {
                $table->string('hiv_test_place', 150)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            if (Schema::hasColumn('tb_screenings', 'nursing_note')) {
                $table->dropColumn('nursing_note');
            }
        });
        Schema::table('tb_registrations', function (Blueprint $table) {
            foreach (['hiv_counselor', 'hiv_test_place'] as $c) {
                if (Schema::hasColumn('tb_registrations', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
}
