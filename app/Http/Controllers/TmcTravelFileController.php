<?php

namespace App\Http\Controllers;

use App\Models\TmcTravelFile;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * ไฟล์แนบแผนการเดินทาง TMC ในหน้าตรวจ (2569-10-07)
 *
 * กล่องอัปโหลดอยู่ "ข้างใน" ฟอร์มหลักของหน้าตรวจ ทำเป็น <form> ไม่ได้ (ฟอร์มซ้อน = ไปกด Save ฟอร์มหลักแทน)
 * หน้าเว็บจึงส่งด้วย fetch แล้วเอา HTML รายการที่ตอบกลับไปแทนที่ในหน้า — ข้อมูลที่กรอกค้างไว้ในฟอร์มหลักไม่หาย
 * ตอบเป็น JSON ทุกกรณี (รวมตอนตารางยังไม่ขึ้น) หน้าเว็บจะได้แสดงข้อความได้ ไม่ใช่หน้า error
 */
class TmcTravelFileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function store(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        if (! TmcTravelFile::ready()) {
            return response()->json(['ok' => false, 'msg' => 'กล่องไฟล์แนบยังไม่เปิดใช้ (รอผู้ดูแลระบบอัปเดตฐานข้อมูล)'], 422);
        }

        $v = $this->validatorFor($request);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'msg' => implode(' · ', $v->errors()->all())], 422);
        }

        $saved = 0;
        $skipped = [];
        $title = trim((string) $request->input('title'));
        foreach ($request->file('files') as $file) {
            // ตรวจ mime จากเนื้อไฟล์อีกชั้น — ชนิดที่เบราว์เซอร์เปิด inline ไม่ได้ไม่รับ
            $mime = (string) $file->getMimeType();
            if (! isset(TmcTravelFile::MIMES[$mime])) {
                $skipped[] = $file->getClientOriginalName();
                continue;
            }
            $name = TmcTravelFile::putEncrypted(file_get_contents($file->getRealPath()));
            try {
                TmcTravelFile::create([
                    'visit_id'      => $visit->id,
                    'patient_id'    => $visit->patient_id,
                    'title'         => $title === '' ? null : mb_substr($title, 0, 150),
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime'          => $mime,
                    'size'          => $file->getSize(),
                    'stored_name'   => $name,
                    'uploaded_by'   => Auth::id(),
                ]);
                $saved++;
            } catch (\Throwable $e) {
                // บันทึกแถวไม่ได้ = ไฟล์ที่เขียนไปแล้วไม่มีใครอ้างถึง ลบทิ้ง
                Storage::disk('local')->delete(TmcTravelFile::DIR . '/' . $name);
                throw $e;
            }
        }

        $msg = $saved
            ? 'เก็บไฟล์แล้ว ' . $saved . ' ไฟล์' . ($skipped ? ' — ข้ามไฟล์ที่ไม่ใช่ PDF/รูป: ' . implode(', ', $skipped) : '')
            : 'ไม่ได้เก็บไฟล์ — ไม่ใช่ PDF/รูป: ' . implode(', ', $skipped);

        return response()->json(['ok' => $saved > 0, 'msg' => $msg, 'html' => $this->listHtml($visit)], $saved ? 200 : 422);
    }

    /** แยกออกมาให้ทดสอบกฎได้โดยไม่ต้องมีตาราง */
    public function validatorFor(Request $request)
    {
        $v = Validator::make($request->all(), [
            'files'   => 'required|array|max:10',
            'files.*' => 'file|max:' . TmcTravelFile::MAX_KB . '|mimes:' . implode(',', array_unique(array_merge(array_values(TmcTravelFile::MIMES), ['jpeg']))),
            'title'   => 'nullable|string|max:150',
        ], [
            'files.required' => 'ยังไม่ได้เลือกไฟล์',
            'files.max'      => 'ส่งได้ครั้งละไม่เกิน 10 ไฟล์',
            'files.*.max'    => 'ไฟล์ ":attribute" ใหญ่เกิน 10 MB',
            'files.*.mimes'  => 'ไฟล์ ":attribute" ไม่ใช่ PDF / JPG / PNG / WEBP',
            'files.*.file'   => 'ไฟล์ ":attribute" อัปโหลดไม่สำเร็จ ลองใหม่อีกครั้ง',
        ]);
        // บอกชื่อไฟล์จริง ไม่ใช่ files.0 — เลือกทีละหลายไฟล์ ต้องรู้ว่าไฟล์ไหนไม่ผ่าน
        $names = [];
        foreach ((array) $request->file('files') as $i => $f) {
            if ($f) { $names["files.$i"] = $f->getClientOriginalName(); }
        }
        $v->setAttributeNames($names);
        return $v;
    }

    /** เปิดดูไฟล์ (ถอดรหัสแล้วส่งแบบ inline) — ไฟล์ของ visit อื่นของคนไข้คนเดียวกันเปิดได้ (เอกสารเดินทางใช้ต่อข้าม visit) */
    public function show($visitId, $fileId)
    {
        abort_unless(TmcTravelFile::ready(), 404);
        $visit = Visit::findOrFail($visitId);
        $f = TmcTravelFile::where('patient_id', $visit->patient_id)->findOrFail($fileId);
        abort_unless($f->fileExists(), 404);

        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $f->original_name) ?: 'file';
        return response($f->readDecrypted())
            ->header('Content-Type', $f->mime)
            ->header('Content-Disposition', "inline; filename=\"{$fallback}\"; filename*=UTF-8''" . rawurlencode((string) $f->original_name))
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'private, max-age=600');
    }

    /** ลบได้เฉพาะไฟล์ของ visit นี้ — ไฟล์ของ visit เก่าให้ไปลบที่ visit นั้น กันลบเอกสารที่อีกครั้งใช้อ้างอิงอยู่โดยไม่ตั้งใจ */
    public function destroy($visitId, $fileId)
    {
        $visit = Visit::findOrFail($visitId);
        if (! TmcTravelFile::ready()) {
            return response()->json(['ok' => false, 'msg' => 'กล่องไฟล์แนบยังไม่เปิดใช้'], 422);
        }
        $f = TmcTravelFile::where('visit_id', $visit->id)->find($fileId);
        if (! $f) {
            return response()->json(['ok' => false, 'msg' => 'ไม่พบไฟล์ (อาจถูกลบไปแล้ว)', 'html' => $this->listHtml($visit)], 404);
        }
        $label = $f->title ?: $f->original_name;
        $f->delete();   // ลบไฟล์บนดิสก์ใน TmcTravelFile::booted

        return response()->json(['ok' => true, 'msg' => 'ลบไฟล์ "' . $label . '" แล้ว', 'html' => $this->listHtml($visit)]);
    }

    private function listHtml(Visit $visit): string
    {
        return view('visit.form._tmc_travel_files_list', ['visit' => $visit])->render();
    }
}
