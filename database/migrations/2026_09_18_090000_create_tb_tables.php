<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ทะเบียนวัณโรค TB 01 (คลินิก TB) — เริ่ม 2569-09-18
 *
 * ทำไมไม่เก็บใน visits:
 *   การรักษาวัณโรคหนึ่ง "คอร์ส" ยาว 6 เดือน ถึง 2 ปี คนไข้มาหลายสิบครั้งในคอร์สเดียว
 *   ข้อมูลที่พยาบาลกรอกในบัตร TB 01 (เลขทะเบียน, สูตรยา, ผลเสมหะรายเดือน, ผลการรักษา)
 *   เป็นของ "คอร์ส" ไม่ใช่ของการมาครั้งใดครั้งหนึ่ง
 *   คนไข้คนเดียวอาจมีหลายคอร์ส (รักษาซ้ำ/กลับเป็นซ้ำ) จึงต้องแยกเป็นตารางของตัวเอง
 *
 * วันที่ทุกคอลัมน์ในกลุ่มนี้เก็บเป็น พ.ศ. (Y-m-d) เหมือน visits.date และ visit_appointments
 * ห้ามโยนเข้า Carbon::parse() ตรง ๆ — แปลงด้วย \App\Utillity::BE2AD() ก่อน
 *
 * ปริมาณงานจริงที่คลินิกแจ้ง (ก.ย. 2569): TB 20 ราย · LTBI 10 ราย · MDR 10 ราย
 */
class CreateTbTables extends Migration
{
    public function up()
    {
        // ---------- 1) ทะเบียนผู้ป่วยวัณโรค = หนึ่งคอร์สการรักษา ----------
        Schema::create('tb_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('patient_id')->index();

            // เลขทะเบียนจาก NTIP — พยาบาลคีย์เอง ระบบไม่ได้เชื่อมกับ NTIP
            // ไม่ทำ unique เพราะรูปแบบเลขของแต่ละปี/แต่ละประเภทไม่เหมือนกัน และอาจต้องแก้ทีหลัง
            $table->string('tb_no', 50)->nullable()->index();

            // 1 = TB (ใช้ TB 01) · 2 = LTBI (วัณโรคแฝง) · 3 = MDR-TB (ใช้ PMTB 01)
            // แยกตั้งแต่ต้นเพราะทั้งสามกลุ่มใช้แบบฟอร์มคนละใบ แต่ใช้ทะเบียนชุดเดียวกัน
            $table->unsignedTinyInteger('registry_type')->default(1)->index();

            $table->date('register_date')->nullable();          // วันขึ้นทะเบียนรักษา (พ.ศ.)
            $table->date('treatment_start_date')->nullable();   // วันเริ่มกินยา (พ.ศ.)

            // การจำแนกผู้ป่วย: 1 = วัณโรคปอด · 2 = วัณโรคนอกปอด (ระบุตำแหน่งในช่องถัดไป)
            $table->unsignedTinyInteger('classification')->nullable();
            $table->string('extrapulmonary_site')->nullable();

            // การขึ้นทะเบียนรักษา: 1 ใหม่ · 2 กลับเป็นซ้ำ · 3 รักษาซ้ำภายหลังล้มเหลว
            // 4 รักษาซ้ำภายหลังขาดยา · 5 รับโอน · 6 อื่นๆ
            $table->unsignedTinyInteger('register_type')->nullable();
            $table->string('register_type_other')->nullable();

            // ตารางตรวจเสมหะ: 1 = New สูตรที่ 1 (เดือน 0,2(3),5,6) · 2 = History สูตรที่ 2 (เดือน 0,3(4),5,8)
            $table->unsignedTinyInteger('sputum_schedule')->nullable();

            $table->string('regimen')->nullable();               // สูตรยา เช่น 2HRZE/4HR
            $table->decimal('height', 5, 1)->nullable();         // ซม. (ใช้คิดขนาดยา)

            // กำกับการกินยา: dot 1 = DOT · 0 = No DOT
            // dot_by 1 เจ้าหน้าที่สาธารณสุข · 2 อาสาสมัคร · 3 ญาติ · 4 กินยาเอง · 5 อื่นๆ
            $table->boolean('dot')->nullable();
            $table->unsignedTinyInteger('dot_by')->nullable();
            $table->string('dot_by_other')->nullable();

            // อาการแรกรับ + ระยะเวลา เก็บเป็น JSON [{"key":"cough","duration":"2 สัปดาห์"}, ...]
            // ไม่แตกเป็นคอลัมน์ละอาการ เพราะแบบฟอร์มมีช่อง "อื่นๆ" ให้เขียนเองอยู่แล้ว
            $table->json('symptoms')->nullable();

            // ประวัติที่ TB 01 ให้กรอก — ส่วนใหญ่ดึงมาจากหน้าคัดกรอง/ทะเบียนคนไข้ได้ แต่ต้องแก้ทับได้
            $table->text('previous_tb_history')->nullable();
            $table->text('contact_history')->nullable();
            $table->text('other_disease')->nullable();
            $table->text('drug_allergy')->nullable();
            $table->unsignedSmallInteger('contact_under5')->nullable();           // ผู้สัมผัสอายุ < 5 ปี
            $table->unsignedSmallInteger('contact_under5_screened')->nullable();  // และได้รับการตรวจ
            $table->unsignedSmallInteger('contact_5_14')->nullable();
            $table->unsignedSmallInteger('contact_5_14_screened')->nullable();

            // BCG: 1 เคย มีแผลเป็น · 2 เคย ไม่มีแผลเป็น · 3 ไม่เคยได้รับ
            $table->unsignedTinyInteger('bcg')->nullable();

            // ให้คำปรึกษา/ตรวจเลือด HIV (TB 01 มีช่องนี้ · PMTB 01 มี VCT/DCT + ARV/CPT)
            $table->date('counsel_date')->nullable();
            $table->date('blood_test_date')->nullable();
            $table->unsignedTinyInteger('hiv_result')->nullable();   // 1 Negative · 2 Positive · 3 Not available
            $table->string('arv')->nullable();
            $table->string('cpt')->nullable();

            // ผลการรักษา: 1 หาย (Cured) · 2 ครบ (Completed) · 3 ล้มเหลว (Failed) · 4 ตาย (Died)
            // 5 ขาดยา > 2 เดือนติดกัน (Defaulted) · 6 โอนออก ไม่ทราบผล (Transferred out)
            $table->unsignedTinyInteger('outcome')->nullable()->index();
            $table->date('outcome_date')->nullable();

            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        // ---------- 2) ผลตรวจเสมหะรายเดือน (TB 01 ข้อ 3) ----------
        Schema::create('tb_sputum_exams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tb_registration_id')->index();

            // เดือนที่ของการรักษา 0 = ก่อนเริ่มยา · เคสดื้อยาไปได้ถึง 31 (เห็นในแฟ้มจริง)
            $table->unsignedTinyInteger('month_no');
            $table->date('collected_date')->nullable();

            // ผลแต่ละช่องเก็บเป็นข้อความ เพราะของจริงเขียนได้ทั้ง N, NG, 1+, 2+, "-" และวงกลมแดง
            $table->string('spot', 20)->nullable();
            $table->string('collect', 20)->nullable();
            $table->string('culture', 20)->nullable();
            $table->string('lab_serial', 50)->nullable();

            // สิ่งส่งตรวจ: เสมหะเป็นค่าปกติ แต่มีเคสส่ง blood/urine/tissue/LN/Bronchial lavage
            $table->string('specimen', 50)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['tb_registration_id', 'month_no'], 'tb_sputum_reg_month_unique');
        });

        // ---------- 3) ผล X-ray (TB 01 มี 3 ครั้งในบัตร แต่ของจริงถ่ายมากกว่านั้น) ----------
        Schema::create('tb_xrays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tb_registration_id')->index();
            $table->date('xray_date')->nullable();
            $table->string('xray_no', 50)->nullable();
            $table->text('result')->nullable();
            $table->timestamps();
        });

        // ---------- 4) น้ำหนัก + ขนาดยารายเดือน (TB 01 หน้า 2) ----------
        Schema::create('tb_monthly_doses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tb_registration_id')->index();
            $table->unsignedTinyInteger('month_no');
            $table->decimal('weight', 5, 2)->nullable();

            // ขนาดยา มก./วัน — เก็บเป็นข้อความ เพราะบางช่องเขียน "1 เม็ด" หรือ "-"
            $table->string('dose_h', 30)->nullable();   // Isoniazid
            $table->string('dose_r', 30)->nullable();   // Rifampicin
            $table->string('dose_e', 30)->nullable();   // Ethambutol
            $table->string('dose_z', 30)->nullable();   // Pyrazinamide
            $table->string('dose_s', 30)->nullable();   // Streptomycin
            $table->string('dose_fdc', 30)->nullable(); // ยารวมเม็ดหลายขนาน
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['tb_registration_id', 'month_no'], 'tb_dose_reg_month_unique');
        });

        // ---------- 5) ปฏิทินกินยารายวัน (ระยะเข้มข้น / ระยะต่อเนื่อง) ----------
        Schema::create('tb_dot_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tb_registration_id')->index();
            $table->date('dot_date');                               // พ.ศ.

            // 1 = ระยะเข้มข้น (Intensive) · 2 = ระยะต่อเนื่อง (Continuation)
            $table->unsignedTinyInteger('phase')->default(1);

            // TB 01 ติ๊กวันละช่อง · PMTB 01 (MDR) แยกเช้า/เย็น — เผื่อไว้ตั้งแต่ต้น
            // 0 = ทั้งวัน · 1 = เช้า · 2 = เย็น
            $table->unsignedTinyInteger('slot')->default(0);

            // สัญลักษณ์ตามที่ระบุท้ายบัตร TB 01:
            // start = X (วันเริ่มกินยา/วันที่คาดว่าจะกินเดือนถัดไป) · taken = ✓ (ผู้ป่วยกิน)
            // dispensed = - (จำนวนวันที่จ่ายยาให้) · missed = 0 (ผู้ป่วยไม่กิน)
            $table->string('mark', 10)->nullable();
            $table->timestamps();

            $table->unique(['tb_registration_id', 'dot_date', 'slot'], 'tb_dot_reg_date_slot_unique');
        });

        // ---------- 6) ผลทดสอบความไวต่อยา (DST) ----------
        Schema::create('tb_drug_susceptibilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tb_registration_id')->index();
            $table->date('sent_date')->nullable();
            $table->date('result_date')->nullable();

            // วิธี: 1 Solid · 2 Liquid · 3 Molecular (ตามท้ายบัตร TB 01)
            $table->unsignedTinyInteger('method')->nullable();
            $table->string('cs_no', 50)->nullable();

            // ผลรายยา เก็บเป็น JSON {"H":"S","R":"R","E":"S",...}
            // ไม่แตกเป็นคอลัมน์ละยา เพราะรายการยาต่างกันระหว่าง TB 01 (13 ตัว) กับ PMTB 01 (9 ตัว)
            // และยาใหม่ (BDQ, DLM, LZD) เพิ่มเข้ามาเรื่อย ๆ
            $table->json('results')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tb_drug_susceptibilities');
        Schema::dropIfExists('tb_dot_days');
        Schema::dropIfExists('tb_monthly_doses');
        Schema::dropIfExists('tb_xrays');
        Schema::dropIfExists('tb_sputum_exams');
        Schema::dropIfExists('tb_registrations');
    }
}
