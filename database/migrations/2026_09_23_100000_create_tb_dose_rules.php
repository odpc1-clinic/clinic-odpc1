<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * เกณฑ์ขนาดยาวัณโรคตามน้ำหนัก — จากไฟล์ Calculation_4TB.xlsx ชีต "1ตาราง_ขนาดยาDS_TB_Adult"
 * (คลินิกขอให้ระบบคำนวณให้ และแก้ไขเกณฑ์เองได้ 2569-09-23)
 *
 * มีสองแบบในตารางเดียวกัน:
 *   1) ขนาดมาตรฐานตามช่วงน้ำหนัก (35-49 / 50-69 / >70 กก.) — ใช้เป็นหลัก
 *   2) มก./กก./วัน — ใช้กับคนที่น้ำหนักนอกช่วง (<35 หรือ >70) และไว้ตรวจทานว่าขนาดที่ให้อยู่ในกรอบไหม
 *
 * เก็บเป็นข้อมูลในฐาน ไม่ฝังในโค้ด เพราะแนวทางการรักษาเปลี่ยนได้
 * และคลินิกต้องแก้เองได้โดยไม่ต้องรอแก้โปรแกรม
 */
class CreateTbDoseRules extends Migration
{
    public function up()
    {
        Schema::create('tb_dose_rules', function (Blueprint $table) {
            $table->id();

            // กลุ่มสูตร: DS = ไม่ดื้อยา (ตอนนี้มีเฉพาะผู้ใหญ่) · เผื่อ Resist/TPT ไว้ด้วย
            $table->string('group', 20)->default('DS');
            $table->string('patient_type', 20)->default('adult');   // adult | ped

            $table->string('drug_key', 20);        // H, R, Z, E, Mfx, Lfx
            $table->string('drug_label');          // ชื่อที่แสดง เช่น "H (Isoniazid)"
            $table->string('strength')->nullable(); // ขนาดเม็ดที่มี เช่น "100 mg"

            // ช่วงน้ำหนัก (กก.) — ว่าง = ไม่จำกัดด้านนั้น
            $table->decimal('weight_min', 5, 1)->nullable();
            $table->decimal('weight_max', 5, 1)->nullable();

            $table->unsignedSmallInteger('dose_mg')->nullable();    // ขนาดมาตรฐานของช่วงน้ำหนักนี้
            $table->decimal('mgkg_min', 5, 2)->nullable();          // กรอบ มก./กก./วัน
            $table->decimal('mgkg_max', 5, 2)->nullable();

            $table->string('note')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $now  = now();
        $sort = 0;

        // [drug_key, label, strength, mgkg_min, mgkg_max, [[min,max,dose], ...], note]
        $rules = [
            ['H',   'H (Isoniazid)',    '100, 300 mg', 4,   6,  [[35, 49.9, 300], [50, 69.9, 300], [70, null, 300]], 'ปรับตาม NAT2'],
            ['R',   'R (Rifampicin)',   '300, 450 mg', 8,   12, [[35, 49.9, 450], [50, 69.9, 600], [70, null, 600]], ''],
            ['Z',   'Z (Pyrazinamide)', '500 mg',      20,  30, [[35, 49.9, 1000], [50, 69.9, 1500], [70, null, 2000]], ''],
            ['E',   'E (Ethambutol)',   '400, 500 mg', 15,  20, [[35, 49.9, 800], [50, 69.9, 1000], [70, null, 1200]], ''],
            ['Mfx', 'Moxifloxacin',     '400, 500 mg', 7.5, 10, [[35, null, 400]], 'ขนาดคงที่ตั้งแต่ 35 กก. ขึ้นไป'],
            ['Lfx', 'Levofloxacin',     '250, 500 mg', 15,  15, [[35, 50, 500], [50.1, null, 750]], ''],
        ];

        foreach ($rules as [$key, $label, $strength, $kgMin, $kgMax, $bands, $note]) {
            foreach ($bands as [$wMin, $wMax, $dose]) {
                DB::table('tb_dose_rules')->insert([
                    'group'        => 'DS',
                    'patient_type' => 'adult',
                    'drug_key'     => $key,
                    'drug_label'   => $label,
                    'strength'     => $strength,
                    'weight_min'   => $wMin,
                    'weight_max'   => $wMax,
                    'dose_mg'      => $dose,
                    'mgkg_min'     => $kgMin,
                    'mgkg_max'     => $kgMax,
                    'note'         => $note ?: null,
                    'sort'         => ++$sort,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('tb_dose_rules');
    }
}
