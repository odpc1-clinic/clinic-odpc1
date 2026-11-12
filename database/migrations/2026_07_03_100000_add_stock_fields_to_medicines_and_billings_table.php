<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStockFieldsToMedicinesAndBillingsTable extends Migration
{
    public function up()
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->unsignedInteger('stock')->default(0)->after('price'); // จำนวนคงเหลือในสต๊อก
            $table->string('unit')->nullable()->after('stock'); // หน่วยนับ เช่น เม็ด, ขวด, ซอง
            $table->unsignedInteger('reorder_point')->default(0)->after('unit'); // จุดเตือนสต๊อกต่ำ
        });

        Schema::table('billings', function (Blueprint $table) {
            $table->timestamp('stock_deducted_at')->nullable()->after('status'); // กันหักสต๊อกซ้ำเมื่อบันทึกบิลซ้ำ
        });
    }

    public function down()
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn(['stock', 'unit', 'reorder_point']);
        });

        Schema::table('billings', function (Blueprint $table) {
            $table->dropColumn('stock_deducted_at');
        });
    }
}
