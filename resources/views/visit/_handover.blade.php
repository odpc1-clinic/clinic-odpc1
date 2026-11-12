{{-- ส่งเวร / บันทึกภายใน ระดับคนไข้ — ไม่ต้องมีนัด (คลินิกขอ 2569-10-27)
     ใช้ตาราง patient_notes ตัวเดียวกับป้าย NOTE ลอยขวาบน (ไม่เพิ่มช่องใหม่) — เขียนที่ไหนก็เห็นทั้งสองที่
     เรื่องที่ยังไม่ปิดติดตัวคนไข้ไปทุกครั้งที่มา (แถบบนหัวหน้าตรวจ) จนกว่าจะกด "ปิดเรื่อง"
     ช่องส่งเวรรายนัด (appt_note) ยังอยู่เหมือนเดิม — ดึงมารวมในประวัติด้วย

     $hoMode = 'banner' (แถบบนหน้าตรวจ) | 'block' (กล่องเขียน + ประวัติ ในหมวดวันนัด)
     ทั้งหมดอยู่ในหน้าโหลดเต็ม (edit) — ปุ่มยิง fetch ห้ามใช้ <form> (หมวดวันนัดอยู่ในฟอร์มหลัก) --}}
@php
    $__hoNotes = $patientNotes ?? \App\Models\PatientNote::where('patient_id', $visit->patient_id)->with(['author', 'resolvedByUser'])->orderByDesc('id')->get();
    $__hoOpen  = $__hoNotes->where('is_resolved', false)->values();
    $__hoTh    = fn ($d) => $d ? $d->format('d/m/') . ($d->year + 543) . ' ' . $d->format('H:i') : '';
@endphp

@if(($hoMode ?? 'block') === 'banner')
    <div class="ho-banner p-2 px-3 mb-3 shadow-sm {{ $__hoOpen->isEmpty() ? 'd-none' : '' }}" id="ho-banner">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <span class="fw-bold"><i class="fa fa-bullhorn me-1"></i> ส่งเวรค้างอยู่ <span class="js-ho-count">{{ $__hoOpen->count() }}</span> เรื่อง</span>
            <a href="#ho-box" class="small ms-auto js-ho-jump">ดูประวัติส่งเวร / เขียนเพิ่ม (หมวดวันนัด) ↓</a>
        </div>
        <div class="js-ho-open">
            @foreach($__hoOpen as $__n)
                @include('visit._handover_item', ['n' => $__n, 'th' => $__hoTh])
            @endforeach
        </div>
    </div>
@else
    @php
        // ประวัติ = เรื่องที่ปิดแล้ว + โน้ตส่งเวรที่เคยเขียนกำกับนัด (ทุก visit ของคนไข้)
        $__hoAppt = \App\Models\VisitAppointment::where('visit_appointments.patient_id', $visit->patient_id)
            ->whereNotNull('staff_note')->where('staff_note', '<>', '')
            ->leftJoin('visits', 'visits.id', '=', 'visit_appointments.visit_id')
            ->orderByDesc('visit_appointments.id')->limit(60)
            ->get(['visit_appointments.*', 'visits.date as visit_date', 'visits.clinic as visit_clinic']);
        $__hoUsers = \App\Models\User::whereIn('id', $__hoAppt->pluck('made_by')->filter()->unique())->pluck('name', 'id');
        $__hoHist = collect();
        foreach ($__hoNotes->where('is_resolved', true) as $__n) {
            $__hoHist->push(['t' => $__n->created_at, 'kind' => 'note', 'n' => $__n]);
        }
        foreach ($__hoAppt as $__a) {
            $__hoHist->push(['t' => $__a->created_at, 'kind' => 'appt', 'a' => $__a]);
        }
        $__hoHist = $__hoHist->sortByDesc(fn ($h) => optional($h['t'])->timestamp ?? 0)->values();
    @endphp
    {{-- ไม่มีเรื่องค้างและไม่มีประวัติ = ซ่อนกล่อง (เขียนที่ช่องเทาในตารางวันนัดแทน) --}}
    <div class="ho-box mb-3 {{ $__hoOpen->isEmpty() && $__hoHist->isEmpty() ? 'd-none' : '' }}" id="ho-box" data-no-dirty
         data-store="{{ route('patient.notes.store', $visit->patient_id) }}">
        <div class="ho-head">
            <i class="fa fa-lock me-1"></i> ส่งเวรที่ค้างอยู่
            <span class="fw-normal small">— เขียนส่งเวรที่ <b>ช่องเทาในตารางวันนัด</b> ด้านล่าง ไม่มีวันนัดก็ได้ · เจ้าหน้าที่เห็นเท่านั้น · ติดตัวคนไข้จนกว่าจะกดปิดเรื่อง</span>
        </div>
        <div class="row g-0">
            <div class="col-12 ho-col">
                <div class="small fw-bold mb-1">เรื่องที่ยังค้าง (<span class="js-ho-count">{{ $__hoOpen->count() }}</span>)</div>
                <div class="js-ho-open">
                    @foreach($__hoOpen as $__n)
                        @include('visit._handover_item', ['n' => $__n, 'th' => $__hoTh])
                    @endforeach
                </div>
                <div class="small text-muted js-ho-none {{ $__hoOpen->isEmpty() ? '' : 'd-none' }}">ไม่มีเรื่องค้าง</div>

                <details class="mt-2">
                    <summary class="small fw-bold" style="cursor:pointer;">ประวัติส่งเวร ({{ $__hoHist->count() }})</summary>
                    <div class="ho-hist mt-1">
                        @forelse($__hoHist as $__h)
                            @if($__h['kind'] === 'note')
                                <div class="ho-hist-row">
                                    <div style="white-space:pre-wrap;">{{ $__h['n']->body }}</div>
                                    <div class="small text-muted">
                                        {{ $__h['n']->author->name ?? '-' }} · {{ $__hoTh($__h['n']->created_at) }}
                                        · <span class="text-success">ปิดแล้ว{{ $__h['n']->resolvedByUser ? ' โดย ' . $__h['n']->resolvedByUser->name : '' }}{{ $__h['n']->resolved_at ? ' ' . $__hoTh($__h['n']->resolved_at) : '' }}</span>
                                    </div>
                                </div>
                            @else
                                @php $__a = $__h['a']; @endphp
                                <div class="ho-hist-row">
                                    <div>{{ $__a->staff_note }}</div>
                                    <div class="small text-muted">
                                        <span class="badge" style="background:{{ \App\Models\Visit::$ClinicColor[$__a->visit_clinic] ?? '#64748b' }};">{{ \App\Models\Visit::$ClinicShortName[$__a->visit_clinic] ?? '?' }}</span>
                                        กำกับนัด {{ \App\Utillity::db2thDate($__a->appointment_date) }}
                                        · จาก visit {{ \App\Utillity::db2thDate($__a->visit_date) }}
                                        @if($__hoUsers[$__a->made_by] ?? null) · {{ $__hoUsers[$__a->made_by] }}@endif
                                        @if($__a->closed_at) · <span class="text-success">นัดปิดแล้ว</span>@endif
                                    </div>
                                </div>
                            @endif
                        @empty
                            <div class="small text-muted">ยังไม่มีประวัติ</div>
                        @endforelse
                    </div>
                </details>
            </div>
        </div>
    </div>
@endif
