<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * แบบบันทึกผลการทดสอบพิสูจน์เชื้อหนองใน (FM-STI-WI08-02 REV1) — ห้อง Lab ขอ 2569-10-02
 *
 * แยกตารางเพราะเป็นผลขั้นทดสอบ (worksheet) ของห้อง Lab ไม่ใช่ผลทางการที่ส่งให้พยาบาล/หมอ
 * ผลทางการยังเป็น lab_subs.result (ผล Culture) เหมือนเดิม — ไม่ยัดลง lab_subs.ast
 * เพราะ ast มีเวลา 'at' ที่ใช้เทียบกับเวลา Approve ใบ Lab ถ้ามาเขียนร่วมจะทำให้ผล AST กลายเป็น "รอ approve"
 * หนึ่งแถวต่อรายการ Culture หนึ่งตัว (lab_sub_id unique)
 */
class CreateGcIdentificationsTable extends Migration
{
    public function up()
    {
        Schema::create('gc_identifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('lab_sub_id')->unique();   // lab_subs.id เป็น int unsigned
            $table->string('lab_no', 50)->nullable();          // Lab number ที่ห้อง Lab ใช้ (เช่น STI69-2018) ในระบบยังไม่มีที่เก็บ
            $table->string('culture', 4)->nullable();          // G / NG / SO
            $table->string('typical', 2)->nullable();          // Y / N
            $table->string('gndc', 2)->nullable();             // P / N
            $table->string('oxidase', 2)->nullable();
            $table->string('superoxol', 10)->nullable();       // Negative / 1+ .. 4+
            $table->string('glucose', 2)->nullable();
            $table->string('maltose', 2)->nullable();
            $table->string('sucrose', 2)->nullable();
            $table->string('lactose', 2)->nullable();
            $table->string('final_id', 10)->nullable();        // NG / Non-NG
            $table->string('remark', 255)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->dateTime('reported_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('gc_identifications');
    }
}
