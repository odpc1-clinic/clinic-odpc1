<?php

namespace App\Http\Controllers;

use App\Models\LabCatalogItem;
use App\Models\LabPrice;
use App\Models\TbRegistration;
use App\Models\Visit;
use App\Models\VisitAppointment;
use App\Services\LabCatalogOrder;
use App\Services\LabCatalogSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * แคตตาล็อกแล็บรวม (2569-10-02)
 *   - หน้า /lab-catalog : ผู้ดูแล/หัวหน้า Lab ดูตามหมวด ค้นหา เปิด-ปิด แก้ชื่อ/หมวด/ราคา/คลินิก/ลำดับ
 *   - picker / order    : ตัวเลือก "สั่งแล็บ" (partials/lab_catalog_picker) โหลดรายการ + ยืนยันสั่งทีเดียว
 *
 * ทุกทางเช็ค LabCatalogItem::ready() — migration ยังไม่รัน = หน้าจอเดิมทำงานเหมือนเดิม endpoint ตอบ 404
 */
class LabCatalogController extends Controller
{
    public function __construct()
    {
        // แก้แคตตาล็อก = กระทบทุกคลินิก จำกัดเฉพาะผู้ดูแลกับห้อง Lab · การสั่งใช้สิทธิ์เดียวกับปุ่มสั่งแล็บเดิม (ล็อกอินแล้ว)
        $this->middleware('role:Admin|Lab')->only(['index', 'save', 'sync']);
    }

    // ===================== หน้าผู้ดูแล =====================

    public function index(Request $request)
    {
        if (! LabCatalogItem::ready()) {
            return view('lab_catalog.index', ['ready' => false]);
        }

        $q      = trim((string) $request->get('q', ''));
        $cat    = (string) $request->get('category', '');
        $kind   = (string) $request->get('kind', '');
        $clinic = (int) $request->get('clinic', 0);
        $show   = (string) $request->get('show', 'all');   // all | active | inactive

        $rows = LabCatalogItem::with('externalTest')
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'LIKE', "%{$q}%")->orWhere('code', 'LIKE', "%{$q}%")))
            ->when($cat !== '', fn ($x) => $x->where('category', $cat))
            ->when($kind !== '', fn ($x) => $x->where('kind', $kind))
            ->when($clinic > 0, fn ($x) => $x->where(fn ($w) => $w->whereNull('clinics')->orWhereJsonContains('clinics', $clinic)))
            ->when($show === 'active', fn ($x) => $x->where('active', true))
            ->when($show === 'inactive', fn ($x) => $x->where('active', false))
            ->orderBy('sort')->orderBy('name')
            ->get();

        $order   = array_flip(array_keys(LabCatalogItem::CATEGORIES));
        $grouped = $rows->groupBy('category')->sortBy(fn ($g, $k) => $order[$k] ?? 999);
        $counts  = LabCatalogItem::selectRaw('category, count(*) n, sum(active) a')->groupBy('category')->get()->keyBy('category');

        return view('lab_catalog.index', [
            'ready'     => true,
            'grouped'   => $grouped,
            'counts'    => $counts,
            'total'     => $rows->count(),
            'labPrices' => LabPrice::pluck('price', 'name')->toArray(),
            'filters'   => compact('q', 'cat', 'kind', 'clinic', 'show'),
        ]);
    }

    /** บันทึกเฉพาะแถวที่แก้ (หน้าจอส่งมาแค่แถวที่เปลี่ยน — คนอื่นแก้แถวอื่นพร้อมกันไม่ทับกัน) */
    public function save(Request $request)
    {
        abort_unless(LabCatalogItem::ready(), 404);

        $data = $request->validate([
            'rows'                 => ['required', 'array', 'max:500'],
            'rows.*.name'          => ['required', 'string', 'max:255'],
            'rows.*.code'          => ['nullable', 'string', 'max:50'],
            'rows.*.category'      => ['required', 'in:' . implode(',', array_keys(LabCatalogItem::CATEGORIES))],
            'rows.*.default_price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'rows.*.price_note'    => ['nullable', 'string', 'max:255'],
            'rows.*.clinics'       => ['nullable', 'array'],
            'rows.*.clinics.*'     => ['in:1,2,3'],
            'rows.*.sort'          => ['nullable', 'integer', 'min:-9999', 'max:99999'],
            'rows.*.active'        => ['nullable'],
        ], [
            'rows.*.name.required'         => 'ชื่อรายการห้ามว่าง',
            'rows.*.default_price.numeric' => 'ราคาต้องเป็นตัวเลข',
            'rows.*.default_price.min'     => 'ราคาติดลบไม่ได้',
        ]);

        $n = 0;
        DB::transaction(function () use ($data, &$n) {
            foreach ($data['rows'] as $id => $r) {
                $item = LabCatalogItem::find((int) $id);
                if (! $item) { continue; }
                $item->name       = trim($r['name']);
                $item->code       = isset($r['code']) && trim($r['code']) !== '' ? trim($r['code']) : null;
                $item->category   = $r['category'];
                // แล็บในคลินิก/ส่งตรวจวัณโรค ราคาไม่ได้มาจากแคตตาล็อก (ดู LabCatalogItem::priceInfo) — ไม่รับค่าที่ส่งมา
                if ($item->kind === LabCatalogItem::EXTERNAL) {
                    $item->default_price = isset($r['default_price']) && $r['default_price'] !== '' ? $r['default_price'] : null;
                }
                $item->price_note = isset($r['price_note']) && trim($r['price_note']) !== '' ? trim($r['price_note']) : null;
                $clinics = array_values(array_unique(array_map('intval', (array) ($r['clinics'] ?? []))));
                sort($clinics);
                $item->clinics    = $clinics;   // ไม่ติ๊กสักคลินิก = ไม่มีใครเห็น (ต่างจาก null = ทุกคลินิก ซึ่งตั้งได้จาก seeder เท่านั้น)
                $item->sort       = (int) ($r['sort'] ?? 0);
                $item->active     = ! empty($r['active']);
                if ($item->isDirty()) { $item->save(); $n++; }
            }
        });

        flash('บันทึกแล้ว ' . $n . ' รายการ', 'success');
        return redirect()->route('lab-catalog.index', $request->only(['q', 'category', 'kind', 'clinic', 'show']));
    }

    /** เติมรายการที่ยังไม่มี (เช่น แล็บนอกที่เพิ่งเพิ่มในหน้า "รายการ Lab นอก") — ไม่ทับที่แก้ไว้ */
    public function sync(Request $request)
    {
        abort_unless(LabCatalogItem::ready(), 404);
        $r = LabCatalogSync::run(false);
        flash('เติมรายการใหม่ ' . $r['created'] . ' รายการ (ของเดิม ' . $r['kept'] . ' รายการไม่ถูกแตะ)', 'success');
        return redirect()->route('lab-catalog.index');
    }

    // ===================== ตัวเลือก "สั่งแล็บ" =====================

    /** รายการทั้งหมดที่คลินิกของ visit นี้สั่งได้ + สิ่งที่สั่งไปแล้ว — โหลดใหม่ทุกครั้งที่เปิดตัวเลือก (อีกห้องอาจเพิ่งสั่ง) */
    public function picker(Request $request, $visitId)
    {
        if (! LabCatalogItem::ready()) { return response()->json(['message' => 'ยังไม่ได้เปิดใช้แคตตาล็อกแล็บ'], 404); }
        $visit  = Visit::with('patient2')->findOrFail($visitId);
        $clinic = (int) ($visit->clinic ?? 1);

        $labPrices = LabPrice::pluck('price', 'name')->toArray();
        $state     = LabCatalogOrder::orderedState($visit);
        $order     = array_flip(array_keys(LabCatalogItem::CATEGORIES));

        $items = LabCatalogItem::with('externalTest')->forClinic($clinic)->get()
            ->sortBy(fn ($c) => sprintf('%03d|%06d|%s', $order[$c->category] ?? 999, $c->sort + 10000, mb_strtolower($c->name)))
            ->values();

        $outItems = []; $cats = [];
        foreach ($items as $c) {
            $t  = $c->externalTest;
            $pi = $c->priceInfo($labPrices, $t);
            $warn = null;
            if ($c->kind === LabCatalogItem::EXTERNAL && $t) {
                // ด่านเตือนเดิมของ Lab นอก (เคยสั่งผิดจนคลินิกต้องจ่ายเอง) — ยกมาทั้งสองชั้น
                if ($t->inHouseName()) {
                    $warn = ['type' => 'inhouse', 'text' => 'ห้องแล็บเราตรวจ "' . $t->inHouseName() . '" เองได้ — ส่งนอกมีค่าใช้จ่ายที่อาจไม่จำเป็น'];
                } elseif ($cf = $t->confusableWith()) {
                    $warn = ['type' => 'confuse', 'text' => 'ชื่อคล้ายกับ "' . $cf['name'] . '" — ' . $cf['note']];
                }
            }
            $spec = [];
            foreach ($c->specimenOptions() as $k => $label) { $spec[] = ['v' => (string) $k, 'l' => $label]; }
            $outItems[] = [
                'id'        => $c->id,
                'name'      => $c->name,
                'code'      => $c->code,
                'category'  => $c->category,
                'kind'      => $c->kind,
                'kindLabel' => $c->kindLabel($t),
                'specimenNote' => $t->specimen ?? null,
                'price'     => $pi['price'],
                'priceNote' => $pi['note'],
                'priceEdit' => $pi['editable'],
                'ref'       => $pi['ref'],
                'needsSpec' => $c->needsSpecimen(),
                'specOther' => $c->kind === LabCatalogItem::IN_HOUSE ? '10' : 'other',
                'specs'     => $spec,
                'needsText' => $c->needsText(),
                'needsDate' => $c->kind === LabCatalogItem::EXTERNAL || $c->needsSpecimen() && $c->kind === LabCatalogItem::TB_SENDOUT,
                'ordered'   => $state[$c->target] ?? null,
                'warn'      => $warn,
            ];
            $cats[$c->category] = ($cats[$c->category] ?? 0) + 1;
        }

        $catList = [];
        foreach (LabCatalogItem::CATEGORIES as $k => $label) {
            if (! empty($cats[$k])) { $catList[] = ['key' => $k, 'label' => $label, 'count' => $cats[$k]]; }
        }

        $pt = $visit->patient2;
        return response()->json([
            'visit' => [
                'id'     => $visit->id,
                'clinic' => $clinic,
                'date'   => \App\Utillity::db2thDate($visit->date),
                'who'    => trim(($pt->name ?? '') . ' ' . ($pt->surname ?? '')),
                'hn'     => $pt->code ?? null,
            ],
            'today'      => \App\Utillity::db2thDate(VisitAppointment::todayBe()),
            'categories' => $catList,
            'items'      => $outItems,
        ]);
    }

    /**
     * ลงผลรายการตรวจข้างเตียงที่สั่งไว้แล้ว (DTX / EKG) จากหน้าต่างสั่ง — ไม่มีห้อง Lab ไม่ต้องอนุมัติ (2569-10-22)
     * EKG: ค่าเดียวกันลงช่อง QTcF ของ visit ด้วย สัญญาณชีพกับตารางติดตามในบัตร TB 01 จะได้ตรงกัน
     */
    public function result(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $m = (int) $request->input('method');
        if (! \App\Models\LabSub::isPoc($m)) { return response()->json(['message' => 'รายการนี้ลงผลที่ห้อง Lab'], 422); }
        [$unit, $min, $max] = \App\Models\LabSub::$pocMethods[$m];
        $v = trim((string) $request->input('value'));
        if ($v !== '' && (! is_numeric($v) || (float) $v < $min || (float) $v > $max)) {
            return response()->json(['message' => 'ผลต้องเป็นตัวเลข ' . $min . '–' . $max . ' ' . $unit], 422);
        }
        $lab = \App\Models\Lab::where('visit_id', $visit->id)->first();
        $sub = $lab ? \App\Models\LabSub::where('lab_id', $lab->id)->where('method', $m)->orderBy('id')->first() : null;
        if (! $sub) { return response()->json(['message' => 'ยังไม่ได้สั่งรายการนี้ใน visit นี้ — ติ๊กสั่งก่อน'], 422); }

        $val = $v === '' ? null : rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.');
        $sub->fill(['result' => $val, 'tested_by' => $val ? \Illuminate\Support\Facades\Auth::id() : null,
                    'tested_date' => $val ? VisitAppointment::todayBe() : null])->save();
        if ($m === 16 && \Illuminate\Support\Facades\Schema::hasColumn('visits', 'qtcf')) {
            $visit->qtcf = $val === null ? null : (int) round((float) $val);
            $visit->saveQuietly();
        }

        $flag = \App\Models\LabSub::pocFlag($m, $val);
        return response()->json(['ok' => true, 'message' => $val === null ? 'ลบผลแล้ว'
            : 'บันทึกผล ' . \App\Models\LabSub::$methodOption[$m] . ' = ' . $val . ' ' . $unit . ($flag ? ' · ' . $flag[0] : '')]);
    }

    /** ยืนยันสั่ง — คำขอเดียว หลายรายการ ส่งต่อแต่ละรายการไปทางเดิมของมัน (App\Services\LabCatalogOrder) */
    public function order(Request $request, $visitId)
    {
        if (! LabCatalogItem::ready()) { return response()->json(['message' => 'ยังไม่ได้เปิดใช้แคตตาล็อกแล็บ'], 404); }
        $visit = Visit::findOrFail($visitId);

        // จากบัตร TB 01: ต้องเป็น visit TB ของคนไข้ในบัตร และเป็นของวันนี้ (กติกาเดียวกับ TbController@saveLabOrder)
        // กันกดจากหน้าที่เปิดค้างข้ามวัน แล้วไปลงผิด visit
        if ($request->filled('tb_reg')) {
            $reg = TbRegistration::find((int) $request->input('tb_reg'));
            if (! $reg || (int) $visit->patient_id !== (int) $reg->patient_id || (int) $visit->clinic !== 2
                || $visit->date !== VisitAppointment::todayBe()) {
                return response()->json(['message' => 'ยังไม่ได้สั่ง — ไม่พบ visit TB ของวันนี้ (หน้าอาจเปิดค้างไว้ข้ามวัน) โหลดหน้าใหม่แล้วลองอีกครั้ง'], 409);
            }
        }

        $items = $request->input('items');
        if (! is_array($items) || ! $items || count($items) > 60) {
            return response()->json(['message' => 'ยังไม่ได้เลือกรายการ (หรือเลือกเกิน 60 รายการ)'], 422);
        }
        $items = array_values(array_filter($items, 'is_array'));

        $res = LabCatalogOrder::order($visit, $items);
        if (! $res['ok']) {
            return response()->json(['message' => $res['message'], 'errors' => $res['errors']], 422);
        }

        $kinds = array_unique(array_column($res['results'], 'kind'));
        $out = ['ok' => true, 'message' => $res['message'], 'results' => $res['results']];

        // หน้าตรวจ OPD ขอเนื้อในส่วนที่เกี่ยวกลับไปด้วย จะได้สลับในที่ ไม่ต้องโหลดหน้าใหม่ (ไม่มี <script> ทั้งสองไฟล์)
        if ($request->input('context') === 'opd') {
            $fresh = $visit->fresh();
            if (in_array('in_house', $kinds, true) && (int) $visit->clinic === 2) {
                $out['inhouse_panel'] = view('tb._inhouse_lab_panel', ['visit' => $fresh, 'fullLink' => true])->render();
            }
            if (in_array('external', $kinds, true)) {
                $out['external_rows']  = view('visit.form._external_lab_rows', ['visit' => $fresh, 'page' => $request->input('page', 1)])->render();
                $out['external_count'] = \App\Models\VisitExternalLab::where('visit_id', $visit->id)->count();
                $out['external_created'] = count(array_filter($res['results'], fn ($r) => $r['kind'] === 'external'));
            }
            if (in_array('tb_sendout', $kinds, true)) {
                $out['tb_state'] = LabCatalogOrder::tbState($fresh);
            }
        }
        return response()->json($out);
    }
}
