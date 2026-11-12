@extends('layouts.app')

@section('content')
<style>
    .al-hero { background: linear-gradient(135deg,#1e293b 0%,#334155 55%,#475569 100%); border-radius:16px; color:#fff; }
    .al-filter { background:#fff; border:1px solid #e5e7eb; border-radius:14px; }
    .al-filter label { font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.03em; margin-bottom:2px; }
</style>

<div class="container-fluid py-4">

    <div class="al-hero p-4 mb-4 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h3 class="mb-1 fw-bold"><i class="fas fa-clock-rotate-left me-2"></i>ประวัติการแก้ไข (Activity Log)</h3>
            <div class="opacity-75">ใครสร้าง / แก้ไข / ลบ ข้อมูลอะไร เมื่อไหร่ — บันทึกอัตโนมัติทั้งระบบ</div>
        </div>
    </div>

    <form method="GET" class="al-filter p-3 mb-4 shadow-sm">
        <div class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label>ค้นหา (ชื่อผู้ทำ / ข้อมูล / id)</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="เช่น ชื่อพยาบาล, HN...">
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <label>ชนิดข้อมูล</label>
                <select name="type" class="form-select">
                    <option value="">ทั้งหมด</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}" {{ request('type')===$t ? 'selected' : '' }}>
                            {{ \App\Models\ActivityLog::$typeLabels[$t] ?? class_basename($t) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label>การกระทำ</label>
                <select name="action" class="form-select">
                    <option value="">ทั้งหมด</option>
                    <option value="created" {{ request('action')==='created' ? 'selected' : '' }}>สร้างใหม่</option>
                    <option value="updated" {{ request('action')==='updated' ? 'selected' : '' }}>แก้ไข</option>
                    <option value="deleted" {{ request('action')==='deleted' ? 'selected' : '' }}>ลบ</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label>วันที่</label>
                <input type="date" name="date" class="form-control" value="{{ request('date') }}">
            </div>
            <div class="col-lg-2 col-md-4 d-grid">
                <button class="btn btn-primary fw-bold" type="submit"><i class="fa fa-filter me-1"></i> กรอง</button>
            </div>
        </div>
    </form>

    @include('activity_log._table')
</div>
@endsection
