@extends('layouts.app')

@section('content')
<style>
    .dg-hero { background: linear-gradient(135deg,#be123c 0%,#e11d48 55%,#f43f5e 100%); border-radius:16px; color:#fff; }
    .dg-table thead th { background:#f1f5f9; color:#334155; font-size:.78rem; text-transform:uppercase; letter-spacing:.02em;
                         border-bottom:2px solid #e2e8f0; white-space:nowrap; }
    .dg-table tbody tr:hover { background:#fff1f2; }
    .dg-table td { vertical-align:middle; }
</style>

<div class="container-fluid py-4">
    <div class="row">
        @include('admin.sidebar')

        <div class="col-md-9">
            {{-- Hero + toolbar --}}
            <div class="dg-hero p-4 mb-4 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h4 class="mb-1 fw-bold"><i class="fas fa-stethoscope me-2"></i>การวินิจฉัย (Diagnosis)</h4>
                    <div class="opacity-75 small">รายการวินิจฉัยโรคของผู้รับบริการ</div>
                </div>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <form method="GET" action="{{ url('/backend/diagnosis') }}" accept-charset="UTF-8" role="search">
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white border-0"><i class="fa fa-search text-muted"></i></span>
                            <input type="text" class="form-control border-0" name="search" placeholder="ค้นหา..." value="{{ request('search') }}">
                            <button class="btn btn-light fw-bold" type="submit">ค้นหา</button>
                        </div>
                    </form>
                    <a href="{{ url('/backend/diagnosis/create') }}" class="btn btn-light fw-bold shadow-sm" title="เพิ่มการวินิจฉัย">
                        <i class="fa fa-plus text-success me-1"></i> เพิ่มใหม่
                    </a>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table dg-table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th class="ps-3">#</th>
                                    <th>Visit Id</th>
                                    <th>Disease</th>
                                    <th>Other Disease</th>
                                    <th class="text-center pe-3">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($diagnosis as $item)
                                <tr>
                                    <td class="ps-3 text-muted">{{ $loop->iteration }}</td>
                                    <td><span class="badge bg-secondary-subtle text-secondary-emphasis">#{{ $item->visit_id }}</span></td>
                                    <td class="fw-semibold">{{ $item->disease }}</td>
                                    <td class="text-muted">{{ $item->other_disease ?: '-' }}</td>
                                    <td class="text-center pe-3 text-nowrap">
                                        <a href="{{ url('/backend/diagnosis/' . $item->id) }}" title="View" class="btn btn-outline-info btn-sm"><i class="fa fa-eye"></i></a>
                                        <a href="{{ url('/backend/diagnosis/' . $item->id . '/edit') }}" title="Edit" class="btn btn-outline-primary btn-sm"><i class="fa fa-pencil"></i></a>
                                        <form method="POST" action="{{ url('/backend/diagnosis' . '/' . $item->id) }}" accept-charset="UTF-8" style="display:inline">
                                            {{ method_field('DELETE') }}
                                            {{ csrf_field() }}
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete" onclick="return confirm(&quot;Confirm delete?&quot;)"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">ไม่มีข้อมูลการวินิจฉัย</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-center py-3">
                        {!! $diagnosis->appends(['search' => Request::get('search')])->render() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('sweetalert::alert')
</div>
@endsection
