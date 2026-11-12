<?php

namespace App\Http\Controllers;

use App\Models\PatientNote;
use App\Models\PatientNoteRead;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class PatientNoteController extends Controller
{
    public function store(Request $request, $patientId)
    {
        $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $note = PatientNote::create([
            'patient_id' => $patientId,
            'user_id'    => auth()->id(),
            'body'       => $request->input('body'),
        ]);

        // กล่องส่งเวรในหน้าตรวจยิงด้วย fetch (อยู่ในฟอร์มหลัก ห้ามฟอร์มซ้อน) — ตอบ JSON ให้เติมรายการเองไม่ต้องโหลดหน้าใหม่
        if ($request->expectsJson()) {
            // ผู้เขียนถือว่าอ่านแล้ว ไม่งั้นป้าย NOTE ขึ้นเลขยังไม่อ่านของตัวเอง
            PatientNoteRead::create(['patient_note_id' => $note->id, 'user_id' => auth()->id(), 'read_at' => now()]);
            return response()->json(['ok' => true, 'note' => [
                'id'          => $note->id,
                'body'        => $note->body,
                'author'      => auth()->user()->name ?? '-',
                'at'          => $note->created_at->format('d/m/') . ($note->created_at->year + 543) . ' ' . $note->created_at->format('H:i'),
                'resolve_url' => route('patient.notes.resolve', $note->id),
            ]]);
        }

        Alert::success('บันทึก note เรียบร้อยแล้ว');

        return back();
    }

    public function resolve(Request $request, $id)
    {
        $note = PatientNote::findOrFail($id);
        $note->is_resolved = true;
        $note->resolved_by = auth()->id();
        $note->resolved_at = now();
        $note->save();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'by' => auth()->user()->name ?? '-']);
        }

        Alert::success('ปิด note เรียบร้อยแล้ว');

        return back();
    }

    // เรียกผ่าน AJAX ตอนเปิด popup note จริงๆ เท่านั้น (ไม่ใช่ตอนโหลดหน้าเฉยๆ)
    // เพื่อให้ badge เลขที่ยังไม่อ่านนับแยกตามคนอ่านจริง
    public function markRead(Request $request, $patientId)
    {
        $userId = auth()->id();
        $noteIds = PatientNote::where('patient_id', $patientId)->pluck('id');

        $alreadyRead = PatientNoteRead::where('user_id', $userId)
            ->whereIn('patient_note_id', $noteIds)
            ->pluck('patient_note_id');

        $toMark = $noteIds->diff($alreadyRead);

        foreach ($toMark as $noteId) {
            PatientNoteRead::create([
                'patient_note_id' => $noteId,
                'user_id'         => $userId,
                'read_at'         => now(),
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}
