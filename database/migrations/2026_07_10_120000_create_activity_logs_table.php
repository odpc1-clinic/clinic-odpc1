<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActivityLogsTable extends Migration
{
    public function up()
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();     // ใครทำ (อาจ null ถ้าเป็น console/seed)
            $table->string('user_name')->nullable();               // เก็บชื่อ ณ ตอนนั้น กัน user ถูกลบแล้วสืบไม่ได้
            $table->string('action', 20);                          // created | updated | deleted
            $table->string('subject_type');                        // เช่น App\Models\LabSub
            $table->unsignedBigInteger('subject_id')->nullable();  // id ของ record
            $table->string('subject_label')->nullable();           // ป้ายอ่านง่าย เช่น ชื่อคนไข้/HN
            $table->json('changes')->nullable();                   // {field: {old, new}}
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('activity_logs');
    }
}
