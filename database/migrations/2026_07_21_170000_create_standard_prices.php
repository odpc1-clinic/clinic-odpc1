<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ราคากลาง — ตามประกาศ สคร.1 เชียงใหม่ เรื่องอัตราค่าบริการสาธารณสุข
 * เก็บแยกจากราคาที่ใช้จริง (medicines.price / lab_prices / service_prices)
 * เพราะประกาศเป็น "อัตราอ้างอิงตามกฎหมาย" ที่ออกใหม่เป็นปีๆ ส่วนราคาที่ใช้จริงหน้างานปรับได้
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('standard_prices', function (Blueprint $table) {
            $table->id();
            $table->string('category');                          // หมวด เช่น ยา/วัคซีน, Lab, บริการ
            $table->string('name');
            $table->string('unit')->nullable();                  // dose / เม็ด / Test / ครั้ง
            $table->decimal('price', 10, 2)->default(0);         // ราคาสำหรับคนไทย
            $table->decimal('price_foreign', 10, 2)->nullable(); // ราคาสำหรับชาวต่างชาติ (ถ้าประกาศแยก)
            $table->string('source')->nullable();                // ที่มาของราคา
            $table->string('effective_date')->nullable();        // วันที่ประกาศ (พ.ศ.)
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('standard_prices');
    }
};
