<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B6 (Pyridoxine) 50 มก. ขนาดคงที่ — คลินิกขอให้มีคอลัมน์ B6 ในตารางขนาดยาทุกเคส
 *
 * B6 กินคู่ INH และ Linezolid เสมอเพื่อกันปลายประสาทอักเสบ ไม่ได้คิดตามน้ำหนัก
 * จึงเป็นขนาดคงที่ทุกกลุ่ม (ไวต่อยา · ดื้อยา · ยาป้องกัน) — 2569-09-28
 *
 * เด็กยังไม่ใส่เกณฑ์ เพราะขนาดในเด็กไม่ใช่ 50 มก. คงที่ รอคลินิกยืนยันตัวเลขก่อน
 */
return new class extends Migration
{
    public function up()
    {
        $now = now();

        // ของเดิมในกลุ่มยาป้องกันเป็นข้อความช่วง "10–100 มก." -> เปลี่ยนเป็นขนาดคงที่ตามที่คลินิกใช้จริง
        DB::table('tb_dose_rules')
            ->where('group', 'TPT')->where('patient_type', 'adult')->where('drug_key', 'B6')
            ->update([
                'dose_mg'   => 50,
                'dose_text' => null,
                'strength'  => '50 mg',
                'note'      => 'ขนาดคงที่ 50 มก./วัน — กินคู่ INH เสมอ',
                'updated_at' => $now,
            ]);

        foreach (['DS' => 1020, 'Resist' => 1021] as $group => $sort) {
            $exists = DB::table('tb_dose_rules')
                ->where('group', $group)->where('patient_type', 'adult')->where('drug_key', 'B6')->exists();
            if ($exists) { continue; }

            DB::table('tb_dose_rules')->insert([
                'group'        => $group,
                'patient_type' => 'adult',
                'drug_key'     => 'B6',
                'drug_label'   => 'Pyridoxine (B6)',
                'strength'     => '50 mg',
                'weight_min'   => null,
                'weight_max'   => null,
                'dose_mg'      => 50,
                'dose_text'    => null,
                'note'         => 'ขนาดคงที่ 50 มก./วัน — กินคู่ INH และ Lzd เสมอ',
                'sort'         => $sort,
                'active'       => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        // ให้ B6 อยู่ในกลุ่มยาป้องกันด้วย (เดิมติดไว้แค่ DS กับ Resist)
        foreach (DB::table('tb_drugs')->where('name', 'like', '%B6%')->get(['id', 'groups']) as $d) {
            $groups = json_decode($d->groups, true) ?: [];
            if (! in_array('TPT', $groups, true)) {
                $groups[] = 'TPT';
                DB::table('tb_drugs')->where('id', $d->id)
                    ->update(['groups' => json_encode($groups), 'updated_at' => $now]);
            }
        }

        echo "  ตั้งขนาด B6 คงที่ 50 มก. ให้ทุกกลุ่มแล้ว\n";
    }

    public function down()
    {
        DB::table('tb_dose_rules')->whereIn('group', ['DS', 'Resist'])
            ->where('drug_key', 'B6')->delete();
    }
};
