<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\Visit;
use App\Models\VisitVaccine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * บันทึกการฉีดวัคซีน — คลินิก TMC (เวชศาสตร์การเดินทาง)
 *
 * แยกจาก visit_medicines เพราะใบรับรองการฉีดวัคซีนต้องมี lot / ผู้ผลิต / เข็มที่ /
 * ตำแหน่งที่ฉีด ซึ่ง visit_medicines เก็บไม่ได้ (มีแค่ dose/route/amount)
 */
class VisitVaccineController extends Controller
{
    /** แพทย์เวชศาสตร์การเดินทาง (เลขใบอนุญาต => ชื่ออังกฤษตามแบบฟอร์มเดิม) — ใบรับรองวัคซีนเติมชื่อ + "Travel Medicine Physician" ให้เฉพาะคนในรายการนี้ */
    private const TRAVEL_MED_DOCTORS = ['53410' => 'Nisachon Jumroonrat, M.D.'];

    /** เพิ่มการฉีดวัคซีนเข้า visit */
    public function store(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $page  = $request->input('page', 1);

        $data = $request->validate([
            'medicine_id'     => ['nullable', 'integer', 'exists:medicines,id'],
            'name'            => ['nullable', 'string', 'max:255'],   // เผื่อวัคซีนที่ยังไม่มีในแคตตาล็อก
            'lot_number'      => ['nullable', 'string', 'max:255'],
            'manufacturer'    => ['nullable', 'string', 'max:255'],
            'dose_number'     => ['nullable', 'integer', 'min:1', 'max:20'],
            'site'            => ['nullable', 'string', 'max:255'],
            'route'           => ['nullable', 'string', 'in:' . implode(',', array_keys(VisitVaccine::$routeOption))],
            'vaccinated_date' => ['nullable', 'string', 'max:20'],
            'vaccinated_time' => ['nullable', 'string', 'max:10'],
            'next_due_date'   => ['nullable', 'string', 'max:20'],
            'source'          => ['nullable', 'integer', 'in:1,2,3'],
            'note'            => ['nullable', 'string'],
        ]);

        // ตัวอ่านวันที่แบบเข้มงวดเดียวกับงาน TB (คลินิกขอ 2569-10-05) — เดิม toDbDate รับวันไม่มีจริง/ปีมั่วได้
        // ค่าที่ระบบเติมเอง (Y-m-d) ไม่ผ่านตรงนี้ · ปี ค.ศ. ที่พิมพ์มา = แปลงเป็น พ.ศ. ให้ · วันที่ฉีดห้ามเป็นวันอนาคต
        // จ่ายให้ไปทานเอง: วันที่ = วันที่นัดให้ทาน เป็นวันในอนาคตได้
        $takeHome = (int) ($data['source'] ?? 0) === VisitVaccine::TAKE_HOME;
        foreach (['vaccinated_date' => ['วันที่ฉีด', ! $takeHome], 'next_due_date' => ['นัดเข็มถัดไป', false]] as $f => [$label, $past]) {
            $raw = trim((string) ($data[$f] ?? ''));
            if ($raw === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) { continue; }
            $r = \App\Support\ThaiDate::parse($raw, ['notFuture' => $past]);
            if (! $r['ok']) {
                $msg = $label . ': ' . $r['error'];
                return $request->wantsJson() ? response()->json(['message' => $msg], 422) : back()->with('flash_message', $msg);
            }
            $data[$f] = \App\Support\ThaiDate::show($r['be']);
        }

        $med  = !empty($data['medicine_id']) ? Medicine::find($data['medicine_id']) : null;
        $name = $med->name ?? trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return back()->with('flash_message', 'กรุณาเลือกวัคซีน');
        }

        $source = (int) ($data['source'] ?? VisitVaccine::HERE);

        // ฉีดที่นี่แต่ไม่ได้กรอกวันที่ฉีด + หมอสั่งวัคซีนตัวนี้ใน visit นี้ -> ใช้วันที่หมอสั่ง (คลินิกขอ 2569-09-30)
        if ($source === VisitVaccine::HERE && $med && empty($data['vaccinated_date'])) {
            $ordered = \App\Models\VisitMedicine::where('visit_id', $visit->id)->where('medicine_id', $med->id)->min('created_at');
            if ($ordered) { $data['vaccinated_date'] = \Carbon\Carbon::parse($ordered)->format('Y-m-d'); }
        }

        // ฉีดที่นี่แต่ไม่ได้กรอกเข็มที่/นัดเข็มถัดไป -> คิดจากตารางนัดวัคซีนให้ (2569-09-30)
        // ต้องคิดก่อนสร้างแถวใหม่ ไม่งั้นเข็มนี้จะถูกนับเป็นประวัติของตัวเอง
        if (VisitVaccine::isGiven($source) && $med && (empty($data['next_due_date']) || empty($data['dose_number']))) {
            // นับจากวันที่ฉีดจริงของเข็มนี้ (อาจเป็นวันที่หมอสั่งย้อนไป) ไม่ใช่วันที่กดบันทึก
            $shotDay = VisitVaccine::toDbDate($data['vaccinated_date'] ?? null);
            // ไปทานเองลงใน visit เดียวกับเข็มก่อนหน้า (มารับวันเดียวกัน) — ต้องนับเข็มของ visit นี้ด้วย ไม่งั้นได้เข็ม 1 ซ้ำ
            $nx = \App\Services\VaccineSchedule::next((int) $visit->patient_id, $med,
                $source === VisitVaccine::TAKE_HOME ? null : (int) $visit->id,
                $shotDay ? \Carbon\Carbon::parse($shotDay) : null);
            if (empty($data['dose_number'])) { $data['dose_number'] = $nx['dose_now']; }
            if (empty($data['next_due_date']) && $nx['date']) { $data['next_due_date'] = $nx['date']->format('Y-m-d'); }
        }

        // ตัดสต็อกเฉพาะเข็มที่ฉีดที่นี่จริง — ประวัติเดิมจากที่อื่นไม่ได้ใช้ของเรา
        // ตัดที่นี่ (ไม่ใช่ที่ห้องยา) เพราะวัคซีน TMC ฉีดในห้อง 7 เอง
        $lotId   = null;
        $lotUsed = null;
        $short   = 0;
        // จ่ายให้ไปทานเอง = ของออกจากคลังวันนี้ ตัดเหมือนฉีดที่นี่
        if (VisitVaccine::isGiven($source) && $med) {
            $res = $med->deductFromLots(1, [
                'visit_id' => $visit->id,
                'note'     => $source === VisitVaccine::TAKE_HOME ? 'จ่ายวัคซีนชนิดกินให้ไปทานเอง TMC' : 'ฉีดวัคซีน TMC',
            ]);
            $short = $res['shortfall'];
            if (!empty($res['lots'])) {
                $lotId   = $res['lots'][0]['lot_id'];
                $lotUsed = $res['lots'][0]['lot_number'];
            }
        }

        VisitVaccine::create([
            'patient_id'  => $visit->patient_id,
            // ประวัติเดิมที่ฉีดมาจากที่อื่น ไม่ผูกกับ visit ครั้งนี้ เพราะไม่ได้ฉีดในครั้งนี้จริง
            'visit_id'    => $source === VisitVaccine::HISTORY ? null : $visit->id,
            'medicine_id'     => $med->id ?? null,
            'medicine_lot_id' => $lotId,
            'name'            => $name,
            // nullable ใน validate = ไม่ส่งมาก็ไม่มีคีย์ ต้อง ?? กันก่อนเสมอ
            // ถ้าพยาบาลไม่ได้พิมพ์ lot เอง ให้ใช้เลข lot ที่ระบบตัดออกจากคลังจริง
            'lot_number'      => ($data['lot_number'] ?? null) ?: $lotUsed,
            'manufacturer'    => $data['manufacturer'] ?? null,
            'dose_number'     => $data['dose_number']  ?? null,
            'site'            => $data['site']         ?? null,
            // ไม่ได้เลือกในหน้าจอ -> ใช้ค่าตั้งต้นของวัคซีนตัวนั้น (แปลง "IM stat"/"im" เป็นรหัสมาตรฐานก่อน)
            'route'           => ($data['route'] ?? null) ?: VisitVaccine::normalizeRoute($med->route ?? null),
            // ต้องแปลง d/m/Y (พ.ศ.) -> Y-m-d (ค.ศ.) ไม่งั้น MySQL เก็บเป็น 0000-00-00
            'vaccinated_date' => VisitVaccine::toDbDate($data['vaccinated_date'] ?? null) ?: now()->format('Y-m-d'),
            'vaccinated_time' => ($data['vaccinated_time'] ?? null) ?: now()->format('H:i'),
            'next_due_date'   => VisitVaccine::toDbDate($data['next_due_date'] ?? null),
            'source'          => $source,
            'injected_by'     => Auth::id(),
            'note'            => $data['note'] ?? null,
        ]);

        $this->syncBilling($visit);

        // เด้งกลับมาที่โซนวัคซีน (#tmcVaccine) ไม่กระโดดขึ้นบนสุด
        $back = route('visit.edit', ['visit' => $visit->id, 'page' => $page]) . '#tmcVaccine';
        if ($short > 0) {
            // ไม่บล็อกการฉีด (ของจริงฉีดไปแล้ว) แค่บอกให้รู้ว่าคลังไม่ตรง
            return redirect()->to($back)
                ->with('flash_message', "บันทึกแล้ว แต่สต็อก {$name} ไม่พอตัด (ขาด {$short}) กรุณาตรวจสอบคลัง");
        }

        return redirect()->to($back);
    }

    /**
     * อัปเดตบิลของ visit ให้ตรงกับรายการวัคซีนล่าสุด
     * ยอดเงินคิดจาก billing_items เป็นแหล่งความจริงเดียว (ดู Billing::recalcTotals)
     */
    private function syncBilling(Visit $visit): void
    {
        $billing = \App\Models\Billing::firstOrCreate(['visit_id' => $visit->id]);

        if (!$billing->bill_no) { $billing->bill_no = \App\Models\Billing::generateBillNo(); }
        if (!$billing->user_id) { $billing->user_id = Auth::id(); }
        $billing->save();

        $billing->syncVaccineItems();
        $billing->recalcTotals();
    }

    /**
     * ใบรับรองการฉีดวัคซีน (ภาษาอังกฤษ) — IMMUNIZATION CERTIFICATE เต็มหน้า A4
     * ทำตามแบบฟอร์มที่ TMC ใช้อยู่ (ไฟล์ Word ตัวอย่าง 2569-11-10): ตารางวัคซีน × เข็มที่ 1st–4th · แพทย์ลงนาม + เลขใบอนุญาต
     *
     * เข็มที่ฉีดที่อื่น (source = HISTORY) ขึ้นในตารางด้วย เพราะแพทย์ตรวจหลักฐานแล้ว (verified) — แต่ติด * กำกับ
     * ไม่งั้นอ่านแล้วเหมือนศูนย์เป็นผู้ฉีดเอง
     */
    public function certificate(Request $request, $visitId)
    {
        return view('visit.vaccine_certificate', $this->certificateData($visitId));
    }

    /**
     * ใบรับรองเดียวกันเป็นไฟล์ Word (.docx) — วัคซีนเยอะจนล้นหน้า / อยากแก้ถ้อยคำ ให้โหลดไปแก้ต่อใน Word (TMC ขอ 2569-11-10)
     * แพทย์ที่เลือกบนหน้าจอส่งมาใน ?doctor= · ฟอนต์ TH Sarabun PSK ตามแบบฟอร์ม Word เดิมของคลินิก
     */
    public function certificateDocx(Request $request, $visitId)
    {
        $d = $this->certificateData($visitId);
        $doc = collect($d['doctors'])->firstWhere('id', (int) $request->query('doctor'));
        $file = \App\Support\ImmunizationDocx::build($d, $doc, public_path('images/logo-กรม-2566 png.png'));
        $hn = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($d['patient']->code ?? $d['visit']->id));
        return response()->download($file, 'Immunization_Certificate_' . $hn . '.docx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    private function certificateData($visitId): array
    {
        $visit   = Visit::with('patient2')->findOrFail($visitId);
        $patient = $visit->patient2;

        $shots = VisitVaccine::where('patient_id', $visit->patient_id)
            ->orderByRaw('vaccinated_date IS NULL, vaccinated_date ASC, id ASC')
            ->get();

        // จัดเป็นแถวละวัคซีน คอลัมน์ละเข็ม — ใช้เลขเข็มที่บันทึกไว้ (คนไข้อาจมาฉีดเข็ม 2 ที่นี่ เข็ม 1 ที่อื่นไม่ได้ลง)
        // ไม่มีเลขเข็ม/ช่องซ้ำ = ช่องว่างถัดไป · ชื่อใช้ส่วนภาษาอังกฤษหลัง " / " (ชื่อในระบบเป็น "ไทย / English")
        $rows = [];
        foreach ($shots as $s) {
            $name = VisitVaccine::certName($s->name);   // "ชื่อวัคซีน (ยี่ห้อ)"
            $key = mb_strtolower($name);
            $rows[$key] ??= ['name' => $name, 'doses' => []];
            $n = (int) $s->dose_number;
            if ($n < 1 || $n > 10 || isset($rows[$key]['doses'][$n])) {
                $n = 1;
                while (isset($rows[$key]['doses'][$n])) { $n++; }
            }
            $rows[$key]['doses'][$n] = [
                'date' => $s->vaccinated_date ? $s->vaccinated_date->format('d M Y') : 'date n/a',
                // เข็มจากที่อื่นไม่ติด * แล้ว (TMC ขอ 2569-11-10 — ไม่อยากให้ใบดูต่างชั้น)
                'mark' => (int) $s->source === VisitVaccine::TAKE_HOME ? '†' : '',
            ];
        }
        $doseCols = 4;
        foreach ($rows as $r) { $doseCols = max($doseCols, ...array_keys($r['doses'])); }

        // แถวเรียง A–Z ตามชื่ออังกฤษ เหมือนใบที่คลินิกทำเอง (TMC ขอ 2569-11-10)
        uasort($rows, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        // แพทย์ผู้ลงนาม: เลือกได้ทุกคน (User::physicians) · ค่าเริ่มต้น = แพทย์เวชศาสตร์การเดินทาง (พญ.นิศาชล ว.53410)
        // "Travel Medicine Physician" เฉพาะแพทย์ในรายการ TRAVEL_MED_DOCTORS · แพทย์ท่านอื่น = บรรทัดตำแหน่งเป็นจุดให้เขียนเอง
        // ชื่ออังกฤษใช้ users.name_en (ตั้งที่หน้าจัดการผู้ใช้) · ไม่มี = จุดให้เขียนเอง
        // ผูกกับเลขใบอนุญาต ไม่ใช่ id ผู้ใช้ (id เครื่องทดสอบกับเครื่องจริงไม่ตรงกัน)
        $doctors = \App\Models\User::whereIn('id', \App\Models\User::physicians()->pluck('id'))->get(['id', 'name', 'name_en', 'license_number'])
            ->map(function ($u) {
                $lic = preg_replace('/^[^0-9]*/u', '', (string) $u->license_number);
                $tm  = isset(self::TRAVEL_MED_DOCTORS[$lic]);
                return [
                    'id'      => $u->id,
                    'name'    => $u->name,
                    'name_en' => trim((string) $u->name_en) ?: ($tm ? self::TRAVEL_MED_DOCTORS[$lic] : ''),
                    'license' => $lic,
                    'title'   => $tm ? 'Travel Medicine Physician' : '',
                ];
            })->sortByDesc('title')->values();   // แพทย์เวชศาสตร์การเดินทางขึ้นก่อน
        $docId = ($doctors->firstWhere('title', '!=', '') ?? [])['id'] ?? null;

        return [
            'visit'    => $visit,
            'patient'  => $patient,
            'nameEn'   => mb_strtoupper(\App\Support\LabReportLang::patientName($patient, 'en')),
            'hasEn'    => \App\Support\LabReportLang::hasEnglishName($patient),
            'rows'     => array_values($rows),
            'doseCols' => $doseCols,
            'hasHome'  => $shots->contains(fn ($s) => (int) $s->source === VisitVaccine::TAKE_HOME),
            'dobEn'    => $this->dobEn($patient),
            'doctors'  => $doctors,
            'docId'    => $docId,
        ];
    }

    /** patients.birth_date เก็บเป็น พ.ศ. → "20 Feb 1999" */
    private function dobEn($patient): ?string
    {
        if (empty($patient->birth_date) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $patient->birth_date, $m)) { return null; }
        $y = (int) $m[1];
        if ($y > 2400) { $y -= 543; }
        try {
            return \Carbon\Carbon::createFromFormat('Y-m-d', sprintf('%04d-%s-%s', $y, $m[2], $m[3]))->format('d M Y');
        } catch (\Throwable $e) { return null; }
    }

    /**
     * แก้รายละเอียดเข็มที่บันทึกไปแล้ว (TMC ขอ 2569-10-16: Lot ผิด/ลืมกรอก ต้องลบแล้วบันทึกใหม่)
     * แก้ได้: Lot · ผู้ผลิต · เข็มที่ · ตำแหน่ง · Route · วันที่/เวลาฉีด · นัดเข็มถัดไป · หมายเหตุ
     * ไม่ให้แก้ชื่อวัคซีน / ที่มา — ผูกกับสต๊อกและบิล ถ้าผิดให้ลบแล้วบันทึกใหม่ (ระบบคืนสต๊อก/แก้บิลให้)
     * ตอบ JSON (หน้าตรวจเรียกด้วย fetch แล้วโหลดหน้าใหม่)
     */
    public function update(Request $request, $visitId, $id)
    {
        $visit = Visit::findOrFail($visitId);
        $shot = VisitVaccine::where('id', $id)->where('patient_id', $visit->patient_id)->first();
        if (! $shot) { return response()->json(['message' => 'ไม่พบรายการฉีดนี้'], 404); }

        $data = $request->validate([
            'lot_number'      => ['nullable', 'string', 'max:255'],
            'manufacturer'    => ['nullable', 'string', 'max:255'],
            'dose_number'     => ['nullable', 'integer', 'min:1', 'max:20'],
            'site'            => ['nullable', 'string', 'max:255'],
            'route'           => ['nullable', 'string', 'in:' . implode(',', array_keys(VisitVaccine::$routeOption))],
            'vaccinated_date' => ['nullable', 'string', 'max:20'],
            'vaccinated_time' => ['nullable', 'string', 'max:10'],
            'next_due_date'   => ['nullable', 'string', 'max:20'],
            'note'            => ['nullable', 'string'],
        ], [
            'dose_number.*' => 'เข็มที่ต้องเป็นตัวเลข 1-20', 'route.in' => 'Route ไม่ถูกต้อง',
        ]);

        $takeHome = (int) $shot->source === VisitVaccine::TAKE_HOME;   // วันนัดให้ทานเป็นวันอนาคตได้
        foreach (['vaccinated_date' => ['วันที่ฉีด', ! $takeHome], 'next_due_date' => ['นัดเข็มถัดไป', false]] as $f => [$label, $past]) {
            $raw = trim((string) ($data[$f] ?? ''));
            if ($raw === '') { $data[$f] = null; continue; }
            $r = \App\Support\ThaiDate::parse($raw, ['notFuture' => $past]);
            if (! $r['ok']) { return response()->json(['message' => $label . ': ' . $r['error']], 422); }
            $data[$f] = VisitVaccine::toDbDate(\App\Support\ThaiDate::show($r['be']));
        }
        if (! $data['vaccinated_date']) { unset($data['vaccinated_date']); }   // วันที่ฉีดห้ามว่าง — ไม่กรอก = คงเดิม
        $t = trim((string) ($data['vaccinated_time'] ?? ''));
        if ($t !== '' && ! preg_match('/^\d{1,2}:\d{2}$/', $t)) { return response()->json(['message' => 'เวลาฉีดไม่ถูกต้อง (ชั่วโมง:นาที)'], 422); }

        $shot->fill([
            'lot_number'   => trim((string) ($data['lot_number'] ?? '')) ?: null,
            'manufacturer' => trim((string) ($data['manufacturer'] ?? '')) ?: null,
            'dose_number'  => $data['dose_number'] ?? null,
            'site'         => trim((string) ($data['site'] ?? '')) ?: null,
            'route'        => ($data['route'] ?? null) ?: null,
            'vaccinated_time' => $t ?: $shot->vaccinated_time,
            'next_due_date'   => $data['next_due_date'] ?? null,
            'note'         => trim((string) ($data['note'] ?? '')) ?: null,
        ]);
        if (isset($data['vaccinated_date'])) { $shot->vaccinated_date = $data['vaccinated_date']; }
        $shot->save();

        return response()->json(['ok' => true, 'message' => 'แก้รายการฉีด ' . $shot->name . ' แล้ว']);
    }

    public function destroy(Request $request, $visitId, $id)
    {
        $visit = Visit::findOrFail($visitId);
        $page  = $request->input('page', 1);

        // ผูกกับคนไข้ของ visit นี้เท่านั้น กันลบข้ามคนไข้จาก URL ที่เดาเอา
        $shot = VisitVaccine::where('id', $id)
            ->where('patient_id', $visit->patient_id)
            ->first();

        if (!$shot) {
            return redirect()->route('visit.edit', ['visit' => $visit->id, 'page' => $page]);
        }

        // คืนของเข้า lot เดิมถ้าตอนบันทึกมีการตัดสต็อกไป (ลบเพราะคีย์ผิด ของยังอยู่ในตู้)
        if ($shot->medicine_lot_id && VisitVaccine::isGiven($shot->source)) {
            $lot = \App\Models\MedicineLot::find($shot->medicine_lot_id);
            if ($lot) {
                $lot->quantity_remaining += 1;
                $lot->save();

                \App\Models\MedicineLotMovement::create([
                    'medicine_lot_id' => $lot->id,
                    'medicine_id'     => $lot->medicine_id,
                    'visit_id'        => $visit->id,
                    'type'            => 'return',
                    'qty'             => 1,
                    'user_id'         => Auth::id(),
                    'note'            => 'ยกเลิกรายการฉีดวัคซีน TMC',
                ]);

                optional($lot->medicine)->syncStockFromLots();
            }
        }

        $shot->delete();
        $this->syncBilling($visit);

        return redirect()->route('visit.edit', ['visit' => $visit->id, 'page' => $page]);
    }
}
