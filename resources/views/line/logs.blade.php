@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fab fa-line text-success"></i> ประวัติข้อความ LINE ที่ส่งออก</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-4">
                    <input type="text" name="search" value="{{ $keyword }}" class="form-control" placeholder="ค้นหา HN / ชื่อ / นามสกุล / ข้อความ">
                </div>
                <div class="col-md-3">
                    <input type="date" name="filter_date" value="{{ $filterDate }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> ค้นหา</button>
                    <a href="{{ route('line.logs') }}" class="btn btn-outline-secondary">ล้าง</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 140px;">เวลา</th>
                            <th style="width: 160px;">คนไข้</th>
                            <th style="width: 90px;">ประเภท</th>
                            <th style="width: 140px;">วัตถุประสงค์</th>
                            <th>ข้อความ</th>
                            <th style="width: 80px;">ผล</th>
                        </tr>
                    </thead>
                    <tbody>
                    @php
                        $purposeLabel = [
                            'appointment_reminder' => 'แจ้งเตือนนัด',
                            'link_success' => 'ผูกบัญชีสำเร็จ',
                            'link_failed' => 'รหัสผูกผิด/หมดอายุ',
                            'greeting' => 'ทักทาย/แอดเพื่อน',
                            'inquiry_redirect' => 'ตอบ/ส่งต่อ OA หลัก',
                        ];
                    @endphp
                    @forelse($logs as $log)
                        <tr>
                            <td class="small text-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td class="small">
                                @if($log->patient)
                                    <span class="fw-bold">{{ $log->patient->code }}</span><br>
                                    {{ $log->patient->name }} {{ $log->patient->surname }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="small">
                                @if($log->type === 'push')
                                    <span class="badge bg-primary">ส่งออก</span>
                                @else
                                    <span class="badge bg-secondary">ตอบแชท</span>
                                @endif
                            </td>
                            <td class="small">{{ $purposeLabel[$log->purpose] ?? $log->purpose ?? '-' }}</td>
                            <td class="small" style="white-space: pre-wrap;">{{ $log->message }}</td>
                            <td class="small">
                                @if($log->success)
                                    <span class="badge bg-success">สำเร็จ</span>
                                @else
                                    <span class="badge bg-danger" title="{{ $log->error }}">ล้มเหลว</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">ยังไม่มีประวัติการส่งข้อความ</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
