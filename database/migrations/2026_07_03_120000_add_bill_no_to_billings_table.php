<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBillNoToBillingsTable extends Migration
{
    public function up()
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->string('bill_no')->nullable()->unique()->after('id'); // เลขที่บิล เช่น 13783 + ปี พ.ศ. 2 หลัก + เลขรัน 7 หลัก
        });
    }

    public function down()
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->dropColumn('bill_no');
        });
    }
}
