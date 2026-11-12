@extends('layouts.app')

@section('content')
<style>
    #cs-page { font-size: 1.05rem; }
    #cs-page table.cs td, #cs-page table.cs th { vertical-align: middle; white-space: nowrap; padding: .4rem .5rem; }
    #cs-page table.cs .form-select, #cs-page table.cs .form-control { font-size: 1rem; min-width: 110px; }
    #cs-page .js-cs-field { transition: box-shadow .2s, border-color .2s; }
    #cs-page .cs-saved { border-color: #198754 !important; box-shadow: 0 0 0 .18rem rgba(25,135,84,.25) !important; }
    #cs-page .cs-error { border-color: #dc3545 !important; box-shadow: 0 0 0 .18rem rgba(220,53,69,.25) !important; }
    /* จัดกลุ่มตาม visit (เรียงตามวันที่) */
    #cs-page table.cs tr.cs-grp-1 > td { background: #f2f7ff; }
    #cs-page table.cs tr.cs-visit-top > td { border-top: 2px solid #9db8dd; }
    #cs-page .cs-ident { color: #8a97a4; }
    #cs-page .cs-partner-name { font-size: .82rem; color: #6c757d; display:block; margin-bottom:2px; }
    #cs-page .cs-empty { color: #adb5bd; font-style: italic; }
    /* ช่องที่ยังไม่ได้กรอก — แถบสีเตือนให้มาเติม */
    #cs-page .cs-need { background: #fff3cd !important; border-color: #ffc107 !important; }
</style>
<div class="container-fluid py-2" id="cs-page">

    <div class="d-flex align-items-center flex-wrap gap-3 mb-2">
        <h4 class="mb-0"><i class="fas fa-people-arrows me-2"></i>ทะเบียนติดตามผู้ป่วย 5 โรคหลัก + คู่สัมผัส</h4>
        <form method="GET" action="{{ route('contact-sti.index') }}" class="d-flex align-items-center gap-2 flex-wrap" id="cs-filter">
            <label class="mb-0">กรองตาม</label>
            <select name="mode" class="form-select form-select-sm" style="width:120px" onchange="this.form.submit()">
                <option value="visit" {{ $mode==='visit' ? 'selected' : '' }}>วันที่มา</option>
                <option value="fu" {{ $mode==='fu' ? 'selected' : '' }}>วันนัด F/U</option>
            </select>
            <input type="text" name="from" value="{{ $fromTh }}" readonly autocomplete="off"
                   class="form-control form-control-sm date" style="width:120px" placeholder="วว/ดด/ปปปป">
            <span>ถึง</span>
            <input type="text" name="to" value="{{ $toTh }}" readonly autocomplete="off"
                   class="form-control form-control-sm date" style="width:120px" placeholder="วว/ดด/ปปปป">
            <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-search"></i> แสดง</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="csQuickMonth(0)">เดือนนี้</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="csQuickMonth(-1)">เดือนก่อน</button>
        </form>
        <a href="{{ route('contact-sti.export', ['from'=>$from, 'to'=>$to, 'mode'=>$mode]) }}"
           class="btn btn-success btn-sm"><i class="fas fa-file-excel me-1"></i> Export Excel</a>
        <span class="text-muted">
            @if($mode==='fu')<span class="badge bg-info text-dark">กรองตามวันนัด F/U</span> @endif
            {{ collect($rows)->pluck('hn')->unique()->count() }} คน · {{ count($rows) }} แถว · แก้ช่องไหน เซฟอัตโนมัติ
        </span>
    </div>

    <div class="table-responsive" style="max-height: calc(100vh - 170px); overflow:auto;">
        <table class="table table-bordered align-middle cs mb-0">
            <thead class="table-light" style="position:sticky; top:0; z-index:3;">
                <tr>
                    <th>วันที่</th>
                    <th>ประเภท</th>
                    <th>เพศ</th>
                    <th>HN</th>
                    <th>ชื่อ-สกุล</th>
                    <th>โรค</th>
                    <th>วันนัด F/U</th>
                    <th>สถานะ F/U</th>
                    <th>วันมีเพศสัมพันธ์<br>ใน 3 ด.</th>
                    <th>เพศ<br>คู่สัมผัส</th>
                    <th>คู่สัมผัส</th>
                    <th>การติดตาม<br>คู่สัมผัส</th>
                    <th>การตรวจ HIV</th>
                    <th>หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>
                @php $groupParity = 0; @endphp
                @forelse($rows as $r)
                @php
                    $vf = $r['is_visit_first'];        // แถวแรกของ visit
                    if ($vf) { $groupParity ^= 1; }    // สลับสีต่อ visit
                @endphp
                <tr class="cs-grp-{{ $groupParity }} {{ $vf ? 'cs-visit-top' : '' }}">
                    {{-- ===== ระดับ visit/คนไข้ (แถวแรกของ visit เท่านั้น) ===== --}}
                    <td>{{ $vf ? $r['date'] : '' }}</td>
                    <td>
                        @if($vf)
                        <select class="form-select form-select-sm js-cs-field {{ trim((string)$r['type'])==='' ? 'cs-need' : '' }}" data-target="contact_sti" data-id="{{ $r['visit_id'] }}" data-field="patient_type">
                            <option value=""></option>
                            @foreach(\App\Models\ContactSti::$patientTypeOption as $opt)
                                <option value="{{ $opt }}" {{ $r['type']===$opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                        @endif
                    </td>
                    <td>{{ $vf ? $r['sex'] : '' }}</td>
                    <td class="cs-ident">{{ $vf ? $r['hn'] : '' }}</td>
                    <td>
                        @if($vf)
                            <a href="{{ route('visit.edit', ['visit' => $r['visit_id'], 'page' => 2]) }}" target="_blank" class="text-decoration-none" title="เปิดหน้าตรวจของ visit นี้ (ที่วินิจฉัยโรค)">{{ $r['name'] }}</a>
                        @endif
                    </td>
                    <td>{{ $vf ? $r['disease'] : '' }}</td>
                    <td>
                        @if($vf)
                            {{ $r['appointment'] }}
                            <br>
                            @if($r['came'])
                                <span class="badge bg-success" title="มี visit ถัดไป {{ $r['came_date'] }}"><i class="fas fa-check"></i> มาแล้ว {{ $r['came_date'] }}</span>
                            @endif
                        @endif
                    </td>
                    <td>
                        @if($vf)
                        <select class="form-select form-select-sm js-cs-field {{ trim((string)$r['fu_status'])==='' ? 'cs-need' : '' }}" data-target="contact_sti" data-id="{{ $r['visit_id'] }}" data-field="fu_status">
                            <option value=""></option>
                            @foreach(\App\Models\ContactSti::$fuStatusOption as $opt)
                                <option value="{{ $opt }}" {{ $r['fu_status']===$opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                        @endif
                    </td>

                    {{-- ===== ระดับคู่สัมผัส (จาก contact_people) ===== --}}
                    @if($r['contact_id'])
                    <td>
                        <input type="text" style="width:130px"
                               class="form-control form-control-sm js-cs-field {{ trim((string)$r['sex_date'])==='' ? 'cs-need' : '' }}"
                               data-target="contact_person" data-id="{{ $r['contact_id'] }}" data-field="date"
                               value="{{ $r['sex_date'] }}" placeholder="เช่น 2 วันก่อน">
                    </td>
                    <td>
                        <select class="form-select form-select-sm js-cs-field {{ in_array((string)$r['partner_sex'],['','0'],true) ? 'cs-need' : '' }}"
                                data-target="contact_person" data-id="{{ $r['contact_id'] }}" data-field="cpsex">
                            @foreach(\App\Models\ContactPerson::$cpsexOption as $key => $label)
                                <option value="{{ $key }}" {{ (string)$r['partner_sex']===(string)$key ? 'selected' : '' }}>{{ $label ?: '—' }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        @if($r['partner_name'])<span class="cs-partner-name" title="ชื่อคู่สัมผัส">{{ $r['partner_name'] }}</span>@endif
                        <select class="form-select form-select-sm js-cs-field {{ in_array((string)$r['partner_type'],['','0'],true) ? 'cs-need' : '' }}"
                                data-target="contact_person" data-id="{{ $r['contact_id'] }}" data-field="type">
                            @foreach(\App\Models\ContactPerson::$typeOption as $key => $label)
                                <option value="{{ $key }}" {{ (string)$r['partner_type']===(string)$key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select class="form-select form-select-sm js-cs-field {{ in_array((string)$r['tracing'],['','0'],true) ? 'cs-need' : '' }}"
                                data-target="contact_person" data-id="{{ $r['contact_id'] }}" data-field="trush_tracks">
                            @foreach(\App\Models\ContactPerson::$touchTracingOption as $key => $label)
                                <option value="{{ $key }}" {{ (string)$r['tracing']===(string)$key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </td>
                    @else
                    <td class="cs-empty">—</td>
                    <td class="cs-empty">—</td>
                    <td class="cs-empty" title="ยังไม่มีคู่สัมผัส เพิ่มได้ในหน้าตรวจของ visit นี้">— ไม่มีคู่สัมผัส —</td>
                    <td class="cs-empty">—</td>
                    @endif

                    {{-- ===== การตรวจ HIV (บริการเท่านั้น — ไม่โชว์ผล เพื่อรักษาความลับ) ===== --}}
                    <td>
                        @if($vf)
                        <select class="form-select form-select-sm js-cs-field {{ trim((string)$r['hiv_service'])==='' ? 'cs-need' : '' }}" data-target="contact_sti" data-id="{{ $r['visit_id'] }}" data-field="hiv_service">
                            <option value=""></option>
                            @foreach(\App\Models\ContactSti::$hivServiceOption as $opt)
                                <option value="{{ $opt }}" {{ $r['hiv_service']===$opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                        @endif
                    </td>
                    {{-- ประวัติการตรวจ Syphilis ไม่แสดงในตารางนี้ (พยาบาลกรอกที่หน้าตรวจแทน) --}}
                    <td>
                        @if($vf)
                        <input type="text" class="form-control form-control-sm js-cs-field" style="width:200px"
                               data-target="contact_sti" data-id="{{ $r['visit_id'] }}" data-field="note"
                               value="{{ $r['note'] }}" placeholder="หมายเหตุ (อื่นๆ ใส่ตรงนี้)">
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="14" class="text-center text-muted py-4">ไม่พบผู้ป่วย 5 โรคหลักในช่วงนี้</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script src="/js/bootstrap-datepicker.js"></script>
<script src="/js/bootstrap-datepicker-thai.js"></script>
<script src="/js/bootstrap-datepicker.th.js"></script>
<script>
$(function () {
    $('#cs-filter .date').datepicker({
        language: 'th-th', format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true
    });
});
// ปุ่มลัดเลือกทั้งเดือน (0 = เดือนนี้, -1 = เดือนก่อน) แล้วส่งฟอร์ม
function csQuickMonth(offset) {
    var d = new Date(); d.setDate(1); d.setMonth(d.getMonth() + offset);
    var be = d.getFullYear() + 543, m = d.getMonth() + 1;
    var last = new Date(d.getFullYear(), m, 0).getDate();
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var f = document.getElementById('cs-filter');
    f.querySelector('input[name=from]').value = '01/' + pad(m) + '/' + be;
    f.querySelector('input[name=to]').value = pad(last) + '/' + pad(m) + '/' + be;
    f.submit();
}
(function () {
    var url = '{{ route('contact-sti.save') }}';
    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }
    function save(el) {
        el.classList.remove('cs-saved', 'cs-error');
        var fd = new FormData();
        fd.append('target', el.dataset.target);
        fd.append('id', el.dataset.id);
        fd.append('field', el.dataset.field);
        fd.append('value', el.value);
        fd.append('_token', csrf());
        fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: fd
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            el.classList.add(j.ok ? 'cs-saved' : 'cs-error');
            if (j.ok) setTimeout(function () { el.classList.remove('cs-saved'); }, 1500);
        })
        .catch(function () { el.classList.add('cs-error'); });
    }
    var timers = {};
    // ช่องว่าง = ใส่แถบเตือน cs-need ; พอกรอกแล้วเอาออก
    function markNeed(el) {
        if (!el || !el.classList.contains('js-cs-field')) return;
        var v = (el.value || '').trim();
        if (v === '' || v === '0') el.classList.add('cs-need');
        else el.classList.remove('cs-need');
    }
    document.addEventListener('change', function (e) {
        var el = e.target.closest('.js-cs-field');
        if (el) { save(el); markNeed(el); }
    });
    document.addEventListener('input', function (e) {
        var el = e.target.closest('input.js-cs-field');
        if (!el) return;
        markNeed(el);
        var k = el.dataset.target + '-' + el.dataset.id + '-' + el.dataset.field;
        clearTimeout(timers[k]);
        timers[k] = setTimeout(function () { save(el); }, 900);
    });
})();
</script>
@endsection
