<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * เติม "ช่วงสูตรแรก" ให้บัตรวัณโรคที่เปิดไว้ก่อนมีระบบประวัติสูตรยา
 *
 * บัตรเดิมเก็บสูตรไว้ในช่อง regimen ช่องเดียว พอถึงวันเปลี่ยนสูตรกลางคอร์ส
 * จะไม่มีช่วงแรกให้ปิด ประวัติจึงเริ่มนับจากสูตรที่สองแทน — เติมย้อนให้ครั้งเดียว (2569-09-24)
 */
return new class extends Migration
{
    public function up()
    {
        $regs = DB::table('tb_registrations')
            ->whereNotNull('regimen')
            ->where('regimen', '<>', '')
            ->get(['id', 'regimen', 'treatment_start_date', 'register_date']);

        $catalogue = DB::table('tb_regimens')->where('active', 1)->get();
        $seeded = 0;

        foreach ($regs as $reg) {
            $has = DB::table('tb_regimen_changes')->where('tb_registration_id', $reg->id)->exists();
            if ($has) { continue; }

            $text  = trim($reg->regimen);
            // แคตตาล็อกมีทั้งแถวเก่าที่มีแต่ชื่อสูตร และแถวใหม่ที่ผูกตัวยาไว้ — เอาแถวที่มีตัวยาก่อน
            $hits  = $catalogue->filter(fn ($r) => strcasecmp($r->regimen, $text) === 0 || strcasecmp((string) $r->name, $text) === 0);
            $match = $hits->first(fn ($r) => ! empty($r->drugs)) ?: $hits->first();
            $drugs = $match && $match->drugs ? array_keys((array) json_decode($match->drugs, true)) : null;

            DB::table('tb_regimen_changes')->insert([
                'tb_registration_id' => $reg->id,
                'regimen'     => $text,
                'category'    => $match->category ?? null,
                'start_month' => 0,
                'start_date'  => $reg->treatment_start_date ?: $reg->register_date,
                'reason'      => 'สูตรเริ่มต้นของคอร์สนี้',
                'drugs'       => $drugs ? json_encode($drugs) : null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
            $seeded++;
        }

        echo "  เติมช่วงสูตรแรกให้บัตรเดิม {$seeded} ใบ\n";
    }

    public function down()
    {
        // ลบเฉพาะแถวที่ migration นี้สร้าง (ดูจากเหตุผลที่เขียนกำกับไว้)
        DB::table('tb_regimen_changes')->where('reason', 'สูตรเริ่มต้นของคอร์สนี้')->delete();
    }
};
