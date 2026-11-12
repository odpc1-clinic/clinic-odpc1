@extends('layouts.app')

@section('content')
@php
    $visitId = $visit->id ?? request('visit');
    $page = request('page', 1);
    $p = $visit->patient2 ?? null;
@endphp

<link rel="stylesheet" href="/css/select2.min.css">
<style>
    .rx-hero { background: linear-gradient(135deg,#0d9488 0%,#0ea5e9 100%); border-radius:16px; color:#fff; }
    .rx-card { border:0; border-radius:14px; }
    .rx-table thead th { background:#f1f5f9; color:#475569; font-size:.82rem; text-transform:uppercase; letter-spacing:.03em; }
    .rx-table td { vertical-align:middle; }
    .rx-table input.form-control { min-height:44px; }        /* แตะง่ายบนแท็บเล็ต */
    .select2-container .select2-selection--single { height:48px; display:flex; align-items:center; border-radius:10px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height:46px; }
    .stock-badge { font-size:.75rem; padding:.15rem .45rem; border-radius:6px; }
    .empty-rx { color:#94a3b8; }
    .med-order-chip { display:inline-block; background:#eef2ff; color:#3730a3; border-radius:999px; padding:.2rem .7rem; margin:.15rem; font-size:.85rem; }
</style>

<div class="container-fluid py-4">

    <a href="{{ route('visit.edit', ['visit' => $visitId, 'page' => $page]) }}" class="btn btn-light border btn-sm mb-3">
        <i class="fa fa-arrow-left"></i> กลับหน้าตรวจ
    </a>

    {{-- Patient hero --}}
    <div class="rx-hero p-4 mb-3 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="opacity-75 mb-1"><i class="fas fa-prescription me-1"></i> สั่งยา (Prescription)</div>
            <h3 class="mb-0 fw-bold">{{ $p->name ?? '-' }} {{ $p->surname ?? '' }}</h3>
            <div class="opacity-90 mt-1">
                <span class="me-3"><i class="fas fa-id-card-clip me-1"></i> HN {{ $p->code ?? '-' }}</span>
                @if($p)
                    <span class="me-3">{{ isset(\App\Models\Patient::$sexOption[$p->sex]) ? \App\Models\Patient::$sexOption[$p->sex] : '-' }}</span>
                    <span><i class="fas fa-cake-candles me-1"></i> {{ method_exists($p,'age') ? $p->age() : '-' }} ปี</span>
                @endif
            </div>
        </div>
        <div class="text-end">
            <div class="opacity-75 small">Visit #{{ $visitId }}</div>
        </div>
    </div>

    {{-- ยาที่สั่งไปแล้วใน visit นี้ --}}
    @if($visit && $visit->visitMedicines && $visit->visitMedicines->count())
        <div class="alert alert-light border rx-card mb-3">
            <b class="text-muted"><i class="fas fa-clipboard-list"></i> ยาที่สั่งไปแล้วใน visit นี้:</b>
            @foreach($visit->visitMedicines as $vm)
                <span class="med-order-chip">{{ $vm->medicine->name ?? 'ยา#'.$vm->medicine_id }}@if($vm->amount) · {{ $vm->amount }}@endif</span>
            @endforeach
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('visit-medicine.store', ['page' => $page]) }}" id="rxForm">
        {{ csrf_field() }}
        <input type="hidden" name="visit_id" value="{{ $visitId }}">
        <input type="hidden" name="page" value="{{ $page }}">

        {{-- Search & add --}}
        <div class="card rx-card shadow-sm mb-3">
            <div class="card-body">
                <label class="form-label fw-semibold"><i class="fas fa-magnifying-glass text-primary"></i> ค้นหา &amp; เพิ่มยา</label>
                <select id="med-picker" class="form-control">
                    <option value=""></option>
                    @foreach($medicines as $item)
                        <option value="{{ $item->id }}"
                            data-name="{{ $item->name }}"
                            data-lots="{{ json_encode(($lotsByMed ?? collect())->get($item->id, collect())->all()) }}"
                            data-dose="{{ $item->dose }}"
                            data-route="{{ $item->route }}"
                            data-amount="{{ $item->amount }}"
                            data-stock="{{ $item->stock }}"
                            data-unit="{{ $item->unit }}"
                            data-sig="{{ json_encode(\App\Support\DrugSig::guess($item->dose, $item->route, $item->amount, $item->unit)) }}"
                            data-low="{{ $item->isLowStock() ? 1 : 0 }}">
                            {{ $item->name }} (คงเหลือ {{ $item->stock }} {{ $item->unit }})
                        </option>
                    @endforeach
                </select>
                <div class="form-text">พิมพ์ชื่อยาเพื่อค้นหา แล้วเลือก — ระบบจะเติม dose/route/จำนวน และวิธีใช้ให้อัตโนมัติ (แก้ไขได้)
                    · ช่อง "จำนวน" ใช้ค่าตั้งต้นจากคลังยา (หน่วยที่จ่าย/คิดเงิน) — ยอดที่คิดจากวิธีใช้ขึ้นเป็นคำแนะนำใต้ช่องจำนวน</div>
            </div>
        </div>

        {{-- Prescription rows --}}
        <div class="card rx-card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table rx-table mb-0">
                        <thead>
                            <tr>
                                <th style="width:44px;">#</th>
                                <th>ชื่อยา</th>
                                @if(\App\Models\VisitMedicine::lotReady())<th style="width:14%;">Lot</th>@endif
                                <th style="width:18%;">Dose</th>
                                <th style="width:18%;">Route</th>
                                <th style="width:18%;">จำนวน</th>
                                <th style="width:56px;"></th>
                            </tr>
                        </thead>
                        <tbody id="rx-body">
                            <tr id="rx-empty"><td colspan="6" class="text-center empty-rx py-5">
                                <i class="fas fa-pills fa-2x d-block mb-2 opacity-50"></i>
                                ยังไม่มีรายการยา — ค้นหาและเลือกยาด้านบนเพื่อเพิ่ม
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-success btn-lg px-4" id="rxSave">
                <i class="fas fa-check me-1"></i> บันทึกใบสั่งยา
            </button>
            <a href="{{ route('visit.edit', ['visit' => $visitId, 'page' => $page]) }}" class="btn btn-light border btn-lg">ยกเลิก</a>
        </div>
    </form>
</div>

{{-- แม่แบบช่องวิธีใช้ — JS โคลนแล้วเปลี่ยน __I__ เป็นเลขแถว (ชุดเดียวกับหน้าแก้ไขยา) --}}
<template id="rx-sig-tpl">@include('partials.drug_sig_fields', ['prefix' => 'sig[__I__]', 'sig' => []])</template>
@include('partials.drug_sig_js')
<script src="/js/select2.min.js"></script>
<script>
$(function () {
    var rowIndex = 0;
    // ช่อง Lot ต่อจากชื่อยา (TMC ขอ 2569-10-09) — เลือกจาก lot ที่ยังเหลือในคลัง หรือพิมพ์เอง
    var LOT_ON = {{ \App\Models\VisitMedicine::lotReady() ? 'true' : 'false' }};
    var COLS = LOT_ON ? 7 : 6;
    // จ่ายยาถึงวันนัดถัดไป (2569-10-12) — ใช้เป็นจำนวนวันตั้งต้นเมื่อคลังยาไม่ได้ระบุไว้
    var APPT_DAYS = {{ json_encode($apptDays ?? null) }};

    $('#med-picker').select2({
        placeholder: 'พิมพ์ชื่อยา...',
        allowClear: true,
        width: '100%'
    });

    function refreshEmptyState() {
        if ($('#rx-body tr.rx-row').length === 0) {
            if ($('#rx-empty').length === 0) {
                $('#rx-body').append('<tr id="rx-empty"><td colspan="6" class="text-center empty-rx py-5"><i class="fas fa-pills fa-2x d-block mb-2 opacity-50"></i> ยังไม่มีรายการยา — ค้นหาและเลือกยาด้านบนเพื่อเพิ่ม</td></tr>');
            }
        } else {
            $('#rx-empty').remove();
        }
    }

    $('#med-picker').on('select2:select', function (e) {
        var opt = $(e.params.data.element);
        var id = opt.val();
        if (!id) return;

        rowIndex++;
        var low = opt.data('low') == 1;
        var stockBadge = '<span class="stock-badge ' + (low ? 'bg-danger text-white' : 'bg-success-subtle text-success') +
                         '" style="' + (low ? '' : 'background:#d1fae5;color:#059669;') + '">คงเหลือ ' +
                         (opt.data('stock') ?? 0) + ' ' + (opt.data('unit') || '') + (low ? ' • ต่ำ' : '') + '</span>';

        var esc = function (s) { return $('<div>').text(s == null ? '' : s).html(); };

        // ใช้เลขแถวเป็นคีย์ของทุกช่อง ให้ช่องวิธีใช้ (sig[n]) จับคู่กับยาตัวเดียวกันได้แน่นอน
        var n = rowIndex;
        var row = '<tr class="rx-row" data-rx-scope data-rx="' + n + '">' +
            '<td class="text-muted" rowspan="2">' + rowIndex + '</td>' +
            '<td><div class="fw-semibold">' + esc(opt.data('name')) + '</div>' + stockBadge +
                '<input type="hidden" name="medicine_id[' + n + ']" value="' + esc(id) + '"></td>' +
            // ล็อตแรกในรายการ (ในคลัง / ที่เคยพิมพ์ล่าสุด) เติมให้ พื้นเหลือง = ระบบเติม แก้ได้ (2569-10-14)
            (LOT_ON ? '<td><input type="text" name="lot_number[' + n + ']" class="form-control" maxlength="50" placeholder="Lot" autocomplete="off" list="rx-lots-' + n + '"'
                + ((opt.data('lots') || []).length ? ' value="' + esc((opt.data('lots') || [])[0]) + '" style="background:#fff3cd;" title="ล็อตที่ใช้ล่าสุด — แก้ได้" oninput="this.style.background=\'\'"' : '') + '>'
                + '<datalist id="rx-lots-' + n + '">' + (opt.data('lots') || []).map(function (l) { return '<option value="' + esc(l) + '">'; }).join('') + '</datalist></td>' : '') +
            '<td><input type="text" name="dose[' + n + ']" class="form-control" value="' + esc(opt.data('dose')) + '"></td>' +
            '<td><input type="text" name="route[' + n + ']" class="form-control" value="' + esc(opt.data('route')) + '"></td>' +
            '<td><input type="text" name="amount[' + n + ']" class="form-control js-rx-amount" value="' + esc(opt.data('amount')) + '"></td>' +
            '<td class="text-center" rowspan="2"><button type="button" class="btn btn-outline-danger btn-sm rx-remove" title="ลบ"><i class="fas fa-trash"></i></button></td>' +
            '</tr>' +
            '<tr class="rx-sig-row" data-rx-scope data-rx="' + n + '"><td colspan="' + (COLS - 2) + '" class="pt-0 border-top-0">' +
                document.getElementById('rx-sig-tpl').innerHTML.replace(/__I__/g, n) +
            '</td></tr>';

        $('#rx-body').append(row);
        refreshEmptyState();

        // เติมวิธีใช้ที่เดาจากคลังยา (หมอดู/แก้อีกที) — แถวเดียวกันผูกด้วย data-rx
        var sigRow = document.querySelector('tr.rx-sig-row[data-rx="' + n + '"]');
        var amt = document.querySelector('tr.rx-row[data-rx="' + n + '"] .js-rx-amount');
        // ช่องจำนวนอยู่คนละ <tr> กับช่องวิธีใช้ ให้แถววิธีใช้ชี้หาช่องจำนวนของแถวยาเดียวกัน
        sigRow.querySelector('.js-rx-sig').dataset.amount = n;
        var __sig = $.extend({}, opt.data('sig') || {});
        if (!__sig.days && APPT_DAYS) { __sig.days = String(APPT_DAYS); }
        window.rxSigApply(sigRow.querySelector('.js-rx-sig'), __sig);
        if (amt && !amt.value) { amt.dataset.manual = ''; }

        // reset picker
        $('#med-picker').val('').trigger('change');
    });

    $('#rx-body').on('click', '.rx-remove', function () {
        var n = $(this).closest('tr').data('rx');
        $('#rx-body tr[data-rx="' + n + '"]').remove();
        refreshEmptyState();
    });

    $('#rxForm').on('submit', function (e) {
        if ($('#rx-body tr.rx-row').length === 0) {
            e.preventDefault();
            alert('กรุณาเพิ่มรายการยาอย่างน้อย 1 รายการ');
        }
    });
});
</script>
@endsection
