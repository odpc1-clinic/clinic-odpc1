<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBillPrintLogsTable extends Migration
{
    public function up()
    {
        Schema::create('bill_print_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('billing_id');
            $table->unsignedBigInteger('printed_by')->nullable();
            $table->string('reason')->nullable(); // บังคับกรอกเฉพาะพิมพ์ซ้ำ (ครั้งที่ 2 ขึ้นไป) เพื่อการตรวจสอบ
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bill_print_logs');
    }
}
