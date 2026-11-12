<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ใบซักประวัติ/คัดกรองวัณโรค — ผูกกับ "การมาแต่ละครั้ง" (คนละระดับกับ tb_registrations)
 *
 * ใช้ทุกครั้งที่คนไข้มาคัดกรอง ตราบใดที่ยังไม่ได้ขึ้นทะเบียน TB/LTBI (พี่กานต์ยืนยัน 2569-09-18)
 * คนที่คัดกรองแล้วผลปกติก็มีประวัติเก็บไว้ ไม่ต้องเปิดบัตร
 *
 * คะแนนอาการใช้เกณฑ์ "แบบคัดกรองผู้มีอาการสงสัยวัณโรคปอด" ของกรมควบคุมโรค (tbthailand.org):
 *   ไอเกิน 2 สัปดาห์ 3 · ไอเป็นเลือด 3 · ไอน้อยกว่า 2 สัปดาห์ 2 · น้ำหนักลด 1 · ไข้ 1 สัปดาห์ 1 · เหงื่อออกกลางคืน 1
 *   รวมตั้งแต่ 3 คะแนนขึ้นไป = ผู้มีอาการสงสัยวัณโรค ต้องส่งตรวจต่อ
 *
 * ช่องน้ำหนัก/ส่วนสูง/สัญญาณชีพ ไม่เก็บซ้ำที่นี่ — ใช้ของเดิมใน visits (ทำไว้ตอนคลินิก TMC)
 */
class CreateTbScreeningsTable extends Migration
{
    public function up()
    {
        Schema::create('tb_screenings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('visit_id')->unique();   // หนึ่งใบต่อการมาหนึ่งครั้ง
            $table->unsignedInteger('patient_id')->index();

            $table->text('illness_history')->nullable();        // ประวัติการเจ็บป่วย

            // อาการภายใน 1 เดือนที่ผ่านมา — เก็บเป็นธงแยกช่อง เพราะต้องเอาไปนับคะแนนและทำรายงาน
            $table->boolean('sym_cough_over2wk')->default(false);   // 3 คะแนน
            $table->boolean('sym_hemoptysis')->default(false);      // 3
            $table->boolean('sym_cough_under2wk')->default(false);  // 2
            $table->boolean('sym_weight_loss')->default(false);     // 1
            $table->boolean('sym_fever')->default(false);           // 1
            $table->boolean('sym_night_sweat')->default(false);     // 1
            $table->unsignedTinyInteger('symptom_score')->default(0);   // คิดจากธงข้างบน เก็บไว้เพื่อค้น/รายงาน

            $table->text('underlying_disease')->nullable();
            $table->boolean('contact_tb')->nullable();          // ประวัติสัมผัสผู้ป่วยวัณโรค
            $table->text('contact_tb_detail')->nullable();
            $table->boolean('previous_tb')->nullable();         // เคยป่วย/กำลังรักษาวัณโรค
            $table->text('previous_tb_detail')->nullable();

            // ตรวจร่างกายตามช่องในใบ (แยกช่องเพราะแพทย์เขียนคนละบรรทัด)
            $table->text('pe_ga')->nullable();
            $table->text('pe_heent')->nullable();
            $table->text('pe_heart')->nullable();
            $table->text('pe_chest')->nullable();
            $table->text('pe_abdomen')->nullable();
            $table->text('pe_ext')->nullable();
            $table->text('pe_neuro')->nullable();

            // การตรวจที่สั่ง — เก็บเป็น JSON ["sputum_afb","xpert",...] เพราะเป็นรายการติ๊ก
            $table->json('labs_ordered')->nullable();
            $table->string('labs_other')->nullable();

            // ผลเอกซเรย์ปอด: 1 ปกติ · 2 เข้าได้กับวัณโรคปอด · 3 โรคปอดอื่นๆ · 4 ไม่ได้เอกซเรย์
            $table->unsignedTinyInteger('cxr_result')->nullable();
            $table->string('cxr_note')->nullable();

            // Impression: 1 normal · 2 LTBI · 3 TB · 4 pulmonary TB · 5 extrapulmonary TB · 6 อื่นๆ
            $table->unsignedTinyInteger('impression')->nullable()->index();
            $table->unsignedTinyInteger('afb_status')->nullable();   // 1 = M+ · 2 = M- (ใช้กับวัณโรคปอด)
            $table->string('impression_site')->nullable();           // ตำแหน่งที่เป็น (นอกปอด)
            $table->string('impression_other')->nullable();

            $table->text('plan')->nullable();

            // บัตรที่เปิดจากใบนี้ (ถ้าคัดกรองแล้วเจอ) — ไว้เดินกลับไปมาระหว่างใบคัดกรองกับบัตร
            $table->unsignedBigInteger('tb_registration_id')->nullable()->index();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tb_screenings');
    }
}
