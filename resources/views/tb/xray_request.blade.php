@extends('layouts.app')

{{-- ใบส่งตรวจเอกซเรย์ — ทำตามแบบฟอร์มกระดาษของศูนย์
     ติ๊กท่าที่จะถ่ายแล้วพิมพ์ ให้คนไข้ถือไปห้องเอกซเรย์ (คลินิกขอ 2569-09-29) --}}

@section('content')
<style>
    .xr-sheet { max-width: 20cm; border: 1px solid #000; }
    .xr-sheet td { border: 1px solid #000; padding: .35cm .4cm; vertical-align: top; }
    .xr-box { display:inline-block; width:14px; height:14px; border:1.5px solid #000; margin-right:6px; vertical-align:-2px; }
    .xr-box.on::after { content:'✓'; display:block; margin-top:-4px; margin-left:1px; font-weight:700; }
    .xr-line { border-bottom: 1px dotted #000; display:inline-block; min-width:5cm; }
    @media print {
        .no-print, nav, .navbar, footer { display:none !important; }
        body { background:#fff; }
        .container, .container-wide { max-width:none !important; padding:0 !important; }
        .xr-sheet { border:1px solid #000; }
    }
</style>

<div class="container container-wide">
    <div class="no-print d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h5 class="fw-bold mb-0"><i class="fa fa-x-ray me-1"></i> ใบส่งตรวจเอกซเรย์</h5>
        <div class="d-flex gap-2">
            <a href="{{ route('tb.show', $reg->id) }}" class="btn btn-outline-secondary">
                <i class="fa fa-arrow-left"></i> กลับไปที่บัตร
            </a>
            <button class="btn btn-primary" onclick="window.print()"><i class="fa fa-print"></i> พิมพ์</button>
        </div>
    </div>

    <div class="no-print alert alert-info py-2 px-3">
        ท่าที่ติ๊กมาจากแถวเอกซเรย์ล่าสุดในบัตร ถ้าจะส่งท่าอื่นให้กลับไปติ๊กในตารางแล้วกดบันทึกก่อน
    </div>

    @php $views = (array) ($row->views ?? []); @endphp

    <table class="xr-sheet" style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:55%;">
                <div class="fw-bold mb-2" style="font-size:16px;">ใบส่งตรวจเอกซเรย์ / Request for X-Ray</div>
                <div class="fw-bold mb-1">รายการส่งตรวจเอกซเรย์</div>
                @foreach(\App\Models\TbXray::$viewOption as $k => $label)
                    <div style="margin-bottom:.15cm;">
                        <span class="xr-box {{ in_array($k, $views, true) ? 'on' : '' }}"></span>{{ $label }}
                    </div>
                @endforeach
                <div style="margin-bottom:.15cm;">
                    <span class="xr-box {{ $row && $row->other_view ? 'on' : '' }}"></span>อื่นๆ
                    <span class="xr-line">{{ $row->other_view ?? '' }}</span>
                </div>
                <div class="mt-3">ผู้ส่งตรวจ <span class="xr-line">{{ auth()->user()->name ?? '' }}</span></div>
            </td>

            <td style="width:45%;">
                <div class="fw-bold mb-2 text-center" style="font-size:16px;">ข้อมูลผู้รับบริการ</div>
                <div class="mb-2">วันที่ <span class="xr-line">{{ \App\Utillity::db2thDate($row->xray_date ?? \App\Models\VisitAppointment::todayBe()) }}</span></div>
                <div class="mb-2">HN <span class="xr-line">{{ optional($reg->patient)->code }}</span></div>
                <div class="mb-2">XN <span class="xr-line">{{ $row->xn ?? '' }}</span> (กรณีผู้ป่วยเก่า)</div>
                <div class="mb-2">ชื่อ-สกุล <span class="xr-line">{{ optional($reg->patient)->name }} {{ optional($reg->patient)->surname }}</span></div>
                <div class="mb-2">อายุ <span class="xr-line" style="min-width:2cm;">{{ optional($reg->patient)->age() }}</span> ปี</div>
                <div class="mt-3 fw-bold">สำหรับเจ้าหน้าที่เอกซเรย์</div>
                <div style="height:1.6cm;"></div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="fw-bold me-3">บริการอื่นๆ</span>
                <span class="me-4"><span class="xr-box {{ ($row->cd_to_pacs ?? false) ? 'on' : '' }}"></span>นำ CD ภาพเอกซเรย์ จาก รพ.อื่น เข้าสู่ระบบ PACS</span>
                <span><span class="xr-box {{ ($row->cd_write ?? false) ? 'on' : '' }}"></span>Write CD ภาพเอกซเรย์ เพื่อไปรักษาต่อ</span>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="font-size:13px;">
                <b>วัณโรค:</b> เลขทะเบียน {{ $reg->tb_no ?: '—' }} ·
                {{ $reg->typeName() }} ·
                เริ่มรักษา {{ $reg->treatment_start_date ? \App\Utillity::db2thDate($reg->treatment_start_date) : '—' }} ·
                เดือนที่ {{ $reg->monthsOnTreatment() }}
            </td>
        </tr>
    </table>
</div>
@endsection
