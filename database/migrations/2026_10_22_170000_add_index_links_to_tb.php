<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ผูกผู้สัมผัสกับผู้ป่วยต้นเหตุ (index case) — 2569-10-22
 *
 * บัตร LTBI (หรือบัตรของผู้สัมผัสที่ป่วยเอง) → ชี้ไปบัตร TB ของ index
 * ใบคัดกรองผู้สัมผัส (ไม่ได้เปิดบัตร) → ชี้ไปบัตร TB ของ index ได้เหมือนกัน
 * บัตร index จึงรวมรายชื่อผู้สัมผัสทั้งหมดได้ (ร่วมบ้าน + คัดกรอง + บัตร LTBI) — ดู App\Services\TbContacts
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tb_registrations' => 'registry_type', 'tb_screenings' => 'contact_tb'] as $t => $after) {
            if (! Schema::hasColumn($t, 'index_registration_id')) {
                Schema::table($t, function (Blueprint $table) use ($after) {
                    $table->unsignedBigInteger('index_registration_id')->nullable()->after($after)->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['tb_registrations', 'tb_screenings'] as $t) {
            if (Schema::hasColumn($t, 'index_registration_id')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->dropIndex([ 'index_registration_id' ]);
                    $table->dropColumn('index_registration_id');
                });
            }
        }
    }
};
