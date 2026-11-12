<?php

namespace App\Http\Controllers;

use App\Models\TbLabFile;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * ไฟล์ผลตรวจจากที่อื่น ในหน้าตรวจ — ทุกคลินิก (2569-10-07) ใช้ตาราง/กฎเดียวกับบัตร TB (TbLabFile)
 *
 * กล่องอยู่ "ข้างใน" ฟอร์มหลักของหน้าตรวจ ทำเป็น <form> ไม่ได้ จึงส่งด้วย fetch แล้วตอบ JSON + HTML รายการ
 * (แบบเดียวกับ TmcTravelFileController) ข้อมูลที่กรอกค้างในฟอร์มหลักไม่หาย
 */
class VisitLabFileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function store(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        if (! TbLabFile::visitReady()) {
            return response()->json(['ok' => false, 'msg' => 'กล่องไฟล์ผลตรวจยังไม่เปิดใช้ (รอผู้ดูแลระบบอัปเดตฐานข้อมูล)'], 422);
        }

        [$v, $date] = app(TbLabFileController::class)->validatorFor($request);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'msg' => implode(' · ', $v->errors()->all())], 422);
        }

        $saved = 0;
        $skipped = [];
        foreach ($request->file('files') as $file) {
            $mime = (string) $file->getMimeType();
            if (! isset(TbLabFile::MIMES[$mime])) {
                $skipped[] = $file->getClientOriginalName();
                continue;
            }
            $name = TbLabFile::putEncrypted(file_get_contents($file->getRealPath()));
            try {
                TbLabFile::create([
                    'visit_id'      => $visit->id,
                    'patient_id'    => $visit->patient_id,
                    'test_date'     => $date['be'],
                    'source'        => TbLabFileController::clean($request->input('source')),
                    'title'         => TbLabFileController::clean($request->input('title')),
                    'note'          => TbLabFileController::clean($request->input('note')),
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime'          => $mime,
                    'size'          => $file->getSize(),
                    'stored_name'   => $name,
                    'uploaded_by'   => Auth::id(),
                ]);
                $saved++;
            } catch (\Throwable $e) {
                Storage::disk('local')->delete(TbLabFile::DIR . '/' . $name);
                throw $e;
            }
        }

        $msg = $saved
            ? 'เก็บไฟล์ผลตรวจแล้ว ' . $saved . ' ไฟล์' . ($skipped ? ' — ข้ามไฟล์ที่ไม่ใช่ PDF/รูป: ' . implode(', ', $skipped) : '')
            : 'ไม่ได้เก็บไฟล์ — ไม่ใช่ PDF/รูป: ' . implode(', ', $skipped);

        return response()->json(['ok' => $saved > 0, 'msg' => $msg, 'html' => $this->listHtml($visit)], $saved ? 200 : 422);
    }

    /** เปิดดูได้ทุกไฟล์ของคนไข้คนเดียวกัน (รวมที่อัปจากบัตร TB และ visit ก่อน ๆ) */
    public function show(Request $request, $visitId, $fileId)
    {
        abort_unless(TbLabFile::visitReady(), 404);
        $visit = Visit::findOrFail($visitId);
        $f = TbLabFile::where('patient_id', $visit->patient_id)->findOrFail($fileId);
        abort_unless($f->fileExists(), 404);
        return TbLabFileController::inlineResponse($request, $f);
    }

    /** แก้ข้อมูลกำกับไฟล์ (พิมพ์ผิด) — แก้ได้ทุกไฟล์ของคนไข้คนเดียวกัน (TbLabFile::applyEdit) */
    public function update(Request $request, $visitId, $fileId)
    {
        abort_unless(TbLabFile::visitReady(), 404);
        $visit = Visit::findOrFail($visitId);
        $f = TbLabFile::where('patient_id', $visit->patient_id)->find($fileId);
        if (! $f) {
            return response()->json(['ok' => false, 'msg' => 'ไม่พบไฟล์ (อาจถูกลบไปแล้ว)', 'html' => $this->listHtml($visit)], 404);
        }
        if ($err = $f->applyEdit($request->only(['test_date', 'source', 'title', 'note']))) {
            return response()->json(['ok' => false, 'msg' => $err], 422);
        }
        return response()->json(['ok' => true, 'msg' => 'แก้ข้อมูลไฟล์ "' . ($f->title ?: $f->original_name) . '" แล้ว', 'html' => $this->listHtml($visit)]);
    }

    /** ลบได้เฉพาะไฟล์ที่อัปใน visit นี้ — ของ visit เก่า/บัตร TB ให้ไปลบที่ต้นทาง กันลบผลที่อีกที่ใช้อ้างอิงโดยไม่ตั้งใจ */
    public function destroy($visitId, $fileId)
    {
        $visit = Visit::findOrFail($visitId);
        abort_unless(TbLabFile::visitReady(), 404);
        $f = TbLabFile::where('visit_id', $visit->id)->find($fileId);
        if (! $f) {
            return response()->json(['ok' => false, 'msg' => 'ไม่พบไฟล์ (อาจถูกลบไปแล้ว)', 'html' => $this->listHtml($visit)], 404);
        }
        $label = $f->title ?: $f->original_name;
        $f->delete();   // ลบไฟล์บนดิสก์ใน TbLabFile::booted

        return response()->json(['ok' => true, 'msg' => 'ลบไฟล์ "' . $label . '" แล้ว', 'html' => $this->listHtml($visit)]);
    }

    private function listHtml(Visit $visit): string
    {
        return view('visit.form._lab_files_list', ['visit' => $visit])->render();
    }
}
