<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * วิธีใช้ยา (Sig) ของยาวัณโรคแต่ละตัว — ต่อหนึ่งบัตร TB 01 (2569-09-29)
 *
 * ที่มา: ช่องสั่งยาเดิมมีแค่ "ยาอะไร" กับ "ขนาดต่อวันตามน้ำหนัก" ไม่มีจำนวนต่อครั้ง ความถี่
 * route และเวลาที่สัมพันธ์กับมื้ออาหาร สติกเกอร์ยาเลยพิมพ์ตายตัวว่า "วันละ 1 ครั้ง หลังอาหารเช้า"
 * ทุกตัว ซึ่งผิดกับยาที่ต้องกินตอนท้องว่าง และสูตรที่กินสัปดาห์ละครั้ง (3HP)
 *
 * เก็บที่บัตร ไม่ใช่ที่ช่วงสูตรยา (tb_regimen_changes) เพราะการบันทึกสูตรยาแต่ละครั้ง
 * คือการ "เปลี่ยนสูตรกลางคอร์ส" ระบบจะปิดช่วงเดิมแล้วเปิดช่วงใหม่ ถ้าผูกวิธีใช้ไว้ที่นั่น
 * การแก้วิธีใช้ยาแค่ตัวเดียวจะกลายเป็นการเปลี่ยนสูตรไปด้วย
 *
 * รูปแบบ: {"H": {"qty":"1","unit":"เม็ด","freq":"OD","route":"PO","timing":"hs"}, ...}
 */
class AddDrugSigsToTbRegistrations extends Migration
{
    public function up()
    {
        Schema::table('tb_registrations', function (Blueprint $table) {
            $table->json('drug_sigs')->nullable()->after('regimen');
        });
    }

    public function down()
    {
        Schema::table('tb_registrations', function (Blueprint $table) {
            $table->dropColumn('drug_sigs');
        });
    }
}
