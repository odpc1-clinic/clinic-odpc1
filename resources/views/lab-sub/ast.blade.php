@extends('layouts.app')

{{-- ลงผลความไวต่อยาของ Neisseria gonorrhoeae (GC AST) — ห้อง Lab ขอ 2569-09-30
     ผลแปลเสนอให้อัตโนมัติจากเกณฑ์ใน App\Support\GcAst แก้ได้ก่อนบันทึก · หน้านี้เป็นหน้าเต็ม สคริปต์ทำงานได้ --}}
@section('content')
@php
    $__ast   = (array) ($sub->ast ?? []);
    $__d     = (array) ($__ast['drugs'] ?? []);
    $__rules = collect(\App\Support\GcAst::DRUGS);
@endphp
<style>
    .ast-table td, .ast-table th { vertical-align: middle; }
    .ast-i-R { color: #b91c1c; font-weight: 700; }
    .ast-alert-row { background: #fee2e2 !important; }
    @media print { .no-print, nav, .navbar { display: none !important; } }
</style>
<div class="container py-3" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div>
            <h4 class="fw-bold mb-0"># Antimicrobial susceptibility testing of <i>Neisseria gonorrhoeae</i></h4>
            <div class="text-muted">
                {{ $patient->name ?? '' }} {{ $patient->surname ?? '' }} · HN {{ $patient->code ?? '-' }}
                @if($lab->LN) · LN {{ $lab->LN }} @endif
                · Culture: <b>{{ $sub->result }}</b> ({{ $sub->specimenFrom() }})
            </div>
        </div>
        <div class="no-print d-flex gap-2">
            {{-- กลับหน้าลงผลของใบ Lab นี้เสมอ — เดิมใช้ "หน้าก่อนหน้า" พอกดบันทึกแล้วหน้าก่อนหน้าคือหน้านี้เอง เลยเด้งไปหน้าแรก --}}
            <a href="{{ route('lab.edit', $lab->id) }}" class="btn btn-light border btn-sm">
                <i class="fa fa-arrow-left"></i> กลับหน้าลงผล Lab
            </a>
            <a href="{{ route('lab-sub.ast.print', $sub->id) }}" target="_blank" class="btn btn-outline-primary btn-sm {{ empty($sub->ast) ? 'disabled' : '' }}">
                <i class="fa fa-print"></i> พิมพ์ใบรายงาน
            </a>
        </div>
    </div>

    @if(! $sub->needsAst())
        <div class="alert alert-warning py-2 no-print">ผล Culture ตอนนี้ไม่ใช่ Growth / GC Growth — ปกติไม่ต้องลง AST (ยังบันทึกได้)</div>
    @endif
    @if($__alerts = \App\Support\GcAst::alerts($sub->ast))
        <div class="alert alert-danger py-2"><i class="fa fa-triangle-exclamation me-1"></i>
            <b>พบเชื้อดื้อ {{ implode(', ', $__alerts) }}</b> — ต้องแจ้งแพทย์/ผู้รับผิดชอบ</div>
    @endif

    @if(\App\Support\GcAst::pendingApproval($lab, $sub->ast))
        <div class="alert alert-warning py-2"><i class="fa fa-clock me-1"></i>
            ผล AST นี้บันทึก/แก้ <b>หลังจาก Approve ใบ Lab แล้ว</b> — พยาบาล/หมอยังไม่เห็น และใบพิมพ์ยังเป็น PRELIMINARY
            จนกว่าจะกด Approve อีกครั้งที่หน้าลงผล Lab</div>
    @endif
    @unless($canEdit)
        <div class="alert alert-secondary py-2 no-print">ดูอย่างเดียว — ลงผล/แก้ผล AST ได้เฉพาะห้อง Lab</div>
    @endunless

    <form method="POST" action="{{ route('lab-sub.ast.save', $sub->id) }}" autocomplete="off">
        @csrf
        <fieldset {{ $canEdit ? '' : 'disabled' }}>
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-bordered mb-0 ast-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:12rem;">ยา</th>
                            <th style="width:12rem;">ค่า</th>
                            <th style="width:13rem;">Interpretation</th>
                            <th>Normal range</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach([['mic', 'E-test · MIC Breakpoint (ug/ml)'], ['bl', null], ['zone', 'Disc Diffusion · Inhibition zone (mm.)']] as [$__type, $__head])
                        @if($__head)
                            <tr class="table-secondary"><td colspan="4" class="fw-bold small">{{ $__head }}</td></tr>
                        @endif
                        @foreach($__rules->where('type', $__type) as $k => $def)
                            @php
                                $__row = $__d[$k] ?? [];
                                // ผลแปลที่บันทึกไว้ตรงกับที่ระบบแปลเอง = ไม่ได้เลือกเอง → ให้ช่องอยู่ที่ "(อัตโนมัติ)"
                                // ไม่งั้นแก้ค่าทีหลัง ผลแปลเก่าค้างเพราะระบบนึกว่าเป็นค่าที่ Lab ตั้งเอง (รีวิว 2569-09-30)
                                $__auto = \App\Support\GcAst::interpret($k, $__row['v'] ?? '');
                                $__sel  = ($__row['i'] ?? '') !== '' && ($__row['i'] ?? '') !== $__auto ? $__row['i'] : '';
                            @endphp
                            <tr class="js-ast-row {{ \App\Support\GcAst::isAlert($k, $__row['v'] ?? '', $__row['i'] ?? '') ? 'ast-alert-row' : '' }}" data-alert-at="{{ $def['alert_at'] ?? '' }}" data-drug="{{ $k }}" data-alert="{{ $def['alert'] ? 1 : 0 }}">
                                <td>{{ $def['label'] }}</td>
                                <td>
                                    @if($def['type'] === 'bl')
                                        <select name="drugs[{{ $k }}][v]" class="form-select form-select-sm js-ast-v">
                                            <option value="">—</option>
                                            @foreach(['Positive', 'Negative'] as $__o)
                                                <option value="{{ $__o }}" {{ ($__row['v'] ?? '') === $__o ? 'selected' : '' }}>{{ $__o }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" name="drugs[{{ $k }}][v]" value="{{ $__row['v'] ?? '' }}"
                                               class="form-control form-control-sm js-ast-v" inputmode="decimal"
                                               placeholder="{{ $def['type'] === 'mic' ? 'เช่น 0.016' : 'mm เช่น 28' }}">
                                    @endif
                                </td>
                                <td>
                                    <select name="drugs[{{ $k }}][i]" class="form-select form-select-sm js-ast-i">
                                        <option value="">(อัตโนมัติ)</option>
                                        @foreach(\App\Support\GcAst::INTERP as $__c => $__l)
                                            <option value="{{ $__c }}" {{ $__sel === $__c ? 'selected' : '' }}>{{ $__l }}</option>
                                        @endforeach
                                    </select>
                                    <div class="small text-muted js-ast-hint"></div>
                                </td>
                                <td class="small">{{ $def['range'] }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body">
                <label class="form-label small mb-1">Comment</label>
                <input type="text" name="comment" class="form-control form-control-sm"
                       value="{{ $__ast['comment'] ?? \App\Support\GcAst::DEFAULT_COMMENT }}" maxlength="500">
                <label class="form-label small mb-1 mt-2">Detection Method</label>
                <textarea name="method" class="form-control form-control-sm" rows="2" maxlength="1000">{{ $__ast['method'] ?? \App\Support\GcAst::DEFAULT_METHOD }}</textarea>
                @if(! empty($__ast['at']))
                    <div class="small text-muted mt-2">บันทึกล่าสุด {{ $__ast['at'] }}</div>
                @endif
            </div>
            @if($canEdit)
            <div class="card-footer bg-white no-print">
                <button class="btn btn-primary"><i class="fa fa-save"></i> บันทึกผล AST</button>
                <span class="small text-muted ms-2">ช่อง Interpretation เว้นเป็น "(อัตโนมัติ)" ได้ ระบบแปลผลตามเกณฑ์ให้ตอนบันทึก — เลือกเองเฉพาะเมื่อจะแปลต่างจากเกณฑ์</span>
            </div>
            @endif
        </div>
        </fieldset>
    </form>
</div>
<script>
(function () {
    // เสนอผลแปลสด ๆ ตอนพิมพ์ค่า — เกณฑ์ชุดเดียวกับ App\Support\GcAst::interpret()
    var L = @json(\App\Support\GcAst::INTERP);
    // ตรงกับ GcAst::number() — รับ ".064" และ "0,5"
    function num(s) { var m = String(s || '').replace(/,/g, '.').match(/(\d*\.\d+|\d+)/); return m ? parseFloat(m[1]) : null; }
    function interp(drug, raw) {
        raw = String(raw || '').trim();
        if (!raw) return '';
        if (drug === 'betalactamase') { var t = raw.toLowerCase(); return t.indexOf('pos') === 0 ? 'PPNG' : (t.indexOf('neg') === 0 ? 'NonPPNG' : ''); }
        var v = num(raw); if (v === null) return '';
        switch (drug) {
            case 'cefixime':      return v <= 0.064 ? 'S' : (v < 0.25 ? 'I' : 'R');
            case 'ceftriaxone':   return v <= 0.125 ? 'S' : (v < 0.5 ? 'I' : 'R');
            case 'azithromycin':  return v <= 1 ? 'S' : 'NWT';
            case 'tetracycline':  return v <= 0.25 ? 'S' : (v < 2 ? 'I' : 'R');
            case 'penicillin':    return v >= 47 ? 'S' : (v >= 27 ? 'I' : 'R');
            case 'spectinomycin': return v >= 18 ? 'S' : (v >= 15 ? 'I' : 'R');
            case 'ciprofloxacin': return v >= 41 ? 'S' : (v >= 28 ? 'I' : 'R');
        }
        return '';
    }
    function refresh(tr) {
        var v = tr.querySelector('.js-ast-v').value, sel = tr.querySelector('.js-ast-i');
        var auto = interp(tr.dataset.drug, v), hint = tr.querySelector('.js-ast-hint');
        hint.textContent = auto ? 'ตามเกณฑ์: ' + L[auto] + (sel.value && sel.value !== auto ? ' (เลือกเองต่างจากเกณฑ์)' : '') : '';
        hint.className = 'small js-ast-hint ' + (auto === 'R' ? 'ast-i-R' : 'text-muted');
        var eff = sel.value || auto;
        // เตือนเมื่อค่าถึงเกณฑ์ (Cefixime >= 0.25 · Ceftriaxone >= 0.5) หรือผลแปลเป็น R — ตรงกับ GcAst::isAlert()
        var n = num(v), at = parseFloat(tr.dataset.alertAt);
        var hit = tr.dataset.alert === '1' && (eff === 'R' || (n !== null && !isNaN(at) && n >= at));
        tr.classList.toggle('ast-alert-row', hit);
        if (hit) { hint.textContent = '⚠ ถึงเกณฑ์ alert — ต้องแจ้งแพทย์' + (auto ? ' · ตามเกณฑ์: ' + L[auto] : ''); hint.className = 'small js-ast-hint ast-i-R'; }
    }
    document.querySelectorAll('.js-ast-row').forEach(function (tr) {
        refresh(tr);
        tr.addEventListener('input', function () { refresh(tr); });
        tr.addEventListener('change', function () { refresh(tr); });
    });
})();
</script>
@endsection
