<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Models\Lab;
use App\Models\LabSub;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;
use App\Models\Patient;
use Illuminate\Support\Facades\Auth;

class LabSubController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $perPage = 25;

        if (!empty($keyword)) {
            $labsub = LabSub::where('lab_id', 'LIKE', "%$keyword%")
                ->orWhere('method', 'LIKE', "%$keyword%")
                ->orWhere('specimen_from', 'LIKE', "%$keyword%")
                ->orWhere('result', 'LIKE', "%$keyword%")
                ->latest()->paginate($perPage);
        } else {
            $labsub = LabSub::latest()->paginate($perPage);
        }

        return view('lab-sub.index', compact('labsub'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('lab-sub.create');
    }

    /**
     * Show the form for add a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function add()
    {
        return view('lab-sub.add');
    }
    // public function config()
    // {
    //     return view('lab-sub.config.main');
    // }
    public function config(Request $request)
{
    $visitId = $request->input('visit');
    $page = $request->input('page', 1); // กำหนดค่า default ของ page เป็น 1
    $lab = Lab::where('visit_id', $visitId)->first(); // ดึง lab จาก visit_id

    // ดึงข้อมูล visit และ patient พร้อมกับ sex
    $visit = Visit::where('id', $visitId)->first();
    $sex = $visit->patient2->sex ?? null; // ดึงค่า sex จาก patient

    // ดึงข้อมูลอื่นๆ ที่เกี่ยวข้อง
    $labSubConfig = $lab ? $lab->labSubConfig() : [];

    // สร้าง URL สำหรับปุ่ม Back
    $backUrl = url("/visit/{$visitId}/edit?page={$page}");

    return view('lab-sub.config.main', compact('lab', 'labSubConfig', 'backUrl', 'sex'));
}




    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(Request $request)
{
    $requestData = $request->all();

    if (array_key_exists('specimen_from', $requestData) && is_array($requestData['specimen_from'])) {
        foreach ($requestData['specimen_from'] as $specimen_from) {
            LabSub::create([
                'lab_id'               => $requestData['lab_id'],
                'method'               => $requestData['method'],
                'method_other'         => $requestData['method_other'],
                'specimen_from'        => $specimen_from,
                'specimen_from_other'  => $requestData['specimen_from_other'],
            ]);
        }
    } else {
        LabSub::create($requestData);
    }

    // ✅ บันทึกผู้ส่งแล็บ: คนที่กดบันทึกหน้านี้
    if (Auth::check()) {
        $lab = Lab::find($requestData['lab_id']);
        if ($lab) {
            $visit = Visit::find($lab->visit_id);
            if ($visit) {
                $visit->lab_by     = Auth::id();
                $visit->handled_by = Auth::id();
                $visit->save();
            }
        }
    }

    flash('LabSub saved!', 'success');
    Alert::success('LabSub updated!');

    echo '<script type="text/javascript">history.go(-2);</script>';
    return; // กันไม่ให้โค้ดข้างล่างรันต่อ
}


    /**
     * สั่ง/ถอด Lab ในคลินิกแบบ "เฉพาะส่วนต่าง" — แผง Lab ในคลินิกของคลินิก TB (บัตร TB 01 popup + หน้าตรวจ TB) 2569-10-02
     *
     * ส่งมา 2 ชุด: orig = ที่ติ๊กอยู่ตอนเปิดแผง · new = ที่ติ๊กตอนกดบันทึก
     * เพิ่ม = new − orig · ถอด = orig − new — ของที่อีกห้องสั่งระหว่างนั้นไม่ถูกลบ (แนวเดียวกับ _touched ของหน้าตรวจ)
     * กติกาจับคู่/ห้ามถอดตัวที่มีผล ใช้ตัวเดียวกับ saveConfig (\App\Services\LabSubOrder)
     *
     * กุญแจรายการ: เลขวิธีตรวจของกลุ่มเลือด (6 HIV, 7 TPHA, 8 RPR, 11 HBsAg, 12 Anti-HCV)
     * หรือ "10|ข้อความ" = Other — แบบเดียวกับที่หน้าสั่ง Lab เต็มเก็บ
     */
    public function quickOrder(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'orig'   => ['nullable', 'array', 'max:50'],
            'new'    => ['nullable', 'array', 'max:50'],
            'orig.*' => ['string', 'max:300'],
            'new.*'  => ['string', 'max:300'],
        ]);
        $toAttrs = function ($key) {
            $key = trim((string) $key);
            if (preg_match('/^10\|(.+)$/su', $key, $m)) {
                $text = trim($m[1]);
                return ($text !== '' && mb_strlen($text) <= 255) ? ['method' => 10, 'method_other' => $text] : null;
            }
            return in_array((int) $key, \App\Models\LabSub::$tbQuickMethods, true) && (string) (int) $key === $key
                ? ['method' => (int) $key] : null;
        };
        $bad = [];
        foreach (array_merge((array) $request->input('orig', []), (array) $request->input('new', [])) as $k) {
            if ($toAttrs($k) === null) { $bad[] = $k; }
        }
        if ($validator->fails() || $bad) {
            $errors = $validator->errors()->toArray();
            if ($bad) { $errors['new'] = ['รายการตรวจไม่ถูกต้อง: ' . implode(', ', array_map('strval', $bad))]; }
            if ($request->wantsJson()) {
                return response()->json(['message' => 'ยังไม่ได้บันทึก — รายการตรวจไม่ถูกต้อง', 'errors' => $errors], 422);
            }
            flash('ยังไม่ได้บันทึก — รายการตรวจไม่ถูกต้อง', 'danger');
            return back();
        }

        $orig = array_values(array_unique(array_map('trim', (array) $request->input('orig', []))));
        $new  = array_values(array_unique(array_map('trim', (array) $request->input('new', []))));

        // ผลตรวจข้างเตียง (DTX) ที่พยาบาลพิมพ์ในแผง — กรอกผล = สั่งให้ด้วย ไม่ต้องติ๊กก่อน
        $poc = [];
        foreach ((array) $request->input('poc', []) as $m => $v) {
            $m = (int) $m; $v = trim((string) $v);
            if (! \App\Models\LabSub::isPoc($m) || $v === '') { continue; }
            [$unit, $min, $max] = \App\Models\LabSub::$pocMethods[$m];
            if (! is_numeric($v) || (float) $v < $min || (float) $v > $max) {
                $msg = 'ผล ' . \App\Models\LabSub::$methodOption[$m] . ' ต้องเป็นตัวเลข ' . $min . '–' . $max . ' ' . $unit;
                return $request->wantsJson()
                    ? response()->json(['message' => 'ยังไม่ได้บันทึก — ' . $msg, 'errors' => ['poc' => [$msg]]], 422)
                    : back()->with('flash_message', $msg);
            }
            $poc[$m] = rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.');
            if (! in_array((string) $m, $new, true)) { $new[] = (string) $m; }
        }
        $add    = array_map($toAttrs, array_values(array_diff($new, $orig)));
        $remove = array_map($toAttrs, array_values(array_diff($orig, $new)));

        $res = \App\Services\LabSubOrder::orderFromTb($visit, $add, $remove);

        if ($poc && ! empty($res['lab_id'])) {
            foreach ($poc as $m => $val) {
                $sub = \App\Models\LabSub::where('lab_id', $res['lab_id'])->where('method', $m)->orderBy('id')->first();
                if ($sub && (string) $sub->result !== $val) {
                    $sub->fill(['result' => $val, 'tested_by' => \Illuminate\Support\Facades\Auth::id(),
                                'tested_date' => \App\Models\VisitAppointment::todayBe()])->save();
                    $res['added']++;   // ให้ข้อความบอกว่าบันทึกแล้ว
                }
            }
        }

        // สั่ง/ลงผลแค่ DTX = ไม่ได้ส่งอะไรเข้าห้อง Lab — อย่าบอกว่าส่งแล้ว เดี๋ยวพยาบาลรอผลจากห้อง Lab
        $labWork = collect($add)->contains(fn ($a) => ! \App\Models\LabSub::isPoc($a['method'] ?? null));
        $pocNames = implode(' / ', array_map(fn ($m) => \App\Models\LabSub::$methodOption[$m] ?? $m, array_keys($poc)));
        $msg =! ($res['added'] || $res['removed']) ? 'ไม่มีอะไรเปลี่ยน'
             : ($labWork ? 'บันทึกแล้ว — ส่งเข้าห้อง Lab ของ visit วันนี้' . ($poc ? ' · บันทึกผล ' . $pocNames . ' แล้ว' : '')
                         : ($poc ? 'บันทึกผล ' . $pocNames . ' แล้ว' : 'บันทึกแล้ว'));
        if ($res['blocked']) {
            $msg = 'ถอดรายการที่ห้อง Lab ลงผลแล้วไม่ได้: ' . implode(', ', $res['blocked'])
                 . ' — ถ้าต้องการเอาออกจริง ให้ห้อง Lab ลบผลก่อน';
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => $msg,
                'blocked' => $res['blocked'],
                'added'   => $res['added'],
                'removed' => $res['removed'],
                'panel'   => view('tb._inhouse_lab_panel', ['visit' => $visit->fresh()])->render(),
            ]);
        }
        flash($msg, $res['blocked'] ? 'warning' : 'success');
        return back();
    }

    public function saveConfig(Request $request)
{
    $requestData = $request->all();

    DB::beginTransaction();
    try {
        $labId = $requestData['lab_id'];

        // เดิมโค้ดตรงนี้ "ลบรายการตรวจทั้งใบแล้วสร้างใหม่" ทุกครั้งที่กดบันทึก
        // ทำให้ผลที่ห้อง Lab พิมพ์ไประหว่างที่ห้องเจาะเลือดเปิดหน้านี้ค้างไว้ หายทั้งหมด
        // (เคสจริง visit 12319 เมื่อ 2569-09-09 ถูกลบ-สร้างใหม่ 4 รอบ ผลหายเกลี้ยง)
        // และ id ที่เปลี่ยนทุกครั้งยังทำให้ billing_items ของค่าแล็บชี้ไปยังรายการที่ไม่มีแล้ว
        //
        // เปลี่ยนเป็นเทียบรายการแทน: ที่ยังสั่งอยู่ = คงของเดิมไว้ (id เดิม ผลเดิม)
        // ที่สั่งเพิ่ม = สร้างเฉพาะตัวใหม่ · ที่ถอดออก = ลบเฉพาะตัวที่ยังไม่มีผล
        // ตัวที่ห้อง Lab ลงผลแล้ว จะไม่ถูกแตะและถอดออกไม่ได้ (ผู้ใช้ตัดสินใจ 2569-09-10)
        // จับคู่/สร้าง/ถอด ทำที่ \App\Services\LabSubOrder::syncFull (กติกากลาง ใช้ร่วมกับการสั่งจากบัตร TB 01 2569-10-02)
        // ที่นี่แค่รวบรวมรายการที่ติ๊กตามลำดับเดิมของฟอร์ม
        $items = [];
        $put = function (array $attrs, $incomingResult = null, $incomingRemark = null) use (&$items) {
            $items[] = [$attrs, $incomingResult, $incomingRemark];
        };

        // จัดการข้อมูลของ LabSub
        foreach ($requestData['item'] as $methodId => $itemMethod) {
            $method_other = "";
            foreach ($itemMethod as $specimenFromId => $value) {
                $specimen_from_other = "";
                if ($specimenFromId == 10) {
                    $specimen_from_other = $value['key'];
                    if ($specimen_from_other) {
                        $put([
                            'method' => $methodId,
                            'method_other' => $method_other,
                            'specimen_from' => $specimenFromId,
                            'specimen_from_other' => $specimen_from_other,
                        ], $value['value']);
                    }
                } else {
                    $put([
                        'method' => $methodId,
                        'method_other' => $method_other,
                        'specimen_from' => $specimenFromId,
                        'specimen_from_other' => $specimen_from_other,
                    ], ($value != -1) ? $value : null);
                }
            }
        }

        // จัดการข้อมูล PCR
        if (array_key_exists('pcr', $requestData)) {
            foreach ($requestData['pcr'] as $specimenFromId => $value) {
                $put(['method' => 5, 'specimen_from' => $specimenFromId]);
            }
        }

        // จัดการข้อมูล Blood Test
        if (array_key_exists('blood', $requestData)) {
            foreach ($requestData['blood'] as $specimenFromId => $value) {
                $remark = null;
                if (isset($requestData['blood_remark']) && array_key_exists($specimenFromId, $requestData['blood_remark'])) {
                    $remark = $requestData['blood_remark'][$specimenFromId];
                }

                $put(['method' => $specimenFromId], ($value != -1) ? $value : null, $remark);
            }
        }

        // จัดการข้อมูล Other
        if ($requestData['other']) {
            $put([
                'method' => 10,
                'method_other' => $requestData['other'],
            ], $requestData['othervalue']);
        }

        // รายการที่ถูกถอดออกจากใบสั่ง: ลบได้เฉพาะตัวที่ยังไม่มีผล
        // ตัวที่มีผลแล้วให้คงไว้ + บอกผู้ใช้ว่าถอดไม่ได้ ถ้าจะเอาออกจริงต้องให้ห้อง Lab ลบผลก่อน
        $blocked = \App\Services\LabSubOrder::syncFull((int) $labId, $items);

        // // อัปเดตค่า Status ของ Lab
        // if (array_key_exists('status', $requestData)) {
        //     $lab = Lab::find($requestData['lab_id']);
        //     if ($lab) {
        //         $lab->status = $requestData['status'];
        //         $lab->save();
        //     }
        // }
        if (array_key_exists('status', $requestData)) {
    $lab = Lab::find($requestData['lab_id']);
if ($lab) {
    $lab->status = $requestData['status'];

    // collected_date (เดิม)
    if (array_key_exists('collected_date', $requestData)) {
        $lab->collected_date = $requestData['collected_date'];
    }

    // ✅ collected_at (ใหม่) — เก็บเป็น 24H HH:MM:SS
    if (array_key_exists('collected_at', $requestData)) {
        $raw = trim((string) $requestData['collected_at']); // อาจได้ "14:05" หรือ "2:05 PM"
        if ($raw !== '') {
            $parsed = null;
            foreach (['H:i:s','H:i','g:i A','g:i:s A','h:i A','h:i:s A'] as $fmt) {
                try {
                    $parsed = \Carbon\Carbon::createFromFormat($fmt, $raw, config('app.timezone'));
                    break;
                } catch (\Exception $e) {}
            }
            if ($parsed) {
                $lab->collected_at = $parsed->format('H:i:s'); // บันทึกเป็น 24H
            }
        } else {
            // ถ้าอยากให้เคลียร์เมื่อส่งค่าว่าง
            $lab->collected_at = null;
        }
    }

    $lab->save();
}

}

        if (Auth::check()) {
    $lab = Lab::find($requestData['lab_id']);
    if ($lab) {
        $visit = Visit::find($lab->visit_id);
        if ($visit) {
            $visit->lab_by     = Auth::id();
            $visit->handled_by = Auth::id();
            $visit->save();
        }
    }
}

DB::commit();

// ถอดรายการที่มีผลแล้วออกไม่ได้ — บอกให้ชัดว่าตัวไหน จะได้ไม่งงว่าทำไมยังอยู่
if (!empty($blocked)) {
    flash('บันทึกแล้ว แต่ถอดรายการที่ห้อง Lab ลงผลไว้แล้วออกไม่ได้: '
        . implode(', ', array_unique($blocked))
        . ' — ถ้าต้องการเอาออกจริง ให้ห้อง Lab ลบผลของรายการนั้นก่อน '
        . '(สั่งตรวจรายการใหม่เพิ่มได้ตามปกติ)', 'warning');
} else {
    flash('LabSub and Lab status saved!', 'success');
}
    } catch (\Exception $e) {
        DB::rollback();
        flash($e->getMessage(), 'danger');
        throw $e;
    }

    $visit_id = Lab::find($requestData['lab_id'])->visit_id;
    Alert::success('Lab updated!');

    return back()->with('flash_message', 'Lab updated!');
}

    public function show($id)
    {
        $labsub = LabSub::findOrFail($id);

        return view('lab-sub.show', compact('labsub'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     *
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $labsub = LabSub::findOrFail($id);

        return view('lab-sub.edit', compact('labsub'));
    }

    public function result($id)
    {
        $labsub = LabSub::findOrFail($id);

        return view('lab-sub.result', compact('labsub'));
    }
    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param  int  $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update(Request $request, $id)
    {

        $requestData = $request->all();

        $labsub = LabSub::findOrFail($id);
        $labsub->update($requestData);
        //$lab = Lab::findOrFail($requestData['lab_id']);
        //$lab->update($requestData);

        // if (array_key_exists('submit', $requestData) && $requestData['submit'] == 'result') {
        //     return redirect(route('lab.edit', $labsub->lab_id))->with('flash_message', 'LabSub updated!');
        // }
        $visit_id = $labsub->visit()->id;

        // $visit_id = Lab::find($requestData['lab_id'])->visit()->id;
        // return redirect('backend/lab')->with('flash_message', 'Lab updated!');
        flash('Lab updated!', 'success'); // Flash message (ตัวเลือกเสริม)
    Alert::success('Lab updated!');  // Alert message (ตัวเลือกเสริม)

    return back()->with('flash_message', 'Lab updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy(Request $request, $id)
    {

        $labItem = LabSub::findOrFail($id);
        $visit = $labItem->visit();
        LabSub::destroy($id);

        $requestData = $request->all();
        $visit_id = $visit->id;
        flash('ลบรายการตรวจเรียบร้อยแล้ว', 'success');

        $page = (int) ($requestData['page'] ?? 1);

        // Visit::next() เป็นตัวกำหนดปลายทางแบบ wizard และ case 1 ของมันส่งไป patient.edit
        // โดยยัด visit id ลงช่อง patient id (คนละตารางกัน) — ถ้าลบจากแท็บ Lab ในหน้าตรวจจะเด้งไปหน้าผิด
        // เดิมไม่มีใครเจอเพราะปุ่มลบตรงนั้นกดไม่ได้ (ฟอร์มซ้อนในฟอร์มหลัก) พอแก้ให้กดได้จึงโผล่ 2569-09-08
        if ($page === 1) {
            return redirect()->route('visit.edit', ['visit' => $visit_id, 'page' => 1])
                ->with('flash_message', 'ลบรายการตรวจเรียบร้อยแล้ว');
        }

        return Visit::next($page, $visit_id);

        // switch ($requestData['page']) {
        //     case 2:
        //         return redirect(route('visit.edit', $visit_id))->with('flash_message', 'LabSub deleted!');
        //         break;
        //     case 4:
        //         return redirect(route('visit.lab', $visit_id))->with('flash_message', 'LabSub deleted!');
        //         break;
        // }

        // return redirect(route('visit.edit',$visit->id ))->with('flash_message', 'LabSub deleted!');
    }
    public function print($id)
    {
        // ดึงข้อมูลเฉพาะที่เลือก
        $lab = LabSub::findOrFail($id); // ดึงข้อมูลจาก ID
        return view('lab-sub.print', compact('lab'));
    }
    public function showVisit($visitId)
{
    $visit = Visit::find($visitId);
    $patient = $visit->patient ?? Patient::find(request()->get('patient'));

    return view('lab-sub.table', compact('patient'));
}

public function showTable(Request $request)
{
    if (isset($request->visit)) {
        $patient = $request->visit->patient();
    } else {
        $patient = App\Models\Patient::find($request->input('patient'));
    }

    return view('lab-sub.table', compact('patient'));
}

}
