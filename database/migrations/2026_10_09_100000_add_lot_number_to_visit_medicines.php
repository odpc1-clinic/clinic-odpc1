<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot ของยา/วัคซีนที่สั่ง (TMC ขอ 2569-10-09) — เดิมพยาบาลพิมพ์ "lot.576904" ลงช่องหมายเหตุวิธีใช้
 * เก็บเป็นข้อความเฉย ๆ ไว้อ้างอิง (ใบรับรอง/ตามหาเข็ม) — ห้องยายังตัดสต๊อกตาม FEFO ของตัวเอง ไม่ผูกกับช่องนี้
 */
class AddLotNumberToVisitMedicines extends Migration
{
    public function up()
    {
        Schema::table('visit_medicines', function (Blueprint $table) {
            if (! Schema::hasColumn('visit_medicines', 'lot_number')) {
                $table->string('lot_number', 50)->nullable()->after('medicine_id');
            }
        });
    }

    public function down()
    {
        Schema::table('visit_medicines', function (Blueprint $table) {
            if (Schema::hasColumn('visit_medicines', 'lot_number')) {
                $table->dropColumn('lot_number');
            }
        });
    }
}
