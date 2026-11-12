<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLineMessageLogsTable extends Migration
{
    public function up()
    {
        Schema::create('line_message_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id')->nullable()->index(); // อาจไม่มีถ้าเป็นข้อความตอนยังไม่ผูกคนไข้ (เช่น ทักถามมั่วๆ)
            $table->string('line_user_id')->nullable();
            $table->string('type', 10); // push (ระบบส่งออก) หรือ reply (ตอบกลับในแชท)
            $table->string('purpose', 40)->nullable(); // appointment_reminder, link_success, greeting, ...
            $table->text('message');
            $table->boolean('success')->default(false);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('line_message_logs');
    }
}
