<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * รหัสโรค ICD-10 + การผูกรหัสเข้ากับเอกสาร (2569-09-29)
 *
 * ที่มา: หมอวัณโรคขอให้ระบุเหตุผลการวินิจฉัยเป็นรหัส ICD-10 ได้
 * ใช้ 3 ที่: ใบคัดกรองวัณโรค · ทะเบียน TB 01 · ตารางวินิจฉัยของหน้าตรวจ
 *
 * แยกเป็น 2 ตารางโดยตั้งใจ:
 *
 * 1. icd10_codes = "พจนานุกรมรหัส" ตัวเดียวใช้ร่วมกันทุกที่
 *    ถ้าใส่รหัสเป็นข้อความอิสระในแต่ละหน้า จะได้ A15.0 / A15 / a15.0 ปนกัน
 *    แล้วรายงานนับไม่ได้ ชุดแรกเป็นรหัสวัณโรค 24 ตัว เติมกลุ่มโรคอื่นทีหลังได้
 *
 * 2. icd10_entries = "รหัสที่ถูกเลือกไว้" ผูกกับเอกสารแบบ polymorphic
 *    ต้องเลือกได้หลายรหัสต่อหนึ่งเอกสาร เพราะ U84.3 (ดื้อ Rifampicin) และ
 *    U84.7 (ดื้อหลายขนาน) เป็น "รหัสรอง" ที่ต้องใช้คู่กับรหัสกลุ่ม A เสมอ
 *    ใช้ owner_type/owner_id เพื่อให้ทั้งสามที่ใช้ตารางเดียวกัน ไม่ต้องมีสามตาราง
 *
 * เก็บ code เป็นข้อความ ไม่ใช่ foreign key ไป icd10_codes.id
 * เพราะรหัส ICD-10 เป็นมาตรฐานสากลที่ไม่เปลี่ยน และเวลาส่งออกรายงาน/ดูฐานตรงๆ
 * ต้องอ่านออกทันทีว่าเป็นรหัสอะไร ไม่ต้อง join
 */
class CreateIcd10Tables extends Migration
{
    public function up()
    {
        Schema::create('icd10_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('description_en', 255)->nullable();
            $table->string('description_th', 255)->nullable();
            $table->string('group_type', 60)->nullable();
            // รหัสรอง = ใช้ลำพังไม่ได้ ต้องมีรหัสหลักคู่เสมอ (U84.x)
            $table->boolean('is_secondary')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['group_type', 'sort']);
        });

        Schema::create('icd10_entries', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 60);
            $table->unsignedBigInteger('owner_id');
            $table->string('code', 10);
            // รหัสหลักของเอกสารนั้น — ใช้ตอนทำรายงานที่ต้องนับโรคเดียวต่อเคส
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
            $table->index('code');
            // กันเลือกรหัสเดียวกันซ้ำในเอกสารเดียว (กดปุ่มรัวหรือส่งฟอร์มซ้ำ)
            $table->unique(['owner_type', 'owner_id', 'code'], 'icd10_entries_owner_code_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('icd10_entries');
        Schema::dropIfExists('icd10_codes');
    }
}
