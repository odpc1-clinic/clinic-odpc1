<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMedicineLotsTables extends Migration
{
    public function up()
    {
        // ล็อตยาแต่ละครั้งที่รับเข้าคลัง (มีเลข lot, ทุน, ขาย, จำนวน, วันหมดอายุ แยกกัน)
        Schema::create('medicine_lots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('medicine_id')->index();
            $table->string('lot_number')->nullable();
            $table->date('received_date')->nullable();        // วันที่รับเข้าคลัง
            $table->decimal('cost_price', 10, 2)->default(0);  // ราคาทุน/หน่วย
            $table->decimal('sale_price', 10, 2)->default(0);  // ราคาขาย/หน่วย
            $table->unsignedInteger('quantity_received')->default(0);  // จำนวนที่รับเข้า
            $table->integer('quantity_remaining')->default(0);         // จำนวนคงเหลือ (int เผื่อปรับติดลบกรณีแก้ไข)
            $table->date('expiry_date')->nullable()->index();  // วันหมดอายุ (ใช้จัด FEFO)
            $table->timestamps();
        });

        // ประวัติการเคลื่อนไหวของสต๊อก (รับเข้า / เบิกจ่าย / ปรับยอด) — ไว้ตรวจสอบว่าเบิกจาก lot ไหนไปบ้าง
        Schema::create('medicine_lot_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_lot_id')->index();
            $table->unsignedInteger('medicine_id')->index();
            $table->unsignedInteger('visit_id')->nullable();
            $table->string('type', 15);        // receive | dispense | adjust
            $table->integer('qty');            // +รับเข้า / -เบิกออก
            $table->unsignedInteger('user_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('medicine_lot_movements');
        Schema::dropIfExists('medicine_lots');
    }
}
