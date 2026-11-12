<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests;

use App\Models\Diagnosi;
use App\Models\Visit;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class DiagnosisController extends Controller
{
    /**
     * ติ๊ก "ให้คำปรึกษา HIV" Pre-test / Post-test ในหน้าตรวจ TB (2569-10-09) — สร้าง/ลบแถว diagnoses ให้
     * สถานะโรคเหมือนที่เคยลงผ่าน Add New: Pre-test = 21 (ตรวจเลือด HIV) · Post-test = 8 (มาฟังผลเลือด HIV) — ทะเบียน RIHIS-VCT นับจากแถวพวกนี้
     */
    public function hivCounsel(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $disease = (int) $request->input('disease');
        if (! in_array($disease, [13, 14], true)) {
            return response()->json(['ok' => false, 'message' => 'ชนิดไม่ถูกต้อง'], 422);
        }
        $on = (string) $request->input('on') === '1';
        $rows = Diagnosi::where('visit_id', $visit->id)->where('disease', $disease)->get();
        if ($on && $rows->isEmpty()) {
            Diagnosi::create(['visit_id' => $visit->id, 'disease' => $disease, 'disease_state' => $disease === 13 ? 21 : 8]);
        } elseif (! $on) {
            $rows->each->delete();
        }
        return response()->json(['ok' => true]);
    }

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
            $diagnosis = Diagnosi::where('visit_id', 'LIKE', "%$keyword%")
                ->orWhere('disease', 'LIKE', "%$keyword%")
                ->orWhere('other_disease', 'LIKE', "%$keyword%")
                ->orWhere('term_syphilis', 'LIKE', "%$keyword%")
                ->latest()->paginate($perPage);
        } else {
            $diagnosis = Diagnosi::latest()->paginate($perPage);
        }

        return view('diagnosis.index', compact('diagnosis'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    /**
     * หน้าเพิ่มวินิจฉัย — ต้องโชว์ของที่ลงไว้แล้วใน visit นี้ด้วย
     * ไม่งั้นพยาบาลที่เข้ามารอบสองจะลงซ้ำโดยไม่รู้ตัว (ผู้ใช้แจ้ง 2569-08-05)
     */
    public function create(Request $request)
    {
        $visitId = $request->input('visit', $_GET['visit'] ?? null);

        $existing = $visitId
            ? Diagnosi::where('visit_id', $visitId)->orderBy('id')->get()
            : collect();

        // visit ของ TMC และ TB ใช้ช่อง diagnosis แบบเลือก/พิมพ์เพิ่มเอง (รายการของแต่ละคลินิกใน diagnosis_presets)
        // แทนรายการโรค STI · ไม่มีสถานะของโรค/ICD-10 (ของงาน STI) — คลินิกขอ 2569-10-01
        $clinic = (int) optional(\App\Models\Visit::find($visitId))->clinic;
        $picker = in_array($clinic, [2, 3], true);

        return view('diagnosis.create', [
            'existing' => $existing,
            'isTmc'    => $picker,   // = ใช้ช่องแบบเลือก/พิมพ์ (TMC และ TB)
            'hideState' => $picker,
            'presets'  => $picker ? \App\Models\DiagnosisPreset::namesFor($clinic) : [],
            // รหัสโรคที่มีอยู่แล้ว ใช้ทำป้าย "ลงแล้ว" ในช่องเลือกโรค
            'usedDiseases' => $existing->pluck('disease')->map(fn ($d) => (string) $d)->unique()->values()->all(),
        ]);
    }

    // public function add()
    // {
    //     return view('diagnosis.add');
    // }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    /**
     * วินิจฉัยย้อนหลังของคนไข้ (popup ในหน้าวินิจฉัย) + ดึงของเดิมมาใช้
     * เคสติดตามอาการมักวินิจฉัยเดิม จึงให้ดึงมาแล้วค่อยแก้ ดีกว่าเลือกใหม่ทุกครั้ง
     */
    public function history($visitId)
    {
        $visit = \App\Models\Visit::findOrFail($visitId);

        $past = \App\Models\Visit::where('patient_id', $visit->patient_id)
            ->where('id', '!=', $visit->id)
            ->whereHas('diagnoses')
            ->with('diagnoses')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $already = $this->diagnosisKeys($visit->id);

        return view('diagnosis.history', compact('visit', 'past', 'already'));
    }

    public function copy(Request $request, $visitId)
    {
        $visit = \App\Models\Visit::findOrFail($visitId);
        $page  = $request->input('page', 1);

        $sources = collect();
        if ($request->filled('item')) {
            $one = Diagnosi::find($request->input('item'));
            // ต้องเป็นวินิจฉัยของคนไข้คนเดียวกัน กันยิง id ข้ามคนไข้
            if ($one && optional(\App\Models\Visit::find($one->visit_id))->patient_id === $visit->patient_id) {
                $sources = collect([$one]);
            }
        } elseif ($request->filled('from_visit')) {
            $from = \App\Models\Visit::find($request->input('from_visit'));
            if ($from && $from->patient_id === $visit->patient_id) {
                $sources = Diagnosi::where('visit_id', $from->id)->get();
            }
        }

        $already = $this->diagnosisKeys($visit->id);
        $added = 0; $skipped = 0;

        foreach ($sources as $src) {
            if (in_array($this->diagnosisKey($src), $already, true)) { $skipped++; continue; }

            $new = Diagnosi::create([
                'visit_id'            => $visit->id,
                'disease'             => $src->disease,
                'other_disease'       => $src->other_disease,
                'term_syphilis'       => $src->term_syphilis,
                'disease_state'       => $src->disease_state,
                'disease_state_other' => $src->disease_state_other,
                'egasp_info'          => $src->egasp_info,
                'gf'                  => $src->gf,
            ]);
            // รหัส ICD-10 ตามมาด้วย ไม่งั้นหมอต้องเลือกใหม่ และรายงานที่นับจากรหัสจะขาด (รีวิว 2569-09-30)
            $codes = \App\Models\Icd10Code::codesFor(\App\Models\Diagnosi::icd10Owner(), $src->id);
            if ($codes) { \App\Models\Icd10Code::syncFor(\App\Models\Diagnosi::icd10Owner(), $new->id, $codes); }
            $already[] = $this->diagnosisKey($src);
            $added++;
        }

        $msg = $added
            ? "ดึงวินิจฉัยเดิมมาแล้ว {$added} รายการ" . ($skipped ? " (ข้ามที่ซ้ำ {$skipped} รายการ)" : '')
            : ($skipped ? "วินิจฉัยทั้ง {$skipped} รายการมีอยู่แล้ว ไม่ได้เพิ่มซ้ำ" : 'ไม่พบวินิจฉัยที่จะดึง');

        Alert::success($msg);

        return redirect()->route('visit.edit', ['visit' => $visit->id, 'page' => $page]);
    }

    /** กันซ้ำโดยดูทั้งโรคและรายละเอียดย่อย (ระยะซิฟิลิส/โรคอื่นๆ) ไม่ใช่ดูแค่ disease */
    private function diagnosisKey(Diagnosi $d): string
    {
        return $this->makeDiagnosisKey($d->disease, $d->term_syphilis, $d->other_disease);
    }

    /**
     * คีย์เทียบว่าซ้ำกันไหม — โรค + ระยะซิฟิลิส + ชื่อโรคอื่นๆ
     * ต้อง normalize ก่อนเทียบ ไม่งั้น null กับ 0 กลายเป็นคนละคีย์ทั้งที่ความหมายเดียวกัน
     * (ของเดิมในฐานข้อมูลเก็บ term_syphilis = 0 แต่ฟอร์มที่ไม่ได้เลือกส่งมาเป็น null)
     */
    private function makeDiagnosisKey($disease, $term, $other, $state = false): string
    {
        $parts = [(int) $disease, (int) $term, mb_strtolower(trim((string) $other))];
        // ตอนลงเพิ่มเอง นับสถานะของโรคด้วย — โรคเดียวกันคนละสถานะในวันเดียวกันลงได้
        // เช่น ซิฟิลิสมาฟังผล RPR ครบ 24 เดือน + สิ้นสุดการรักษา (STI ขอ 2569-11-10 · ข้อมูลเดิมมีแบบนี้อยู่แล้ว 10 visit)
        // ส่วน "ดึงวินิจฉัยเดิม" ไม่ส่ง $state มา ยังกันโรคเดียวกันไว้เหมือนเดิม
        if ($state !== false) { $parts[] = (int) $state; }
        return implode('|', $parts);
    }

    private function diagnosisKeys($visitId): array
    {
        return Diagnosi::where('visit_id', $visitId)->get()
            ->map(fn ($d) => $this->diagnosisKey($d))->all();
    }

    /** ติ๊ก/เอาออก "ส่ง 506" ต่อวินิจฉัย (เรียกด้วย JS/AJAX) */
    public function toggle506(Request $request, $id)
    {
        $d = Diagnosi::findOrFail($id);

        if (!$d->is506Reportable()) {
            return response()->json(['ok' => false, 'msg' => 'ไม่ใช่โรคที่รายงาน 506'], 422);
        }
        if ($d->report_506_sent_at) {
            return response()->json(['ok' => false, 'sent' => true, 'msg' => 'เคสนี้ส่ง 506 ไปแล้ว'], 409);
        }

        $d->report_506 = $request->boolean('on');
        $d->save();

        return response()->json(['ok' => true, 'on' => (bool) $d->report_506]);
    }

    /**
     * บันทึกการวินิจฉัย
     *
     * หน้าเพิ่มวินิจฉัยส่งมาเป็นชุด (rows[]) เพราะพยาบาลลงได้หลายโรคในครั้งเดียว
     * ไม่ต้องกด "เพิ่ม" แล้วเด้งกลับหน้า visit ทีละรอบ (2569-08-05)
     * ยังรองรับรูปแบบเดิม (ฟิลด์เดี่ยว) เผื่อมีหน้าอื่นยิงเข้ามา
     */
    public function store(Request $request)
{
    $visitId = $request->input('visit_id');
    $page    = $request->input('page', 1);
    $fields  = ['disease', 'other_disease', 'term_syphilis', 'disease_state', 'disease_state_other', 'egasp_info', 'gf'];

    $rows = $request->input('rows');

    // TMC: เลือก/พิมพ์ diagnosis ได้หลายรายการในช่องเดียว → แตกเป็นหนึ่งแถวต่อรายการ
    // บันทึกเป็นโรค "Other" + ชื่อ เหมือนที่ TMC ลงมาตลอด · ชื่อใหม่เก็บเข้ารายการกลางให้เคสต่อไปเลือกได้ (2569-10-01)
    // tmc_mode ส่งมาเสมอ — ไม่เลือกอะไรเลยต้องได้ข้อความเตือน ไม่ใช่ตกไปสาขา "ฟิลด์เดี่ยว" ที่สร้างแถวว่าง
    if ($request->has('tmc_mode')) {
        $state = null;   // TMC/TB ไม่ใช้ "สถานะของโรค" (ของ STI)
        $pickClinic = (int) optional(\App\Models\Visit::find($visitId))->clinic === 2 ? 2 : 3;   // รายการชื่อแยกตามคลินิก
        $rows = [];
        foreach ((array) $request->input('tmc_dx', []) as $name) {
            $name = \App\Models\DiagnosisPreset::remember($pickClinic, (string) $name);
            if ($name === '') { continue; }
            $rows[] = ['disease' => \App\Models\DiagnosisPreset::OTHER_DISEASE, 'other_disease' => $name, 'disease_state' => $state];
        }
    }

    if (is_array($rows) && (int) optional(\App\Models\Visit::find($visitId))->clinic === 2) {
        foreach ($rows as $k => $row) {
            if (! is_array($row) || array_key_exists('disease', $row) && (string) $row['disease'] !== '' && (int) $row['disease'] !== \App\Models\DiagnosisPreset::OTHER_DISEASE) { continue; }
            $name  = trim((string) ($row['other_disease'] ?? ''));
            $codes = array_values(array_filter((array) ($row['icd10'] ?? [])));
            if ($name === '' && $codes) { $name = implode(', ', $codes); }
            $rows[$k]['disease']       = $name === '' ? '' : \App\Models\DiagnosisPreset::OTHER_DISEASE;
            $rows[$k]['other_disease'] = $name === '' ? '' : \App\Models\DiagnosisPreset::remember(2, $name);
        }
    }

    if (is_array($rows)) {
        // ของที่ลงไว้แล้วใน visit นี้ + ที่เพิ่งเพิ่มในรอบนี้ ใช้กันลงซ้ำทั้งสองทาง (เทียบรวมสถานะของโรค)
        $seen  = Diagnosi::where('visit_id', $visitId)->get()
            ->map(fn ($d) => $this->makeDiagnosisKey($d->disease, $d->term_syphilis, $d->other_disease, $d->disease_state))->all();
        $saved = 0; $dup = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) { continue; }
            // ไม่ได้เลือกโรค = แถวที่เผลอกดเพิ่มแล้วไม่ได้กรอก ข้ามไป ไม่ต้องบันทึกขยะ
            if (trim((string) ($row['disease'] ?? '')) === '') { continue; }

            $data = ['visit_id' => $visitId];
            foreach ($fields as $f) {
                $data[$f] = $row[$f] ?? null;
            }

            // คีย์เดียวกับที่ใช้ตอน "ดึงของเดิม" (Syphilis คนละระยะ = คนละรายการ ไม่ใช่ซ้ำ)
            $key = $this->makeDiagnosisKey($data['disease'], $data['term_syphilis'] ?? null, $data['other_disease'] ?? null, $data['disease_state'] ?? null);
            if (in_array($key, $seen, true)) {
                $dup++;
                // แถวซ้ำแต่เลือกรหัส ICD มาด้วย — เติมรหัสเข้าแถวเดิม ไม่ทิ้งเงียบ ๆ (รีวิว 2569-09-30)
                $codes = array_filter((array) ($row['icd10'] ?? []));
                if ($codes) {
                    $same = Diagnosi::where('visit_id', $visitId)->get()->first(function ($d) use ($key) {
                        return $this->makeDiagnosisKey($d->disease, $d->term_syphilis, $d->other_disease, $d->disease_state) === $key;
                    });
                    if ($same) {
                        $merged = array_values(array_unique(array_merge(\App\Models\Icd10Code::codesFor(\App\Models\Diagnosi::icd10Owner(), $same->id), $codes)));
                        \App\Models\Icd10Code::syncFor(\App\Models\Diagnosi::icd10Owner(), $same->id, $merged);
                    }
                }
                continue;
            }
            $seen[] = $key;

            $dg = Diagnosi::create($data);
            // รหัส ICD-10 ของแถวนี้ เก็บคนละตารางเพราะเลือกได้หลายรหัส
            if (isset($row['icd10'])) {
                \App\Models\Icd10Code::syncFor(\App\Models\Diagnosi::icd10Owner(), $dg->id, (array) $row['icd10']);
            }
            $saved++;
        }

        if ($saved === 0 && $dup === 0) {
            return back()->withInput()->withErrors(['disease' => 'ยังไม่ได้เลือกโรคสักรายการ']);
        }

        $msg = 'บันทึกการวินิจฉัย ' . $saved . ' รายการเรียบร้อยแล้ว';
        if ($dup > 0) { $msg .= ' (ข้ามที่ซ้ำกับของเดิม ' . $dup . ' รายการ)'; }
        Alert::success($msg);
    } else {
        // รูปแบบเดิม: ฟิลด์เดี่ยวชุดเดียว
        Diagnosi::create($request->all());
        Alert::success('Diagnosis added successfully!');
    }

    // Redirect ไปที่หน้า Visit
    return redirect()->route('visit.edit', ['visit' => $visitId, 'page' => $page])
                     ->with('success', 'Diagnosis added successfully.');
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
        $diagnosi = Diagnosi::findOrFail($id);

        return view('diagnosis.show', compact('diagnosi'));
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
        $diagnosi = Diagnosi::findOrFail($id);
        // รายการของ TMC ที่ลงเป็น "Other" + ชื่อ → แก้ด้วยช่องเลือก/พิมพ์เพิ่มเอง แบบหน้าเพิ่ม (2569-10-01)
        // TB ใช้แบบเดียวกัน (ไม่มีรายการโรค STI) — ตัวแปรชื่อ isTmc คงไว้ แต่หมายถึง "หน้าแบบพิมพ์/เลือกชื่อ"
        $clinicOf = (int) optional(\App\Models\Visit::find($diagnosi->visit_id))->clinic;
        $isTmc   = in_array($clinicOf, [2, 3], true)
                   && (int) $diagnosi->disease === \App\Models\DiagnosisPreset::OTHER_DISEASE;
        $presets = $isTmc ? \App\Models\DiagnosisPreset::namesFor($clinicOf) : [];
        $hideState = in_array((int) optional(\App\Models\Visit::find($diagnosi->visit_id))->clinic, [2, 3], true);

        return view('diagnosis.edit', compact('diagnosi', 'isTmc', 'presets', 'hideState'));
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
    
    // ค้นหาข้อมูลที่ต้องการอัปเดต
    $diagnosi = Diagnosi::findOrFail($id);

    // TMC: แก้แค่ชื่อ diagnosis + สถานะ · ชื่อใหม่เก็บเข้ารายการกลาง (2569-10-01)
    if ($request->has('tmc_mode')) {
        $clinicOf = (int) optional(\App\Models\Visit::find($diagnosi->visit_id))->clinic === 2 ? 2 : 3;
        $name = \App\Models\DiagnosisPreset::remember($clinicOf, (string) $request->input('tmc_name', ''));
        if ($name === '') {
            return back()->withInput()->withErrors(['tmc_name' => 'ยังไม่ได้เลือก diagnosis']);
        }
        $requestData = [
            'disease'       => \App\Models\DiagnosisPreset::OTHER_DISEASE,
            'other_disease' => $name,
        ];
    }
    $diagnosi->update($requestData);

    // มีช่อง ICD ในฟอร์มค่อยซิงก์ ไม่งั้นฟอร์มเก่าที่ยิงมาจะลบรหัสทิ้งหมด
    if ($request->has('icd10')) {
        \App\Models\Icd10Code::syncFor(\App\Models\Diagnosi::icd10Owner(), $diagnosi->id, (array) $request->input('icd10', []));
    }

    // ดึงค่า visit_id และ page จาก Request
    $visitId = $request->input('visit_id');
    $page = $request->input('page', 1); // ถ้าไม่มี page จะใช้ค่า default เป็น 1

    // แสดงข้อความแจ้งเตือน
    Alert::success('Diagnosis updated successfully!');

    // Redirect กลับไปที่หน้า Visit
    return redirect()->route('visit.edit', ['visit' => $visitId, 'page' => $page])
                     ->with('success', 'Diagnosis updated successfully.');
}

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy(Request $request,$id)
    {
        $diagnosi = Diagnosi::findOrFail($id);
        $visit_id = $diagnosi->visit_id;
        $label    = $diagnosi->disease();
        Diagnosi::destroy($id);
        $requestData = $request->all();

        // ลบจากหน้าเพิ่มวินิจฉัย -> อยู่หน้าเดิมต่อ จะได้ลบ/เพิ่มรวดเดียวไม่ต้องเดินไปกลับ
        if ($request->input('from') === 'create') {
            Alert::success('ลบ "' . $label . '" แล้ว');
            return redirect()->route('diagnosis.create', [
                'visit' => $visit_id,
                'page'  => $requestData['page'] ?? 1,
            ]);
        }

        // return Visit::next($requestData['page'], $visit_id);
        return redirect()->route('visit.edit', ['visit' => $visit_id, 'page' => $requestData['page'] + 1])->with('success', 'Diagnosis deleted successfully.');

    }
}
