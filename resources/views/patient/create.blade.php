@extends('layouts.app')

@section('content')
<style>
    .pc-hero { background: linear-gradient(135deg,#047857 0%,#059669 55%,#10b981 100%); border-radius:16px; color:#fff; }
    .pc-card { border:0; border-radius:14px; }
    .pc-card .sec-head { display:flex; align-items:center; gap:8px; font-weight:700; color:#334155;
                         border-left:4px solid #10b981; padding:.35rem .75rem; background:#f0fdf4; border-radius:8px; margin-bottom:1rem; }
    .pc-card .sec-head.addr { border-left-color:#0ea5e9; background:#f0f9ff; }
</style>

<div class="container-fluid py-4">

    {{-- ===== Hero ===== --}}
    <div class="pc-hero p-4 mb-4 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h3 class="mb-1 fw-bold"><i class="fas fa-user-plus me-2"></i>ลงทะเบียนผู้ป่วยใหม่</h3>
            <div class="opacity-75">กรอกข้อมูลผู้ป่วย · เสียบบัตร ปชช. เพื่อกรอกอัตโนมัติ · เพิ่มที่อยู่</div>
        </div>
        <a href="{{ route('patient.index') }}" class="btn btn-light fw-bold shadow-sm">
            <i class="fa fa-arrow-left me-1"></i> กลับหน้าทะเบียนผู้ป่วย
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('patient.store') }}" accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data" autocomplete="off">
        {{ csrf_field() }}

        <div class="card pc-card shadow-sm mb-4">
            <div class="card-body">
                <div class="sec-head"><i class="fas fa-id-card text-success"></i> ข้อมูลผู้ป่วย</div>
                @include ('patient.form', ['formMode' => 'create'])

                <div class="sec-head addr mt-4"><i class="fas fa-map-marker-alt text-info"></i> ที่อยู่</div>
                @include('address.form', ['formMode' => 'create'])
            </div>
        </div>
    </form>

    @include('sweetalert::alert')
</div>
@endsection
