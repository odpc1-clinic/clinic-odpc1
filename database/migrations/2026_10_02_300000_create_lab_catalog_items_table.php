<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * แคตตาล็อกแล็บรวม — รายการตรวจทุกตัวที่สั่งได้ อยู่ในที่เดียว (ผู้ดูแลคลินิกตัดสินใจ 2569-10-02)
 *
 * ทำไมต้องมีตารางนี้: เดิมแต่ละคลินิกสั่งแล็บคนละหน้าจอ (หน้าสั่ง Lab เต็มของ STI, แผงเลือดของ TB,
 * ตัวเลือก Lab นอก, ช่องส่งตรวจวัณโรค) เปิดคลินิกใหม่ทีไรต้องทำหน้าสั่งแล็บใหม่
 * ตารางนี้เป็นแค่ "เมนู" — ไม่เก็บสิ่งที่สั่ง ของที่สั่งจริงยังลงที่เดิมทุกอย่าง:
 *   in_house   -> lab_subs (ผ่าน App\Services\LabSubOrder)            lab_method / lab_specimen
 *   external   -> visit_external_labs (ExternalLabController)          external_lab_test_id
 *   tb_sendout -> tb_screenings ช่อง lab_* (TbScreening::saveLabSection) tb_key
 * ระบบรายงาน/บิล/ห้อง Lab จึงไม่ต้องแก้อะไร
 *
 * target = กุญแจไม่ซ้ำของปลายทาง (เช่น in_house:6, external:41, tb:afb) — ใช้ให้คำสั่งเติมแคตตาล็อกรันซ้ำได้โดยไม่เกิดรายการซ้ำ
 * หมวด (category) เป็นรายการตายตัวในโค้ด (App\Models\LabCatalogItem::CATEGORIES) ไม่แยกตาราง
 * เพราะหมวดแทบไม่เปลี่ยน และลำดับหมวดต้องตรงกันทุกคลินิก
 */
class CreateLabCatalogItemsTable extends Migration
{
    public function up()
    {
        Schema::create('lab_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->nullable();                     // รหัสแล็บนอก (N620) / ชื่อย่อ ใช้ค้นหา
            $table->string('category', 40)->index();
            $table->enum('kind', ['in_house', 'external', 'tb_sendout']);
            $table->string('target', 60)->unique();
            $table->unsignedSmallInteger('lab_method')->nullable();      // LabSub::$methodOption
            $table->unsignedSmallInteger('lab_specimen')->nullable();    // ตำแหน่งตั้งต้น (Gram/Wet/Culture/PCR)
            $table->unsignedBigInteger('external_lab_test_id')->nullable();
            $table->string('tb_key', 30)->nullable();                    // afb / mol:xpert / culture_dst / tst / cytb / igra
            $table->decimal('default_price', 10, 2)->nullable();         // ราคาเก็บคนไข้ตั้งต้น (แล็บนอก/อื่น ๆ) — แล็บในคลินิกใช้ lab_prices
            $table->string('price_note')->nullable();
            $table->json('clinics')->nullable();                          // [1,2,3] คลินิกที่เห็นรายการนี้ · null = ทุกคลินิก
            $table->boolean('active')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('lab_catalog_items');
    }
}
