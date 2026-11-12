<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\Visit;
use App\Utillity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RealRashid\SweetAlert\Facades\Alert;

class HolidayController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $years = Holiday::selectRaw('LEFT(holiday_date, 4) AS y')
            ->distinct()->orderByDesc('y')->pluck('y')->all();

        // ค่าเริ่มต้นเป็นปีปัจจุบัน ถ้ายังไม่มีข้อมูลปีนั้นก็เอาปีที่ใหม่ที่สุดที่มี
        $thisYear = (string) (date('Y') + 543);
        $year = (string) $request->input('year', in_array($thisYear, $years, true) ? $thisYear : ($years[0] ?? $thisYear));

        $holidays = Holiday::where('holiday_date', 'like', $year . '-%')
            ->orderBy('holiday_date')->get();

        // นัดที่ค้างอยู่ในวันหยุด — ตรงนี้คือเหตุผลหลักที่ทำหน้านี้ขึ้นมา
        // ถ้ามีนัดตกวันหยุด ระบบเดิมจะส่ง LINE เตือนให้คนไข้มาวันที่คลินิกปิด
        //
        // นับเฉพาะนัดที่ยังไม่ถึง — นัดที่ผ่านไปแล้วเลื่อนไม่ได้ ขึ้นมาก็มีแต่กลบของที่ต้องทำจริง
        // (ตอนเปิดใช้ครั้งแรกมี 24 ราย แต่ 23 รายเป็นของที่ผ่านไปแล้ว)
        $todayBE = Utillity::AD2BE(date('Y-m-d'));
        $clash = Visit::whereIn('appointment', $holidays->pluck('holiday_date'))
            ->where('appointment', '>=', $todayBE)
            ->selectRaw('appointment, COUNT(*) AS n')
            ->groupBy('appointment')->pluck('n', 'appointment')->all();

        return view('holiday.index', [
            'holidays' => $holidays,
            'years'    => $years,
            'year'     => $year,
            'clash'    => $clash,
            'types'    => Holiday::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Holiday::create($data);

        Alert::success('เพิ่มวันหยุดแล้ว', $this->thaiDate($data['holiday_date']) . ' — ' . $data['name']);
        return redirect()->route('holiday.index', ['year' => substr($data['holiday_date'], 0, 4)]);
    }

    public function update(Request $request, $id)
    {
        $holiday = Holiday::findOrFail($id);
        $holiday->update($this->validated($request, $holiday->id));

        Alert::success('บันทึกแล้ว', '');
        return redirect()->route('holiday.index', ['year' => substr($holiday->holiday_date, 0, 4)]);
    }

    /** เปิด/ปิดว่าวันนั้นคลินิกปิดจริงไหม — เก็บวันไว้แต่ไม่ล็อกปฏิทิน */
    public function toggle($id)
    {
        $holiday = Holiday::findOrFail($id);
        $holiday->is_closed = ! $holiday->is_closed;
        $holiday->save();

        Alert::success(
            $holiday->is_closed ? 'ล็อกวันนี้แล้ว' : 'เปิดให้นัดได้แล้ว',
            $this->thaiDate($holiday->holiday_date) . ' — ' . $holiday->name
        );
        return redirect()->route('holiday.index', ['year' => substr($holiday->holiday_date, 0, 4)]);
    }

    public function destroy($id)
    {
        $holiday = Holiday::findOrFail($id);
        $year = substr($holiday->holiday_date, 0, 4);
        $holiday->delete();

        Alert::success('ลบวันหยุดแล้ว', '');
        return redirect()->route('holiday.index', ['year' => $year]);
    }

    private function validated(Request $request, $ignoreId = null): array
    {
        $data = $request->validate([
            'holiday_date' => [
                'required', 'date_format:d/m/Y',
                function ($attr, $value, $fail) {
                    // ช่องนี้กรอกเป็น พ.ศ. เสมอ (ปฏิทินไทยของระบบ) — ถ้าได้ ค.ศ. มาแปลว่ากรอกผิด
                    $year = (int) substr($value, -4);
                    if ($year < 2400) {
                        $fail('กรุณากรอกปีเป็น พ.ศ. เช่น ' . (date('Y') + 543));
                    }
                },
            ],
            'name'      => ['required', 'string', 'max:255'],
            'type'      => ['required', Rule::in(array_keys(Holiday::TYPES))],
            'note'      => ['nullable', 'string', 'max:255'],
        ], [], [
            'holiday_date' => 'วันที่',
            'name'         => 'ชื่อวันหยุด',
            'type'         => 'ประเภท',
            'note'         => 'หมายเหตุ',
        ]);

        // d/m/Y (พ.ศ.) -> Y-m-d (พ.ศ.) ให้ตรงรูปแบบเดียวกับ visits.appointment
        $data['holiday_date'] = Utillity::th2dbDate($data['holiday_date']);

        $exists = Holiday::where('holiday_date', $data['holiday_date'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists();
        if ($exists) {
            abort(redirect()->back()->withInput()
                ->withErrors(['holiday_date' => 'วันที่นี้มีอยู่ในรายการแล้ว']));
        }

        $data['is_closed'] = (bool) $request->input('is_closed', 1);
        return $data;
    }

    private function thaiDate(string $be): string
    {
        $months = \App\Utillity::TH_MONTH_SHORT;
        [$y, $m, $d] = explode('-', $be);
        return (int) $d . ' ' . $months[(int) $m] . ' ' . $y;
    }
}
