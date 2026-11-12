<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * สามเรื่องในชุดเดียว (คลินิกขอ 2569-09-24)
 *
 * 1) เปลี่ยนสูตรยากลางคันต้องเก็บสูตรเดิมไว้ -> ตาราง tb_regimen_changes
 *    บัตรใบเดียวมีได้หลายช่วงสูตร เช่น เดือน 0-2 ใช้ 2HRZE แล้วเดือน 3 เปลี่ยนเป็น 4HR
 *    ของเดิมเก็บสูตรเดียวในช่อง regimen ทับไปเรื่อย ๆ ประวัติหาย
 *
 * 2) ช่องยาในตารางขนาดยารายเดือนเปลี่ยนตัวยาได้ -> เก็บเป็น JSON แทนคอลัมน์ตายตัว
 *    ของเดิมล็อกไว้ 6 ช่อง (H R E Z S FDC) เคสดื้อยาไม่มี Streptomycin แต่ต้องใช้ Lfx/Bdq/Lzd
 *
 * 3) รายการยา/สูตรยาชุดใหม่จากไฟล์ Datasheet.xlsx ของคลินิก (มีรหัสยา M01-M11 และจำนวนเม็ดต่อสูตร)
 */
class TbRegimenHistoryAndDrugCodes extends Migration
{
    public function up()
    {
        /* ---------- 1) ประวัติการใช้สูตรยา ---------- */
        Schema::create('tb_regimen_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tb_registration_id')->index();

            $table->string('regimen');                     // ข้อความสูตร เช่น 2HRZE
            $table->string('category', 50)->nullable();    // กลุ่มสูตร (DS-TB / MDR ฯลฯ)
            $table->unsignedTinyInteger('start_month')->nullable();  // เริ่มใช้เดือนที่เท่าไหร่ของการรักษา
            $table->date('start_date')->nullable();        // พ.ศ.
            $table->date('end_date')->nullable();          // ว่าง = ยังใช้อยู่
            $table->string('reason')->nullable();          // เหตุผลที่เปลี่ยน เช่น ผลดื้อยา / แพ้ยา

            // ยาที่ใช้ในช่วงนี้ ["H","R","Z","E"] — ใช้กำหนดว่าตารางขนาดยาจะมีช่องยาอะไรบ้าง
            $table->json('drugs')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        /* ---------- 2) ขนาดยารายเดือนเก็บเป็น JSON ---------- */
        Schema::table('tb_monthly_doses', function (Blueprint $table) {
            // {"H":"300","R":"600","Lfx":"750"} — คีย์คือรหัสยา ค่าคือขนาดที่สั่ง
            $table->json('doses')->nullable()->after('weight');
        });

        // ย้ายข้อมูลเดิม (ถ้ามี) เข้า JSON แล้วค่อยทิ้งคอลัมน์ตายตัว
        foreach (DB::table('tb_monthly_doses')->get() as $row) {
            $map = ['dose_h' => 'H', 'dose_r' => 'R', 'dose_e' => 'E', 'dose_z' => 'Z', 'dose_s' => 'S', 'dose_fdc' => 'FDC'];
            $doses = [];
            foreach ($map as $col => $key) {
                if (! empty($row->$col)) { $doses[$key] = $row->$col; }
            }
            if ($doses) {
                DB::table('tb_monthly_doses')->where('id', $row->id)
                    ->update(['doses' => json_encode($doses, JSON_UNESCAPED_UNICODE)]);
            }
        }

        Schema::table('tb_monthly_doses', function (Blueprint $table) {
            $table->dropColumn(['dose_h', 'dose_r', 'dose_e', 'dose_z', 'dose_s', 'dose_fdc']);
        });

        /* ---------- 3) รหัสยา + สูตรยาชุดใหม่จาก Datasheet.xlsx ---------- */
        Schema::table('tb_drugs', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->after('id');        // M01..M11 (ใช้ร่วมกับระบบ vDOT)
            $table->string('appearance')->nullable()->after('note');    // ลักษณะเม็ดยา ไว้ให้ AI/คนดูรูปตรวจสอบ
        });

        $now = now();
        foreach ([
            ['M01', 'Isoniazid (H)',    'เม็ดกลมสีขาว ขนาดเล็ก ผิวเรียบ'],
            ['M02', 'Rifampicin (R)',   'แคปซูลสีส้มแดง หรือ แคปซูลสีส้ม-น้ำตาล'],
            ['M03', 'Pyrazinamide (Z)', 'เม็ดกลมสีขาว ขนาดใหญ่ มีรอยบากครึ่งเม็ด'],
            ['M04', 'Ethambutol (E)',   'เม็ดรีทรงกระบอกสีขาว หรือ เม็ดกลมโตสีขาวผิวเคลือบ'],
            ['M05', 'Levofloxacin (Lfx)', 'เม็ดรีเคลือบฟิล์มสีชมพูเข้ม หรือ สีส้มอ่อน'],
            ['M06', 'Moxifloxacin (Mfx)', 'เม็ดรีรูปไข่เคลือบฟิล์มสีแดงอิฐ หรือ สีเหลืองเข้ม'],
            ['M07', 'Bedaquiline (Bdq)', 'เม็ดกลมแบนสีขาวนวลหรือสีครีม'],
            ['M08', 'Linezolid (Lzd)',  'เม็ดรีเคลือบฟิล์มสีขาว มีรอยบาก'],
            ['M09', 'Clofazimine (Cfz)', 'แคปซูลนิ่มทรงกลมรีสีน้ำตาลเข้มจัดหรือสีช็อกโกแลต'],
            ['M10', 'Cycloserine (Cs)', 'แคปซูลสีทูโทน เช่น ขาว-แดง หรือ ขาว-เทา'],
            ['M11', 'Pretomanid (Pa)',  'เม็ดรีรูปไข่สีขาวถึงขาวนวล'],
        ] as [$code, $name, $look]) {
            // ยาตัวไหนมีอยู่แล้วในแคตตาล็อกให้เติมรหัส/ลักษณะ ไม่สร้างซ้ำ
            $short = preg_match('/\(([^)]+)\)/', $name, $m) ? $m[1] : $name;
            $existing = DB::table('tb_drugs')->where('name', 'like', '%(' . $short . ')%')->first();

            if ($existing) {
                DB::table('tb_drugs')->where('id', $existing->id)
                    ->update(['code' => $code, 'appearance' => $look, 'updated_at' => $now]);
            } else {
                DB::table('tb_drugs')->insert([
                    'code' => $code, 'name' => $name, 'groups' => json_encode(['Resist'], JSON_UNESCAPED_UNICODE),
                    'appearance' => $look, 'sort' => 100, 'active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        Schema::table('tb_regimens', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->after('id');      // R01..R07
            $table->string('name')->nullable()->after('code');        // ชื่อสูตรแบบอ่านง่าย
            $table->json('drugs')->nullable()->after('regimen');      // {"H":1,"R":1,"Z":3,"E":3} = ยา:จำนวนเม็ด
        });

        $sort = 100;
        foreach ([
            ['R01', 'DS-TB (2HRZE)',          'ไวต่อยา (ช่วงเข้มข้น 2 เดือนแรก)',        '2HRZE',      ['H' => 1, 'R' => 1, 'Z' => 3, 'E' => 3]],
            ['R02', 'DS-TB (4HR)',            'ไวต่อยา (ช่วงประคับประคอง 4 เดือนหลัง)', '4HR',        ['H' => 1, 'R' => 1]],
            ['R03', 'Hr-TB (6RZE-Lfx)',       'ดื้อยา Isoniazid ตัวเดียว',               '6RZE-Lfx',   ['R' => 1, 'Z' => 3, 'E' => 3, 'Lfx' => 1]],
            ['R04', 'BPaLM (6 เดือน)',        'ดื้อยาหลายขนาน (MDR/RR-TB สูตรสั้นใหม่)', 'BPaLM',      ['Bdq' => 1, 'Pa' => 1, 'Lzd' => 1, 'Mfx' => 1]],
            ['R05', 'BPaL (6 เดือน)',         'ดื้อยาหลายขนานรุนแรง (Pre-XDR-TB)',       'BPaL',       ['Bdq' => 1, 'Pa' => 1, 'Lzd' => 1]],
            ['R06', 'Shorter DR (9-11 เดือน)', 'ดื้อยาสูตรสั้นกินล้วน',                   'Shorter DR', ['Bdq' => 1, 'Lfx' => 1, 'Lzd' => 1, 'Cfz' => 1, 'Z' => 3, 'E' => 3]],
            ['R07', 'Individualized Regimen', 'ดื้อยาสูตรยาว 18-20 เดือน (ปรับรายบุคคล)', 'Individualized', []],
        ] as [$code, $name, $category, $regimen, $drugs]) {
            DB::table('tb_regimens')->insert([
                'code'     => $code,
                'name'     => $name,
                'category' => $category,
                'regimen'  => $regimen,
                'drugs'    => $drugs ? json_encode($drugs, JSON_UNESCAPED_UNICODE) : null,
                'sort'     => ++$sort,
                'active'   => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down()
    {
        DB::table('tb_regimens')->whereNotNull('code')->delete();

        Schema::table('tb_regimens', function (Blueprint $table) {
            $table->dropColumn(['code', 'name', 'drugs']);
        });
        Schema::table('tb_drugs', function (Blueprint $table) {
            $table->dropColumn(['code', 'appearance']);
        });

        Schema::table('tb_monthly_doses', function (Blueprint $table) {
            $table->string('dose_h', 30)->nullable();
            $table->string('dose_r', 30)->nullable();
            $table->string('dose_e', 30)->nullable();
            $table->string('dose_z', 30)->nullable();
            $table->string('dose_s', 30)->nullable();
            $table->string('dose_fdc', 30)->nullable();
            $table->dropColumn('doses');
        });

        Schema::dropIfExists('tb_regimen_changes');
    }
}
