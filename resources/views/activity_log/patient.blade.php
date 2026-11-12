@extends('layouts.app')

@section('content')
<style>
    .al-hero { background: linear-gradient(135deg,#0f766e 0%,#0891b2 100%); border-radius:16px; color:#fff; }
    .al-hero .id-chip { display:inline-block; background:rgba(255,255,255,.2); border:1px solid rgba(255,255,255,.32);
                        border-radius:8px; padding:2px 12px; font-weight:600; font-size:.85rem; }
</style>

<div class="container-fluid py-4">

    <div class="al-hero p-4 mb-4 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="opacity-75 small"><i class="fas fa-clock-rotate-left me-1"></i> ประวัติการแก้ไขเฉพาะเคส</div>
            <h3 class="mb-2 fw-bold text-white">{{ $patient->name ?? '-' }} {{ $patient->surname ?? '' }}</h3>
            <span class="id-chip"><i class="fas fa-id-card-clip me-1"></i> HN {{ $patient->code ?? '-' }}</span>
        </div>
        <a href="{{ route('patient.edit', $patient->id) }}" class="btn btn-light fw-bold shadow-sm">
            <i class="fa fa-arrow-left me-1"></i> กลับหน้าคนไข้
        </a>
    </div>

    {{-- ตัวกรองย่อ (การกระทำ / ชนิดข้อมูล) --}}
    <form method="GET" class="mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="type" class="form-select form-select-sm">
                    <option value="">ชนิดข้อมูล: ทั้งหมด</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}" {{ request('type')===$t ? 'selected' : '' }}>
                            {{ \App\Models\ActivityLog::$typeLabels[$t] ?? class_basename($t) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="action" class="form-select form-select-sm">
                    <option value="">การกระทำ: ทั้งหมด</option>
                    <option value="created" {{ request('action')==='created' ? 'selected' : '' }}>สร้างใหม่</option>
                    <option value="updated" {{ request('action')==='updated' ? 'selected' : '' }}>แก้ไข</option>
                    <option value="deleted" {{ request('action')==='deleted' ? 'selected' : '' }}>ลบ</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary btn-sm w-100" type="submit"><i class="fa fa-filter me-1"></i> กรอง</button>
            </div>
        </div>
    </form>

    @include('activity_log._table')
</div>
@endsection
