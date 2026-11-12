<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddServiceWaivedToBillingsTable extends Migration
{
    public function up()
    {
        Schema::table('billings', function (Blueprint $table) {
            // ขอความอนุเคราะห์: ยกเว้น "ค่าบริการ" (total_service = 0) แต่ยังบันทึกว่าทำอะไรบ้าง
            $table->boolean('service_waived')->default(false)->after('total_service');
        });
    }

    public function down()
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->dropColumn('service_waived');
        });
    }
}
