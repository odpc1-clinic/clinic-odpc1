<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        // "วันนี้": visits.date เก็บเป็น พ.ศ. (Y-m-d) เช่น 2569-07-20 ; created_at เป็น timestamp จริง
        $todayBE = (now()->year + 543) . now()->format('-m-d');
        $todayAD = now()->toDateString();

        $todayVisitIds = \App\Models\Visit::currentClinic()->where('date', $todayBE)->pluck('id');
        $totalVisits = $todayVisitIds->count();

        // เสร็จสิ้น = ลงเวลา finished_at แล้ว หรือ status=6 (กลับบ้าน) ; ที่เหลือ = กำลังรับบริการ
        $finished = \App\Models\Visit::whereIn('id', $todayVisitIds)
            ->where(function ($q) { $q->whereNotNull('finished_at')->orWhere('status', \App\Enums\VisitStatus::Home->value); })
            ->count();
        $inService = max($totalVisits - $finished, 0);

        // แยกเพศ (join patient)
        $bySex = \App\Models\Visit::whereIn('visits.id', $todayVisitIds)
            ->join('patients', 'patients.id', '=', 'visits.patient_id')
            ->selectRaw('SUM(patients.sex = 1) AS male, SUM(patients.sex = 2) AS female')
            ->first();
        $male   = (int) ($bySex->male ?? 0);
        $female = (int) ($bySex->female ?? 0);

        $newPatients  = \App\Models\Patient::whereDate('created_at', $todayAD)->count();
        $apptToday    = \App\Models\Visit::currentClinic()->where('appointment', $todayBE)->count();
        $incomeToday  = (float) \App\Models\Billing::whereIn('visit_id', $todayVisitIds)->sum('net_total');
        $labsToday    = \App\Models\Lab::whereIn('visit_id', $todayVisitIds)->count();

        // คิวที่รอเรียกในระบบคิวกลางวันนี้
        $queueWaiting = \App\Models\QueueSystemQueue::where('status', 'waiting')
            ->whereDate('created_at', $todayAD)->count();

        // รายการนัดวันนี้ (ใครมาแล้ว / ยังไม่มา) — "มาแล้ว" = คนไข้รายนั้นมี visit ของวันนี้
        $camePatientIds = \App\Models\Visit::currentClinic()->where('date', $todayBE)->pluck('patient_id')->unique();
        $todayAppointments = \App\Models\Visit::currentClinic()->where('appointment', $todayBE)
            ->with('patient2')
            ->get()
            ->map(function ($v) use ($camePatientIds) {
                $p = $v->patient2;
                return (object) [
                    'hn'       => $p->code ?? '-',
                    'name'     => trim(($p->name ?? '') . ' ' . ($p->surname ?? '')),
                    'tel'      => $p->tel ?? '',
                    'reason'   => $v->appointment_reason ?: '-',
                    'came'     => $camePatientIds->contains($v->patient_id),
                    'line'     => !empty($p->line_user_id),
                    'reminded' => !empty($v->reminder_sent_at),
                ];
            })
            ->sortBy('came')   // ยังไม่มา ขึ้นก่อน (ต้องตาม)
            ->values();

        $apptCame    = $todayAppointments->where('came', true)->count();
        $apptPending = $todayAppointments->where('came', false)->count();

        return view('home', compact(
            'totalVisits', 'finished', 'inService', 'male', 'female',
            'newPatients', 'apptToday', 'incomeToday', 'labsToday', 'queueWaiting',
            'todayAppointments', 'apptCame', 'apptPending'
        ));
    }

}
