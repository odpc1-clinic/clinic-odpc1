<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * รองรับ TMC (คลินิกเวชศาสตร์การเดินทาง) ย้ายจาก Medivel เข้ามาคีย์ที่ระบบนี้
 *  - passport: คนไข้ TMC เป็นชาวต่างชาติจำนวนมาก เลขบัตร ปชช. ใช้ไม่ได้
 *  - vital signs: เดิมไม่มีเก็บเลย (ห้องซักประวัติ TMC ต้องกรอกครบ 8 ค่า)
 *  - visit_vaccines: visit_medicines เก็บได้แค่ dose/route/amount ไม่มี lot/เข็มที่/ตำแหน่งฉีด
 *    ซึ่งจำเป็นสำหรับใบรับรองการฉีดวัคซีน
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('passport')->nullable()->after('id_card_number');
        });

        Schema::table('medicines', function (Blueprint $table) {
            // แยกวัคซีนออกจากยา เพื่อไม่ให้ dropdown ห้องยารกและให้ TMC เลือกได้เฉพาะวัคซีน
            $table->boolean('is_vaccine')->default(false)->after('unit');
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->decimal('weight', 5, 2)->nullable()->after('body_check_marks');      // กก.
            $table->decimal('height', 5, 1)->nullable()->after('weight');                // ซม.
            $table->decimal('temperature', 4, 1)->nullable()->after('height');           // °C
            $table->unsignedSmallInteger('pulse')->nullable()->after('temperature');     // ครั้ง/นาที
            $table->unsignedSmallInteger('systolic')->nullable()->after('pulse');
            $table->unsignedSmallInteger('diastolic')->nullable()->after('systolic');
            $table->unsignedSmallInteger('respiratory_rate')->nullable()->after('diastolic');
            $table->unsignedTinyInteger('spo2')->nullable()->after('respiratory_rate');  // %
        });

        Schema::create('visit_vaccines', function (Blueprint $table) {
            $table->id();

            // patient_id เป็นหลัก ส่วน visit_id ว่างได้ เพราะต้องคีย์ "ประวัติวัคซีนเดิม"
            // ที่ฉีดมาจากที่อื่น/จาก Medivel ซึ่งไม่มี visit ในระบบนี้
            $table->unsignedInteger('patient_id')->index();
            $table->unsignedInteger('visit_id')->nullable()->index();

            $table->unsignedInteger('medicine_id')->nullable();
            $table->unsignedBigInteger('medicine_lot_id')->nullable();

            // snapshot ไว้ เพราะใบรับรองที่ออกไปแล้วต้องคงข้อความเดิมเสมอ
            // ถึงแม้ภายหลังจะแก้ชื่อยาหรือลบ lot ทิ้ง
            $table->string('name');
            $table->string('lot_number')->nullable();
            $table->string('manufacturer')->nullable();

            $table->unsignedTinyInteger('dose_number')->nullable();   // เข็มที่เท่าไหร่
            $table->string('site')->nullable();                       // ตำแหน่งฉีด
            $table->string('route')->nullable();                      // IM / SC / PO
            $table->date('vaccinated_date');                          // เก็บเป็น ค.ศ. เท่านั้น (ดู MedicineLot::toDbDate)
            $table->date('next_due_date')->nullable();                // นัดเข็มถัดไป

            // 1 = ฉีดที่คลินิกนี้, 2 = ประวัติเดิม/ฉีดที่อื่น (คนไข้แจ้ง)
            $table->unsignedTinyInteger('source')->default(1);

            $table->unsignedBigInteger('injected_by')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('visit_vaccines');

        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn([
                'weight', 'height', 'temperature', 'pulse',
                'systolic', 'diastolic', 'respiratory_rate', 'spo2',
            ]);
        });

        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn('is_vaccine');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('passport');
        });
    }
};
