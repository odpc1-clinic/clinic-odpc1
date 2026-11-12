@extends('layouts.app')

{{-- ทะเบียนวัณโรค — รายชื่อบัตร TB 01 ทั้งหมด
     ค่าเริ่มต้นแสดงเฉพาะ "กำลังรักษา" เพราะเป็นงานที่พยาบาลเปิดดูทุกวัน --}}

@section('content')
<style>
    .tb-hero { background: linear-gradient(135deg,#b45309 0%,#f59e0b 100%); color:#fff; border-radius:.75rem; }
    .tb-pill { border-radius:999px; padding:.35rem .9rem; font-weight:600; text-decoration:none; display:inline-block; }
    .tb-pill.off { background:#fff; color:#b45309; border:1px solid #f0c088; }
    .tb-pill.on  { background:#b45309; color:#fff; }
</style>

<div class="container">
    <div class="tb-hero p-3 mb-3 shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <div class="opacity-75 small"><i class="fa-solid fa-lungs me-1"></i> คลินิกวัณโรค</div>
            <h4 class="mb-0 fw-bold">ทะเบียนวัณโรค (TB 01)</h4>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            {{-- วันที่ผิดรูปแบบในบัตร (ปี ค.ศ./อนาคต) ให้พยาบาลไล่แก้ — 2569-10-05 --}}
            @php $__badDates = \App\Http\Controllers\TbDateCheckController::count(); @endphp
            @if($__badDates)
                <a href="{{ route('tb.dateCheck') }}" class="btn btn-danger shadow-sm">
                    <i class="fa fa-calendar-xmark"></i> วันที่ผิดรูปแบบ ({{ $__badDates }})
                </a>
            @endif
            <a href="{{ route('tb.create') }}" class="btn btn-light shadow-sm">
                <i class="fa fa-plus"></i> เปิดบัตรใหม่
            </a>
        </div>
    </div>

    {{-- ตัวกรองประเภท: ตัวเลขคือจำนวนที่ "ยังรักษาอยู่" --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('tb.index', ['status' => $status]) }}"
           class="tb-pill {{ $type === '' ? 'on' : 'off' }}">ทั้งหมด {{ $counts['all'] }}</a>
        @foreach(\App\Models\TbRegistration::$typeOption as $k => $label)
            <a href="{{ route('tb.index', ['type' => $k, 'status' => $status]) }}"
               class="tb-pill {{ (string) $type === (string) $k ? 'on' : 'off' }}">{{ $label }} {{ $counts[$k] }}</a>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="GET" action="{{ route('tb.index') }}" class="row g-2 align-items-end mb-3">
                <input type="hidden" name="type" value="{{ $type }}">
                <div class="col-md-5">
                    <label class="form-label small text-muted mb-1">ค้นหา</label>
                    <input type="text" name="search" value="{{ $search }}" class="form-control"
                           placeholder="เลขทะเบียน TB / HN / ชื่อ-สกุล">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">สถานะ</label>
                    <select name="status" class="form-select">
                        <option value="active" {{ $status === 'active' ? 'selected' : '' }}>กำลังรักษา</option>
                        <option value="done"   {{ $status === 'done' ? 'selected' : '' }}>จบการรักษาแล้ว ({{ $counts['done'] }})</option>
                        <option value="all"    {{ $status === 'all' ? 'selected' : '' }}>ทั้งหมด</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button class="btn btn-primary"><i class="fa fa-magnifying-glass"></i> ค้นหา</button>
                    <a href="{{ route('tb.index') }}" class="btn btn-outline-secondary">ล้าง</a>
                </div>
            </form>

            @if($rows->isEmpty())
                <div class="text-muted">ยังไม่มีบัตรในเงื่อนไขนี้</div>
            @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:8rem">เลขทะเบียน</th>
                            <th style="width:7rem">ประเภท</th>
                            <th>คนไข้</th>
                            <th style="width:8rem">เริ่มรักษา</th>
                            <th style="width:6rem">เดือนที่</th>
                            <th style="width:10rem">สูตรยา</th>
                            <th style="width:10rem">นัดครั้งถัดไป</th>
                            <th style="width:12rem">สถานะ</th>
                            <th style="width:4rem"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($rows as $r)
                        @php $pt = $r->patient; @endphp
                        <tr>
                            <td class="fw-bold">{{ $r->tb_no ?: '—' }}</td>
                            <td>
                                @php $badge = [1 => 'bg-warning text-dark', 2 => 'bg-info text-dark', 3 => 'bg-danger'][$r->registry_type] ?? 'bg-secondary'; @endphp
                                <span class="badge {{ $badge }}">{{ ['', 'TB', 'LTBI', 'MDR'][$r->registry_type] ?? '-' }}</span>
                            </td>
                            <td>
                                {{ $pt->name ?? '-' }} {{ $pt->surname ?? '' }}
                                <div class="small text-muted">HN {{ $pt->code ?? '-' }}</div>
                            </td>
                            <td>{{ $r->treatment_start_date ? \App\Utillity::db2thDate($r->treatment_start_date) : '—' }}</td>
                            <td class="text-center">{{ $r->treatment_start_date ? $r->monthsOnTreatment() : '—' }}</td>
                            <td class="small">{{ $r->regimen ?: '—' }}</td>
                            {{-- นัดครั้งถัดไป (เฉพาะนัดที่ยังไม่ถูกปิด) · เลยวันนัดแล้วขึ้นสีแดงเตือน --}}
                            <td class="small">
                                @php $ap = $nextAppt[$r->patient_id] ?? null; @endphp
                                @if(! $ap)
                                    <span class="text-muted">— ยังไม่มีนัด</span>
                                @else
                                    @php $late = $ap->appointment_date < $today; @endphp
                                    <span class="fw-bold {{ $late ? 'text-danger' : '' }}">
                                        {{ \App\Utillity::db2thDate($ap->appointment_date) }}
                                    </span>
                                    @if($late)
                                        <div class="text-danger">เลยนัดมาแล้ว</div>
                                    @endif
                                    @if($ap->reason)
                                        <div class="text-muted">{{ \Illuminate\Support\Str::limit($ap->reason, 40) }}</div>
                                    @endif
                                @endif
                            </td>
                            <td>
                                @if($r->isActive())
                                    <span class="badge bg-success">กำลังรักษา</span>
                                @else
                                    <span class="badge bg-secondary">{{ $r->outcomeName() }}</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('tb.show', $r->id) }}" class="btn btn-sm btn-primary" title="เปิดบัตร TB 01">
                                    <i class="fa fa-folder-open"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-center">{{ $rows->onEachSide(1)->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
