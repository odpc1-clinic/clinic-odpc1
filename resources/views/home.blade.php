@extends('layouts.app')

@section('content')
@php
    $thMonths = \App\Utillity::TH_MONTH_FULL;
    $thDays = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
    $now = now();
    $thaiDate = 'วัน' . $thDays[$now->dayOfWeek] . 'ที่ ' . $now->format('j') . ' ' . $thMonths[(int)$now->format('n')] . ' ' . ($now->year + 543);
@endphp

<style>
    .dash-hero{ background:linear-gradient(135deg,#047857 0%,#059669 55%,#10b981 100%); border-radius:18px; color:#fff; }
    .stat{ border:0; border-radius:16px; overflow:hidden; transition:.18s; height:100%; }
    .stat:hover{ transform:translateY(-3px); box-shadow:0 16px 34px -16px rgba(15,40,30,.4); }
    .stat .ico{ width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; }
    .stat .num{ font-size:2rem; font-weight:800; line-height:1; }
    .stat .lbl{ color:#64748b; font-weight:600; font-size:.9rem; }
    .qlink{ text-decoration:none; border:1px solid #e6ebf1; border-radius:14px; padding:16px; display:flex; align-items:center; gap:12px; color:#334155; background:#fff; transition:.18s; height:100%; }
    .qlink:hover{ border-color:#10b981; background:#f0fdf4; color:#047857; transform:translateY(-2px); }
    .qlink i{ font-size:1.35rem; }
</style>

<div class="container-fluid py-4">

    {{-- Hero --}}
    <div class="dash-hero p-4 mb-4 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h3 class="mb-1 fw-bold"><i class="fas fa-gauge-high me-2"></i>แดชบอร์ดวันนี้</h3>
            <div class="opacity-75">{{ $thaiDate }} · สวัสดี {{ auth()->user()->name ?? '' }}</div>
        </div>
        <div class="text-end">
            <div class="opacity-75 small">ผู้ป่วยวันนี้</div>
            <div class="fw-bold" style="font-size:2.4rem; line-height:1;">{{ number_format($totalVisits) }}</div>
        </div>
    </div>

    {{-- Stat cards --}}
    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['ผู้ป่วยวันนี้', $totalVisits, 'fa-users', '#059669', '#d1fae5'],
                ['กำลังรับบริการ', $inService, 'fa-person-walking', '#d97706', '#fef3c7'],
                ['เสร็จ / กลับบ้าน', $finished, 'fa-house-circle-check', '#2563eb', '#dbeafe'],
                ['นัดหมายวันนี้', $apptToday, 'fa-calendar-check', '#7c3aed', '#ede9fe'],
                ['ลงทะเบียนใหม่วันนี้', $newPatients, 'fa-user-plus', '#0891b2', '#cffafe'],
                ['Lab วันนี้', $labsToday, 'fa-vial', '#0d9488', '#ccfbf1'],
                ['คิวรอเรียก', $queueWaiting, 'fa-bullhorn', '#dc2626', '#fee2e2'],
            ];
        @endphp
        @foreach($cards as [$label, $value, $icon, $color, $bg])
        <div class="col-6 col-md-4 col-xl-3">
            <div class="stat card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="ico" style="background:{{ $bg }}; color:{{ $color }};"><i class="fas {{ $icon }}"></i></div>
                    <div>
                        <div class="num" style="color:{{ $color }};">{{ number_format($value) }}</div>
                        <div class="lbl">{{ $label }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach

        {{-- รายได้วันนี้ (เด่นกว่า) --}}
        <div class="col-6 col-md-4 col-xl-3">
            <div class="stat card shadow-sm h-100" style="background:linear-gradient(135deg,#065f46,#059669); color:#fff;">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="ico" style="background:rgba(255,255,255,.2); color:#fff;"><i class="fas fa-sack-dollar"></i></div>
                    <div>
                        <div class="num text-white">{{ number_format($incomeToday, 0) }} <small style="font-size:1rem;">฿</small></div>
                        <div class="lbl text-white-50">รายได้วันนี้</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- รายการนัดวันนี้ --}}
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
            <h6 class="fw-bold text-secondary mb-0">
                <i class="fas fa-calendar-check text-primary me-1"></i> นัดหมายวันนี้
                <span class="badge bg-primary ms-1">{{ $apptToday }}</span>
            </h6>
            <div class="small">
                <span class="badge bg-success">มาแล้ว {{ $apptCame }}</span>
                <span class="badge bg-danger ms-1">ยังไม่มา {{ $apptPending }}</span>
                <a href="{{ route('visit.appointment') }}" class="btn btn-outline-primary btn-sm ms-2">
                    <i class="fas fa-list"></i> ดูทั้งหมด
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height:380px; overflow-y:auto;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light" style="position:sticky; top:0; z-index:1;">
                        <tr>
                            <th style="width:110px;">HN</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>เหตุผลการนัด</th>
                            <th style="width:130px;">โทรศัพท์</th>
                            <th class="text-center" style="width:115px;">สถานะ</th>
                            <th class="text-center" style="width:110px;">LINE</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($todayAppointments as $a)
                        <tr class="{{ $a->came ? 'table-light text-muted' : '' }}">
                            <td class="fw-bold text-info">{{ $a->hn }}</td>
                            <td>{{ $a->name }}</td>
                            <td class="small">{{ $a->reason }}</td>
                            <td class="small">{{ $a->tel }}</td>
                            <td class="text-center">
                                @if($a->came)
                                    <span class="badge bg-success"><i class="fas fa-check"></i> มาแล้ว</span>
                                @else
                                    <span class="badge bg-danger">ยังไม่มา</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($a->line)
                                    <span class="badge {{ $a->reminded ? 'bg-success' : 'bg-secondary' }}"
                                          title="{{ $a->reminded ? 'ส่งข้อความเตือนแล้ว' : 'ผูก LINE แล้ว (ยังไม่ได้ส่งเตือน)' }}">
                                        <i class="fab fa-line"></i> {{ $a->reminded ? 'เตือนแล้ว' : 'ผูกแล้ว' }}
                                    </span>
                                @else
                                    <span class="text-muted small" title="ยังไม่ได้ผูก LINE">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="fas fa-calendar-xmark fa-2x d-block mb-2 opacity-50"></i> ไม่มีนัดหมายวันนี้
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- แยกเพศ + ทางลัด --}}
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-body">
                    <h6 class="fw-bold text-secondary mb-3"><i class="fas fa-venus-mars me-1"></i> ผู้ป่วยวันนี้ แยกเพศ</h6>
                    @php $sexTot = max($male + $female, 1); @endphp
                    <div class="d-flex justify-content-between mb-1"><span><i class="fas fa-mars text-primary"></i> ชาย</span><b>{{ number_format($male) }}</b></div>
                    <div class="progress mb-3" style="height:8px;"><div class="progress-bar bg-primary" style="width: {{ $male/$sexTot*100 }}%"></div></div>
                    <div class="d-flex justify-content-between mb-1"><span><i class="fas fa-venus text-danger"></i> หญิง</span><b>{{ number_format($female) }}</b></div>
                    <div class="progress" style="height:8px;"><div class="progress-bar bg-danger" style="width: {{ $female/$sexTot*100 }}%"></div></div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-body">
                    <h6 class="fw-bold text-secondary mb-3"><i class="fas fa-bolt me-1"></i> ทางลัด</h6>
                    <div class="row g-2">
                        <div class="col-6 col-lg-3"><a class="qlink" href="{{ route('patient.index') }}"><i class="fas fa-address-card text-success"></i> ทะเบียนผู้ป่วย</a></div>
                        <div class="col-6 col-lg-3"><a class="qlink" href="{{ route('visit.appointment') }}"><i class="fas fa-calendar-check text-primary"></i> นัดหมาย</a></div>
                        <div class="col-6 col-lg-3"><a class="qlink" href="{{ route('pharmacy.index') }}"><i class="fas fa-pills text-danger"></i> ห้องยา</a></div>
                        <div class="col-6 col-lg-3"><a class="qlink" href="{{ route('finance.index') }}"><i class="fas fa-cash-register text-warning"></i> ห้องการเงิน</a></div>
                        <div class="col-6 col-lg-3"><a class="qlink" href="{{ route('lab.index') }}"><i class="fas fa-vial text-info"></i> ห้อง Lab</a></div>
                        <div class="col-6 col-lg-3"><a class="qlink" href="{{ route('rooms.index') }}"><i class="fas fa-grip text-secondary"></i> ปุ่มลัดเข้าห้อง</a></div>
                        <div class="col-6 col-lg-3"><a class="qlink" href="{{ route('roadmap') }}" target="_blank"><i class="fas fa-timeline text-success"></i> เส้นทางระบบคลินิก</a></div>
                        @hasanyrole('Admin|Nurse|Doctor')
                        <div class="col-6 col-lg-3"><a class="qlink" href="{{ route('report.explore') }}"><i class="fas fa-magnifying-glass-chart text-primary"></i> ดึงรายชื่อคนไข้เอง</a></div>
                        @endhasanyrole
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center text-muted small mt-4">
        ข้อมูล ณ {{ $now->format('H:i') }} น. · นับ "วันนี้" จากวันที่มารับบริการ (Visit) และวันที่ลงทะเบียน
    </div>
</div>
@endsection
