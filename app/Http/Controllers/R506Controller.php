<?php

namespace App\Http\Controllers;

use App\Models\Diagnosi;
use App\Models\Visit;
use App\Support\R506\R506Exporter;
use App\Utillity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * หน้าจัดการรายงานเฝ้าระวังโรค 506 (STI)
 * - ให้พยาบาลติ๊กเลือกเคสที่จะส่ง
 * - เตือนเคสที่อยู่ไม่ครบ (ได้ ADDRCODE ไม่ครบ)
 * - สร้างไฟล์ DBF เฉพาะที่ติ๊ก แล้วบันทึกสถานะ "ส่งแล้ว" กันส่งซ้ำ
 */
class R506Controller extends Controller
{
    /** ช่วงวันที่เริ่มต้น = เดือนปัจจุบัน (พ.ศ.) */
    protected function defaultRange(): array
    {
        $now = Carbon::now();
        $beYear = $now->year + 543;
        $from = sprintf('%04d-%02d-01', $beYear, $now->month);
        $to   = sprintf('%04d-%02d-%02d', $beYear, $now->month, $now->daysInMonth);
        return [$from, $to];
    }

    /** รับค่าจาก datepicker (dd/mm/yyyy พ.ศ.) หรือ yyyy-mm-dd แล้วคืน พ.ศ. yyyy-mm-dd */
    protected function toBeDbDate(?string $val, string $default): string
    {
        $val = trim((string) $val);
        if ($val === '') return $default;
        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $val)) return $val;
        if (preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $val)) {
            try { return Utillity::th2dbDate($val) ?: $default; } catch (\Throwable $e) { return $default; }
        }
        return $default;
    }

    public function index(Request $request)
    {
        [$defFrom, $defTo] = $this->defaultRange();
        $from = $this->toBeDbDate($request->get('from'), $defFrom);
        $to   = $this->toBeDbDate($request->get('to'), $defTo);
        $status = $request->get('status', 'unsent'); // all | unsent | sent

        $rows = R506Exporter::baseQuery()
            ->whereBetween('v.date', [$from, $to])
            ->orderBy('v.date')->orderBy('v.id')
            ->get();

        $exporter = new R506Exporter();

        $cases = [];
        $countSelected = 0; $countSent = 0; $countPartialAddr = 0;

        foreach ($rows as $r) {
            $sent = !empty($r->report_506_sent_at);
            if ($status === 'unsent' && $sent) continue;
            if ($status === 'sent' && !$sent) continue;

            $addr = $exporter->addrStatus($r->prov_name, $r->dist_name, $r->sub_name, $r->house_no);
            if ($addr !== 'ok') $countPartialAddr++;
            if (!$sent && $r->report_506) $countSelected++;
            if ($sent) $countSent++;

            $cases[] = [
                'diag_id'  => $r->diag_id,
                'visit_id' => $r->visit_id,
                'patient_id' => $r->patient_id,
                'hn'       => $r->code,
                'name'     => trim($r->name . ' ' . ($r->surname === '-' ? '' : $r->surname)),
                'disease'  => Diagnosi::$diseaseOption[(int) $r->disease] ?? $r->disease,
                'code506'  => R506Exporter::code506((int) $r->disease),
                'code_name'=> R506Exporter::CODE506_NAME[R506Exporter::code506((int) $r->disease)] ?? '',
                'vdate'    => $r->vdate,
                // วันเริ่มป่วย (DATESICK) — ยังไม่ได้ลงก็ใช้วันที่มารับบริการไปก่อน แก้ได้ในตาราง
                'onset'    => Utillity::db2thDate($r->onset_date ?: $r->vdate),
                'onset_set'=> trim((string) $r->onset_date) !== '',
                'addr'     => $addr, // ok | partial | none
                'addr_text'=> trim(implode(' ', array_filter([$r->sub_name, $r->dist_name, $r->prov_name]))),
                'checked'  => (bool) $r->report_506,
                'sent'     => $sent,
                'sent_at'  => $r->report_506_sent_at,
            ];
        }

        return view('r506.index', [
            'cases' => $cases,
            'from' => $from, 'to' => $to, 'status' => $status,
            'fromTh' => Utillity::db2thDate($from), 'toTh' => Utillity::db2thDate($to),
            'countTotal' => count($cases),
            'countSelected' => $countSelected,
            'countSent' => $countSent,
            'countPartialAddr' => $countPartialAddr,
        ]);
    }

    /** บันทึกการติ๊ก (ไม่ export) */
    public function saveSelection(Request $request)
    {
        $this->applySelection($request);
        return back()->with('success', 'บันทึกการเลือกแล้ว');
    }

    /**
     * บันทึกวันที่เริ่มป่วยของ visit หนึ่งใบ (แก้ในตารางแล้วบันทึกทันที)
     * ค่านี้ไปลงช่อง DATESICK ของไฟล์ 506 — ย้ายมาเป็นระดับ visit แล้ว (ไม่ใช่ต่อวินิจฉัย, 2569-08-07)
     * ช่องเดียวกับที่แก้ได้ในหน้าตรวจ (ประวัติการเจ็บป่วยปัจจุบัน)
     */
    public function saveOnset(Request $request)
    {
        $v = Visit::find((int) $request->input('visit_id'));
        if (!$v) {
            return response()->json(['ok' => false, 'msg' => 'ไม่พบ visit'], 404);
        }

        // เคสที่ส่ง 506 ไปแล้ว (วินิจฉัยใดของ visit นี้ก็ตาม) ห้ามแก้ ไม่งั้นไฟล์ที่ส่งไปกับในระบบจะไม่ตรงกัน
        $sent = Diagnosi::where('visit_id', $v->id)->whereNotNull('report_506_sent_at')->exists();
        if ($sent) {
            return response()->json(['ok' => false, 'msg' => 'เคสนี้ส่ง 506 ไปแล้ว แก้ไม่ได้'], 409);
        }

        $val = trim((string) $request->input('value'));
        $v->onset_date = $val === '' ? null : (Utillity::th2dbDate($val) ?: null);
        $v->saveQuietly();   // ไม่ปลุก model event ของ Visit (sync คิวกลาง ฯลฯ) แค่บันทึกวันที่

        return response()->json(['ok' => true, 'onset_set' => $v->onset_date !== null]);
    }

    /** สร้างไฟล์ DBF เฉพาะที่ติ๊ก + ยังไม่ส่ง แล้ว mark ว่าส่งแล้ว */
    public function export(Request $request)
    {
        $this->applySelection($request);

        $from = $request->input('from');
        $to   = $request->input('to');

        $rows = R506Exporter::baseQuery()
            ->whereBetween('v.date', [$from, $to])
            ->where('d.report_506', 1)
            ->whereNull('d.report_506_sent_at')
            ->orderBy('v.date')->orderBy('v.id')
            ->get();

        if ($rows->isEmpty()) {
            return back()->with('error', 'ไม่มีเคสที่ติ๊กไว้ (และยังไม่ส่ง) ในช่วงนี้');
        }

        $exporter = new R506Exporter();
        $built = $exporter->buildRecords($rows, true); // E0 นับต่อจากที่ส่งไปแล้วในปีนั้น

        if (!$built['records']) {
            return back()->with('error', 'ไม่มี record ให้เขียน');
        }

        $beYear = (int) $built['year'];
        $fileName = R506Exporter::fileName($beYear, (int) $built['seqFirst'], (int) $built['seqLast']);

        $dir = storage_path('app/r506');
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $path = $dir . DIRECTORY_SEPARATOR . $fileName;
        $exporter->write($path, $built['records']);

        // mark ว่าส่งแล้ว + เก็บเลข E0 (seq) และปี ต่อ diagnosis (กันส่งซ้ำ + นับต่อปีถัดไป)
        $now = now();
        foreach ($built['diagSeq'] as $diagId => $ys) {
            DB::table('diagnoses')->where('id', $diagId)->update([
                'report_506_sent_at' => $now,
                'report_506_year'    => $ys['year'],
                'report_506_seq'     => $ys['seq'],
            ]);
        }

        return response()->download($path, $fileName, [
            'Content-Type' => 'application/octet-stream',
        ])->deleteFileAfterSend(false);
    }

    /** ตั้งค่า report_506 ตาม checkbox ที่ส่งมา (เฉพาะเคสที่ยังไม่ส่ง) */
    protected function applySelection(Request $request): void
    {
        $from = $request->input('from');
        $to   = $request->input('to');
        $checked = array_map('intval', (array) $request->input('diag', []));

        // ราย diagnosis ที่อยู่ในช่วง + ยังไม่ส่ง (จะเป็นตัวที่ปรับค่าได้)
        // query เบา ๆ ไม่ผ่าน baseQuery() (กัน select alias ชนกับ pluck)
        $editable = DB::table('diagnoses as d')
            ->join('visits as v', 'v.id', '=', 'd.visit_id')
            ->where('v.clinic', 1)
            ->whereIn('d.disease', array_keys(R506Exporter::DISEASE_MAP))
            ->whereBetween('v.date', [$from, $to])
            ->whereNull('d.report_506_sent_at')
            ->pluck('d.id')->map(fn ($id) => (int) $id)->all();

        $on  = array_values(array_intersect($editable, $checked));
        $off = array_values(array_diff($editable, $checked));

        if ($on)  DB::table('diagnoses')->whereIn('id', $on)->update(['report_506' => 1]);
        if ($off) DB::table('diagnoses')->whereIn('id', $off)->update(['report_506' => 0]);
    }
}
