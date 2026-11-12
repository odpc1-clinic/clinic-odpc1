@extends('layouts.app')

{{-- รายงาน ก.1 คลินิก STI (2569-11-10) — หน้าตาตามไฟล์ "ก.1 เม.ย. 67.xlsx" ที่คลินิกส่งทุกเดือน
     ตัวเลขคิดใน App\Services\StiK1Report · ทุกช่องแก้ทับได้ (บันทึกเอง) · ช่องที่ระบบไม่มีข้อมูล = ว่างไว้ให้กรอก --}}

@section('content')
@php
    $cols  = \App\Services\StiK1Report::COLS;
    $glabs = \App\Services\StiK1Report::GROUP_LABELS;
    $cell = function ($row, $col) use ($overrides) {
        $auto = is_array($row['vals']) ? $row['vals'][$col] : null;
        $ov   = $overrides[$row['key']][$col] ?? null;
        return [$auto, $ov];
    };
@endphp
<style>
    @font-face { font-family: 'THSarabunNew-web'; src: url('{{ asset("fonts/sarabun/THSarabunNew.ttf") }}') format('truetype'); font-weight: normal; }
    @font-face { font-family: 'THSarabunNew-web'; src: url('{{ asset("fonts/sarabun/THSarabunNew Bold.ttf") }}') format('truetype'); font-weight: bold; }
    /* ฟอร์มรายงานใช้ TH Sarabun New 16pt · ขอบ ซ้าย 3 ขวา 2 บน/ล่าง 1.5 ซม. เหมือนรายงาน TB/TMC (คลินิกขอ 2569-11-10)
       แนวนอน เพราะมี 13 คอลัมน์ */
    .k1-sheet { font-family: 'TH Sarabun New', 'THSarabunNew-web', sans-serif; font-size: 16pt; line-height: 1.15; color: #000; }
    .k1-title { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; font-weight: bold; }
    .k1-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .k1-table th, .k1-table td { border: 1px solid #000; padding: 0 .25rem; vertical-align: middle; }
    .k1-table thead th { text-align: center; font-weight: bold; line-height: 1.05; }
    .k1-table td.c { text-align: center; }
    .k1-table tr.k1-head td { font-weight: bold; }
    .k1-table td.k1-sub { padding-left: 1.2rem; }
    .k1-cell { width: 100%; border: 0; background: transparent; text-align: center; font: inherit; padding: 0; }
    .k1-cell:focus { outline: 2px solid #3b82f6; background: #eff6ff; }
    .k1-cell.ov { background: #fff3cd; }
    .k1-cell.na:placeholder-shown { background: #f3f4f6; }
    .k1-cell.saving { background: #e0f2fe; }
    .k1-cell.err { background: #fee2e2; }
    .k1-note { font-size: 14pt; }
    /* ช่องตัวเลขกว้างพอดีเลข 3 หลัก ที่เหลือให้ช่องกิจกรรม จะได้ไม่ตัดบรรทัด (กระดาษแนวนอนกว้าง 24.7 ซม. หลังหักขอบ) */
    .k1-cno { width: 1.1cm; } .k1-cunit { width: 1.4cm; } .k1-cnum { width: 1.3cm; }
    .k1-gh { font-size: 12pt; font-weight: bold; }
    .k1-edit { outline: 1px dashed #93c5fd; outline-offset: 1px; padding: 0 2px; }
    .k1-foot { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; page-break-inside: avoid; }
    .k1-sign { margin: .4rem 2rem 0 auto; width: max-content; text-align: center; line-height: 1.5; flex-shrink: 0; }
    @page { size: A4 landscape; margin: 15mm 20mm 15mm 30mm; }
    @media print {
        .no-print, nav, .navbar { display: none !important; }
        main.py-4, main > .container-fluid, .k1-page { padding: 0 !important; margin: 0 !important; max-width: none !important; }
        body { margin: 0 !important; padding: 0 !important; background: #fff !important; }
        .k1-cell, .k1-cell.ov, .k1-cell.na:placeholder-shown { background: transparent !important; }
        .k1-cell::placeholder { color: transparent; }
        .k1-edit { outline: none; }
        .k1-sheet + .k1-sheet { page-break-before: always; }
        .k1-table thead { display: table-header-group; }
        .k1-table tr { page-break-inside: avoid; }
        .k1-table th, .k1-table td { line-height: 1.05; }
        .k1-note { font-size: 13pt; }
    }
</style>

<div class="container-fluid py-3 k1-page" style="max-width: 1400px;">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3 no-print">
        <div>
            <h4 class="fw-bold mb-0"><i class="fa-solid fa-file-lines me-1"></i> รายงาน ก.1 คลินิก STI</h4>
            <div class="small text-muted">ประจำเดือน {{ $monthTh }}</div>
        </div>
        <form method="GET" action="{{ route('report.sti-k1') }}" class="d-flex gap-2">
            <select name="month" class="form-select" onchange="this.form.submit()">
                @foreach($months as $v => $label)
                    <option value="{{ $v }}" {{ $v === $ym ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fa fa-print"></i> พิมพ์</button>
        </form>
    </div>

    <div class="alert alert-light border small no-print py-2">
        <i class="fa fa-circle-info me-1"></i>
        ตัวเลขนับจากระบบ <b>คลิกแก้ได้ทุกช่อง</b> บันทึกให้เองเมื่อออกจากช่อง ·
        <span style="background:#fff3cd;padding:0 .3rem;">พื้นเหลือง</span> = ตัวเลขที่แก้เอง (ชี้ดูตัวเลขที่ระบบนับได้) ลบให้ว่าง = กลับไปใช้ตัวเลขระบบ ·
        <span style="background:#f3f4f6;padding:0 .3rem;">พื้นเทา</span> = ระบบไม่มีข้อมูล กรอกเอง ·
        แก้ช่องย่อยแล้วช่อง "รวม" ไม่บวกให้เอง
    </div>

    @foreach(['sheet1' => true, 'sheet2' => false] as $sheet => $withNo)
        <div class="k1-sheet mb-4">
            @if($withNo)
                <div class="k1-title"><span>รายงานการปฏิบัติงานป้องกันควบคุม/ดูแลรักษาโรคติดต่อทางเพศสัมพันธ์</span><span>แบบ ก.1</span></div>
                <div class="k1-title mb-1" style="font-weight:normal;">
                    <span>หน่วยงาน <span class="k1-edit" contenteditable="true" data-key="unit">คลินิกพิเศษกามโรค สคร.1 เชียงใหม่</span> ประจำเดือน {{ $monthTh }}</span>
                    <span>(ฉบับปรับปรุง พ.ศ.2555)</span>
                </div>
            @else
                <div class="k1-title mb-1"><span>แยกรายโรค ประจำเดือน {{ $monthTh }}</span></div>
            @endif
            <table class="k1-table">
                <colgroup>
                    @if($withNo)<col class="k1-cno">@endif
                    <col>
                    <col class="k1-cunit">
                    @foreach($cols as $c)<col class="k1-cnum">@endforeach
                </colgroup>
                <thead>
                    <tr>
                        @if($withNo)<th rowspan="2">ลำดับ</th>@endif
                        <th rowspan="2">กิจกรรม</th>
                        <th rowspan="2">หน่วยนับ</th>
                        <th colspan="3">จำนวน</th>
                        <th colspan="7">จำนวนแยกตามกลุ่มเป้าหมาย</th>
                    </tr>
                    <tr>
                        <th>รวม</th><th>ช</th><th>ญ</th>
                        @foreach($glabs as $g)<th class="k1-gh">{{ $g }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                @foreach($report[$sheet] as $row)
                    @if($row['vals'] === 'head')
                        <tr class="k1-head">
                            @if($withNo)<td class="c">{{ $row['no'] }}</td>@endif
                            <td colspan="{{ 2 + count($cols) }}">{{ $row['label'] }}</td>
                        </tr>
                    @else
                        <tr>
                            @if($withNo)<td class="c">{{ $row['no'] }}</td>@endif
                            <td class="{{ $row['sub'] ? 'k1-sub' : '' }}">{{ $row['label'] }}</td>
                            <td class="c">{{ $row['unit'] }}</td>
                            @foreach($cols as $c)
                                @php [$auto, $ov] = $cell($row, $c); $show = $ov ?? $auto; @endphp
                                <td>
                                    <input type="text" inputmode="numeric" class="k1-cell {{ $ov !== null ? 'ov' : '' }} {{ $auto === null ? 'na' : '' }}"
                                           value="{{ $show }}" placeholder="{{ $auto === null ? 'กรอก' : '' }}"
                                           data-key="{{ $row['key'] }}" data-col="{{ $c }}" data-auto="{{ $auto ?? '' }}" data-last="{{ $show }}"
                                           title="{{ $auto === null ? 'ระบบไม่มีข้อมูล — กรอกเอง' : 'ระบบนับได้ ' . $auto }}">
                                </td>
                            @endforeach
                        </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
            {{-- หมายเหตุซ้าย ลายเซ็นขวา บรรทัดเดียวกัน (เหมือนไฟล์ Excel) — ไม่งั้นลายเซ็นตกไปหน้าใหม่คนเดียว --}}
            <div class="k1-foot">
            <div class="k1-note mt-1">
                @if($withNo)
                    <div>*โรคติดต่อทางเพศสัมพันธ์หลัก ได้แก่ กามโรค 5 โรค และพยาธิช่องคลอด</div>
                    <div>**โรคติดต่อทางเพศสัมพันธ์อื่นๆ ได้แก่ เริม หูดหงอนไก่ หิด โลน หูดข้าวสุก</div>
                @else
                    <div>*โรคติดต่อทางเพศสัมพันธ์หลัก ได้แก่ กามโรค 5 โรค (ซิฟิลิส หนองใน หนองในเทียม กามโรคของต่อมและท่อน้ำเหลือง และแผลริมอ่อน) และพยาธิช่องคลอด</div>
                    <div>**โรคติดต่อทางเพศสัมพันธ์อื่นๆ ได้แก่ เริม เชื้อรา หูด หิด โลน</div>
                @endif
            </div>
            @if($withNo)
                <div class="k1-sign">
                    <div>ลงชื่อผู้รายงาน <span class="k1-edit" contenteditable="true" data-key="sign">..............................................................</span></div>
                    <div>(<span class="k1-edit" contenteditable="true" data-key="name">............................................</span>)</div>
                    <div><span class="k1-edit" contenteditable="true" data-key="title">พยาบาลวิชาชีพชำนาญการ</span></div>
                </div>
            @endif
            </div>
        </div>
    @endforeach

    <div class="small text-muted no-print">
        เกณฑ์: นับคนไม่ซ้ำในเดือน (3.3 นับครั้ง · 4.2/4.4 นับคู่สัมผัส) · กลุ่มเป้าหมายคนละ 1 กลุ่ม ตามกลุ่มเสี่ยงในหน้าลงทะเบียน
        (MSM/MSW/TG = ชายที่มีเพศสัมพันธ์กับชาย · พนักงานบริการ หญิง/ชาย · ผู้ใช้สารเสพติด · แรงงานข้ามชาติ · เยาวชน หรือคนอื่นที่อายุ 10–24 ปี) ·
        ผู้ป่วย = วินิจฉัยสถานะผู้ป่วยใหม่/เกิดโรคซ้ำ · 1.1 = สั่ง TP syphilis/RPR · 2 = วินิจฉัย Pre-test/Post-test + สั่ง Anti-HIV/ผลบวก ·
        3.1 และ 6.1 = ทุกคนที่มาคลินิก STI · 4.1 = ผู้ป่วยโรคหลัก + Pap Smear ผิดปกติ ติดตามได้ = มาครั้งถัดไปแล้ว หรือทะเบียนคู่สัมผัสลงว่า F/U แล้ว ·
        4.2 = คู่สัมผัสของผู้ป่วยโรคหลัก (เพศตามคู่สัมผัส กลุ่มตามผู้ป่วย) · 4.4 = คู่สัมผัสที่ลงวิธีติดตามไว้ (ยกเว้น "ไม่สามารถติดตามได้")
    </div>
</div>

<script>
(function () {
    var MONTH = @json($ym);
    var URL_SAVE = @json(route('report.sti-k1.save'));
    var TOKEN = @json(csrf_token());

    function save(el) {
        var v = el.value.trim();
        if (v === el.dataset.last) { return; }
        if (v !== '' && !/^\d{1,7}$/.test(v)) { el.classList.add('err'); el.title = 'ใส่ได้เฉพาะตัวเลข'; return; }
        // พิมพ์กลับเป็นค่าที่ระบบนับได้ = ไม่ต้องเก็บค่าแก้
        var send = (v === el.dataset.auto) ? '' : v;
        el.classList.remove('err'); el.classList.add('saving');
        var body = new URLSearchParams({ month: MONTH, key: el.dataset.key, col: el.dataset.col, value: send, _token: TOKEN });
        fetch(URL_SAVE, { method: 'POST', headers: { 'Accept': 'application/json' }, body: body })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok && j.ok, j: j }; }); })
            .then(function (res) {
                el.classList.remove('saving');
                if (!res.ok) { el.classList.add('err'); el.title = (res.j && res.j.msg) || 'บันทึกไม่สำเร็จ'; return; }
                if (send === '') { el.value = el.dataset.auto; el.classList.remove('ov'); }
                else { el.classList.add('ov'); }
                el.dataset.last = el.value;
            })
            .catch(function () { el.classList.remove('saving'); el.classList.add('err'); el.title = 'บันทึกไม่สำเร็จ — ลองอีกครั้ง'; });
    }
    document.querySelectorAll('.k1-cell').forEach(function (el) {
        el.addEventListener('change', function () { save(el); });
        el.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); el.blur(); } });
    });

    // ชื่อหน่วยงาน/ผู้รายงาน จำในเบราว์เซอร์เครื่องนี้
    document.querySelectorAll('.k1-edit').forEach(function (el) {
        var k = 'stiK1Sign.' + el.dataset.key;
        try { var v = localStorage.getItem(k); if (v) { el.textContent = v; } } catch (e) {}
        el.addEventListener('input', function () { try { localStorage.setItem(k, el.textContent.trim()); } catch (e) {} });
    });
})();
</script>
@endsection
