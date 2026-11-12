<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Patient;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function __construct()
    {
        // log รวมทั้งระบบ = Admin เท่านั้น; ส่วนดูเฉพาะเคส (forPatient) เจ้าหน้าที่ที่ล็อกอินดูได้
        $this->middleware('role:Admin')->only('index');
    }

    public function index(Request $request)
    {
        $query = ActivityLog::with('user')->latest('id');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('type')) {
            $query->where('subject_type', $request->type);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('user_name', 'LIKE', "%$q%")
                    ->orWhere('subject_label', 'LIKE', "%$q%")
                    ->orWhere('subject_id', $q);
            });
        }
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $logs = $query->paginate(50)->appends($request->query());

        // ชนิดข้อมูลที่มีใน log จริง (ไว้ทำ dropdown filter)
        $types = ActivityLog::select('subject_type')->distinct()->pluck('subject_type');

        return view('activity_log.index', compact('logs', 'types'));
    }

    // ประวัติการแก้ไข "เฉพาะเคส" ของคนไข้รายนี้ (ครอบคลุมทุก visit/lab/บิล/วินิจฉัย ของเขา)
    public function forPatient($patientId, Request $request)
    {
        $patient = Patient::findOrFail($patientId);

        $query = ActivityLog::with('user')
            ->where('patient_id', $patient->id)
            ->latest('id');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('type')) {
            $query->where('subject_type', $request->type);
        }

        $logs = $query->paginate(50)->appends($request->query());
        $types = ActivityLog::where('patient_id', $patient->id)->select('subject_type')->distinct()->pluck('subject_type');

        return view('activity_log.patient', compact('logs', 'types', 'patient'));
    }
}
