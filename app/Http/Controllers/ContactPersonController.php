<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests;

use App\Models\ContactPerson;
use App\Models\Visit;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class ContactPersonController extends Controller
{
    /**
     * คู่สัมผัสที่เคยบันทึกไว้ของคนไข้รายนี้ (popup ในหน้าคู่นอน/คู่สัมผัส)
     * คนไข้ส่วนใหญ่เป็นคู่เดิม พยาบาลขอให้ดึงของครั้งก่อนมาใช้ ไม่ต้องคีย์ใหม่ทุกครั้ง
     * (ทำตามแบบเดียวกับ "ยาเดิม" ในหน้าสั่งยา)
     */
    public function history($visitId)
    {
        $visit = Visit::findOrFail($visitId);

        // visits.date เก็บเป็น พ.ศ. Y-m-d เรียงแบบข้อความได้ตรงตามเวลาจริง
        $past = Visit::where('patient_id', $visit->patient_id)
            ->where('id', '!=', $visit->id)
            ->whereHas('contactPersons')
            ->with(['contactPersons'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        // ชื่อที่มีอยู่แล้วในครั้งนี้ -> ใช้เตือนว่าซ้ำ จะได้ไม่คีย์ซ้อน (เทียบแบบตัดช่องว่าง/ไม่สนตัวพิมพ์)
        $already = ContactPerson::where('visit_id', $visit->id)
            ->pluck('name_surname')
            ->map(fn ($n) => mb_strtolower(trim((string) $n)))
            ->filter()
            ->all();

        return view('visit.contact_history', compact('visit', 'past', 'already'));
    }

    /**
     * คัดลอกคู่สัมผัสเดิมมาใส่ visit ปัจจุบัน — ทั้งชุด (from_visit) หรือทีละคน (item)
     * ข้ามชื่อที่มีอยู่แล้วเสมอ กันบันทึกซ้ำโดยไม่ตั้งใจ
     */
    public function copy(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $page  = $request->input('page', 1);

        $sources = collect();
        if ($request->filled('item')) {
            $one = ContactPerson::find($request->input('item'));
            // ต้องเป็นคู่สัมผัสของคนไข้คนเดียวกันเท่านั้น กันยิง id มั่วข้ามคนไข้
            if ($one && optional(Visit::find($one->visit_id))->patient_id === $visit->patient_id) {
                $sources = collect([$one]);
            }
        } elseif ($request->filled('from_visit')) {
            $from = Visit::find($request->input('from_visit'));
            if ($from && $from->patient_id === $visit->patient_id) {
                $sources = ContactPerson::where('visit_id', $from->id)->get();
            }
        }

        $already = ContactPerson::where('visit_id', $visit->id)
            ->pluck('name_surname')
            ->map(fn ($n) => mb_strtolower(trim((string) $n)))
            ->filter()
            ->all();

        // ช่องที่คัดลอกได้ = ทุกช่องใน fillable ยกเว้น visit_id (ตั้งใหม่) และ date
        //
        // ตั้งใจ "ไม่" คัดลอกช่อง date เพราะมันไม่ใช่วันที่มาตรวจ แต่เป็นช่องกรอกอิสระว่า
        // สัมผัสกันครั้งล่าสุดเมื่อไหร่ (ค่าจริงในฐานเป็นแบบ "2 วัน", "1 เดือน", "ก.ค.69")
        // ถ้าลอกมาจากครั้งก่อน ค่าจะกลายเป็นข้อมูลเท็จทันที — ปล่อยว่างให้พยาบาลกรอกใหม่ปลอดภัยกว่า
        $fields = array_values(array_diff((new ContactPerson)->getFillable(), ['visit_id', 'date']));

        $added = 0;
        $skipped = 0;
        foreach ($sources as $src) {
            $key = mb_strtolower(trim((string) $src->name_surname));
            if ($key !== '' && in_array($key, $already, true)) { $skipped++; continue; }

            $data = ['visit_id' => $visit->id, 'date' => null];
            foreach ($fields as $f) { $data[$f] = $src->{$f}; }
            ContactPerson::create($data);

            if ($key !== '') { $already[] = $key; }
            $added++;
        }

        $msg = $added
            ? "ดึงคู่สัมผัสเดิมมาแล้ว {$added} ราย" . ($skipped ? " (ข้ามที่ซ้ำ {$skipped} ราย)" : '')
            : ($skipped ? "คู่สัมผัสทั้ง {$skipped} รายมีอยู่แล้ว ไม่ได้เพิ่มซ้ำ" : 'ไม่พบคู่สัมผัสที่จะดึง');

        flash($msg, $added ? 'success' : 'warning');

        return redirect()->route('visit.edit', ['visit' => $visit->id, 'page' => $page]);
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
            $contactperson = ContactPerson::where('date', 'LIKE', "%$keyword%")
                ->orWhere('name_surname', 'LIKE', "%$keyword%")
                ->orWhere('type', 'LIKE', "%$keyword%")
                ->orWhere('method', 'LIKE', "%$keyword%")
                ->orWhere('condom', 'LIKE', "%$keyword%")
                ->latest()->paginate($perPage);
        }
        $cpsex = $request->get('cpsex');
        if (!empty($cpsex)) {
            $query = $query->where('cpsex', $cpsex);
        } else {
            $contactperson = ContactPerson::latest()->paginate($perPage);
        }

        return view('contact-person.index', compact('contactperson'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('contact-person.create');
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
        $requestData = ContactPerson::format2DB($requestData);
        ContactPerson::create($requestData);
        flash('ContactPerson added!','success');
        Alert::success('ContactPerson updated!');
        echo '<script type="text/javascript">'
               , 'history.go(-2);'
               , '</script>';
        // return Visit::next($requestData['page'], $requestData['visit_id']);
        // return redirect(route('visit.edit',$requestData['visit_id']))->with('flash_message', 'ContactPerson added!');
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
        $contactperson = ContactPerson::findOrFail($id);

        return view('contact-person.show', compact('contactperson'));
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
        $contactperson = ContactPerson::findOrFail($id);
        $contactperson->formatDBBack();
        return view('contact-person.edit', compact('contactperson'));
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
        
        $contactperson = ContactPerson::findOrFail($id);
        $contactperson->clearCheckBox();
        $requestData = ContactPerson::format2DB($requestData);
        $contactperson->update($requestData);
        flash('ContactPerson updated!','success');
        Alert::success('ContactPerson updated!');
        echo '<script type="text/javascript">'
               , 'history.go(-2);'
               , '</script>';
        // return redirect('backend/contact-person')->with('flash_message', 'ContactPerson updated!');
        // return redirect(route('visit.edit',$requestData['visit_id']))->with('flash_message', 'ContactPerson updated!');
        // return Visit::next($requestData['page'], $requestData['visit_id']);
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
        $contactperson = ContactPerson::findOrFail($id);
        $visit_id = $contactperson->visit_id;
        $name = trim((string) $contactperson->name_surname);

        ContactPerson::destroy($id);

        // เดิมใช้ echo history.go(-1) ซึ่งเป็นการ "ถอยกลับ" ไปหน้าที่เบราว์เซอร์แคชไว้ก่อนลบ
        // ตารางเลยยังโชว์คนที่ลบไปแล้ว เหมือนกดแล้วไม่มีอะไรเกิดขึ้น (พยาบาลแจ้ง 2569-09-08)
        // เปลี่ยนเป็น redirect กลับหน้าตรวจจริง ๆ ให้โหลดข้อมูลใหม่ทุกครั้ง
        $msg = 'ลบคู่สัมผัส' . ($name !== '' ? ' "' . $name . '"' : '') . ' เรียบร้อยแล้ว';
        flash($msg, 'success');

        return redirect()->route('visit.edit', [
            'visit' => $visit_id,
            'page'  => $request->input('page', 1),
        ])->with('flash_message', $msg);
    }
}
