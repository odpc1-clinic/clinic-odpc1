@extends('layouts.app')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Prompt', sans-serif; background-color: #f8f9fc; }
    .card-stat { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: transform 0.2s; }
    .card-stat:hover { transform: translateY(-5px); }
    .stat-label { color: #858796; font-size: 0.9rem; font-weight: 500; }
    .stat-value { font-size: 1.8rem; font-weight: 700; color: #4e73df; }
    .icon-bg { position: absolute; right: 10px; top: 10px; opacity: 0.1; font-size: 3rem; }
</style>

<div class="container-fluid py-4">
    
    {{-- Header & Filter --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-gray-800 mb-0"><i class="fas fa-chart-pie me-2"></i> Dashboard สถิติบริการ</h3>
            <small class="text-muted">
                ปีงบประมาณ {{ $year + 543 }} (1 ต.ค. {{ $year + 542 }} - 30 ก.ย. {{ $year + 543 }})
            </small>
        </div>
        <div class="d-flex gap-2">
            <form action="{{ route('stats.index') }}" method="GET" class="d-flex align-items-center">
                <select name="year" class="form-select form-select-sm me-2" onchange="this.form.submit()">
                    @php
                        $curFiscal = (date('m') >= 10) ? date('Y') + 1 : date('Y');
                    @endphp
                    @for($y = $curFiscal; $y >= $curFiscal-5; $y--)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>ปีงบ {{ $y + 543 }}</option>
                    @endfor
                </select>
            </form>
            @hasanyrole('Admin|Nurse|Doctor')
            {{-- หน้านี้บอกได้แค่ "กี่คน" ถ้าอยากได้ "ใครบ้าง" ให้ไปหน้าดึงรายชื่อ --}}
            <a href="{{ route('report.explore') }}" class="btn btn-outline-primary btn-sm"
               title="เลือกเงื่อนไขเองแล้วดึงรายชื่อคนไข้ + ออก Excel">
                <i class="fas fa-magnifying-glass-chart"></i> ดึงรายชื่อคนไข้เอง
            </a>
            @endhasanyrole
            <button onclick="window.print()" class="btn btn-secondary btn-sm"><i class="fas fa-print"></i></button>
        </div>
    </div>

    {{-- GROUP 1: Patient Mix (Top Cards) --}}
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card card-stat h-100 py-2 border-start border-primary border-3">
                <div class="card-body position-relative">
                    <div class="stat-label text-uppercase text-primary mb-1">ผู้ป่วยทั้งหมด (Total)</div>
                    <div class="stat-value text-dark">{{ number_format($totalVisits) }} <span class="fs-6 text-muted fw-light">ราย</span></div>
                    <i class="fas fa-users icon-bg text-primary"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stat h-100 py-2 border-start border-success border-3">
                <div class="card-body position-relative">
                    <div class="stat-label text-uppercase text-success mb-1">รายใหม่ (New)</div>
                    <div class="stat-value text-success">{{ number_format($newPatients) }}</div>
                    <small class="text-muted">คิดเป็น {{ $totalVisits > 0 ? number_format(($newPatients/$totalVisits)*100, 1) : 0 }}%</small>
                    <i class="fas fa-user-plus icon-bg text-success"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stat h-100 py-2 border-start border-info border-3">
                <div class="card-body position-relative">
                    <div class="stat-label text-uppercase text-info mb-1">รายเก่า (Old)</div>
                    <div class="stat-value text-info">{{ number_format($oldPatients) }}</div>
                    <i class="fas fa-history icon-bg text-info"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- MAIN ROW: Split Left (Stats) / Right (Calculator) --}}
    <div class="row">
        
        {{-- LEFT COLUMN: Tables (Width 7) --}}
        <div class="col-lg-7 mb-4">
            
            {{-- GROUP 2: Daily Stats --}}
            <div class="card card-stat shadow mb-4">
                <div class="card-header py-3 bg-white border-bottom-0">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-calendar-alt me-1"></i> ปริมาณคนไข้ต่อวัน</h6>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-bordered text-center align-middle mb-0">
                            <thead class="bg-light text-muted small">
                                <tr>
                                    <th>สูงสุด (Max)</th>
                                    <th>ต่ำสุด (Min)</th>
                                    <th>เฉลี่ย (Avg)</th>
                                    <th>ฐานนิยม (Mode)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-danger fw-bold fs-4">{{ $dailyStats['max'] }}</td>
                                    <td class="text-success fw-bold fs-4">{{ $dailyStats['min'] }}</td>
                                    <td class="text-primary fw-bold fs-4">{{ $dailyStats['avg'] }}</td>
                                    <td class="text-dark fw-bold">{{ $dailyStats['mode'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

           {{-- GROUP 3: Time Stats (Wait & Service) --}}
            <div class="card card-stat shadow">
                <div class="card-header py-3 bg-white border-bottom-0">
                    <h6 class="m-0 fw-bold text-info"><i class="fas fa-stopwatch me-1"></i> เวลาในการให้บริการ (นาที)</h6>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small">
                                <tr>
                                    <th>ขั้นตอน</th>
                                    <th class="text-center">เฉลี่ย (Avg)</th>
                                    <th class="text-center text-primary">ฐานนิยม (Mode)</th>
                                    <th class="text-center text-danger">นาน 4-8 ชม.</th> {{-- ✅ เพิ่มคอลัมน์นี้ --}}
                                    <th class="text-center">เร็วสุด</th>
                                    <th class="text-center">ช้าสุด</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- แถวเวลารอคอย --}}
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-warning text-white d-flex justify-content-center align-items-center me-2" style="width:30px;height:30px;"><i class="fas fa-hourglass-half fa-xs"></i></div>
                                            <div>
                                                <span class="fw-bold text-dark">เวลารอคอย</span>
                                                <div class="text-muted small" style="font-size: 0.75rem;">Waiting Time</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center text-warning fs-5">{{ $timeStats['wait']['avg'] }}</td>
                                    <td class="text-center fw-bold text-primary fs-5">{{ $timeStats['wait']['mode'] }}</td>
                                    
                                    {{-- ✅ แสดงจำนวนเคสนาน --}}
                                    <td class="text-center">
                                        @if($timeStats['wait']['long_count'] > 0)
                                            <span class="badge bg-danger rounded-pill">{{ $timeStats['wait']['long_count'] }} ราย</span>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>

                                    <td class="text-center text-muted">{{ $timeStats['wait']['min'] }}</td>
                                    <td class="text-center text-muted">{{ $timeStats['wait']['max'] }}</td>
                                </tr>

                                {{-- แถวเวลารับบริการ --}}
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-success text-white d-flex justify-content-center align-items-center me-2" style="width:30px;height:30px;"><i class="fas fa-check fa-xs"></i></div>
                                            <div>
                                                <span class="fw-bold text-dark">เวลารับบริการ</span>
                                                <div class="text-muted small" style="font-size: 0.75rem;">Service Time</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center text-success fs-5">{{ $timeStats['service']['avg'] }}</td>
                                    <td class="text-center fw-bold text-primary fs-5">{{ $timeStats['service']['mode'] }}</td>

                                    {{-- ✅ แสดงจำนวนเคสนาน --}}
                                    <td class="text-center">
                                        @if($timeStats['service']['long_count'] > 0)
                                            <span class="badge bg-danger rounded-pill">{{ $timeStats['service']['long_count'] }} ราย</span>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>

                                    <td class="text-center text-muted">{{ $timeStats['service']['min'] }}</td>
                                    <td class="text-center text-muted">{{ $timeStats['service']['max'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-light mt-2 mb-0 py-1 small text-muted">
                        <i class="fas fa-info-circle"></i> กรองเฉพาะวัน จันทร์, พุธ, ศุกร์ | ตัดข้อมูล > 8 ชม. (Error) | <span class="text-danger fw-bold">สีแดง</span> คือกลุ่ม 4-8 ชม.
                    </div>
                </div>
            </div>

        </div> {{-- End Left Column --}}

        {{-- RIGHT COLUMN: Calculator (Width 5) --}}
        <div class="col-lg-5 mb-4">
            
            {{-- GROUP 4: Productivity Calculator --}}
            <div class="card card-stat shadow border-warning h-100">
                <div class="card-header py-3 bg-white border-bottom-0">
                    <h6 class="m-0 fw-bold text-warning">
                        <i class="fas fa-calculator me-1"></i> คำนวณความคุ้มค่า (Productivity)
                        <small class="text-secondary fw-normal" style="font-size: 0.8rem;">(กรอกเอง)</small>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="bg-light p-3 rounded mb-3 border">
                        <small class="text-muted fw-bold">สูตรคำนวณ:</small><br>
                        <small class="text-dark fst-italic">(เวลาที่ลดได้ x เคสต่อวัน x ค่าจ้าง) x วันทำงาน</small>
                    </div>
                    
                    <form id="prodForm">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">เวลาที่ลดได้/เคส (นาที)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-clock text-muted"></i></span>
                                    <input type="number" id="timeSaved" class="form-control" value="10">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">จำนวนเคส/วัน</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-users text-muted"></i></span>
                                    <input type="number" id="taskCount" class="form-control" value="{{ floor($dailyStats['avg']) }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">ค่าจ้าง จนท./ชั่วโมง (บาท)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white">฿</span>
                                    <input type="number" id="hourlyWage" class="form-control" value="150">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">วันทำการ/ปี</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-calendar text-muted"></i></span>
                                    <input type="number" id="workingDays" class="form-control" value="250">
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-warning w-100 text-white shadow-sm fw-bold" onclick="calcProductivity()">
                            <i class="fas fa-coins me-1"></i> คำนวณมูลค่า
                        </button>
                    </form>

                    <div class="mt-4 text-center p-4 bg-soft-success rounded border border-success border-opacity-25" id="resultArea" style="display:none; background-color: #e8f5e9;">
                        <h6 class="text-success mb-2">มูลค่าที่เพิ่มขึ้นต่อปี</h6>
                        <h1 class="text-success fw-bold mb-0" id="prodResult">0</h1>
                        <span class="text-muted small">บาท / ปี</span>
                    </div>
                </div>
            </div>

        </div> {{-- End Right Column --}}

    </div> {{-- End Main Row --}}

</div>

<script>
function calcProductivity() {
    let timeSaved = parseFloat(document.getElementById('timeSaved').value) || 0;
    let taskCount = parseFloat(document.getElementById('taskCount').value) || 0;
    let hourlyWage = parseFloat(document.getElementById('hourlyWage').value) || 0;
    let days = parseFloat(document.getElementById('workingDays').value) || 0;

    let hoursSavedPerDay = (timeSaved * taskCount) / 60;
    let valuePerDay = hoursSavedPerDay * hourlyWage;
    let totalValue = valuePerDay * days;

    document.getElementById('prodResult').innerText = totalValue.toLocaleString('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    let resultArea = document.getElementById('resultArea');
    resultArea.style.display = 'block';
}
</script>
@endsection