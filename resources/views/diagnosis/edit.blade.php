@extends('layouts.app')

@section('content')
<style>
    .dg-hero { background: linear-gradient(135deg,#be123c 0%,#e11d48 55%,#f43f5e 100%); border-radius:16px; color:#fff; }
</style>

<div class="container-fluid py-4">
    <div class="dg-hero p-4 mb-4 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h4 class="mb-1 fw-bold"><i class="fas fa-notes-medical me-2"></i>แก้ไขการวินิจฉัย #{{ $diagnosi->id }}</h4>
            <div class="opacity-75 small">Visit #{{ $diagnosi->visit_id }}</div>
        </div>
        <a href="{{ route('visit.edit', ['visit' => $diagnosi->visit_id, 'page' => $_GET['page'] ?? 1]) }}" class="btn btn-light fw-bold shadow-sm">
            <i class="fa fa-arrow-left me-1"></i> กลับหน้า Visit
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('diagnosis.update',['diagnosi'=>$diagnosi->id,'page'=>request('page', 1)]) }}" accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data">
                {{ method_field('PATCH') }}
                {{ csrf_field() }}
                <input type="hidden" name="visit_id" value="{{ $diagnosi->visit_id }}">
                @if($isTmc ?? false)
                    @include('diagnosis._tmc_edit')
                @else
                    @include ('diagnosis.form', ['formMode' => 'edit'])
                @endif
            </form>
        </div>
    </div>

    @include('sweetalert::alert')
</div>
@endsection
