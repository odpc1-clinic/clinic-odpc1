<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ช่องส่งตรวจทางห้องปฏิบัติการของใบคัดกรองวัณโรค (คลินิกขอ 2569-09-29)
 *
 * ของเดิมเป็นแค่ติ๊กรายการลอย ๆ (labs_ordered) ซึ่งไม่บอกว่าเก็บสิ่งส่งตรวจอะไร ตรวจด้วยวิธีไหน
 * ชุดใหม่แยกเป็น 3 ส่วนตามที่พยาบาลใช้จริง:
 *   1) สิ่งส่งตรวจ (specimen)
 *   2) วิธีตรวจหาเชื้อ: AFB · Molecular (Xpert/LPA/PCR) · Culture + DST
 *   3) การตรวจหาการติดเชื้อวัณโรค: Skin test (TST/Cy-TB/อื่น ๆ) และ IGRA พร้อมผลเป็นมิลลิเมตร
 *
 * คอลัมน์ labs_ordered เดิมยังอยู่ ไม่ได้ลบ ข้อมูลเก่าจึงไม่หาย
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->string('lab_specimen', 60)->nullable()->after('labs_other');
            $table->string('lab_specimen_other', 255)->nullable()->after('lab_specimen');
            $table->date('lab_sent_date')->nullable()->after('lab_specimen_other');      // พ.ศ.

            $table->json('lab_methods')->nullable()->after('lab_sent_date');             // afb · culture_dst
            $table->json('lab_molecular')->nullable()->after('lab_methods');             // xpert · lpa · pcr
            $table->string('lab_afb_result', 60)->nullable()->after('lab_molecular');    // N · scanty · 1+ · 2+ · 3+
            $table->string('lab_molecular_result', 255)->nullable()->after('lab_afb_result');
            $table->string('lab_culture_result', 255)->nullable()->after('lab_molecular_result');

            // การตรวจหาการติดเชื้อ (ใช้กับผู้สัมผัส/LTBI)
            $table->boolean('tst_done')->nullable()->after('lab_culture_result');
            $table->decimal('tst_mm', 5, 1)->nullable()->after('tst_done');
            $table->boolean('cytb_done')->nullable()->after('tst_mm');
            $table->decimal('cytb_mm', 5, 1)->nullable()->after('cytb_done');
            $table->string('skin_other_name', 60)->nullable()->after('cytb_mm');
            $table->decimal('skin_other_mm', 5, 1)->nullable()->after('skin_other_name');
            $table->boolean('igra_done')->nullable()->after('skin_other_mm');
            $table->string('igra_result', 60)->nullable()->after('igra_done');
        });
    }

    public function down()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->dropColumn([
                'lab_specimen', 'lab_specimen_other', 'lab_sent_date', 'lab_methods', 'lab_molecular',
                'lab_afb_result', 'lab_molecular_result', 'lab_culture_result',
                'tst_done', 'tst_mm', 'cytb_done', 'cytb_mm', 'skin_other_name', 'skin_other_mm',
                'igra_done', 'igra_result',
            ]);
        });
    }
};
