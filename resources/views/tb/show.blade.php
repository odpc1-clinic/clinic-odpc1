@extends('layouts.app')

{{-- บัตร TB 01 ของคนไข้หนึ่งราย
     หน้านี้มี 2 ฟอร์มแยกกัน (ข้อมูลบัตร / ตารางผลเสมหะ) วางเป็นพี่น้องกัน ไม่ซ้อนกัน --}}

@section('content')
@php
    $pt = $reg->patient;
    $required = $reg->requiredSputumMonths();
    // บัตรวัณโรคแฝง: หัวบัตรสีม่วง + ไม่มีตารางเสมหะ/DST (LTBI ไม่ตรวจเสมหะ — คลินิกขอ 2569-10-27)
    $__ltbi = $reg->isLtbi();
@endphp

<div class="container container-wide">
    <div class="p-3 mb-3 shadow-sm" style="background:{{ $__ltbi ? 'linear-gradient(135deg,#6d28d9 0%,#a78bfa 100%)' : 'linear-gradient(135deg,#b45309 0%,#f59e0b 100%)' }};color:#fff;border-radius:.75rem;">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                @if($__ltbi)
                    <div class="opacity-75 small"><i class="fa-solid fa-shield-virus me-1"></i> บัตรวัณโรคแฝง (LTBI) · ยาป้องกัน TPT
                        @if(\App\Models\TbRegistration::ltbiReady() && $reg->tpt_target) · {{ \App\Models\TbRegistration::$tptTargetOption[$reg->tpt_target] ?? '' }}@endif
                    </div>
                @else
                <div class="opacity-75 small"><i class="fa-solid fa-lungs me-1"></i> บัตร TB 01 · {{ $reg->typeName() }}</div>
                @endif
                <h4 class="mb-0 fw-bold">{{ $pt->name ?? '-' }} {{ $pt->surname ?? '' }}</h4>
                <div class="small opacity-90 mt-1">
                    <span class="me-3">HN {{ $pt->code ?? '-' }}</span>
                    <span class="me-3">เลขทะเบียน {{ $reg->tb_no ?: '—' }}</span>
                    {{-- ผู้สัมผัส → ลิงก์บัตรผู้ป่วยต้นเหตุ (2569-10-22) --}}
                    @if(\App\Services\TbContacts::ready() && $reg->index_registration_id && ($__hIdx = \App\Models\TbRegistration::with('patient')->find($reg->index_registration_id)))
                        <a href="{{ route('tb.show', $__hIdx->id) }}" class="me-3 text-white fw-semibold" style="text-decoration:underline;">
                            <i class="fa fa-people-arrows me-1"></i>ผู้สัมผัสของ {{ optional($__hIdx->patient)->name }} {{ optional($__hIdx->patient)->surname }}</a>
                    @endif
                    @if($reg->treatment_start_date)
                        @php $__cs = $reg->countStartDate(); @endphp
                        <span class="me-3">เริ่มรักษา {{ \App\Utillity::db2thDate($reg->treatment_start_date) }}
                            @if($__cs && $__cs !== $reg->treatment_start_date)
                                · เริ่มสูตร {{ $reg->currentRegimenText() ?: $reg->regimen }} {{ \App\Utillity::db2thDate($__cs) }}
                            @endif
                            (เดือนที่ {{ $reg->monthsOnTreatment() }})</span>
                    @endif
                </div>
                {{-- ช่วงนี้ต้องตรวจอะไร ตามตารางติดตามการรักษา (2569-10-05) --}}
                @include('tb._monitor_header', ['reg' => $reg])
            </div>
            <div class="text-end">
                {{-- กลับหน้าตรวจ (พยาบาลขอ 2569-09-30)
                     เปิดมาจากหน้าตรวจ (แท็บใหม่) = ปิดแท็บบัตรแล้วกลับไปแท็บหน้าตรวจเดิม ไม่เปิดหน้าตรวจซ้อนอีกแท็บ
                     ที่ไม่ได้มาจากหน้าตรวจ = ลิงก์ไปหน้าตรวจของวันนี้ ถ้าคนไข้มา visit วันนี้ --}}
                @php
                    $__backVisit = request('from') === 'visit' && request('visit')
                        ? \App\Models\Visit::where('patient_id', $reg->patient_id)->find((int) request('visit'))
                        : \App\Models\Visit::where('patient_id', $reg->patient_id)
                            ->where('date', \App\Models\VisitAppointment::todayBe())->orderByDesc('id')->first();
                @endphp
                @if($__backVisit)
                    <a href="{{ route('visit.edit', ['visit' => $__backVisit->id, 'page' => request('page', 1)]) }}"
                       class="btn btn-warning btn-sm fw-bold js-back-visit" data-opener="{{ request('from') === 'visit' ? 1 : 0 }}">
                        <i class="fa fa-arrow-left"></i> {{ request('from') === 'visit' ? 'กลับหน้าตรวจ' : 'ไปหน้าตรวจวันนี้' }}
                    </a>
                    <script>
                    document.addEventListener('click', function (e) {
                        var a = e.target.closest && e.target.closest('.js-back-visit');
                        if (!a || a.dataset.opener !== '1') return;
                        // แท็บหน้าตรวจเดิมยังเปิดอยู่ -> กลับไปที่แท็บนั้นแล้วปิดแท็บบัตร (ข้อมูลที่กรอกค้างในหน้าตรวจไม่หาย)
                        try {
                            if (window.opener && !window.opener.closed && window.opener.location.host === location.host) {
                                e.preventDefault();
                                window.opener.focus();
                                window.close();
                                // เบราว์เซอร์ไม่ยอมปิดแท็บที่เปลี่ยนหน้ามาแล้ว (เช่นกดบันทึกในบัตร) -> ไปหน้าตรวจในแท็บนี้แทน
                                setTimeout(function () { location.href = a.href; }, 300);
                            }
                        } catch (err) { /* อ้างถึงแท็บเดิมไม่ได้ -> ปล่อยให้ลิงก์ทำงานตามปกติ */ }
                    });
                    </script>
                @endif
                {{-- เปิดมาจากหน้าติดตาม DOT — ปุ่มกลับไปวันเดิม (พยาบาลขอ 2569-09-30) --}}
                @if(request('from') === 'dot')
                    <a href="{{ route('tb.dotBoard', array_filter(['date' => request('date'), 'view' => request('view')])) }}"
                       class="btn btn-warning btn-sm fw-bold"><i class="fa fa-arrow-left"></i> กลับหน้าติดตาม DOT</a>
                @endif
                <a href="{{ route('tb.index') }}" class="btn btn-light btn-sm"><i class="fa fa-arrow-left"></i> ทะเบียน</a>
                @if($pt)
                    <a href="{{ route('patient.edit', $pt->id) }}" class="btn btn-outline-light btn-sm">หน้าคนไข้</a>
                @endif
                <div class="mt-1">
                    @if($reg->isActive())
                        <span class="badge bg-success">กำลังรักษา</span>
                    @else
                        <span class="badge bg-dark">{{ $reg->outcomeName() }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <ul class="alert alert-danger">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif

    {{-- บัตรที่จบการรักษาแล้ว (หาย/ครบ) — รอบนัดติดตามหลังจบ อยู่นอกแท็บ เห็นทันทีที่เปิดบัตร (2569-10-27) --}}
    @unless($reg->isActive())
        @include('tb._post_followup', ['reg' => $reg])
    @endunless

    {{-- แท็บ ข้อมูลบัตร · ผลตรวจ · ยา · DOT — สคริปต์ซ่อน/แสดงกล่องตาม id (2569-10-01)
         ต้องอยู่ก่อนกล่องทั้งหมดในหน้า แต่สคริปต์รอให้หน้าโหลดครบก่อน (ดูท้าย _tabs) --}}
    @include('tb._tabs')

    {{-- สั่ง Lab ให้ visit TB วันนี้ได้จากบัตรเลย (เคส F/U ไม่ต้องกลับไปหน้าตรวจ 2569-10-02) — กล่องแรกของแท็บผลตรวจ --}}
    @include('tb._lab_order')

    {{-- ---------- ตารางผลตรวจเสมหะ (ส่วนที่ใช้บ่อยที่สุด จึงอยู่บนสุด) · บัตร LTBI ไม่มี ---------- --}}
    @unless($__ltbi)
    <form method="POST" action="{{ route('tb.sputum', $reg->id) }}" autocomplete="off" class="mb-3" id="tb-sputum">
        @csrf
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <h6 class="fw-bold mb-0"><i class="fa fa-vial me-1"></i> ผลตรวจเสมหะรายเดือน</h6>
                    {{-- Sputum conversion (สูตรไวต่อยา) — คลินิกขอ 2569-10-28 --}}
                    @if($__conv = $reg->sputumConversion())
                        <span class="badge fw-bold" style="background:{{ $__conv['bg'] }};color:{{ $__conv['color'] }};border:1px solid {{ $__conv['color'] }};font-size:.9rem;">
                            Sputum conversion: {{ $__conv['text'] }}
                        </span>
                    @endif
                    <div class="small text-muted">
                        @if($required)
                            เดือนที่ต้องตรวจตามสูตร: <b>{{ implode(', ', $required) }}</b> (แถวสีเหลือง)
                        @else
                            เลือก "ตารางตรวจเสมหะ" ด้านล่างเพื่อให้ระบบไฮไลต์เดือนที่ต้องตรวจ
                        @endif
                    </div>
                </div>

                <div class="table-responsive">
                    {{-- หนึ่งแถว = การตรวจหนึ่งครั้ง (ไม่ใช่หนึ่งเดือน)
                         แถวมาตรฐานคือ 0 · 2wk · 1 · 2 ... ถ้าตรวจเพิ่มระหว่างเดือนให้กด "เพิ่มช่อง"
                         แล้วพิมพ์ป้ายกำกับเอง เช่น 6wk หรือ 2 (ซ้ำ) — คลินิกขอ 2569-09-29 --}}
                    @php
                        // แถวที่บันทึกไว้แล้ว + แถวมาตรฐานที่ยังไม่มี เรียงตาม sort/เดือน
                        $__saved = $reg->sputumExams->sortBy([['sort', 'asc'], ['month_no', 'asc'], ['id', 'asc']]);
                        // เทียบป้ายแบบไม่สนช่องว่าง/ตัวพิมพ์ — "2 wk" ที่พิมพ์เองคือแถว 2wk เดียวกัน ไม่งั้นได้แถวว่างซ้ำ (คลินิกแจ้ง 2569-10-26)
                        $__norm  = fn ($s) => strtolower(preg_replace('/\s+/', '', (string) $s));
                        // ช่วงนับรอบเดียวกับใบสรุปการรักษา (คลินิกขอ 2569-10-27 "AFB ต้องตรงกับยา") — แถวอยู่ช่วงไหนดูจากวันที่เก็บ
                        // เดือนของเสมหะนับจากต้นช่วงนั้น · แถวมาตรฐานที่ยังว่างเติมให้เฉพาะช่วงปัจจุบัน
                        $__sSegs = $reg->doseSegments();
                        $__sCur  = count($__sSegs) - 1;
                        $__segOf = fn ($r) => $r ? $reg->segmentIndexFor($r->collected_date, $__sSegs) : $__sCur;
                        $__have  = $__saved->filter(fn ($r) => $__segOf($r) === $__sCur)->map(fn ($r) => $__norm($r->label ?: $r->month_no))->all();

                        $__standard = ['0', '2wk'];
                        for ($m = 1; $m <= $maxMonth; $m++) { $__standard[] = (string) $m; }

                        $__rows = [];
                        foreach ($__saved as $r) { $__rows[] = ['label' => (string) ($r->label ?: $r->month_no), 'row' => $r]; }
                        foreach ($__standard as $label) {
                            if (! in_array($__norm($label), $__have, true)) { $__rows[] = ['label' => $label, 'row' => null]; }
                        }
                        // เรียงให้ 0 · 2wk · 1 · 2 ... อยู่ตามลำดับเวลา
                        foreach ($__rows as &$__it) { $__it['seg'] = $__segOf($__it['row']); }
                        unset($__it);
                        usort($__rows, function ($a, $b) {
                            if ($a['seg'] !== $b['seg']) { return $a['seg'] <=> $b['seg']; }
                            $key = function ($label) {
                                if (trim($label) === 'ก่อนเริ่ม') { return -1; }   // ผลก่อนวันเริ่มรักษารอบนี้ (2569-10-12) ขึ้นบนสุด
                                if (preg_match('/^(\d+(?:\.\d+)?)\s*wk$/i', $label, $m)) { return ((float) $m[1]) / 4.345; }
                                return preg_match('/^\d+$/', $label) ? (float) $label : 99;
                            };
                            // รอบเดียวกัน (เช่น "ก่อนเริ่ม" หลายแถว) เรียงตามวันที่เก็บ — แถวว่างไปท้ายรอบ
                            return ($key($a['label']) <=> $key($b['label']))
                                ?: (((string) optional($a['row'])->collected_date ?: '9999') <=> ((string) optional($b['row'])->collected_date ?: '9999'));
                        });
                    @endphp

                    <table class="table table-sm table-bordered align-middle mb-2" id="tb-sputum-table" data-start="{{ $reg->countStartDate() }}" data-segs='@json(array_values(array_filter(array_column($__sSegs, 'start'))))'>
                        <thead class="table-light">
                            <tr>
                                <th style="width:5rem">เดือน/รอบ</th>
                                <th style="width:9rem">วันที่เก็บ</th>
                                <th style="width:6.5rem">Spot</th>
                                {{-- Collect 2 ครั้ง (เสมหะตอนเช้า) — คลินิกขอ 2569-10-02 · ช่องที่ 2 โผล่หลังรัน migration collect2 --}}
                                @php $__c2 = \App\Models\TbSputumExam::hasCollect2(); @endphp
                                <th style="width:6.5rem">{{ $__c2 ? 'Collect 1' : 'Collect' }}</th>
                                @if($__c2)<th style="width:6.5rem">Collect 2</th>@endif
                                <th style="width:9rem">Molecular</th>
                                <th style="width:6.5rem">Culture</th>
                                <th style="width:8rem">Lab Serial No.</th>
                                {{-- เลขที่ตลับ / ครั้งที่ เอาออกจากตาราง (คลินิกขอ 2569-10-02) ค่าเดิมในฐานยังอยู่
                                     ฟอร์มไม่ส่งสองช่องนี้แล้ว saveSputum จึงไม่แตะ --}}
                                <th style="width:9rem">ลักษณะเสมหะ</th>
                                <th style="width:9rem">สิ่งส่งตรวจ</th>
                                <th>หมายเหตุ</th>
                                <th style="width:2.5rem"></th>
                            </tr>
                        </thead>
                        <tbody>
                        @php $__sShown = -1; $__afbShort = ['N' => 'Neg', 'scanty' => 'Scanty', '1+' => '1+', '2+' => '2+', '3+' => '3+', 'N/A' => 'N/A (ไม่ได้ส่ง)']; @endphp
                        @foreach($__rows as $i => $item)
                            @if(count($__sSegs) > 1 && $item['seg'] !== $__sShown)
                                @php $__sShown = $item['seg']; $__ssg = $__sSegs[$__sShown] ?? []; @endphp
                                <tr class="js-sp-seg">
                                    <td colspan="{{ 11 + ($__c2 ? 1 : 0) }}" class="small fw-bold" style="background:#fff7ed; color:#9a3412;">
                                        <i class="fa fa-layer-group me-1"></i> ช่วงที่ {{ $__sShown + 1 }} · {{ implode(' → ', array_unique($__ssg['regimens'] ?? [$__ssg['regimen'] ?? ''])) }}
                                        <span class="fw-normal text-muted">({{ \App\Utillity::db2thDate($__ssg['start'] ?? null) }}{{ ! empty($__ssg['end']) ? ' – ' . \App\Utillity::db2thDate($__ssg['end']) : ' – ปัจจุบัน' }}) · เดือนนับจากต้นช่วง</span>
                                    </td>
                                </tr>
                            @endif
                            @php
                                $row    = $item['row'];
                                $label  = $item['label'];
                                $isReq  = preg_match('/^\d+$/', $label) && in_array((int) $label, $required, true);
                            @endphp
                            <tr @if($isReq) style="background:#fff8e1;" @endif>
                                <td>
                                    <input type="hidden" name="sputum[{{ $i }}][id]" value="{{ $row->id ?? '' }}">
                                    {{-- ปุ่มลบแถว (คลินิกขอ 2569-10-09) ตั้งค่านี้เป็น 1 → saveSputum ลบแถวนี้ตอนกดบันทึก --}}
                                    <input type="hidden" name="sputum[{{ $i }}][_delete]" value="" class="js-sp-del-flag">
                                    {{-- กด + ท้ายแถวแล้วแถวใหม่ใช้เดือนเดิม ไม่ต้องพิมพ์ซ้ำ (คลินิกขอ 2569-09-29) --}}
                                    <input type="text" name="sputum[{{ $i }}][label]" value="{{ $label }}" data-keep-on-insert="1" data-no-highlight="1"
                                           class="form-control form-control-sm text-center fw-bold" style="padding:.15rem .25rem;">
                                    @if($isReq)<div class="small text-muted text-center">ต้องตรวจ</div>@endif
                                </td>
                                <td><input type="text" name="sputum[{{ $i }}][collected_date]" class="form-control form-control-sm tb-date"
                                           value="{{ $row && $row->collected_date ? \App\Utillity::db2thDate($row->collected_date) : '' }}" placeholder="วว/ดด/ปปปป"></td>
                                {{-- AFB เป็นตัวเลือก (คลินิกขอ 2569-10-27): Neg · Scanty · 1+ · 2+ · 3+ · N/A (ไม่ได้ส่ง)
                                     ค่าเก่าที่พิมพ์มือแบบอื่น (เช่น Neg,Neg) โชว์เป็นตัวเลือก "(ค่าเดิม)" ไม่หาย --}}
                                @foreach(['spot', 'collect'] + ($__c2 ? [2 => 'collect2'] : []) as $__col)
                                    @php
                                        $__cur = trim((string) ($row->$__col ?? ''));
                                        if (preg_match('/^neg(ative)?$/i', $__cur)) { $__cur = 'N'; }
                                        elseif (strcasecmp($__cur, 'scanty') === 0) { $__cur = 'scanty'; }
                                        elseif (\App\Models\TbScreening::afbNotSent($__cur)) { $__cur = 'N/A'; }
                                    @endphp
                                    <td>
                                        <select name="sputum[{{ $i }}][{{ $__col }}]" class="form-select form-select-sm">
                                            <option value=""></option>
                                            @if($__cur !== '' && ! isset($__afbShort[$__cur]))
                                                <option value="{{ $__cur }}" selected data-legacy>{{ $__cur }} (ค่าเดิม)</option>
                                            @endif
                                            @foreach($__afbShort as $__k => $__l)
                                                <option value="{{ $__k }}" {{ $__cur === $__k ? 'selected' : '' }}>{{ $__l }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endforeach
                                <td><input type="text" name="sputum[{{ $i }}][molecular]" class="form-control form-control-sm" list="tb-molecular-list"
                                           value="{{ $row->molecular ?? '' }}" placeholder="Xpert: MTB not detected"></td>
                                <td>
                                    {{-- N/A = ค่าว่าง (ยังไม่ส่งเพาะเชื้อ) · ค่าเก่าที่ไม่ตรงตัวเลือกโผล่เป็นตัวเลือกเพิ่ม จะได้ไม่หายตอนเซฟ
                                         data-legacy ให้ปุ่มเพิ่มแถวลบทิ้งในแถวใหม่ --}}
                                    @php $__cul = trim((string) ($row->culture ?? '')); @endphp
                                    <select name="sputum[{{ $i }}][culture]" class="form-select form-select-sm">
                                        <option value="">N/A</option>
                                        @if($__cul !== '' && ! in_array($__cul, \App\Models\TbSputumExam::$cultureOption, true))
                                            <option value="{{ $__cul }}" selected data-legacy="1">{{ $__cul }}</option>
                                        @endif
                                        @foreach(\App\Models\TbSputumExam::$cultureOption as $cOpt)
                                            <option value="{{ $cOpt }}" {{ $__cul === $cOpt ? 'selected' : '' }}>{{ $cOpt }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" name="sputum[{{ $i }}][lab_serial]" class="form-control form-control-sm" value="{{ $row->lab_serial ?? '' }}"></td>
                                <td>
                                    <select name="sputum[{{ $i }}][appearance]" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        @foreach(\App\Models\TbSputumExam::$appearanceOption as $ak => $aLabel)
                                            <option value="{{ $ak }}" {{ (int) ($row->appearance ?? 0) === $ak ? 'selected' : '' }}>{{ $aLabel }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" name="sputum[{{ $i }}][specimen]" class="form-control form-control-sm" list="tb-specimen-list"
                                           value="{{ $row->specimen ?? '' }}" placeholder="เสมหะ"></td>
                                <td><input type="text" name="sputum[{{ $i }}][note]" class="form-control form-control-sm" value="{{ $row->note ?? '' }}"></td>
                                {{-- แทรกแถวต่อจากแถวนี้ (พยาบาลขอให้อยู่ติดกัน ไม่ต้องไปโผล่ล่างสุด 2569-09-29) --}}
                                <td class="text-center text-nowrap">
                                    <button type="button" class="btn btn-outline-primary btn-sm js-tb-insert-row" title="แทรกแถวต่อจากแถวนี้">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm js-sp-del" title="ลบแถวนี้ (ลบจริงเมื่อกดบันทึก)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <script>
                    // เดือน/รอบ คิดจากวันที่เก็บเทียบวันเริ่มรักษาของบัตร (คลินิกขอ 2569-10-12 — เดิมปุ่ม + คัดลอกเลขเดือนแถวบน ทุกแถวเลยเป็น 0)
                    // ก่อนวันเริ่ม = "ก่อนเริ่ม" · 0-7 วัน = 0 · 8-21 วัน = 2wk · ต่อจากนั้น = จำนวนเดือน (ปัดเศษ)
                    // พิมพ์เดือนเองแล้ว = ไม่ทับ (ติดธง manual ตอนพิมพ์ในช่องเดือน)
                    (function () {
                        if (window.__spAutoLabel) return;
                        window.__spAutoLabel = true;
                        function be2date(s) {   // พ.ศ. Y-m-d หรือ วว/ดด/ปปปป → Date (ค.ศ.)
                            var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || '');
                            if (m) return new Date(+m[1] - (+m[1] > 2400 ? 543 : 0), +m[2] - 1, +m[3]);
                            m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec((s || '').trim());
                            if (m) return new Date(+m[3] - (+m[3] > 2400 ? 543 : 0), +m[2] - 1, +m[1]);
                            return null;
                        }
                        function labelFor(start, d) {
                            var days = Math.round((d - start) / 86400000);
                            if (days < 0) return 'ก่อนเริ่ม';
                            if (days <= 7) return '0';
                            if (days <= 21) return '2wk';
                            return String(Math.max(1, Math.round(days / 30.44)));
                        }
                        document.addEventListener('input', function (e) {
                            if (e.isTrusted && e.target.matches && e.target.matches('#tb-sputum-table input[name$="[label]"]')) { e.target.dataset.manual = '1'; }
                        });
                        function onDate(e) {
                            var el = e.target;
                            if (!el.matches || !el.matches('#tb-sputum-table input[name$="[collected_date]"]')) return;
                            var table = document.getElementById('tb-sputum-table'), start = be2date(table.dataset.start), d = be2date(el.value);
                            // หลายช่วงสูตร: นับเดือนจากต้นช่วงที่วันเก็บอยู่ (ตรงกับใบสรุปการรักษา 2569-10-27)
                            try { JSON.parse(table.dataset.segs || '[]').forEach(function (s) { var sd = be2date(s); if (sd && d && sd <= d) start = sd; }); } catch (e) {}
                            var lab = el.closest('tr').querySelector('input[name$="[label]"]');
                            if (!start || !d || !lab || lab.dataset.manual === '1') return;
                            lab.value = labelFor(start, d);
                        }
                        document.addEventListener('change', onDate);
                        document.addEventListener('focusout', onDate);
                    })();
                    // ลบแถวผลเสมหะ — เดิมต้องล้างทุกช่องให้ว่างถึงจะลบ ซึ่งไม่มีใครรู้ (คลินิกขอปุ่มลบ 2569-10-09)
                    // แถวที่บันทึกแล้ว: ทำเครื่องหมาย "จะลบ" แล้วลบจริงตอนกดบันทึกตาราง (กดซ้ำ = ยกเลิก) · แถวใหม่ที่ยังไม่บันทึก: เอาออกเลย
                    // หน้านี้โหลดเต็ม สคริปต์รันได้ · ผูกที่ document เพราะแถวแทรกใหม่ถูกสร้างทีหลัง
                    (function () {
                        if (window.__spDelInit) return;
                        window.__spDelInit = true;
                        document.addEventListener('click', function (e) {
                            var b = e.target.closest && e.target.closest('.js-sp-del');
                            if (!b) return;
                            var tr = b.closest('tr'), id = tr.querySelector('input[name$="[id]"]'), flag = tr.querySelector('.js-sp-del-flag');
                            if (!id || !id.value) { tr.remove(); return; }
                            var on = flag.value !== '1';
                            if (on && !confirm('ลบผลเสมหะแถวนี้? (จะลบจริงเมื่อกด "บันทึก" ของตาราง)')) return;
                            flag.value = on ? '1' : '';
                            tr.style.opacity = on ? '.45' : '';
                            tr.style.textDecoration = on ? 'line-through' : '';
                            tr.title = on ? 'จะถูกลบเมื่อกดบันทึก — กดปุ่มถังขยะอีกครั้งเพื่อยกเลิก' : '';
                            b.classList.toggle('btn-danger', on); b.classList.toggle('btn-outline-danger', !on);
                        });
                    })();
                    </script>

                    <button type="button" class="btn btn-outline-primary btn-sm js-tb-add-row" data-table="tb-sputum-table">
                        <i class="fa fa-plus"></i> เพิ่มช่อง
                    </button>
                    <span class="small text-muted ms-2">แถวที่เพิ่มเอง พิมพ์ป้ายกำกับได้ เช่น 6wk หรือ 2 (ซ้ำ)</span>

                    <datalist id="tb-specimen-list">
                        @foreach(\App\Models\TbSputumExam::$specimenOption as $s)<option value="{{ $s }}">@endforeach
                    </datalist>
                    <datalist id="tb-molecular-list">
                        @foreach(\App\Models\TbSputumExam::$molecularOption as $mOpt)<option value="{{ $mOpt }}">@endforeach
                    </datalist>
                </div>

                <div class="small text-muted">
                    ช่องที่ล้างจนว่างทั้งแถว = ลบผลของเดือนนั้นออก · ตารางยืดตามระยะเวลาที่รักษาจริง (สูงสุดเดือนที่ 31)
                </div>
            </div>
            <div class="card-footer bg-white">
                <button class="btn btn-primary"><i class="fa fa-save"></i> บันทึกผลเสมหะ</button>
            </div>
        </div>
    </form>

    {{-- ผลทดสอบความไวต่อยา (DST) วางใต้ผลเสมหะรายเดือน อ่านต่อกันได้ (คลินิกขอ 2569-09-29) --}}
    @include('tb._dst')
    @endunless

    {{-- ประวัติ Lab ทั้งในคลินิกและส่งตรวจนอก ต่อจากผลเสมหะ/DST (คลินิกขอ 2569-09-30) --}}
    @include('tb._lab_history')

    {{-- ไฟล์ผลตรวจจากที่อื่น (สแกน/PDF) — มีฟอร์มอัปโหลดของตัวเอง ต้องอยู่นอกฟอร์มอื่น จึงวางตรงนี้ระหว่างฟอร์ม (2569-10-05) --}}
    @include('tb._lab_files')

    {{-- ---------- ข้อมูลบัตร ---------- --}}
    <form method="POST" action="{{ route('tb.update', $reg->id) }}" autocomplete="off" id="tb-info">
        @csrf
        @method('PUT')
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fa fa-id-card me-1"></i> {{ $__ltbi ? 'ข้อมูลบัตรวัณโรคแฝง / ยาป้องกัน' : 'ข้อมูลทะเบียน / การรักษา' }}</h6>
                @include('tb._form', ['reg' => $reg])
            </div>
            <div class="card-footer bg-white d-flex justify-content-between">
                <button class="btn btn-primary"><i class="fa fa-save"></i> บันทึกข้อมูลบัตร</button>

                @hasrole('Admin')
                    @if(! $reg->sputumExams()->exists() && ! $reg->outcome)
                        {{-- ปุ่มลบต้องไม่อยู่ในฟอร์มนี้ (ห้ามฟอร์มซ้อน) — ให้ JS สร้างฟอร์มต่อกับ body --}}
                        <button type="button" class="btn btn-outline-danger js-tb-delete"
                                data-url="{{ route('tb.destroy', $reg->id) }}">
                            <i class="fa fa-trash"></i> ลบบัตรนี้
                        </button>
                    @endif
                @endhasrole
            </div>
        </div>
    </form>

    {{-- ผู้สัมผัส ↔ ผู้ป่วยต้นเหตุ (2569-10-22) — แท็บข้อมูลบัตร ต่อจากฟอร์ม (อยู่นอกฟอร์ม) --}}
    <div class="mt-3">@include('tb._contacts', ['reg' => $reg])</div>

    {{-- X-ray · ขนาดยารายเดือน · ปฏิทินกินยา · ผลความไวต่อยา --}}
    @include('tb._sections', ['dotMonthOptions' => $months])

    {{-- แท็บติดตาม: ตารางติดตามการรักษา · ตรวจสายตา · คัดกรองซึมเศร้า (2569-10-05) --}}
    @include('tb._monitor', ['reg' => $reg])
</div>

{{-- popup ของกล่อง "สั่งตรวจครั้งนี้" — ต้องอยู่นอกฟอร์มอื่นทั้งหมด จึงวางท้ายหน้า หลังปิด container --}}
@include('tb._lab_order_modals')
{{-- popup ของแท็บติดตาม (ติ๊กช่อง / ตรวจสายตา / ซึมเศร้า) — นอกฟอร์มอื่นทั้งหมด --}}
@include('tb._monitor_modals', ['reg' => $reg])
{{-- ตัวเลือก "สั่งแล็บ" จากแคตตาล็อกรวม (2569-10-02) — ตารางยังไม่ขึ้น = ไม่วาด ปุ่มชุดเดิมทำงานตามปกติ --}}
@if(\App\Models\LabCatalogItem::availableFor(2))
    @include('partials.lab_catalog_picker')
@endif
@endsection

@push('scripts')
{{-- ปฏิทิน พ.ศ. ไม่ได้อยู่ใน layout กลาง แต่ละหน้าต้องดึงเอง (ทำเหมือนหน้าวันหยุด/แล็บ) --}}
<script src="/js/bootstrap-datepicker.js"></script>
<script src="/js/bootstrap-datepicker-thai.js"></script>
<script src="/js/bootstrap-datepicker.th.js"></script>
@include('partials.th_datepicker_guard')
{{-- กล่องสั่งตรวจครั้งนี้: ตัวจัดการช่องส่งตรวจ (ชุดเดียวกับหน้าตรวจ) + popup --}}
@include('tb._lab_fields_js')
@include('tb._inhouse_lab_js')
@include('tb._lab_order_js')
@if(\App\Models\LabCatalogItem::availableFor(2))
    @include('partials.lab_catalog_picker_js')
@endif
@include('tb._monitor_js')
@include('tb._date_input_js', ['scope' => 'body'])
<script>
$(function () {
    $('.tb-date').datepicker({ language: 'th-th', autoclose: true, todayHighlight: true });
});


// ลบบัตร — สร้างฟอร์มต่อกับ body ตามกติกาของโปรเจกต์ (ห้าม <form> ซ้อน)
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.js-tb-delete');
    if (!btn) return;
    if (!confirm('ลบบัตรนี้ทิ้ง? ข้อมูลที่กรอกไว้จะหายทั้งหมด')) return;

    var f = document.createElement('form');
    f.method = 'POST';
    f.action = btn.dataset.url;
    f.style.display = 'none';
    f.innerHTML = '<input type="hidden" name="_method" value="DELETE">'
                + '<input type="hidden" name="_token" value="{{ csrf_token() }}">';
    document.body.appendChild(f);
    f.submit();
});
</script>
@endpush
