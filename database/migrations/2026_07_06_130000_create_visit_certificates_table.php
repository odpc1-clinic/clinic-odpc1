<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVisitCertificatesTable extends Migration
{
    public function up()
    {
        Schema::create('visit_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('visit_id');
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->json('data'); // เนื้อหาที่แก้ไขจริงตอนออกใบรับรอง (snapshot ทุกช่องที่แก้ไขได้)
            $table->timestamps();

            $table->index('visit_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('visit_certificates');
    }
}
