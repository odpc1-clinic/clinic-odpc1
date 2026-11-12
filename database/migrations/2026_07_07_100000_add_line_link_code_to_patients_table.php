<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLineLinkCodeToPatientsTable extends Migration
{
    public function up()
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('line_link_code', 6)->nullable()->after('line_linked_at');
            $table->timestamp('line_link_code_expires_at')->nullable()->after('line_link_code');
        });
    }

    public function down()
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['line_link_code', 'line_link_code_expires_at']);
        });
    }
}
