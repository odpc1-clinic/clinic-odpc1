<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ชื่อ-นามสกุลภาษาอังกฤษ (2569-10-07) — ใบรับรองแพทย์ภาษาอังกฤษ / สมุดวัคซีนต้องสะกดตามพาสปอร์ต
 * เดิมใบภาษาอังกฤษพิมพ์ชื่อไทยออกไป · ว่างได้ ใบรับรองจะถอยไปใช้ชื่อไทยเหมือนเดิม
 */
class AddEnglishNameToPatients extends Migration
{
    public function up()
    {
        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'name_en')) {
                $table->string('name_en', 150)->nullable()->after('surname');
            }
            if (! Schema::hasColumn('patients', 'surname_en')) {
                $table->string('surname_en', 150)->nullable()->after('name_en');
            }
        });
    }

    public function down()
    {
        Schema::table('patients', function (Blueprint $table) {
            foreach (['surname_en', 'name_en'] as $c) {
                if (Schema::hasColumn('patients', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
}
