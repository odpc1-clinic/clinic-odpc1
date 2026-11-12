<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * สูตรเม็ดรวมแบบครบคอร์ส — 2HRZE/4HR (เม็ดรวม)
 *
 * ของเดิมแยกเป็นสองสูตร (2HRZE เม็ดรวม / 4HR เม็ดรวม) ซึ่งต้องมาเปลี่ยนสูตรอีกครั้งตอนเข้าระยะต่อเนื่อง
 * ในทางปฏิบัติหมอสั่งทีเดียวทั้งคอร์ส สูตรนี้จึงเปิดช่องยาไว้ทั้ง 4-FDC (ระยะเข้มข้น)
 * และ 2-FDC (ระยะต่อเนื่อง) เดือนไหนใช้ตัวไหนก็กรอกช่องนั้น เหมือนสูตรแยกเม็ดที่เปิด H R Z E ไว้ทั้งคอร์ส
 *
 * คลินิกถามถึงระยะเข้มข้น 2569-09-28
 */
return new class extends Migration
{
    public function up()
    {
        if (DB::table('tb_regimens')->where('code', 'R10')->exists()) { return; }

        DB::table('tb_regimens')->insert([
            'code'     => 'R10',
            'name'     => 'DS-TB (2HRZE/4HR เม็ดรวม)',
            'category' => 'ไวต่อยา · ครบคอร์ส แบบเม็ดรวม',
            'regimen'  => '2HRZE/4HR (FDC)',
            'drugs'    => json_encode(['4FDC' => 1, '2FDC' => 1]),
            'sort'     => 110,
            'active'   => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        echo "  เพิ่มสูตร 2HRZE/4HR (เม็ดรวม) แล้ว\n";
    }

    public function down()
    {
        DB::table('tb_regimens')->where('code', 'R10')->delete();
    }
};
