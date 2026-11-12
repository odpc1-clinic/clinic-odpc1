<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReport506ToDiagnoses extends Migration
{
    public function up()
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            // พยาบาลติ๊กว่าจะส่งเข้ารายงาน 506 (opt-in ต่อวินิจฉัย)
            if (!Schema::hasColumn('diagnoses', 'report_506')) {
                $table->boolean('report_506')->default(0)->after('gf');
            }
            // เวลาที่ export/ส่งเข้า 506 แล้ว (กันส่งซ้ำ; null = ยังไม่ส่ง)
            if (!Schema::hasColumn('diagnoses', 'report_506_sent_at')) {
                $table->timestamp('report_506_sent_at')->nullable()->after('report_506');
            }
        });
    }

    public function down()
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            if (Schema::hasColumn('diagnoses', 'report_506_sent_at')) {
                $table->dropColumn('report_506_sent_at');
            }
            if (Schema::hasColumn('diagnoses', 'report_506')) {
                $table->dropColumn('report_506');
            }
        });
    }
}
