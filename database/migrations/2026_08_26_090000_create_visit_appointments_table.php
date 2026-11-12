<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * นัดหลายวันต่อการมาหนึ่งครั้ง (พยาบาลขอ 2569-08-26)
 *
 * ของเดิม visits.appointment เก็บได้วันเดียว แต่ความจริงคนไข้หนึ่งครั้งอาจได้หลายนัด
 * ที่มีเหตุผลต่างกัน เช่น "2 ก.ย. ฟังผลเลือด" กับ "16 ก.ย. ฉีดยาเข็ม 2"
 *
 * ทำไมไม่ย้ายทิ้งไปเลย:
 *   visits.appointment ถูกอ้างอยู่ 81 จุดใน 20 ไฟล์ (รายงานส่งออก ปฏิทินวันหยุด
 *   หน้ารายชื่อนัด ทะเบียน ContactSTI ฯลฯ) การรื้อทีเดียวหมดเสี่ยงเกินไปกับระบบที่ใช้งานจริง
 *
 * จึงเก็บ visits.appointment ไว้เป็น "เงาของนัดที่ใกล้ที่สุดที่ยังมาไม่ถึง"
 * โค้ดเก่าทุกจุดทำงานต่อได้เหมือนเดิมโดยไม่ต้องแก้ ส่วนของใหม่อ่านจากตารางนี้
 * ตัวซิงก์เงาอยู่ที่ VisitAppointment::syncMirror()
 */
class CreateVisitAppointmentsTable extends Migration
{
    public function up()
    {
        Schema::create('visit_appointments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('visit_id')->index();

            // เก็บซ้ำไว้ตรงนี้ด้วย เพราะการค้นนัดของคนไข้คนหนึ่งเกิดบ่อยมาก
            // (แจ้งเตือน LINE, ใบนัด, เช็คนัดชนกัน) ถ้าต้อง join visits ทุกครั้งจะช้าเปล่า ๆ
            $table->unsignedBigInteger('patient_id')->index();

            // เก็บเป็น พ.ศ. ในคอลัมน์ DATE ให้ตรงกับ visits.appointment และ holidays.holiday_date
            // ห้ามโยนเข้า Carbon::parse() ตรง ๆ จะได้ ค.ศ. 2569 — ใช้ Utillity::BE2AD()
            $table->date('appointment_date')->index();

            // เหตุผลนัด (คนไข้ได้อ่าน) กับโน้ตส่งเวร (คนไข้ไม่ได้อ่าน) แยกกันเหมือนของเดิม
            // ห้ามส่ง staff_note ออก LINE เด็ดขาด — คลินิกโรคติดต่อทางเพศสัมพันธ์
            $table->string('reason', 255)->nullable();
            $table->string('staff_note', 255)->nullable();

            // กันส่งซ้ำ แยกรายนัด ไม่ใช่รายการมาตรวจ
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('confirm_sent_at')->nullable();

            $table->timestamps();

            // เตือนล่วงหน้าค้นด้วย "วันไหน + ยังไม่ได้ส่ง" ทุก 30 นาที
            $table->index(['appointment_date', 'reminder_sent_at'], 'va_date_reminder_idx');
        });

        // ---------- ย้ายของเดิมเข้ามา ----------
        // ทำใน migration เลย ไม่แยกคำสั่ง เพราะถ้าลืมรัน หน้าจอใหม่จะว่างเปล่า
        // ทั้งที่ข้อมูลมีอยู่ ซึ่งพยาบาลจะตีความว่าข้อมูลหาย
        DB::statement("
            INSERT INTO visit_appointments
                (visit_id, patient_id, appointment_date, reason, staff_note, created_at, updated_at)
            SELECT v.id, v.patient_id, v.appointment, v.appointment_reason, v.appointment_staff_note,
                   COALESCE(v.created_at, NOW()), NOW()
            FROM visits v
            WHERE v.appointment IS NOT NULL
        ");

        // ยกสถานะ "เตือนไปแล้ว / ยืนยันไปแล้ว" มาด้วย
        // ลืมข้อนี้ตอนแรก แล้วเกือบทำให้คนไข้ 4 รายได้ข้อความ LINE ซ้ำในวันเดียว
        // (ตัวส่งอ่านจากตารางนี้แล้ว ถ้าปล่อยว่างไว้มันจะนับว่า "ยังไม่เคยส่ง")
        DB::statement("
            UPDATE visit_appointments a JOIN visits v ON v.id = a.visit_id
            SET a.reminder_sent_at = v.reminder_sent_at
            WHERE v.reminder_sent_at IS NOT NULL AND a.appointment_date = v.appointment
        ");
        DB::statement("
            UPDATE visit_appointments a JOIN visits v ON v.id = a.visit_id
            SET a.confirm_sent_at = v.appointment_confirm_sent_at
            WHERE v.appointment_confirm_sent_at IS NOT NULL AND a.appointment_date = v.appointment
        ");
    }

    public function down()
    {
        Schema::dropIfExists('visit_appointments');
    }
}
