<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * รายการยาวัณโรค + สูตรยา — นำเข้าจากไฟล์ "รายการยาวัณโรค.xlsx" ของคลินิก (2569-09-23)
 *
 * แยกจากตาราง medicines (คลังยาของห้องยา) โดยตั้งใจ:
 *   ตารางนี้เป็น "แคตตาล็อกอ้างอิง" ว่าสูตรไหนใช้ยาอะไร ไม่ได้ผูกกับสต๊อก/ราคา
 *   ห้องยายังจ่ายยาจากคลังเดิมตามปกติ
 *
 * กลุ่มยา: DS = ไม่ดื้อยา · Resist = ดื้อยา · TPT = ยาป้องกันในผู้สัมผัส
 * ยาหนึ่งตัวอยู่ได้หลายกลุ่ม (เช่น INH ใช้ทั้ง DS และ TPT) จึงเก็บเป็น JSON
 */
class CreateTbDrugTables extends Migration
{
    public function up()
    {
        Schema::create('tb_drugs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('dose', 50)->nullable();     // ขนาดต่อเม็ด เช่น "300 mg"
            $table->json('groups')->nullable();         // ["DS","Resist","TPT"]
            $table->string('note')->nullable();         // เช่น "จ-พ-ศ, OD" หรือ "กินคู่ INH, Lzd เสมอ"
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('tb_regimens', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50);   // DS-TB · MDR-Longer regimen · MDR-Shorter Regimem · Bpal · BPalM · TPT
            $table->string('regimen');        // เช่น 2HRZE/4HR
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $now  = now();
        $sort = 0;
        foreach ([
            ['4-FDC (Rimstar) I75-R150-Z400-E275', '', ['DS'], ''],
            ['2FDC (RIMACTAZID/ Rifanah) I75-R150', '', ['DS'], ''],
            ['Isoniazid (INH)', '100 mg', ['DS', 'TPT'], ''],
            ['Rifampicin (R)', '300 mg', ['DS', 'TPT'], ''],
            ['Rifampicin (R)', '450 mg', ['DS', 'TPT'], ''],
            ['Pyrazibamide (Z)', '500 mg.', ['DS', 'Resist'], ''],
            ['Ethambutol (E)', '400 mg', ['DS', 'Resist'], ''],
            ['Ethambutol (E)', '500 mg', ['DS', 'Resist'], ''],
            ['Moxiflox (Mfx)', '500 mg.', ['DS', 'Resist'], ''],
            ['Moxiflox (Mfx)', '400 mg.', ['DS', 'Resist'], ''],
            ['Levoflox (Lfx.)', '500 mg.', ['DS', 'Resist'], ''],
            ['Levoflox (Lfx.)', '250 mg.', ['DS', 'Resist'], ''],
            ['Prothionamide (Pto)', '500 mg.', ['Resist'], ''],
            ['Prothionamide (Pto)', '250 mg.', ['Resist'], ''],
            ['Clofazimine (Cfz)', '100 mg.', ['Resist'], ''],
            ['Isoniazid (INH)', '300 mg', ['Resist', 'TPT'], ''],
            ['Bedaquiline (Bdq)', '100 mg.', ['Resist'], 'จ-พ-ศ, OD'],
            ['Pretomanid (Pa)', '200 mg.', ['Resist'], ''],
            ['Linezolid (Lzd)', '600 mg.', ['Resist'], ''],
            ['Pyridoxine (B6)', '50 mg.', ['DS', 'Resist'], 'กินคู่ INH, Lzd เสมอ'],
        ] as [$name, $dose, $groups, $note]) {
            DB::table('tb_drugs')->insert([
                'name'       => $name,
                'dose'       => $dose ?: null,
                'groups'     => json_encode($groups, JSON_UNESCAPED_UNICODE),
                'note'       => $note ?: null,
                'sort'       => ++$sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $sort = 0;
        foreach ([
            ['DS-TB', '2HRZE/4HR'],
            ['DS-TB', '2HRZE/7HR'],
            ['MDR-Longer regimen', '4-6 Bdq*-Mfx(Lfx)-Pto(Eto)-Cfz-Z-Hhigh-dose-E / 5 Mfx(Lfx)-Cfz-Z-E'],
            ['MDR-Shorter Regimem', '> 6Km5LfxEtoCs+ PAS/>12LfxEtoCs+ PAS'],
            ['Bpal', 'Bpal'],
            ['BPalM', 'BPalM'],
            ['TPT', '6-9 INH'],
            ['TPT', '9-12INH+B6'],
            ['TPT', 'B6'],
            ['TPT', '3-4R'],
            ['TPT', '3-4HR'],
        ] as [$cat, $reg]) {
            DB::table('tb_regimens')->insert([
                'category'   => $cat,
                'regimen'    => $reg,
                'sort'       => ++$sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('tb_regimens');
        Schema::dropIfExists('tb_drugs');
    }
}
