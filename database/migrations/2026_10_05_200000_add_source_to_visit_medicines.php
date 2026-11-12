<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * แถวยาที่กล่อง "สั่งยาครั้งนี้" (คลินิกวัณโรค) ลงไว้ในรายการยาของ visit — 2569-10-05
 * กดบันทึกกล่องซ้ำต้องแก้/ลบเฉพาะแถวที่กล่องสร้าง ไม่แตะยาที่หมอเพิ่มเองด้วยปุ่ม Add New
 *
 * ก่อนรัน migration นี้ โค้ดใช้เครื่องหมายใน sig (JSON) แทน (_src/_visit/_key) — ใช้ได้เหมือนกัน
 * แต่ถ้าแถวนั้นถูกแก้ด้วยปุ่มดินสอ sig ถูกล้าง เครื่องหมายหาย · คอลัมน์นี้ไม่หายแม้แก้แถว
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('visit_medicines', function (Blueprint $table) {
            if (! Schema::hasColumn('visit_medicines', 'source')) {
                $table->string('source', 20)->nullable()->after('sig');        // tb_order = มาจากกล่องสั่งยาวัณโรค
            }
            if (! Schema::hasColumn('visit_medicines', 'source_key')) {
                $table->string('source_key', 120)->nullable()->after('source'); // รหัสยาในกล่อง (H, 4FDC, other:Para ...)
            }
        });
    }

    public function down()
    {
        Schema::table('visit_medicines', function (Blueprint $table) {
            if (Schema::hasColumn('visit_medicines', 'source_key')) { $table->dropColumn('source_key'); }
            if (Schema::hasColumn('visit_medicines', 'source')) { $table->dropColumn('source'); }
        });
    }
};
