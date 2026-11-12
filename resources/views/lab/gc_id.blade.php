@extends('layouts.app')

{{-- แบบบันทึกผลการทดสอบพิสูจน์เชื้อหนองใน FM-STI-WI08-02 (ห้อง Lab ขอ 2569-10-02)
     ตารางกรอกไม่มี <form> เลย — บันทึกด้วย fetch ทั้งตาราง จะได้ไม่ซ้อนฟอร์มและไม่ต้องรีโหลดหน้า --}}
@section('content')
@php
    use App\Models\GcIdentification as G;
    $cols = [
        'culture'   => ['Culture*', '(G/NG/SO)'],
        'typical'   => ['Typical Colonies', '(Y/N)'],
        'gndc'      => ['GNDC', '(P/N)'],
        'oxidase'   => ['Oxidase', '(P/N)'],
        'superoxol' => ['Superoxol**', ''],
        'glucose'   => ['Glucose', '(P/N)'],
        'maltose'   => ['Maltose', '(P/N)'],
        'sucrose'   => ['Sucrose', '(P/N)'],
        'lactose'   => ['Lactose', '(P/N)'],
        'final_id'  => ['Final Identification', '(NG/Non-NG)'],
    ];
    $thDate = function ($d) {
        if (! $d) { return ''; }
        $p = explode('-', $d);
        if (count($p) !== 3) { return $d; }
        $y = (int) $p[0] < 2400 ? (int) $p[0] + 543 : (int) $p[0];   // ข้อมูลยุคแรกเก็บ ค.ศ.
        return $p[2] . '/' . $p[1] . '/' . $y;
    };
    $dis = $ready ? '' : 'disabled';
@endphp
<style>
    .gc-hero { background: linear-gradient(135deg,#0f766e 0%,#0891b2 100%); border-radius:16px; color:#fff; }
    .gc-table { font-size:.85rem; }
    .gc-table th { background:#f1f5f9; text-align:center; vertical-align:middle; white-space:nowrap; font-size:.75rem; line-height:1.2; }
    .gc-table th small { display:block; font-weight:400; color:#64748b; }
    .gc-table td { vertical-align:middle; padding:3px 4px; }
    .gc-table select { min-width:58px; padding:2px 18px 2px 4px; font-size:.85rem; background-position:right 3px center; }
    .gc-table select.wide { min-width:86px; }
    .gc-table input.labno { width:110px; font-size:.85rem; padding:2px 4px; }
    .gc-table input.remark { min-width:140px; font-size:.85rem; padding:2px 4px; }
    .gc-table .dirty { background-color:#fef9c3 !important; border-color:#ca8a04; }
    .gc-table tr.is-done td { background:#f0fdf4; }
    .gc-hn { font-weight:700; color:#0f766e; }
    .gc-sum td, .gc-sum th { padding:2px 8px; font-size:.82rem; }
</style>

<div class="container-fluid py-4">
    <div class="gc-hero p-4 mb-3 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h4 class="mb-1 fw-bold"><i class="fas fa-microscope me-2"></i>แบบบันทึกผลการทดสอบพิสูจน์เชื้อหนองใน</h4>
            <div class="opacity-75 small">FM-STI-WI08-02 REV1 · รายการ Culture ตามวันเก็บสิ่งส่งตรวจ</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('lab.index') }}" class="btn btn-light btn-sm"><i class="fa fa-arrow-left me-1"></i> ห้อง Lab</a>
            <a href="{{ route('lab.gcId.print', ['date' => $date, 'done' => $showDone ? 1 : 0]) }}" target="_blank" class="btn btn-light btn-sm fw-bold">
                <i class="fa fa-print me-1"></i> พิมพ์แบบฟอร์ม
            </a>
        </div>
    </div>

    @unless($ready)
        <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle me-1"></i>
            ยังไม่ได้สร้างตารางเก็บผลทดสอบ (รอผู้ดูแลระบบ migrate) — ตอนนี้ดูรายการและพิมพ์ใบงานไปกรอกมือได้ แต่ยังบันทึกผลในหน้านี้ไม่ได้
        </div>
    @endunless

    {{-- ตัวกรอง: ฟอร์ม GET แยกเดี่ยว ไม่ได้ครอบตาราง --}}
    <form method="GET" action="{{ route('lab.gcId') }}" id="gc-filter" class="card card-body shadow-sm border-0 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="small fw-bold text-muted mb-1">วันเก็บสิ่งส่งตรวจ (Collected date)</label>
                <input type="text" name="date" class="form-control gc-date" value="{{ $dateTh }}" autocomplete="off" style="width:150px">
            </div>
            <div class="col-auto">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="done" value="1" id="gc-done" {{ $showDone ? 'checked' : '' }} onchange="this.form.submit()">
                    <label class="form-check-label" for="gc-done">แสดงที่ลงผลแล้ว</label>
                </div>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary"><i class="fa fa-search me-1"></i> ดึงรายการ</button>
            </div>
            <div class="col text-end small text-muted">
                {{ $showDone ? 'แสดงทุกรายการ Culture ของวันนี้' : 'แสดงเฉพาะรายการที่ยังไม่มีผล (ลงผล Culture ในใบ Lab หรือ Final ID แล้ว = หลุดจากรายการ)' }}
            </div>
        </div>
    </form>

    @if($waitingOfficial->count())
        <div class="alert alert-info py-2 small">
            <i class="fa fa-info-circle me-1"></i>
            ลง Final ID แล้ว แต่ผล Culture ในใบ Lab ยังว่าง {{ $waitingOfficial->count() }} รายการ — ต้องไปลงผลทางการที่หน้าลงผล Lab ด้วย:
            @foreach($waitingOfficial as $w)
                <a href="{{ route('lab.edit', $w->lab_id) }}" target="_blank" class="ms-1">{{ $w->hn }} ({{ $w->final_id }})</a>@if(!$loop->last),@endif
            @endforeach
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-2">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="fw-bold">{{ $rows->count() }} รายการ</span>
                <span class="text-muted small">· {{ $rows->pluck('hn')->unique()->count() }} คน</span>
                <span class="ms-auto small" id="gc-msg"></span>
                <button type="button" class="btn btn-success btn-sm" id="gc-save" {{ $dis }}><i class="fa fa-save me-1"></i> บันทึกทั้งหมด</button>
                {{-- สองปุ่มนี้แค่ลงชื่อ "Reported by / Approved by" ท้ายแบบฟอร์มที่พิมพ์ — ไม่ใช่ Report/Approve ผลในใบ Lab
                     เดิมเขียนแค่ Report / Approve ผู้ใช้เข้าใจว่าผลจะไปโชว์ในใบ Lab (ถาม 2569-10-02) จึงเขียนให้ชัด --}}
                <button type="button" class="btn btn-outline-primary btn-sm js-gc-stamp" data-action="report" {{ $dis }}
                        title="ลงชื่อผู้รายงานท้ายแบบฟอร์มที่พิมพ์ — ไม่ได้ส่งผลไปใบ Lab"><i class="fa fa-signature me-1"></i> ลงชื่อผู้รายงาน (ในแบบฟอร์ม)</button>
                <button type="button" class="btn btn-outline-dark btn-sm js-gc-stamp" data-action="approve" {{ $dis }}
                        title="ลงชื่อผู้อนุมัติท้ายแบบฟอร์มที่พิมพ์ — ไม่ได้ส่งผลไปใบ Lab"><i class="fa fa-signature me-1"></i> ลงชื่อผู้อนุมัติ (ในแบบฟอร์ม)</button>
            </div>
            <div class="alert alert-warning py-1 px-2 small mb-2">
                <i class="fa fa-circle-info me-1"></i> ผลในหน้านี้ใช้กับแบบฟอร์มพิสูจน์เชื้อเท่านั้น <b>ยังไม่ไปขึ้นเป็นผล Culture ในใบ Lab</b> —
                ลงผล Culture และ Report/Approve ที่หน้าลงผลใบ Lab เหมือนเดิม (กดลิงก์ในคอลัมน์ "ผลในใบ Lab")
            </div>
            @if($stamps['reported']['names'] || $stamps['approved']['names'])
                <div class="small text-muted mb-2">
                    @if($stamps['reported']['names']) Reported by {{ $stamps['reported']['names'] }} ({{ $stamps['reported']['date'] }}) · {{ $stamps['reported']['count'] }} แถว @endif
                    @if($stamps['approved']['names']) &nbsp;|&nbsp; Approved by {{ $stamps['approved']['names'] }} ({{ $stamps['approved']['date'] }}) · {{ $stamps['approved']['count'] }} แถว @endif
                </div>
            @endif
            <div class="table-responsive">
                <table class="table table-bordered table-sm gc-table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Lab number</th>
                            <th>H.N.</th>
                            <th>EGASP No.</th>
                            <th>Collected<small>date</small></th>
                            @foreach($cols as $k => $c)
                                <th>{{ $c[0] }}<small>{{ $c[1] }}</small></th>
                            @endforeach
                            <th>Remark</th>
                            <th>ผลในใบ Lab</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $i => $r)
                            <tr data-sub="{{ $r->sub_id }}" class="{{ $r->done ? 'is-done' : '' }}">
                                <td class="text-center text-muted">{{ $i + 1 }}</td>
                                <td>
                                    <input type="text" class="form-control form-control-sm labno gc-in" data-f="lab_no"
                                           value="{{ $r->lab_no ?? '' }}" data-orig="{{ $r->lab_no ?? '' }}"
                                           placeholder="{{ $r->LN ?: 'STI69-' }}" maxlength="50" {{ $dis }}>
                                </td>
                                <td>
                                    <span class="gc-hn">{{ $r->hn }}</span>
                                    <div class="text-muted" style="font-size:.72rem">{{ $r->specimen }}</div>
                                </td>
                                <td class="text-nowrap">{{ $r->egasp }}</td>
                                <td class="text-nowrap">{{ $thDate($r->collected_date) }}</td>
                                @foreach($cols as $k => $c)
                                    <td class="text-center">
                                        <select class="form-select form-select-sm gc-in {{ in_array($k, ['superoxol', 'final_id']) ? 'wide' : '' }}"
                                                data-f="{{ $k }}" data-orig="{{ $r->{$k} }}" {{ $dis }}>
                                            <option value=""></option>
                                            @foreach(G::FIELDS[$k] as $opt)
                                                <option value="{{ $opt }}" {{ (string) $r->{$k} === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endforeach
                                <td>
                                    <input type="text" class="form-control form-control-sm remark gc-in" data-f="remark"
                                           value="{{ $r->remark }}" data-orig="{{ $r->remark }}" maxlength="255" {{ $dis }}>
                                </td>
                                <td class="small text-nowrap">
                                    @if(trim((string) $r->result) !== '')
                                        <span class="text-success fw-bold">{{ $r->result }}</span>
                                    @else
                                        <span class="text-muted">ยังไม่ลง</span>
                                    @endif
                                    <a href="{{ route('lab.edit', $r->lab_id) }}" target="_blank" title="เปิดหน้าลงผลใบ Lab"><i class="fa fa-external-link-alt ms-1"></i></a>
                                    <span class="gc-done-badge badge bg-success ms-1 {{ $r->done ? '' : 'd-none' }}">เสร็จ</span>
                                    @if(!empty($r->approved_by))<span class="badge bg-dark ms-1">Approved</span>
                                    @elseif(!empty($r->reported_by))<span class="badge bg-primary ms-1">Reported</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="17" class="text-center text-muted py-4">
                                {{ $showDone ? 'ไม่มีรายการ Culture ที่เก็บวันนี้' : 'ไม่มีรายการ Culture ที่ยังไม่มีผล ของวันที่เก็บนี้' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="small text-muted mt-2">
                Culture* (G/NG/SO) = Growth/No Growth/Secondary organisms · (P/N) = Positive/Negative ·
                Superoxol** = Negative, 1+, 2+, 3+, 4+ · Final Identification = Neisseria gonorrhoeae / Non-Neisseria gonorrhoeae ·
                ช่องที่แก้แล้วยังไม่บันทึกเป็นสีเหลือง · แก้ผลหลังลงชื่อแล้ว ต้องกดลงชื่อใหม่
            </div>
        </div>
    </div>

    {{-- ตัวเลขไว้เทียบกับห้อง Lab --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-2">
            <div class="fw-bold mb-1">จำนวน Culture ตามวันเก็บ 30 วันล่าสุด ({{ $thDate($summary['from']) }} – {{ $thDate($summary['to']) }})</div>
            <div class="small text-muted mb-2">นับเป็นรายการ (คนหนึ่งเก็บ 2 ตำแหน่ง = 2 รายการ) · "ค้าง" = ยังไม่มีผล Culture ในใบ Lab และยังไม่ลง Final ID</div>
            <div class="table-responsive">
            <table class="table table-sm table-bordered gc-sum mb-1 w-auto">
                <thead><tr><th>วันเก็บ</th><th class="text-end">รายการ</th><th class="text-end">คน</th><th class="text-end">ค้าง</th><th></th></tr></thead>
                <tbody>
                @forelse($summary['byDate'] as $s)
                    <tr class="{{ $s->collected_date === $date ? 'table-info' : '' }}">
                        <td>{{ $thDate($s->collected_date) }}</td>
                        <td class="text-end">{{ $s->total }}</td>
                        <td class="text-end">{{ $s->people }}</td>
                        <td class="text-end {{ $s->pending ? 'text-danger fw-bold' : 'text-muted' }}">{{ (int) $s->pending }}</td>
                        <td><a href="{{ route('lab.gcId', ['date' => $s->collected_date]) }}">เปิด</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">ไม่มี Culture ในช่วงนี้</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
            <div class="small text-muted">
                สั่ง Culture แล้วแต่ใบ Lab ยังไม่มีวันเก็บ (30 วัน): {{ $summary['noCollected'] }} รายการ — จะยังไม่ขึ้นในหน้านี้จนกว่าจะลงวันเก็บ
                · ค้างไม่มีผลที่เก่ากว่า 30 วัน (ตั้งแต่ 1 ม.ค. 2569): {{ $summary['olderPending'] }} รายการ
            </div>
        </div>
    </div>
</div>

<script src="/js/bootstrap-datepicker.js"></script>
<script src="/js/bootstrap-datepicker-thai.js"></script>
<script src="/js/bootstrap-datepicker.th.js"></script>
@include('partials.th_datepicker_guard')
<script>
(function () {
    window.bindThDatepicker('#gc-filter .gc-date', { autoclose: true, todayHighlight: true, format: 'dd/mm/yyyy' });
    if (window.jQuery) {
        jQuery('#gc-filter .gc-date').on('changeDate', function () { document.getElementById('gc-filter').submit(); });
    }

    var saveUrl  = @json(route('lab.gcId.save'));
    var stampUrl = @json(route('lab.gcId.stamp'));
    var date     = @json($date);
    var token    = @json(csrf_token());
    var msg      = document.getElementById('gc-msg');

    function say(text, ok) {
        msg.textContent = text;
        msg.className = 'ms-auto small fw-bold ' + (ok ? 'text-success' : 'text-danger');
    }
    function markDirty(el) {
        el.classList.toggle('dirty', el.value !== (el.dataset.orig || ''));
    }
    // ผูกที่ document — ตารางอาจถูกวาดใหม่ภายหลัง
    document.addEventListener('change', function (e) { if (e.target.classList && e.target.classList.contains('gc-in')) markDirty(e.target); });
    document.addEventListener('input',  function (e) { if (e.target.classList && e.target.classList.contains('gc-in')) markDirty(e.target); });

    function collect() {
        var rows = {}, n = 0;
        document.querySelectorAll('.gc-table tr[data-sub]').forEach(function (tr) {
            tr.querySelectorAll('.gc-in').forEach(function (el) {
                // ส่งเฉพาะช่องที่แก้ — อีกเครื่องที่เปิดใบเดียวกันจะไม่โดนทับช่องที่เราไม่ได้แตะ
                if (el.value === (el.dataset.orig || '')) return;
                var id = tr.dataset.sub;
                rows[id] = rows[id] || {};
                rows[id][el.dataset.f] = el.value;
                n++;
            });
        });
        return { rows: rows, n: n };
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false, message: 'เซิร์ฟเวอร์ตอบกลับผิดพลาด (' + r.status + ')' }; });
        });
    }

    var saveBtn = document.getElementById('gc-save');
    if (saveBtn) saveBtn.addEventListener('click', function () {
        var c = collect();
        if (!c.n) { say('ไม่มีอะไรเปลี่ยน', true); return; }
        saveBtn.disabled = true;
        post(saveUrl, { rows: c.rows }).then(function (res) {
            say(res.message || (res.ok ? 'บันทึกแล้ว' : 'บันทึกไม่สำเร็จ'), !!res.ok);
            if (!res.ok) return;
            // ค่าที่ส่งไปกลายเป็นค่าตั้งต้นใหม่ · แถวที่ลงผลครบแล้วขึ้นป้าย "เสร็จ" (หายจากรายการค้างเมื่อรีโหลด)
            Object.keys(c.rows).forEach(function (id) {
                var tr = document.querySelector('.gc-table tr[data-sub="' + id + '"]');
                if (!tr) return;
                tr.querySelectorAll('.gc-in').forEach(function (el) { el.dataset.orig = el.value; el.classList.remove('dirty'); });
                var info = (res.rows || {})[id];
                if (info) {
                    tr.classList.toggle('is-done', !!info.done);
                    var b = tr.querySelector('.gc-done-badge');
                    if (b) b.classList.toggle('d-none', !info.done);
                }
            });
        }).catch(function () { say('ส่งข้อมูลไม่สำเร็จ — เช็คการเชื่อมต่อแล้วกดบันทึกอีกครั้ง', false); })
          .finally(function () { saveBtn.disabled = false; });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.js-gc-stamp');
        if (!btn) return;
        if (collect().n) { say('มีช่องที่ยังไม่บันทึก — กดบันทึกก่อน', false); return; }
        var label = btn.dataset.action === 'approve' ? 'ผู้อนุมัติ' : 'ผู้รายงาน';
        if (!confirm('ลงชื่อ' + label + 'ในแบบฟอร์ม สำหรับทุกแถวที่ลงผลแล้วของวันเก็บนี้? (ไม่ได้ส่งผลไปใบ Lab)')) return;
        btn.disabled = true;
        post(stampUrl, { date: date, action: btn.dataset.action }).then(function (res) {
            say(res.message || '', !!res.ok);
            if (res.ok) setTimeout(function () { location.reload(); }, 800);
        }).catch(function () { say('ส่งข้อมูลไม่สำเร็จ', false); })
          .finally(function () { btn.disabled = false; });
    });

    window.addEventListener('beforeunload', function (e) {
        if (collect().n) { e.preventDefault(); e.returnValue = ''; }
    });
})();
</script>
@endsection
