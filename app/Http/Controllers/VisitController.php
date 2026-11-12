<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Models\ContactPerson;
use App\Models\Diagnosi;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\VisitAppointment;
use App\Utillity;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use App\Exports\VisitExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use App\Traits\VisitRecorder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;   // เดิมเขียน PhpSpreadsheet\Spreadsheet (ไม่มีคลาสนี้) ปุ่มส่งออก Excel หน้านัดหมายพังมาตลอด — แก้ 2569-10-20
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class VisitController extends Controller
{
    use VisitRecorder;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
   

public function index0(Request $request)
{
    $keyword  = $request->get('search');
    $perPage  = 25;

    $dxId     = $request->get('disease');       // ?disease=1
    $stateId  = $request->get('diseaseState');  // ?diseaseState=1

    $query = Visit::query()->with('patient2');

    // 1) บังคับให้อยู่ "แถวเดียวกัน" ของ diagnoses
    if (!empty($dxId) || !empty($stateId)) {
        $query->whereHas('diagnoses', function ($q) use ($dxId, $stateId) {
            if (!empty($dxId))   $q->where('disease', $dxId);
            if (!empty($stateId))$q->where('disease_state', $stateId);
        })
        // 2) preload เฉพาะแถวที่ match เพื่อนำไป render ใน Blade
        ->with(['diagnoses' => function ($q) use ($dxId, $stateId) {
            if (!empty($dxId))   $q->where('disease', $dxId);
            if (!empty($stateId))$q->where('disease_state', $stateId);
        }]);
    } else {
        $query->with('diagnoses'); // ไม่กรองอะไร preload ทั้งหมดได้
    }

    // 3) ค้นหาเดิมแบบ LIKE + exact สองตัวในตาราง visits (ถ้าจำเป็น)
    if (!empty($keyword)) {
        $query->where(function ($q) use ($keyword) {
            $q->where('patient_id', 'LIKE', "%{$keyword}%")
              ->orWhere('know_from', 'LIKE', "%{$keyword}%")
              ->orWhere('send_from', 'LIKE', "%{$keyword}%")
              ->orWhere('other_from', 'LIKE', "%{$keyword}%")
              ->orWhere('reason_sti', 'LIKE', "%{$keyword}%")
              ->orWhere('reason_sti_other', 'LIKE', "%{$keyword}%")
              ->orWhere('reason_vct', 'LIKE', "%{$keyword}%")
              ->orWhere('reason_vct_other', 'LIKE', "%{$keyword}%")
              ->orWhere('sti_hostory', 'LIKE', "%{$keyword}%")
              ->orWhere('contraceptive_method', 'LIKE', "%{$keyword}%")
              ->orWhere('LMP', 'LIKE', "%{$keyword}%")
              ->orWhere('symptom', 'LIKE', "%{$keyword}%")
              ->orWhere('diagnosis', $keyword)          // exact เฉพาะคอลัมน์ใน visits
              ->orWhere('disease_state', $keyword)      // exact เฉพาะคอลัมน์ใน visits
              ->orWhere('term_syphilis', 'LIKE', "%{$keyword}%")
              ->orWhere('treatment', 'LIKE', "%{$keyword}%")
              ->orWhere('disease_state_other', 'LIKE', "%{$keyword}%")
              ->orWhere('consultation', 'LIKE', "%{$keyword}%")
              ->orWhere('hiv_sti_test', 'LIKE', "%{$keyword}%")
              ->orWhere('hiv_sti_test_date', 'LIKE', "%{$keyword}%")
              ->orWhere('hiv_sti_test_resule', 'LIKE', "%{$keyword}%")
              ->orWhere('touch_tracing', 'LIKE', "%{$keyword}%")
              ->orWhere('touch_tracing_fail', 'LIKE', "%{$keyword}%")
              ->orWhere('provide_condom_site', 'LIKE', "%{$keyword}%")
              ->orWhere('provide_condom_quantity', 'LIKE', "%{$keyword}%")
              ->orWhere('provide_lubricant_quantity', 'LIKE', "%{$keyword}%")
              ->orWhere('appointment', 'LIKE', "%{$keyword}%")
              ->orWhere('appointment_reason', 'LIKE', "%{$keyword}%");
        });
    }

    $visits = $query->latest()->paginate($perPage);
    // ⚠️ Blade ใช้ $visits -> ต้องส่ง compact('visits')
    return view('visit.index', compact('visits'));
}

public function index(Request $request)
{
    $keyword = $request->get('search');
    $perPage = 500;

    $dxId     = $request->get('disease');       // ?disease=1
    $stateId  = $request->get('diseaseState');  // ?diseaseState=1

    // ตั้งใจ "ไม่" กรองคลินิก — หน้าค้นหาต้องหาเจอทุกคลินิก ไม่งั้นค้นไม่เจอแล้วนึกว่าไม่มีข้อมูล
    // (แนวเดียวกับรายการห้อง ส่วนแดชบอร์ด/รายงานยังแยกคลินิกตามเดิม)
    $query = Visit::with(['patient2', 'diagnoses', 'contactPersons']);

    // ค้นด้วย HN / ชื่อ / นามสกุล ที่ฝั่งเซิร์ฟเวอร์
    // (เดิมอ่าน $keyword ไว้แต่ไม่เคยใช้ ค้นได้เฉพาะ DataTables ฝั่งเบราว์เซอร์
    //  ซึ่งค้นได้แค่ 500 แถวที่โหลดมา ทำให้คนไข้คลินิกเล็กอย่าง TMC มักหาไม่เจอ)
    if ($keyword = trim((string) $keyword)) {
        $query->whereHas('patient2', function ($q) use ($keyword) {
            $q->where('code', 'like', "%{$keyword}%")
              ->orWhere('name', 'like', "%{$keyword}%")
              ->orWhere('surname', 'like', "%{$keyword}%")
              // พิมพ์ "ชื่อ นามสกุล" ติดกันมาก็ต้องเจอ
              ->orWhereRaw("CONCAT(name, ' ', surname) like ?", ["%{$keyword}%"]);
        });
    }

    // เพศ
    if ($sex = $request->get('sex')) {
        $patient_ids = Patient::where('sex', $sex)->pluck('id');
        $query->whereIn('patient_id', $patient_ids);
    }

    // อายุ
    $start_age = $request->get('start_age');
    $end_age   = $request->get('end_age');
    if (!empty($start_age) || !empty($end_age)) {
        $qp = Patient::query();
        if (!empty($start_age)) {
            $year = Utillity::age2BirthBE($start_age);
            $qp->where('birth_date', '<=', "{$year}-01-01");
        }
        if (!empty($end_age)) {
            $year = Utillity::age2BirthBE($end_age);
            $qp->where('birth_date', '>=', "{$year}-01-01");
        }
        $query->whereIn('patient_id', $qp->pluck('id'));
    }

    // วันที่มา
    if ($start = $request->get('start')) {
        $query->where('date', '>=', Utillity::th2dbDate($start));
    }
    if ($end = $request->get('end')) {
        $query->where('date', '<=', Utillity::th2dbDate($end));
    }

    // สถานะคนไข้
    if ($status = $request->get('status')) {
        $patient_ids = Patient::where('status', $status)->pluck('id');
        $query->whereIn('patient_id', $patient_ids);
    }

    // นัด
    if ($appointment = $request->get('appointment')) {
        $query->where('appointment', '=', Utillity::th2dbDate($appointment));
    }

    // ✅ แก้ logic disease + diseaseState ให้ “อยู่แถวเดียวกัน” ของ diagnoses
    if (!empty($dxId) || !empty($stateId)) {
        $query->whereHas('diagnoses', function ($q) use ($dxId, $stateId) {
            if (!empty($dxId))   $q->where('disease', $dxId);
            if (!empty($stateId))$q->where('disease_state', $stateId);
        })
        ->with(['diagnoses' => function ($q) use ($dxId, $stateId) {
            if (!empty($dxId))   $q->where('disease', $dxId);
            if (!empty($stateId))$q->where('disease_state', $stateId);
        }]);
    } else {
        $query->with('diagnoses');
    }

    $visits = $query->paginate($perPage);
    return view('visit.index', compact('visits'));
}

    /**
     * จำนวนคนไข้แต่ละห้อง สำหรับป้ายตัวเลขบนแถบเมนู (2569-11-02)
     * เดิมตัวเลขคิดตอนโหลดหน้าเท่านั้น — นั่งทำงานหน้าเดิมนาน ๆ ตัวเลขค้าง ไม่รู้ว่ามีคนไข้เข้าห้อง
     * หน้าเว็บถามที่นี่เมื่อได้สัญญาณ visit จาก Reverb และทุก 30 วินาที (กันสัญญาณหลุด) · นับแบบเดียวกับ Visit::count()
     */
    public function roomCounts()
    {
        $statuses = [2, 8, 3, 7, 10, 5, 9];
        $rows = Visit::whereIn('status', $statuses)->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');
        $out = [];
        foreach ($statuses as $s) { $out[$s] = (int) ($rows[$s] ?? 0); }
        return response()->json($out)->header('Cache-Control', 'no-store');
    }

   // app/Http/Controllers/VisitController.php
public function room(Request $request, $status)
{
    $keyword = $request->get('search');
    $perPage = 25;

    // ตั้งใจ "ไม่" กรองคลินิก — เจ้าหน้าที่ขอเห็นคนไข้ทุกคลินิกในห้องเดียวกัน
    // จะได้ไม่ต้องคอยสลับคลินิกไปมา (คอลัมน์ "คลินิก" ในตารางบอกอยู่แล้วว่าใครมาจากไหน)
    // พอกดเข้า visit ระบบจะสลับคลินิกให้ตามคนไข้เอง (ดู edit())
    $visit = Visit::with(['patient2','registrationUser','nurseUser','doctorUser','labUser','pharmacyUser'])
    // ผลแล็บอนุมัติแล้วไหม — ใช้ทำป้ายเด่นๆ หลังชื่อคนไข้แทนป็อปอัพเดิม (พยาบาลอาวุโสมองป็อปอัพไม่ทัน, 2569-08-11)
    ->withExists(['labs as lab_approved' => function ($q) {
        $q->whereNotNull('approve_by');
    }])
    ->where('status', $status)
    ->when($keyword, function($query) use ($keyword) {
        $query->whereHas('patient2', function($q) use ($keyword) {
            $q->where('code','like',"%{$keyword}%")
              ->orWhere('name','like',"%{$keyword}%")
              ->orWhere('surname','like',"%{$keyword}%");
        });
    })
    ->orderBy('room_entered_at')
    ->paginate($perPage);

    return view('visit.room', compact('visit','status'));
}


/**
 * ส่งคนไข้ข้ามคลินิก โดย "เปิด visit ใหม่" ให้เลย (ไม่ย้าย visit เดิม)
 *
 * พยาบาลส่งเองได้จากหน้าตรวจ คนไข้ไม่ต้องเดินกลับไปเปิด visit ที่เวชระเบียน
 * เปิดใหม่แทนการย้าย เพราะงานคนละคลินิกต้องนับสถิติและคิดเงินแยกกัน
 * ถ้าย้าย visit เดิม งานที่ทำไปแล้วในคลินิกต้นทางจะหายไปจากรายงานคลินิกนั้น
 */
public function sendToClinic(Request $request, $id)
{
    $from = Visit::findOrFail($id);

    $data = $request->validate([
        'clinic' => ['required', 'integer', 'in:1,2,3'],
    ]);
    $to   = (int) $data['clinic'];
    $page = $request->input('page', 2);

    if ($to === (int) $from->clinic) {
        return back()->with('flash_message', 'คนไข้อยู่คลินิกนี้อยู่แล้ว');
    }

    $todayBE = (now()->year + 543) . now()->format('-m-d');

    // กันกดซ้ำ/กดพร้อมกันจนได้ visit ซ้อนในวันเดียว
    $exists = Visit::where('patient_id', $from->patient_id)
        ->where('clinic', $to)
        ->where('date', $todayBE)
        ->first();

    if ($exists) {
        return back()->with('flash_message',
            'คนไข้รายนี้มี visit ของคลินิก ' . (Visit::$ClinicShortName[$to] ?? $to) . ' ในวันนี้อยู่แล้ว');
    }

    // ห้องเริ่มต้นของปลายทาง: TMC เข้าห้อง 7, คลินิกอื่นเข้าห้องตรวจชายเป็นค่าตั้งต้น
    $startStatus = $to === 3 ? 7 : 2;

    // สร้าง visit + lab คู่กันเสมอ เหมือน PatientController::createVisit() —
    // ของเดิมสร้างแค่ visit ทำให้ visit ใหม่ไม่มี lab แล้ว Visit::RPR() (ใช้ในตาราง Visit History)
    // ไป Lab::where(...)->first()->id บน null ทำหน้า visit/edit พังเป็น 500 ทันทีที่ redirect กลับมา
    // (เกิดจริง 2569-08-10 กระทบผู้ใช้หลายคนที่กด "ส่งคนไข้ข้ามคลินิก")
    $new = DB::transaction(function () use ($from, $to, $startStatus, $todayBE) {
        $new = Visit::create([
            'patient_id'      => $from->patient_id,
            'clinic'          => $to,
            'status'          => $startStatus,
            'date'            => $todayBE,
            // เลขคิวเดิมไว้เรียกชื่อ/ระบุตัวคนไข้ แต่ไม่ผูก queue_system_id ซ้ำ
            // เพราะแถวคิวหนึ่งแถวควรผูกกับ visit เดียว ไม่งั้นระบบเลือกคิวจะมองว่าถูกจองซ้อน
            'queue'           => $from->queue,
            'room_entered_at' => now(),
            'registration_by' => Auth::id(),
            'registration_at' => now(),
        ]);
        \App\Models\Lab::create([
            'patient_id' => $new->patient_id,
            'visit_id'   => $new->id,
        ]);
        return $new;
    });

    return redirect()
        ->route('visit.edit', ['visit' => $from->id, 'page' => $page])
        ->with('flash_message',
            'เปิด visit ' . (Visit::$ClinicShortName[$to] ?? $to) . ' ให้แล้ว (เลขที่ ' . $new->id . ') — คนไข้ไปที่ห้อง '
            . (Visit::$statusOption[$startStatus] ?? $startStatus) . ' ได้เลย');
}

/**
 * รายการคนไข้ "วันนี้" ทุกคลินิก ทุกสถานะ
 *
 * จำเป็นสำหรับ TMC/TB ที่ไม่ได้เดินตาม flow ห้องตรวจของ STI
 * (visit ของ TMC ถูกปิดเป็น status 6 ตั้งแต่ต้น จึงไม่โผล่ในรายการห้องไหนเลย
 *  ก่อนหน้านี้เข้าถึงได้ทางเดียวคือค้นหา ซึ่งใช้งานจริงไม่ไหว)
 * ไม่กรองคลินิกด้วยเหตุผลเดียวกับ room()
 */
public function today(Request $request)
{
    $keyword = $request->get('search');
    $todayBE = (now()->year + 543) . now()->format('-m-d');

    $visit = Visit::with(['patient2','registrationUser','nurseUser','doctorUser','labUser','pharmacyUser'])
        ->where('date', $todayBE)
        ->when($keyword, function ($query) use ($keyword) {
            $query->whereHas('patient2', function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%")
                  ->orWhere('name', 'like', "%{$keyword}%")
                  ->orWhere('surname', 'like', "%{$keyword}%");
            });
        })
        ->orderByRaw('room_entered_at IS NULL, room_entered_at')
        ->orderBy('id')
        ->paginate(25);

    // ใช้ view เดียวกับรายการห้อง (ไม่ได้อ้าง $status จึงส่งไปเฉยๆ ได้)
    return view('visit.room', ['visit' => $visit, 'status' => null]);
}

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create(Request $request)
    {
        $requestData = $request->all();
        $visit = Visit::create($requestData);
        return redirect(route('visit.edit', $visit->id))->with('flash_message', 'Visit added!');
        return view('visit.create');
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
        $requestData = Visit::format2DB($requestData);
        $requestData2 = $request->all();
        $requestData2 = Patient::format2DB($requestData2);
        $visit = Visit::create($requestData);
        flash('Visit added!', 'success');
        return redirect(route('visit.edit', $visit->id))->with('flash_message', 'Visit added!');
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
        $visit = Visit::findOrFail($id);

        return view('visit.show', compact('visit'));
    }


    /** ช่องข้อความคลินิกที่ให้ดูย้อนหลัง/ดึงของเดิมมาใช้ได้ (คีย์ = ชื่อคอลัมน์ใน visits) */
    public static $historyFields = [
        'symptom'    => 'อาการเจ็บป่วยครั้งปัจจุบัน',
        'body_check' => 'การตรวจร่างกาย',
        'treatment'  => 'การให้คำแนะนำ',
    ];

    /**
     * ประวัติย้อนหลังของช่องข้อความช่องเดียว (popup ในหน้าตรวจ)
     * มีปุ่มดึงข้อความเดิมมาใช้ เพราะส่วนใหญ่เนื้อหาซ้ำกับครั้งก่อน จะได้ไม่ต้องพิมพ์ใหม่
     */
    public function fieldHistory($id, $field)
    {
        abort_unless(array_key_exists($field, self::$historyFields), 404);

        $visit = Visit::findOrFail($id);

        // visits.date เก็บเป็น พ.ศ. รูปแบบ Y-m-d จึงเรียงแบบข้อความได้ตรงตามเวลาจริง
        $rows = Visit::where('patient_id', $visit->patient_id)
            ->whereNotNull($field)
            ->where($field, '!=', '')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'date', $field]);

        return view('visit.field_history', [
            'rows'      => $rows,
            'field'     => $field,
            'label'     => self::$historyFields[$field],
            'visit'     => $visit,
            'patient'   => $visit->patient(),   // patient() เป็นเมธอดธรรมดา ไม่ใช่ relation
        ]);
    }

    public function medicine($id)
    {
        $visit = Visit::findOrFail($id);

        return view('visit.medicine', compact('visit'));
    }

 public function lab($id)
{
    $visit = Visit::findOrFail($id);

    // บันทึกผู้แตะล่าสุด (source 'lab' ไม่เข้าเงื่อนไข nurse/doctor จึงเขียนแค่ handled_by
    // ไม่ไปแตะ nurse_by/doctor_by ให้เส้นทางการรับบริการเพี้ยน)
    $this->stampByRules($visit, ['source' => 'lab', 'overwrite' => true]);

    return view('visit.lab', compact('visit'));
}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     *
     * @return \Illuminate\View\View
     */
public function edit(Request $request, $id)
{
    $visit = Visit::findOrFail($id);

    // ตั้งใจ "ไม่" สลับคลินิกตอนเปิด visit — เดิมสลับให้อัตโนมัติ แต่ทำให้หน้าจอเปลี่ยน
    // (เช่นคลิกคนไข้ TMC แล้วคลินิกกลายเป็น TMC) จนเจ้าหน้าที่งงว่าคนไข้หายไปไหน
    // ตอนนี้รายการห้อง/ค้นหาแสดงทุกคลินิกอยู่แล้ว ไม่ต้องพึ่ง active_clinic
    // ส่วนหัวห้องยังเลือกส่งคนไข้ไปคลินิกไหนได้ตามเดิม (ช่อง "คลินิก" ในหน้านี้)

    $visit->formatDBBack();

    $hasMethod = $visit->latestLabMethod()->exists();

    $page = $request->input('page', 1); // ✅ รองรับ page

    // คิวที่ผูกไว้กับ visit นี้ (ล็อกไว้ตั้งแต่ตอนลงทะเบียนที่หน้าผู้ป่วยแล้ว)
    $linkedQueue = $visit->queue_system_id
        ? \App\Models\QueueSystemQueue::find($visit->queue_system_id)
        : null;
    // วินาทีที่ต้องรอก่อนกดเรียกคิวได้ (ให้เวลาพิมพ์บัตรคิวก่อน)
    $queueCallWait = $linkedQueue ? $this->queueCallWaitSeconds($linkedQueue) : 0;

    // Popup note: ผูกกับคนไข้ ติดตัวไปทุก visit (ไม่ใช่แค่ visit นี้)
    $patientNotes = \App\Models\PatientNote::where('patient_id', $visit->patient_id)
        ->with(['author', 'resolvedByUser'])
        ->orderByDesc('id')
        ->get();

    $readNoteIds = \App\Models\PatientNoteRead::where('user_id', auth()->id())
        ->whereIn('patient_note_id', $patientNotes->pluck('id'))
        ->pluck('patient_note_id');

    $unreadNoteCount = $patientNotes->whereNotIn('id', $readNoteIds)->count();

    // ค่าบริการ: ให้แพทย์/พยาบาลติ๊กได้ตั้งแต่หน้าตรวจ แล้วไหลเข้าบิลอัตโนมัติ (billing_items type=service)
    $serviceMaster = \App\Models\ServicePrice::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
    $savedServiceQtys = [];
    $savedServicePrices = [];
    $savedServiceWaived = [];   // service_id => ถูกขอความอนุเคราะห์ (ยกเว้น) ไว้ไหม
    $existingBilling = \App\Models\Billing::where('visit_id', $visit->id)->first();
    if ($existingBilling) {
        foreach (\App\Models\BillingItem::where('billing_id', $existingBilling->id)->where('item_type', 'service')->get() as $bi) {
            $savedServiceQtys[$bi->item_id] = $bi->qty;
            $savedServicePrices[$bi->item_id] = $bi->unit_price;
            $savedServiceWaived[$bi->item_id] = (bool) $bi->waived;
        }
    }

    // ===== เตรียมข้อมูล Lab สำหรับคิดเงิน (โชว์ในหน้าตรวจ ให้หมอ/พยาบาลตั้งไว้ก่อน การเงินรีวิว) =====
    // ใช้กติกาเดียวกับหน้า pharmacy/edit: เลือด auto, HBsAg/Anti-HCV เบิกสปสช, Gram/C&S/ฯลฯ คลินิกคิดเอง
    $visit->loadMissing('labs.labSub2');
    $labMasterPrices = \App\Models\LabPrice::pluck('price', 'name')->toArray();
    $labMethods = \App\Models\LabSub::$methodOption;

    $savedLabPrices = [];
    $savedLabWaived = [];
    if ($existingBilling) {
        foreach (\App\Models\BillingItem::where('billing_id', $existingBilling->id)->where('item_type', 'lab')->get() as $bi) {
            $savedLabPrices[$bi->item_id] = $bi->unit_price;
            $savedLabWaived[$bi->item_id] = (bool) $bi->waived;
        }
    }

    // RPR ฟรี: เคยวินิจฉัย Syphilis + สั่ง TPHA(7)
    $patientVisitIds = \App\Models\Visit::where('patient_id', $visit->patient_id)->pluck('id');
    $hasSyphilisDx = \App\Models\Diagnosi::whereIn('visit_id', $patientVisitIds)->where('disease', 1)->exists();
    $labMethodsPresent = ($visit->labs && $visit->labs->labSub2)
        ? $visit->labs->labSub2->pluck('method')->map(fn ($m) => (int) $m) : collect();
    $freeRpr = $hasSyphilisDx && $labMethodsPresent->contains(7);

    $labs = collect();
    if ($visit->labs && $visit->labs->labSub2) {
        foreach ($visit->labs->labSub2 as $sub) {
            $method = (int) $sub->method;
            $methodName = $labMethods[$sub->method] ?? 'ไม่ระบุรายการ';
            $master = (float) ($labMasterPrices[$methodName] ?? 0);
            $category = \App\Models\LabSub::billingCategory($method);
            $hasSaved = array_key_exists($sub->id, $savedLabPrices);
            $price = $hasSaved ? (float) $savedLabPrices[$sub->id] : ($category === 'manual' ? 0.0 : $master);
            $isFree = false;
            if ($freeRpr && $method === 8) { $price = 0; $isFree = true; }
            $ssoClaimed = ($category === 'sso' && $hasSaved && (float) $price === 0.0);
            $labs->push((object)[
                'id' => $sub->id, 'name' => $methodName, 'price' => $price, 'free' => $isFree,
                'category' => $category, 'master_price' => $master, 'sso_claimed' => $ssoClaimed,
                'waived' => $savedLabWaived[$sub->id] ?? false,   // การเงินอนุเคราะห์ไว้ — หน้าตรวจแค่บอก
            ]);
        }
    }

    return view('visit.edit', compact('visit', 'hasMethod', 'page', 'linkedQueue', 'queueCallWait','patientNotes', 'unreadNoteCount', 'serviceMaster', 'savedServiceQtys', 'savedServicePrices', 'savedServiceWaived', 'labs'));
}

    // เลือกคิวจากระบบเรียกคิวกลาง มาผูกกับ visit นี้
    public function selectQueue(Request $request, $id)
{
    $visit = Visit::findOrFail($id);
    $queue = \App\Models\QueueSystemQueue::findOrFail($request->input('queue_system_queue_id'));

    // กันจองคิวซ้ำ: ถ้าคิวนี้ถูกผูกกับ visit อื่นที่ยังไม่กลับบ้าน (status != 6) อยู่แล้ว ห้ามเลือกซ้ำ
    $takenByOther = Visit::where('queue_system_id', $queue->id)
        ->where('id', '!=', $visit->id)
        ->where('status', '!=', 6)
        ->exists();

    if ($takenByOther) {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'error', 'message' => 'คิว ' . $queue->queue_number . ' ถูกผูกกับผู้ป่วยรายอื่นแล้ว กรุณาเลือกคิวอื่น'], 422);
        }
        Alert::error('คิว ' . $queue->queue_number . ' ถูกผูกกับผู้ป่วยรายอื่นแล้ว', 'กรุณาเลือกคิวอื่น');
        return back();
    }

    $visit->queue_system_id = $queue->id;
    $visit->queue = $queue->queue_number; // ให้หน้าจออื่น (ห้องยา ฯลฯ) ที่ใช้ visits.queue อยู่แล้วเห็นเลขคิวตรงกัน
    $visit->save();

    if ($request->ajax() || $request->wantsJson()) {
        return response()->json(['status' => 'success', 'queue' => $queue->queue_number, 'room' => $queue->room_number]);
    }

    flash('เลือกคิว ' . $queue->queue_number . ' ให้ visit นี้แล้ว', 'success');
    Alert::success('เลือกคิวเรียบร้อยแล้ว');

    return back();
}

    // ดึงรายการคิวที่กำลังรอ (status=waiting) ในห้องที่เกี่ยวกับ STI (4=ชาย, 5=หญิง, 6=ฉีดยา/Treatment)
    // ใช้แบบ AJAX เพื่อให้หน้าเลือกคิวเห็นข้อมูลสดๆ ไม่ใช่แค่ตอนโหลดหน้าครั้งแรก
    public function availableQueues(Request $request)
    {
        // คิวที่ถูกผูกกับ visit ที่ยังไม่กลับบ้าน (status != 6) ถือว่าถูกจองแล้ว ไม่เอามาให้เลือกซ้ำ
        // (ยกเว้นคิวของ visit ที่กำลังแก้ไขอยู่เอง เพื่อให้ยังเห็นคิวปัจจุบันของตัวเองได้)
        $excludeVisitId = $request->input('visit_id');
        $lockedQueueIds = Visit::whereNotNull('queue_system_id')
            ->where('status', '!=', 6)
            ->when($excludeVisitId, function ($q) use ($excludeVisitId) {
                $q->where('id', '!=', $excludeVisitId);
            })
            ->pluck('queue_system_id')
            ->all();

        // ห้องคิวต่างกันตามคลินิก (STI = 4,5,6 · TB = 2,6,10 · TMC = 7)
        // ใช้คลินิกที่เลือกในฟอร์ม (ถ้าส่งมา) ไม่ใช่ active clinic — กันเคสเปิด TMC ขณะ active เป็น STI แล้วคิวห้อง 7 ไม่ขึ้น
        $clinic = $request->input('clinic');
        // ปุ่ม "เลือกคิว" ในหน้า Room ส่งมาแค่ visit_id — ไม่มีคลินิก/ห้อง เดิมเลยตกไปใช้ active clinic
        // เปิดห้อง TB ขณะ active เป็น STI → ได้คิวห้อง 4,5,6 คิวห้อง TB ไม่ขึ้น ต้องลบ visit ลงใหม่ (แจ้ง 2569-10-08)
        // → ไม่ได้ส่งมา = ใช้คลินิกและห้องของ visit นั้นเอง
        $selfVisit = ($excludeVisitId && ($clinic === null || $clinic === '')) ? Visit::find($excludeVisitId) : null;
        if ($selfVisit) {
            $clinic = $selfVisit->clinic;
            if (! $request->filled('status')) { $request->merge(['status' => $selfVisit->status]); }
        }
        $rooms = Visit::queueRooms($clinic !== null && $clinic !== '' ? (int) $clinic : null);

        // ถ้าหน้าลงทะเบียนบอกมาว่าจะส่งไปห้องไหน ให้เอาคิวของห้องนั้นเป็นหลัก
        // เคสจริง: พยาบาลเลือก "ส่งไปที่ ห้องตรวจวัณโรค 2" แต่คลินิกในฟอร์มยังเป็น STI
        // รายการคิวเลยไปดึงห้อง 4,5,6 ซึ่งว่าง ทำให้ขึ้นแค่ "ไม่ล็อกคิว" (2569-09-29)
        //
        // แต่ห้ามตัดคิวห้องอื่นของคลินิกทิ้ง — เดิมเลือกห้องแล้วเหลือแค่คิวห้องนั้น ถ้าบัตรคิวคนไข้ออกมาเป็นห้องอื่น
        // (เช่นบัตรห้อง 5 แต่ส่งเข้าห้อง 4) หรือเบราว์เซอร์จำห้องที่เลือกค้างไว้ตอนกดย้อนกลับ ช่องคิวจะว่างเปล่า
        // พยาบาลต้องสลับคลินิกไปมา (ซึ่งล้างช่องห้องโดยบังเอิญ) คิวถึงโผล่ (แจ้ง 2569-10-02)
        // → รวมห้องที่จะส่งไปเข้ากับห้องของคลินิก แล้วเอาคิวห้องนั้นขึ้นก่อน
        $status = $request->input('status');
        $room = ($status !== null && $status !== '') ? (Visit::$statusQueueRoom[(int) $status] ?? null) : null;
        if ($room && ! in_array($room, $rooms, true)) { $rooms[] = $room; }
        // ห้อง 1 = เวชระเบียน — บัตรที่จองคิวไว้ล่วงหน้า (เช่น "จะกลับมาบ่าย") ยังไม่รู้ว่าไปคลินิกไหน
        // ต้องเลือกได้จากทุกคลินิก ไม่งั้นคนไข้กลับมาแล้วหาเลขคิวในช่องไม่เจอ (2569-10-02)
        if (! in_array(1, $rooms, true)) { $rooms[] = 1; }

        // เฉพาะคิวที่ออกวันนี้ (นาฬิกาฝั่งฐานคิว) — คิวที่ค้างจากเมื่อวานไม่ให้ไปผูกกับคนไข้วันนี้
        $queues = \App\Models\QueueSystemQueue::where('status', 'waiting')
            ->whereRaw('DATE(created_at) = CURDATE()')
            ->whereIn('room_number', $rooms)
            ->when($lockedQueueIds, function ($q) use ($lockedQueueIds) {
                $q->whereNotIn('id', $lockedQueueIds);
            })
            ->when($room, fn ($q) => $q->orderByRaw('room_number = ? DESC', [$room]))
            ->orderBy('queue_number')
            ->get(['id', 'queue_number', 'room_number']);

        return response()->json($queues);
    }

    // คิวต้องออกมาครบ 60 วินาทีก่อนถึงจะเรียกได้ — กติกาเดียวกับปุ่ม Call ในโปรแกรมเรียกคิวกลาง (public/queue/fetch_queues.php)
    // ใช้นาฬิกาของ MySQL ฝั่งคิว (เวลาเดียวกับที่ใส่ created_at) กันเวลาเครื่อง PHP/DB ไม่ตรงกัน
    private function queueCallWaitSeconds(\App\Models\QueueSystemQueue $queue): int
    {
        $age = $queue->getConnection()
            ->selectOne('SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age FROM queues WHERE id = ?', [$queue->id]);

        return $age && $age->age !== null ? max(0, 60 - (int) $age->age) : 0;
    }

    // กดเรียกคิว (ทำงานเหมือน call_queue.php ของระบบเรียกคิวกลางทุกประการ)
    public function callQueue(Request $request, $id)
{
    $visit = Visit::findOrFail($id);

    $wantsJson = $request->ajax() || $request->wantsJson();

    if (!$visit->queue_system_id) {
        if ($wantsJson) {
            return response()->json(['status' => 'error', 'message' => 'ยังไม่ได้เลือกคิว กรุณาเลือกคิวก่อนกดเรียกคิว'], 422);
        }
        Alert::error('ยังไม่ได้เลือกคิว', 'กรุณาเลือกคิวก่อนกดเรียกคิว');
        return back();
    }

    $queue = \App\Models\QueueSystemQueue::find($visit->queue_system_id);
    if (!$queue) {
        if ($wantsJson) {
            return response()->json(['status' => 'error', 'message' => 'ไม่พบคิวนี้ในระบบเรียกคิว'], 404);
        }
        Alert::error('ไม่พบคิวนี้ในระบบเรียกคิว', '');
        return back();
    }

    $wait = $this->queueCallWaitSeconds($queue);
    if ($wait > 0) {
        $msg = 'คิวนี้เพิ่งออก รออีก ' . $wait . ' วินาที ให้พิมพ์บัตรคิวก่อนค่อยเรียก';
        if ($wantsJson) {
            return response()->json(['status' => 'wait', 'wait' => $wait, 'message' => $msg], 429);
        }
        Alert::warning('ยังเรียกคิวไม่ได้', $msg);
        return back();
    }

    // ปิด is_calling ของคิวอื่นที่กำลังเรียกอยู่ในห้องเดียวกันก่อน (เหมือน call_queue.php)
    \App\Models\QueueSystemQueue::where('room_number', $queue->room_number)
        ->where('is_calling', 1)
        ->update(['is_calling' => 0]);

    $queue->status = 'called';
    $queue->call_count = $queue->call_count + 1;
    $queue->is_calling = 1;
    $queue->save();

    // หน้าตรวจเรียกผ่าน AJAX — ไม่โหลดหน้าใหม่ ข้อมูลที่กรอกค้างไว้จะไม่หาย
    if ($wantsJson) {
        return response()->json([
            'status'     => 'success',
            'queue'      => $queue->queue_number,
            'room'       => $queue->room_number,
            'call_count' => (int) $queue->call_count,
        ]);
    }

    flash('เรียกคิว ' . $queue->queue_number . ' ห้อง ' . $queue->room_number . ' แล้ว', 'success');
    Alert::success('เรียกคิว ' . $queue->queue_number . ' เรียบร้อยแล้ว');

    return back();
}



    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param  int  $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
   /**
     * บันทึกนัดหลายวันของ visit หนึ่งใบ (พยาบาลขอ 2569-08-26)
     *
     * ฟอร์มส่งมาเป็นชุด appt_id[] / appt_date[] / appt_reason[] / appt_note[]
     * แถวที่ลบออกจากหน้าจอจะไม่ถูกส่งมา -> ตีความว่า "ลบ"
     *
     * เสร็จแล้วต้องเรียก syncMirror() เสมอ เพื่อให้ visits.appointment
     * (ที่โค้ดเก่า 81 จุดยังอ่านอยู่) ตรงกับนัดที่ใกล้ที่สุด
     *
     * คืนรายชื่อวันหยุดที่ชนกับวันนัด เพื่อเอาไปเตือนพยาบาล (เตือนอย่างเดียว ไม่บล็อก
     * เหมือนของเดิม — คนตัดสินใจคือพยาบาลไม่ใช่ระบบ)
     */
    /**
     * รายชื่อช่อง (ชื่อระดับบนสุด เช่น symptom, status, appt_date, services) ที่ผู้ใช้แก้ในหน้านี้
     * หน้าเว็บคำนวณแล้วส่งมาใน _touched ตอนกด Save — null = ไม่มีข้อมูล (JS ไม่ทำงาน) ให้เซฟทั้งฟอร์มแบบเดิม
     */
    private function touchedKeys(Request $request): ?array
    {
        $raw = $request->input('_touched');
        if (!is_string($raw) || $raw === '') { return null; }

        $list = json_decode($raw, true);
        if (!is_array($list)) { return null; }

        return array_flip(array_filter($list, 'is_string'));
    }

    /** นัดที่หน้าเว็บเห็นตอนเปิดหน้า (ส่งมาใน _orig_appt) */
    private function origAppointments(Request $request): array
    {
        $list = json_decode((string) $request->input('_orig_appt', ''), true);
        $out = [];
        foreach ((array) $list as $id => $row) {
            if ((int) $id > 0 && is_array($row)) { $out[(int) $id] = $row; }
        }
        return $out;
    }


    /**
     * แผนนัดฉีดวัคซีน TMC จากฟอร์ม -> รูปแบบที่เก็บ (ใช้ทั้งปุ่ม Save ของหน้าตรวจ และบันทึกอัตโนมัติของกล่องแผน)
     * ฟอร์มส่งมาเป็น 5 นัด × ช่องวัคซีนคงที่ (index ต้องคงที่ให้ช่องจับคู่ถูก) — เก็บเฉพาะที่กรอกจริง
     * วันที่ใช้ตัวอ่านเข้มงวดเดียวกับงาน TB: ถูก = วว/ดด/พ.ศ. · ผิด = เก็บตามที่พิมพ์ + คืนข้อความใน $bad (ไม่ทิ้งค่า)
     * $origJson = แผนตอนเปิดหน้า — ระหว่างเปิดค้าง ระบบ/อีกเครื่องอาจแก้แผน เอาความเปลี่ยนแปลงนั้นมารวม ไม่เขียนทับ
     */
    private function cleanVaccinePlan(Visit $visit, $plan, $origJson, array &$bad): array
    {
        $clean = [];
        if (is_array($plan)) {
            foreach ($plan as $row) {
                if (!is_array($row)) { continue; }
                $date = trim((string) ($row['date'] ?? ''));
                if ($date !== '') {
                    $pd = \App\Support\ThaiDate::parse($date);
                    if ($pd['ok']) { $date = \App\Support\ThaiDate::show($pd['be']); }
                    else { $bad[] = $pd['error']; }
                }
                $items = [];
                foreach ((array) ($row['items'] ?? []) as $it) {
                    if (!is_array($it)) { continue; }
                    $name = trim((string) ($it['name'] ?? ''));
                    if ($name === '') { continue; }   // ยังไม่เลือกวัคซีน = ช่องว่าง
                    $items[] = ['name' => $name, 'dose' => \App\Models\VisitVaccine::doseLabel((string) ($it['dose'] ?? ''))];
                }
                if ($date === '' && empty($items)) { continue; }
                $r = ['date' => $date, 'items' => $items];
                // ฉีดที่อื่น (2569-10-09) — เก็บเฉพาะเมื่อเลือก "ฉีดที่อื่น" · ชื่อสถานที่ไม่บังคับ
                if (($row['site'] ?? '') === 'other') {
                    $r['site'] = 'other';
                    $r['place'] = mb_substr(trim((string) ($row['place'] ?? '')), 0, 150);
                } elseif (($row['site'] ?? '') === 'self') {
                    $r['site'] = 'self';   // วัคซีนชนิดกิน รับไปทานเองที่บ้าน (TMC ขอ 2569-10-27)
                }
                $clean[] = $r;
            }
        }
        $orig = json_decode((string) $origJson, true);
        if (is_array($orig)) {
            $clean = $this->mergeVaccinePlan(\App\Models\VisitVaccine::normalizePlan($orig),
                \App\Models\VisitVaccine::normalizePlan($visit->tmc_vaccine_plan), $clean);
        }
        return $clean;
    }

    /**
     * บันทึกแผนนัดฉีดวัคซีนทันทีที่แก้ (คลินิกแจ้ง 2569-10-05) — เดิมแผนบันทึกพร้อมปุ่ม Save เท่านั้น
     * พยาบาลกรอกแผนแล้วกด "เพิ่มการฉีด" (ฟอร์มแยก โหลดหน้าใหม่) แผนที่ยังไม่ Save หายไปเงียบ ๆ กลับมาดูไม่รู้ว่าบันทึกอะไรไป
     */
    public function saveVaccinePlan(Request $request, $id)
    {
        $visit = Visit::findOrFail($id);
        $bad = [];
        $visit->tmc_vaccine_plan = $this->cleanVaccinePlan($visit, $request->input('plan', []), $request->input('orig'), $bad);
        $visit->save();
        $saved = \App\Models\VisitVaccine::normalizePlan($visit->tmc_vaccine_plan);
        // วันในแผน → ลงตารางวันนัดด้วย (รายชื่อนัดรวม / LINE เตือน) 2569-10-28
        $apptChanged = \App\Models\VisitAppointment::syncVaccinePlan($visit);

        return response()->json([
            'ok'    => true,
            'appt'  => $apptChanged,
            'plan'  => $saved,
            'time'  => now()->format('H:i'),
            'warn'  => $bad ? 'วันที่ไม่ถูกต้อง — ' . implode(' · ', $bad) : null,
        ]);
    }

    /**
     * @param array|null $orig  นัดที่หน้าเว็บเห็นตอนเปิดหน้า [id => ['date'=>..,'reason'=>..,'note'=>..]]
     *                          null = ไม่รู้ (JS ไม่ทำงาน) -> ทำแบบเดิม
     */
    /**
     * บันทึกใบซักประวัติ/คัดกรองวัณโรคของการมาครั้งนี้ (หนึ่งใบต่อหนึ่ง visit)
     *
     * คะแนนอาการคิดใหม่ทุกครั้งจากอาการที่ติ๊ก ไม่เชื่อค่าที่ส่งมาจากหน้าจอ
     * (หน้าจอคิดให้ดูเฉย ๆ ถ้าเชื่อค่านั้นแล้วมีคนแก้ HTML คะแนนจะเพี้ยน)
     */
    /**
     * รวมแผนนัดวัคซีน 3 ฉบับ: ตอนเปิดหน้า ($orig) · ในฐานตอนนี้ ($db) · ที่ฟอร์มส่งมา ($form)
     * รายการที่ฐานเพิ่มเข้ามาหลังเปิดหน้า → เติมเข้าไป · รายการที่ฐานเอาออกไปแล้วแต่ฟอร์มยังส่งค่าเดิมมา → ตัดออก
     * ที่เหลือเป็นไปตามฟอร์ม (สิ่งที่คนกดแก้ในหน้านี้)
     */
    private function mergeVaccinePlan(array $orig, array $db, array $form): array
    {
        $keys = function (array $plan) {
            $out = [];
            foreach ($plan as $r) {
                foreach ($r['items'] as $it) { $out[$r['date'] . '|' . $it['name'] . '|' . $it['dose']] = [$r['date'], $it]; }
            }
            return $out;
        };
        $o = $keys($orig); $d = $keys($db);
        if ($o == $d) { return $form; }                   // ไม่มีใครแตะระหว่างเปิดหน้า

        $removed = array_diff_key($o, $d);
        foreach ($form as $i => $r) {
            $form[$i]['items'] = array_values(array_filter($r['items'],
                fn ($it) => ! isset($removed[$r['date'] . '|' . $it['name'] . '|' . $it['dose']])));
            if ($form[$i]['date'] !== '' && ! $form[$i]['items'] && $r['items']) { unset($form[$i]); }
        }
        $form = array_values($form);

        foreach (array_diff_key($d, $o) as [$date, $it]) {
            $placed = false;
            foreach ($form as $i => $r) {
                if ($r['date'] === $date && count($r['items']) < \App\Models\VisitVaccine::PLAN_ITEMS_PER_VISIT) {
                    $form[$i]['items'][] = $it; $placed = true; break;
                }
            }
            if (! $placed && count($form) < \App\Models\VisitVaccine::PLAN_MAX_ROWS) {
                $form[] = ['date' => $date, 'items' => [$it]];
            }
        }
        return $form;
    }

    private function saveTbScreening(Visit $visit, array $in): void
    {
        $sc = \App\Models\TbScreening::firstOrNew(['visit_id' => $visit->id]);
        $sc->patient_id = $visit->patient_id;
        if (! $sc->exists) { $sc->created_by = auth()->id(); }

        foreach (array_keys(\App\Models\TbScreening::$symptomScore) as $field) {
            $sc->$field = ! empty($in[$field]);
        }

        $text = fn ($k) => isset($in[$k]) && trim((string) $in[$k]) !== '' ? trim((string) $in[$k]) : null;
        $int  = fn ($k) => isset($in[$k]) && $in[$k] !== '' ? (int) $in[$k] : null;

        foreach (['chief_complaint', 'illness_history', 'contact_tb_detail', 'previous_tb_detail',
                  'pe_ga', 'pe_heent', 'pe_heart', 'pe_chest', 'pe_abdomen', 'pe_ext', 'pe_neuro',
                  'impression_site', 'impression_other', 'plan', 'lung_note'] as $f) {
            $sc->$f = $text($f);
        }
        // บันทึกแพทย์ใต้ตรวจร่างกาย (2569-10-08) — เขียนเฉพาะเมื่อฟอร์มส่งมา: หน้าที่เปิดค้างไว้ก่อนมีช่องนี้จะไม่ล้างข้อความที่หมอเขียน
        if (array_key_exists('pe_note', $in) && \App\Models\TbScreening::peNoteReady()) {
            $sc->pe_note = $text('pe_note');
        }
        // บันทึกทางการพยาบาล (2569-10-14) — เขียนเฉพาะเมื่อส่งมา เหตุผลเดียวกับ pe_note
        if (array_key_exists('nursing_note', $in) && \App\Models\TbScreening::nursingNoteReady()) {
            $sc->nursing_note = $text('nursing_note');
        }
        foreach (['contact_tb', 'previous_tb', 'impression', 'dm_hba1c', 'visit_type'] as $f) {
            $sc->$f = $int($f);
        }
        // มาด้วยเรื่อง (ผู้ป่วยใหม่ — 2569-11-04) · เขียนเฉพาะเมื่อส่งมา หน้าเก่าที่เปิดค้างไม่มีช่องนี้ ไม่ให้ล้างเป็นว่าง
        if (array_key_exists('come_reason', $in) && \App\Models\TbScreening::comeReasonReady()) {
            $v = $int('come_reason');
            $sc->come_reason = isset(\App\Models\TbScreening::$comeReasonOption[$v]) ? $v : null;
        }
        // ผลเอกซเรย์ย้ายไปเป็นหลายแถวในช่อง tb_cxr[...] แล้ว (saveTbCxr) — หน้าใหม่ไม่ส่ง tb[cxr_*] มา
        // เขียนเฉพาะเมื่อยังส่งมา (หน้าเก่าที่เปิดค้างไว้) ไม่งั้นแก้ช่องอื่นของใบคัดกรองแล้วผลเอกซเรย์จะถูกล้างเป็นว่าง
        if (array_key_exists('cxr_result', $in)) { $sc->cxr_result = $int('cxr_result'); }
        if (array_key_exists('cxr_note', $in))   { $sc->cxr_note   = $text('cxr_note'); }

        // จุด/เส้นที่มาร์คบนรูปปอด — หน้าจอส่งมาเป็น JSON string เก็บเฉพาะที่อ่านเป็น array ได้
        $lung = $text('lung_marks');
        $sc->lung_marks = $lung && is_array(json_decode($lung, true)) ? json_decode($lung, true) : null;
        // Form 2 ข้อ 2 (ผู้ป่วยต้นทาง) + ข้อ 3 (ลักษณะการสัมผัส)
        foreach (['index_name', 'index_id_card', 'index_diagnosis', 'index_dx_hospital',
                  'index_treat_hospital', 'ct_other'] as $f) {
            $sc->$f = $text($f);
        }
        // ผูกบัตร TB ของผู้ป่วยต้นเหตุ (2569-10-22) — HN / เลขทะเบียน · ว่าง = ลองจับจากเลขบัตรประชาชนของ index
        // ผูกแล้วบัตร index เห็นผู้สัมผัสรายนี้ แม้ไม่ได้เปิดบัตร LTBI (คัดกรองแล้วไม่ติดเชื้อ)
        if (\App\Services\TbContacts::ready() && array_key_exists('index_ref', $in)) {
            $ref = $text('index_ref');
            $idx = $ref ? \App\Services\TbContacts::resolveIndex($ref, (int) $sc->patient_id) : null;
            if (! $idx && ! $ref && $sc->index_id_card) {
                $idx = \App\Services\TbContacts::resolveIndex($sc->index_id_card, (int) $sc->patient_id);
            }
            if ($ref && ! $idx) {
                flash('ผู้ป่วยต้นเหตุ: ไม่พบบัตรวัณโรคของ "' . $ref . '" — ยังไม่ได้ผูก (ใส่ HN หรือเลขทะเบียน TB)', 'warning');
            }
            $sc->index_registration_id = $idx->id ?? null;
        }
        // โรคประจำตัวอื่น ๆ / ปฏิเสธ ย้ายไปใช้ช่อง congenitaldisease ของทะเบียนคนไข้หลัก (2569-09-29)
        // เขียนเฉพาะเมื่อหน้าจอยังส่งมา (หน้าเก่าที่เปิดค้างไว้) ไม่งั้นค่าเดิมจะถูกล้างเป็นว่าง
        if (array_key_exists('underlying_disease', $in)) { $sc->underlying_disease = $text('underlying_disease'); }
        if (array_key_exists('underlying_denied', $in))  { $sc->underlying_denied  = ! empty($in['underlying_denied']); }
        $sc->index_age = $int('index_age');
        foreach (['ct_same_house', 'ct_same_room', 'ct_aircon', 'ct_close', 'ct_close_8h', 'ct_close_120h', 'ct_low_risk'] as $f) {
            $sc->$f = ! empty($in[$f]);
        }

        $sc->has_arv    = ! empty($in['has_arv']);
        $sc->arv_detail = $text('arv_detail');
        $sc->has_dm     = ! empty($in['has_dm']);
        if (! $sc->has_dm) { $sc->dm_hba1c = null; }   // ไม่ได้ติ๊กเบาหวาน = ไม่เก็บระดับน้ำตาลค้างไว้

        $labs = array_values(array_intersect((array) ($in['labs_ordered'] ?? []),
                                             array_keys(\App\Models\TbScreening::$labOption)));
        $sc->labs_ordered  = $labs ?: null;

        // ส่งตรวจทางห้องปฏิบัติการ (สิ่งส่งตรวจ · วิธีตรวจ · ผล · skin test / IGRA · ผลเสมหะ M+/M−)
        // ย้ายไปใช้ชื่อช่อง tb_lab[...] แยกจาก tb[...] แล้ว (2569-10-02) — บันทึกที่ TbScreening::saveLabSection ตาม _touched ของตัวเอง
        // แก้ประวัติในหน้าตรวจจะไม่ไปทับผลแล็บที่อีกห้อง/บัตร TB 01 เพิ่งลง
        // หน้าเก่าที่เปิดค้าง (ยังส่งส่วนนี้มาใน tb[...]) ใช้ตัวเติมค่าตัวเดียวกัน แบบเขียนทั้งส่วนเหมือนเดิม
        if (array_intersect(\App\Models\TbScreening::LAB_LEGACY_KEYS, array_keys($in))) {
            $sc->fillLabInput($in, true);
        }

        // ผังครอบครัว (Form 2 ข้อ 6) — เก็บเข้ารหัสเหมือนรูปคนไข้ ไม่วางไว้ใน public
        $file = request()->file('tb_family_tree');
        if ($file && $file->isValid()) {
            $name = 'ft_' . $sc->visit_id . '_' . time() . '.enc';
            \Illuminate\Support\Facades\Storage::disk('local')
                ->put("private/tb_family_tree/{$name}", \Illuminate\Support\Facades\Crypt::encrypt(file_get_contents($file->getRealPath())));

            // ลบรูปเก่าทิ้ง ไม่ให้ไฟล์ค้างสะสมโดยไม่มีใครอ้างถึง
            if ($sc->family_tree_photo) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete("private/tb_family_tree/{$sc->family_tree_photo}");
            }
            $sc->family_tree_photo = $name;
        }
        if (request()->boolean('tb_family_tree_remove') && $sc->family_tree_photo) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete("private/tb_family_tree/{$sc->family_tree_photo}");
            $sc->family_tree_photo = null;
        }

        $sc->symptom_score = $sc->calcScore();
        $sc->save();

        $this->saveHouseholdMembers($sc, (array) ($in['members'] ?? []));
    }

    /**
     * ผลเอกซเรย์ปอดของใบคัดกรอง — ลงได้หลายแถวในวันเดียว (หลายท่าถ่าย เช่น PA + Lateral) คลินิกขอ 2569-10-02
     *
     * แต่ละแถว {view, result, note} · แถวว่างทั้งแถวไม่เก็บ
     * เก็บทั้งชุดใน tb_screenings.cxr_results (JSON) และเขียนแถวแรกลงคอลัมน์เดิม cxr_result / cxr_note ด้วย
     * ที่อื่นที่อ่านคอลัมน์เดิมจะได้ไม่พัง (แบบเดียวกับ lab_specimens)
     * ถ้ายังไม่ได้รัน migration (ไม่มีคอลัมน์ cxr_results) เก็บได้แถวเดียวในคอลัมน์เดิม — หน้าจอก็ซ่อนปุ่มเพิ่มแถวไว้
     *
     * เป็น static แยกออกมา เพื่อทดสอบตรง ๆ ได้โดยไม่ต้องผ่าน update() (update เขียนไปฐานคิวกลาง)
     */
    public static function saveTbCxr(Visit $visit, array $rows): ?\App\Models\TbScreening
    {
        $clean = [];
        foreach ($rows as $row) {
            if (! is_array($row)) { continue; }
            $view   = trim((string) ($row['view'] ?? ''));
            $result = (int) ($row['result'] ?? 0);
            $note   = mb_substr(trim((string) ($row['note'] ?? '')), 0, 255);
            $doctor = mb_substr(trim((string) ($row['doctor'] ?? '')), 0, 150);
            $r = [
                'view'   => isset(\App\Models\TbXray::$viewOption[$view]) ? $view : null,
                'result' => isset(\App\Models\TbScreening::$cxrOption[$result]) ? $result : null,
                'note'   => $note !== '' ? $note : null,
            ];
            if ($r['view'] === null && $r['result'] === null && $r['note'] === null) { continue; }
            // แพทย์ผู้สั่งตรวจ/อ่านผล (2569-10-09) — ไม่นับเป็นข้อมูลของแถว: เลือกแพทย์อย่างเดียวไม่ถือว่ามีผลเอกซเรย์
            if ($doctor !== '') { $r['doctor'] = $doctor; }
            $clean[] = $r;
        }

        $sc = \App\Models\TbScreening::where('visit_id', $visit->id)->first();
        if (! $sc) {
            if (! $clean) { return null; }   // ยังไม่มีใบ และไม่ได้กรอกอะไร — ไม่ต้องสร้างใบเปล่า
            $sc = new \App\Models\TbScreening(['visit_id' => $visit->id]);
            $sc->patient_id = $visit->patient_id;
            $sc->created_by = auth()->id();
        }

        if (\App\Models\TbScreening::hasCxrRows()) {
            $sc->cxr_results = $clean ?: null;
        }
        $first = $clean[0] ?? null;
        $sc->cxr_result = $first['result'] ?? null;
        $sc->cxr_note   = $first['note'] ?? null;
        $sc->save();

        return $sc;
    }

    /**
     * รายชื่อผู้สัมผัสร่วมบ้าน (Form 2 ข้อ 5)
     * แถวที่ไม่ได้กรอกชื่อ = แถวเปล่า ไม่เก็บ · แถวที่หายไปจากฟอร์ม = พยาบาลลบออก
     */
    private function saveHouseholdMembers(\App\Models\TbScreening $sc, array $rows): void
    {
        $keep = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') { continue; }

            $values = [
                'name'          => $name,
                'age'           => trim((string) ($row['age'] ?? '')) !== '' ? (int) $row['age'] : null,
                'relation'      => trim((string) ($row['relation'] ?? '')) ?: null,
                'disease'       => trim((string) ($row['disease'] ?? '')) ?: null,
                'screened_date' => \App\Utillity::th2dbDate(trim((string) ($row['screened_date'] ?? ''))) ?: null,
            ];
            // ผลตรวจของผู้สัมผัสแต่ละคน — ตามตารางที่คลินิกใช้จริง
            foreach (['symptoms', 'cxr_result', 'sputum_result', 'igra_result', 'treatment_status', 'note'] as $f) {
                $values[$f] = trim((string) ($row[$f] ?? '')) ?: null;
            }

            // HN ว่าง = ผู้สัมผัสรายนี้ยังไม่ได้มาตรวจที่คลินิก
            // ถ้ากรอก HN แล้วตรงกับคนไข้ในระบบ ผูก patient_id ไว้ด้วย จะได้กดข้ามไปดูประวัติได้
            $hn = trim((string) ($row['hn'] ?? ''));
            $values['hn'] = $hn ?: null;
            $values['patient_id'] = $hn !== ''
                ? \App\Models\Patient::where('code', $hn)->value('id')
                : null;

            $id = (int) ($row['id'] ?? 0);
            $m = $id ? \App\Models\TbHouseholdMember::where('tb_screening_id', $sc->id)->find($id) : null;
            if ($m) { $m->update($values); }
            else { $m = \App\Models\TbHouseholdMember::create($values + ['tb_screening_id' => $sc->id]); }
            $keep[] = $m->id;
        }

        \App\Models\TbHouseholdMember::where('tb_screening_id', $sc->id)
            ->whereNotIn('id', $keep ?: [0])->delete();
    }

    private function saveAppointments(Visit $visit, Request $request, ?array $orig = null): array
    {
        if (! $request->has('appt_date')) {
            return [];   // ฟอร์มที่ยังไม่มีช่องนี้ (เช่นหน้าอื่น) -> ไม่แตะนัดเลย
        }

        $ids     = (array) $request->input('appt_id', []);
        $dates   = (array) $request->input('appt_date', []);
        $reasons = (array) $request->input('appt_reason', []);
        $notes   = (array) $request->input('appt_note', []);

        // ช่องของคลินิกวัณโรค: เวลา · ก่อนพบแพทย์ · แพทย์ที่ทำการนัด (2569-09-29)
        // มีเฉพาะหน้าคลินิกวัณโรค — คลินิกอื่นไม่ส่งมา ต้องไม่ไปล้างค่าเป็นว่าง
        $hasTb = $request->has('appt_time');
        $times = (array) $request->input('appt_time', []);
        $pres  = (array) $request->input('appt_pre', []);
        $docs  = (array) $request->input('appt_doctor', []);
        $tbVal = function ($i) use ($times, $pres, $docs) {
            $t = trim((string) ($times[$i] ?? ''));
            return [
                'appt_time' => preg_match('/^\d{2}:\d{2}$/', $t) ? $t : null,
                'pre_visit' => trim((string) ($pres[$i] ?? '')) ?: null,
                'doctor_id' => (int) ($docs[$i] ?? 0) ?: null,
            ];
        };

        $keep = [];
        $clash = [];
        $lostText = [];   // ข้อความที่พิมพ์ไว้แต่ยังไม่มีวันนัดให้ผูก
        $handover = [];   // ช่องเทา (ส่งเวร) ที่ไม่มีวันนัด → ส่งเวรครั้งถัดไปของคนไข้ (patient_notes)

        foreach ($dates as $i => $raw) {
            $raw = trim((string) $raw);

            // แถวที่ไม่มีวันนัด: เดิมข้ามทิ้งเงียบ ๆ ทั้งแถว
            // พยาบาลที่พิมพ์ "บันทึกภายใน/ส่งเวร" หรือ "เหตุผลนัด" ไว้ก่อนโดยยังไม่ใส่วันนัด
            // กดเซฟแล้วข้อความหายหมดโดยไม่มีอะไรเตือน (พี่กานต์แจ้ง 2569-09-11)
            // ตอนนี้ยังบันทึกไม่ได้เหมือนเดิม (appointment_date เป็น NOT NULL)
            // แต่ต้องบอกให้รู้ว่าอะไรยังไม่ได้บันทึก จะได้ไม่เสียข้อความไปฟรี ๆ
            if ($raw === '') {
                // ช่องเทา (ส่งเวร) ไม่ต้องมีวันนัด (คลินิกขอ 2569-10-27): เก็บเป็นส่งเวรของคนไข้ ขึ้นแถบส้มตอนมาครั้งถัดไป
                $noteTxt = trim((string) ($notes[$i] ?? ''));
                if ($noteTxt !== '' && ! (int) ($ids[$i] ?? 0)) {
                    $exists = \App\Models\PatientNote::where('patient_id', $visit->patient_id)
                        ->where('is_resolved', false)->where('body', $noteTxt)->exists();   // กดบันทึกซ้ำไม่ซ้ำเรื่อง
                    if (! $exists) {
                        $n = \App\Models\PatientNote::create(['patient_id' => $visit->patient_id, 'user_id' => auth()->id(), 'body' => $noteTxt]);
                        \App\Models\PatientNoteRead::create(['patient_note_id' => $n->id, 'user_id' => auth()->id(), 'read_at' => now()]);
                    }
                    $handover[] = mb_substr($noteTxt, 0, 40) . (mb_strlen($noteTxt) > 40 ? '…' : '');
                }
                $typed = trim((string) ($reasons[$i] ?? '')) ?: trim((string) ($pres[$i] ?? ''));
                if ($typed !== '') {
                    $lostText[] = mb_substr($typed, 0, 40) . (mb_strlen($typed) > 40 ? '…' : '');
                }
                continue;
            }

            $be = Utillity::th2dbDate($raw);
            if (! $be) { continue; }                     // กรอกวันที่ไม่ได้ความ ข้ามดีกว่าบันทึกขยะ

            $reason = trim((string) ($reasons[$i] ?? '')) ?: null;
            $note   = trim((string) ($notes[$i] ?? '')) ?: null;
            $rowId  = (int) ($ids[$i] ?? 0);
            $tb     = $hasTb ? $tbVal($i) : [];

            $row = $rowId ? VisitAppointment::where('visit_id', $visit->id)->find($rowId) : null;

            // แถวใหม่ที่วันตรงกับนัดที่ระบบเพิ่งลงให้ระหว่างเปิดหน้า (กล่องสั่งยาวัณโรคลงนัดให้ 2569-10-29)
            // = นัดเดียวกัน แก้แถวนั้นแทนสร้างซ้ำ · ไม่แตะแถวที่ฟอร์มนี้ส่ง id มาเอง
            if (! $row && ! $rowId) {
                $row = VisitAppointment::open()->where('visit_id', $visit->id)->where('appointment_date', $be)
                    ->whereNotIn('id', array_merge($keep, array_map('intval', array_filter($ids))) ?: [0])
                    ->orderBy('id')->first();
            }

            if ($row && $orig !== null && isset($orig[$rowId])) {
                $o = $orig[$rowId];
                $same = trim((string) ($o['date'] ?? '')) === $raw
                     && trim((string) ($o['reason'] ?? '')) === (string) ($reason ?? '')
                     && trim((string) ($o['note'] ?? '')) === (string) ($note ?? '')
                     && (! $hasTb || (
                            trim((string) ($o['time'] ?? '')) === (string) ($tb['appt_time'] ?? '')
                         && trim((string) ($o['pre'] ?? ''))  === (string) ($tb['pre_visit'] ?? '')
                         && trim((string) ($o['doctor'] ?? '')) === (string) ($tb['doctor_id'] ?? '')
                     ));
                if ($same) {
                    // แถวนี้ไม่ได้แก้ในหน้านี้ -> ไม่เขียนทับ อีกห้องอาจเพิ่งแก้เหตุผล/โน้ตของนัดนี้ไป
                    $keep[] = $row->id;
                    continue;
                }
            }

            if ($row) {
                // เปลี่ยนวันนัด -> ล้างสถานะส่งแล้ว ให้เตือนตามวันใหม่ได้อีกครั้ง
                if ($row->appointment_date !== $be) {
                    $row->reminder_sent_at = null;
                    $row->confirm_sent_at  = null;
                }
                $row->fill(['appointment_date' => $be, 'reason' => $reason, 'staff_note' => $note] + $tb);
                // ไม่ใส่ made_by ตอนแก้นัดเก่าที่ยังไม่มีผู้นัด — คนแก้ไม่ใช่คนนัด ใบนัดจะพิมพ์ชื่อผิดคน (รีวิว 2569-09-30)
                $row->save();
            } else {
                $row = VisitAppointment::create([
                    'visit_id'         => $visit->id,
                    'patient_id'       => $visit->patient_id,
                    'appointment_date' => $be,
                    'reason'           => $reason,
                    'staff_note'       => $note,
                    // ผู้นัด = คนที่ลงนัดในระบบ ใช้พิมพ์บนใบนัดวัณโรค (เก็บทุกคลินิก ไว้ตรวจย้อนหลังได้)
                    'made_by'          => auth()->id(),
                ] + $tb);
            }

            $keep[] = $row->id;

            if ($name = \App\Models\Holiday::nameForBE($be)) {
                $clash[] = $raw . ' (' . $name . ')';
            }
        }

        // แถวที่หายไปจากฟอร์ม = พยาบาลกดลบ
        // แต่ต้องไม่แตะนัดที่ "ปิดไปแล้ว" เพราะฟอร์มไม่ได้แสดงมันตั้งแต่แรก
        // ถ้าไม่กันไว้ นัดที่พยาบาลเพิ่งกดปิดจะถูกลบทิ้งทันทีที่กด Save ครั้งถัดไป
        // และต้องไม่ลบนัดที่ "เพิ่มเข้ามาหลังเปิดหน้านี้" (อีกห้องเพิ่งลง) — ลบได้เฉพาะนัดที่หน้านี้เห็นแล้วกดลบออกเอง
        $del = VisitAppointment::where('visit_id', $visit->id)
            ->whereNull('closed_at')
            ->whereNotIn('id', $keep ?: [0]);
        if ($orig !== null) {
            $del->whereIn('id', array_keys($orig) ?: [0]);
        }
        $del->delete();

        VisitAppointment::syncMirror($visit->id);

        // บอกให้รู้ทันทีว่าข้อความไหนยังไม่ได้บันทึกเพราะไม่มีวันนัด
        // ใช้ flash เพราะ layout เรียก flash::message ไว้ทุกหน้า และไม่ไปปนกับ flash_message ของการบันทึก visit
        if ($lostText) {
            flash('⚠ ยังไม่ได้บันทึกเหตุผลนัด: "' . implode('", "', $lostText) . '" '
                . 'เพราะแถวนั้นยังไม่ได้ใส่วันนัด — เหตุผลนัด (ช่องเหลือง) ต้องมีวันนัด · '
                . 'ถ้าเป็นเรื่องฝากเวร ให้พิมพ์ในช่องเทา ไม่ต้องมีวันนัด', 'warning');
        }
        if ($handover) {
            flash('ส่งเวรครั้งถัดไปแล้ว (ไม่มีวันนัด): "' . implode('", "', $handover) . '" — ขึ้นแถบส้มบนหน้าตรวจตอนคนไข้มาครั้งถัดไป จนกว่าจะกดปิดเรื่อง', 'success');
        }

        return $clash;
    }

   /**
     * ปิดนัด / เปิดนัดที่ปิดไว้ กลับมาใหม่
     *
     * เคสหลักคือ "คนไข้มาก่อนวันนัดเพราะวันนัดไม่ว่าง" แล้วทำเรื่องที่นัดไว้เสร็จในวันนี้
     * ระบบเดาแทนไม่ได้ว่ามาแทนนัดเดิมหรือมาด้วยเรื่องอื่น จึงให้พยาบาลกดเอง
     * (ดูตัวเลขที่มาของฟีเจอร์นี้ใน migration 2026_08_26_140000)
     */
    /**
     * ตัดนัดที่พยาบาล "ปิด" ไปแล้วออกจากรายชื่อนัด
     *
     * visits.appointment เป็นแค่เงาของนัดล่าสุด และต้องคงไว้เสมอเพื่อให้ประวัติย้อนหลังอ่านได้
     * (เคยลองล้างเป็นว่างตอนปิดนัด แล้วประวัติของ visit ก่อนหน้าหายไปทั้งแถว)
     * การกรองจึงต้องทำที่ตัวรายชื่อนัดแบบนี้แทน
     */
    private function excludeClosedAppointments($query)
    {
        return $query->whereNotExists(function ($q) {
            $q->select(DB::raw(1))
              ->from('visit_appointments as va')
              ->whereColumn('va.visit_id', 'visits.id')
              ->whereColumn('va.appointment_date', 'visits.appointment')
              ->whereNotNull('va.closed_at');
        });
    }

    /**
     * บันทึก "ส่งเวร/บันทึกภายใน" ใส่นัดที่มีอยู่แล้ว โดยไม่สร้างนัดใหม่
     *
     * พยาบาลอยากโน้ตกำกับนัดเดิมที่ค้างมาจากการมาครั้งก่อน (เช่น "นัดเดิม ไม่ต้องนัดซ้ำ")
     * แต่ถ้าไปพิมพ์ในตารางวันนัดแล้วใส่วันนัด จะกลายเป็นนัดใหม่ซ้อนขึ้นมาอีกอัน
     * ส่วนถ้าไม่ใส่วันนัด ข้อความก็หายไปเฉย ๆ — เลยต้องมีที่ให้โน้ตนัดเดิมตรง ๆ (พี่กุ้งแจ้ง 2569-09-11)
     *
     * ยิงทันทีแบบเดียวกับปุ่มปิดนัด ไม่รอกด Save เพราะพยาบาลอาจปิดหน้าไปเลย
     */
    public function noteAppointment(Request $request, $id)
    {
        $appt = VisitAppointment::findOrFail((int) $id);

        $note = trim((string) $request->input('staff_note', ''));
        $appt->staff_note = $note !== '' ? mb_substr($note, 0, 255) : null;
        $appt->save();

        $msg = $note !== ''
            ? 'บันทึกส่งเวรของนัดวันที่ ' . Utillity::db2thDate($appt->appointment_date) . ' แล้ว'
            : 'ล้างบันทึกส่งเวรของนัดนี้แล้ว';

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'msg' => $msg, 'staff_note' => $appt->staff_note]);
        }

        return back()->with('success', $msg);
    }

   public function closeAppointment(Request $request, $id)
    {
        $appt = VisitAppointment::findOrFail((int) $id);

        if ($request->input('action') === 'reopen') {
            $appt->forceFill([
                'closed_at' => null, 'closed_reason' => null,
                'closed_by' => null, 'closed_visit_id' => null,
            ])->save();
            VisitAppointment::syncMirror($appt->visit_id);
            $msg = 'เปิดนัดกลับมาแล้ว';
        } else {
            $reason = in_array($request->input('reason'), ['done_early', 'cancelled', 'no_show'], true)
                ? $request->input('reason') : 'done_early';
            $appt->close($reason, $request->input('visit_id') ? (int) $request->input('visit_id') : null);
            $msg = 'ปิดนัดวันที่ ' . Utillity::db2thDate($appt->appointment_date) . ' แล้ว';
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'msg' => $msg]);
        }
        return back()->with('success', $msg);
    }

   public function update(Request $request, $id)
    {
        $visit = Visit::with('patient2')->findOrFail($id);
        // $status = (int)($request->input('status', $visit->status)); // (ไม่ได้ใช้ตัวแปรนี้ แต่เก็บไว้ได้)
        // $setsLab = (bool)$request->boolean('send_lab');             // (ไม่ได้ใช้ตัวแปรนี้ แต่เก็บไว้ได้)

        // 1) เตรียมข้อมูลเข้มขึ้น + ปลอดภัย
        $requestData = $request->all();
        $user = auth()->user(); // ✅ ประกาศตัวแปร $user ไว้ใช้ข้างล่าง

        // เซฟเฉพาะช่องที่คนนี้แก้จริง (2569-09-14)
        // เดิมกด Save แล้วเขียนทั้งฟอร์มลงไป หลายห้องเปิดหน้าตรวจคนไข้คนเดียวกันค้างไว้
        // คนที่กด Save ทีหลังจึงเอาค่าเก่าในหน้าของตัวเองไปทับสิ่งที่อีกห้องเพิ่งบันทึก
        // (ข้อความที่หมอพิมพ์หาย / คนไข้เด้งกลับห้องตรวจทั้งที่ส่งไปห้องยาแล้ว / นัดที่อีกห้องลงไว้ถูกลบ)
        // หน้าเว็บจำค่าตอนเปิดหน้าไว้ แล้วส่งรายชื่อช่องที่เปลี่ยนมาใน _touched
        // ถ้าไม่มี _touched (JS ไม่ทำงาน) ให้เซฟทั้งฟอร์มแบบเดิม ดีกว่าเซฟไม่ได้เลย
        $touched = $this->touchedKeys($request);
        $isTouched = fn (string $key) => $touched === null || isset($touched[$key]);

        // มีห้องอื่นย้ายคนไข้ไประหว่างที่เปิดหน้านี้ค้างไว้หรือยัง
        // เคสจริง (2569-09-14): ห้อง 4 ส่งไปห้อง 6 แล้วเปิดหน้าค้าง · ห้อง 6 แก้ผลตรวจแล้วส่งกลับห้อง 4
        // · ห้อง 4 ไม่รู้ นึกว่ายังไม่ได้ส่ง เลยกดส่งไปห้อง 6 ซ้ำเกือบพร้อมกัน
        // เทียบทั้งห้องและเวลาเข้าห้อง เพราะถ้าย้ายไปแล้วย้ายกลับ (4 -> 6 -> 4) เลขห้องจะเท่าเดิม
        $roomMoved = false;
        if ($request->has('_orig_status')) {
            $origRoomAt = trim((string) $request->input('_orig_room_at', ''));
            $nowRoomAt  = optional($visit->room_entered_at)->format('Y-m-d H:i:s') ?? '';
            $roomMoved  = (int) $request->input('_orig_status') !== (int) $visit->status
                       || $origRoomAt !== $nowRoomAt;
        }

        if ($roomMoved) {
            // ไม่ย้ายห้องตามหน้าเก่า — ข้อมูลอื่นที่แก้ยังบันทึกตามปกติ
            $wanted = (int) $request->input('status');
            unset($requestData['status']);
            $isTouchedBase = $isTouched;
            $isTouched = fn (string $key) => $key === 'status' ? false : $isTouchedBase($key);
            if ($touched !== null) { unset($touched['status']); }

            $rooms = Visit::$statusOption;
            flash('⚠ ยังไม่ได้ย้ายห้อง — ระหว่างที่เปิดหน้านี้ค้างไว้ มีห้องอื่นย้ายคนไข้ไปแล้ว · ตอนนี้คนไข้อยู่ที่ "'
                . ($rooms[(int) $visit->status] ?? $visit->status) . '"'
                . ($visit->room_entered_at ? ' (ย้ายเมื่อ ' . $visit->room_entered_at->format('H:i') . ' น.)' : '')
                . ' · ข้อมูลอื่นที่แก้บันทึกให้แล้ว และหน้านี้แสดงข้อมูลล่าสุดแล้ว'
                . ($wanted && $wanted !== (int) $visit->status
                    ? ' ถ้ายังต้องการส่งไป "' . ($rooms[$wanted] ?? $wanted) . '" ให้เลือกห้องแล้วกดบันทึกอีกครั้ง'
                    : ''), 'warning');
        }

        // ถ้าฟอร์มนี้เปลี่ยนห้อง (status) ด้วย ให้ปั๊มเวลาที่เข้าห้องใหม่ ไว้เรียงคิวหน้า visit.room
        if ($isTouched('status') && $request->has('status') && (int)$request->input('status') !== (int)$visit->status) {
            $requestData['room_entered_at'] = now();
        }

        // =========================================================
        // ❌ 1.1 พยาบาล (Nurse) -> COMMENT ปิดส่วนนี้ไปเลยครับ! ❌
        /*
        if ($user->hasRole('Nurse') && empty($visit->screening_at)) {
            $requestData['screening_at'] = now();
            $requestData['nurse_by']     = $user->id;
        }
        */
        // =========================================================

        // 1.2 แพทย์ (Doctor) -> ลงเวลาพบแพทย์ (doctor_at)
        //     เฉพาะเมื่อ "รับคนไข้แล้ว" (มี screening_at) เท่านั้น — กันเคสเปิด visit มาดู/บันทึกก่อนรับคนไข้
        //     แล้ว doctor_at ถูกปั๊มก่อนเวลาจริง ทำให้ Patient Journey เรียงผิด (พบแพทย์ก่อนซักประวัติ)
        if ($user->hasRole('Doctor') && empty($visit->doctor_at) && !empty($visit->screening_at)) {
            $requestData['doctor_at'] = now();
            $requestData['doctor_by'] = $user->id;
        }

        // 1.3 เภสัชกร (Pharmacy) -> ลงเวลาจ่ายยา (pharmacy_at)
        if (($user->hasRole('Nurse') || $user->hasRole('Doctor')) && !empty($visit->screening_at) && empty($visit->pharmacy_at)) {
            $requestData['pharmacy_at'] = now();
            $requestData['pharmacy_by'] = $user->id;
        }

        // 1.4 จุดจบงาน (Finished) -> ลงเวลาเสร็จสิ้น (finished_at)
        if ($isTouched('status') && ($request->input('status') == 6 || $request->input('status') == 'finished') && empty($visit->finished_at)) {
            $requestData['finished_at'] = now();
            $requestData['handled_by']  = $user->id;
        }

        // body_check_marks: รับทั้ง JSON string/array และกรองค่าให้ถูกต้อง
        if (array_key_exists('body_check_marks', $requestData)) {
            $marks = $requestData['body_check_marks'];
            if (is_string($marks)) {
                $decoded = json_decode($marks, true);
                $marks   = is_array($decoded) ? $decoded : [];
            }
            if (is_array($marks)) {
                $validMarks = collect($marks)->filter(function ($mark) {
                    if (!is_array($mark) || !isset($mark['type'])) return false;
                    if ($mark['type'] === 'point') {
                        return isset($mark['x'], $mark['y']) && is_numeric($mark['x']) && is_numeric($mark['y']);
                    }
                    if ($mark['type'] === 'path') {
                        return isset($mark['points']) && is_array($mark['points']) && count($mark['points']) > 1;
                    }
                    return false;
                })->values()->all();
                $requestData['body_check_marks'] = $validMarks;
            } else {
                $requestData['body_check_marks'] = [];
            }
        }

        // แผนนัดฉีดวัคซีน TMC: ฟอร์มส่งมาเป็น 5 นัด × 3 ช่องวัคซีนคงที่ (index ต้องคงที่ให้ช่องจับคู่ถูก)
        // เก็บเฉพาะที่กรอกจริง: ตัดช่องวัคซีนที่ยังไม่เลือก และตัดนัดที่ว่างทั้งวันที่+วัคซีน
        if (array_key_exists('tmc_vaccine_plan', $requestData)) {
            $badPlanDates = [];
            $requestData['tmc_vaccine_plan'] = $this->cleanVaccinePlan($visit, $requestData['tmc_vaccine_plan'],
                $requestData['tmc_vaccine_plan_orig'] ?? null, $badPlanDates);   // cast 'array' บนโมเดลจะ encode ให้เอง
            if ($badPlanDates) {
                flash('⚠ แผนนัดวัคซีน: วันที่ไม่ถูกต้อง — ' . implode(' · ', $badPlanDates) . ' (ใช้รูปแบบ วว/ดด/ปปปป พ.ศ.) กรุณาแก้แล้วบันทึกใหม่', 'warning');
            }
        }
        unset($requestData['tmc_vaccine_plan_orig']);

        // ประเทศปลายทาง TMC (2569-11-04) — ยังไม่ migrate ตัดทิ้ง ไม่งั้นเซฟทั้งหน้าล้ม
        if (array_key_exists('tmc_dest_countries', $requestData)) {
            if (Visit::destCountriesReady()) {
                $requestData['tmc_dest_countries'] = Visit::cleanDestCountries($requestData['tmc_dest_countries']);
            } else {
                unset($requestData['tmc_dest_countries']);
            }
        }

        // การตรวจร่างกาย TMC (สวิตช์ระบบ) -> เก็บแบบโครงสร้าง + ประกอบข้อความลง body_check ให้ใบพิมพ์/ประวัติอ่าน (2569-09-30)
        // body_check ไม่มีในฟอร์ม TMC จึงต้องนับว่า "ถูกแก้" ตามไปด้วย ไม่งั้นโดนตัดทิ้งตอนกรอง _touched
        if (array_key_exists('tmc_pe', $requestData)) {
            $requestData['tmc_pe']     = Visit::cleanTmcPe($requestData['tmc_pe']);
            $requestData['body_check'] = Visit::tmcPeText($requestData['tmc_pe']);
            if ($touched !== null && isset($touched['tmc_pe'])) { $touched['body_check'] = true; }
        }

        // ส่วนสุภาพสตรี TMC (2569-10-07): ตรวจวันที่ + ตัดคีย์ทิ้งถ้าคอลัมน์ยังไม่ขึ้น (รอ migration) ไม่งั้นเซฟทั้งหน้าล้ม
        [$requestData, $__femaleWarn] = Visit::cleanTmcFemale($requestData, $visit->LMP);
        if ($__femaleWarn) {
            flash('⚠ ส่วนสุภาพสตรี: ' . implode(' · ', $__femaleWarn) . ' (ใช้รูปแบบ วว/ดด/ปปปป พ.ศ.) กรุณาแก้แล้วบันทึกใหม่', 'warning');
        }

        // 2) แปลงรูปแบบฟิลด์ตามโปรเจกต์
        $requestData = Visit::format2DB($requestData);

        // ตัดช่องที่ไม่ได้แก้ทิ้ง — ต้องทำ "หลัง" format2DB เพราะมันเติมค่าให้เองถ้าไม่มีคีย์
        // (ไม่มี date -> ตั้งเป็นวันนี้ / ไม่มี reason_sti -> ล้างเป็น null) ถ้าปล่อยผ่านจะไปทับของจริง
        // ช่องเวลา/ผู้บันทึกที่ระบบคำนวณเองด้านบนต้องเก็บไว้เสมอ
        if ($touched !== null) {
            $serverStamps = ['room_entered_at', 'doctor_at', 'doctor_by', 'pharmacy_at', 'pharmacy_by', 'finished_at', 'handled_by'];
            foreach (array_keys($requestData) as $key) {
                if (!isset($touched[$key]) && !in_array($key, $serverStamps, true)) {
                    unset($requestData[$key]);
                }
            }
        }

        // เปลี่ยนวันนัดใหม่ -> ล้างสถานะแจ้งเตือนเดิมทั้งคู่ ให้เตือน/ยืนยันตามวันนัดล่าสุดได้อีกครั้ง
        if (array_key_exists('appointment', $requestData) && $requestData['appointment'] !== $visit->appointment) {
            $requestData['reminder_sent_at'] = null;
            $requestData['appointment_confirm_sent_at'] = null;

            // ปฏิทินล็อกวันหยุดไว้แล้ว แต่ช่องนี้พิมพ์เองได้ และของเดิมในฐานข้อมูลก็ยังมีวันหยุดค้างอยู่
            // เตือนอย่างเดียว ไม่บล็อก — บางเคสอาจนัดนอกเวลากันจริง คนตัดสินใจคือพยาบาลไม่ใช่ระบบ
            $__holidayName = \App\Models\Holiday::nameForBE($requestData['appointment'] ?? null);
            if ($__holidayName) {
                session()->flash('appointment_holiday_warning', $__holidayName);
            }
        }

        // 3) อัปเดตแบบ transaction
        DB::transaction(function () use ($visit, $requestData) {
            $visit->update($requestData);

            // 3.1) อัปเดต patient2
            $patient = $visit->patient2;
            if ($patient) {
                $newCongenital  = $requestData['congenitaldisease']   ?? $patient->congenitaldisease;
                $newAllergy     = $requestData['drug_allergy']        ?? $patient->drug_allergy;
                $newPStatus     = $requestData['patient_status']      ?? $patient->status;
                $newTel         = $requestData['tel']                 ?? $patient->tel;
                // อีเมล (2569-09-30) — ช่องว่าง = ล้างอีเมล ไม่ได้แตะช่อง = คีย์ถูกตัดไปแล้ว ใช้ของเดิม
                $newEmail       = array_key_exists('email', $requestData)
                                    ? (mb_substr(trim((string) $requestData['email']), 0, 191) ?: null)
                                    : $patient->email;
                $newOcc         = $requestData['occupation']          ?? $patient->occupation;
                $newOccOther    = $requestData['other_occupation']    ?? $patient->other_occupation;
                $newOrg         = $requestData['organization']        ?? $patient->organization;
                // ประวัติวัคซีน STIs (2569-10-21) — ลบข้อความทิ้งได้ (ช่องว่าง = ล้าง) จึงเช็คว่ามีคีย์ ไม่ใช้ ??
                $newStiVac      = array_key_exists('sti_vaccine_history', $requestData)
                                    ? (mb_substr(trim((string) $requestData['sti_vaccine_history']), 0, 1000) ?: null)
                                    : $patient->sti_vaccine_history;
                // สิทธิการรักษา (2569-10-21) — แก้จากหน้าตรวจได้ · รับเฉพาะค่าที่อยู่ในรายการ · ช่อง รพ. ว่าง = ล้าง
                $newRight       = array_key_exists('health_right', $requestData)
                                  && array_key_exists((string) $requestData['health_right'], \App\Models\Patient::$healthRightOption)
                                    ? ((string) $requestData['health_right'] === '' ? null : (string) $requestData['health_right'])
                                    : $patient->health_right;
                $newRightHosp   = array_key_exists('health_right_hospital', $requestData)
                                    ? (mb_substr(trim((string) $requestData['health_right_hospital']), 0, 255) ?: null)
                                    : $patient->health_right_hospital;

                if (
                    $patient->congenitaldisease !== $newCongenital ||
                    $patient->drug_allergy      !== $newAllergy    ||
                    $patient->status            !== $newPStatus    ||
                    $patient->tel               !== $newTel        ||
                    $patient->email             !== $newEmail      ||
                    $patient->occupation        !== $newOcc        ||
                    $patient->other_occupation  !== $newOccOther   ||
                    $patient->organization      !== $newOrg        ||
                    $patient->sti_vaccine_history !== $newStiVac ||
                    (string) $patient->health_right !== (string) $newRight ||
                    $patient->health_right_hospital !== $newRightHosp
                ) {
                    $patient->congenitaldisease = $newCongenital;
                    $patient->drug_allergy      = $newAllergy;
                    $patient->status            = $newPStatus;
                    $patient->tel               = $newTel;
                    $patient->email             = $newEmail;
                    $patient->occupation        = $newOcc;
                    $patient->other_occupation  = $newOccOther;
                    $patient->organization      = $newOrg;
                    $patient->sti_vaccine_history = $newStiVac;
                    $patient->health_right          = $newRight;
                    $patient->health_right_hospital = $newRightHosp;
                    $patient->save();
                }
            }
        });

        // 3.7) นัดหลายวัน — ต้องทำก่อน sendOnDischarge() ข้างล่าง
        //      เพราะข้อความยืนยันนัดทาง LINE อ่าน visits.appointment ที่ syncMirror() เพิ่งตั้งให้
        // ส่งรายการนัดที่เห็นตอนเปิดหน้าไปด้วย — ไม่งั้นนัดที่อีกห้องเพิ่งลงจะถูกมองว่า "ถูกลบ" แล้วหายไป
        // ช่องของใบนัด TB (เวลา · ก่อนพบแพทย์ · แพทย์) ต้องอยู่ในเงื่อนไขนี้ด้วย ไม่งั้นแก้แค่ช่องพวกนี้แล้วไม่ถูกบันทึก (รีวิว 2569-09-30)
        $__apptClash = $isTouched('appt_date') || $isTouched('appt_reason') || $isTouched('appt_note') || $isTouched('appt_id')
                    || $isTouched('appt_time') || $isTouched('appt_pre') || $isTouched('appt_doctor')
            ? $this->saveAppointments($visit, $request, $touched === null ? null : $this->origAppointments($request))
            : [];
        if ($__apptClash) {
            session()->flash('appointment_holiday_warning', implode(' · ', $__apptClash));
        }
        // แผนวัคซีนถูกแก้ในรอบนี้ → ลงวันในแผนเป็นนัดในตารางวันนัด (ทำหลังตารางนัด ไม่ให้ตารางในฟอร์มลบทิ้ง) 2569-10-28
        if ($isTouched('tmc_vaccine_plan')) {
            \App\Models\VisitAppointment::syncVaccinePlan($visit->fresh());
        }

        // 3.8) ให้คิวบนจอ terminal ตรงกับสถานะจริง (ปิดเมื่อกลับบ้าน / เปิดกลับเมื่อยังอยู่ในห้อง)
        $visit->fresh()->syncCentralQueue();

        // 3.9) กดกลับบ้านแล้ว -> เพิ่งจบเคส ข้อมูลนิ่ง ส่งยืนยันนัดทาง LINE ให้คนไข้ (ถ้าผูก LINE + มีนัด)
        //      ยิงนอก transaction: ถ้าอยู่ข้างในแล้ว rollback คนไข้จะได้ข้อความนัดที่ไม่มีจริง
        //      ตัวเช็คเงื่อนไข/กันส่งซ้ำอยู่ใน sendOnDischarge ที่เดียว (ห้องยาก็เรียกตัวเดียวกัน)
        app(\App\Services\AppointmentLineNotifier::class)->sendOnDischargeLater($visit->fresh());

        // 4) ลงชื่อผู้บันทึกตามบทบาท
        if (auth()->user()->hasRole('Nurse')) {
            $this->stampByRules($visit, ['source' => 'nurse', 'overwrite' => true]);
        }
        if (auth()->user()->hasRole('Doctor')) {
            $this->stampByRules($visit, ['source' => 'doctor', 'overwrite' => true]);
        }

        // 4.5) ใบซักประวัติ/คัดกรองวัณโรค (คลินิก TB) — เก็บคนละตารางกับ visit
        //      ใช้กติกา _touched เหมือนช่องอื่น: ไม่ได้แตะในหน้านี้ = ไม่เขียนทับของห้องอื่น
        if ($request->has('tb') && $isTouched('tb')) {
            $this->saveTbScreening($visit, (array) $request->input('tb', []));
        }

        // 4.52) ส่งตรวจทางห้องปฏิบัติการของวัณโรค — ชื่อช่อง tb_lab[...] แยกจาก tb[...] ให้ _touched แยกกันได้ (2569-10-02)
        //       บัตร TB 01 ก็บันทึกส่วนนี้ผ่าน TbScreening::saveLabSection ตัวเดียวกัน หน้าตรวจที่เปิดค้างแต่ไม่ได้แตะส่วนนี้จึงไม่ทับ
        if ($request->has('tb_lab') && $isTouched('tb_lab')) {
            \App\Models\TbScreening::saveLabSection($visit, (array) $request->input('tb_lab', []));
        }

        // 4.55) ผลเอกซเรย์ปอดหลายแถวต่อวัน (หลายท่าถ่าย) — ชื่อช่องแยกจาก tb[...] เพื่อให้ _touched แยกกันได้
        //       ห้องที่เปิดหน้าค้างแต่ไม่ได้แตะผลเอกซเรย์ จะไม่ไปทับแถวที่อีกห้องเพิ่งลง
        if ($request->has('tb_cxr') && $isTouched('tb_cxr')) {
            self::saveTbCxr($visit, (array) $request->input('tb_cxr', []));
        }

        // 4.6) รหัสโรค ICD-10 ของใบคัดกรอง — เก็บคนละตาราง (icd10_entries) เพราะเลือกได้หลายรหัส
        //      ผ่านกติกา _touched เหมือนช่องอื่น ไม่งั้นห้องที่เปิดฟอร์มค้างไว้จะลบรหัสของห้องนี้ทิ้ง
        if ($request->has('tb_icd10') && $isTouched('tb_icd10')) {
            $sc = \App\Models\TbScreening::where('visit_id', $visit->id)->first();
            // visit ใหม่ที่ยังไม่มีใบคัดกรอง และหมอเลือกแค่รหัส ICD ไม่ได้แตะช่อง tb[...] อื่น
            // เดิมรหัสหายเงียบเพราะไม่มีแถวให้ผูก — สร้างใบเปล่าให้ก่อน (รีวิว 2569-09-30)
            if (! $sc && array_filter((array) $request->input('tb_icd10', []))) {
                $sc = new \App\Models\TbScreening(['visit_id' => $visit->id]);
                $sc->patient_id = $visit->patient_id;
                $sc->created_by = auth()->id();
                $sc->save();
            }
            if ($sc) {
                \App\Models\Icd10Code::syncFor(\App\Models\TbScreening::icd10Owner(), $sc->id, (array) $request->input('tb_icd10', []));
            }
        }

        // 4.7) การวินิจฉัยแบบมีประเภท (Principal/Comorbidity/Complication/Other) ของใบคัดกรองวัณโรค — แทนช่อง tb_icd10 เดิม (2569-10-01)
        if ($request->has('tb_dx') && $isTouched('tb_dx')) {
            $rows = array_values(array_filter((array) $request->input('tb_dx', []), 'is_array'));
            $sc = \App\Models\TbScreening::where('visit_id', $visit->id)->first();
            if (! $sc && collect($rows)->contains(fn ($r) => trim((string) ($r['value'] ?? '')) !== '')) {
                $sc = new \App\Models\TbScreening(['visit_id' => $visit->id]);
                $sc->patient_id = $visit->patient_id;
                $sc->created_by = auth()->id();
                $sc->save();
            }
            if ($sc) {
                \App\Models\Icd10Code::syncTyped(\App\Models\TbScreening::icd10Owner(), $sc->id, $rows);
            }
        }

        // 5) ค่าบริการที่แพทย์/พยาบาลติ๊ก -> ซิงก์เข้าบิล (billing_items type=service) เพื่อไหลไปหน้าห้องยา/การเงิน
        //    ราคาใช้จาก master (แพทย์แค่ติ๊ก+จำนวน ไม่ต้องกรอกราคา) แตะเฉพาะ service ไม่ยุ่งกับยา/lab ที่ห้องยาทำ
        // ไม่ได้แตะค่าบริการในหน้านี้ = ไม่ไปลบ-สร้างใหม่ (กันทับราคาที่ห้องยา/อีกห้องเพิ่งแก้)
        if ($request->has('services') && $isTouched('services')) {
            $billing = \App\Models\Billing::firstOrCreate(['visit_id' => $visit->id]);
            if (!$billing->bill_no) { $billing->bill_no = \App\Models\Billing::generateBillNo(); }
            if (!$billing->user_id) { $billing->user_id = auth()->id(); }

            \App\Models\BillingItem::where('billing_id', $billing->id)->where('item_type', 'service')->delete();

            $serviceTotal = 0;
            $anyWaived = false;
            foreach ((array) $request->input('services', []) as $serviceId => $data) {
                if (empty($data['use'])) { continue; }
                $svc = \App\Models\ServicePrice::find($serviceId);
                if (!$svc) { continue; }
                $qty = (float) ($data['qty'] ?? 1) ?: 1;
                // ราคาที่แพทย์/พยาบาลแก้ก่อนสั่ง (ถ้าไม่กรอกใช้ราคามาตรฐาน)
                $unitPrice = array_key_exists('price', $data) && $data['price'] !== '' ? (float) $data['price'] : (float) $svc->price;
                $line = $qty * $unitPrice;

                // ขอความอนุเคราะห์รายรายการ: ยกเว้นได้เฉพาะที่ "ไม่ใช่แพ็กเกจ"
                $waived = !empty($data['waive']) && !$svc->is_package;
                if ($waived) { $anyWaived = true; }

                $item = new \App\Models\BillingItem();
                $item->billing_id  = $billing->id;
                $item->item_type   = 'service';
                $item->item_id     = $serviceId;
                $item->item_name   = $svc->name;
                $item->qty         = $qty;
                $item->unit_price  = $unitPrice;
                $item->total_price = $waived ? 0 : $line;   // ยกเว้น -> 0 (แต่ยังเก็บ unit_price ไว้ดูอ้างอิง)
                $item->waived      = $waived;
                $item->save();

                if (!$waived) { $serviceTotal += $line; }
            }

            $billing->service_waived = $anyWaived; // มีรายการที่อนุเคราะห์อย่างน้อย 1 (ไว้โชว์ป้าย)
            $billing->total_service = $serviceTotal;
            $billing->net_total = ($billing->total_medicine + $billing->total_lab + $serviceTotal) - $billing->discount;
            if ($billing->net_total < 0) { $billing->net_total = 0; }
            $billing->save();
        }

        // 5.1) ราคา Lab ที่แพทย์/พยาบาลตั้งไว้ -> ซิงก์เข้าบิล (billing_items type=lab) การเงินจะเห็นราคาพร้อม
        //      กติกาเดียวกับหน้าห้องยา: เลือด auto, HBsAg/Anti-HCV เบิกสปสช(0), Gram/C&S คลินิกคิดเอง (แตะเฉพาะ lab ไม่ยุ่งยา/service)
        if ($request->has('labs') && $isTouched('labs')) {
            $billing = \App\Models\Billing::firstOrCreate(['visit_id' => $visit->id]);
            if (!$billing->bill_no) { $billing->bill_no = \App\Models\Billing::generateBillNo(); }
            if (!$billing->user_id) { $billing->user_id = auth()->id(); }

            // อนุเคราะห์ที่การเงินติ๊กไว้ — หน้าตรวจไม่มีช่องนี้ ต้องคงไว้ ไม่งั้นบันทึกหน้าตรวจแล้วกลับมาคิดเงิน
            $prevLabWaived = \App\Models\BillingItem::where('billing_id', $billing->id)->where('item_type', 'lab')
                ->pluck('waived', 'item_id')->map(fn ($w) => (bool) $w)->all();
            \App\Models\BillingItem::where('billing_id', $billing->id)->where('item_type', 'lab')->delete();

            $labSubMethods  = \App\Models\LabSub::whereIn('id', array_keys((array) $request->input('labs', [])))->pluck('method', 'id');
            $labMethodNames = \App\Models\LabSub::$methodOption;

            $labTotal = 0;
            foreach ((array) $request->input('labs', []) as $labSubId => $data) {
                $unitPrice = array_key_exists('price', $data) && $data['price'] !== '' ? (float) $data['price'] : 0;
                $methodId  = $labSubMethods[$labSubId] ?? null;
                $name      = $methodId !== null ? ($labMethodNames[$methodId] ?? 'Lab') : 'Lab';

                $item = new \App\Models\BillingItem();
                $item->billing_id  = $billing->id;
                $item->item_type   = 'lab';
                $item->item_id     = $labSubId;
                $item->item_name   = $name;
                $item->qty         = 1;
                $waived            = $prevLabWaived[$labSubId] ?? false;
                $item->unit_price  = $unitPrice;
                $item->total_price = $waived ? 0 : $unitPrice;
                $item->waived      = $waived;
                $item->save();

                $labTotal += $waived ? 0 : $unitPrice;
            }

            // รวมค่าแล็บนอกเข้ามาด้วย ไม่งั้นการบันทึกหน้าตรวจจะไปทับยอดจนค่าแล็บนอกหาย
            $billing->syncExternalLabItems();
            $billing->recalcTotals();
        }

        flash('Visit updated!', 'success');
        Alert::success('Visit updated!');

        return back();
    } // <--- ✅ ปิดปีกกาของ function update ตรงนี้ครับ (ห้ามปิดก่อนหน้านี้)

    // พิมพ์บัตรคิว "เฉพาะคน" จาก Laravel (แทนการพิมพ์จากโปรแกรมเรียกคิวภายนอกที่พิมพ์ผิด)
    // ดึงเลขคิว/ห้องจากคิวที่ผูกกับ visit นี้จริงๆ (visit.queue_system_id) -> ถูกคนเสมอ
    public function queueTicket($id)
    {
        $visit = Visit::with('patient2')->findOrFail($id);

        $queue = $visit->queue_system_id
            ? \App\Models\QueueSystemQueue::find($visit->queue_system_id)
            : null;

        $roomName = null;
        if ($queue) {
            $room = \App\Models\QueueSystemRoom::find($queue->room_number);
            // ใบคิวขึ้นเลขห้องเสมอ + ชื่อห้องถ้าตั้งไว้ต่างจาก "Room N" → "Room 7 - TMC" (2569-10-28)
            // แก้ที่ใบคิวแทนตาราง rooms เพราะจอทีวีคิวใช้ชื่อในตารางนั้นด้วย
            $roomName = 'Room ' . $queue->room_number;
            $__rn = trim((string) ($room->room_name ?? ''));
            if ($__rn !== '' && strcasecmp($__rn, $roomName) !== 0) {
                $roomName .= ' - ' . $__rn;
            }
        }

        // เลขคิว: ใช้จากคิวที่ผูกไว้ ถ้าไม่มีก็ fallback เป็นเลขที่บันทึกในตัว visit
        $queueNumber = $queue->queue_number ?? $visit->queue;

        // QR ประเมินความพึงพอใจ (ตั้ง URL ได้ที่ config: satisfaction_survey_url) — ฝัง SVG inline พิมพ์ได้แม้ออฟไลน์
        $surveyUrl = \App\Models\Config::getValue('satisfaction_survey_url');
        $surveyQr = null;
        if (!empty($surveyUrl)) {
            try {
                $surveyQr = \DNS2D::getBarcodeSVG($surveyUrl, 'QRCODE', 3, 3, 'black');
                // SVG ของ DNS2D มีแค่ width/height ไม่มี viewBox — ย่อด้วย CSS แล้วรูปถูก "ตัด" แทนการย่อ (สแกนไม่ได้)
                // เติม viewBox ให้ย่อทั้งรูปได้ (บัตรที่มีชื่ออังกฤษย่อ QR เหลือ 0.7in — 2569-10-07)
                $surveyQr = preg_replace_callback('/<svg width="(\d+)" height="(\d+)"/', function ($m) {
                    return '<svg width="' . $m[1] . '" height="' . $m[2] . '" viewBox="0 0 ' . $m[1] . ' ' . $m[2] . '" shape-rendering="crispEdges"';
                }, $surveyQr, 1);
            } catch (\Throwable $e) {
                $surveyQr = null;
            }
        }

        // ชื่อเครื่องพิมพ์คิว (thermal) สำหรับ QZ Tray — ตั้งได้ที่ config: printer_queue ; ว่าง = ใช้เครื่อง default
        $qzPrinter = \App\Models\Config::getValue('printer_queue');

        return view('visit.queue_ticket', compact('visit', 'queue', 'roomName', 'queueNumber', 'surveyUrl', 'surveyQr', 'qzPrinter'));
    }

    // =====================================================================
    // ✅ เพิ่มฟังก์ชันใหม่ ต่อท้าย (ต้องอยู่นอก update แต่อยู่ใน class)
    // =====================================================================
    public function receivePatient(Request $request, $id)
    {
        $visit = Visit::findOrFail($id);

        // บันทึกเวลาซักประวัติ (Screening Time)
        if (empty($visit->screening_at)) {
            $visit->screening_at = now();
            $visit->nurse_by = auth()->id();

            // (Option) ถ้าอยากเปลี่ยนสถานะด้วย ให้เปิดบรรทัดล่าง
            // $visit->status = 2; // ส่งต่อรอตรวจ

            $visit->save();
            return response()->json(['status' => 'success', 'time' => $visit->screening_at->format('H:i')]);
        }

        return response()->json(['status' => 'already_received']);
    }

   public function changeStatus(Request $request, $room)
{
    $requestData = $request->all();
    $visit = Visit::findOrFail($requestData['id']);

    $visit->status = $requestData['status'];
    $visit->room_entered_at = now(); // ใช้เรียงคิวหน้า visit.room ตามเวลาที่เข้าห้องนี้จริง
$visit->save();

$this->stampByRules($visit, [
    'status'    => (int)$visit->status,
    'overwrite' => true,
]);

    flash('Visit updated!', 'success');
    return redirect(route('visit.room', $room));
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
        $requestData = $request->all();
        $visit = Visit::find($id);
        Visit::destroy($id);

        // return redirect('visit')->with('flash_message', 'Visit deleted!');
        return Visit::next($requestData['page'], $visit->patient_id);
    }

    public function addContactPerson(Request $request, $id)
    {

        $requestData = $request->all();

        // $visit = Visit::findOrFail($id);
        // $visit->update($requestData);
        ContactPerson::create($requestData);
        return redirect(route('visit.edit', $id))->with('flash_message', 'Visit updated!');
        
    }


    public function updateStatus(Request $request, $id)
    {

        $requestData = $request->all();
        $visit = Visit::findOrFail($id);
        $requestData = Visit::format2DB($requestData);
        $visit->update($requestData);
        flash('Visit updated!', 'success');
        return redirect(route('visit.edit', $visit->id))->with('flash_message', 'Visit updated!');
    }

    public function appointment(Request $request)
{
    $start_date = $request->get('start'); // รับวันที่เริ่มต้น
    $end_date = $request->get('end'); // รับวันที่สิ้นสุด
    $hn = $request->get('hn'); // รับ HN
    $reason = $request->get('reason'); // รับ appointment_reason
    $sex = $request->get('sex'); // รับเพศ
    $status = $request->get('status'); // รับประเภทผู้รับบริการ
    $perPage = 25;

    // $query = Visit::query()->with('patient2'); // ใช้ with() สำหรับ Join ข้อมูลจากตาราง patients
    $query = Visit::query()->with(['patient2', 'diagnoses', 'followups']); // เพิ่ม 'followups' เข้ามา

    // ค้นหาด้วย HN
    if (!empty($hn)) {
        $query->whereHas('patient2', function ($q) use ($hn) {
        $q->where('code', 'LIKE', "%$hn%");
        });

    }

    // ค้นหาด้วย appointment_reason
    if (!empty($reason)) {
        // เหตุผลอยู่ทั้งที่ visit (นัดเดียวแบบเดิม) และที่แต่ละแถวนัด (นัดหลายวัน / แผนวัคซีน)
        $query->where(function ($q) use ($reason) {
            $q->where('appointment_reason', 'LIKE', "%$reason%")
              ->orWhereExists(fn ($e) => $e->select(DB::raw(1))->from('visit_appointments as vr')
                  ->whereColumn('vr.visit_id', 'visits.id')->whereNull('vr.closed_at')->where('vr.reason', 'LIKE', "%$reason%"));
        });
    }

    // ค้นหาด้วยเพศ / ประเภทผู้รับบริการ
    // เทียบกับ '' ตรง ๆ ไม่ใช้ !empty เพราะเลข 0 มีอยู่จริงในฐานข้อมูล
    // (เพศ=0 จำนวน 1 ราย, ประเภท=0 จำนวน 8 ราย) ถ้าใช้ !empty จะกรองไม่ได้
    if ($sex !== null && $sex !== '') {
        $query->whereHas('patient2', function ($q) use ($sex) {
            $q->where('sex', $sex);
        });
    }
    if ($status !== null && $status !== '') {
        $query->whereHas('patient2', function ($q) use ($status) {
            $q->where('status', $status);
        });
    }

    // ค้นหาด้วยวันที่
    $this->apptDateFilter($query, $start_date, $end_date);

    // แยกคลินิก (คลินิกขอ 2569-10-27) — ตั้งต้นเป็นคลินิกที่เลือกอยู่บน navbar · นับแต่ละคลินิกไว้ทำแท็บ
    $clinicCounts = (clone $query)->reorder()->select([])->selectRaw('clinic, COUNT(*) AS c')->groupBy('clinic')->pluck('c', 'clinic');
    $clinic = $this->apptClinicFilter($request);
    if ($clinic) {
        $query->where('clinic', $clinic);
    }

    $query->orderBy('appt_hit', 'asc'); // เรียงตามวันนัดที่ตรงช่วงค้นหา
    $totalVisits = $query->count();  // นับจำนวนทั้งหมด
    $visit = $query->paginate($perPage);
     // นับจำนวนผู้ป่วยที่ตรงกับเงื่อนไขการค้นหา
    
    // เพิ่มวันที่มาถัดไป
    $visit->getCollection()->transform(function ($item) {
    $this->apptShowHit($item);
    $nextVisit = Visit::where('patient_id', $item->patient_id)
                      ->where('date', '>', $item->date)
                      ->orderBy('date', 'desc')
                      ->first();

    // คำนวณวันที่มาถัดไป
    $item->next_visit = $nextVisit ? $nextVisit->date : null;

    // คำนวณจำนวนการติดตามที่สำเร็จ
    $followUpCount = $item->followups->count();
    $item->follow_up_count = $followUpCount;
    
    // หากติดตามครบตามเป้าหมาย (กำหนดเองในกรณีนี้ว่าคือ 3 ครั้ง) ให้แสดงสถานะว่า "สำเร็จ"
    $item->follow_up_status = $followUpCount >= 3 ? 'สำเร็จ' : 'ยังไม่ครบ';

    return $item;
});


    return view('visit.appointment', compact('visit', 'totalVisits', 'clinic', 'clinicCounts'));
}

    /** คลินิกที่จะกรองในหน้ารายชื่อนัด: ?clinic=all = ทุกคลินิก · ไม่ส่ง = คลินิกที่เลือกอยู่ */
    /**
     * กรองรายชื่อนัดตาม "แถวนัด" ใน visit_appointments โดยตรง (แก้ 2569-11-05)
     *
     * เดิมค้นจาก visits.appointment ซึ่งเป็นแค่เงาของนัดถัดไป ณ ตอนกดบันทึก visit
     * visit ที่มีหลายนัด (แผนวัคซีน TMC เข็ม 2/3, นัด TB หลายวัน) พอเลยนัดแรกไปแล้ว เงาไม่เลื่อนตาม
     * นัดถัดไปจึงค้นไม่เจอ · ตอนนี้ visit โผล่ถ้ามีนัดที่ยังเปิดอยู่ในช่วงวันที่ค้น
     * appt_hit / appt_hit_reason = นัดแรกที่ตรงช่วง ใช้แสดงและเรียง (visit หนึ่งขึ้นแถวเดียว)
     */
    private function apptDateFilter($query, $start, $end): void
    {
        $from = $start ? Utillity::th2dbDate($start) : \App\Models\VisitAppointment::todayBe();
        $to   = ($start && $end) ? Utillity::th2dbDate($end) : null;
        $match = function ($q) use ($from, $to) {
            $q->whereColumn('va.visit_id', 'visits.id')->whereNull('va.closed_at')
              ->where('va.appointment_date', '>=', $from);
            if ($to) { $q->where('va.appointment_date', '<=', $to); }
        };
        $query->whereExists(function ($q) use ($match) { $q->select(DB::raw(1))->from('visit_appointments as va'); $match($q); });
        $query->select('visits.*')
            ->selectSub(function ($q) use ($match) { $q->from('visit_appointments as va')->selectRaw('MIN(va.appointment_date)'); $match($q); }, 'appt_hit')
            ->selectSub(function ($q) use ($match) {
                $q->from('visit_appointments as va')->select('va.reason'); $match($q);
                $q->orderBy('va.appointment_date')->orderBy('va.id')->limit(1);
            }, 'appt_hit_reason');
    }

    /** แสดงวันนัด/เหตุผลของนัดที่ตรงช่วงค้น แทนเงาใน visits (แค่แสดง ไม่ได้บันทึก) */
    private function apptShowHit($v): void
    {
        if (! empty($v->appt_hit)) {
            $v->appointment = $v->appt_hit;
            if (trim((string) $v->appt_hit_reason) !== '') { $v->appointment_reason = $v->appt_hit_reason; }
        }
    }

    private function apptClinicFilter(Request $request): ?int
    {
        $c = $request->get('clinic');
        if ($c === 'all') {
            return null;
        }
        $c = (int) $c;
        return array_key_exists($c, Visit::$ClinicOption) ? $c : Visit::activeClinic();
    }



    // Controller
    public function patient2()
    {
    $visits = Visit::with('patient2')
        ->where('patient_id', $visit->patient_id)
        ->where('id', '<>', $visit->id)
        ->orderBy('id', 'asc')
        ->get();

    return view('lab-sub.table', compact('visits'));

    
}

public function export(Request $request)
{
    // 1. ค้นหาข้อมูล (Query เดิม)
    $start_date = $request->get('start');
    $end_date = $request->get('end');
    $hn = $request->get('hn');
    $reason = $request->get('reason');
    $sex = $request->get('sex');
    $status = $request->get('status');

    $query = Visit::query()->with(['patient2', 'diagnoses', 'followups', 'contactPersons']);

    if (!empty($hn)) {
        $query->whereHas('patient2', function ($q) use ($hn) {
            $q->where('code', 'LIKE', "%$hn%");
        });
    }
    if (!empty($reason)) {
        // เหตุผลอยู่ทั้งที่ visit (นัดเดียวแบบเดิม) และที่แต่ละแถวนัด (นัดหลายวัน / แผนวัคซีน)
        $query->where(function ($q) use ($reason) {
            $q->where('appointment_reason', 'LIKE', "%$reason%")
              ->orWhereExists(fn ($e) => $e->select(DB::raw(1))->from('visit_appointments as vr')
                  ->whereColumn('vr.visit_id', 'visits.id')->whereNull('vr.closed_at')->where('vr.reason', 'LIKE', "%$reason%"));
        });
    }
    // ต้องกรองเหมือนหน้าจอเป๊ะ ๆ ไม่งั้นไฟล์ Excel จะได้คนละชุดกับที่พยาบาลเห็น
    if ($sex !== null && $sex !== '') {
        $query->whereHas('patient2', function ($q) use ($sex) {
            $q->where('sex', $sex);
        });
    }
    if ($status !== null && $status !== '') {
        $query->whereHas('patient2', function ($q) use ($status) {
            $q->where('status', $status);
        });
    }
    $this->apptDateFilter($query, $start_date, $end_date);

    // กรองคลินิกเหมือนหน้าจอ (ไฟล์ Excel ต้องตรงกับที่เห็น)
    if ($clinic = $this->apptClinicFilter($request)) {
        $query->where('clinic', $clinic);
    }

    $query->orderBy('appt_hit', 'asc');
    $visits = $query->get();
    $visits->each(fn ($v) => $this->apptShowHit($v));

    // 2. สร้าง Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // ✅ กำหนดหัวตารางใหม่ (แยกคอลัมน์ผู้สัมผัสให้ชัดเจน)
    $headers = [
        'HN', 'ชื่อ', 'นามสกุล', 'เพศ', 'ประเภท', 'วันที่มา', 
        'วินิจฉัย/สถานะ', 'วันนัด', 'เหตุผลการนัด', 'นัดถัดไป/มาจริง', 
        'สถานะการมา', 'สถานะการติดตาม', 
        'ชื่อผู้สัมผัส', 'เพศ(สัมผัส)', 'ความสัมพันธ์', 'ติดตาม(สัมผัส)', // แยก 4 ช่องนี้
        'เบอร์โทรศัพท์'
    ];
    $sheet->fromArray($headers, NULL, 'A1');

    // Style หัวตาราง
    $sheet->getStyle('A1:Q1')->getFont()->setBold(true); // ถึง Q
    $sheet->getStyle('A1:Q1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1:Q1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');

    $row = 2; // เริ่มเขียนข้อมูลที่บรรทัด 2

    foreach ($visits as $item) {
        // --- เตรียมข้อมูลคนไข้ ---
        $nextVisit = \App\Models\Visit::where('patient_id', $item->patient_id)
                  ->where('date', '>', $item->date)
                  ->orderBy('date', 'asc')->first();
        
        $nextVisitDateVal = $nextVisit ? $nextVisit->date : null;
        $visitStatus = 'ยังไม่มา';
        if ($nextVisitDateVal) {
            $nDate = \Carbon\Carbon::parse($nextVisitDateVal);
            $aDate = \Carbon\Carbon::parse($item->appointment);
            $visitStatus = $nDate->lt($aDate) ? 'มาก่อนนัด' : 'มาแล้ว';
        }

        $diagStr = '';
        foreach ($item->diagnoses as $diag) {
            if(isset(\App\Models\Diagnosi::$diseaseOption[$diag->disease])) {
                $diagStr .= "- " . \App\Models\Diagnosi::$diseaseOption[$diag->disease] . "\n";
            }
        }

        $followUpCount = $item->followups->count();
        $successCount = $item->followups->where('status', 'สำเร็จ')->count();
        $followStatus = "ติดตาม $followUpCount ครั้ง\n(สำเร็จ $successCount)";

        // ข้อมูลหลักคนไข้ (Map กับคอลัมน์ A-L และ Q)
        $patientData = [
            'A' => $item->patient2->code ?? '',
            'B' => $item->patient2->name ?? '',
            'C' => $item->patient2->surname ?? '',
            'D' => \App\Models\Patient::$sexOption[$item->patient2->sex] ?? '-',
            'E' => \App\Models\Patient::$statusOption[$item->patient2->status] ?? '-',
            'F' => $item->date,
            'G' => trim($diagStr),
            'H' => $item->appointment,
            'I' => $item->appointment_reason,
            'J' => $nextVisitDateVal,
            'K' => $visitStatus,
            'L' => $followStatus,
            // M, N, O, P คือผู้สัมผัส (เว้นไว้ก่อน)
            'Q' => $item->patient2->tel ?? ''
        ];

        // --- Logic แตกแถวผู้สัมผัส ---
        $contacts = $item->contactPersons;
        $contactCount = $contacts->count();
        $startRow = $row;

        if ($contactCount > 0) {
            // กรณีมีผู้สัมผัส: วนลูปสร้างแถว
            foreach ($contacts as $cp) {
                // ใส่ข้อมูลคนไข้ทุกบรรทัด (เดี๋ยว Merge)
                foreach ($patientData as $col => $val) {
                    $sheet->setCellValue($col . $row, $val);
                }

                // ✅ ใส่ข้อมูลผู้สัมผัสลงคอลัมน์แยกกัน (M, N, O, P)
                $sheet->setCellValue('M' . $row, $cp->name_surname);
                $sheet->setCellValue('N' . $row, \App\Models\ContactPerson::$cpsexOption[$cp->cpsex] ?? '-');
                $sheet->setCellValue('O' . $row, \App\Models\ContactPerson::$typeOption[$cp->type] ?? '-');
                
                $trackVal = $cp->trush_tracks ?? 0;
                $trackStr = isset(\App\Models\ContactPerson::$touchTracingOption[$trackVal]) ? \App\Models\ContactPerson::$touchTracingOption[$trackVal] : '';
                $sheet->setCellValue('P' . $row, $trackStr);
                
                $row++;
            }

            // Merge Cells ข้อมูลคนไข้ (A-L และ Q)
            $endRow = $row - 1;
            if ($endRow > $startRow) {
                foreach (['A','B','C','D','E','F','G','H','I','J','K','L','Q'] as $col) {
                    $sheet->mergeCells($col . $startRow . ':' . $col . $endRow);
                }
            }

        } else {
            // กรณีไม่มีผู้สัมผัส: สร้าง 1 แถวว่างๆ
            foreach ($patientData as $col => $val) {
                $sheet->setCellValue($col . $row, $val);
            }
            $sheet->setCellValue('M' . $row, '-');
            $sheet->setCellValue('N' . $row, '-');
            $sheet->setCellValue('O' . $row, '-');
            $sheet->setCellValue('P' . $row, '-');
            $row++;
        }
    }

    // 4. จัดรูปแบบสุดท้าย
    $lastRow = $row - 1;
    
    // จัดตำแหน่ง: ชิดซ้าย + ชิดบน
    $sheet->getStyle('A2:Q'.$lastRow)->getAlignment()
        ->setVertical(Alignment::VERTICAL_TOP)
        ->setHorizontal(Alignment::HORIZONTAL_LEFT);
    
    // Wrap Text
    $sheet->getStyle('G2:G'.$lastRow)->getAlignment()->setWrapText(true);
    $sheet->getStyle('L2:L'.$lastRow)->getAlignment()->setWrapText(true);
    
    // ตีเส้นตาราง
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FFCCCCCC'],
            ],
        ],
    ];
    $sheet->getStyle('A1:Q'.$lastRow)->applyFromArray($styleArray);

    // ปรับความกว้าง
    foreach(range('A','Q') as $col) { 
        if($col == 'G') {
            $sheet->getColumnDimension($col)->setWidth(30); 
        } elseif (in_array($col, ['M','O','P'])) {
            $sheet->getColumnDimension($col)->setWidth(20); // ช่องผู้สัมผัสกว้างหน่อย
        } else {
            $sheet->getColumnDimension($col)->setAutoSize(true); 
        }
    }

    // Export
    $writer = new Xlsx($spreadsheet);
    $fileName = "appointments_" . date('Y-m-d_H-i') . ".xlsx";
    
    // ส่งไฟล์ผ่าน response ของ Laravel แทน header()+exit — middleware (session/log) ทำงานครบ และทดสอบอัตโนมัติได้
    return response()->streamDownload(function () use ($writer) {
        $writer->save('php://output');
    }, $fileName, [
        'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Cache-Control' => 'max-age=0',
    ]);
}
protected function stampByContext(Visit $visit, ?string $forceStage = null): void
{
    $stage = $forceStage ?? session('acting_stage');

    if (!$stage && auth()->check()) {
        $u = auth()->user();
        if (method_exists($u, 'hasRole')) {
            if     ($u->hasRole('Nurse'))     { $stage = 'nurse'; }
            elseif ($u->hasRole('Doctor'))    { $stage = 'doctor'; }
            elseif ($u->hasRole('Registrar')) { $stage = 'registration'; }
            elseif ($u->hasRole('Pharmacy'))  { $stage = 'pharmacy'; }
            elseif ($u->hasRole('Lab'))       { $stage = 'lab'; }
        }
    }

    if ($stage) {
        $this->stampByRules($visit, ['source' => $stage, 'overwrite' => true]);
    }
}
public function print($id)
{
    $visit = \App\Models\Visit::findOrFail($id);
    $patient = $visit->patient(); // หรือ $visit->patient ขึ้นอยู่กับความสัมพันธ์ใน Model
    
    return view('visit.print', compact('visit', 'patient'));
}
public function stats(Request $request)
    {
        // 1. คำนวณปีงบประมาณ
        $defaultYear = (date('m') >= 10) ? date('Y') + 1 : date('Y');
        $year = $request->get('year', $defaultYear);

        $startDate = \Carbon\Carbon::createFromDate($year - 1, 10, 1)->startOfDay();
        $endDate   = \Carbon\Carbon::createFromDate($year, 9, 30)->endOfDay();

        // -------------------------------------------------------
        // (ส่วนที่ 1 & 2: รายใหม่/เก่า และ สถิติรายวัน ... ใช้โค้ดเดิมได้เลยครับ)
        // -------------------------------------------------------
        $totalVisits = Visit::currentClinic()->whereBetween('created_at', [$startDate, $endDate])->count();
        $newPatients = Visit::currentClinic()->whereBetween('created_at', [$startDate, $endDate])
            ->whereHas('patient2', function($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]); 
            })->count();
        $oldPatients = $totalVisits - $newPatients;

        // ==========================================
        // 2. สถิติรายวัน (Max, Min, Mode, Avg)
        // ✅ แก้ไข: เพิ่ม whereRaw เพื่อกรองวันในระดับ SQL (เฉพาะ จ,พ,ศ)
        // หมายเหตุ: ใน MySQL -> DAYOFWEEK(1)=อาทิตย์, 2=จันทร์, 4=พุธ, 6=ศุกร์
        // ==========================================
        $dailyCounts = Visit::currentClinic()->selectRaw('DATE(created_at) as visit_date, count(*) as total')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereRaw('DAYOFWEEK(created_at) IN (2, 4, 6)') // 2=Mon, 4=Wed, 6=Fri
            ->groupBy('visit_date')
            ->pluck('total');

        if ($dailyCounts->count() > 0) {
            $dailyStats = [
                'max' => $dailyCounts->max(),
                'min' => $dailyCounts->min(),
                'avg' => number_format($dailyCounts->avg(), 2),
                'mode' => implode(', ', $dailyCounts->mode()) 
            ];
        } else {
            // ... else {
            $dailyStats = ['max'=>0, 'min'=>0, 'avg'=>0, 'mode'=>'-'];
        }

        // -------------------------------------------------------
        // 3. สถิติเวลา (Waiting & Service Time)
        // ✅ กรองเฉพาะ จันทร์(1), พุธ(3), ศุกร์(5) + ตัดข้อมูล Error (> 8 ชม.)
        // -------------------------------------------------------
        // ... (โค้ดส่วนกรองวันและตัดเวลา 8 ชม. เดิม) ...
        $visitsWithTime = Visit::whereBetween('created_at', [$startDate, $endDate])
                               ->whereNotNull('created_at')
                               ->get()
                               ->filter(function($v) {
                                   return in_array($v->created_at->dayOfWeek, [1, 3, 5]);
                               });

        // 3.1 เวลารอคอย (Wait) - ตัดที่ 480 นาที (8 ชม.)
        $waitTimes = $visitsWithTime->map(function($v) {
            $end = $v->doctor_at ?? $v->screening_at;
            if ($end) {
                $diff = (int) $v->created_at->diffInMinutes($end, true);
                if ($diff > 0 && $diff < 480) return $diff;
            }
            return null;
        })->filter(fn($val) => !is_null($val));

        // 3.2 เวลารับบริการ (Service) - ตัดที่ 480 นาที
        $serviceTimes = $visitsWithTime->map(function($v) {
            $start = $v->created_at;
            $end = $v->finished_at ?? $v->updated_at; 
            if ($start && $end && $end > $start) {
                $diff = (int) $start->diffInMinutes($end, true);
                return ($diff < 480) ? $diff : null; 
            }
            return null;
        })->filter(fn($val) => !is_null($val));

        // ✅ เพิ่ม: นับจำนวนคนที่รอนานเกิน 4 ชั่วโมง (240 นาที) แต่ไม่เกิน 8 ชม.
        $longWaitCount = $waitTimes->filter(function ($t) { return $t >= 240; })->count();
        $longServiceCount = $serviceTimes->filter(function ($t) { return $t >= 240; })->count();

        $timeStats = [
            'wait' => [
                'min' => $waitTimes->count() ? $waitTimes->min() : 0,
                'max' => $waitTimes->count() ? $waitTimes->max() : 0,
                'avg' => $waitTimes->count() ? number_format($waitTimes->avg(), 0) : 0,
                'mode' => $waitTimes->count() ? implode(', ', $waitTimes->mode()) : '-',
                'long_count' => $longWaitCount, // ✅ ส่งค่าไป View
            ],
            'service' => [
                'min' => $serviceTimes->count() ? $serviceTimes->min() : 0,
                'max' => $serviceTimes->count() ? $serviceTimes->max() : 0,
                'avg' => $serviceTimes->count() ? number_format($serviceTimes->avg(), 0) : 0,
                'mode' => $serviceTimes->count() ? implode(', ', $serviceTimes->mode()) : '-',
                'long_count' => $longServiceCount, // ✅ ส่งค่าไป View
            ]
        ];

        return view('stats.index', compact('year', 'totalVisits', 'newPatients', 'oldPatients', 'dailyStats', 'timeStats'));
    }
    public function updateQueue(Request $request, $id)
    {
        $visit = Visit::findOrFail($id);
        $visit->queue = $request->input('queue'); // รับค่าคิวใหม่
        $visit->save();

        return response()->json(['status' => 'success', 'queue' => $visit->queue]);
    }
    public function medicalCertificate($id)
    {
        // ดึงข้อมูลที่เกี่ยวข้องทั้งหมด
        $visit = Visit::with(['patient2', 'doctorUser', 'diagnoses'])->findOrFail($id);
        $snapshot = null;
        $certificate = null;

        return view('visit.certificate', compact('visit', 'snapshot', 'certificate'));
    }

    /** ใบรับรองแพทย์ฉบับภาษาอังกฤษ — คนไข้ต่างชาติ TMC ใช้ยื่นสมัครงาน/เรียน/วีซ่า */
    public function medicalCertificateEn($id)
    {
        $visit = Visit::with(['patient2', 'doctorUser', 'diagnoses'])->findOrFail($id);
        $snapshot = null;
        $certificate = null;

        return view('visit.certificate_en', compact('visit', 'snapshot', 'certificate'));
    }

    // บันทึกเนื้อหาที่แก้ไขจริงตอนออกใบรับรองแพทย์ (เพื่อดูย้อนหลังได้เป๊ะๆ ตามที่ปริ้นท์จริง)
    public function storeMedicalCertificate(Request $request, $id)
    {
        $visit = Visit::findOrFail($id);

        $certificate = \App\Models\VisitCertificate::create([
            'visit_id' => $visit->id,
            'issued_by' => auth()->id(),
            'data' => $request->input('data', []),
        ]);

        return response()->json(['status' => 'success', 'id' => $certificate->id]);
    }

    // ดูใบรับรองแพทย์ที่เคยออกไปแล้ว (เนื้อหาเป๊ะๆ ตามตอนออกจริง ไม่ generate ใหม่)
    public function viewMedicalCertificate($certificateId)
    {
        $certificate = \App\Models\VisitCertificate::with(['visit.patient2', 'visit.doctorUser', 'issuedByUser'])->findOrFail($certificateId);
        $visit = $certificate->visit;
        $snapshot = $certificate->data;

        // ต้องดูย้อนหลังด้วย "ภาษาเดียวกับตอนออก" — เก็บ lang ไว้ใน snapshot ตอนบันทึก
        $view = (($snapshot['lang'] ?? 'th') === 'en') ? 'visit.certificate_en' : 'visit.certificate';

        return view($view, compact('visit', 'snapshot', 'certificate'));
    }

    /**
     * Autosave (AJAX) เฉพาะช่องข้อความคลินิกในหน้า visit/edit
     * บันทึกเฉพาะ whitelist (symptom/body_check/treatment/body_check_marks) แบบ saveQuietly()
     * -> ไม่แตะ status/คิว/เวลา(doctor_at ฯลฯ)/บิล/patient2 และไม่ยิง model event ใดๆ
     * งานจบ/เปลี่ยนห้อง/วันนัด/บิล ยังต้องกดปุ่ม Save เดิม (update())
     */
    public function autosave(Request $request, $id)
    {
        $visit = Visit::findOrFail($id);

        $data = [];
        foreach (['symptom', 'body_check', 'treatment'] as $f) {
            if ($request->has($f)) {
                $data[$f] = $request->input($f);
            }
        }

        // สัญญาณชีพ TMC — autosave ด้วย
        // ช่องว่าง = ยังไม่ได้วัด เก็บเป็น null (ไม่ใช่ 0 ซึ่งจะหมายถึงวัดได้ 0)
        // ค่าที่พิมพ์ค้างครึ่งๆ ระหว่างพิมพ์ (เช่น "3." ตอนกำลังจะพิมพ์ 36.5) ให้ข้ามไปก่อน
        // ไม่เขียนทับของเดิม กันค่าดีหายเพราะจังหวะ autosave
        $vitalRules = [
            'weight' => [0, 400], 'height' => [30, 250], 'temperature' => [30, 45],
            'pulse' => [20, 250], 'systolic' => [40, 300], 'diastolic' => [20, 200],
            'respiratory_rate' => [5, 80], 'spo2' => [50, 100],
        ];
        // คลินิกวัณโรค (2569-10-22) — ยังไม่รัน migration ห้ามเขียน ไม่งั้น autosave ล้มทั้งก้อน
        if (\Illuminate\Support\Facades\Schema::hasColumn('visits', 'dtx')) {
            $vitalRules += ['dtx' => [10, 900], 'qtcf' => [200, 800]];
        }
        foreach ($vitalRules as $f => [$min, $max]) {
            if (!$request->has($f)) { continue; }
            $raw = trim((string) $request->input($f));
            if ($raw === '') { $data[$f] = null; continue; }
            if (!is_numeric($raw)) { continue; }          // พิมพ์ค้าง เช่น "3." — รอ autosave รอบหน้า
            $v = (float) $raw;
            if ($v < $min || $v > $max) { continue; }     // นอกช่วงที่เป็นไปได้ = พิมพ์ยังไม่จบ ข้ามไว้
            $data[$f] = $v;
        }

        // body_check_marks: กรอง JSON ให้ถูกต้องเหมือนใน update()
        if ($request->has('body_check_marks')) {
            $marks = $request->input('body_check_marks');
            if (is_string($marks)) {
                $decoded = json_decode($marks, true);
                $marks = is_array($decoded) ? $decoded : [];
            }
            $data['body_check_marks'] = is_array($marks)
                ? collect($marks)->filter(function ($mark) {
                    if (!is_array($mark) || !isset($mark['type'])) return false;
                    if ($mark['type'] === 'point') {
                        return isset($mark['x'], $mark['y']) && is_numeric($mark['x']) && is_numeric($mark['y']);
                    }
                    if ($mark['type'] === 'path') {
                        return isset($mark['points']) && is_array($mark['points']) && count($mark['points']) > 1;
                    }
                    return false;
                })->values()->all()
                : [];
        }

        if (empty($data)) {
            return response()->json(['status' => 'noop']);
        }

        $visit->fill($data);
        $visit->saveQuietly(); // ไม่ยิง event -> ไม่ trigger side-effect/queue/audit
        // QTcF = ผลของ EKG ที่สั่งไว้ใน visit นี้ (ถ้ามี)
        if (array_key_exists('qtcf', $data)) { \App\Models\LabSub::syncQtcfFromVisit($visit); }

        return response()->json(['status' => 'success', 'saved_at' => now()->format('H:i')]);
    }

    /**
     * แบบฟอร์มการส่งผู้ป่วยรับการรักษาต่อ (ครึ่ง A4 / A5 แนวนอน)
     * ใช้กับเคสผลเลือดบวกที่ต้องส่งไปตรวจ CD4 และรักษาต่อที่โรงพยาบาล
     *
     * เติมให้เท่าที่ระบบรู้ (ชื่อ/เพศ/อายุ/เลขบัตร/ผลเลือดจากแล็บเรา)
     * ส่วนชื่อโรงพยาบาลกับผู้ลงนามพิมพ์บนหน้าจอก่อนสั่งพิมพ์ได้
     */
    public function referForm(Request $request, $id)
    {
        $visit   = Visit::findOrFail($id);
        $patient = $visit->patient();

        // ผลเลือดต้องมาจากแล็บเราและผ่านการอนุมัติแล้วเท่านั้น (กติกาเดียวกับที่อื่นทั้งระบบ)
        $result = $visit->labResultByMethod(6);

        // วันที่ผล: ใช้วันอนุมัติผลของห้องแล็บถ้ามี ไม่มีก็ใช้วันที่มารับบริการ
        $lab        = \App\Models\Lab::where('visit_id', $visit->id)->orderByDesc('id')->first();
        $resultDate = $lab && $lab->approve_date ? $lab->approve_date : $visit->date;

        $nowBe = (now()->year + 543) . now()->format('-m-d');

        return view('visit.refer_form', [
            'visit'       => $visit,
            'name'        => trim((\App\Models\Patient::prefixLabel($patient) ?? '')
                             . ' ' . ($patient->name ?? '') . ' ' . ($patient->surname ?? '')),
            'sex'         => (int) ($patient->sex ?? 0) === 1 ? 'ชาย' : ((int) ($patient->sex ?? 0) === 2 ? 'หญิง' : ''),
            'age'         => $patient ? preg_replace('/\D/', '', $patient->age()) : '',
            'idCard'      => $patient->id_card_number ?? '',
            'bloodResult' => $result !== '' ? 'Anti-HIV ' . $result : '',
            'resultDate'  => $this->thDateText($resultDate),
            'hospital'    => $request->get('hospital', ''),
            // ข้อความตามแบบฟอร์มบันทึกข้อความฉบับใหม่ของคลินิก (2569-10-05)
            'reason'      => $request->get('reason', 'พิจารณาตรวจ CD4 และเข้าสู่กระบวนการรักษา'),
            'signer'      => '',
            'day'         => (int) substr($nowBe, 8, 2),
            'monthName'   => \App\Utillity::TH_MONTH_FULL[(int) substr($nowBe, 5, 2)] ?? '',
            'year'        => substr($nowBe, 0, 4),
        ]);
    }

    /**
     * ใบนัดคลินิกวัณโรค — หนึ่งใบต่อหนึ่งวันนัด
     *
     * ทุกช่องมาจากข้อมูลที่กรอกไว้ในหน้าตรวจ (คลินิกต้องการกรอกก่อนแล้วค่อยพิมพ์)
     * แพทย์ที่ทำการนัด: ที่เลือกไว้ในแถวนัด -> ถ้าไม่ได้เลือก ใช้แพทย์ของการมาครั้งนี้
     * ชื่อผู้นัด: คนที่ลงนัดในระบบ -> ถ้าเป็นนัดเก่าที่ไม่ได้เก็บไว้ ใช้คนที่กดพิมพ์
     */
    private function tbAppointmentCard(Visit $visit)
    {
        $short = [1 => 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        $be2text = function ($be) use ($short) {
            $p = explode('-', (string) $be);
            return count($p) === 3 ? ((int) $p[2]) . ' ' . ($short[(int) $p[1]] ?? '') . ' ' . ((int) $p[0]) : (string) $be;
        };

        $pt = $visit->patient2;
        $hn = (string) ($pt->code ?? '');
        $patientName = trim(((\App\Models\Patient::prefixLabel($pt) ?? '')) . ($pt->name ?? '') . ' ' . ($pt->surname ?? ''));

        $rightText     = ($pt && (string) $pt->health_right !== '') ? $pt->healthRightText() : '';
        $rightHospital = $pt ? $pt->healthRightHospital() : '';

        // บาร์โค้ด HN ทำฝั่งเซิร์ฟเวอร์ ไม่พึ่งสคริปต์จากเน็ต (เครื่องในคลินิกออกเน็ตไม่ได้ทุกเครื่อง)
        $barcodeSvg = '';
        if ($hn !== '') {
            try {
                $svg = (new \Milon\Barcode\DNS1D)->getBarcodeSVG($hn, 'C128', 1.4, 40, 'black', false);
                // ตัดหัว XML/DOCTYPE ออก ฝังลงในหน้า HTML ตรง ๆ ไม่ได้ถ้ามีหัวนี้ติดมา
                $barcodeSvg = preg_replace('/^.*?(<svg\b)/s', '$1', $svg);
            } catch (\Throwable $e) {
                $barcodeSvg = '';
            }
        }

        $appts = \App\Models\VisitAppointment::open()->where('visit_id', $visit->id)
            ->where('appointment_date', '>=', \App\Models\VisitAppointment::todayBe())
            ->orderBy('appointment_date')->orderBy('id')->get();

        $userIds = $appts->pluck('doctor_id')->merge($appts->pluck('made_by'))->filter()->unique()->all();
        $names = $userIds ? \App\Models\User::whereIn('id', $userIds)->pluck('name', 'id') : collect();

        $slips = $appts->map(function ($a) use ($be2text, $names, $visit) {
            return [
                'date'   => $be2text($a->appointment_date),
                'time'   => (string) ($a->appt_time ?? ''),
                'reason' => trim((string) ($a->reason ?? ''), ' ,'),
                'pre'    => trim((string) ($a->pre_visit ?? '')),
                'doctor' => $names[$a->doctor_id] ?? ($visit->doctorUser->name ?? '-'),
                // ไม่รู้ว่าใครนัด (นัดก่อนมีช่องนี้) = เว้นว่างให้เขียนเอง ไม่เดาเป็นคนที่กดพิมพ์
                'maker'  => $names[$a->made_by] ?? '',
            ];
        })->values();

        $now = now();
        $issueText = (int) $now->format('d') . ' ' . $short[(int) $now->format('n')] . ' ' . ((int) $now->format('Y') + 543);

        // ค่าของคลินิกวัณโรค — คัดจากใบนัดเดิมของคลินิก เปลี่ยนวัน/ห้องแก้ตรงนี้ที่เดียว
        $tbSlip = [
            'room'          => 'วัณโรค',
            'place'         => 'ห้องเวชระเบียน หมายเลข 1',
            'days'          => 'ให้บริการเฉพาะ วันอังคาร และ วันพฤหัสบดี',
            'rights_no'     => 'ไม่สามารถใช้สิทธิสวัสดิการรักษาพยาบาลจากทุกหน่วยงานได้',
            'rights_except' => 'ยกเว้น!! ผู้ที่ได้รับการส่งต่อจาก รพ.ต้นสังกัด และมีหนังสือรับรองสิทธิ์ เท่านั้น',
            'contact'       => 'โทรศัพท์ 053-140774 ต่อ 600-602 หรือ Line Official: "@352Bavoo"',
        ];

        return view('visit.appointment_card_tb', compact(
            'visit', 'slips', 'hn', 'patientName', 'barcodeSvg', 'rightText', 'rightHospital', 'issueText', 'tbSlip'
        ));
    }

    /** 'Y-m-d' พ.ศ. -> 'd/m/Y' (ว่างคืน '') */
    private function thDateText(?string $be): string
    {
        if (!$be || !preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $be, $m)) { return ''; }
        return (int) $m[3] . '/' . (int) $m[2] . '/' . $m[1];
    }

    // ใบนัดหมาย (แบบราชการ) — พิมพ์จากหน้าตรวจ ดึงวันนัด/เหตุผล/แพทย์ผู้นัด จาก visit
    public function appointmentCard(Request $request, $id)
    {
        $visit = Visit::with(['patient2', 'doctorUser'])->findOrFail($id);

        // รายชื่อแพทย์ (role Doctor) ให้เลือกเปลี่ยนแพทย์ผู้นัดได้ — กันเคสพยาบาลใช้ ID ตัวเองเป็นหมอ
        $doctors = \App\Models\User::doctors();

        // ใบนัด 2 แบบ: general = นัดทั่วไป · vaccine = นัดฉีดวัคซีนเข็มถัดไป
        // รูปแบบที่ส่งให้ view เหมือนกันทั้ง 2 ที่มา: ['date' => 'dd/mm/พ.ศ.', 'vaccines' => [ชื่อ, ...]]
        $type = $request->get('type') === 'vaccine' ? 'vaccine' : 'general';

        // คลินิกวัณโรคใช้ใบนัดคนละแบบกับ STI ตามฟอร์มเดิมของคลินิก (2569-09-29)
        if ((int) $visit->clinic === 2 && $type === 'general') {
            return $this->tbAppointmentCard($visit);
        }
        $vaccineDue = collect();
        if ($type === 'vaccine') {
            // แผนที่แพทย์วางไว้มาก่อนเสมอ — พยาบาลต้องการใบนัดที่บอกว่า "วันไหน ฉีดอะไร เข็มที่เท่าไร"
            $plan = \App\Models\VisitVaccine::normalizePlan($visit->tmc_vaccine_plan);
            if (count($plan)) {
                $vaccineDue = collect($plan)->map(function ($r) {
                    return ['date' => $r['date'], 'text' => \App\Models\VisitVaccine::planItemsText($r['items']) . \App\Models\VisitVaccine::planSiteText($r)];
                })->values();
            } else {
                // visit เก่าที่ยังไม่มีแผน — ถอยไปใช้กำหนดเข็มถัดไปจากประวัติการฉีด (ล่าสุดต่อชนิดวัคซีน)
                $vaccineDue = \App\Models\VisitVaccine::where('patient_id', $visit->patient_id)
                    ->whereNotNull('next_due_date')->where('next_due_date', '<>', '')
                    ->orderByDesc('id')->get()->unique('name')
                    ->map(function ($s) {
                        $dose = $s->dose_number ? ('Dose ' . ((int) $s->dose_number + 1)) : '';
                        return ['date' => $s->next_due_date_th ?? '', 'text' => trim($s->name . ' ' . $dose)];
                    })->values();
            }
        }

        return view('visit.appointment_card', compact('visit', 'doctors', 'type', 'vaccineDue'));
    }
}
