<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Form 2 ข้อ 2, 3, 5 — เอามาไว้ในใบคัดกรอง (Form 1) ตามที่คลินิกขอ 2569-09-18
 *
 *   ข้อ 2 = ข้อมูลผู้ป่วยวัณโรคที่ไปสัมผัสมา (index case)
 *   ข้อ 3 = ลักษณะการสัมผัส
 *   ข้อ 4 = อาการ + คะแนน — มีอยู่แล้วในใบคัดกรอง ไม่ทำซ้ำ
 *   ข้อ 5 = รายชื่อสมาชิกที่อยู่บ้านเดียวกัน (ตารางแยก เพราะมีได้หลายคน)
 *
 * ส่วนนี้จะโผล่ในหน้าจอเมื่อระบุว่า "มีประวัติสัมผัสผู้ป่วยวัณโรค" เท่านั้น
 */
class AddContactScreeningToTb extends Migration
{
    public function up()
    {
        Schema::table('tb_screenings', function (Blueprint $table) {
            // ---- ข้อ 2: ผู้ป่วยวัณโรคต้นทาง (index case) ----
            $table->string('index_name')->nullable()->after('contact_tb_detail');
            $table->unsignedSmallInteger('index_age')->nullable()->after('index_name');
            $table->string('index_id_card', 20)->nullable()->after('index_age');
            $table->string('index_diagnosis')->nullable()->after('index_id_card');      // ปอด/หลอดลม/กล่องเสียง/หลังโพรงจมูก
            $table->string('index_dx_hospital')->nullable()->after('index_diagnosis');  // วินิจฉัยจาก รพ.
            $table->string('index_treat_hospital')->nullable()->after('index_dx_hospital'); // ปัจจุบันรักษาที่ รพ.

            // ---- ข้อ 3: ลักษณะการสัมผัส (ติ๊กได้หลายข้อ) ----
            $table->boolean('ct_same_house')->default(false)->after('index_treat_hospital');  // 3.1 อยู่บ้านเดียวกันใน 3 เดือน
            $table->boolean('ct_same_room')->default(false)->after('ct_same_house');          //     - นอนห้องเดียวกัน
            $table->boolean('ct_aircon')->default(false)->after('ct_same_room');              //     - บ้านเป็นห้องแอร์ ใช้แอร์ร่วมกัน
            $table->boolean('ct_close')->default(false)->after('ct_aircon');                  // 3.2 อยู่ใกล้ชิด (ไม่ได้อยู่บ้านเดียวกัน)
            $table->boolean('ct_close_8h')->default(false)->after('ct_close');                //     - มากกว่า 8 ชม. ใน 1 วัน
            $table->boolean('ct_close_120h')->default(false)->after('ct_close_8h');           //     - ตั้งแต่ 120 ชม. ใน 1 เดือน
            $table->string('ct_other')->nullable()->after('ct_close_120h');                   // 3.3 อื่นๆ
        });

        // ---- ข้อ 5: สมาชิกที่อยู่บ้านเดียวกันกับผู้ป่วยวัณโรคใน 3 เดือนที่ผ่านมา ----
        Schema::create('tb_household_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tb_screening_id')->index();

            $table->string('name');
            $table->unsignedSmallInteger('age')->nullable();
            $table->string('relation', 100)->nullable();     // ความสัมพันธ์กับผู้ป่วย
            $table->string('disease')->nullable();           // โรคประจำตัว
            $table->date('screened_date')->nullable();       // วันที่ตรวจคัดกรองวัณโรค (พ.ศ.)

            // ถ้าคนในบ้านมาเป็นคนไข้ของคลินิกเองด้วย ผูก HN ไว้ จะได้กดข้ามไปดูประวัติได้
            $table->unsignedInteger('patient_id')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tb_household_members');

        Schema::table('tb_screenings', function (Blueprint $table) {
            $table->dropColumn([
                'index_name', 'index_age', 'index_id_card', 'index_diagnosis',
                'index_dx_hospital', 'index_treat_hospital',
                'ct_same_house', 'ct_same_room', 'ct_aircon',
                'ct_close', 'ct_close_8h', 'ct_close_120h', 'ct_other',
            ]);
        });
    }
}
