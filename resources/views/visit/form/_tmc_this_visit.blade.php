{{-- TMC + TB: "มาครั้งนี้เพื่ออะไร" (คลินิกขอ 2569-10-14 · TB ใช้ด้วยตั้งแต่ 2569-10-14 บ่าย — แผนวัคซีนมีแต่ของ TMC) — คนไข้ที่หมอนัดไว้จาก visit ก่อน ให้หน้าตรวจบอกเลยว่านัดวัคซีนอะไร/มาทำอะไร
     ดึงจาก 2 ที่: แผนนัดฉีดวัคซีน (visits.tmc_vaccine_plan) ของ visit TMC ครั้งล่าสุดก่อนหน้า + นัดในระบบ (visit_appointments) ช่วงวันนี้ ±14 วัน
     อ่านอย่างเดียว ไม่มีช่องกรอก · ตัวแปร: $visit --}}
@php
    $__vd = (string) $visit->getRawOriginal('date');
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $__vd, $__m) && (int) $__m[1] < 2500) { $__vd = ((int) $__m[1] + 543) . '-' . $__m[2] . '-' . $__m[3]; }
    $__toBe = function ($d) {   // แผนเก็บ วว/ดด/ปปปป · นัดเก็บ ปปปป-ดด-วว (พ.ศ.)
        $d = trim((string) $d);
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $d, $m)) { return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]); }
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
    };
    $__days = function ($be) use ($__vd) {
        try {
            return (int) \Carbon\Carbon::parse(\App\Utillity::BE2AD($__vd))->diffInDays(\Carbon\Carbon::parse(\App\Utillity::BE2AD($be)), false);
        } catch (\Throwable $e) { return null; }
    };
    $__prevPlanVisit = \App\Models\Visit::where('patient_id', $visit->patient_id)->where('clinic', 3)
        ->where('id', '!=', $visit->id)->where('date', '<=', $__vd)->whereNotNull('tmc_vaccine_plan')
        ->orderByDesc('date')->orderByDesc('id')->first();
    $__plan = $__prevPlanVisit ? \App\Models\VisitVaccine::normalizePlan($__prevPlanVisit->tmc_vaccine_plan) : [];
    // เฉพาะนัดที่วางไว้จาก visit ก่อนหน้า (ไม่เอานัดที่ทำทีหลังจาก visit อื่น)
    $__prevIds = \App\Models\Visit::where('patient_id', $visit->patient_id)->where('id', '!=', $visit->id)->where('date', '<=', $__vd)->pluck('id');
    $__appts = \App\Models\VisitAppointment::where('patient_id', $visit->patient_id)->whereIn('visit_id', $__prevIds)
        ->whereBetween('appointment_date', [
            \App\Utillity::AD2BE(\Carbon\Carbon::parse(\App\Utillity::BE2AD($__vd))->subDays(14)->format('Y-m-d')),
            \App\Utillity::AD2BE(\Carbon\Carbon::parse(\App\Utillity::BE2AD($__vd))->addDays(14)->format('Y-m-d')),
        ])->orderBy('appointment_date')->get();
    // นัดที่ผ่านมาแล้วและคนไข้มาแล้ว (มีการมาระหว่างวันนัดถึงก่อนวันนี้) ไม่ต้องโชว์ — เหลือเฉพาะนัดที่พลาด
    $__cameDates = \App\Models\Visit::where('patient_id', $visit->patient_id)->where('id', '!=', $visit->id)
        ->where('date', '<', $__vd)->pluck('date')->all();
    $__appts = $__appts->reject(fn ($a) => $a->appointment_date < $__vd
        && collect($__cameDates)->contains(fn ($d) => $d >= $a->appointment_date))->values();
@endphp
@if($__plan || $__appts->count())
    <div class="card mb-3" style="border:2px solid #0d9488;">
        <div class="card-header fw-bold" style="background:#ccfbf1; color:#134e4a;">
            <i class="fa fa-calendar-check me-1"></i> นัดครั้งนี้ — มาเพื่ออะไร
            <span class="small fw-normal text-muted ms-1">จากนัดที่วางไว้ครั้งก่อน</span>
        </div>
        <div class="card-body py-2">
            @foreach($__appts as $__a)
                @php $__dd = $__days($__a->appointment_date); @endphp
                <div class="mb-1 {{ $__dd === 0 ? 'fw-bold' : '' }}">
                    <span class="badge {{ $__dd === 0 ? 'bg-success' : 'bg-light text-dark border' }}">
                        {{ $__dd === 0 ? 'นัดวันนี้' : ($__dd > 0 ? 'นัดอีก ' . $__dd . ' วัน' : 'นัดเลยมา ' . abs($__dd) . ' วัน') }}
                    </span>
                    {{ \App\Utillity::db2thDate($__a->appointment_date) }}{{ $__a->appt_time ? ' ' . $__a->appt_time . ' น.' : '' }}
                    — {{ trim((string) $__a->reason) !== '' ? $__a->reason : 'ไม่ระบุเหตุผล' }}
                    @if((int) $__a->closed_visit_id === (int) $visit->id)<span class="small text-success">✓ มาตามนัดนี้แล้ว</span>
                    @elseif($__a->closed_at)<span class="small text-muted">(ปิดนัดแล้ว)</span>@endif
                </div>
            @endforeach
            @if($__plan)
                <div class="small text-muted mt-1">แผนนัดฉีดวัคซีน (วางไว้เมื่อ {{ \App\Utillity::db2thDate($__prevPlanVisit->getRawOriginal('date')) }}):</div>
                <ul class="mb-0 ps-3">
                    @foreach($__plan as $__r)
                        @php $__be = $__toBe($__r['date']); $__dd = $__be ? $__days($__be) : null; @endphp
                        <li class="{{ $__dd === 0 ? 'fw-bold' : ($__dd !== null && $__dd < 0 ? 'text-muted' : '') }}">
                            @if($__dd === 0)<span class="badge bg-success">วันนี้</span>@endif
                            {{ $__r['date'] ?: 'ไม่ระบุวันที่' }} — {{ \App\Models\VisitVaccine::planItemsText($__r['items']) ?: '-' }}{{ \App\Models\VisitVaccine::planSiteText($__r) }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif
