<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\Visit;
use App\Models\VisitMedicine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VisitMedicineController extends Controller
{
    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $perPage = 25;

        if (!empty($keyword)) {
            $visitmedicine = VisitMedicine::where('visit_id', 'LIKE', "%$keyword%")
                ->orWhere('medicine', 'LIKE', "%$keyword%")
                ->orWhere('method', 'LIKE', "%$keyword%")
                ->orWhere('amount', 'LIKE', "%$keyword%")
                ->latest()->paginate($perPage);
        } else {
            $visitmedicine = VisitMedicine::latest()->paginate($perPage);
        }

        return view('visit-medicine.index', compact('visitmedicine'));
    }

    public function create()
    {
        return view('visit-medicine.create');
    }

    /**
     * ยาที่เคยสั่งให้คนไข้คนนี้ (popup ในหน้าสั่งยา)
     * คนไข้ STI ส่วนใหญ่ได้ยาชุดเดิมซ้ำ จึงให้ดึงมาทั้งชุดหรือทีละตัวได้ ไม่ต้องเลือกใหม่ทุกครั้ง
     */
    public function history($visitId)
    {
        $visit = Visit::findOrFail($visitId);

        // visits.date เก็บเป็น พ.ศ. Y-m-d เรียงแบบข้อความได้ตรงตามเวลาจริง
        $past = Visit::where('patient_id', $visit->patient_id)
            ->where('id', '!=', $visit->id)
            ->whereHas('visitMedicines')
            ->with(['visitMedicines'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $medicines = Medicine::pluck('name', 'id');

        // ยาที่มีอยู่แล้วในครั้งนี้ -> ใช้เตือนว่าซ้ำ จะได้ไม่สั่งซ้อน
        $already = VisitMedicine::where('visit_id', $visit->id)->pluck('medicine_id')
            ->map(fn ($m) => (string) $m)->all();

        return view('visit-medicine.history', compact('visit', 'past', 'medicines', 'already'));
    }

    /**
     * คัดลอกยาเดิมมาใส่ visit ปัจจุบัน — ทั้งชุด (from_visit) หรือทีละตัว (item)
     * ข้ามยาที่มีอยู่แล้วเสมอ กันสั่งซ้ำโดยไม่ตั้งใจ
     */
    public function copy(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $page  = $request->input('page', 1);

        $sources = collect();
        if ($request->filled('item')) {
            $one = VisitMedicine::find($request->input('item'));
            // ต้องเป็นยาของคนไข้คนเดียวกันเท่านั้น กันยิง id มั่วข้ามคนไข้
            if ($one && optional(Visit::find($one->visit_id))->patient_id === $visit->patient_id) {
                $sources = collect([$one]);
            }
        } elseif ($request->filled('from_visit')) {
            $from = Visit::find($request->input('from_visit'));
            if ($from && $from->patient_id === $visit->patient_id) {
                $sources = VisitMedicine::where('visit_id', $from->id)->get();
            }
        }

        $already = VisitMedicine::where('visit_id', $visit->id)->pluck('medicine_id')
            ->map(fn ($m) => (string) $m)->all();

        $added = 0;
        $skipped = 0;
        foreach ($sources as $src) {
            if (in_array((string) $src->medicine_id, $already, true)) { $skipped++; continue; }

            VisitMedicine::create([
                'visit_id'    => $visit->id,
                'medicine_id' => $src->medicine_id,
                'dose'        => $src->dose,
                'route'       => $src->route,
                'amount'      => $src->amount,
                'sig'         => $src->sig,
            ]);
            $already[] = (string) $src->medicine_id;
            $added++;
        }

        // ไม่ใส่นัดวัคซีนลงแผนให้เองแล้ว — ตั้งแต่ 2569-10-01 แพทย์วางแผนนัดฉีดวัคซีนเองทั้งหมด
        // (ระบบเติมให้แล้วหมอต้องมาไล่ลบ/แก้ทีหลัง) เก็บ planNextVaccines() ไว้เผื่อกลับมาใช้
        // if ($added) { $this->planNextVaccines($visit, $sources->pluck('medicine_id')->all()); }

        // เดินตามแบบเดียวกับ store(): ถือว่าคนที่ดึงยาเข้ามาคือผู้สั่งยา
        if ($added && Auth::check()) {
            $visit->pharmacy_by = Auth::id();
            $visit->handled_by  = Auth::id();
            $visit->save();
        }

        $msg = $added
            ? "ดึงยาเดิมมาแล้ว {$added} รายการ" . ($skipped ? " (ข้ามที่ซ้ำ {$skipped} รายการ)" : '')
            : ($skipped ? "ยาทั้ง {$skipped} รายการมีอยู่แล้ว ไม่ได้เพิ่มซ้ำ" : 'ไม่พบยาที่จะดึง');

        flash($msg, $added ? 'success' : 'warning');

        return redirect()->route('visit.edit', ['visit' => $visit->id, 'page' => $page]);
    }

    public function add(Request $request)
    {
        $visit = Visit::with(['patient2', 'visitMedicines.medicine'])->find($request->get('visit'));
        $medicines = Medicine::orderBy('name')->get();
        // Lot ที่ยังเหลือในคลัง ให้เลือกใส่ช่อง Lot (TMC 2569-10-09) — ล็อตใหม่/หมดอายุใกล้สุดขึ้นก่อน
        $lotsByMed = \App\Models\MedicineLot::where('quantity_remaining', '>', 0)
            ->orderBy('expiry_date')->get(['medicine_id', 'lot_number', 'expiry_date'])
            ->groupBy('medicine_id')->map(fn ($g) => $g->pluck('lot_number')->filter()->unique()->values());
        // + Lot ที่พยาบาลเคยพิมพ์เอง (ห้องคลังยายังไม่ลงล็อต — TMC ขอ 2569-10-14) ต่อท้ายล็อตในคลัง
        foreach (\App\Models\VisitVaccine::recentLots() as $__mid => $__lots) {
            $lotsByMed[$__mid] = collect($lotsByMed[$__mid] ?? [])->merge($__lots)->unique()->values();
        }
        // จำนวนวันที่จ่ายยา = ถึงวันนัดครั้งถัดไปของ visit นี้ (คลินิกขอ 2569-10-12) — ไม่มีนัด = ปล่อยว่างแบบเดิม
        $apptDays = null; $apptDateTh = null;
        if ($visit) {
            $next = \App\Models\VisitAppointment::open()->where('visit_id', $visit->id)
                ->where('appointment_date', '>', \App\Models\VisitAppointment::todayBe())
                ->orderBy('appointment_date')->value('appointment_date');
            if ($next && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $next, $m)) {
                $d = \Carbon\Carbon::create((int) $m[1] - 543, (int) $m[2], (int) $m[3])->startOfDay();
                $apptDays = (int) \Carbon\Carbon::today()->diffInDays($d, false);
                $apptDateTh = ((int) $m[3]) . '/' . ((int) $m[2]) . '/' . $m[1];
                if ($apptDays < 1) { $apptDays = null; }
            }
        }
        return view('visit-medicine.add', compact('visit', 'medicines', 'lotsByMed', 'apptDays', 'apptDateTh'));
    }

    public function store(Request $request)
    {
        $requestData = $request->all();

        // บันทึกรายการยา (ตามโค้ดเดิม)
        if (is_array($requestData['medicine_id'])) {
            foreach ($requestData['medicine_id'] as $key => $value) {
                if ($requestData['medicine_id'][$key]) {
                    $__lot = trim((string) ($requestData['lot_number'][$key] ?? ''));
                    VisitMedicine::create([
                        'visit_id'    => $requestData['visit_id'],
                        'medicine_id' => $requestData['medicine_id'][$key],
                    ] + (VisitMedicine::lotReady() ? ['lot_number' => $__lot === '' ? null : mb_substr($__lot, 0, 50)] : []) + [
                        'dose'        => $requestData['dose'][$key],
                        'route'       => $requestData['route'][$key],
                        'amount'      => $requestData['amount'][$key],
                        'sig'         => \App\Support\DrugSig::clean($requestData['sig'][$key] ?? null),
                    ]);
                }
            }
        } else {
            // sig ต้องผ่าน clean() เหมือนสาขาหลายรายการ — ค่าดิบที่ยิงมาตรง ๆ ทำหน้ายาพัง 500 (รีวิว 2569-09-30)
            if (array_key_exists('sig', $requestData)) {
                $requestData['sig'] = \App\Support\DrugSig::clean($requestData['sig']);
            }
            VisitMedicine::create($requestData);
        }

        // วัคซีนของ TMC: เดิมใส่นัดเข็มถัดไปลงแผนนัดฉีดวัคซีนให้เอง (2569-09-30)
        // ปิดตั้งแต่ 2569-10-01 — แพทย์วางแผนนัดเองทั้งหมด ไม่ให้ระบบแทรกแถวที่หมอไม่ได้ตั้งใจ
        // $this->planNextVaccines(Visit::find($requestData['visit_id']), (array) ($requestData['medicine_id'] ?? []));

        // ✅ อัปเดตแพทย์ผู้สั่งยาในตาราง visits
        if (Auth::check()) {
            $visit = Visit::find($requestData['visit_id']);
            if ($visit) {
                $visit->pharmacy_by = Auth::id();      // แพทย์ผู้สั่งยา
                $visit->handled_by  = Auth::id();      // ผู้แก้ไขล่าสุด
                $visit->save();
            }
        }

        flash('VisitMedicine added!', 'success');

        return redirect()->route('visit.edit', [
            'visit' => $requestData['visit_id'],
            'page'  => $requestData['page']
        ])->with('success', 'VisitMedicine added successfully.');
    }

    /**
     * [ไม่ได้เรียกใช้แล้วตั้งแต่ 2569-10-01 — แพทย์วางแผนนัดฉีดวัคซีนเอง เก็บไว้เผื่อคลินิกอยากกลับมาใช้]
     * หมอสั่งวัคซีน -> นัดเข็มถัดไปลง "แผนนัดฉีดวัคซีน" ของ visit นี้ (เฉพาะคลินิก TMC)
     * ตารางระยะห่างอยู่ที่หน้า "ตารางนัดวัคซีน" · นับเข็มจากประวัติฉีดรวมที่อื่น · เลี่ยงวันหยุด
     * ล้มเหลวตรงนี้ต้องไม่ทำให้การสั่งยาพัง — แค่ไม่ได้นัดให้ หมอลงเองได้
     */
    private function planNextVaccines(?Visit $visit, array $medicineIds): void
    {
        if (! $visit || (int) $visit->clinic !== 3) { return; }
        $notes = [];
        try {
            $meds = Medicine::whereIn('id', array_filter($medicineIds))->where('is_vaccine', 1)->get();
            foreach ($meds as $med) {
                if ($msg = \App\Services\VaccineSchedule::addToPlan($visit->fresh(), $med)) { $notes[] = $msg; }
            }
        } catch (\Throwable $e) {
            report($e);
        }
        if ($notes) {
            flash('ใส่นัดวัคซีนลงแผนนัดฉีดวัคซีนแล้ว: ' . implode(' · ', $notes), 'info');
        }
    }

    /** [ไม่ได้เรียกใช้แล้วตั้งแต่ 2569-10-01 — ดู planNextVaccines]
     *  ลบ/เปลี่ยนใบสั่งวัคซีน → เอานัดเข็มถัดไปที่ระบบใส่ไว้ออกจากแผน (TMC เท่านั้น) — พังตรงนี้ต้องไม่ทำให้การลบพัง */
    private function unplanVaccine(?Visit $visit, int $medicineId): void
    {
        if (! $visit || (int) $visit->clinic !== 3) { return; }
        try {
            $med = Medicine::where('id', $medicineId)->where('is_vaccine', 1)->first();
            if ($med && ($msg = \App\Services\VaccineSchedule::removeFromPlan($visit, $med))) {
                flash('เอานัด ' . $msg . ' ออกจากแผนนัดฉีดวัคซีนแล้ว', 'info');
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function show($id)
    {
        $visitmedicine = VisitMedicine::findOrFail($id);
        return view('visit-medicine.show', compact('visitmedicine'));
    }

    public function edit($id)
    {
        $visitmedicine = VisitMedicine::findOrFail($id);
        return view('visit-medicine.edit', compact('visitmedicine'));
    }

    public function update(Request $request, $id)
    {
        $requestData = $request->all();

        $visitMedicine = VisitMedicine::findOrFail($id);
        // ฟอร์มแก้ไขเก่า (backend) ไม่มีช่องวิธีใช้ — ไม่ได้ส่งมา = คงของเดิม
        if ($request->has('sig')) {
            $requestData['sig'] = \App\Support\DrugSig::clean($request->input('sig'));
        }
        $oldMedicineId = (int) $visitMedicine->medicine_id;
        if (array_key_exists('lot_number', $requestData)) {
            $__lot = trim((string) $requestData['lot_number']);
            $requestData['lot_number'] = $__lot === '' ? null : mb_substr($__lot, 0, 50);
            if (! VisitMedicine::lotReady()) { unset($requestData['lot_number']); }
        }
        $visitMedicine->update($requestData);

        // เดิม: เปลี่ยนตัววัคซีน = เอานัดของตัวเดิมออกจากแผน แล้วนัดตัวใหม่แทน
        // ปิดตั้งแต่ 2569-10-01 — แพทย์วางแผนนัดฉีดวัคซีนเอง ระบบห้ามแก้แผนที่หมอกรอกไว้
        // if ($oldMedicineId !== (int) $visitMedicine->medicine_id) {
        //     $this->unplanVaccine(Visit::find($visitMedicine->visit_id), $oldMedicineId);
        //     $this->planNextVaccines(Visit::find($visitMedicine->visit_id), [$visitMedicine->medicine_id]);
        // }

        // ✅ ถ้ามีการแก้ไขใบสั่งยาให้ถือว่าแพทย์สั่ง/แก้ไข ย้ำบันทึก pharmacy_by
        if (Auth::check()) {
            $visit = Visit::find($visitMedicine->visit_id);
            if ($visit) {
                $visit->pharmacy_by = Auth::id();
                $visit->handled_by  = Auth::id();
                $visit->save();
            }
        }

        return redirect()->route('visit.edit', [
            'visit' => $visitMedicine->visit_id,
            'page'  => $requestData['page']
        ])->with('success', 'VisitMedicine updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $visitMedicine = VisitMedicine::findOrFail($id);
        $visitId = $visitMedicine->visit_id;

        VisitMedicine::destroy($id);
        // ปิดตั้งแต่ 2569-10-01 — แพทย์วางแผนนัดฉีดวัคซีนเอง ลบใบสั่งแล้วต้องไม่ไปลบแถวที่หมอกรอกไว้ในแผน
        // $this->unplanVaccine(Visit::find($visitId), (int) $visitMedicine->medicine_id);

        // (ปกติการลบไม่ต้องแตะ pharmacy_by นะครับ เลยไม่อัปเดตที่นี่)
        return redirect()->route('visit.edit', [
            'visit' => $visitId,
            'page'  => $request->page
        ])->with('success', 'VisitMedicine deleted successfully.');
    }
}
