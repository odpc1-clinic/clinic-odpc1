<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddScopeToActivityLogsTable extends Migration
{
    public function up()
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('patient_id')->nullable()->after('subject_id');
            $table->unsignedBigInteger('visit_id')->nullable()->after('patient_id');
            $table->index('patient_id');
            $table->index('visit_id');
        });
    }

    public function down()
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['patient_id']);
            $table->dropIndex(['visit_id']);
            $table->dropColumn(['patient_id', 'visit_id']);
        });
    }
}
