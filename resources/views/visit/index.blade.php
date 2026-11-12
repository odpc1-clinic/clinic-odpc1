@extends('layouts.app')

@section('content')
{{-- Import Font & Custom CSS --}}
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Prompt', sans-serif; background-color: #f8f9fc; }
    
    /* Card Styles */
    .card-modern {
        border: none;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }
    .card-header-modern {
        background: linear-gradient(45deg, #4e73df, #224abe);
        color: white;
        font-weight: 600;
        padding: 15px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Table Styles */
    .table-modern thead th {
        background-color: #f8f9fc;
        color: #858796;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        border-bottom: 2px solid #e3e6f0;
        vertical-align: middle;
        white-space: nowrap;
    }
    .table-modern tbody td {
        vertical-align: middle;
        font-size: 0.95rem;
        color: #5a5c69;
    }
    .table-hover tbody tr:hover {
        background-color: #f8f9fc;
    }

    /* Badges & Elements */
    .badge-soft { padding: 5px 10px; border-radius: 8px; font-weight: 500; font-size: 0.8rem; }
    .badge-male { background-color: #e3f2fd; color: #1976d2; }
    .badge-female { background-color: #fce4ec; color: #c2185b; }
    
    .diag-tag {
        display: inline-block;
        background: #e8f5e9;
        color: #2e7d32;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.85rem;
        margin-bottom: 2px;
        border: 1px solid #c8e6c9;
    }
    
    .contact-card {
        background-color: #fff;
        border: 1px dashed #d1d3e2;
        border-radius: 8px;
        padding: 8px;
        margin-bottom: 5px;
        font-size: 0.85rem;
    }
    .contact-header { font-weight: 600; color: #4e73df; }
</style>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-12">
            
            {{-- Search Area (ถ้าต้องการให้ดูสะอาดขึ้น อาจจะย้ายไปไว้ข้างบนหรือซ่อนใน Card) --}}
            <div class="mb-4">
                 @include('visit.searchForm', ['visit' => ""]) 
            </div>

            <div class="card card-modern">
                <div class="card-header-modern">
                    <span><i class="fas fa-notes-medical me-2"></i> รายการผู้รับบริการ (Visits)</span>
                    {{-- ปุ่ม Add New (ถ้าจะเปิดใช้) --}}
                    {{-- <a href="{{ route('visit.create') }}" class="btn btn-light btn-sm text-primary fw-bold shadow-sm">
                        <i class="fa fa-plus"></i> เพิ่มข้อมูล
                    </a> --}}
                </div>
                
                <div class="card-body p-0">
                    <div class="table-responsive p-3">
                        <table class="table table-modern table-hover w-100" id="box2">
                            <thead>
    <tr>
        <th class="text-center" width="5%">#</th>
        <th>HN</th>
        <th class="text-center">คลินิก</th>
        <th>ชื่อ-นามสกุล</th>
        <th class="text-center">เพศ</th>
        <th class="text-center">อายุ</th>
        <th>ประเภท</th>
        <th>วันที่มา</th>
        <th>วินิจฉัย / สถานะ</th>
        <th>ข้อมูลผู้สัมผัส</th> <th>นัดถัดไป</th>
        <th>มาครั้งแรก</th>
        
        {{-- ✅ เพิ่มบรรทัดนี้: หัวตารางสำหรับปุ่มจัดการ --}}
        <th class="text-center" width="10%">จัดการ</th>
    </tr>
</thead>
                            <tbody>
    @foreach($visits as $item)
    <tr>
        <td class="text-center">{{ $loop->iteration }}</td>
        
        {{-- 1. HN -> ไปทะเบียนห้องบัตร (Patient) --}}
        <td>
            <a href="{{ route('patient.edit', $item->patient_id) }}" class="fw-bold text-primary text-decoration-none" title="ไปที่ทะเบียนห้องบัตร">
                {{ $item->patient2->code }}
            </a>
        </td>
        
        {{-- หน้าค้นหาแสดงทุกคลินิก ป้ายนี้บอกว่าแถวนี้เป็นงานของคลินิกไหน --}}
        <td class="text-center">
            @include('partials.clinic-badge', ['clinic' => $item->clinic])
        </td>

        {{-- 2. ชื่อ-นามสกุล -> ไปทะเบียนห้องบัตร (Patient) --}}
        <td>
            <a href="{{ route('patient.edit', $item->patient_id) }}" class="text-dark text-decoration-none" title="ไปที่ทะเบียนห้องบัตร">
                <div class="fw-bold">{{ $item->patient2->name .' '.$item->patient2->surname }}</div>
            </a>
            <small class="text-muted"><i class="fas fa-phone-alt fa-xs"></i> {{ $item->patient2->tel }}</small>
        </td>
        
        {{-- ... (ส่วนอื่นๆ: เพศ, อายุ, ประเภท, วันที่, วินิจฉัย, ผู้สัมผัส, นัดถัดไป, มาครั้งแรก คงเดิม) ... --}}
        <td class="text-center">
            @if($item->patient2->sex == 1)
                <span class="badge badge-soft badge-male"><i class="fas fa-mars"></i> ชาย</span>
            @elseif($item->patient2->sex == 2)
                <span class="badge badge-soft badge-female"><i class="fas fa-venus"></i> หญิง</span>
            @else
                <span>-</span>
            @endif
        </td>
        <td class="text-center">{{ $item->patient2->age() }}</td>
        <td>
            <span class="badge bg-secondary rounded-pill" style="font-weight: 400;">
                {{ App\Models\Patient::$statusOption[$item->patient2->status] ?? '-' }}
            </span>
        </td>
        <td nowrap>
            <i class="far fa-calendar-alt text-muted me-1"></i>
            {{ \Carbon\Carbon::parse($item->date)->format('d/m/Y') }}
        </td>
        <td style="max-width: 250px;">
            @if($item->diagnoses->count() > 0)
                @foreach($item->diagnoses as $diag)
                    <div class="mb-1">
                        <span class="diag-tag">
                            {{ $diag->disease() }}
                        </span>
                        <small class="text-muted d-block ps-1" style="font-size: 0.75rem;">
                            • {{ $diag->diseaseState() }}
                        </small>
                    </div>
                @endforeach
            @else
                <span class="text-muted small">- ยังไม่วินิจฉัย -</span>
            @endif
        </td>
        <td style="min-width: 200px;">
             @if(isset($item->contactPersons) && $item->contactPersons->count() > 0)
                @foreach($item->contactPersons as $cp)
                    <div class="contact-card">
                        <div class="d-flex justify-content-between">
                            <span class="contact-header"><i class="fas fa-user-friends fa-xs"></i> {{ $cp->name_surname }}</span>
                            <span class="badge bg-light text-dark border">{{ \App\Models\ContactPerson::$cpsexOption[$cp->cpsex] ?? '' }}</span>
                        </div>
                        <div class="mt-1 text-muted">
                            ความสัมพันธ์: {{ \App\Models\ContactPerson::$typeOption[$cp->type] ?? '-' }}
                        </div>
                    </div>
                @endforeach
            @else
                <span class="text-muted small">- ไม่มีข้อมูล -</span>
            @endif
        </td>
        <td nowrap>
            @if($item->appointment)
                <div class="text-primary fw-bold">
                    {{ \Carbon\Carbon::parse($item->appointment)->format('d/m/Y') }}
                </div>
                <small class="text-muted">{{ Str::limit($item->appointment_reason, 20) }}</small>
            @else
                <span class="text-muted">-</span>
            @endif
        </td>
        <td class="text-muted small" nowrap>
            {{ $item->patient2->first_visit ?? '-' }}
        </td>

        {{-- 3. ปุ่มจัดการด้านหลัง -> ไป Visit นี้ (Medical Record) --}}
        <td class="text-center">
            <a href="{{ route('visit.edit', ['visit' => $item->id, 'page' => 1]) }}" class="btn btn-sm btn-outline-primary shadow-sm" title="ดูบันทึกการรักษา (Visit)">
                <i class="fas fa-file-medical"></i> ดู Visit
            </a>
        </td>

    </tr>
    @endforeach
</tbody>
                            <tfoot class="bg-light">
                                <tr>
                                    {{-- ตารางนี้มี 13 คอลัมน์ (เพิ่ม "คลินิก" แล้ว) --}}
                                    <td colspan="13" class="text-end fw-bold text-secondary p-3">
                                        รวมทั้งหมด {{ number_format(count($visits)) }} รายการ
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    {{-- Pagination --}}
                    <div class="d-flex justify-content-center pb-3">
                        <div class="pagination-wrapper">
                             {{-- ใส่ Logic Pagination ของคุณที่นี่ --}}
                             {{ $visits->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

{{-- Script DataTables --}}
<script type="text/javascript">
$(document).ready(function() {
    $('#box2').DataTable({
        dom: '<"d-flex justify-content-between align-items-center mb-3"Bf>rt<"d-flex justify-content-between align-items-center mt-3"lip>',
        buttons: [
            { extend: 'copy', className: 'btn btn-sm btn-outline-secondary' },
            { extend: 'excel', className: 'btn btn-sm btn-outline-success' },
            { extend: 'print', className: 'btn btn-sm btn-outline-primary' }
        ],
        pageLength: 25,
        lengthMenu: [ [10, 25, 50, 100], [10, 25, 50, 100] ],
        language: {
            search: "ค้นหาในตาราง:",
            lengthMenu: "แสดง _MENU_ รายการ",
            info: "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
            paginate: { first: "หน้าแรก", last: "หน้าสุดท้าย", next: "ถัดไป", previous: "ก่อนหน้า" }
        }
    });
});
</script>

@include('sweetalert::alert')
@endsection