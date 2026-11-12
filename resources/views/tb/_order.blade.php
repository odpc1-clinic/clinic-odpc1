{{--
    กล่อง "สั่งยาครั้งนี้" — ใช้ 2 ที่ (บัตร TB 01 คลินิกขอ 2569-10-02 · หน้าตรวจ OPD วัณโรค 2569-10-05)
      1) บัตร TB 01 แท็บยา: เป็น <form> ของตัวเอง วางเป็นพี่น้องกับฟอร์มอื่นในบัตร ไม่ซ้อนกัน (CLAUDE.md ข้อ 1)
      2) หน้าตรวจ ($embed): กล่องอยู่ "ใน" ฟอร์มหลักของหน้าตรวจ จึงห้ามเป็น <form> — ใช้ <div> แทน
         และทุกช่องได้ form="tb-ord-detached" (ไม่มีฟอร์ม id นี้) = ช่องไม่ได้เป็นของฟอร์มหลัก
         → ไม่ถูกส่งตอนกด Save หน้าตรวจ ไม่ถูกนับใน _touched ไม่โดนตรวจ required/min/max ของฟอร์มหลัก
           (ช่อง weight ชื่อชนกับน้ำหนักของ visit — ถ้าหลุดไปกับฟอร์มหลักจะทับน้ำหนักที่พยาบาลชั่ง)
         + data-no-dirty ไม่ให้ขึ้นป้าย "ยังไม่ได้ Save" · บันทึกด้วย fetch (_order_js) แล้วรีเฟรชรายการยาในที่
    ขั้นที่ 1 รวม น้ำหนัก · วันนัด/จำนวนวัน · สูตรยา ไว้แถวเดียว (คลินิกขอ 2569-10-05 เดิมแยก 3 ขั้น)
    เกณฑ์เด็ก/ผู้ใหญ่ตามอายุอย่างเดียว (18 ปีขึ้นไป = ผู้ใหญ่) ไม่มีช่องให้สลับแล้ว
    หมอและพยาบาลบันทึกได้เหมือนกัน (สิทธิ์เดียวกับการบันทึกขนาดยา/วิธีใช้เดิม)

    ตัวแปร: $reg, $order (TbController::orderBoxData) · $embed (TbVisitOrder::visitOrderBoxData — ไม่ส่งมา = หน้าบัตร)
--}}
@php
    $o = $order;
    $__embed = $embed ?? null;
    $__hasReg = ! $__embed || $__embed['registered'];   // มีบัตรจริง = มีรอบ / ประวัติสูตร / สติกเกอร์
    // ในหน้าตรวจห้ามอ่าน old() — ค่าเก่าของฟอร์มหลัก (เช่น weight ของ visit) จะมาโผล่ในกล่องนี้
    $__old = fn ($k, $d = null) => $__embed ? $d : old($k, $d);
    $__fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    $__grpName = \App\Models\TbDrug::$groupOption;
    // ตัวเลือกสูตร จัดกลุ่มตามกลุ่มยา — กลุ่มที่ใช้อยู่ขึ้นก่อน
    $__optGroups = collect($o['opts'])->except('custom')->groupBy('group', true)
        ->sortBy(fn ($v, $g) => $g === $o['activeGroup'] ? 0 : 1);
    $__sel = $__old('regimen_opt', $o['cur']['key']);
    $__oldItems = $__old('items', []);
    $__savedRow = (! $__embed && session('tb_order_row')) ? $reg->monthlyDoses->firstWhere('id', (int) session('tb_order_row')) : null;
    $__printRow = $__hasReg ? ($__savedRow ?: $o['latest']) : null;
    $__isCustom = $__sel === 'custom';
    $__customDrugs = $__old('drugs', $o['cur']['customDrugs']);
    // ยาวัณโรคที่สั่งผ่าน Add New ในหน้าตรวจ (2569-10-12) — สูตร Individualized ติ๊กให้ด้วย
    $__fromManual = (array) ($__embed['fromManual'] ?? []);
    // ตารางยาตารางเดียว (คลินิกขอ 2569-10-14): กล่องที่แก้ได้ในหน้าตรวจ = รายการยาของ visit — ตารางรายการยาเดิมด้านบนซ่อน
    // ล็อก (ไม่ใช่ visit วันนี้ / การเงิน-ห้องยาทำงานแล้ว) = ใช้ตารางรายการยาเดิมเหมือนก่อน
    $__unified = ! empty($__embed['unified']);
    // B6: สูตรที่ต้องมี B6 แต่รอบที่สั่งไว้วันนี้ไม่มี = หมอเอาออกเอง → เปิดหน้ามาก็ยังไม่ให้ (2569-10-14)
    $__b6Off = $o['todayRow'] && ! array_key_exists('B6', (array) $o['todayRow']->doses);
    if ($__fromManual && $__sel === 'custom') {
        $__customDrugs = array_values(array_unique(array_merge((array) $__customDrugs, array_diff(array_keys($__fromManual), ['B6']))));
    }
    $__step = 'display:inline-flex;align-items:center;justify-content:center;width:1.7rem;height:1.7rem;border-radius:50%;background:#b45309;color:#fff;font-weight:700;margin-right:.4rem;';
    $__doseText = ($o['doseType'] === 'ped' ? 'เด็ก' : 'ผู้ใหญ่')
        . (isset($o['doseAge']) && $o['doseAge'] !== null ? ' (อายุ ' . $o['doseAge'] . ')' : ' (ไม่ทราบอายุ)');
    // ข้อมูลที่สคริปต์ใช้ — ชุดเดียวกันทั้งสองโหมด
    $__data = 'data-rules="' . e(json_encode($o['rules'])) . '"'
        . ' data-age="' . (isset($o['doseAge']) && $o['doseAge'] !== null ? (int) $o['doseAge'] : '') . '"'
        . ' data-ltbi="' . ($__hasReg ? ($o['ltbi'] ? '1' : '0') : 'auto') . '"'
        . ' data-today="' . e(\App\Utillity::db2thDate($o['today'])) . '"'
        . ' data-orig="' . e($o['cur']['key']) . '"'
        . ' data-orig-drugs="' . e(json_encode($o['cur']['customDrugs'])) . '"'
        . ' data-b6-with="' . e(json_encode(\App\Models\TbRegistration::$b6With)) . '"'
        . ' data-b6-off="' . ($__b6Off ? 1 : 0) . '"'
        . ' data-unified="' . ($__unified ? 1 : 0) . '"';
@endphp
<style>
    #tb-order .tb-ord-step { background:#fffbeb; border:1px solid #fde68a; border-radius:.5rem; padding:.6rem .75rem; margin-bottom:.6rem; }
    #tb-order .tb-ord-step > .tb-ord-title { font-weight:700; color:#7c2d12; margin-bottom:.4rem; }
    #tb-order .js-ord-row.tb-ord-auto .js-ord-qty { background:#fff3cd; }
    #tb-order td { vertical-align: middle; }
</style>
@php ob_start(); @endphp
@if($__embed)
<div class="mt-3" id="tb-order" data-embed="1" data-no-dirty {!! $__data !!}
     data-url="{{ $__embed['url'] }}" data-token="{{ csrf_token() }}" data-page="{{ request('page', 1) }}"
     data-pharm="{{ json_encode($__embed['pharm']) }}" data-locked="{{ $__embed['lock'] ? 1 : 0 }}">
@else
<form method="POST" action="{{ route('tb.order', $reg->id) }}" autocomplete="off" class="mb-3" id="tb-order" {!! $__data !!}>
    @csrf
@endif
    <div class="card border-0 shadow-sm" style="border:2px solid #f0c088 !important;">
        <div class="card-body">
            <h6 class="fw-bold mb-2" style="color:#7c2d12;"><i class="fa fa-prescription me-1"></i> สั่งยาวัณโรคครั้งนี้
                <span class="small fw-normal text-muted ms-1">กรอกขั้น ① → ② แล้วกดบันทึกครั้งเดียว</span>
            </h6>

            @if($__embed)
                {{-- บอกให้ชัดว่ากดบันทึกแล้วไปลงที่ไหนบ้าง — ห้องยา/การเงินเห็นจากรายการยาด้านบน --}}
                <div class="small mb-2 px-2 py-1 rounded" style="background:#f0fdfa;color:#115e59;border:1px solid #99f6e4;">
                    <i class="fa fa-circle-info me-1"></i>
                    @if($__embed['registered'])
                        กดบันทึก = ลง<b>รายการยา{{ $__unified ? '' : 'ด้านบน' }}</b> (การเงินคิดเงิน · ห้องยาจ่าย/ตัดสต๊อก) และลง<b>{{ $reg->cardName() }}</b> (รอบสั่งยา · สูตร · วิธีใช้) พร้อมกัน
                    @else
                        คนไข้ยังไม่มีบัตร TB 01 — กดบันทึก = ลง<b>รายการยา{{ $__unified ? '' : 'ด้านบน' }}</b>อย่างเดียว (ไม่บันทึกอะไรลงบัตร)
                    @endif
                    @if($__unified)
                        · <b>ตารางนี้คือรายการยาของ visit นี้</b> — ยาที่เพิ่มด้วยปุ่ม Add New / ยาเดิม ก็ขึ้นในตารางนี้ แก้ในแถวแล้วกดบันทึกครั้งเดียว
                    @else
                        · กดซ้ำ = แก้ยาที่กล่องนี้ลงไว้ ไม่แตะยาที่เพิ่มด้วยปุ่ม Add New
                    @endif
                </div>
                @if($__embed['lock'])
                    <div class="alert alert-warning py-2 px-3 mb-2 d-flex flex-wrap align-items-center gap-2 js-ord-lock-msg"><span><i class="fa fa-lock me-1"></i> {{ $__embed['lock'] }}</span>
                        @if(! empty($__embed['dateLockOnly']))
                            {{-- visit เก่าที่ยังไม่มีการเงินคิดเงิน — ปลดล็อกเองได้ (ต้องกดยืนยัน) ลงยาย้อนหลังตามวันของ visit --}}
                            <button type="button" class="btn btn-sm btn-outline-dark ms-auto js-ord-unlock"><i class="fa fa-unlock me-1"></i> ปลดล็อก ลงยาย้อนหลัง</button>
                        @endif
                    </div>
                @elseif(! empty($__embed['asOf']))
                    {{-- ลงประวัติย้อนหลัง: บอกให้ชัดว่ารอบยาในบัตรจะลงวันไหน กันคิดว่าเป็นของวันนี้ --}}
                    <div class="alert alert-info py-2 px-3 mb-2"><i class="fa fa-clock-rotate-left me-1"></i>
                        visit ย้อนหลัง — กดบันทึกแล้วรอบยาใน{{ $reg->cardName() }} ลง<b>วันที่ {{ \App\Utillity::db2thDate($__embed['asOf']) }}</b> (วันของ visit นี้) และวันนัดคิดจากวันนั้น
                        @if(! empty($o['pastSeg']))
                            {{-- วันนั้นอยู่ในช่วงสูตรเก่า (2569-10-26) — บอกให้รู้ว่าไม่ไปแตะสูตรที่ใช้อยู่ตอนนี้ --}}
                            <div class="mt-1"><i class="fa fa-layer-group me-1"></i>
                                วันนั้นอยู่ในช่วงสูตร <b>{{ $o['pastSeg']['regimen'] }}</b>
                                ({{ \App\Utillity::db2thDate($o['pastSeg']['start']) }}{{ $o['pastSeg']['end'] ? ' – ' . \App\Utillity::db2thDate($o['pastSeg']['end']) : '' }})
                                — ลงเป็นรอบของช่วงนั้น นับรอบแยกจากสูตรปัจจุบัน · สูตรและวิธีใช้ยาที่ใช้อยู่ตอนนี้ไม่เปลี่ยน
                            </div>
                        @endif
                    </div>
                @endif
            @endif

            @if(! $__embed && $errors->has('order'))
                <div class="alert alert-danger py-2 px-3">{{ $errors->first('order') }}</div>
            @endif
            @if($__savedRow)
                <div class="alert alert-success py-2 px-3 d-flex flex-wrap align-items-center gap-2">
                    <span><i class="fa fa-circle-check me-1"></i> บันทึกการสั่งยา <b>{{ $__savedRow->labelForHumans() }}</b> แล้ว — พิมพ์ได้เลย:</span>
                    <a href="{{ route('tb.labels', ['id' => $reg->id, 'row' => $__savedRow->id]) }}" target="_blank" class="btn btn-sm btn-success">
                        <i class="fa fa-tag"></i> พิมพ์สติกเกอร์</a>
                    <a href="{{ route('tb.medSheet', $reg->id) }}" target="_blank" class="btn btn-sm btn-outline-success">
                        <i class="fa fa-print"></i> พิมพ์ใบกินยา (มีรูป)</a>
                    <a href="{{ route('tb.labels', ['id' => $reg->id, 'row' => $__savedRow->id, 'format' => 'summary']) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-file-medical"></i> ใบสรุปรายการยา</a>
                </div>
            @endif
            @if($o['todayRow'])
                <div class="alert alert-warning py-2 px-3 small mb-2">
                    <i class="fa fa-circle-info me-1"></i> วันนี้สั่งยาไปแล้ว ({{ $o['todayRow']->labelForHumans() }}) — ค่าในกล่องนี้เป็นของที่สั่งไว้
                    กดบันทึกอีกครั้ง = <b>แก้รอบเดิม</b> ไม่สร้างรอบใหม่ซ้ำ
                </div>
            @endif

            {{-- ① น้ำหนัก · วันนัด → จำนวนวัน · (รอบ) · สูตรยา — แถวเดียว --}}
            <div class="tb-ord-step">
                <div class="tb-ord-title"><span style="{{ $__step }}">1</span> น้ำหนัก · วันนัด · สูตรยา</div>
                <div class="row g-2 align-items-end">
                    <div class="col-auto">
                        <label class="form-label small text-muted mb-1" for="tb-ord-weight">น้ำหนัก (กก.)</label>
                        <input type="number" step="0.01" min="1" max="250" name="weight" class="form-control form-control-sm" style="width:6.5rem;"
                               value="{{ $__old('weight', $o['weight'] !== null ? $__fmt($o['weight']) : '') }}" id="tb-ord-weight">
                    </div>
                    <div class="col-auto">
                        <span class="form-label small text-muted mb-1 d-block">เกณฑ์ขนาดยา</span>
                        {{-- ตามอายุอย่างเดียว (18 ปีขึ้นไป = ผู้ใหญ่) — ใช้คิดอย่างเดียว ไม่บันทึก --}}
                        <span class="d-inline-block small px-2 py-1 rounded border bg-light text-dark" style="white-space:nowrap;"
                              title="18 ปีขึ้นไป = เกณฑ์ผู้ใหญ่ · ต่ำกว่า 18 ปี = เกณฑ์เด็ก (ตามอายุในทะเบียนคนไข้) · ยาป้องกัน LTBI ตัดตามสูตร: 3HP 14 ปี · 1HP ตั้งแต่ 13 ปี · 4R/3HR/6-9H 10 ปี">เกณฑ์{{ $__doseText }}</span>
                        <input type="hidden" id="tb-ord-type" value="{{ $o['doseType'] }}">
                    </div>
                    <div class="col-auto">
                        <label class="form-label small text-muted mb-1" for="tb-ord-date">วันนัดครั้งหน้า</label>
                        <input type="text" name="next_date" class="form-control form-control-sm tb-date" style="width:8.5rem;" id="tb-ord-date"
                               value="{{ $__old('next_date', \App\Utillity::db2thDate($o['nextDate'])) }}" placeholder="วว/ดด/ปปปป">
                    </div>
                    <div class="col-auto">
                        <label class="form-label small text-muted mb-1" for="tb-ord-days">จ่ายยา (วัน)</label>
                        <input type="number" min="1" max="120" name="days" class="form-control form-control-sm" style="width:5rem;" id="tb-ord-days"
                               value="{{ $__old('days', $o['days']) }}" required>
                    </div>
                    @if($__hasReg)
                        <div class="col-auto">
                            <label class="form-label small text-muted mb-1" for="tb-ord-label">รอบ (แถวในใบสรุปการรักษา)</label>
                            <div class="d-flex gap-1">
                                <select name="label" class="form-select form-select-sm" style="width:10rem;" id="tb-ord-label"
                                        data-default="{{ $o['defaultLabel'] }}" data-fixed="{{ $o['todayRow'] ? 1 : 0 }}">
                                    @foreach($o['labels'] as $__lv => $__lt)
                                        <option value="{{ $__lv }}" {{ (string) $__old('label', $o['defaultLabel']) === (string) $__lv ? 'selected' : '' }}>{{ $__lt }}</option>
                                    @endforeach
                                    <option value="__custom" {{ $__old('label') === '__custom' ? 'selected' : '' }}>นอกรอบ (ตั้งชื่อเอง)…</option>
                                </select>
                                <input type="text" name="label_custom" maxlength="30" class="form-control form-control-sm {{ $__old('label') === '__custom' ? '' : 'd-none' }}"
                                       id="tb-ord-label-custom" style="width:9rem;" placeholder="เช่น วันที่ 2 / ปรับยา" value="{{ $__old('label_custom') }}">
                                <select name="month_no" class="form-select form-select-sm {{ $__old('label') === '__custom' ? '' : 'd-none' }}" id="tb-ord-month" style="width:8rem;"
                                        title="แถวนี้อยู่ในเดือนที่">
                                    @for($__m = 0; $__m <= $o['maxMonth']; $__m++)
                                        <option value="{{ $__m }}" {{ $__m === min($o['maxMonth'], $reg->monthsOnTreatment()) ? 'selected' : '' }}>ในเดือนที่ {{ $__m }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                    @endif
                    <div class="col" style="min-width:16rem;">
                        <label class="form-label small text-muted mb-1" for="tb-ord-regimen">สูตรยา</label>
                        <select name="regimen_opt" class="form-select form-select-sm" id="tb-ord-regimen" required>
                            <option value="" {{ $__sel === '' ? 'selected' : '' }}>— เลือกสูตรยา —</option>
                            @foreach($__optGroups as $__g => $__list)
                                <optgroup label="{{ $__grpName[$__g] ?? $__g }}">
                                    @foreach($__list as $__k => $__op)
                                        <option value="{{ $__k }}" data-drugs='@json($__op['drugs'])' data-group="{{ $__op['group'] }}" {{ $__sel === $__k ? 'selected' : '' }}>
                                            {{ $__op['text'] }}{{ $__k === $o['cur']['key'] ? '  ✓ ใช้อยู่' : '' }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                            <optgroup label="ปรับรายบุคคล">
                                <option value="custom" data-drugs="[]" data-group="{{ $o['opts']['custom']['group'] }}" {{ $__isCustom ? 'selected' : '' }}>
                                    {{ $o['opts']['custom']['text'] }}{{ $o['cur']['key'] === 'custom' ? '  ✓ ใช้อยู่' : '' }}
                                </option>
                            </optgroup>
                        </select>
                    </div>
                </div>
                <div class="small text-muted mt-1">
                    {{ $o['weight'] !== null ? 'น้ำหนักล่าสุด ' . $__fmt($o['weight']) . ' กก. (' . ($o['weightSource'] ?? '') . ')' : 'ยังไม่มีน้ำหนักในระบบ — กรอกน้ำหนักเพื่อคำนวณขนาดยา' }}
                    · <span id="tb-ord-date-src">{{ $o['dateSource'] }}</span>
                    @if($__hasReg) · {{ ! empty($o['pastSeg']) ? 'สูตร ณ วันนั้น' : 'สูตรที่ใช้อยู่' }}: <b>{{ $o['cur']['curText'] ?: 'ยังไม่ระบุ' }}</b>@if($o['cur']['curSet']) ({{ implode(' · ', $o['cur']['curSet']) }})@endif @endif
                    @hasrole('Admin') · <a href="{{ route('tb.doseRules') }}" target="{{ $__embed ? '_blank' : '_self' }}">แก้เกณฑ์ขนาดยา</a> @endhasrole
                </div>
                <div class="small text-danger d-none" id="tb-ord-date-warn">วันนัดต้องอยู่หลังวันนี้ — แก้วันนัดหรือพิมพ์จำนวนวันเอง</div>

                {{-- สิ้นสุดระยะเข้มข้น → ระยะต่อเนื่อง (คลินิกขอ 2569-10-28): ไม่กด = ให้ยาระยะเข้มข้นต่อ (ขยายได้ถึงเดือนที่ 3 ถ้าเสมหะยังบวก)
                     กดแล้วเลือกสูตรระยะต่อเนื่องในช่องสูตรยาให้ · กดบันทึกการสั่งยา = ขึ้นระยะใหม่ในประวัติสูตร (นับเดือนต่อจากช่วงเดิม) --}}
                @php
                    $__curOpt = $o['opts'][$o['cur']['key'] ?? ''] ?? null;
                    $__contKey = null;
                    // โผล่ตั้งแต่ครบเดือนที่ 2 (สิ้นสุดระยะเข้มข้นตามปกติ หรือขยายถึงเดือนที่ 3)
                    if ($__hasReg && empty($o['pastSeg']) && $__curOpt && ($__curOpt['phase'] ?? null) === 1 && ($__curOpt['group'] ?? null) === 'DS'
                        && $reg->monthsOnTreatment() >= 2) {
                        $__k = $o['cur']['key'];
                        if (! empty($__curOpt['split']) && isset($o['opts'][preg_replace('/:1$/', ':2', $__k)])) {
                            $__contKey = preg_replace('/:1$/', ':2', $__k);
                        } else {
                            foreach ($o['opts'] as $__ck => $__co) {
                                if ($__ck !== 'custom' && ($__co['phase'] ?? null) === 2 && ($__co['group'] ?? null) === $__curOpt['group']
                                    && empty($__co['split']) && ! array_diff($__co['drugs'], $__curOpt['drugs'])) { $__contKey = $__ck; break; }
                            }
                        }
                    }
                    $__conv = $__hasReg ? $reg->sputumConversion() : null;
                @endphp
                @if($__contKey || $__conv)
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2 p-2 rounded" style="background:#fff7ed;border:1px solid #fdba74;">
                        @if($__conv)
                            <span class="badge fw-bold" style="background:{{ $__conv['bg'] }};color:{{ $__conv['color'] }};border:1px solid {{ $__conv['color'] }};">Sputum conversion: {{ $__conv['text'] }}</span>
                        @endif
                        @if($__contKey)
                            <button type="button" class="btn btn-sm btn-warning fw-bold js-ord-cont" data-key="{{ $__contKey }}">
                                <i class="fa fa-forward me-1"></i> สิ้นสุดระยะเข้มข้น → เริ่มระยะต่อเนื่อง ({{ $o['opts'][$__contKey]['text'] }})
                            </button>
                            <span class="small text-muted">ไม่กด = ให้ยาระยะเข้มข้นเดิมต่อ · ตอนนี้เดือนที่ {{ $reg->monthsOnTreatment() }}</span>
                        @endif
                    </div>
                @endif

                @if($__hasReg && empty($o['pastSeg']))
                    {{-- Off สูตรเดิมแล้วเริ่มนับใหม่ (คลินิกขอ 2569-10-26) — ขาดยาแล้วกลับมาเริ่มใหม่ / เปลี่ยนแนวทางรักษา
                         ติ๊กแล้วรอบกลับเป็น 0 · ใบสรุปขึ้นหัวช่วงใหม่ · ตารางติดตามและ "เดือนที่" นับจากวันนี้ (TbRegistration::doseSegments) --}}
                    <div class="form-check mt-1 small">
                        <input class="form-check-input" type="checkbox" name="restart" value="1" id="tb-ord-restart" {{ $__old('restart') ? 'checked' : '' }}>
                        <label class="form-check-label" for="tb-ord-restart">
                            <b>Off สูตรเดิม แล้วเริ่มนับใหม่</b> (รอบ 0 · ตารางติดตามนับ ด.0 วันนี้) — ใช้เมื่อขาดยาแล้วเริ่มใหม่ หรือเปลี่ยนการรักษาเป็นคอร์สใหม่
                        </label>
                    </div>
                @endif

                @if($__hasReg)
                    {{-- เปลี่ยนสูตร/ระยะ = ขึ้นช่วงสูตรใหม่ในประวัติ (สูตรเดิมไม่ถูกลบ) — โผล่เฉพาะตอนเลือกต่างจากที่ใช้อยู่
                         ไม่มีบัตร = ไม่มีประวัติสูตรให้เขียน จึงไม่มีช่องนี้ --}}
                    <div class="d-none mt-2" id="tb-ord-reason-wrap">
                        <div class="alert alert-info py-1 px-2 small mb-1" style="background:#e7f1ff;color:#084298;border-color:#b6d4fe;">
                            <i class="fa fa-arrows-rotate me-1"></i> เปลี่ยนสูตร/ระยะ — กดบันทึกแล้วระบบจะปิดช่วงสูตรเดิมและขึ้นช่วงใหม่วันนี้ (ดูได้ในประวัติสูตรยาท้ายแท็บยาของบัตร)
                        </div>
                        <input type="text" name="reason" maxlength="255" class="form-control form-control-sm" value="{{ $__old('reason') }}"
                               placeholder="เหตุผลที่เปลี่ยน (ไม่ใส่ก็ได้) เช่น ครบ 2 เดือน เข้าระยะต่อเนื่อง / แพ้ยา / ผลดื้อยา">
                    </div>
                @endif

                {{-- Individualized: ติ๊กยาได้กลุ่มเดียว (กลุ่มที่ใช้อยู่) — กลุ่มอื่นซ่อนทั้งแถว กดลิงก์ถึงจะเปิด --}}
                <div class="mt-2 {{ $__isCustom ? '' : 'd-none' }}" id="tb-ord-custom" data-active="{{ $o['activeGroup'] }}">
                    <div class="row g-2 mb-1">
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">ชื่อสูตร</label>
                            <input type="text" name="regimen_text" maxlength="255" class="form-control form-control-sm"
                                   value="{{ $__old('regimen_text', $o['cur']['customText']) }}" {{ $__isCustom ? '' : 'disabled' }}>
                        </div>
                    </div>
                    @php $__curExtra = array_diff((array) $__customDrugs, array_keys($o['groups'][$o['activeGroup']] ?? [])); @endphp
                    @foreach($o['groups'] as $__g => $__drugs)
                        @continue(empty($__drugs))
                        @php $__on = $__g === $o['activeGroup']; @endphp
                        <div class="border rounded p-2 mb-1 js-ord-grp {{ $__on ? '' : 'd-none' }}" data-group="{{ $__g }}"
                             style="background:#fff7ed; border-color:#f0c088 !important;">
                            <div class="small fw-bold mb-1" style="color:#b45309;">
                                {{ $__grpName[$__g] ?? $__g }}
                                <a href="#" class="fw-normal small ms-2 js-ord-grp-switch {{ $__on ? '' : 'd-none' }}">เปลี่ยนกลุ่มยา</a>
                                <a href="#" class="fw-normal small ms-2 js-ord-grp-use {{ $__on ? 'd-none' : '' }}">ใช้กลุ่มนี้แทน</a>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($__drugs as $__dk => $__info)
                                    @continue($__dk === 'B6')
                                    <div class="form-check">
                                        <input class="form-check-input js-ord-drug" type="checkbox" id="ordd_{{ $__g }}_{{ $__dk }}" name="drugs[]" value="{{ $__dk }}"
                                               {{ $__on && in_array($__dk, $__customDrugs, true) ? 'checked' : '' }} {{ $__on && $__isCustom ? '' : 'disabled' }}>
                                        <label class="form-check-label small" for="ordd_{{ $__g }}_{{ $__dk }}"
                                               title="{{ $__info['label'] }}{{ count($__info['strengths']) ? ' · ' . implode(' / ', array_unique($__info['strengths'])) : '' }}">{{ $__dk }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    @if($__curExtra)
                        {{-- ยาในสูตรเดิมที่ไม่อยู่ในกลุ่มนี้ — ต้องโชว์ไว้ ไม่งั้นกดบันทึกแล้วหายเงียบ ๆ --}}
                        <div class="border rounded p-2 mb-1 js-ord-grp-extra" style="background:#fff7ed; border-color:#f0c088 !important;">
                            <div class="small fw-bold mb-1" style="color:#b45309;">ยาในสูตรเดิมที่อยู่นอกกลุ่มนี้</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($__curExtra as $__dk)
                                    <div class="form-check">
                                        <input class="form-check-input js-ord-drug" type="checkbox" id="ordd_x_{{ $__dk }}" name="drugs[]" value="{{ $__dk }}"
                                               checked {{ $__isCustom ? '' : 'disabled' }}>
                                        <label class="form-check-label small" for="ordd_x_{{ $__dk }}">{{ $__dk }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="small text-muted">4FDC กับ 2FDC ติ๊กได้ตัวเดียว (คนละระยะ) · B6 เติมให้เองในขั้นที่ 2 เฉพาะสูตรที่มี Isoniazid (H, Hhd, 4FDC, 2FDC), Cycloserine, Ethionamide หรือ Prothionamide</div>
                </div>
            </div>

            {{-- ② ตารางสั่งยา — หนึ่งแถวต่อยา: เกณฑ์ตามน้ำหนัก + ครั้งละ + ความถี่ + เวลา → จำนวนที่ต้องจ่าย --}}
            <div class="tb-ord-step">
                <div class="tb-ord-title"><span style="{{ $__step }}">2</span> ยาและวิธีใช้</div>
                @if($__fromManual)
                    <div class="small p-2 mb-2 rounded" style="background:#eff6ff; border:1px solid #bfdbfe; color:#1e3a8a;">
                        <i class="fa fa-circle-info me-1"></i> เติมจากยาที่สั่งผ่านปุ่ม Add New: <b>{{ implode(', ', $__fromManual) }}</b>
                        — ตรวจแล้วกดบันทึกของกล่องนี้ ระบบจะใช้แถวเดิมในรายการยา (ไม่ลงซ้ำ) คิดจำนวนจ่าย และลงบัตร TB 01 ให้
                    </div>
                @endif
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-1 bg-white" id="tb-ord-table">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width:13rem">ยา · เกณฑ์ตามน้ำหนัก</th>
                                <th style="width:10rem">ครั้งละ</th>
                                <th style="width:11.5rem">ความถี่ · วันที่กิน</th>
                                <th style="width:7.5rem">Route</th>
                                <th style="width:11rem">เวลา</th>
                                <th style="min-width:15rem">ช่วงเวลา / พิมพ์เพิ่มเอง</th>
                                <th style="width:8rem">จ่ายทั้งหมด</th>
                                @if($__unified)<th style="width:10rem">ในรายการยา · ราคา</th>@endif
                            </tr>
                        </thead>
                        <tbody id="tb-ord-body">
                        @foreach($o['rows'] as $__k => $__r)
                            @php
                                $__p  = 'items[' . $__k . ']';
                                $__sg = (array) ($__oldItems[$__k] ?? $__r['sig']);
                                $__qty = $__oldItems[$__k]['qty'] ?? $__r['qty'];
                            @endphp
                            <tr class="js-ord-row d-none" data-key="{{ $__k }}" data-kind="{{ $__r['kind'] }}"
                                data-prefer="{{ $__r['prefer'] }}" data-fallback="{{ $__r['fallback'] }}"
                                data-preset="{{ $__qty !== null && $__qty !== '' ? 1 : 0 }}">
                                <td>
                                    <div class="fw-bold">{{ $__r['name'] }}</div>
                                    @if($__k === 'B6')
                                        {{-- B6 ขึ้นเองตามสูตร แต่หมอยืนยันไม่ให้ได้ (คลินิกขอ 2569-10-14) --}}
                                        <button type="button" class="btn btn-link btn-sm text-danger p-0 js-ord-b6-off">ไม่ให้ B6 รอบนี้</button>
                                    @endif
                                    @if($__r['kind'] === 'strength')
                                        <div class="d-flex align-items-center gap-1 small">
                                            <span class="text-muted">ความแรง</span>
                                            <select name="{{ $__p }}[strength]" class="form-select form-select-sm js-ord-str" style="width:auto;" disabled>
                                                @foreach($__r['strengths'] as $__s)
                                                    <option value="{{ $__fmt($__s) }}" {{ (float) ($__sg['strength'] ?? $__r['strength']) === (float) $__s ? 'selected' : '' }}>{{ $__fmt($__s) }} mg</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                    <div class="small js-ord-rule"></div>
                                    <div class="small text-danger js-ord-warn"></div>
                                    @if($__embed)<div class="small js-ord-pharm"></div>@endif
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="text" inputmode="decimal" name="{{ $__p }}[qty]" class="form-control js-ord-qty" style="max-width:4.5rem;"
                                               value="{{ $__qty }}" placeholder="0" disabled>
                                        <span class="input-group-text">{{ $__r['kind'] === 'mg' ? 'มก.' . (! empty($__r['inj']) ? ' (ฉีด)' : '') : 'เม็ด' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <select name="{{ $__p }}[freq]" class="form-select form-select-sm js-ord-freq" disabled>
                                        @foreach(\App\Models\TbRegistration::$sigFreq as $__c => [$__lbl, $__n])
                                            <option value="{{ $__c }}" {{ ($__sg['freq'] ?? 'OD') === $__c ? 'selected' : '' }}>{{ $__c }} · {{ $__lbl }}</option>
                                        @endforeach
                                    </select>
                                    {{-- ติ๊กวันที่กิน (คลินิกขอ 2569-10-22 — BDQ จ-พ-ศ หาตัวเลือก TIW ในรายการไม่เจอ บันทึกออกมาเป็นวันละครั้ง)
                                         ไม่มี name: เป็นตัวช่วยตั้งความถี่ + เขียนวันลงช่อง "พิมพ์เพิ่มเอง" เท่านั้น ไม่ได้ถูกส่งไปเอง --}}
                                    <div class="js-ord-wd d-flex flex-wrap gap-1 mt-1" title="ติ๊ก 3 วัน = สัปดาห์ละ 3 ครั้ง · 1 วัน = สัปดาห์ละครั้ง · ไม่ติ๊ก = ทุกวัน">
                                        @foreach(['จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส', 'อา'] as $__wi => $__wd)
                                            <input type="checkbox" class="btn-check" id="tb-wd-{{ $__k }}-{{ $__wi }}" value="{{ $__wd }}" autocomplete="off">
                                            <label class="btn btn-outline-primary btn-sm py-0 px-1" style="min-width:1.9rem;font-size:.8rem;" for="tb-wd-{{ $__k }}-{{ $__wi }}">{{ $__wd }}</label>
                                        @endforeach
                                    </div>
                                    <div class="small text-muted js-ord-wd-hint"></div>
                                </td>
                                <td>
                                    <select name="{{ $__p }}[route]" class="form-select form-select-sm" disabled>
                                        @foreach(\App\Models\TbRegistration::$sigRoute as $__c => $__lbl)
                                            <option value="{{ $__c }}" {{ ($__sg['route'] ?? 'PO') === $__c ? 'selected' : '' }}>{{ $__c }} · {{ $__lbl }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="{{ $__p }}[timing]" class="form-select form-select-sm" disabled>
                                        <option value="">— ไม่ระบุ —</option>
                                        @foreach(\App\Models\TbRegistration::$sigTiming as $__c => $__lbl)
                                            <option value="{{ $__c }}" {{ ($__sg['timing'] ?? '') === $__c ? 'selected' : '' }}>{{ $__lbl }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        @include('partials.drug_sig_period', ['prefix' => $__p, 'sig' => $__sg])
                                    </div>
                                </td>
                                <td class="fw-bold js-ord-total"></td>
                                @if($__unified)<td>@include('tb._order_vm_cell', ['vm' => $__embed['linked'][$__k] ?? null, 'editLink' => false])</td>@endif
                            </tr>
                        @endforeach
                        </tbody>
                        {{-- ยาอื่นที่ไม่ใช่ยาวัณโรค (Para, Brufen ...) — จ่ายเป็นจำนวน ไม่ได้อยู่ในสูตร --}}
                        <tbody id="tb-ord-others">
                        @foreach($__old('other', $o['others']) as $__n => $__ot)
                            @include('tb._order_other_row', ['n' => $__n, 'ot' => $__ot])
                        @endforeach
                        </tbody>
                        {{-- ยาที่สั่งผ่าน Add New แต่กล่องรับเป็นแถวไม่ได้ (ยาฉีด vial, STAT, วัคซีน ...) — อ่านอย่างเดียว แก้ที่ปุ่มดินสอ --}}
                        @if($__unified && ! empty($__embed['extra']))
                        <tbody id="tb-ord-extra">
                            <tr><td colspan="8" class="small fw-bold bg-light">ยาอื่นที่สั่งผ่านปุ่ม Add New — แก้ด้วยปุ่มดินสอ (ไม่ผ่านกล่องนี้)</td></tr>
                            @foreach($__embed['extra'] as $__x)
                                <tr>
                                    <td colspan="6">
                                        <div class="fw-bold">{{ $__x['name'] }}</div>
                                        @if($__x['sigText'] !== '')<div class="small" style="color:#0f766e;"><i class="fa fa-prescription-bottle-medical"></i> {{ $__x['sigText'] }}</div>@endif
                                    </td>
                                    <td class="fw-bold">{{ $__x['amount'] !== '' ? $__x['amount'] . ' ' . $__x['unit'] : '—' }}</td>
                                    <td>@include('tb._order_vm_cell', ['vm' => $__x, 'editLink' => true])</td>
                                </tr>
                            @endforeach
                        </tbody>
                        @endif
                    </table>
                </div>
                @if($__unified)
                    @php
                        $__vms = collect($__embed['linked'] ?? [])->values()
                            ->merge(collect($o['others'])->pluck('vm')->filter())->merge($__embed['extra'] ?? [])->unique('id');
                        $__sumAll = $__vms->sum('total');
                        $__noPr = $__vms->filter(fn ($v) => $v['hasMed'] && $v['price'] <= 0)->count();
                    @endphp
                    <div class="small px-2 py-1 mb-2 rounded" style="background:#ecfeff;border:1px solid #a5f3fc;">
                        <i class="fas fa-circle-info me-1"></i> รวมค่ายาที่ลงรายการแล้ว (ประมาณการ): <b class="text-success">{{ number_format($__sumAll, 2) }} บาท</b>
                        — คิดจากราคาในคลังยา ยอดจริงยึดตามห้องยา/การเงิน
                        @if($__noPr)<span class="text-danger ms-1">· มียา {{ $__noPr }} รายการที่ยังไม่ตั้งราคา</span>@endif
                    </div>
                @endif
                <template id="tb-ord-other-tpl">
                    @include('tb._order_other_row', ['n' => '__N__', 'ot' => ['name' => '', 'qty' => '', 'sig' => ['freq' => 'OD', 'route' => 'PO', 'unit' => 'เม็ด']]])
                </template>
                @if($__embed)
                    {{-- ชื่อยาอื่นต้องตรงกับคลังยาถึงจะลงรายการยา/คิดเงินได้ — ให้เลือกจากรายการแทนการพิมพ์เอง --}}
                    <datalist id="tb-ord-medlist">
                        @foreach($__embed['medNames'] as $__mn)<option value="{{ $__mn }}"></option>@endforeach
                    </datalist>
                @endif
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="tb-ord-add-other"><i class="fa fa-plus"></i> เพิ่มยาอื่น (Para, Brufen …)</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="tb-ord-b6-on"><i class="fa fa-plus"></i> ให้ B6 ตามสูตร</button>
                    <span class="small text-muted">
                        จำนวนต่อครั้งเติมให้จากเกณฑ์ตามน้ำหนัก (พื้นเหลือง) แก้ได้ · จ่ายทั้งหมด = ครั้งละ × ความถี่ × จำนวนวัน
                        · ช่องว่าง = ไม่จ่ายยาตัวนั้นรอบนี้
                    </span>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex flex-wrap align-items-center gap-2">
            @if($__embed)
                <button type="button" class="btn btn-primary" id="tb-ord-save" {{ $__embed['lock'] ? 'disabled' : '' }}>
                    <i class="fa fa-save"></i> บันทึกการสั่งยา{{ $__embed['registered'] ? ' (รายการยา + ' . $reg->cardName() . ')' : ' (ลงรายการยา)' }}
                </button>
                @if($__embed['boxCount'])
                    <span class="small text-muted">มียาจากกล่องนี้ในรายการยาแล้ว {{ $__embed['boxCount'] }} รายการ — กดบันทึกซ้ำ = แก้ชุดเดิม</span>
                @endif
            @else
                <button class="btn btn-primary"><i class="fa fa-save"></i> บันทึกการสั่งยา</button>
            @endif
            <span id="tb-ord-print" class="d-inline-flex flex-wrap align-items-center gap-2">
            @if($__printRow)
                <span class="small text-muted ms-2">พิมพ์ของ{{ $__printRow->labelForHumans() }}:</span>
                <a href="{{ route('tb.labels', ['id' => $reg->id, 'row' => $__printRow->id]) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="fa fa-tag"></i> พิมพ์สติกเกอร์</a>
                <a href="{{ route('tb.medSheet', $reg->id) }}" target="_blank" class="btn btn-sm btn-outline-success">
                    <i class="fa fa-print"></i> พิมพ์ใบกินยา (มีรูป)</a>
                {{-- ใบสรุปรายการยากลับบ้าน/ส่งต่อ (คลินิกขอ 2569-10-06) — คิดจากรอบสั่งยาเดียวกับสติกเกอร์ --}}
                <a href="{{ route('tb.labels', ['id' => $reg->id, 'row' => $__printRow->id, 'format' => 'summary']) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="fa fa-file-medical"></i> ใบสรุปรายการยา</a>
            @endif
            </span>
            @if($__embed)
                <div id="tb-ord-msg" class="w-100"></div>
            @endif
        </div>
    </div>
@if($__embed)
</div>
@else
</form>
@endif
@php
    $__html = ob_get_clean();
    // หน้าตรวจ: ผูกทุกช่องกับฟอร์มที่ไม่มีอยู่จริง ช่องจะไม่เป็นของฟอร์มหลัก (ดูหัวไฟล์) — รวมแถวใน <template> ด้วย
    echo $__embed ? preg_replace('/<(input|select|textarea|button)(?=[\s>\/])/i', '<$1 form="tb-ord-detached"', $__html) : $__html;
@endphp

@include('tb._order_js')
