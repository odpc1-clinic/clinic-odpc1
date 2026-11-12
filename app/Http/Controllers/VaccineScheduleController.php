<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Services\VaccineSchedule;
use Illuminate\Http\Request;

/**
 * ตารางระยะนัดวัคซีน — หมอแก้เองได้ (คลินิก TMC ขอ 2569-09-30)
 * ระบบใช้ตารางนี้นัดเข็มถัดไปอัตโนมัติ ดู App\Services\VaccineSchedule
 */
class VaccineScheduleController extends Controller
{
    public function index()
    {
        $vaccines = Medicine::where('is_vaccine', 1)->orderBy('name')->get();

        return view('vaccine_schedule.index', compact('vaccines'));
    }

    public function save(Request $request)
    {
        $bad = [];
        $changed = 0;
        foreach ((array) $request->input('v', []) as $id => $row) {
            $med = Medicine::where('is_vaccine', 1)->find((int) $id);
            if (! $med || ! is_array($row)) { continue; }

            $spec = trim(preg_replace('/\s*,\s*/', ', ', (string) ($row['intervals'] ?? '')));
            // ว่าง = ไม่นัดอัตโนมัติ · รูปแบบผิด = ไม่บันทึกตัวนั้น แล้วบอกชื่อ
            if ($spec !== '' && ! VaccineSchedule::parse($spec)) { $bad[] = $med->name; continue; }

            $med->vaccine_intervals     = $spec !== '' ? strtolower($spec) : null;
            $med->vaccine_schedule_note = mb_substr(trim((string) ($row['note'] ?? '')), 0, 255) ?: null;
            if ($med->isDirty()) { $med->save(); $changed++; }
        }

        flash($bad
            ? 'บันทึกแล้ว ' . $changed . ' รายการ · รูปแบบไม่ถูก (ไม่ได้บันทึก): ' . implode(', ', $bad)
            : 'บันทึกตารางนัดวัคซีนแล้ว (' . $changed . ' รายการ)', $bad ? 'warning' : 'success');

        return redirect()->route('vaccine-schedule.index');
    }
}
