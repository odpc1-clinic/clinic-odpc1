@extends('layouts.app')

@section('content')
{{-- ตัวใหญ่อ่านง่าย (พยาบาลอาวุโส) --}}
<style>
    #r506-page { font-size: 1.12rem; }
    #r506-page table.r506-big td,
    #r506-page table.r506-big th { font-size: 1.1rem; padding: .55rem .6rem; vertical-align: middle; }
    #r506-page .r506-row, #r506-page #r506-check-all { width: 1.35rem; height: 1.35rem; cursor: pointer; }
    #r506-page .badge { font-size: .95rem; }
    #r506-page .form-control, #r506-page .form-select { font-size: 1.08rem; }
</style>
<div class="container-fluid py-2" id="r506-page">

    <div class="d-flex align-items-center flex-wrap mb-2">
        <h4 class="mb-0"><i class="fas fa-file-medical me-2"></i>รายงานเฝ้าระวังโรค 506 (STI)</h4>
        <span class="ms-3 text-muted">เลือกช่วงวัน → ติ๊กเคส → สร้างไฟล์ .dbf นำเข้าโปรแกรม 506</span>
    </div>

    {{-- ตัวกรอง + สรุป อยู่บรรทัดเดียวกัน (ลดความสูงหน้า) --}}
    <form method="GET" action="{{ route('r506.index') }}" class="card card-body py-2 mb-2">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0 small">ตั้งแต่</label>
                <input type="text" name="from" value="{{ $fromTh }}" autocomplete="off"
                       class="form-control form-control-sm date" placeholder="วว/ดด/ปปปป" style="width:120px" readonly>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">ถึง</label>
                <input type="text" name="to" value="{{ $toTh }}" autocomplete="off"
                       class="form-control form-control-sm date" placeholder="วว/ดด/ปปปป" style="width:120px" readonly>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">สถานะ</label>
                <select name="status" class="form-select form-select-sm" style="width:130px">
                    <option value="unsent" {{ $status==='unsent'?'selected':'' }}>ยังไม่ส่ง</option>
                    <option value="all"    {{ $status==='all'?'selected':'' }}>ทั้งหมด</option>
                    <option value="sent"   {{ $status==='sent'?'selected':'' }}>ส่งแล้ว</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm"><i class="fas fa-search"></i> แสดง</button>
            </div>
            <div class="col-auto ms-auto d-flex gap-1 align-items-center small">
                <span class="badge bg-secondary">ทั้งหมด {{ $countTotal }}</span>
                <span class="badge bg-primary">ติ๊ก {{ $countSelected }}</span>
                <span class="badge bg-dark">ส่งแล้ว {{ $countSent }}</span>
                @if($countPartialAddr)
                    <span class="badge bg-warning text-dark"><i class="fas fa-triangle-exclamation"></i> ที่อยู่ไม่ครบ {{ $countPartialAddr }}</span>
                @endif
            </div>
        </div>
    </form>

    @if(session('success'))<div class="alert alert-success py-1 mb-2">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger py-1 mb-2">{{ session('error') }}</div>@endif

    <form method="POST" id="r506-form" action="{{ route('r506.select') }}">
        @csrf
        <input type="hidden" name="from" value="{{ $from }}">
        <input type="hidden" name="to" value="{{ $to }}">
        <input type="hidden" name="status" value="{{ $status }}">

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-2 r506-big">
                <thead class="table-light">
                    <tr>
                        <th style="width:34px"><input type="checkbox" id="r506-check-all" title="เลือกทั้งหมด"></th>
                        <th>HN</th>
                        <th>ชื่อ-สกุล</th>
                        <th>วินิจฉัย → 506</th>
                        <th style="width:90px">วันที่</th>
                        <th style="width:125px">วันที่เริ่มป่วย</th>
                        <th>ที่อยู่</th>
                        @if($status !== 'unsent')<th style="width:110px">สถานะ</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($cases as $c)
                    <tr class="{{ $c['sent'] ? 'table-light text-muted' : ($c['addr']!=='ok' ? 'table-warning' : '') }}">
                        <td>
                            @if($c['sent'])
                                <i class="fas fa-lock text-muted" title="ส่งแล้ว แก้ไม่ได้"></i>
                            @else
                                <input type="checkbox" class="r506-row" name="diag[]" value="{{ $c['diag_id'] }}"
                                       data-addr="{{ $c['addr'] }}" {{ $c['checked'] ? 'checked' : '' }}>
                            @endif
                        </td>
                        <td>{{ $c['hn'] }}</td>
                        <td>{{ $c['name'] }}</td>
                        <td>{{ $c['disease'] }} <span class="badge bg-info text-dark">{{ $c['code506'] }}</span></td>
                        <td>{{ \Illuminate\Support\Str::of($c['vdate'])->explode('-')->reverse()->implode('/') }}</td>
                        {{-- วันเริ่มป่วย = ค่าเดียวต่อ visit (แก้ที่หน้าตรวจก็ได้ หรือแก้ตรงนี้เลย) ส่งเข้าช่อง DATESICK ของไฟล์ 506
                             ถ้า visit เดียวมีหลายวินิจฉัยที่รายงาน 506 จะเห็นช่องนี้ซ้ำหลายแถว แก้ช่องไหนก็อัปเดตช่องที่เหลือของ visit เดียวกันให้เอง --}}
                        <td>
                            {{-- Bootstrap ที่นี่เป็น 5.1.3 ห้ามใช้ bg-*-subtle (มองไม่เห็น) ใช้สีตรงๆ แทน --}}
                            <input type="text" class="form-control form-control-sm date js-r506-onset"
                                   @if(!$c['onset_set']) style="background:#fff3cd;border-color:#ffc107;" @endif
                                   data-visit="{{ $c['visit_id'] }}" data-orig="{{ $c['onset'] }}" value="{{ $c['onset'] }}"
                                   autocomplete="off" placeholder="ว/ด/ป"
                                   title="{{ $c['onset_set'] ? 'วันที่เริ่มป่วยที่บันทึกไว้' : 'ยังไม่ได้ลงวันเริ่มป่วย — ใช้วันที่มารับบริการไปก่อน' }}">
                        </td>
                        <td>
                            @if($c['addr']==='ok')
                                <span class="text-success"><i class="fas fa-check-circle"></i></span>
                                <span>{{ $c['addr_text'] }}</span>
                            @else
                                @if($c['addr']==='partial')
                                    <span class="badge bg-warning text-dark" title="จับคู่รหัสตำบลไม่ได้"><i class="fas fa-triangle-exclamation"></i> รหัสไม่ครบ</span>
                                    <span>{{ $c['addr_text'] ?: '' }}</span>
                                @else
                                    <span class="badge bg-danger"><i class="fas fa-ban"></i> ไม่มีที่อยู่</span>
                                @endif
                                @if(!$c['sent'])
                                    <a href="{{ route('patient.edit', $c['patient_id']) }}" target="_blank"
                                       class="ms-1 text-decoration-none" title="เปิดหน้าลงทะเบียนเพื่อแก้ที่อยู่ (แท็บใหม่)">
                                        <i class="fas fa-pen-to-square"></i> แก้ที่อยู่
                                    </a>
                                @endif
                            @endif
                        </td>
                        @if($status !== 'unsent')
                        <td>
                            @if($c['sent'])
                                <span class="badge bg-dark">ส่งแล้ว {{ \Illuminate\Support\Carbon::parse($c['sent_at'])->format('d/m/y') }}</span>
                            @else
                                <span class="badge bg-light text-dark border">ยังไม่ส่ง</span>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">ไม่พบเคสในช่วงนี้</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(count($cases))
        <div class="d-flex gap-2 sticky-bottom bg-white py-2 border-top">
            <button type="submit" class="btn btn-outline-secondary">
                <i class="fas fa-floppy-disk"></i> บันทึกการเลือก
            </button>
            <button type="button" id="r506-export-btn" class="btn btn-success btn-lg">
                <i class="fas fa-file-export"></i> สร้างไฟล์ 506 (เฉพาะที่ติ๊ก)
            </button>
        </div>
        @endif
    </form>
</div>

<script src="/js/bootstrap-datepicker.js"></script>
<script src="/js/bootstrap-datepicker-thai.js"></script>
<script src="/js/bootstrap-datepicker.th.js"></script>
<script>
$(function () {
    $('.date').datepicker({
        language: 'th-th',
        format: 'dd/mm/yyyy',
        autoclose: true,
        todayHighlight: true
    });
});

(function () {
    var form = document.getElementById('r506-form');
    var checkAll = document.getElementById('r506-check-all');
    if (checkAll) {
        checkAll.addEventListener('change', function () {
            document.querySelectorAll('.r506-row').forEach(function (cb) { cb.checked = checkAll.checked; });
        });
    }
    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : (form.querySelector('input[name=_token]') || {}).value;
    }
    var btn = document.getElementById('r506-export-btn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var checked = Array.prototype.slice.call(document.querySelectorAll('.r506-row:checked'));
        if (checked.length === 0) {
            if (window.Swal) Swal.fire({icon:'info', title:'ยังไม่ได้ติ๊กเคสที่จะส่ง'}); else alert('ยังไม่ได้ติ๊กเคสที่จะส่ง');
            return;
        }
        var bad = checked.filter(function (cb) { return cb.dataset.addr !== 'ok'; }).length;
        var html = 'จะสร้างไฟล์ 506 จำนวน <b>' + checked.length + '</b> เคส'
            + (bad ? '<br><span style="color:#b8860b">⚠ ที่อยู่ไม่ครบ ' + bad + ' เคส (ADDRCODE จะว่าง)</span>' : '')
            + '<br><small class="text-muted">ตรวจสอบว่า<b>บันทึกข้อมูลการตรวจ</b>ของทุกเคสแล้ว</small>';
        function doExport() {
            var fd = new FormData(form);
            fd.set('_token', csrf());
            fetch('{{ route('r506.export') }}', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: fd
            })
            .then(function (r) {
                if (!r.ok) throw new Error(r.status);
                var dispo = r.headers.get('Content-Disposition') || '';
                var m = dispo.match(/filename="?([^"]+)"?/);
                var fname = m ? m[1] : 'r506.dbf';
                return r.blob().then(function (b) { return { b: b, fname: fname }; });
            })
            .then(function (res) {
                var url = URL.createObjectURL(res.b);
                var a = document.createElement('a');
                a.href = url; a.download = res.fname; document.body.appendChild(a); a.click();
                a.remove(); URL.revokeObjectURL(url);
                setTimeout(function () { window.location.reload(); }, 800);
            })
            .catch(function () {
                if (window.Swal) Swal.fire({icon:'error', title:'สร้างไฟล์ไม่สำเร็จ'}); else alert('สร้างไฟล์ไม่สำเร็จ');
            });
        }
        if (window.Swal) {
            Swal.fire({
                title: 'ยืนยันสร้างไฟล์ 506?', html: html, icon: 'question',
                showCancelButton: true, confirmButtonText: 'ยืนยันและดาวน์โหลด', cancelButtonText: 'ยกเลิก'
            }).then(function (res) { if (res.isConfirmed) doExport(); });
        } else {
            if (confirm('จะสร้างไฟล์ 506 จำนวน ' + checked.length + ' เคส (ที่อยู่ไม่ครบ ' + bad + ')\nยืนยัน?')) doExport();
        }
    });
})();
</script>

{{-- ปฏิทินไทย + บันทึกวันเริ่มป่วยทันทีที่แก้ --}}
<script src="/js/bootstrap-datepicker.js"></script>
<script src="/js/bootstrap-datepicker-thai.js"></script>
<script src="/js/bootstrap-datepicker.th.js"></script>
<script>
$(function () {
    $('#r506-page .date').datepicker({
        language: 'th-th', format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true
    });

    function saveOnset(el) {
        var $el = $(el);
        var visitId = $el.data('visit');
        var newVal = $el.val();
        var prevVal = $el.data('saved') !== undefined ? $el.data('saved') : $el.data('orig');

        $.post('{{ route('r506.onset') }}', {
            _token: '{{ csrf_token() }}',
            visit_id: visitId,
            value: newVal
        })
        .done(function (j) {
            if (!j || !j.ok) { return; }
            // วันเริ่มป่วยเป็นค่าเดียวต่อ visit — ถ้า visit นี้มีหลายแถว (หลายวินิจฉัย) อัปเดตให้ครบทุกช่อง
            var $siblings = $('.js-r506-onset[data-visit="' + visitId + '"]');
            $siblings.val(newVal).data('saved', newVal)
                     .css({ background: '', 'border-color': '' })
                     .css('box-shadow', '0 0 0 .18rem rgba(25,135,84,.35)');
            setTimeout(function () { $siblings.css('box-shadow', ''); }, 900);
        })
        .fail(function (x) {
            var msg = (x.responseJSON && x.responseJSON.msg) ? x.responseJSON.msg : 'บันทึกไม่สำเร็จ';
            if (window.Swal) Swal.fire({icon:'warning', title: msg}); else alert(msg);
            $el.val(prevVal);   // คืนค่าเดิมถ้าบันทึกไม่ผ่าน (เช่น ส่ง 506 ไปแล้ว) กันช่องค้างค่าที่ไม่ได้บันทึกจริง
        });
    }

    // datepicker เปลี่ยนค่าผ่าน jQuery จึงผูกที่ changeDate ตรงๆ + เผื่อพิมพ์เองด้วย change
    $('#r506-page').on('changeDate change', '.js-r506-onset', function () { saveOnset(this); });
});
</script>
@endsection
