@extends('layouts.app')

@section('content')
<style>
    .dg-hero { background: linear-gradient(135deg,#be123c 0%,#e11d48 55%,#f43f5e 100%); border-radius:16px; color:#fff; }
</style>

@php
    $__visit = $_GET['visit'] ?? request('visit');
    $__page  = $_GET['page']  ?? request('page', 1);
    // ถ้าย้อนกลับมาเพราะกรอกไม่ครบ ให้คืนแถวเดิมทั้งหมด ไม่ใช่เริ่มใหม่แถวเดียว
    $__rows  = old('rows', [[]]);
    $existing     = $existing     ?? collect();
    $usedDiseases = $usedDiseases ?? [];
    $isTmc        = $isTmc ?? false;
    $presets      = $presets ?? [];
@endphp

<div class="container-fluid py-4">
    <div class="dg-hero p-4 mb-4 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h4 class="mb-1 fw-bold"><i class="fas fa-notes-medical me-2"></i>เพิ่มการวินิจฉัย</h4>
            <div class="opacity-75 small">Visit #{{ $__visit }} · เพิ่มได้หลายรายการในครั้งเดียว</div>
        </div>
        <a href="{{ route('visit.edit', ['visit' => $__visit, 'page' => $__page]) }}" class="btn btn-light fw-bold shadow-sm">
            <i class="fa fa-arrow-left me-1"></i> กลับหน้า Visit
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    {{-- ของที่ลงไว้แล้วใน visit นี้ — ต้องเห็นก่อนลงเพิ่ม ไม่งั้นคนที่เข้ามารอบสองจะลงซ้ำ --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex align-items-center gap-2">
            <i class="fas fa-clipboard-check text-success"></i>
            <b>ลงไว้แล้วใน Visit นี้</b>
            <span class="badge bg-secondary">{{ $existing->count() }} รายการ</span>
        </div>
        <div class="card-body py-3">
            @if($existing->isEmpty())
                <span class="text-muted"><i class="fas fa-inbox me-1"></i> ยังไม่มีการวินิจฉัยใน visit นี้</span>
            @else
                {{-- ⚠️ การ์ดนี้อยู่ "นอก" ฟอร์มหลักของหน้านี้ (ฟอร์มเริ่มด้านล่าง)
                     จึงใส่ฟอร์มลบตรงนี้ได้ ไม่เกิด form ซ้อน form --}}
                <div class="d-flex flex-wrap gap-2">
                    @foreach($existing as $d)
                        <span class="badge bg-light text-dark border p-2 d-inline-flex align-items-center gap-2" style="font-size:.95rem;">
                            <span>
                                <i class="fas fa-notes-medical text-danger me-1"></i>
                                {{ $d->disease() }}
                                <span class="text-muted">
                                    · {{ App\Models\Diagnosi::$diseaseStateOption[$d->disease_state] ?? '-' }}
                                </span>
                            </span>
                            <form method="POST" action="{{ route('diagnosis.destroy', ['diagnosi' => $d->id]) }}"
                                  class="d-inline m-0">
                                {{ csrf_field() }}
                                {{ method_field('DELETE') }}
                                <input type="hidden" name="page" value="{{ $__page }}">
                                <input type="hidden" name="from" value="create">
                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1 lh-1"
                                        title="ลบรายการนี้"
                                        onclick="return confirm('ลบการวินิจฉัย\n\n{{ addslashes($d->disease()) }}\n\nออกจาก visit นี้?');">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </form>
                        </span>
                    @endforeach
                </div>
                <div class="form-text mt-2">
                    เลือกโรคที่ตรงกับรายการข้างบน ระบบจะเตือน และถ้าซ้ำทั้งหมดจริงจะข้ามให้ ไม่บันทึกซ้ำ
                    · กดถังขยะเพื่อลบรายการที่ลงผิด แล้วอยู่หน้านี้ต่อได้เลย
                </div>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('diagnosis.store', ['page' => $__page]) }}" accept-charset="UTF-8">
        {{ csrf_field() }}
        <input type="hidden" name="visit_id" value="{{ $__visit }}">

        @if($isTmc)
            {{-- TMC และ TB: ช่อง diagnosis แบบเลือก/พิมพ์เพิ่มเอง แทนรายการโรค STI (2569-10-01) --}}
            @include('diagnosis._tmc_picker')
        @else
        <div id="dg-rows">
            @foreach($__rows as $i => $row)
                @include('diagnosis.row', ['i' => $i, 'row' => $row])
            @endforeach
        </div>
        @endif

        <div class="d-flex flex-wrap gap-2 mb-4">
            @unless($isTmc)
            <button type="button" class="btn btn-outline-primary" id="dg-add">
                <i class="fa fa-plus me-1"></i> เพิ่มอีกรายการ
            </button>
            @endunless
            <button type="submit" class="btn btn-primary ms-auto px-4">
                <i class="fa fa-save me-1"></i> บันทึกทั้งหมด
            </button>
        </div>
    </form>

    @include('sweetalert::alert')
</div>

{{-- แม่แบบของแถวใหม่ — ไม่ถูกส่งตอน submit เพราะอยู่ใน <template> --}}
@unless($isTmc)
<template id="dg-row-tpl">
    @include('diagnosis.row', ['i' => '__IDX__', 'row' => []])
</template>
@endunless

<script>
(function () {
    var box = document.getElementById('dg-rows');
    var tpl = document.getElementById('dg-row-tpl');
    if (!box || !tpl) return;

    var nextIdx = {{ count($__rows) }};

    // โชว์/ซ่อนช่องเสริมตามโรคที่เลือก — ทำเฉพาะภายในแถวที่ถูกแก้เท่านั้น
    function sync(rowEl) {
        var dEl = rowEl.querySelector('.js-dg-disease');
        var d = dEl ? dEl.value : '';

        function show(sel, on) {
            var el = rowEl.querySelector(sel);
            if (!el) return;
            el.style.display = on ? '' : 'none';
            if (!on) {   // ซ่อนแล้วต้องล้างค่า ไม่งั้นค่าที่พิมพ์ไว้ก่อนเปลี่ยนโรคจะติดไปกับที่บันทึก
                el.querySelectorAll('input[type=text]').forEach(function (i) { i.value = ''; });
                el.querySelectorAll('select').forEach(function (i) { i.value = '0'; });
            }
        }

        show('.js-dg-other', d === '11');
        show('.js-dg-term',  d === '1');
        show('.js-dg-egasp', d === '17' || d === '18');
        show('.js-dg-gf',    d === '16');

        // เตือนเมื่อเลือกโรคที่ลงไว้แล้วใน visit นี้
        var opt = dEl ? dEl.options[dEl.selectedIndex] : null;
        var dup = rowEl.querySelector('.js-dg-dup');
        if (dup) { dup.style.display = (opt && opt.getAttribute('data-used')) ? '' : 'none'; }
    }

    // เลขลำดับหัวการ์ด ต้องไล่ใหม่ทุกครั้งที่เพิ่ม/ลบ
    function renumber() {
        var rows = box.querySelectorAll('.dg-row');
        rows.forEach(function (r, n) {
            var no = r.querySelector('.dg-no');
            if (no) no.textContent = (n + 1);
            var del = r.querySelector('.js-dg-del');
            if (del) del.style.visibility = (rows.length > 1) ? 'visible' : 'hidden';   // เหลือแถวเดียวไม่ต้องมีปุ่มลบ
        });
    }

    document.getElementById('dg-add').addEventListener('click', function () {
        var html = tpl.innerHTML.split('__IDX__').join(nextIdx++);
        var wrap = document.createElement('div');
        wrap.innerHTML = html;
        var row = wrap.querySelector('.dg-row');
        box.appendChild(row);
        sync(row);
        renumber();
        var sel = row.querySelector('.js-dg-disease');
        if (sel) { sel.focus(); row.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    });

    box.addEventListener('click', function (e) {
        var del = e.target.closest('.js-dg-del');
        if (!del) return;
        if (box.querySelectorAll('.dg-row').length <= 1) return;
        del.closest('.dg-row').remove();
        renumber();
    });

    box.addEventListener('change', function (e) {
        if (e.target.matches('.js-dg-disease')) {
            sync(e.target.closest('.dg-row'));
        }
    });

    box.querySelectorAll('.dg-row').forEach(sync);
    renumber();
})();
</script>
@endsection
