<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ฟอร์มซักประวัติเวชศาสตร์การเดินทาง TMC (ตามใบซักประวัติ TMC 66)
 * เก็บบน visits (ต่อการมาแต่ละครั้ง) — บันทึกพร้อมปุ่ม Save หน้าตรวจ
 */
class AddTmcHistoryToVisits extends Migration
{
    public function up()
    {
        Schema::table('visits', function (Blueprint $table) {
            $cols = [
                // สาเหตุการมา (checkbox)
                'tmc_reason_malaria'   => ['bool'],
                'tmc_reason_altitude'  => ['bool'],
                'tmc_reason_vaccine'   => ['bool'],
                'tmc_vaccine_specify'  => ['string', 191],
                // สุภาพสตรี
                'tmc_pregnant'         => ['string', 10],
                'tmc_pregnant_detail'  => ['string', 191],
                'tmc_breastfeeding'    => ['string', 10],
                // แผนการท่องเที่ยว
                'tmc_destinations'     => ['string', 500],
                'tmc_depart_date'      => ['string', 30],
                'tmc_return_date'      => ['string', 30],
                'tmc_area_rural'       => ['bool'],
                'tmc_area_urban'       => ['bool'],
                'tmc_area_altitude'    => ['bool'],
                'tmc_trip_purpose'     => ['string', 500],
                'tmc_activities'       => ['string', 500],
                'tmc_animal_exposure'  => ['string', 10],
                'tmc_accommodation'    => ['string', 500],
                // แพทย์
                'tmc_risk_assessment'  => ['text'],
                'tmc_plan'             => ['text'],
                'tmc_yellow_book_no'   => ['string', 50],
            ];

            foreach ($cols as $name => $def) {
                if (Schema::hasColumn('visits', $name)) {
                    continue;
                }
                if ($def[0] === 'bool') {
                    $table->boolean($name)->default(0);
                } elseif ($def[0] === 'text') {
                    $table->text($name)->nullable();
                } else {
                    $table->string($name, $def[1])->nullable();
                }
            }
        });
    }

    public function down()
    {
        $names = [
            'tmc_reason_malaria', 'tmc_reason_altitude', 'tmc_reason_vaccine', 'tmc_vaccine_specify',
            'tmc_pregnant', 'tmc_pregnant_detail', 'tmc_breastfeeding',
            'tmc_destinations', 'tmc_depart_date', 'tmc_return_date',
            'tmc_area_rural', 'tmc_area_urban', 'tmc_area_altitude',
            'tmc_trip_purpose', 'tmc_activities', 'tmc_animal_exposure', 'tmc_accommodation',
            'tmc_risk_assessment', 'tmc_plan', 'tmc_yellow_book_no',
        ];
        Schema::table('visits', function (Blueprint $table) use ($names) {
            foreach ($names as $n) {
                if (Schema::hasColumn('visits', $n)) {
                    $table->dropColumn($n);
                }
            }
        });
    }
}
