<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Exports\LabExport;
use Maatwebsite\Excel\Facades\Excel;

use Carbon\Carbon;
use App\Models\Lab;
use App\Models\LabItem;
use App\Models\LabSub;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Schema;
class LabController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */

public function index(Request $request)
{
    $requestData = $request->all();
    $keyword = $request->get('search');
    $status = $request->get('status');
    $startDate = $request->get('start_date');
    $endDate = $request->get('end_date');
    $perPage = 25;

    // เริ่มต้น query
    // ตั้งใจ "ไม่กรองตามคลินิก" เพราะห้อง Lab เป็นหน่วยบริการกลาง รับงานทั้ง STI/TB/TMC
    // (TMC ส่งคนไข้มาตรวจไวรัสตับอักเสบ ถ้ากรองแล้วเจ้าหน้าที่ Lab จะมองไม่เห็นงานค้างของ TMC)
    // แสดงป้ายคลินิกในตารางแทน เพื่อให้รู้ว่าแต่ละรายการมาจากคลินิกไหน
    $query = Lab::with(['labSub2', 'visit']);

    // ถ้าค้นหาด้วย keyword
    if (!empty($keyword)) {
        $query->where(function ($q) use ($keyword) {
            $q->where('LN', 'LIKE', "%$keyword%")
              ->orWhereHas('patient', function ($q) use ($keyword) {
                  $q->where('name', 'LIKE', "%$keyword%")
                    ->orWhere('surname', 'LIKE', "%$keyword%")
                    ->orWhere('code', 'LIKE', "%$keyword%");
              });
        });
    }

if ($status === "all") {
    // ถ้าเลือก "ทั้งหมด" แสดงทุกสถานะ (รวม 2)
    // ไม่ต้องกรองอะไรเลย
} elseif ($status) {
    // ถ้ามีการเลือกสถานะที่เจาะจง กรองตามค่านั้น
    $query->where('status', $status);
} else {
    // ถ้าไม่เลือกสถานะเลย ให้ซ่อนใบที่เสร็จแล้ว
    $query->where('status', '!=', \App\Enums\LabStatus::Done->value);
}


    // กรองตามวันที่
    if ($startDate && $endDate) {
        $query->whereBetween('collected_date', [$startDate, $endDate]);
    }

    // ดึงข้อมูลที่กรองแล้ว
    $lab = $query->latest()->paginate($perPage);

    return view('lab.index', compact('lab'));
}






    public function room(Request $request)
    {
        $keyword = $request->get('search');
        $perPage = 25;

        if (!empty($keyword)) {
            $lab = Lab::where('patient_id', 'LIKE', "%$keyword%")
                ->orWhere('visit_id', 'LIKE', "%$keyword%")
                ->orWhere('report_by', 'LIKE', "%$keyword%")
                ->orWhere('approve_by', 'LIKE', "%$keyword%")
                ->orWhere('collected_date', 'LIKE', "%$keyword%")
                ->orWhere('report_date', 'LIKE', "%$keyword%")
                ->orWhere('LN', 'LIKE', "%$keyword%")
                ->latest()->paginate($perPage);
        } else {
            $lab = Lab::latest()->paginate($perPage);
        }

        return view('lab.index', compact('lab'));
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('lab.create');
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
    $requestData = $request->all();                 // รับค่าจากฟอร์มทั้งหมด
    $requestData = Lab::format2DB($requestData);    // แปลง format วันที่/เวลา จาก d/m/Y → Y-m-d (ค.ศ.)
    Lab::create($requestData);                      // บันทึกลง DB

    return redirect(route('visit.edit', $requestData['visit_id']))
           ->with('flash_message', 'Lab added!');
}


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     *
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $lab = Lab::findOrFail($id);

        return view('lab.show', compact('lab'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     *
     * @return \Illuminate\View\View
     */
public function edit($id, Request $request)
{
    $lab = Lab::findOrFail($id);
    $lab->formatDBBack();

    // ดึงข้อมูล patient ผ่าน visit ของ lab
    $patient = $lab->visit ? $lab->visit->patient() : null;

    // สร้าง URL สำหรับปุ่ม Back
    $page = $request->input('page', 1); // ค่า default ของ page คือ 1
    $backUrl = $lab->visit ? url("/visit/{$lab->visit->id}/edit?page={$page}") : null;

    return view('lab.edit', compact('lab', 'patient', 'backUrl'));
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

        // ชื่อ/เวลาของ รับ Lab · Report · Approve ประทับจากปุ่มเท่านั้น (คนที่ login + เวลาจริง) — ISO 15189
        // ฟอร์มเก่าที่ยังเปิดค้างอยู่ส่งมาก็ไม่รับ (2569-09-30)
        $requestData = $request->except([
            'report_by', 'report_date', 'report_at', 'approve_by', 'approve_date', 'approve_at',
            'received_by', 'received_at',
        ]);
        // ผู้ทดสอบว่าง = ยังไม่เลือก (คอลัมน์ตัวเลข ส่งค่าว่างไปจะบันทึกไม่ได้)
        if (array_key_exists('tested_by', $requestData)) {
            $requestData['tested_by'] = (int) $requestData['tested_by'] ?: null;
        }
        $lab = Lab::findOrFail($id);
        $requestData = Lab::format2DB($requestData);
        $lab->update($requestData);
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
    public function destroy($id)
    {

        $lab = Lab::findOrFail($id);
        $visit_id = $lab->visit_id;

        DB::beginTransaction();
        try {
            LabItem::where('lab_id', $id)->delete();
            LabSub::where('lab_id', $id)->delete();
            Lab::destroy($id);
            DB::commit();
            // all good
        } catch (\Exception $e) {
            DB::rollback();
            // something went wrong
        }
        // return redirect('backend/lab')->with('flash_message', 'Lab deleted!');
        return redirect(route('visit.edit', $visit_id))->with('flash_message', 'Lab deleted!');
    }
    public function printLabs(Request $request)
{
    $labIds = $request->input('labIds');
    $labs = LabSub::whereIn('id', $labIds)->get();

    return view('lab-sub.print', compact('labs'));
}

    
    public function updateAllSub(Request $request, $id)
    {
        $requestData = $request->all();

        // ตรวจสอบว่ามีข้อมูล result ส่งมาหรือไม่
        if (isset($requestData['result'])) {
            foreach($requestData['result'] as $sub_id => $result){
                $labSub = LabSub::findOrFail($sub_id);
                
                // 1. อัปเดตผล (Result)
                $labSub->result = $result;

                // 2. อัปเดตเหตุผล (Remark)
                // ✅ แก้ไข: ใช้ array_key_exists เพื่อให้บันทึกค่าว่าง (null) ได้เมื่อลบข้อความ
                if (isset($requestData['remark']) && array_key_exists($sub_id, $requestData['remark'])) {
                    $labSub->remark = $requestData['remark'][$sub_id];
                }

                $labSub->save();
            }
        }

        flash('Lab updated!', 'success');
        Alert::success('Lab updated!');

        return back()->with('flash_message', 'Lab updated!');
    }
   public function export(Request $request)
    {
        // โยน Parameter วันที่ไปให้ LabExport
        $fileName = 'รายงาน_Lab_' . $request->start_date . '_ถึง_' . $request->end_date . '.xlsx';
        
        return Excel::download(
            new LabExport($request->start_date, $request->end_date),
            $fileName
        );
    }

    /**
     * อนุมัติ / ถอนอนุมัติผล Lab
     * ผลตรวจจะแสดงให้พยาบาล/หมอเห็นก็ต่อเมื่ออนุมัติแล้วเท่านั้น
     * (ถอนอนุมัติไว้ตอนแก้ผล เพื่อไม่ให้ใครอ่านค่าที่ยังไม่ยืนยัน)
     */
    public function approve(Request $request, $id)
    {
        $lab = \App\Models\Lab::findOrFail($id);

        if ($request->input('action') === 'unapprove') {
            $lab->approve_by   = null;
            $lab->approve_date = null;
            $lab->approve_at   = null;
            $msg = 'ถอนอนุมัติผลแล้ว — พยาบาล/หมอจะไม่เห็นผลจนกว่าจะอนุมัติใหม่';
        } else {
            // ห้ามอนุมัติใบที่ยังไม่ได้ลงผลเลยสักรายการ
            // เพราะพอ approve_by ถูกตั้ง ระบบถือว่า "ผลออกแล้ว" ทันที ป้ายเขียวขึ้นในหน้าห้อง
            // พยาบาลกดเข้าไปดูแล้วไม่เจอผลอะไรเลย (เจอจริงที่ lab 12239 เมื่อ 2569-09-09)
            // อนุมัติทั้งที่ผลออกบางส่วนยังทำได้ตามเดิม เพราะผลแล็บทยอยออกเป็นเรื่องปกติ
            $ready = \App\Models\LabSub::where('lab_id', $lab->id)
                ->whereNotNull('result')->where('result', '!=', '')->count();

            if ($ready === 0) {
                // ใช้ flash() เพราะ layout เรียก flash::message ไว้ทุกหน้า
                // (ถ้าใช้ ->with('error') เฉย ๆ จะไม่มีอะไรขึ้นเลย กดแล้วเงียบ ไม่รู้สาเหตุ)
                flash('ยังไม่ได้ลงผลสักรายการ จึงอนุมัติไม่ได้ — กรุณาลงผลอย่างน้อย 1 รายการก่อน '
                    . 'มิฉะนั้นหน้าพยาบาลจะขึ้นป้าย "ผลแล็บออกแล้ว" ทั้งที่ยังไม่มีผลให้ดู', 'danger');

                return redirect()->back();
            }

            // ต้อง Report ก่อน Approve (ขั้น 4 -> 5) และถ้าคน Report กับคน Approve เป็นคนเดียวกัน
            // ต้องเว้นอย่างน้อย 5 นาที ให้มีเวลาทวนผลก่อนปล่อย (ห้อง Lab กำหนด 2569-09-30)
            if (! $lab->report_by || ! $lab->report_date) {
                flash('ต้องกด Report ก่อน จึงจะ Approve ได้', 'danger');
                return redirect()->back();
            }
            $y  = (int) substr((string) $lab->report_date, 0, 4);
            $rd = ($y > 2400 ? $y - 543 : $y) . substr((string) $lab->report_date, 4, 6);
            $reportedAt = \Carbon\Carbon::parse($rd . ' ' . ($lab->report_at ?: '00:00:00'));
            // ผลถูกแก้หลังกด Report = ผลที่จะ Approve ไม่ใช่ชุดที่ Report ไว้ — ต้อง Report ใหม่ก่อน
            // ไม่งั้นการรอ 5 นาทีเลี่ยงได้ด้วยการ Report ไว้ก่อนแล้วค่อยแก้ผล (รีวิว 2569-09-30)
            $lastEdit = \App\Models\LabSub::where('lab_id', $lab->id)->max('updated_at');
            if ($lastEdit && \Carbon\Carbon::parse($lastEdit)->gt($reportedAt->copy()->addSeconds(59))) {
                flash('มีการแก้ผลหลังกด Report (แก้ล่าสุด ' . \Carbon\Carbon::parse($lastEdit)->format('H:i') . ' น.) — กด Report ใหม่ก่อน แล้วจึง Approve', 'warning');
                return redirect()->back();
            }
            if ((int) $lab->report_by === (int) auth()->id()) {
                $readyAt    = $reportedAt->copy()->addMinutes(5);
                if (now()->lt($readyAt)) {
                    flash('คุณเป็นคน Report ใบนี้เอง ต้องรออย่างน้อย 5 นาทีหลัง Report จึงจะ Approve ได้ '
                        . '(Approve ได้ตั้งแต่ ' . $readyAt->format('H:i') . ' น. · อีกประมาณ '
                        . max(1, (int) ceil(now()->diffInSeconds($readyAt, true) / 60)) . ' นาที) — หรือให้คนอื่น Approve', 'warning');
                    return redirect()->back();
                }
            }

            $lab->approve_by   = auth()->id();
            $lab->approve_date = (now()->year + 543) . now()->format('-m-d');
            $lab->approve_at   = now();
            $msg = 'อนุมัติผลแล้ว — พยาบาล/หมอเห็นผลได้ทันที';
        }

        $lab->save();

        // เดิมส่งข้อความผ่าน ->with('flash_message') ซึ่งไม่มีหน้าไหนแสดง — กด Approve แล้วเงียบ ไม่รู้ว่าสำเร็จไหม
        // (ผู้ใช้แจ้ง 2569-10-20 · เป็นมาแต่เดิม ไม่เกี่ยวกับการยกรุ่น) ใช้ flash() แบบปุ่มรับ Lab/Report
        flash($msg, $request->input('action') === 'unapprove' ? 'warning' : 'success');

        return redirect()->back();
    }

    /** ห้อง Lab กด "รับ Lab" — ประทับคนที่ login + เวลาจริง (ครั้งแรกครั้งเดียว) */
    public function receive($id)
    {
        $lab = \App\Models\Lab::findOrFail($id);
        if (! $lab->received_at) {
            $lab->received_by = auth()->id();
            $lab->received_at = now();
            // ผู้ทดสอบตั้งต้น = ผู้รับ Lab (ห้อง Lab ขอ 2569-09-30) — ถ้ายังไม่ได้เลือกไว้ แก้เป็นคนอื่นได้ในกล่องข้อมูลรายงาน
            if (! $lab->tested_by) {
                $lab->tested_by   = auth()->id();
                $lab->tested_date = (now()->year + 543) . now()->format('-m-d');   // พ.ศ. แบบคอลัมน์วันที่อื่นของ labs
            }
            $lab->save();
            flash('รับ Lab แล้ว ' . now()->format('d/m/') . (now()->year + 543) . ' ' . now()->format('H:i') . ' น.', 'success');
        }
        return redirect()->back();
    }

    /**
     * กด "Report" — ผู้รายงาน = คนที่ login · เวลา = เวลาจริง (เลือกชื่อ/พิมพ์เวลาเองไม่ได้แล้ว 2569-09-30)
     * กดซ้ำได้ (เช่น ผลออกเพิ่มทีหลัง) — ชื่อ/เวลาเก่าอยู่ในบันทึกการแก้ไขของระบบ
     */
    public function reportResult($id)
    {
        $lab = \App\Models\Lab::findOrFail($id);
        $ready = \App\Models\LabSub::where('lab_id', $lab->id)->whereNotNull('result')->where('result', '!=', '')->count();
        if ($ready === 0) {
            flash('ยังไม่ได้ลงผลสักรายการ จึง Report ไม่ได้', 'danger');
            return redirect()->back();
        }
        $lab->report_by   = auth()->id();
        $lab->report_date = (now()->year + 543) . now()->format('-m-d');
        $lab->report_at   = now()->format('H:i:s');
        // ลืมระบุผู้ทดสอบ (และไม่ได้กดรับ Lab) = ใช้ชื่อคน Report (ห้อง Lab ขอ 2569-09-30)
        if (! $lab->tested_by) {
            $lab->tested_by   = auth()->id();
            $lab->tested_date = $lab->tested_date ?: $lab->report_date;
        }
        $lab->save();
        flash('Report แล้ว — โดย ' . (auth()->user()->name ?? '') . ' ' . now()->format('H:i') . ' น.', 'success');

        return redirect()->back();
    }

    /**
     * สถานะผล Lab (ให้ฝั่งพยาบาล poll แบบ AJAX)
     * ready = จำนวนรายการที่ผลออกแล้ว -> ถ้าเพิ่มขึ้น แปลว่ามีผลใหม่เข้ามาให้ดู
     */
    public function approveStatus($id)
    {
        $lab = \App\Models\Lab::findOrFail($id);

        $ready = \App\Models\LabSub::where('lab_id', $lab->id)
            ->whereNotNull('result')
            ->where('result', '!=', '')
            ->count();

        return response()->json([
            'approved' => $lab->isApproved(),
            'visible'  => $lab->isResultVisible(),
            'ready'    => $ready,
        ]);
    }

    /**
     * ผลย้อนหลังของ "รายการตรวจเดียว" ของคนไข้คนหนึ่ง (กดจากตารางผล Lab)
     * ใช้ดูว่าค่าเดินไปทางไหน เช่น RPR 1:32 -> 1:8 -> 1:4 ว่าลงถึงไหนแล้ว
     * เคารพกฎเดิม: ผลที่ห้อง Lab ยังไม่อนุมัติ ห้ามโชว์ให้พยาบาล/หมอ
     */
    public function trend(Request $request, $patient, $method)
    {
        $patientId = (int) $patient;
        $method    = (int) $method;

        // visit ที่เปิดอยู่ตอนกดปุ่ม -> ใช้ไฮไลต์ว่า "ครั้งนี้" คือแถวไหน
        $currentVisitId = (int) $request->query('visit');

        $isLabRoom = auth()->check() && auth()->user()->hasAnyRole(['Lab', 'Admin']);

        // ดึง lab ทั้งหมดของคนไข้ไว้ก่อน แล้วค่อยหยิบ sub เฉพาะ method ที่ขอ
        $labs = Lab::where('patient_id', $patientId)->get()->keyBy('id');

        $subs = $labs->isEmpty() ? collect() : LabSub::whereIn('lab_id', $labs->keys())
            ->where('method', $method)
            ->get();

        $rows = $subs->map(function ($sub) use ($labs, $isLabRoom, $currentVisitId) {
            $lab = $labs->get($sub->lab_id);

            // วันที่อ้างอิง: ใช้วันเก็บสิ่งส่งตรวจ (เก็บเป็น พ.ศ.) ถ้าไม่มีค่อยถอยไปใช้ created_at (ค.ศ.)
            $dateAd = $lab && $lab->collected_date
                ? \App\Utillity::BE2AD($lab->collected_date)
                : optional($lab->created_at ?? null)->toDateString();

            return (object) [
                'sub'      => $sub,
                'lab'      => $lab,
                'date_ad'  => $dateAd,
                'visible'  => $isLabRoom || !$lab || $lab->isResultVisible(),
                'titer'    => LabSub::titerValue($sub->result),
                'current'  => $currentVisitId && $lab && (int) $lab->visit_id === $currentVisitId,
            ];
        })
        ->filter(fn ($r) => !empty($r->date_ad))
        ->sortByDesc('date_ad')          // ใหม่สุดอยู่บน
        ->values();

        return view('lab-sub.trend', [
            'rows'      => $rows,
            'method'    => $method,
            'isTiter'   => LabSub::isTiterMethod($method),
            'patient'   => \App\Models\Patient::find($patientId),
            'isLabRoom' => $isLabRoom,
        ]);
    }
}
    

