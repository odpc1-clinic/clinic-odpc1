@extends('layouts.app')

{{-- สติ๊กเกอร์ยาวัณโรค — พิมพ์จากตารางขนาดยาของเดือนที่เลือก
     ยาเบิกจากกองวัณโรค ไม่ตัดสต็อกคลินิกและไม่คิดเงิน หน้านี้จึงไม่ผ่านคลังยา/ใบเสร็จ
     ขนาดสติ๊กเกอร์ตั้งไว้ที่ 10x5 ซม. ปรับได้จากช่องด้านบนก่อนกดพิมพ์ --}}

@section('content')
<style>
    .label-sheet { display:flex; flex-wrap:wrap; gap:6px; }
    .drug-label {
        width: 10cm; min-height: 5cm; border:1px dashed #adb5bd; border-radius:.25rem;
        padding:.45cm .5cm; page-break-inside: avoid; break-inside: avoid;
        font-family: "TH Sarabun New", "Sarabun", sans-serif;
    }
    .drug-label .clinic { font-size:12px; color:#555; line-height:1.1; }
    .drug-label .pt     { font-size:15px; font-weight:700; margin-top:2px; }
    .drug-label .drug   { font-size:20px; font-weight:700; color:#000; margin:4px 0 2px; }
    .drug-label .how    { font-size:17px; }
    .drug-label .qty    { font-size:15px; font-weight:700; }
    .drug-label .foot   { font-size:11px; color:#666; margin-top:4px; }
    .drug-label .warn   { font-size:12px; color:#b45309; }

    @media print {
        .no-print, .navbar, nav, footer { display:none !important; }
        body { background:#fff; }
        .drug-label { border:none; }
        .container, .container-wide { max-width:none !important; width:auto !important; padding:0 !important; }
    }
</style>

<div class="container container-wide">

    <div class="no-print card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <h5 class="fw-bold mb-0">
                    <i class="fa fa-tag me-1"></i> สติ๊กเกอร์ยาวัณโรค — {{ $rowLabel }}
                </h5>
                <div class="d-flex gap-2">
                    <a href="{{ route('tb.show', $reg->id) }}" class="btn btn-outline-secondary">
                        <i class="fa fa-arrow-left"></i> กลับไปที่บัตร
                    </a>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fa fa-print"></i> พิมพ์
                    </button>
                </div>
            </div>

            {{-- เลือกเดือน/จำนวนวันใหม่แล้วโหลดหน้าใหม่ (ไม่ได้บันทึกลงบัตร แค่ใช้พิมพ์รอบนี้) --}}
            <form method="GET" action="{{ route('tb.labels', $reg->id) }}" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small text-muted mb-1">รอบที่สั่งยา</label>
                    {{-- เลือกเป็นแถวในตารางขนาดยา (มีแถว 2 สัปดาห์/นอกรอบ หลายแถวต่อเดือน) --}}
                    <select name="row" class="form-select form-select-sm" style="width:13rem;">
                        @foreach($allRows as $__r)
                            <option value="{{ $__r->id }}" {{ $dose && $dose->id === $__r->id ? 'selected' : '' }}>{{ $__r->labelForHumans() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small text-muted mb-1">จ่ายกี่วัน</label>
                    <input type="number" name="days" min="1" max="120" value="{{ $days }}"
                           class="form-control form-control-sm" style="width:7rem;">
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-outline-primary">แสดงใหม่</button>
                </div>
                <div class="col-12">
                    <div class="small text-muted">
                        จำนวนเม็ดคิดจาก “ขนาดต่อวัน × จำนวนวัน” · ยาที่กรอกเป็นมิลลิกรัมจะพิมพ์ขนาดต่อวันให้ ไม่ได้คูณจำนวน ·
                        ถ้าจะให้จำนวนวันติดอยู่กับบัตรถาวร ให้กรอกในช่อง “จ่ายกี่วัน” ของตารางขนาดยาแล้วกดบันทึก
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if(! $dose || empty($items))
        <div class="alert alert-warning no-print">
            {{ $rowLabel }} ยังไม่มีขนาดยาในบัตร — กรอกขนาดยาในตาราง “น้ำหนัก / ขนาดยารายเดือน” ก่อน แล้วค่อยพิมพ์สติ๊กเกอร์
        </div>
    @else
        <div class="label-sheet">
            @foreach($items as $it)
                <div class="drug-label">
                    <div class="clinic">
                        ศูนย์บริการเวชศาสตร์ป้องกัน · สคร.1 เชียงใหม่
                        <span class="float-end">{{ \App\Utillity::db2thDate($dose->dispensed_date ?: \App\Models\VisitAppointment::todayBe()) }}</span>
                    </div>

                    <div class="pt">
                        {{ optional($reg->patient)->name }} {{ optional($reg->patient)->surname }}
                        <span class="text-muted" style="font-weight:400;">
                            HN {{ optional($reg->patient)->code }}@if($reg->tb_no) · TB {{ $reg->tb_no }}@endif
                        </span>
                    </div>

                    <div class="drug">
                        {{ $it['name'] }}@if($it['strength']) {{ $it['strength'] }}@endif
                    </div>

                    <div class="how">
                        @if(! empty($it['sig']))
                            {{-- วิธีใช้ที่ตั้งไว้ในบัตร TB 01 (กล่อง "วิธีใช้ยา") --}}
                            <b>{{ $it['sig'] }}</b>
                        @else
                            {{-- ยังไม่ได้ตั้งวิธีใช้ — พิมพ์แบบเดิม --}}
                            รับประทาน ครั้งละ <b>{{ $it['per_day'] }}</b> วันละ 1 ครั้ง หลังอาหารเช้า
                        @endif
                    </div>

                    {{-- ยาอื่น (Para ฯลฯ) จ่ายตามจำนวนที่หมอกรอก ไม่ผูกกับจำนวนวัน --}}
                    @if($it['total'] && ! ($it['is_tb'] ?? true))
                        <div class="qty">จ่าย {{ $it['total'] }}</div>
                    @elseif($it['total'])
                        <div class="qty">จ่าย {{ $it['total'] }} ({{ $days }} วัน)</div>
                    @elseif(! ($it['is_tb'] ?? true))
                    @else
                        <div class="qty">จ่ายสำหรับ {{ $days }} วัน</div>
                    @endif

                    @if($it['note'])
                        <div class="warn"><i class="fa fa-circle-info"></i> {{ $it['note'] }}</div>
                    @endif

                    <div class="foot">
                        {{-- ยาอื่นที่หมอเพิ่มเอง (เช่น Para) ไม่ใช่ยาวัณโรค — ห้ามพิมพ์ "กินต่อเนื่องจนครบ" --}}
                        @if($it['is_tb'] ?? true)ยาวัณโรค — กินต่อเนื่องทุกวันจนครบ ห้ามหยุดยาเอง · @endif เดือนที่ {{ $month }} ของการรักษา
                    </div>
                </div>
            @endforeach
        </div>

        <div class="no-print small text-muted mt-3">
            ทั้งหมด {{ count($items) }} ดวง · ขนาดสติ๊กเกอร์ 10 × 5 ซม. (เส้นประไม่ถูกพิมพ์)
        </div>
    @endif
</div>
@endsection
