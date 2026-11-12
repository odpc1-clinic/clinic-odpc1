<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ชื่อภาษาอังกฤษของเจ้าหน้าที่ (ห้อง Lab ขอ 2569-10-16) — ใช้ในใบผล Lab ภาษาอังกฤษ (Tested/Reported/Approved by)
 * ว่าง = ใช้ชื่อไทยเดิม · แก้ที่หน้าจัดการผู้ใช้ (/users)
 */
class AddNameEnToUsers extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'name_en')) {
                $table->string('name_en', 150)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'name_en')) {
                $table->dropColumn('name_en');
            }
        });
    }
}
