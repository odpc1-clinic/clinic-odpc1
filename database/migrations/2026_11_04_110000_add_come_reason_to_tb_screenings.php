<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "มาด้วยเรื่อง" ของผู้ป่วยใหม่ในใบคัดกรอง TB (คลินิกขอ 2569-11-04)
 *
 * รายงานประจำเดือน ข้อ "ชนิดผู้ป่วยใหม่ที่มารับบริการ" (1.1.1–1.1.5) เดิมคิดได้แค่ 2 แถวจากช่องผู้สัมผัส
 * ส่วนรับส่งต่อ / มาขอคำปรึกษา / leprosy ไม่มีที่ลง → ขึ้น "ไม่มีในระบบ"
 * 1 ผู้มีอาการสงสัยวัณโรค · 2 ผู้สัมผัสวัณโรค · 3 รับส่งต่อจากสถานพยาบาลอื่น · 4 มาขอคำปรึกษา · 5 leprosy
 * ว่าง (ใบเก่า) = รายงานเดาจากช่องผู้สัมผัสแบบเดิม
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tb_screenings', 'come_reason')) { return; }
        Schema::table('tb_screenings', function (Blueprint $t) {
            $t->unsignedTinyInteger('come_reason')->nullable()->after('visit_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tb_screenings', 'come_reason')) { return; }
        Schema::table('tb_screenings', function (Blueprint $t) {
            $t->dropColumn('come_reason');
        });
    }
};
