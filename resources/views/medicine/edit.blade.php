@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <a href="{{ url('/medicine') }}" class="btn btn-light border btn-sm mb-3"><i class="fa fa-arrow-left"></i> กลับรายการยา</a>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-pen text-primary me-2"></i>แก้ไขยา: {{ $medicine->name }}</h5>
                    <a href="{{ route('medicine-lot.show', $medicine->id) }}" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-boxes-stacked"></i> จัดการคลัง / Lot
                    </a>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ url('/medicine/' . $medicine->id) }}" accept-charset="UTF-8" enctype="multipart/form-data">
                        {{ method_field('PATCH') }}
                        {{ csrf_field() }}
                        @include('medicine.form', ['formMode' => 'edit'])
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
