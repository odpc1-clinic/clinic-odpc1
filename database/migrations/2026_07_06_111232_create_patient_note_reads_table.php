<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePatientNoteReadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('patient_note_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_note_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('read_at');

            $table->unique(['patient_note_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('patient_note_reads');
    }
}
