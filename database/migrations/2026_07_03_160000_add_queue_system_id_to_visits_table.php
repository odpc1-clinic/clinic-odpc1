<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddQueueSystemIdToVisitsTable extends Migration
{
    public function up()
    {
        Schema::table('visits', function (Blueprint $table) {
            // อ้างอิงไปที่ queue_system.queues.id (คนละฐานข้อมูล เลยไม่ทำ foreign key จริง)
            $table->unsignedInteger('queue_system_id')->nullable()->after('queue');
        });
    }

    public function down()
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn('queue_system_id');
        });
    }
}
