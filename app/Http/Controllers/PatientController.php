<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Models\Country;
use App\Models\Lab;
use App\Models\Patient;
use App\Models\Visit;
use App\Utillity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\VisitRecorder; // 👈 ใช้ Trait
use App\Models\Item;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;
use App\Models\Address;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class PatientController extends Controller
{
    use VisitRecorder; // 👈 ใส่ trait
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $perPage = 25;
        $query = Patient::query();   

        if (!empty($keyword)) {
            $patient = $query->where('code', 'LIKE', "%$keyword%")
                ->orWhere('id', 'LIKE', "%$keyword%")
                ->orWhere('prefix', 'LIKE', "%$keyword%")
                ->orWhere('name', 'LIKE', "%$keyword%")
                ->orWhere('surname', 'LIKE', "%$keyword%")
                ->orWhere('first_visit', 'LIKE', "%$keyword%")
                ->orWhere('id_card_number', 'LIKE', "%$keyword%")
                ->orWhere('passport', 'LIKE', "%$keyword%")
                ->orWhere('birth_date', 'LIKE', "%$keyword%");
        }
        $sex = $request->get('sex');
        if (!empty($sex)) {
            $query = $query->where('sex', $sex);
        }
        $start_age = $request->get('start_age');
        if (!empty($start_age)) {
            $year = Utillity::age2BirthBE($start_age);
            $query = $query->where('birth_date', '<=',$year."-01-01");
        }
        $end_age = $request->get('end_age');
        if (!empty($end_age)) {
            $year = Utillity::age2BirthBE($end_age);
            $query = $query->where('birth_date', '>=',$year."-01-01");
        }
        

        // $start = $request->get('start');
        // if(!empty($start) || !empty($end)){
        //     $queryVisit  = Visit::query(); 
        //     if (!empty($start)) {
        //         $start = Utillity::th2dbDate($start);
        //         $queryVisit = $queryVisit->where('date', '<=',$start);
        //     }
        //     $end = $request->get('end');
        //     if (!empty($end)) {
        //         $end = Utillity::th2dbDate($end);
        //         $queryVisit = $queryVisit->where('date', '>=',$end);
        //     }
        //     $patient_id = $queryVisit->pluck('patient_id');
        //     $query = $query->whereIn('id',$patient_id);
        // }

      
        

        $patient = $query->latest()->paginate($perPage);
        $tbMarks = \App\Models\TbRegistration::activeMarks($patient->pluck('id'));

        return view('patient.index', compact('patient', 'tbMarks'));
    }

    

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    // public function create()
    // {
    //     return view('patient.create');
    // }
       public function create()
{
    $countries = Country::select('country_id', 'nation_name_en', 'nation_name_th')->get();
    return view('patient.create', compact('countries'));
}


    /** autosave สมุดเหลือง (ระดับคนไข้ 1 คน/เล่ม) จากโซนวัคซีน TMC */
    public function saveYellowBook(Request $request, $id)
    {
        $patient = Patient::findOrFail($id);
        $patient->update([
            'yellow_book_no'      => $request->input('yellow_book_no'),
            'yellow_book_history' => $request->input('yellow_book_history'),
        ]);
        return response()->json(['ok' => true]);
    }

    /**
     * ติ๊ก/เอาออก "เคสเก่าจากทะเบียนกระดาษ" ของคลินิกวัณโรค (2569-11-05) — รายงานนับคนนี้เป็นผู้ป่วยเก่าเสมอ
     * กล่องอยู่ในฟอร์มหลักของหน้าตรวจ จึงบันทึกด้วย fetch ทันทีที่ติ๊ก ไม่ไปกับปุ่ม Save
     */
    public function saveTbPaperOld(Request $request, $id)
    {
        if (! Patient::tbPaperOldReady()) { return response()->json(['ok' => false, 'message' => 'ยังไม่ได้อัปเดตฐานข้อมูล'], 422); }
        $patient = Patient::findOrFail($id);
        $patient->forceFill(['tb_paper_old' => $request->boolean('on')])->save();
        return response()->json(['ok' => true, 'on' => (bool) $patient->tb_paper_old]);
    }

    /** ออกสมุดเหลืองเล่มใหม่ (ทดแทนชำรุด/หาย/เสีย) — เก็บเล่มเก่าเข้าประวัติอัตโนมัติ */
    public function issueYellowBook(Request $request, $id)
    {
        $patient = Patient::findOrFail($id);
        $reason = trim((string) $request->input('reason'));   // ชำรุด / หาย / เสีย
        $newNo  = trim((string) $request->input('new_no'));
        $old    = trim((string) $patient->yellow_book_no);

        $log = trim((string) $patient->yellow_book_history);
        if ($old !== '') {
            $entry = 'เล่มเก่า ' . $old . ($reason !== '' ? ' — ' . $reason : '')
                   . ' (' . now()->format('d/m/') . (now()->year + 543) . ')';
            $log = $log !== '' ? $log . ' | ' . $entry : $entry;
        }

        $patient->update([
            'yellow_book_no'      => $newNo !== '' ? $newNo : $old,
            'yellow_book_history' => $log,
        ]);

        return response()->json([
            'ok'      => true,
            'no'      => $patient->yellow_book_no,
            'history' => $patient->yellow_book_history,
        ]);
    }

    public function createVisit(Request $request)
{
    // 1) validate ก่อน
    $validated = $request->validate([
        'patient_id' => ['required','exists:patients,id'],
        'clinic'     => ['required','integer','in:1,2,3'],
        // ห้องปลายทางต้องอยู่ในลิสต์นี้ ไม่งั้นส่งเข้าห้องไม่ได้ ("The selected status is invalid")
        // 7 = ห้องตรวจ 7 (TMC) · 10 = ห้องตรวจวัณโรค 2 (เพิ่ม 2569-09-18 ตอนเปิดคลินิก TB)
        // อ่านจาก Visit::$statusOption ตรง ๆ จะได้ไม่ต้องไล่แก้ทุกครั้งที่เพิ่มห้องใหม่
        'status'     => ['required','integer','in:' . implode(',', array_keys(\App\Models\Visit::$statusOption))],
        'queue_system_queue_id' => ['nullable','integer'],
    ]);

    // 2) แปลงรูปแบบข้อมูลตามที่โปรเจ็กต์ใช้ (ถ้าจำเป็น)
    //    *รวม* ค่า validated เข้าไปด้วย เพื่อให้แน่ใจว่าฟิลด์หลักไม่หาย
    $payload = Visit::format2DB(array_merge($request->all(), $validated));
    unset($payload['queue_system_queue_id']); // ไม่ใช่คอลัมน์ของ visits โดยตรง จัดการแยกด้านล่าง
    $payload['room_entered_at'] = now(); // ใช้เรียงคิวหน้า visit.room ตามเวลาที่เข้าห้องแรกจริง

    $visit = null;
    $lab   = null;

    // กันกดเปิดงานซ้ำ (ดับเบิลคลิก) — คำขอที่สองมาก่อนคำขอแรกบันทึกเสร็จ ได้ visit ซ้อน 2 อัน (เจอจริง 2569-11-03)
    // ล็อกต่อคนไข้ให้ทำทีละคำขอ แล้วถ้าคนนี้เพิ่งเปิดงานคลินิกเดียวกันไปไม่ถึง 30 วิ ถือว่าเป็นการกดซ้ำ ไม่สร้างใหม่
    $dup = false;
    \Illuminate\Support\Facades\Cache::lock('create-visit:' . $payload['patient_id'], 15)->block(10, function () use ($payload, &$visit, &$dup) {
        $recent = Visit::where('patient_id', $payload['patient_id'])
            ->where('clinic', $payload['clinic'])
            ->where('created_at', '>=', now()->subSeconds(30))
            ->latest('id')->first();
        if ($recent) { $visit = $recent; $dup = true; return; }

        $visit = DB::transaction(function () use ($payload) {
            $visit = Visit::create($payload);
            Lab::create([
                'patient_id' => $visit->patient_id,
                'visit_id'   => $visit->id,
            ]);
            return $visit;
        });
    });
    if ($dup) {
        $patient = Patient::find($visit->patient_id);
        flash('เปิดงานให้คนไข้คนนี้ไปแล้วเมื่อสักครู่ — ไม่ได้เปิดซ้ำ', 'warning');
        return redirect()->route('patient.index', ['search' => $patient->code]);
    }

    // 2.1) ล็อกคิวจากระบบเรียกคิวกลาง ให้ visit นี้ทันทีที่เปิดงาน (ถ้าเลือกไว้)
    if (!empty($validated['queue_system_queue_id'])) {
        $queue = \App\Models\QueueSystemQueue::find($validated['queue_system_queue_id']);
        if ($queue) {
            $visit->queue_system_id = $queue->id;
            $visit->queue = $queue->queue_number;
            $visit->save();
        }
    }

    // 5) ลงชื่อผู้บันทึกตามบทบาท — ใช้ $visit (ห้ามใช้ตัวแปร $lab)
   $this->stampByRules($visit, [
    'source'    => 'registration',
    'overwrite' => true,   // ครั้งแรกครั้งเดียว
]);
    // 6) redirect กลับไปหน้ารายการคนไข้ โดยใส่ค่า search เป็น HN
    $patient = Patient::find($visit->patient_id);

    flash('Visit added!', 'success');
    Alert::success('Visit added!');

    return redirect()->route('patient.index', ['search' => $patient->code]);
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
    DB::beginTransaction();

    try {
        // เลขบัตรประชาชน กับ พาสปอร์ต แยกช่องแล้ว (2569-10-07) — ต้องมีอย่างน้อยหนึ่งช่อง · ซ้ำตรวจเฉพาะช่องที่กรอก
        if ($err = self::idPassportErrors($request)) {
            return redirect()->back()->withErrors($err)->withInput();
        }

        // แปลงข้อมูลก่อนบันทึก
        $requestData = $request->all();
        $requestData = Patient::format2DB($requestData);
        if (array_key_exists('passport', $requestData)) {
            $requestData['passport'] = strtoupper(preg_replace('/\s+/', '', (string) $requestData['passport'])) ?: null;
        }
        $requestData = self::normalizeEnglishName($requestData);

        // ถ้ามี HN ซ้ำ ให้สร้างใหม่
        $existingPatient = Patient::getByNH($requestData['code']);
        if ($existingPatient) {
            $requestData['code'] = Patient::calNH();
        }

        // แปลงค่า checkbox phone_changed
        $requestData['phone_changed'] = $request->has('phone_changed') ? 1 : 0;

        // อัปโหลดรูปภาพ ถ้ามี — ผู้เยาว์ (ต่ำกว่า 20 ปี) ไม่เก็บรูปตอนลงทะเบียน จนกว่าผู้ปกครองยินยอม (PDPA นโยบายกรม ข้อ 9 · 2569-10-29)
        $__age = \App\Models\PatientConsent::ageOf((string) ($requestData['birth_date'] ?? ''));
        if ($request->hasFile('photo') && $__age !== null && $__age < 20) {
            unset($requestData['photo']);
        } elseif ($request->hasFile('photo')) {
    $file = $request->file('photo');
    $contents = file_get_contents($file->getRealPath());

    // เข้ารหัสเนื้อหาไฟล์
    $encrypted = Crypt::encrypt($contents);

    // สร้างชื่อไฟล์สุ่ม
    $filename = uniqid('photo_') . '.enc';

    // บันทึกไฟล์ที่เข้ารหัสในโฟลเดอร์ private
    Storage::disk('local')->put("private/photos/$filename", $encrypted);

    // บันทึกชื่อไฟล์ที่เข้ารหัสลงฐานข้อมูล
    $requestData['photo'] = $filename;
}

        // บันทึกผู้ป่วย
        $createdPatient = Patient::create($requestData);

        // บันทึกที่อยู่
        $addressData = $request->only([
            'house_no', 'address', 'province', 'district', 'sub_district', 'zipcode',
        ]);
        $addressData['patient_id'] = $createdPatient->id;

        Address::create($addressData);

        DB::commit();
        flash('เพิ่มข้อมูลผู้ป่วยเรียบร้อยแล้ว', 'success');
        return redirect('patient');
    } catch (\Exception $e) {
        DB::rollBack();
        flash('เกิดข้อผิดพลาด: ' . $e->getMessage(), 'danger');
        return redirect()->back()->withInput();
    }
}

public function deletePhoto($id)
{
    $patient = Patient::findOrFail($id);

    // ไฟล์ถูกเก็บใน local disk (storage/app/private/photos)
    $photoPath = "private/photos/{$patient->photo}";

    if ($patient->photo && Storage::disk('local')->exists("private/photos/{$patient->photo}")) {
    Storage::disk('local')->delete("private/photos/{$patient->photo}");
}

    $patient->photo = null;
    $patient->save();

    flash('ลบรูปภาพผู้ป่วยเรียบร้อยแล้ว', 'success');
    return back();
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
        $patient = Patient::findOrFail($id);

        return view('patient.show', compact('patient'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     *
     * @return \Illuminate\View\View
     */
    // public function edit($id)
    // {
    //     $patient = Patient::findOrFail($id);
    //     $patient->formatDBBack();
    //     return view('patient.edit', compact('patient'));
    // }

    public function edit($id)
{
    $patient = Patient::findOrFail($id);
    $patient->formatDBBack();
    //$countries = Country::select('country_id', 'nation_name_en')->get();
    $countries = Country::select('country_id', 'nation_name_en', 'nation_name_th')->get();
    // ดึงวันที่ล่าสุดของ HBs Ag (method = 11)
    $latestHBsAgDate = \DB::table('lab_subs')
        ->join('labs', 'lab_subs.lab_id', '=', 'labs.id')
        ->where('lab_subs.method', 11) // ตรวจสอบ HBs Ag
        ->whereIn('labs.visit_id', $patient->visits()->pluck('id')) // ใช้ visit_id ของผู้ป่วย
        ->orderByDesc('lab_subs.created_at') // เรียงวันที่จากล่าสุด
        ->value('lab_subs.created_at'); // ดึงแค่วันที่ล่าสุด

    // ดึงวันที่ล่าสุดของ Anti-HCV (method = 12)
    $latestAntiHCVDate = \DB::table('lab_subs')
        ->join('labs', 'lab_subs.lab_id', '=', 'labs.id')
        ->where('lab_subs.method', 12) // ตรวจสอบ Anti-HCV
        ->whereIn('labs.visit_id', $patient->visits()->pluck('id')) // ใช้ visit_id ของผู้ป่วย
        ->orderByDesc('lab_subs.created_at') // เรียงวันที่จากล่าสุด
        ->value('lab_subs.created_at'); // ดึงแค่วันที่ล่าสุด

    // ใช้ Carbon เพื่อแปลงวันที่เป็นแค่วันที่ (ไม่มีเวลา)
    $latestHBsAgDate = $latestHBsAgDate ? \Carbon\Carbon::parse($latestHBsAgDate)->format('d-m-Y') : null;
    $latestAntiHCVDate = $latestAntiHCVDate ? \Carbon\Carbon::parse($latestAntiHCVDate)->format('d-m-Y') : null;
    // 5) ลงชื่อผู้บันทึกตามบทบาท — ใช้ $visit (ห้ามใช้ตัวแปร $lab)

    // คิวที่รอเรียกจากระบบเรียกคิวกลาง (queue_system) เฉพาะห้องของคลินิกที่เลือกอยู่
    // STI/TB = 4 ชาย, 5 หญิง, 6 ฉีดยา · TMC = 7
    $availableQueues = \App\Models\QueueSystemQueue::where('status', 'waiting')
        ->whereIn('room_number', \App\Models\Visit::queueRooms())
        ->orderBy('queue_number')
        ->get();

    // โน้ตติดตัวคนไข้ (เช่น พยาบาลฝากถึงห้องบัตร) — โชว์ในหน้าเปิดงาน
    $patientNotes = \App\Models\PatientNote::where('patient_id', $patient->id)
        ->with(['author', 'resolvedByUser'])
        ->orderByDesc('id')
        ->get();

    // ประวัติการรักษา (History)
    // ค่าเริ่มต้น = ล่าสุดขึ้นก่อน (desc) · กด ?hsort=asc เพื่อสลับเป็นเก่า->ใหม่ (ล่าสุดอยู่ล่างสุด)
    // โหมด asc จะเปิดที่ "หน้าสุดท้าย" ให้อัตโนมัติ จะได้เห็นครั้งล่าสุดทันทีเหมือนกัน
    // ใช้ชื่อพารามิเตอร์ 'hpage' แยกจาก ?page= ที่หน้าอื่นใช้อยู่ (กันชนกัน)
    $historyPerPage = 10;
    $historySort    = request('hsort') === 'asc' ? 'asc' : 'desc';

    $historyQuery = $patient->visits2()
        ->orderBy('date', $historySort)
        ->orderBy('id', $historySort);

    $hpage = (int) request('hpage');
    if ($hpage < 1) {
        $historyTotal = (clone $historyQuery)->count();
        $hpage = $historySort === 'asc'
            ? max(1, (int) ceil($historyTotal / $historyPerPage))
            : 1;
    }

    $visitHistory = $historyQuery->paginate($historyPerPage, ['*'], 'hpage', $hpage)
        ->appends(['hsort' => $historySort]);

    return view('patient.edit', compact('patient', 'countries', 'latestHBsAgDate', 'latestAntiHCVDate', 'availableQueues', 'patientNotes', 'visitHistory'));
}


    /**
     * ชื่ออังกฤษเก็บตัวพิมพ์ใหญ่ ช่องว่างเดียว — ใบรับรองต้องตรงกับพาสปอร์ต (MRZ เป็นตัวใหญ่)
     *
     * คนต่างชาติที่ไม่มีชื่อไทย พยาบาลใส่ "-" ในช่องชื่อไทย (ช่องบังคับ) แล้วกรอกแต่ชื่ออังกฤษ
     * → เติมชื่ออังกฤษลงช่องชื่อไทยให้ (2569-10-07) เพราะทั้งระบบ (ตารางค้นหา คิว ห้องตรวจ รายงาน ฯลฯ) อ่านช่อง name/surname
     * ไม่งั้นจะขึ้น "Mr. - -" ทุกที่ · คนต่างชาติก่อนหน้านี้ก็เก็บชื่ออังกฤษไว้ในช่องนี้อยู่แล้ว
     */
    private static function normalizeEnglishName(array $data): array
    {
        foreach (['name_en', 'surname_en'] as $k) {
            if (array_key_exists($k, $data)) {
                $v = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $data[$k])));
                $data[$k] = $v === '' ? null : $v;
            }
        }
        foreach (['name' => 'name_en', 'surname' => 'surname_en'] as $th => $en) {
            if (array_key_exists($th, $data) && in_array(trim((string) $data[$th]), ['', '-'], true) && ! empty($data[$en])) {
                $data[$th] = $data[$en];
            }
        }
        return $data;
    }

    /** ตรวจเลขบัตร/พาสปอร์ต: ต้องมีอย่างน้อยหนึ่ง · ห้ามซ้ำกับคนไข้อื่น — คืน [] = ผ่าน */

    private static function idPassportErrors(Request $request, int $exceptId = 0): array
    {
        $id = trim((string) $request->input('id_card_number'));
        $pp = strtoupper(preg_replace('/\s+/', '', (string) $request->input('passport')));
        if ($id === '' && $pp === '') {
            return ['id_card_number' => 'กรุณากรอกเลขบัตรประชาชน หรือ เลขพาสปอร์ต อย่างน้อยหนึ่งช่อง'];
        }
        // ซ้ำ = คนเดิม: บอก HN/ชื่อของคนที่มีอยู่ ให้ไปใช้คนนั้น (ปุ่มลิงก์อยู่ที่ฟอร์ม — checkIdCard) 2569-10-28
        if ($id !== '' && ($o = Patient::where('id_card_number', $id)->where('id', '!=', $exceptId)->first())) {
            return ['id_card_number' => 'เลขบัตรประชาชนนี้เป็นของคนไข้ที่มีอยู่แล้ว: HN ' . $o->code . ' ' . trim($o->name . ' ' . $o->surname) . ' — ใช้คนไข้เดิม ไม่ต้องลงทะเบียนใหม่'];
        }
        if ($pp !== '' && ($o = Patient::where('passport', $pp)->where('id', '!=', $exceptId)->first())) {
            return ['passport' => 'เลขพาสปอร์ตนี้เป็นของคนไข้ที่มีอยู่แล้ว: HN ' . $o->code . ' ' . trim($o->name . ' ' . $o->surname) . ' — ใช้คนไข้เดิม ไม่ต้องลงทะเบียนใหม่'];
        }
        return [];
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
    $patient = Patient::findOrFail($id);

    // ฟอร์มรุ่นใหม่มีช่อง passport — ตรวจ "ต้องมีอย่างน้อยหนึ่งช่อง" + ซ้ำ (ฟอร์มเก่าที่เปิดค้างไม่มีช่องนี้ ข้าม)
    if ($request->has('passport') && ($err = self::idPassportErrors($request, (int) $patient->id))) {
        return redirect()->back()->withErrors($err)->withInput();
    }

    $requestData = $request->all();
    $requestData = Patient::format2DB($requestData);
    if (array_key_exists('passport', $requestData)) {
        $requestData['passport'] = strtoupper(preg_replace('/\s+/', '', (string) $requestData['passport'])) ?: null;
    }
    $requestData = self::normalizeEnglishName($requestData);

    // จัดการรูปภาพ ถ้ามีไฟล์ใหม่อัปโหลด — คนไข้ไม่ยินยอมเก็บรูป (PDPA) = ไม่เก็บ แม้เครื่องอ่านบัตรจะส่งรูปมา (2569-10-29)
    if ($request->hasFile('photo') && ! \App\Models\PatientConsent::allows((int) $patient->id, 'photo')) {
        unset($requestData['photo']);
        flash('คนไข้ไม่ยินยอมเก็บรูปถ่าย (PDPA) — ระบบไม่ได้บันทึกรูป', 'warning');
    } elseif ($request->hasFile('photo')) {
    $file = $request->file('photo');
    $contents = file_get_contents($file->getRealPath());

    // เข้ารหัสเนื้อหาไฟล์
    $encrypted = Crypt::encrypt($contents);

    // สร้างชื่อไฟล์สุ่ม
    $filename = uniqid('photo_') . '.enc';

    // บันทึกไฟล์ที่เข้ารหัสในโฟลเดอร์ private
    Storage::disk('local')->put("private/photos/$filename", $encrypted);

    // บันทึกชื่อไฟล์ที่เข้ารหัสลงฐานข้อมูล
    $requestData['photo'] = $filename;
}

    $patient->update($requestData);
        // 5) ลงชื่อผู้บันทึกตามบทบาท — ใช้ $visit (ห้ามใช้ตัวแปร $lab)
  
    flash('Patient updated!', 'success');
    Alert::success('Patient updated!');

    return back();
}

public function showPhoto($id)
{
    $patient = Patient::findOrFail($id);

    if (!$patient->photo || !Storage::disk('local')->exists("private/photos/{$patient->photo}")) {
        abort(404);
    }

    $encrypted = Storage::disk('local')->get("private/photos/{$patient->photo}");
    $decrypted = Crypt::decrypt($encrypted);

    return response($decrypted)->header('Content-Type', 'image/jpeg');
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
        Patient::destroy($id);
        flash('Patient deleted!','success');
        Alert::success('Patient deleted!');
        return redirect('patient');
    }
    public function searchCountries(Request $request)
{
    $query = $request->get('query');
    
    // ดึง country_id และ nation_name_th อย่างชัดเจน
     $countries = Country::select('country_id', 'nation_name_en', 'nation_name_th')->get(); // เพิ่ม 'nation_name_th'
    return view('patient.create', compact('countries'));}

    public function updatePhoneChanged(Request $request, $id)
{
    $patient = Patient::findOrFail($id);
    $patient->phone_changed = $request->input('phone_changed');
    $patient->save();

    return redirect()->route('patient.edit', $id)->with('success', 'ข้อมูลได้อัปเดตแล้ว');
}
    // สร้างรหัส OTP สั้นๆ ให้เจ้าหน้าที่อ่านให้คนไข้พิมพ์เข้า LINE เอง (กันคนอื่นสวมสิทธิ์เอา HN ไปผูกแทน)
    public function generateLineLinkCode($id)
    {
        $patient = Patient::findOrFail($id);

        do {
            $code = (string) random_int(100000, 999999);
        } while (Patient::where('line_link_code', $code)->where('line_link_code_expires_at', '>', now())->exists());

        $patient->line_link_code = $code;
        $patient->line_link_code_expires_at = now()->addMinutes(10);
        $patient->save();

        return response()->json([
            'code' => $code,
            'expires_at' => $patient->line_link_code_expires_at->format('H:i'),
        ]);
    }

    public function getPatientDetails($id)
{
    $patient = Patient::findOrFail($id);

    // ดึงข้อมูลที่อยู่
    $address = $patient->address ? $patient->address->fullAddress() : '-';
    
    // ส่งข้อมูลผู้ป่วยกลับในรูปแบบ JSON
    return response()->json([
        'patient_code' => $patient->code,
        'name' => $patient->name . ' ' . $patient->surname,
        'age' => $patient->age(),
        'sex' => $patient->sex,
        'date_of_birth' => $patient->birth_date,
        'address' => $address,
        'phone' => $patient->tel,
        'email' => $patient->email ?? '-',
        'nationality' => $patient->nationality,
        'education' => Patient::$educationOption[$patient->education],
        'marital_status' => Patient::$maritalStatusOption[$patient->marital_status],
        'occupation' => Patient::$occupationOption[$patient->occupation],
        'underlying_disease' => $patient->congenitaldisease,
        'allergy' => $patient->drug_allergy,
        'organization' => $patient->organization,
    ]);
}
/**
 * เลขบัตรประชาชน / พาสปอร์ต ตรงกับคนไข้ที่มีอยู่แล้ว = คนเดียวกัน (คลินิกตกลง 2569-10-28 — ไม่เทียบชื่อ เพราะชื่อซ้ำได้)
 * คืนข้อมูลคนเดิมให้ฟอร์มขึ้นปุ่มลิงก์ไปคนนั้นเลย
 */
public function checkIdCard(Request $request, $id = 0)
{
    $idCard = trim((string) $request->input('id_card_number'));
    $pp = strtoupper(preg_replace('/\s+/', '', (string) $request->input('passport')));
    $o = null; $by = null;
    if ($idCard !== '') { $o = Patient::where('id_card_number', $idCard)->where('id', '!=', (int) $id)->first(); $by = 'เลขบัตรประชาชน'; }
    if (! $o && $pp !== '') { $o = Patient::where('passport', $pp)->where('id', '!=', (int) $id)->first(); $by = 'เลขพาสปอร์ต'; }
    if (! $o) { return response()->json(['exists' => false]); }

    $last = \App\Models\Visit::where('patient_id', $o->id)->orderByDesc('id')->first();
    $lastDate = $last ? (string) $last->getRawOriginal('date') : '';
    return response()->json([
        'exists'   => true,
        'by'       => $by,
        'code'     => $o->code,
        'name'     => trim($o->name . ' ' . $o->surname),
        'last'     => $last ? \App\Utillity::db2thDate($lastDate) . ' · ' . (\App\Models\Visit::$ClinicShortName[$last->clinic] ?? '') : null,
        'edit_url' => route('patient.edit', $o->id),
        'list_url' => route('patient.index', ['search' => $o->code]),
    ]);
}



}
