<?php

namespace App\Http\Controllers;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\VisitFollowup;
use Illuminate\Http\Request;

class VisitFollowupController extends Controller
{
    // ดึงข้อมูลการติดตามทั้งหมดของ visit_id
    public function index($visit_id)
    {
        $followups = VisitFollowup::where('visit_id', $visit_id)->get();
        return response()->json($followups);
    }

    public function store(Request $request)
{
    $request->validate([
        'method' => 'required',
        'followup_count' => 'required|integer|min:1',
        'status' => 'required',
        'visit_id' => 'required|exists:visits,id',
        'patient_id' => 'required|exists:patients,id',
        'remark' => 'nullable|string',
        'phone_changed' => 'nullable|in:0,1',
    ]);

    // สร้างการติดตาม
    VisitFollowup::create($request->only([
        'visit_id', 'method', 'followup_count', 'status', 'remark'
    ]));

    // ถ้ามีการติ๊กให้แจ้งเปลี่ยนเบอร์
    if ($request->phone_changed == 1) {
        $patient = Patient::find($request->patient_id);
        if ($patient) {
            $patient->phone_changed = 1;
            $patient->save();
        }
    }

    // เพิ่มจากบัตร TB 01 → กลับไปที่กล่องการติดตามในบัตร (2569-10-01)
    if ($request->filled('back_tb')) {
        return redirect()->to(route('tb.show', (int) $request->back_tb) . '#tb-followup')
            ->with('success', 'การติดตามผู้ป่วยได้ถูกบันทึกเรียบร้อย');
    }
    return redirect()
        ->route('visit.edit', ['visit' => $request->visit_id, 'page' => $request->get('page', 1)])
        ->with('success', 'การติดตามผู้ป่วยได้ถูกบันทึกเรียบร้อย');
}



    public function destroy($id, Request $request)
{
    $followup = VisitFollowup::findOrFail($id);
    $followup->delete();

    return response()->json(['success' => true]);
}



    public function create($visitId)
    {
        $visit = Visit::findOrFail($visitId);
        return view('followups.create', compact('visit'));
    }
    public function edit($id)
{
    $followup = VisitFollowup::findOrFail($id);
    $visit = $followup->visit;

    return view('followups.edit', compact('followup', 'visit'));
}

public function update(Request $request, $id)
{
    $request->validate([
        'method' => 'required',
        'followup_count' => 'required|integer|min:1',
        'status' => 'required',
        'remark' => 'nullable|string',
        'phone_changed' => 'nullable|in:0,1',
    ]);

    $followup = VisitFollowup::findOrFail($id);
    $followup->update($request->only(['method', 'followup_count', 'status', 'remark']));

    // อัปเดต phone_changed ของผู้ป่วย
    if ($followup->visit && $followup->visit->patient2) {
        $followup->visit->patient2->update([
            'phone_changed' => $request->input('phone_changed', 0)
        ]);
    }

    if ($request->filled('back_tb')) {
        return redirect()->to(route('tb.show', (int) $request->back_tb) . '#tb-followup')
            ->with('success', 'อัปเดตข้อมูลการติดตามผู้ป่วยเรียบร้อยแล้ว');
    }
    return redirect()
        ->route('visit.edit', ['visit' => $followup->visit_id, 'page' => $request->get('page', 1)])
        ->with('success', 'อัปเดตข้อมูลการติดตามผู้ป่วยเรียบร้อยแล้ว');
}



}
