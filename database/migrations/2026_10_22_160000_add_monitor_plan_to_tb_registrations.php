<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ตารางติดตามการรักษาที่แพทย์กำหนดเอง — ใช้กับสูตร Individualized (2569-10-22)
 * สูตรมาตรฐานใช้ตารางมาตรฐาน (DS / ดื้อยาระยะสั้น / BPaL(M) / TPT) · กำหนดยาเอง = กำหนดตารางเอง (คลินิกตกลง)
 * {"months": 9, "cells": {"afb": ["d0","m1",...], ...}} — ดู TbMonitorDefinitions::custom()
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tb_registrations', 'monitor_plan')) {
            Schema::table('tb_registrations', function (Blueprint $table) {
                $table->text('monitor_plan')->nullable()->after('drug_sigs');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tb_registrations', 'monitor_plan')) {
            Schema::table('tb_registrations', function (Blueprint $table) {
                $table->dropColumn('monitor_plan');
            });
        }
    }
};
