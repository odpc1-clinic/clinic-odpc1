<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TMC รอบใหญ่:
 * - visits.tmc_travel_phase = ช่วงการเดินทาง (before/during/after)
 * - visits.tmc_menopause    = หมดประจำเดือน (วัยทอง)
 * - visit_vaccines.vaccinated_time = เวลาที่ฉีด (ไว้จับเวลาสังเกตอาการ 30 นาที)
 */
class AddTmcPhaseMenopauseVaccinetime extends Migration
{
    public function up()
    {
        Schema::table('visits', function (Blueprint $table) {
            if (!Schema::hasColumn('visits', 'tmc_travel_phase')) {
                $table->string('tmc_travel_phase', 20)->nullable()->after('tmc_reason');
            }
            if (!Schema::hasColumn('visits', 'tmc_menopause')) {
                $table->boolean('tmc_menopause')->default(0)->after('LMP');
            }
        });

        Schema::table('visit_vaccines', function (Blueprint $table) {
            if (!Schema::hasColumn('visit_vaccines', 'vaccinated_time')) {
                $table->string('vaccinated_time', 10)->nullable()->after('vaccinated_date');
            }
        });
    }

    public function down()
    {
        Schema::table('visits', function (Blueprint $table) {
            foreach (['tmc_travel_phase', 'tmc_menopause'] as $c) {
                if (Schema::hasColumn('visits', $c)) $table->dropColumn($c);
            }
        });
        Schema::table('visit_vaccines', function (Blueprint $table) {
            if (Schema::hasColumn('visit_vaccines', 'vaccinated_time')) $table->dropColumn('vaccinated_time');
        });
    }
}
