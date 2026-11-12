<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateServicePricesTable extends Migration
{
    public function up()
    {
        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // ค่าบริการมาตรฐานจากใบเก็บค่าใช้จ่าย (แก้ไขราคาได้ภายหลังที่หน้าจัดการ)
        $now = now();
        DB::table('service_prices')->insert([
            ['name' => 'ค่าบริการผู้ป่วยนอก (OPD)', 'price' => 120, 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ค่าฉีดยา (Injection)', 'price' => 60, 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ค่าบริการจี้ยา (จี้หูด/course)', 'price' => 150, 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ค่าตรวจคัดกรองกลุ่มเป้าหมาย', 'price' => 100, 'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'แพ็กเกจคัดกรองกลุ่มทั่วไป หญิง (Female STD)', 'price' => 420, 'sort_order' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'แพ็กเกจคัดกรองกลุ่มทั่วไป ชาย (Male STD)', 'price' => 360, 'sort_order' => 6, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('service_prices');
    }
}
