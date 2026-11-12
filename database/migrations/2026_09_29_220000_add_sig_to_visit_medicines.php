<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * วิธีใช้ยาแบบเลือก (Sig) ของใบสั่งยาคลินิกหลัก (2569-09-29)
 *
 * ช่อง dose / route เดิมเป็นข้อความอิสระ ("1*2", "Oral pc") ห้องยาไม่เห็นวิธีใช้ และคนไข้อ่านไม่ออก
 * เก็บแยกเป็น JSON ไม่แตะ dose / route เดิม เพราะ export ทุกตัวกับใบพิมพ์ยังใช้อยู่
 * ดูรหัสใน App\Support\DrugSig
 */
class AddSigToVisitMedicines extends Migration
{
    public function up()
    {
        Schema::table('visit_medicines', function (Blueprint $table) {
            $table->json('sig')->nullable()->after('amount');
        });
    }

    public function down()
    {
        Schema::table('visit_medicines', function (Blueprint $table) {
            $table->dropColumn('sig');
        });
    }
}
