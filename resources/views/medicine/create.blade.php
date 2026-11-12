@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <a href="{{ url('/medicine') }}" class="btn btn-light border btn-sm mb-3"><i class="fa fa-arrow-left"></i> กลับรายการยา</a>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-plus-circle text-success me-2"></i>เพิ่มยาใหม่</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ url('/medicine') }}" accept-charset="UTF-8" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        @include('medicine.form', ['formMode' => 'create'])
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
