<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('billing_id'); // เชื่อมโยงกับบิลหลัก
            $table->decimal('amount', 10, 2); // ยอดเงินที่รับชำระในรอบนี้
            $table->date('payment_date'); // บังคับเก็บเป็น พ.ศ. (เช่น 2569-03-18)
            $table->string('payment_method')->nullable(); // เช่น เงินสด, โอนเงิน
            $table->unsignedBigInteger('received_by')->nullable(); // ID เจ้าหน้าที่การเงินที่รับเงิน
            $table->string('remark')->nullable(); // หมายเหตุ (ถ้ามี)
            $table->timestamps();

            // สร้าง Foreign Key (หากตารางหลักชื่อ billings)
            // $table->foreign('billing_id')->references('id')->on('billings')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
}