<?php

namespace App\Http\Controllers;

use App\Models\TbLabFile;
use App\Models\TbRegistration;
use App\Support\ThaiDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * กล่อง "ไฟล์ผลตรวจจากที่อื่น" ในแท็บผลตรวจของบัตร TB 01 (คลินิกขอ 2569-10-05)
 *
 * แยกจาก TbController (ไฟล์ใหญ่ มีหลายงานแก้พร้อมกัน)
 * สิทธิ์เท่ากับการเปิดบัตร TB 01 (ล็อกอินแล้ว) — ทั้งอัปโหลด ดู และลบ
 * ตารางยังไม่ขึ้น (รอ migration) = หน้าบัตรแสดง "รอเปิดใช้" และ route พวกนี้ตอบกลับพร้อมข้อความ ไม่พัง
 */
class TbLabFileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function store(Request $request, $id)
    {
        $reg = TbRegistration::findOrFail($id);
        if (! TbLabFile::ready()) {
            flash('กล่องไฟล์ผลตรวจยังไม่เปิดใช้ (รอผู้ดูแลระบบอัปเดตฐานข้อมูล)', 'warning');
            return $this->back($reg);
        }

        [$v, $date] = $this->validatorFor($request);
        if ($v->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'msg' => implode("\n", $v->errors()->all())], 422);
            }
            return $this->back($reg)->withInput($request->except('files'))->withErrors($v, 'labFiles');
        }

        // แนบเป็นผลของรายการ Lab ส่งตรวจนอกที่หมอสั่ง (ปุ่มท้ายแถวในประวัติ Lab 2569-10-30)
        // ต้องเป็นรายการของคนไข้คนเดียวกันเท่านั้น — กันยิง id ของคนอื่นมา
        $ext = null;
        if ($request->filled('external_lab_id') && TbLabFile::externalReady()) {
            $ext = \App\Models\VisitExternalLab::whereIn('visit_id', \App\Models\Visit::where('patient_id', $reg->patient_id)->select('id'))
                ->find((int) $request->input('external_lab_id'));
            if (! $ext) {
                return response()->json(['ok' => false, 'msg' => 'ไม่พบรายการสั่งตรวจนี้ของคนไข้คนนี้'], 404);
            }
        }

        $saved = 0;
        $skipped = [];
        foreach ($request->file('files') as $file) {
            // mime จากเนื้อไฟล์อีกชั้น — mimes:jpg ยอมรับบางชนิดที่เบราว์เซอร์เปิด inline ไม่ได้
            $mime = (string) $file->getMimeType();
            if (! isset(TbLabFile::MIMES[$mime])) {
                $skipped[] = $file->getClientOriginalName();
                continue;
            }

            $name = TbLabFile::putEncrypted(file_get_contents($file->getRealPath()));
            try {
                TbLabFile::create(($ext ? ['external_lab_id' => $ext->id] : []) + [
                    'tb_registration_id' => $reg->id,
                    'patient_id'         => $reg->patient_id,
                    'test_date'          => $date['be'],
                    // แนบจากแถวสั่งตรวจ: ชื่อรายการ/แล็บมาจากใบสั่ง ไม่ต้องพิมพ์
                    'source'             => self::clean($request->input('source')) ?? ($ext ? self::clean($ext->vendor) : null),
                    'title'              => self::clean($request->input('title')) ?? ($ext ? self::clean(($ext->code ? $ext->code . ' · ' : '') . $ext->name) : null),
                    'note'               => self::clean($request->input('note')),
                    'original_name'      => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime'               => $mime,
                    'size'               => $file->getSize(),
                    'stored_name'        => $name,
                    'uploaded_by'        => Auth::id(),
                ]);
                $saved++;
            } catch (\Throwable $e) {
                // บันทึกแถวไม่ได้ = ไฟล์ที่เขียนไปแล้วไม่มีใครอ้างถึง ลบทิ้ง
                \Illuminate\Support\Facades\Storage::disk('local')->delete(TbLabFile::DIR . '/' . $name);
                throw $e;
            }
        }

        if ($saved && $ext) {
            $ext->markResultByFile($date['be']);
        }
        if ($request->expectsJson()) {
            return response()->json([
                'ok'  => $saved > 0,
                'msg' => $saved ? 'แนบไฟล์ผลแล้ว ' . $saved . ' ไฟล์' : 'ไม่ได้เก็บไฟล์ — ไม่ใช่ PDF/รูป: ' . implode(', ', $skipped),
            ], $saved ? 200 : 422);
        }

        if ($saved) {
            flash('เก็บไฟล์ผลตรวจแล้ว ' . $saved . ' ไฟล์'
                . ($skipped ? ' — ข้ามไฟล์ที่ไม่ใช่ PDF/รูป: ' . implode(', ', $skipped) : ''), 'success');
        } else {
            flash('ไม่ได้เก็บไฟล์ — ไม่ใช่ PDF/รูป: ' . implode(', ', $skipped), 'danger');
        }
        return $this->back($reg);
    }

    /** แยกออกมาให้ทดสอบกฎได้โดยไม่ต้องมีตาราง — คืน [validator, ผล ThaiDate::parse ของวันที่ตรวจ] */
    public function validatorFor(Request $request): array
    {
        $v = Validator::make($request->all(), [
            'files'   => 'required|array|max:20',
            // mimes ตรวจจากเนื้อไฟล์ (ไม่เชื่อนามสกุล) — .exe ที่เปลี่ยนชื่อเป็น .jpg ก็ไม่ผ่าน
            'files.*' => 'file|max:' . TbLabFile::MAX_KB . '|mimes:' . implode(',', array_unique(array_merge(array_values(TbLabFile::MIMES), ['jpeg']))),
            'source'  => 'nullable|string|max:150',
            'title'   => 'nullable|string|max:150',
            'note'    => 'nullable|string|max:500',
        ], [
            'files.required' => 'ยังไม่ได้เลือกไฟล์',
            'files.max'      => 'ส่งได้ครั้งละไม่เกิน 20 ไฟล์',
            'files.*.max'    => 'ไฟล์ ":attribute" ใหญ่เกิน 10 MB',
            'files.*.mimes'  => 'ไฟล์ ":attribute" ไม่ใช่ PDF / JPG / PNG / WEBP',
            'files.*.file'   => 'ไฟล์ ":attribute" อัปโหลดไม่สำเร็จ ลองใหม่อีกครั้ง',
        ]);
        // ให้ข้อความบอกชื่อไฟล์จริง ไม่ใช่ files.0 — พยาบาลเลือกทีละหลายไฟล์ ต้องรู้ว่าไฟล์ไหนไม่ผ่าน
        $names = [];
        foreach ((array) $request->file('files') as $i => $f) {
            if ($f) { $names["files.$i"] = $f->getClientOriginalName(); }
        }
        $v->setAttributeNames($names);

        $date = ThaiDate::parse($request->input('test_date'), ['allowEmpty' => true, 'notFuture' => true]);
        $v->after(function ($v) use ($date) {
            if (! $date['ok']) { $v->errors()->add('test_date', 'วันที่ตรวจ: ' . $date['error']); }
        });

        return [$v, $date];
    }

    /** เปิดดูไฟล์ (ถอดรหัสแล้วส่งแบบ inline) · ?thumb=1 = รูปย่อสำหรับรายการ ไม่ต้องโหลดรูปเต็มทุกใบ */
    public function show(Request $request, $id, $fileId)
    {
        abort_unless(TbLabFile::ready(), 404);
        $reg = TbRegistration::findOrFail($id);
        // ไฟล์ที่อัปจากหน้าตรวจของคนไข้คนเดียวกันเปิดจากบัตรได้ด้วย (ดู tb/_lab_files)
        $f = TbLabFile::where(function ($q) use ($reg) {
            $q->where('tb_registration_id', $reg->id);
            if ($reg->patient_id) { $q->orWhere('patient_id', $reg->patient_id); }
        })->findOrFail($fileId);
        abort_unless($f->fileExists(), 404);

        return self::inlineResponse($request, $f);
    }

    /** ส่งไฟล์ถอดรหัสแบบ inline — ใช้ร่วมกับ VisitLabFileController */
    public static function inlineResponse(Request $request, TbLabFile $f)
    {
        $raw = $f->readDecrypted();
        $mime = $f->mime;

        if ($request->boolean('thumb') && $f->isImage() && function_exists('imagecreatefromstring')) {
            $thumb = self::thumbnail($raw);
            if ($thumb !== null) {
                [$raw, $mime] = [$thumb, 'image/jpeg'];
            }
        }

        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $f->original_name) ?: 'file';
        return response($raw)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', "inline; filename=\"{$fallback}\"; filename*=UTF-8''" . rawurlencode((string) $f->original_name))
            ->header('X-Content-Type-Options', 'nosniff')
            // ข้อมูลคนไข้ — ห้าม proxy/เครื่องกลางเก็บแคช
            ->header('Cache-Control', 'private, max-age=600');
    }

    /** แก้ข้อมูลกำกับไฟล์ (พิมพ์ผิด) จากบัตร TB 01 — ไฟล์ที่อัปจากหน้าตรวจของคนไข้คนเดียวกันก็แก้ได้ */
    public function update(Request $request, $id, $fileId)
    {
        $reg = TbRegistration::findOrFail($id);
        abort_unless(TbLabFile::ready(), 404);
        $f = TbLabFile::where(function ($q) use ($reg) {
            $q->where('tb_registration_id', $reg->id);
            if ($reg->patient_id) { $q->orWhere('patient_id', $reg->patient_id); }
        })->find($fileId);
        if (! $f) {
            return response()->json(['ok' => false, 'msg' => 'ไม่พบไฟล์ (อาจถูกลบไปแล้ว)'], 404);
        }
        if ($err = $f->applyEdit($request->only(['test_date', 'source', 'title', 'note']))) {
            return response()->json(['ok' => false, 'msg' => $err], 422);
        }
        return response()->json(['ok' => true]);
    }

    public function destroy($id, $fileId)
    {
        $reg = TbRegistration::findOrFail($id);
        abort_unless(TbLabFile::ready(), 404);
        $f = TbLabFile::where('tb_registration_id', $reg->id)->findOrFail($fileId);
        $label = $f->title ?: $f->original_name;
        $f->delete();   // ลบไฟล์บนดิสก์ใน TbLabFile::booted

        flash('ลบไฟล์ "' . $label . '" แล้ว', 'success');
        return $this->back($reg);
    }

    public static function clean($s): ?string
    {
        $s = trim((string) $s);
        return $s === '' ? null : $s;
    }

    /** ย่อรูปเหลือด้านยาวสุด 320px — คืน null ถ้า GD อ่านรูปไม่ได้ (ส่งรูปเต็มแทน) */
    private static function thumbnail(string $raw): ?string
    {
        $src = @imagecreatefromstring($raw);
        if (! $src) { return null; }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, 320 / max($w, $h, 1));
        $dst = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        // พื้นขาว — PNG โปร่งใสแปลงเป็น JPEG แล้วจะได้ไม่ดำ
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
        ob_start();
        imagejpeg($dst, null, 75);
        $out = ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);
        return $out ?: null;
    }

    /** กลับไปที่กล่องในบัตร — ใช้ referer ถ้ามาจากเครื่องเดียวกัน (คง ?query ของหน้าเดิมไว้) แบบ TbController::backToSection */
    private function back(TbRegistration $reg)
    {
        $base = route('tb.show', $reg->id);
        $ref  = (string) request()->headers->get('referer');
        if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === request()->getHost()
            && strpos(parse_url($ref, PHP_URL_PATH) ?: '', '/tb/' . $reg->id) !== false) {
            $base = strtok($ref, '#');
        }
        return redirect()->to($base . '#tb-lab-files');
    }
}
