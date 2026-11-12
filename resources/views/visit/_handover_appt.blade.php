{{-- โน้ตส่งเวรกำกับนัด ที่ visit นี้มาตามนัด (VisitAppointment::notesForVisit) — อ่านอย่างเดียว ไม่มีสคริปต์ (2569-11-11)
     $hoApptVisit = visit ที่กำลังเปิด
     $hoWithOpen = true: รวมส่งเวรค้างระดับคนไข้ (ไม่มีวันนัด) ด้วย — ใช้ในหน้าการเงินที่ไม่มีแถบส่งเวรของหน้าตรวจ
     (หน้าตรวจไม่ต้องรวม เพราะแถบเดิมแสดงอยู่แล้ว พร้อมปุ่มปิดเรื่อง) --}}
@php
    $__hoApN = \App\Models\VisitAppointment::notesForVisit($hoApptVisit);
    $__hoOpenN = ! empty($hoWithOpen)
        ? \App\Models\PatientNote::where('patient_id', $hoApptVisit->patient_id)->where('is_resolved', false)->orderByDesc('id')->get()
        : collect();
@endphp
@if($__hoApN->isNotEmpty() || $__hoOpenN->isNotEmpty())
    <div class="p-2 px-3 mb-3 shadow-sm" style="background:#fff7e6;border:2px solid #f59e0b;border-radius:12px;color:#78350f;">
        <div class="fw-bold mb-1"><i class="fa fa-bullhorn me-1"></i>
            {{ $__hoOpenN->isNotEmpty() ? 'ส่งเวรค้างอยู่' : 'ส่งเวรกำกับนัดที่คนไข้มาตามนัด' }} {{ $__hoApN->count() + $__hoOpenN->count() }} เรื่อง
        </div>
        @foreach($__hoOpenN as $__n)
            <div class="bg-white rounded px-2 py-1 mb-1" style="border-left:5px solid #f59e0b;">
                <b>{{ $__n->body }}</b>
                <span class="small text-muted ms-1">({{ optional($__n->created_at)->format('d/m/') }}{{ $__n->created_at ? $__n->created_at->year + 543 : '' }})</span>
            </div>
        @endforeach
        @foreach($__hoApN as $__a)
            <div class="bg-white rounded px-2 py-1 mb-1" style="border-left:5px solid #78909c;">
                <span class="badge bg-secondary me-1">นัด {{ \App\Utillity::db2thDate($__a->appointment_date) }}</span>
                <b>{{ $__a->staff_note }}</b>
                @if($__a->reason)<span class="small text-muted ms-1">(เหตุผลนัด: {{ $__a->reason }})</span>@endif
            </div>
        @endforeach
        @if($__hoOpenN->isNotEmpty())
            <div class="small">ปิดเรื่องได้ที่หน้าตรวจ (หมวดวันนัด)</div>
        @endif
    </div>
@endif
