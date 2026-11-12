<?php

namespace App\Http\Controllers;

use App\Models\Config;
use App\Support\SeasonFx;
use Illuminate\Http\Request;

/**
 * หน้าตั้งค่าเอฟเฟกต์เทศกาล/ฤดู (แอดมินขอ 2569-11-03 — อยากแก้เองไม่ต้องแก้โค้ด)
 * เก็บใน configs ชื่อ sfx_* (คอลัมน์ value ยาวได้ 255 — รายการวันเก็บเป็น Y-m-d คั่นจุลภาค พอสำหรับราว 20 ปี)
 */
class SeasonFxController extends Controller
{
    public function index()
    {
        $s = SeasonFx::settings();
        $toTh = fn ($ymd) => preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m) ? "{$m[3]}/{$m[2]}/" . ((int) $m[1] + 543) : $ymd;
        return view('backend.season_fx', [
            's'        => $s,
            'loyText'  => implode("\n", array_map($toTh, $s['loy'])),
            'cnyText'  => implode("\n", array_map($toTh, $s['cny'])),
            'today'    => SeasonFx::today(),
            'upcoming' => SeasonFx::upcoming(6),
        ]);
    }

    public function save(Request $request)
    {
        $errors = [];
        $dates = function (string $field, string $label) use ($request, &$errors) {
            $out = [];
            foreach (preg_split('/[\s,]+/', (string) $request->input($field), -1, PREG_SPLIT_NO_EMPTY) as $v) {
                if (! preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m)) { $errors[] = "{$label}: \"{$v}\" ไม่ใช่วันที่แบบ วว/ดด/ปปปป"; continue; }
                $y = (int) $m[3]; if ($y > 2400) { $y -= 543; }
                if (! checkdate((int) $m[2], (int) $m[1], $y)) { $errors[] = "{$label}: \"{$v}\" ไม่มีวันนี้ในปฏิทิน"; continue; }
                $out[] = sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
            }
            $out = array_values(array_unique($out));
            sort($out);
            return $out;
        };
        $loy = $dates('loy', 'วันลอยกระทง');
        $cny = $dates('cny', 'วันตรุษจีน');
        if (strlen(implode(',', $loy)) > 255 || strlen(implode(',', $cny)) > 255) { $errors[] = 'ใส่วันได้ไม่เกินราว 20 ปีต่อเทศกาล — ลบปีที่ผ่านไปแล้วออกก่อน'; }
        if ($errors) { return back()->withInput()->withErrors($errors); }

        $on = array_keys((array) $request->input('items', []));
        $disabled = array_values(array_diff(array_keys(SeasonFx::ITEMS), $on));

        Config::setValue('sfx_enabled', $request->boolean('enabled') ? '1' : '0');
        Config::setValue('sfx_particles', $request->boolean('particles') ? '1' : '0');
        Config::setValue('sfx_season_particles', $request->boolean('season_particles') ? '1' : '0');
        Config::setValue('sfx_disabled', implode(',', $disabled));
        Config::setValue('sfx_loy', implode(',', $loy));
        Config::setValue('sfx_cny', implode(',', $cny));
        foreach (SeasonFx::NUMS as $k => [$def, $min, $max]) {
            $v = $request->input($k);
            Config::setValue('sfx_' . $k, (string) (is_numeric($v) ? max($min, min($max, (int) $v)) : $def));
        }
        SeasonFx::forgetCache();

        flash('บันทึกการตั้งค่าเอฟเฟกต์แล้ว — เครื่องอื่นจะเห็นเมื่อเปลี่ยนหน้าหรือกด F5', 'success');
        return redirect()->route('season-fx.index');
    }
}
