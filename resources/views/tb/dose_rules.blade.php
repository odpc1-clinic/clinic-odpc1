@extends('layouts.app')

{{-- หน้าแก้เกณฑ์ขนาดยา — สำหรับผู้ดูแลระบบ
     เกณฑ์มาจากไฟล์ Calculation_4TB ของคลินิก แต่แนวทางเปลี่ยนได้ จึงต้องแก้เองได้ไม่ต้องรอแก้โปรแกรม --}}

@section('content')
<div class="container container-wide">
    <div class="p-3 mb-3 shadow-sm" style="background:linear-gradient(135deg,#1d4ed8 0%,#3b82f6 100%);color:#fff;border-radius:.75rem;">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="opacity-75 small"><i class="fa-solid fa-calculator me-1"></i> คลินิกวัณโรค</div>
                <h4 class="mb-0 fw-bold">เกณฑ์ขนาดยาตามน้ำหนัก</h4>
            </div>
            <a href="{{ route('tb.index') }}" class="btn btn-light btn-sm"><i class="fa fa-arrow-left"></i> ทะเบียนวัณโรค</a>
        </div>
    </div>

    @if ($errors->any())
        <ul class="alert alert-danger">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    @endif

    {{-- เลือกชุดเกณฑ์ที่จะแก้ — แยกกันคนละชุด จะได้ไม่แก้ทับกัน --}}
    @php
        $__tabs = [
            ['DS', 'adult', 'ไม่ดื้อยา · ผู้ใหญ่'],
            ['DS', 'ped', 'ไม่ดื้อยา · เด็ก'],
            ['Resist', 'adult', 'ดื้อยา · ผู้ใหญ่'],
            ['TPT', 'adult', 'ยาป้องกัน · ผู้ใหญ่'],
            ['TPT', 'ped', 'ยาป้องกัน · เด็ก'],
        ];
    @endphp
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach($__tabs as [$g, $t, $label])
            @php $n = $counts->first(fn ($c) => $c->group === $g && $c->patient_type === $t); @endphp
            <a href="{{ route('tb.doseRules', ['group' => $g, 'type' => $t]) }}"
               class="btn btn-sm {{ $group === $g && $type === $t ? 'btn-primary' : 'btn-outline-primary' }}">
                {{ $label }} <span class="badge bg-light text-dark">{{ $n->n ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <form method="POST" action="{{ route('tb.doseRules.save') }}" autocomplete="off">
        @csrf
        <input type="hidden" name="group" value="{{ $group }}">
        <input type="hidden" name="type" value="{{ $type }}">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="alert alert-light border small">
                    <b>วิธีอ่านตาราง</b> — หนึ่งแถวคือ "ยา 1 ตัว ในช่วงน้ำหนัก 1 ช่วง"
                    ระบบจะเลือกแถวที่น้ำหนักคนไข้ตรงกับช่วง แล้วใช้ "ขนาดมาตรฐาน" ของแถวนั้น ·
                    ถ้าน้ำหนักไม่เข้าช่วงไหนเลย (เช่นต่ำกว่า 35 กก.) จะคิดจาก <b>มก./กก.</b> แทน ·
                    ช่องช่วงน้ำหนักเว้นว่าง = ไม่จำกัดด้านนั้น ·
                    <b>เพดาน/วัน</b> ใช้กับเกณฑ์ที่คิดตาม มก./กก. (เด็ก/TPT) คำนวณได้เท่าไหร่ก็ไม่เกินค่านี้ ·
                    <b>ขนาดแบบข้อความ</b> ใช้กับยาที่ขนาดไม่ใช่ตัวเลขเดียว เช่น Bedaquiline ที่เปลี่ยนขนาดตามสัปดาห์ (ใส่แล้วระบบจะไม่คำนวณตัวเลขให้)
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:5rem">ยา</th>
                                <th style="width:12rem">ชื่อเต็ม</th>
                                <th style="width:8rem">ขนาดเม็ดที่มี</th>
                                <th style="width:6rem">น้ำหนักตั้งแต่</th>
                                <th style="width:6rem">ถึง</th>
                                <th style="width:7rem">ขนาดมาตรฐาน (มก.)</th>
                                <th style="width:6rem">เพดาน/วัน</th>
                                <th style="width:6rem">มก./กก. ต่ำสุด</th>
                                <th style="width:6rem">สูงสุด</th>
                                <th style="width:12rem">ขนาดแบบข้อความ</th>
                                <th>หมายเหตุ</th>
                                <th style="width:4rem">ใช้งาน</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($rules as $i => $r)
                            <tr>
                                <td>
                                    <input type="hidden" name="rules[{{ $i }}][id]" value="{{ $r->id }}">
                                    <input type="text" name="rules[{{ $i }}][drug_key]" class="form-control form-control-sm" value="{{ $r->drug_key }}" required>
                                </td>
                                <td><input type="text" name="rules[{{ $i }}][drug_label]" class="form-control form-control-sm" value="{{ $r->drug_label }}" required></td>
                                <td><input type="text" name="rules[{{ $i }}][strength]" class="form-control form-control-sm" value="{{ $r->strength }}"></td>
                                <td><input type="number" step="0.1" name="rules[{{ $i }}][weight_min]" class="form-control form-control-sm" value="{{ $r->weight_min !== null ? (float) $r->weight_min : '' }}"></td>
                                <td><input type="number" step="0.1" name="rules[{{ $i }}][weight_max]" class="form-control form-control-sm" value="{{ $r->weight_max !== null ? (float) $r->weight_max : '' }}"></td>
                                <td><input type="number" name="rules[{{ $i }}][dose_mg]" class="form-control form-control-sm" value="{{ $r->dose_mg }}"></td>
                                <td><input type="number" name="rules[{{ $i }}][max_mg]" class="form-control form-control-sm" value="{{ $r->max_mg }}"></td>
                                <td><input type="number" step="0.1" name="rules[{{ $i }}][mgkg_min]" class="form-control form-control-sm" value="{{ $r->mgkg_min !== null ? (float) $r->mgkg_min : '' }}"></td>
                                <td><input type="number" step="0.1" name="rules[{{ $i }}][mgkg_max]" class="form-control form-control-sm" value="{{ $r->mgkg_max !== null ? (float) $r->mgkg_max : '' }}"></td>
                                <td><input type="text" name="rules[{{ $i }}][dose_text]" class="form-control form-control-sm" value="{{ $r->dose_text }}" placeholder="เช่น 400 มก. 2 สัปดาห์แรก"></td>
                                <td><input type="text" name="rules[{{ $i }}][note]" class="form-control form-control-sm" value="{{ $r->note }}"></td>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input" name="rules[{{ $i }}][active]" value="1" {{ $r->active ? 'checked' : '' }}>
                                </td>
                            </tr>
                        @endforeach

                        {{-- แถวว่างไว้เพิ่มยา/ช่วงน้ำหนักใหม่ --}}
                        @for($j = 0; $j < 2; $j++)
                            @php $k = $rules->count() + $j; @endphp
                            <tr>
                                <td>
                                    <input type="hidden" name="rules[{{ $k }}][id]" value="">
                                    <input type="text" name="rules[{{ $k }}][drug_key]" class="form-control form-control-sm" placeholder="เช่น Lzd">
                                </td>
                                <td><input type="text" name="rules[{{ $k }}][drug_label]" class="form-control form-control-sm" placeholder="ชื่อเต็ม"></td>
                                <td><input type="text" name="rules[{{ $k }}][strength]" class="form-control form-control-sm"></td>
                                <td><input type="number" step="0.1" name="rules[{{ $k }}][weight_min]" class="form-control form-control-sm"></td>
                                <td><input type="number" step="0.1" name="rules[{{ $k }}][weight_max]" class="form-control form-control-sm"></td>
                                <td><input type="number" name="rules[{{ $k }}][dose_mg]" class="form-control form-control-sm"></td>
                                <td><input type="number" name="rules[{{ $k }}][max_mg]" class="form-control form-control-sm"></td>
                                <td><input type="number" step="0.1" name="rules[{{ $k }}][mgkg_min]" class="form-control form-control-sm"></td>
                                <td><input type="number" step="0.1" name="rules[{{ $k }}][mgkg_max]" class="form-control form-control-sm"></td>
                                <td><input type="text" name="rules[{{ $k }}][dose_text]" class="form-control form-control-sm"></td>
                                <td><input type="text" name="rules[{{ $k }}][note]" class="form-control form-control-sm"></td>
                                <td class="text-center"><input type="checkbox" class="form-check-input" name="rules[{{ $k }}][active]" value="1" checked></td>
                            </tr>
                        @endfor
                        </tbody>
                    </table>
                </div>
                <div class="small text-muted">ล้างชื่อยาจนว่าง = ลบเกณฑ์แถวนั้นออก</div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <button class="btn btn-primary"><i class="fa fa-save"></i> บันทึกเกณฑ์</button>

                {{-- ลองคำนวณดูทันทีว่าเกณฑ์ที่แก้ให้ผลอย่างไร --}}
                <div class="d-flex align-items-center gap-2">
                    <span class="small text-muted">ลองคำนวณ</span>
                    <input type="number" step="0.1" id="try-weight" class="form-control form-control-sm" style="width:7rem;" placeholder="น้ำหนัก" value="55">
                    <span class="small" id="try-result">—</span>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
// ลองคำนวณจากเกณฑ์ "ที่กรอกอยู่ในหน้านี้" (ยังไม่บันทึกก็ลองได้)
(function () {
    var input = document.getElementById('try-weight');
    var out = document.getElementById('try-result');
    if (!input) return;

    function calc() {
        var w = parseFloat(input.value);
        if (!w || w <= 0) { out.textContent = '—'; return; }

        var byDrug = {};
        document.querySelectorAll('#try-weight').length;
        document.querySelectorAll('input[name$="[drug_key]"]').forEach(function (keyEl) {
            var row = keyEl.closest('tr');
            var key = keyEl.value.trim();
            if (!key) return;
            var val = function (n) { var el = row.querySelector('[name$="[' + n + ']"]'); return el ? el.value.trim() : ''; };
            if (!row.querySelector('[name$="[active]"]').checked) return;

            var min = val('weight_min') === '' ? null : parseFloat(val('weight_min'));
            var max = val('weight_max') === '' ? null : parseFloat(val('weight_max'));
            var dose = val('dose_mg') === '' ? null : parseInt(val('dose_mg'), 10);
            var mgkg = val('mgkg_min') === '' ? null : parseFloat(val('mgkg_min'));

            if (byDrug[key] !== undefined) return;   // เจอแถวแรกที่ตรงแล้ว
            var inBand = (min === null || w >= min) && (max === null || w <= max);
            var cap  = val('max_mg') === '' ? null : parseInt(val('max_mg'), 10);
            var text = val('dose_text');

            if (text) { byDrug[key] = text; return; }

            var d = null;
            if (inBand && dose) { d = dose; }
            else if (mgkg) { d = Math.round(w * mgkg / 10) * 10; }
            if (d === null) return;
            if (cap && d > cap) { byDrug[key] = cap + ' มก. (เพดาน)'; return; }
            byDrug[key] = d + ' มก.';
        });

        var parts = Object.keys(byDrug).map(function (k) { return k + ' ' + byDrug[k]; });
        out.textContent = parts.length ? parts.join(' · ') : 'ไม่มีเกณฑ์ที่ตรง';
    }
    input.addEventListener('input', calc);
    document.addEventListener('input', function (e) { if (e.target.closest('table')) calc(); });
    calc();
})();
</script>
@endpush
