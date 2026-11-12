@extends('layouts.app')

@section('content')
<div class="container">
    <h3>รายงานผลแล็บ</h3>

    <!-- ฟอร์มค้นหาช่วงวันที่ -->
    <form method="GET" action="{{ route('lab.report') }}" class="mb-3">
        <div class="row">
            <div class="col-md-4">
                <label>จากวันที่ (พ.ศ.):</label>
                <input type="date" name="date_from" class="form-control" value="{{ $date_from_th }}">
            </div>
            <div class="col-md-4">
                <label>ถึงวันที่ (พ.ศ.):</label>
                <input type="date" name="date_to" class="form-control" value="{{ $date_to_th }}">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">🔍 ค้นหา</button>
            </div>
        </div>
    </form>

    <!-- ปุ่มย้อนกลับ -->
    <a href="{{ route('lab.index') }}" class="btn btn-secondary">⬅️ กลับไปหน้าแล็บ</a>

    <!-- ตารางรายงาน -->
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Method</th>
                <th>Specimen</th>
                <th>Result</th>
                <th>Visit Date (พ.ศ.)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reports as $report)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $report->method }}</td>
                <td>{{ $report->specimen }}</td>
                <td class="{{ str_contains(strtolower($report->result), 'positive') ? 'text-danger fw-bold' : '' }}">
                    {{ $report->result }}
                </td>
                <td>{{ \Carbon\Carbon::parse($report->visit_date)->addYears(543)->format('Y-m-d') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center">ไม่พบข้อมูล</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- ปุ่มพิมพ์ -->
    <button class="btn btn-success mt-3" onclick="window.print()">🖨️ พิมพ์รายงาน</button>
</div>
@endsection
