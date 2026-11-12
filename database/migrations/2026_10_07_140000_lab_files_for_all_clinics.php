<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ไฟล์ผลตรวจจากที่อื่น ใช้ได้ทุกคลินิก (2569-10-07) — เดิมมีแค่ในบัตร TB 01
 *
 * ใช้ตาราง tb_lab_files เดิม (ยังไม่มีแถว) แทนการสร้างตารางใหม่ เพื่อให้ไฟล์ผลของคนไข้คนเดียวกัน
 * เห็นได้ทั้งจากหน้าตรวจและบัตร TB โดยไม่ต้องอัปซ้ำ
 * - visit_id: อัปจากหน้าตรวจ (visit ไหน)
 * - tb_registration_id: ว่างได้ (อัปจากหน้าตรวจ STI/TMC ไม่มีบัตร TB)
 * ใช้ ALTER ตรง ๆ เพราะ ->change() ต้องลง doctrine/dbal ซึ่งโปรเจกต์ไม่มี
 */
class LabFilesForAllClinics extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('tb_lab_files', 'visit_id')) {
            DB::statement('ALTER TABLE tb_lab_files ADD visit_id BIGINT UNSIGNED NULL AFTER tb_registration_id, ADD INDEX tb_lab_files_visit_id_index (visit_id)');
        }
        DB::statement('ALTER TABLE tb_lab_files MODIFY tb_registration_id BIGINT UNSIGNED NULL');
    }

    public function down()
    {
        if (Schema::hasColumn('tb_lab_files', 'visit_id')) {
            DB::statement('ALTER TABLE tb_lab_files DROP INDEX tb_lab_files_visit_id_index, DROP COLUMN visit_id');
        }
    }
}
