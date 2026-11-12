<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * ความยินยอม PDPA (2569-10-29) — paperless: พยาบาลสัมภาษณ์แล้วติ๊กใน popup แทนการเซ็นกระดาษ
 * หลักฐาน = ใครรับเรื่อง เมื่อไหร่ ฉบับประกาศไหน ผู้ตอบเป็นใคร (+ไฟล์แนบ เช่น หนังสือจากโรงเรียน)
 */
class PatientConsentController extends Controller
{
    public function store(Request $request, $patientId)
    {
        $patient = Patient::findOrFail($patientId);
        $tri = 'nullable|in:0,1';
        $request->validate([
            'line' => $tri, 'photo' => $tri, 'contact' => $tri, 'research' => $tri,
            'research_project' => 'nullable|string|max:255',
            'signer' => 'required|in:' . implode(',', array_keys(PatientConsent::SIGNERS)),
            'signer_name' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
        ]);
        if (! $request->boolean('notice_informed')) {
            return response()->json(['ok' => false, 'message' => 'ต้องแจ้งประกาศความเป็นส่วนตัวให้คนไข้ทราบก่อน (ติ๊กข้อแรก)'], 422);
        }
        if ($request->input('signer') !== 'self' && trim((string) $request->input('signer_name')) === '') {
            return response()->json(['ok' => false, 'message' => 'กรุณาใส่ชื่อผู้ปกครอง / สถานศึกษา'], 422);
        }

        $tv = fn ($k) => $request->input($k) === null || $request->input($k) === '' ? null : (int) $request->input($k);

        // ผู้เยาว์ (ต่ำกว่า 20 ปี) ที่ผู้ปกครองไม่ได้เป็นผู้ยินยอม = ไม่ยินยอมทุกเรื่อง (นโยบายกรม ข้อ 9 · คลินิกเลือก 2569-10-29)
        // หนังสือจากโรงเรียนไม่นับเป็นผู้ใช้อำนาจปกครอง · การตรวจรักษาไม่กระทบ (ไม่ได้ใช้ความยินยอมเป็นฐาน)
        $age = PatientConsent::ageOf($patient);
        $minorNoGuardian = $age !== null && $age < 20 && $request->input('signer') !== 'guardian';
        if ($minorNoGuardian) {
            $orig = $tv;
            $tv = function ($k) use ($orig) {
                if ($k === 'research') return $orig($k) === null ? null : 0;   // ไม่ได้เชิญ = คงไว้
                return 0;
            };
        }
        $photoRemoved = false;

        DB::transaction(function () use ($request, $patient, $tv, &$photoRemoved, &$row) {
            $file = null;
            if ($request->hasFile('attachment')) {
                $f = $request->file('attachment');
                $file = uniqid('consent_') . '.' . strtolower($f->getClientOriginalExtension()) . '.enc';
                Storage::disk('local')->put("private/consents/$file", Crypt::encrypt(file_get_contents($f->getRealPath())));
            }
            $row = PatientConsent::create([
                'patient_id'       => $patient->id,
                'notice_version'   => PatientConsent::NOTICE_VERSION,
                'notice_informed'  => true,
                'line'             => $tv('line'),
                'photo'            => $tv('photo'),
                'contact'          => $tv('contact'),
                'research'         => $tv('research'),
                'research_project' => $tv('research') === null ? null : ($request->input('research_project') ?: null),
                'method'           => 'verbal',
                'signer'           => $request->input('signer'),
                'signer_name'      => $request->input('signer') === 'self' ? null : $request->input('signer_name'),
                'attachment'       => $file,
                'note'             => $request->input('note'),
                'user_id'          => Auth::id(),
            ]);

            // ไม่ยินยอมเก็บรูป = ลบรูปที่มีอยู่ทันที (ไม่ใช่แค่ซ่อน)
            if ($tv('photo') === 0 && $patient->photo) {
                Storage::disk('local')->delete("private/photos/{$patient->photo}");
                $patient->photo = null;
                $patient->save();
                $photoRemoved = true;
            }
        });

        return response()->json([
            'ok'           => true,
            'status'       => $row->status(),
            'photoRemoved' => $photoRemoved,
            'message'      => 'บันทึกความยินยอมแล้ว'
                . ($minorNoGuardian ? ' · ผู้เยาว์ไม่มีผู้ปกครองยินยอม — บันทึกเป็นไม่ยินยอมทุกเรื่อง' : '')
                . ($photoRemoved ? ' · ลบรูปถ่ายของคนไข้แล้ว' : ''),
        ]);
    }

    public function attachment($id)
    {
        $c = PatientConsent::findOrFail($id);
        abort_unless($c->attachment && Storage::disk('local')->exists("private/consents/{$c->attachment}"), 404);
        $data = Crypt::decrypt(Storage::disk('local')->get("private/consents/{$c->attachment}"));
        $ext = strtolower(pathinfo(substr($c->attachment, 0, -4), PATHINFO_EXTENSION));
        $type = ['pdf' => 'application/pdf', 'png' => 'image/png', 'webp' => 'image/webp'][$ext] ?? 'image/jpeg';
        return response($data)->header('Content-Type', $type)->header('Cache-Control', 'no-store');
    }

    /** ประกาศความเป็นส่วนตัว — หน้าสาธารณะ ไม่มีข้อมูลคนไข้ (?lang=en) */
    public function notice(Request $request)
    {
        $lang = $request->query('lang') === 'en' ? 'en' : 'th';
        return view('privacy.notice', ['lang' => $lang, 'version' => PatientConsent::NOTICE_VERSION]);
    }
}
