<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab ส่งตรวจภายนอก (เช่น N Health) แยกจาก lab_subs โดยสิ้นเชิง
 * เหตุผล: lab_subs.method เป็นรายการตายตัวของงาน STI และมีตรรกะผูกอยู่มาก
 * (การคิดเงิน/เบิก สปสช., กราฟ titer RPR, ตัวเลือกผลรายวิธี, รายงาน)
 * รายการแล็บนอกเป็นคนละชุดและเปลี่ยนตามใบเสนอราคาแต่ละปี จึงเก็บเป็น catalog แยก
 */
return new class extends Migration
{
    public function up()
    {
        // แคตตาล็อกรายการตรวจตามใบเสนอราคาของแล็บภายนอก
        Schema::create('external_lab_tests', function (Blueprint $table) {
            $table->id();
            $table->string('vendor')->default('N Health');   // เผื่ออนาคตมีหลายเจ้า
            $table->string('code')->index();                 // รหัสตามใบเสนอราคา เช่น A010
            $table->string('name');
            $table->string('specimen')->nullable();
            $table->string('turnaround')->nullable();
            $table->decimal('price', 10, 2)->nullable();     // ราคาตามใบเสนอราคา (ใช้อ้างอิง)
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // รายการที่สั่งจริงในแต่ละ visit
        Schema::create('visit_external_labs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('visit_id')->index();
            $table->unsignedBigInteger('external_lab_test_id')->nullable();

            // เก็บสำเนาชื่อ/รหัส/ราคาอ้างอิงไว้ เผื่อแคตตาล็อกถูกแก้หรือขึ้นราคาปีถัดไป
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('specimen')->nullable();
            $table->decimal('ref_price', 10, 2)->nullable(); // ราคาตามใบเสนอราคา ณ ตอนสั่ง
            $table->decimal('price', 10, 2)->nullable();     // ราคาที่เก็บคนไข้จริง (พยาบาลกรอกเอง)

            $table->text('result')->nullable();              // ผลที่พยาบาลคีย์กลับเข้ามา
            $table->string('remark')->nullable();
            $table->date('sent_date')->nullable();           // วันที่ส่งตรวจ (พ.ศ.)
            $table->date('result_date')->nullable();         // วันที่ได้ผล (พ.ศ.)

            $table->unsignedBigInteger('ordered_by')->nullable();
            $table->unsignedBigInteger('result_by')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('visit_external_labs');
        Schema::dropIfExists('external_lab_tests');
    }
};
