<?php

namespace App\Http\Controllers;

use App\Models\GcIdentification;
use App\Models\LabSub;
use App\Models\User;
use App\Utillity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * แบบบันทึกผลการทดสอบพิสูจน์เชื้อหนองใน (FM-STI-WI08-02 REV1) — ห้อง Lab (พี่ชล) ขอ 2569-10-02
 *
 * ดึงรายการ Culture (lab_subs.method = 4) ตามวันเก็บสิ่งส่งตรวจ มาเป็นตารางเดียวกับแบบฟอร์มกระดาษ
 * ห้อง Lab กรอกผลทดสอบทีละขั้นได้หลายวัน แล้วพิมพ์ออกเป็นแบบฟอร์มเดิม
 *
 * ผลที่นี่เป็น "ใบงาน" ของห้อง Lab — ไม่แตะ lab_subs.result (ผล Culture ทางการ)
 * เพราะ Final ID ไม่ได้แปลงเป็นผล Culture ตัวเดียวได้ตรง ๆ (Non-NG อาจเป็น Growth / Neisseria spp. / Secondary)
 * และผลทางการต้องผ่าน Report/Approve ของใบ Lab ที่หน้าลงผลเดิม
 */
class LabGcIdController extends Controller
{
    public function __construct()
    {
        // หน้านี้มีผลที่ยังไม่ approve — เปิดได้เฉพาะห้อง Lab (Admin ดูแลห้อง Lab ด้วย) กติกาเดียวกับการลงผล AST
        $this->middleware(function ($request, $next) {
            abort_unless(Auth::check() && Auth::user()->hasAnyRole(['Lab', 'Admin']), 403, 'หน้านี้สำหรับห้อง Lab');
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $date     = $this->pickDate($request->input('date'));
        $showDone = $request->boolean('done');
        $ready    = GcIdentification::tableReady();
        $rows     = $this->rows($date, $showDone);

        return view('lab.gc_id', [
            'date'      => $date,
            'dateTh'    => Utillity::db2thDate($date),
            'showDone'  => $showDone,
            'ready'     => $ready,
            'rows'      => $rows,
            'summary'   => $this->summary(),
            'waitingOfficial' => $this->waitingOfficial($date),
            'stamps'    => $this->stampNames($rows),
        ]);
    }

    /** ใบพิมพ์ตามแบบฟอร์มกระดาษ — พิมพ์ได้ทั้งตอนยังไม่มีผล (เป็นใบงานกรอกมือ) และตอนลงผลแล้ว */
    public function print(Request $request)
    {
        $date     = $this->pickDate($request->input('date'));
        $showDone = $request->boolean('done');
        $rows     = $this->rows($date, $showDone);

        return view('lab.gc_id_print', [
            'date'   => $date,
            'rows'   => $rows,
            'stamps' => $this->stampNames($rows),
        ]);
    }

    /**
     * บันทึกทั้งตาราง — รับเฉพาะช่องที่แก้ ({lab_sub_id: {field: value}})
     * สองเครื่องเปิดใบเดียวกันได้ ช่องที่อีกคนไม่ได้แตะจะไม่ทับของอีกคน (หลักเดียวกับ _touched ของหน้าตรวจ)
     */
    public function save(Request $request)
    {
        if (! GcIdentification::tableReady()) {
            return response()->json(['ok' => false, 'message' => 'ยังไม่ได้สร้างตารางเก็บผล (รอผู้ดูแลระบบ migrate) — ยังบันทึกไม่ได้'], 409);
        }
        $rows = (array) $request->input('rows', []);
        $saved = 0; $cleared = 0; $out = [];

        DB::transaction(function () use ($rows, &$saved, &$cleared, &$out) {
            foreach ($rows as $subId => $in) {
                $sub = LabSub::find((int) $subId);
                if (! $sub || (int) $sub->method !== 4 || ! is_array($in)) { continue; }

                $g = GcIdentification::firstOrNew(['lab_sub_id' => $sub->id]);
                $changed = false;
                foreach ($in as $f => $v) {
                    if (! in_array($f, GcIdentification::DATA_FIELDS, true)) { continue; }
                    $v = trim((string) $v);
                    if (isset(GcIdentification::FIELDS[$f])) {
                        $v = in_array($v, GcIdentification::FIELDS[$f], true) ? $v : '';
                    } else {
                        $v = mb_substr($v, 0, $f === 'lab_no' ? 50 : 255);
                    }
                    $v = $v === '' ? null : $v;
                    if ((string) $g->{$f} !== (string) $v) { $g->{$f} = $v; $changed = true; }
                }
                if (! $changed) { continue; }
                // ยังไม่เคยมีแถว และที่ส่งมาว่างหมด = ไม่ต้องสร้างแถวเปล่า
                if (! $g->exists && ! $g->hasData()) { continue; }

                // แก้ผลหลัง Report/Approve = ชุดที่รับรองไว้ไม่ใช่ชุดนี้แล้ว ต้อง Report/Approve ใหม่ (กติกาเดียวกับใบ Lab)
                if ($g->reported_by || $g->approved_by) {
                    $g->reported_by = $g->reported_at = $g->approved_by = $g->approved_at = null;
                    $cleared++;
                }
                $g->updated_by = Auth::id();
                $g->save();
                $saved++;
                $out[$sub->id] = ['done' => $this->isDone($sub->result, $g->final_id)];
            }
        });

        return response()->json([
            'ok' => true, 'saved' => $saved, 'cleared' => $cleared, 'rows' => $out,
            'message' => $saved
                ? 'บันทึกแล้ว ' . $saved . ' แถว' . ($cleared ? ' · ' . $cleared . ' แถวเคย Report/Approve ไว้ ต้องกด Report/Approve ใหม่' : '')
                : 'ไม่มีอะไรเปลี่ยน',
        ]);
    }

    /**
     * Report / Approve ทั้งใบของวันที่เลือก (เฉพาะแถวที่มีผลทดสอบแล้ว)
     * Approve ต้อง Report ก่อน และคน Report เองต้องรอ 5 นาที — ห้อง Lab กำหนดแบบเดียวกับใบ Lab (2569-09-30)
     */
    public function stamp(Request $request)
    {
        if (! GcIdentification::tableReady()) {
            return response()->json(['ok' => false, 'message' => 'ยังไม่ได้สร้างตารางเก็บผล — ยัง Report/Approve ไม่ได้'], 409);
        }
        $date   = $this->pickDate($request->input('date'));
        $action = $request->input('action') === 'approve' ? 'approve' : 'report';

        $subIds = $this->baseQuery($date)->pluck('s.id');
        $gs = GcIdentification::whereIn('lab_sub_id', $subIds)->get()->filter->hasData();
        if ($gs->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'วันนี้ยังไม่มีแถวที่ลงผลทดสอบ'], 422);
        }

        if ($action === 'report') {
            foreach ($gs as $g) {
                $g->reported_by = Auth::id(); $g->reported_at = now();
                $g->approved_by = null;       $g->approved_at = null;
                $g->save();
            }
            return response()->json(['ok' => true, 'message' => 'Report แล้ว ' . $gs->count() . ' แถว']);
        }

        $notReported = $gs->filter(fn ($g) => ! $g->reported_by)->count();
        $mine = $gs->filter(fn ($g) => (int) $g->reported_by === (int) Auth::id() && $g->reported_at
                                      && now()->lt($g->reported_at->copy()->addMinutes(5)));
        if ($mine->isNotEmpty()) {
            $readyAt = $mine->max('reported_at')->copy()->addMinutes(5);
            return response()->json(['ok' => false, 'message' => 'คุณเป็นคน Report เอง ต้องรอ 5 นาทีหลัง Report (Approve ได้ตั้งแต่ '
                . $readyAt->format('H:i') . ' น.) — หรือให้คนอื่น Approve'], 422);
        }
        $n = 0;
        foreach ($gs as $g) {
            if (! $g->reported_by) { continue; }
            $g->approved_by = Auth::id(); $g->approved_at = now(); $g->save(); $n++;
        }
        if (! $n) {
            return response()->json(['ok' => false, 'message' => 'ต้องกด Report ก่อน จึงจะ Approve ได้'], 422);
        }
        return response()->json(['ok' => true, 'message' => 'Approve แล้ว ' . $n . ' แถว'
            . ($notReported ? ' · อีก ' . $notReported . ' แถวยังไม่ได้ Report จึงยังไม่ Approve' : '')]);
    }

    // ------------------------------------------------------------------

    /** รับ dd/mm/ปี พ.ศ. (datepicker) หรือ Y-m-d — คืน พ.ศ. Y-m-d (รูปแบบ labs.collected_date) */
    private function pickDate($val): string
    {
        $today = (now()->year + 543) . now()->format('-m-d');
        $val = trim((string) $val);
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $val, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $val, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return $today;
        }
        if ($y < 2400) { $y += 543; }   // พิมพ์มาเป็น ค.ศ. ก็รับ
        return checkdate($mo, $d, $y - 543) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : $today;
    }

    /** รายการ Culture ที่เก็บวันนั้น — ข้อมูลยุคแรกเก็บวันเป็น ค.ศ. จึงเทียบทั้งสองแบบ */
    private function baseQuery(string $date)
    {
        $ad = ((int) substr($date, 0, 4) - 543) . substr($date, 4);
        return DB::table('lab_subs as s')
            ->join('labs as l', 'l.id', '=', 's.lab_id')
            ->where('s.method', 4)
            ->whereIn('l.collected_date', [$date, $ad]);
    }

    /** ทำเสร็จแล้ว = มีผล Culture ทางการในใบ Lab แล้ว หรือห้อง Lab ลง Final ID แล้ว */
    private function isDone($result, $finalId): bool
    {
        return trim((string) $result) !== '' || trim((string) $finalId) !== '';
    }

    private function rows(string $date, bool $showDone)
    {
        $ready = GcIdentification::tableReady();
        $q = $this->baseQuery($date)
            ->leftJoin('patients as p', 'p.id', '=', 'l.patient_id')
            ->leftJoin('visits as v', 'v.id', '=', 'l.visit_id')
            ->select('s.id as sub_id', 's.lab_id', 's.specimen_from', 's.specimen_from_other', 's.result',
                     'l.LN', 'l.visit_id', 'l.collected_date', 'l.collected_at', 'v.clinic',
                     'p.code as hn', 'p.name', 'p.surname')
            ->orderBy('l.collected_at')->orderBy('l.id')->orderBy('s.specimen_from')->orderBy('s.id');

        if ($ready) {
            $q->leftJoin('gc_identifications as g', 'g.lab_sub_id', '=', 's.id')
              ->addSelect('g.lab_no', 'g.culture', 'g.typical', 'g.gndc', 'g.oxidase', 'g.superoxol',
                          'g.glucose', 'g.maltose', 'g.sucrose', 'g.lactose', 'g.final_id', 'g.remark',
                          'g.reported_by', 'g.reported_at', 'g.approved_by', 'g.approved_at');
        }
        if (! $showDone) {
            // whereNull พ่วงด้วย — ช่องว่างกับ NULL ต้องนับเป็น "ยังไม่มีผล" ทั้งคู่
            $q->where(fn ($w) => $w->whereNull('s.result')->orWhere('s.result', ''));
            if ($ready) {
                $q->where(fn ($w) => $w->whereNull('g.final_id')->orWhere('g.final_id', ''));
            }
        }
        $rows = $q->get();

        $egasp = $this->egaspNumbers($rows->pluck('visit_id')->filter()->unique()->all());
        foreach ($rows as $r) {
            $r->egasp    = $egasp[$r->visit_id] ?? '';
            $r->specimen = (new LabSub(['method' => 4, 'specimen_from' => $r->specimen_from,
                                        'specimen_from_other' => $r->specimen_from_other]))->specimenFrom();
            // Lab number: ห้อง Lab กรอกเองที่หน้านี้ ถ้ายังไม่กรอกใช้ LN ของใบ Lab (ใบใหม่ ๆ ส่วนใหญ่ว่าง)
            $r->lab_no_show = ($r->lab_no ?? null) ?: ($r->LN ?: '');
            $r->done = $this->isDone($r->result, $r->final_id ?? null);
            foreach (array_keys(GcIdentification::FIELDS) as $f) { $r->{$f} = $r->{$f} ?? null; }
            $r->remark = $r->remark ?? null;
        }
        return $rows;
    }

    /**
     * เลข EGASP จากการวินิจฉัยของ visit เดียวกัน (diagnoses.egasp_info เก็บแค่เลข เช่น "0587")
     * Egasp 1 (disease 17) = CMC1-EG-xxxx · Egasp 2 (disease 18) = CMC1-EG2-xxxx — รูปแบบเดียวกับที่ห้อง Lab เคยพิมพ์ไว้ในช่อง LN ปี 2568
     */
    private function egaspNumbers(array $visitIds): array
    {
        if (! $visitIds) { return []; }
        $out = [];
        $dx = DB::table('diagnoses')->whereIn('visit_id', $visitIds)->whereIn('disease', [17, 18])
            ->whereNotNull('egasp_info')->where('egasp_info', '<>', '')->orderBy('id')->get(['visit_id', 'disease', 'egasp_info']);
        foreach ($dx as $d) {
            $info = trim($d->egasp_info);
            if (stripos($info, 'CMC1') === 0) { $no = $info; }
            elseif (stripos($info, 'EG') === 0) { $no = 'CMC1-' . $info; }
            else { $no = ((int) $d->disease === 18 ? 'CMC1-EG2-' : 'CMC1-EG-') . $info; }
            $out[$d->visit_id] = isset($out[$d->visit_id]) ? $out[$d->visit_id] . ', ' . $no : $no;
        }
        return $out;
    }

    /**
     * ตัวเลขไว้เทียบกับห้อง Lab: Culture แต่ละวันเก็บ 30 วันล่าสุด · ทั้งหมด / ยังไม่มีผล
     * นับต่อ "รายการ Culture" (lab_sub) ไม่ใช่ต่อคน — คนหนึ่งเก็บ 2 ตำแหน่ง = 2 รายการ
     */
    private function summary(): array
    {
        $to   = (now()->year + 543) . now()->format('-m-d');
        $from = (now()->subDays(29)->year + 543) . now()->subDays(29)->format('-m-d');
        $ready = GcIdentification::tableReady();

        $q = DB::table('lab_subs as s')->join('labs as l', 'l.id', '=', 's.lab_id')
            ->where('s.method', 4)->whereBetween('l.collected_date', [$from, $to]);
        $pendingSql = "(s.result IS NULL OR s.result = '')";
        if ($ready) {
            $q->leftJoin('gc_identifications as g', 'g.lab_sub_id', '=', 's.id');
            $pendingSql .= " AND (g.final_id IS NULL OR g.final_id = '')";
        }
        $byDate = $q->groupBy('l.collected_date')->orderByDesc('l.collected_date')
            ->select('l.collected_date', DB::raw('COUNT(*) AS total'),
                     DB::raw("SUM($pendingSql) AS pending"),
                     DB::raw('COUNT(DISTINCT l.patient_id) AS people'))
            ->get();

        // สั่ง Culture แล้วแต่ใบ Lab ยังไม่มีวันเก็บ — จะไม่โผล่ในหน้านี้จนกว่าจะลงวันเก็บ
        $noCollected = DB::table('lab_subs as s')->join('labs as l', 'l.id', '=', 's.lab_id')
            ->where('s.method', 4)->where('s.created_at', '>=', now()->subDays(29)->startOfDay())
            ->where(fn ($w) => $w->whereNull('l.collected_date')->orWhere('l.collected_date', ''))
            ->count();

        // ยังค้างจากก่อนช่วง 30 วัน (ไม่มีผลเลย) — ไว้เตือนว่ามีของตกหล่นเก่า
        $olderPending = DB::table('lab_subs as s')->join('labs as l', 'l.id', '=', 's.lab_id')
            ->where('s.method', 4)->where('l.collected_date', '>=', '2569-01-01')->where('l.collected_date', '<', $from)
            ->where(fn ($w) => $w->whereNull('s.result')->orWhere('s.result', ''))
            ->count();

        return compact('byDate', 'noCollected', 'olderPending', 'from', 'to');
    }

    /** ลง Final ID แล้ว แต่ผล Culture ในใบ Lab ยังว่าง — หลุดจากรายการค้าง แต่ยังต้องไปลงผลทางการ */
    private function waitingOfficial(string $date)
    {
        if (! GcIdentification::tableReady()) { return collect(); }
        return $this->baseQuery($date)
            ->join('gc_identifications as g', 'g.lab_sub_id', '=', 's.id')
            ->whereNotNull('g.final_id')->where('g.final_id', '<>', '')
            ->where(fn ($w) => $w->whereNull('s.result')->orWhere('s.result', ''))
            ->leftJoin('patients as p', 'p.id', '=', 'l.patient_id')
            ->get(['s.id as sub_id', 's.lab_id', 'p.code as hn', 'g.final_id']);
    }

    /** ชื่อผู้ Report/Approve + วันที่ ของแถวในใบ (หลายคน = ต่อชื่อกัน) สำหรับท้ายแบบฟอร์ม */
    private function stampNames($rows): array
    {
        $name = function ($id) {
            $u = $id ? User::find($id) : null;
            if (! $u) { return ''; }
            $lic = trim((string) $u->license_number);
            return trim($u->name . ($lic !== '' && ! str_contains($u->name, $lic) ? ' ' . $lic : ''));
        };
        $out = [];
        foreach (['reported' => 'reported', 'approved' => 'approved'] as $k => $col) {
            $ids = $rows->pluck($col . '_by')->filter()->unique()->values();
            $at  = $rows->pluck($col . '_at')->filter()->max();
            $out[$k] = [
                'names' => $ids->map($name)->filter()->implode(', '),
                'date'  => $at ? Carbon::parse($at)->format('j/n/y') : '',
                'count' => $rows->filter(fn ($r) => ! empty($r->{$col . '_by'}))->count(),
            ];
        }
        return $out;
    }
}
