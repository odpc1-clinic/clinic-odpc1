<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * แยก "หน่วย" ออกจากข้อความระยะเวลาอาการที่พยาบาลเคยพิมพ์รวมกันมา
 *
 * ของเดิมมีช่องเดียว พยาบาลพิมพ์ "2 เดือน" บ้าง "2" เฉย ๆ บ้าง
 * พอมีช่องหน่วยแยกแล้ว ถ้าไม่แยกของเก่าจะกลายเป็น "2 เดือน" + หน่วย "เดือน" ซ้ำกัน (2569-09-28)
 */
return new class extends Migration
{
    public function up()
    {
        $units = ['วัน' => 'day', 'สัปดาห์' => 'week', 'อาทิตย์' => 'week', 'เดือน' => 'month', 'ปี' => 'year'];
        $changed = 0;

        foreach (DB::table('tb_registrations')->whereNotNull('symptoms')->get(['id', 'symptoms']) as $reg) {
            $rows = json_decode($reg->symptoms, true);
            if (! is_array($rows)) { continue; }

            $touched = false;
            foreach ($rows as $i => $row) {
                $text = trim((string) ($row['duration'] ?? ''));
                if ($text === '' || isset($row['unit'])) { continue; }

                $unit = 'month';
                foreach ($units as $th => $code) {
                    if (mb_strpos($text, $th) !== false) {
                        $unit = $code;
                        $text = trim(str_replace($th, '', $text));
                        break;
                    }
                }

                $rows[$i]['duration'] = $text !== '' ? $text : null;
                $rows[$i]['unit']     = $unit;
                $touched = true;
            }

            if ($touched) {
                DB::table('tb_registrations')->where('id', $reg->id)
                    ->update(['symptoms' => json_encode($rows, JSON_UNESCAPED_UNICODE)]);
                $changed++;
            }
        }

        echo "  แยกหน่วยระยะเวลาอาการให้บัตรเดิม {$changed} ใบ\n";
    }

    public function down()
    {
        // ไม่ต้องย้อน: ช่อง unit ที่เพิ่มเข้ามาไม่ได้ทำให้ข้อมูลเดิมหาย
    }
};
