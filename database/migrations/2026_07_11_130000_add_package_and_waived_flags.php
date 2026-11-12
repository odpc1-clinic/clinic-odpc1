<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddPackageAndWaivedFlags extends Migration
{
    public function up()
    {
        Schema::table('service_prices', function (Blueprint $table) {
            // แพ็กเกจเหมาจ่าย: ห้ามขอความอนุเคราะห์ (คิดเสมอ)
            $table->boolean('is_package')->default(false)->after('price');
        });

        Schema::table('billing_items', function (Blueprint $table) {
            // ขอความอนุเคราะห์รายรายการ: ยกเว้นเฉพาะรายการนี้ (total_price นับเป็น 0)
            $table->boolean('waived')->default(false)->after('total_price');
        });

        // ตั้งค่าเริ่มต้น: รายการที่ชื่อขึ้นต้น/มีคำว่า "แพ็กเกจ" = เป็นแพ็กเกจ
        DB::table('service_prices')->where('name', 'LIKE', '%แพ็กเกจ%')->update(['is_package' => true]);
    }

    public function down()
    {
        Schema::table('service_prices', function (Blueprint $table) {
            $table->dropColumn('is_package');
        });
        Schema::table('billing_items', function (Blueprint $table) {
            $table->dropColumn('waived');
        });
    }
}
