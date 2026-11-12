@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa fa-history"></i> ประวัติการรับเงิน</h2>
        <form action="{{ url('/finance/history') }}" method="GET" class="form-inline">
            <input type="date" name="filter_date" class="form-control" value="{{ $filterDate }}">
            <button type="submit" class="btn btn-primary">ค้นหา</button>
        </form>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="bg-dark text-white">
                    <tr>
                        <th width="100">คิว</th>
                        <th width="120">HN</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th class="text-right">ยอดสุทธิ</th>
                        <th class="text-center">เวลาที่บันทึก</th>
                        <th class="text-center" width="150">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($visits as $v)
                    <tr>
                        <td class="text-center"><strong>{{ $v->queue }}</strong></td>
                        <td>{{ $v->patient2->code ?? '-' }}</td>
                        <td>{{ $v->patient2->name ?? '' }} {{ $v->patient2->surname ?? '' }}</td>
                        <td class="text-right font-weight-bold text-primary">
                            {{ number_format($v->billings->net_total ?? 0, 2) }}
                        </td>
                        <td class="text-center">{{ $v->updated_at->format('H:i:s') }}</td>
                        <td class="text-center">
                            <a href="{{ url('/finance/'.$v->id.'/edit') }}" class="btn btn-sm btn-warning">
                                <i class="fa fa-edit"></i> แก้ไขบิล
                            </a>
                            <a href="{{ route('finance.print', $v->id) }}" class="btn btn-sm btn-info" target="_blank">
    <i class="fa fa-print"></i> พิมพ์
</a>
                        </td>

                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center py-4">ไม่พบประวัติการรับเงินในวันที่เลือก</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
