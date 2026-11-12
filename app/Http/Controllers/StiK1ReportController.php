<?php

namespace App\Http\Controllers;

use App\Services\StiK1Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * รายงาน ก.1 คลินิก STI รายเดือน (2569-11-10)
 * ตัวเลขระบบคิดใน App\Services\StiK1Report · พยาบาลแก้ทับได้ทุกช่อง เพราะหลายบรรทัดระบบไม่มีข้อมูล/นับยาก (คลินิกขอ)
 * ค่าที่แก้เก็บใน configs ทีละช่อง ชื่อ "k1:<ปีเดือน พ.ศ.>:<แถว>:<ช่อง>" — ไม่ต้องเพิ่มตาราง · ลบค่าออก = กลับไปใช้ตัวเลขระบบ
 */
class StiK1ReportController extends Controller
{
    private const TH_MONTHS = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

    /** เดือน พ.ศ. "2569-10" — ค่าตั้งต้น = เดือนที่แล้ว เพราะรายงานทำหลังปิดเดือน */
    private function month(Request $request): array
    {
        $ym = (string) $request->input('month', '');
        if (! preg_match('/^(\d{4})-(\d{2})$/', $ym, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
            $d = now()->subMonthNoOverflow();
            $m = [null, (string) ($d->year + 543), $d->format('m')];
        }
        $y = (int) $m[1]; $mo = (int) $m[2];
        if ($y < 2400) { $y += 543; }
        return [sprintf('%04d-%02d', $y, $mo), $y, $mo];
    }

    public function index(Request $request)
    {
        [$ym, $y, $m] = $this->month($request);
        $from = $ym . '-01';
        $to   = sprintf('%s-%02d', $ym, (int) date('t', mktime(0, 0, 0, $m, 1, $y - 543)));

        $report = (new StiK1Report())->build($from, $to);

        $saved = DB::table('configs')->where('name', 'like', 'k1:' . $ym . ':%')->pluck('value', 'name');
        $overrides = [];
        foreach ($saved as $name => $value) {
            [, , $key, $col] = array_pad(explode(':', $name, 4), 4, '');
            $overrides[$key][$col] = $value;
        }

        // เลือกเดือนย้อนหลัง 24 เดือน
        $months = [];
        $d = now()->startOfMonth();
        for ($i = 0; $i < 24; $i++) {
            $months[sprintf('%04d-%02d', $d->year + 543, $d->month)] = self::TH_MONTHS[$d->month] . ' ' . ($d->year + 543);
            $d->subMonthNoOverflow();
        }

        return view('report.sti_k1', [
            'ym' => $ym, 'monthTh' => self::TH_MONTHS[$m] . ' ' . $y, 'months' => $months,
            'report' => $report, 'overrides' => $overrides,
        ]);
    }

    /** บันทึกตัวเลขที่พยาบาลแก้ทีละช่อง (autosave) · ค่าว่าง = ลบ กลับไปใช้ตัวเลขระบบ */
    public function save(Request $request)
    {
        $data = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'key'   => ['required', 'regex:/^[a-z0-9.]{1,20}$/'],
            'col'   => ['required', 'in:' . implode(',', StiK1Report::COLS)],
            'value' => ['nullable', 'string', 'max:20'],
        ]);
        $name  = 'k1:' . $data['month'] . ':' . $data['key'] . ':' . $data['col'];
        $value = trim((string) ($data['value'] ?? ''));

        if ($value === '') {
            DB::table('configs')->where('name', $name)->delete();
        } else {
            if (! preg_match('/^\d{1,7}$/', $value)) {
                return response()->json(['ok' => false, 'msg' => 'ใส่ได้เฉพาะตัวเลข'], 422);
            }
            $now = now();
            // เช็คว่ามีแถวก่อน — update() คืน 0 เมื่อค่าเท่าเดิม ถ้าดูจากค่านั้นจะได้แถวซ้ำ
            if (DB::table('configs')->where('name', $name)->exists()) {
                DB::table('configs')->where('name', $name)->update(['value' => $value, 'updated_at' => $now]);
            } else {
                DB::table('configs')->insert(['name' => $name, 'value' => $value, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        return response()->json(['ok' => true]);
    }
}
