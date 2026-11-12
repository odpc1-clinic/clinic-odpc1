<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * รายการ diagnosis สำเร็จรูปต่อคลินิก — หน้าวินิจฉัยของ TMC เลือกจากรายการ หรือพิมพ์เพิ่มเองแล้วเก็บไว้ให้เคสต่อไปเลือก
 * (พยาบาล TMC ขอ 2569-10-01 ตามหน้าตาโปรเจกต์ TMC เดิม)
 *
 * เดิม TMC ลงเป็นโรค "Other" แล้วพิมพ์เอง สะกดกันคนละแบบ ("screen Hepatitis B, C" / "Hep B, C screening" ...)
 * นับยอดไม่ได้ — รายการกลางช่วยให้ใช้คำเดียวกัน
 */
class CreateDiagnosisPresets extends Migration
{
    public function up()
    {
        Schema::create('diagnosis_presets', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('clinic')->index();   // 1 STI · 2 TB · 3 TMC (ตอนนี้ใช้แค่ TMC)
            $table->string('name', 150);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['clinic', 'name']);
        });

        // รายการตั้งต้นตามหน้าจอโปรเจกต์ TMC ที่พยาบาลใช้อยู่ + ตรวจไวรัสตับอักเสบ (ลงบ่อยสุดในข้อมูลเดิม)
        $names = [
            'Vaccination', 'IGRAs', 'รับคำปรึกษา', 'Malaria prophylaxis',
            'Hepatitis B, C screening',
            'COVID-19 Certificate', 'Medical letter of exemption', 'High altitude sickness prophylaxis',
            'ออกสมุดใหม่ทดแทน', 'ฉีดวัคซีนไปฮัจย์', 'ฉีดวัคซีนไปอุมเราะห์',
        ];
        $now = now();
        DB::table('diagnosis_presets')->insert(array_map(fn ($n, $i) => [
            'clinic' => 3, 'name' => $n, 'sort' => ($i + 1) * 10, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], $names, array_keys($names)));
    }

    public function down()
    {
        Schema::dropIfExists('diagnosis_presets');
    }
}
