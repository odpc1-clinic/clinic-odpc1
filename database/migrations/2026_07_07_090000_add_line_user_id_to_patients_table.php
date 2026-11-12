<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLineUserIdToPatientsTable extends Migration
{
    public function up()
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('line_user_id')->nullable()->unique()->after('tel');
            $table->timestamp('line_linked_at')->nullable()->after('line_user_id');
        });
    }

    public function down()
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['line_user_id', 'line_linked_at']);
        });
    }
}
