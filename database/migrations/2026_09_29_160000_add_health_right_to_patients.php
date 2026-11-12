<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * สิทธิการรักษา + สถานพยาบาลหลักของคนไข้ (2569-09-29)
 *
 * ที่มา: ใบนัดของคลินิกวัณโรคต้องพิมพ์ "สิทธิหลัก" และ "สถานพยาบาลหลัก" ลงไปด้วย
 * เดิมเจ้าหน้าที่ต้องเปิดดูจากระบบของโรงพยาบาลแล้วพิมพ์ซ้ำเองทุกใบ
 * เก็บไว้ที่ทะเบียนคนไข้ตั้งแต่แรกจะได้ดึงมาใช้ได้ทุกครั้งที่ออกใบนัด
 *
 * เก็บที่ patients ไม่ใช่ visits เพราะสิทธิเป็นของตัวคนไข้ ไม่ได้เปลี่ยนรายครั้งที่มา
 * (ถ้าวันหนึ่งคนไข้ย้ายสิทธิ ก็แก้ที่ทะเบียนแล้วใบนัดครั้งต่อไปได้ค่าใหม่เอง)
 *
 * โรงพยาบาลเป็นข้อความอิสระตามที่ผู้ใช้เลือก ไม่ผูกกับตารางรหัสสถานพยาบาลของกระทรวง
 * เพราะยังไม่มีตารางนั้นในระบบ และเจ้าหน้าที่พิมพ์ชื่อที่คนไข้บอกได้เร็วกว่ารอค้นรหัส
 *
 * ปล่อยว่างได้ทั้งคู่ — คนไข้เก่า 4,137 คนยังไม่มีข้อมูลนี้ และสิทธิบางอย่าง
 * (เบิกราชการ / ชำระเงินเอง) ไม่ผูกกับสถานพยาบาลหลักอยู่แล้ว
 */
class AddHealthRightToPatients extends Migration
{
    public function up()
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('health_right', 30)->nullable()->after('marital_status');
            $table->string('health_right_hospital', 255)->nullable()->after('health_right');
        });
    }

    public function down()
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['health_right', 'health_right_hospital']);
        });
    }
}
