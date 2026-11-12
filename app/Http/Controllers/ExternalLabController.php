<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\ExternalLabTest;
use App\Models\Visit;
use App\Models\VisitExternalLab;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Lab ส่งตรวจภายนอก (N Health ฯลฯ)
 * แยกจากงาน Lab ในบ้านทั้งหมด — ไม่แตะ lab_subs และไม่เข้าคิวอนุมัติของห้อง Lab
 * เพราะห้อง Lab ไม่ได้เป็นคนตรวจ ผลจึงแสดงทันทีที่พยาบาลคีย์เข้ามา
 */
class ExternalLabController extends Controller
{
    /**
     * เพิ่มรายการส่งตรวจนอกเข้า visit
     *
     * รับได้ 2 แบบ:
     *  - items[] หลายรายการในคำขอเดียว (ตัวเลือกแบบ "รายการที่จะสั่ง" 2569-10-02)
     *    พยาบาลเคยต้องเพิ่มทีละรายการ แต่ละครั้งโหลดหน้าใหม่ + เจอกล่อง "ออกจากหน้านี้?" จนเลื่อนหาที่เดิมไม่เจอ
     *  - ช่องเดี่ยว external_lab_test_id/name/price/sent_date แบบเดิม — หน้าที่เปิดค้างไว้ก่อนอัปเดตยังส่งแบบนี้อยู่
     */
    public function store(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $page  = $request->input('page', 1);

        $batch = $request->has('items');

        if ($batch) {
            $data = $request->validate([
                'items'                        => ['required', 'array', 'min:1', 'max:50'],
                'items.*.external_lab_test_id' => ['nullable', 'integer', 'exists:external_lab_tests,id'],
                'items.*.name'                 => ['nullable', 'string', 'max:255'],
                'items.*.price'                => ['nullable', 'numeric', 'min:0'],
                'items.*.sent_date'            => ['nullable', 'string', 'max:20'],
            ], [
                'items.*.price.numeric' => 'ราคาต้องเป็นตัวเลข',
                'items.*.price.min'     => 'ราคาติดลบไม่ได้',
            ]);
            $items = array_values($data['items']);
        } else {
            $data = $request->validate([
                'external_lab_test_id' => ['nullable', 'integer', 'exists:external_lab_tests,id'],
                'name'                 => ['nullable', 'string', 'max:255'],   // กรณีพิมพ์รายการเองที่ไม่มีในแคตตาล็อก
                'price'                => ['nullable', 'numeric', 'min:0'],
                'sent_date'            => ['nullable', 'string', 'max:20'],
            ]);
            $items = [$data];
        }

        // เตรียมทุกแถวให้ผ่านก่อนค่อยเขียน — ถ้ามีรายการไหนไม่มีชื่อ ต้องไม่บันทึกครึ่ง ๆ กลาง ๆ
        $attrs = [];
        foreach ($items as $i => $it) {
            $row = $this->rowAttributes($visit, (array) $it);
            if ($row === null) {
                $key = $batch ? "items.$i.external_lab_test_id" : 'external_lab_test_id';
                if ($request->wantsJson()) {
                    return response()->json(['message' => 'กรุณาเลือกรายการตรวจ',
                                             'errors'  => [$key => ['กรุณาเลือกรายการตรวจ']]], 422);
                }
                return back()->with('flash_message', 'กรุณาเลือกรายการตรวจ');
            }
            $attrs[] = $row;
        }

        DB::transaction(function () use ($attrs) {
            foreach ($attrs as $a) { VisitExternalLab::create($a); }
        });

        // คิดบิลครั้งเดียวหลังเพิ่มครบ — syncExternalLabItems สร้างรายการบิลใหม่จากทุกแถวของ visit
        // ผลจึงเท่ากับเพิ่มทีละรายการทุกประการ
        $this->syncBilling($visit);

        return $this->backTo($request, $visit, $page, count($attrs));
    }

    /** ค่าของแถวใหม่หนึ่งแถว · null = ไม่มีชื่อรายการ (ไม่ได้เลือกจากแคตตาล็อกและไม่ได้พิมพ์เอง)
     *  public static: ตัวสั่งแล็บรวม (App\Services\LabCatalogOrder) ใช้ตัวเดียวกัน แถวที่ได้จึงเหมือนสั่งจากที่นี่ทุกช่อง */
    public static function rowAttributes(Visit $visit, array $data): ?array
    {
        $test = !empty($data['external_lab_test_id'])
            ? ExternalLabTest::find($data['external_lab_test_id'])
            : null;

        $name = $test->name ?? trim((string) ($data['name'] ?? ''));
        if ($name === '') { return null; }

        $price = $data['price'] ?? null;

        return [
            'visit_id'             => $visit->id,
            'external_lab_test_id' => $test->id ?? null,
            'vendor'               => $test->vendor ?? null,   // ชื่อแล็บ ณ ตอนสั่ง (แคตตาล็อกแก้ทีหลังได้)
            'code'                 => $test->code ?? null,
            'name'                 => $name,
            'specimen'             => $test->specimen ?? null,
            'ref_price'            => $test->price ?? null,   // ราคาตามใบเสนอราคา เก็บไว้อ้างอิง
            'price'                => ($price === '' ? null : $price), // ราคาที่เก็บคนไข้จริง พยาบาลกรอกเอง
            // nullable ใน validate = ถ้าไม่ส่งมาจะไม่มีคีย์เลย ต้อง ?? กันก่อน
            // และต้องแปลง d/m/Y (พ.ศ.) -> Y-m-d ไม่งั้น MySQL เก็บเป็น 0000-00-00
            'sent_date'            => VisitExternalLab::toDbDate($data['sent_date'] ?? null),
            'ordered_by'           => Auth::id(),
        ];
    }

    /** ใบรายงานผลแล็บนอก (A4) — แยกจากใบผลของ Lab ในบ้าน เพราะไม่ได้ตรวจเอง */
    public function print(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);

        $rows = VisitExternalLab::where('visit_id', $visit->id)->orderBy('id')->get();

        // พิมพ์เฉพาะรายการที่มีผลแล้ว ถ้ายังไม่มีผลสักรายการค่อยพิมพ์ทั้งหมด (เป็นใบส่งตรวจ)
        $withResult = $rows->filter(fn ($r) => $r->hasResult())->values();
        if ($withResult->isNotEmpty()) {
            $rows = $withResult;
        }

        // ชื่อแล็บต้นทาง เอาไว้ระบุในหมายเหตุ — ใช้ชื่อที่บันทึกไว้ตอนสั่ง (แคตตาล็อกแก้ชื่อทีหลังได้ รีวิว 2569-09-30)
        // ใบสั่งเก่าที่ยังไม่มีชื่อเก็บไว้ ค่อยถอยไปใช้ชื่อในแคตตาล็อก
        $catalog = ExternalLabTest::whereIn('id', $rows->pluck('external_lab_test_id')->filter())->pluck('vendor', 'id');
        $vendors = $rows->map(fn ($r) => trim((string) $r->vendor) !== '' ? trim((string) $r->vendor) : ($catalog[$r->external_lab_test_id] ?? null))
            ->filter()->unique()->implode(', ');

        // ผู้บันทึกผลคนล่าสุดที่มีในชุดนี้
        $recordedById = $rows->pluck('result_by')->filter()->last();
        $recordedBy   = $recordedById ? optional(\App\Models\User::find($recordedById))->name : null;

        return view('visit.external_lab_print', [
            'visit'       => $visit,
            'patient'     => $visit->patient(),
            'rows'        => $rows,
            'vendors'     => $vendors,
            'recordedBy'  => $recordedBy,
        ]);
    }

    /**
     * ตอบกลับหลังบันทึก
     *
     * หน้าตรวจ OPD ส่งฟอร์มธรรมดา -> กลับหน้าตรวจเหมือนเดิม
     * บัตร TB 01 เรียกผ่าน fetch (popup Lab ภายนอก 2569-10-02) -> ตอบ JSON ให้ปิด popup/รีเฟรชกล่องเองโดยไม่ต้องเปลี่ยนหน้า
     */
    private function backTo(Request $request, Visit $visit, $page, ?int $created = null)
    {
        if ($request->wantsJson()) {
            $rows = VisitExternalLab::where('visit_id', $visit->id)->get();
            $out  = [
                'ok'      => true,
                'count'   => $rows->count(),
                'total'   => (float) $rows->sum(fn ($r) => (float) $r->price),
                'created' => $created,
            ];
            // การ์ดในหน้าตรวจ OPD ขอตารางรายการกลับไปด้วย จะได้สลับเนื้อในที่ ไม่ต้องโหลดหน้าใหม่ (2569-10-02)
            // ตารางไม่มี <script> — innerHTML ไม่รันสคริปต์ ตัวจัดการอยู่ใน visit/form/external_lab
            if ($request->input('fragment') === 'opd') {
                $out['html'] = view('visit.form._external_lab_rows', ['visit' => $visit, 'page' => $page])->render();
            }
            return response()->json($out);
        }

        return redirect()->route('visit.edit', ['visit' => $visit->id, 'page' => $page]);
    }

    /** ดันค่าตรวจแล็บนอกเข้าบิล เพื่อให้ห้องยา/การเงินเห็นยอดพร้อมคิด (public static: ตัวสั่งแล็บรวมเรียกใช้ด้วย) */
    public static function syncBilling(Visit $visit): void
    {
        $billing = Billing::firstOrCreate(['visit_id' => $visit->id]);

        if (!$billing->bill_no) { $billing->bill_no = Billing::generateBillNo(); }
        if (!$billing->user_id) { $billing->user_id = Auth::id(); }
        $billing->save();

        $billing->syncExternalLabItems();
        $billing->recalcTotals();
    }

    /** บันทึกผล/ราคา/หมายเหตุ ของทุกแถวในครั้งเดียว */
    public function update(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $page  = $request->input('page', 1);

        $rows = VisitExternalLab::where('visit_id', $visit->id)->get()->keyBy('id');

        foreach ((array) $request->input('rows', []) as $id => $fields) {
            $row = $rows->get($id);
            if (!$row) { continue; }   // กัน id ที่ไม่ใช่ของ visit นี้

            $hadResult = $row->hasResult();

            $row->price       = ($fields['price'] ?? '') !== '' ? $fields['price'] : null;
            $row->result      = $fields['result'] ?? null;
            $row->remark      = $fields['remark'] ?? null;
            $row->result_date = VisitExternalLab::toDbDate($fields['result_date'] ?? null);

            // บันทึกว่าใครเป็นคนลงผล (เฉพาะตอนที่เพิ่งมีผลครั้งแรก)
            if (!$hadResult && $row->hasResult()) {
                $row->result_by = Auth::id();
            }

            $row->save();
        }

        $this->syncBilling($visit);

        return $this->backTo($request, $visit, $page);
    }

    public function destroy(Request $request, $visitId, $id)
    {
        $visit = Visit::findOrFail($visitId);
        $page  = $request->input('page', 1);

        VisitExternalLab::where('visit_id', $visit->id)->where('id', $id)->delete();

        $this->syncBilling($visit);

        return $this->backTo($request, $visit, $page);
    }
}
