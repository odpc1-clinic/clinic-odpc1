{{-- แผง "Lab ในคลินิก" ของคลินิก TB — ติ๊กสั่ง/ถอดการตรวจเลือด (HIV ก่อน) ให้ visit นี้ (คลินิกขอ 2569-10-02 "Lab ในคลินิก มีตรวจ HIV ด้วย")
     ใช้ 2 ที่: popup ในบัตร TB 01 (โหลดด้วย fetch) และหน้าตรวจ TB (visit/form/lab — อยู่ในฟอร์มหลักของหน้าตรวจ)
     ⚠️ ห้ามมี <form>, <script> และห้ามมี name= บนช่อง — ในหน้าตรวจช่องพวกนี้อยู่ในฟอร์มหลัก ถ้ามีชื่อจะถูกส่งไปกับปุ่ม Save ของหน้าตรวจ
     บันทึกด้วย JS (tb/_inhouse_lab_js) -> LabSubController@quickOrder -> LabSubOrder (กติกาเดียวกับหน้าสั่ง Lab เต็ม)
     data-orig = ที่ติ๊กอยู่ตอนวาดแผง — ส่งไปพร้อมที่ติ๊กตอนบันทึก เซิร์ฟเวอร์ทำเฉพาะส่วนต่าง ไม่ลบของที่อีกห้องเพิ่งสั่ง
     ตัวแปร: $visit · $fullLink (true = มีลิงก์ไปหน้าสั่ง Lab เต็ม ใช้ในหน้าตรวจ) --}}
@php
    $__lab   = \App\Models\Lab::where('visit_id', $visit->id)->first();
    $__subs  = $__lab ? \App\Models\LabSub::where('lab_id', $__lab->id)->orderBy('id')->get() : collect();
    $__vis   = $__lab ? $__lab->isResultVisible() : false;
    $__names = \App\Models\LabSub::$methodOption;
    $__price = \App\Models\LabPrice::pluck('price', 'name');
    $__quick = \App\Models\LabSub::$tbQuickMethods;

    // ที่คลินิก TB เคยสั่งจริง (เรียงตามจำนวน) — โชว์ก่อน ที่เหลือซ่อนไว้ใต้ "แสดงทั้งหมด" · HIV อยู่บนสุดเสมอ
    $__used = \Illuminate\Support\Facades\Cache::remember('tb.inhouse.used_methods', 3600, fn () =>
        \Illuminate\Support\Facades\DB::table('lab_subs as s')->join('labs as l', 'l.id', '=', 's.lab_id')
            ->join('visits as v', 'v.id', '=', 'l.visit_id')->where('v.clinic', 2)
            ->groupBy('s.method')->orderByRaw('count(*) desc')->pluck('s.method')->map(fn ($m) => (int) $m)->all());
    $__focus = array_values(array_unique(array_merge([6], array_values(array_intersect($__used, $__quick)))));

    // แถวที่มีอยู่แล้ว แยกตามกุญแจ
    $__byKey = [];
    foreach ($__subs as $s) { $__byKey[\App\Services\LabSubOrder::keyOf($s)][] = $s; }
    $__keyOfMethod = fn ($m) => \App\Services\LabSubOrder::key($m, null, null, null);

    $__rows = [];   // [key ส่งเซิร์ฟเวอร์, ชื่อ, แถวเดิม|null, อยู่ในกลุ่มหลักไหม]
    foreach ($__quick as $m) {
        $__rows[] = [(string) $m, $__names[$m] ?? ('#' . $m), $__byKey[$__keyOfMethod($m)][0] ?? null, in_array($m, $__focus, true), $m];
    }
    foreach ($__subs as $s) {
        if ((int) $s->method === 10 && (int) $s->specimen_from === 0 && trim((string) $s->specimen_from_other) === '') {
            $__rows[] = ['10|' . trim((string) $s->method_other), 'Other: ' . $s->method_other, $s, true, 10];
        }
    }
    $__orig = collect($__rows)->filter(fn ($r) => $r[2])->pluck(0)->values()->all();

    // รายการอื่นที่สั่งจากหน้าสั่ง Lab เต็ม (Gram/Culture/PCR ฯลฯ) — ดูอย่างเดียวที่นี่
    $__shownIds = collect($__rows)->pluck(2)->filter()->pluck('id')->all();
    // EKG ไม่ใช่การตรวจเลือด ไม่โชว์ในแผงนี้ (ผลอยู่ที่ช่อง QTcF สัญญาณชีพ)
    $__others   = $__subs->reject(fn ($s) => in_array($s->id, $__shownIds, true) || (int) $s->method === 16);

    $__res = function ($s) use ($__vis) {
        if (! $s) { return null; }
        // ตรวจข้างเตียง (DTX) พยาบาลลงเอง — ผลโชว์ในช่องกรอกด้านข้างแล้ว ไม่ต้องรอห้อง Lab
        if (\App\Models\LabSub::isPoc($s->method)) { return null; }
        if (trim((string) $s->result) === '') { return ['wait', 'รอผล']; }
        return $__vis ? ['done', $s->result] : ['wait', 'มีผลแล้ว · รอห้อง Lab อนุมัติ'];
    };
@endphp
<div class="js-inh-panel tb-inh-panel" data-no-dirty data-url="{{ route('lab-sub.quick', $visit->id) }}" data-orig="{{ json_encode($__orig, JSON_UNESCAPED_UNICODE) }}">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <div class="fw-bold" style="color:#5b21b6;"><i class="fa fa-flask-vial me-1"></i> Lab ในคลินิก (ตรวจเลือด)</div>
        {{-- แสดงทุกรายการเสมอ (คลินิกขอ 2569-10-22 — รายการน้อย พยาบาลต้องกดหาทุกครั้ง) · ปุ่ม "แสดงทั้งหมด" เอาออกแล้ว --}}
    </div>

    <div class="row g-2">
        @foreach($__rows as [$__key, $__label, $__row, $__isFocus, $__m])
            @php
                $__r      = $__res($__row);
                $__locked = $__row && trim((string) $__row->result) !== '';
                $__poc    = \App\Models\LabSub::$pocMethods[$__m] ?? null;
                $__p      = $__price[$__names[$__m] ?? ''] ?? null;
                $__id     = 'inh-' . $visit->id . '-' . md5($__key);
            @endphp
            <div class="col-md-6">
                <div class="tb-inh-item {{ $__row ? 'is-on' : '' }} {{ (int) $__m === 6 ? 'is-hiv' : '' }}">
                    <div class="form-check mb-0">
                        <input class="form-check-input js-inh-key" type="checkbox" id="{{ $__id }}" value="{{ $__key }}"
                               @if($__row) checked @endif @if($__locked) disabled title="ห้อง Lab ลงผลแล้ว — ถอดไม่ได้" @endif>
                        <label class="form-check-label fw-semibold" for="{{ $__id }}">
                            {{ ltrim($__label, '# ') }}
                            @if((int) $__m === 6)<span class="badge ms-1" style="background:#7c3aed;">HIV</span>@endif
                        </label>
                    </div>
                    <div class="small text-end">
                        @if($__r && $__r[0] === 'done' && (int) $__m === 6)
                            {{-- ผล HIV ปิดไว้ (2569-10-06) — กดดูผ่าน partials/hiv_mask_js ของหน้าแม่ --}}
                            <span class="tb-chip tb-chip-done py-0">
                                @if($__locked)<i class="fa fa-lock me-1"></i>@endif
                                @include('partials.hiv_mask', ['hmPatient' => $visit->patient_id, 'hmKind' => 'sub', 'hmRef' => $__row->id,
                                    'hmVisit' => $visit->id, 'hmWhere' => 'Lab ในคลินิก (TB)'])
                            </span>
                        @elseif($__r)
                            <span class="tb-chip {{ $__r[0] === 'done' ? 'tb-chip-done' : 'tb-chip-wait' }} py-0">
                                @if($__locked)<i class="fa fa-lock me-1"></i>@endif{{ \Illuminate\Support\Str::limit($__r[1], 40) }}
                            </span>
                        @elseif($__poc)
                            {{-- DTX: พยาบาลพิมพ์ผลเองตรงนี้ (กรอกผล = สั่งให้ด้วย) · ไม่มี name= (อยู่ในฟอร์มหลักของหน้าตรวจ) --}}
                            <div class="input-group input-group-sm" style="width:9.5rem;">
                                <input type="number" class="form-control text-center fw-semibold js-inh-poc" data-method="{{ $__m }}"
                                       min="{{ $__poc[1] }}" max="{{ $__poc[2] }}" step="1" inputmode="numeric" placeholder="ผล"
                                       value="{{ $__row ? trim((string) $__row->result) : '' }}" aria-label="ผล {{ ltrim($__label, '# ') }}">
                                <span class="input-group-text">{{ $__poc[0] }}</span>
                            </div>
                            @php $__flag = $__row ? \App\Models\LabSub::pocFlag($__m, $__row->result) : null; @endphp
                            @if($__flag)
                                <div class="fw-semibold" style="color: {{ $__flag[1] === 'danger' ? '#b91c1c' : '#b45309' }};">{{ $__flag[0] }}</div>
                            @endif
                        @elseif($__p !== null && (int) $__m !== 10)
                            <span class="text-muted">{{ number_format((float) $__p, 0) }}.-</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-md-6">
            <div class="tb-inh-item">
                <input type="text" class="form-control form-control-sm js-inh-other" maxlength="255" autocomplete="off"
                       placeholder="อื่น ๆ (พิมพ์ชื่อการตรวจ แล้วกดบันทึก)">
            </div>
        </div>
    </div>

    @if($__others->count())
        <div class="small mt-2">
            <span class="text-muted">สั่งไว้จากหน้าสั่ง Lab เต็ม:</span>
            @foreach($__others as $s)
                @php $__r = $__res($s); @endphp
                <span class="tb-chip {{ $__r[0] === 'done' ? 'tb-chip-done' : 'tb-chip-wait' }} py-0 me-1">
                    {{ ltrim($__names[$s->method] ?? 'Lab', '# ') }}{{ (int) $s->method === 10 && $s->method_other ? ': ' . $s->method_other : '' }}
                    @if($s->specimen_from) ({{ $s->specimenFrom() }}) @endif ·
                    @if($__r[0] === 'done' && (int) $s->method === 6)
                        @include('partials.hiv_mask', ['hmPatient' => $visit->patient_id, 'hmKind' => 'sub', 'hmRef' => $s->id,
                            'hmVisit' => $visit->id, 'hmWhere' => 'Lab ในคลินิก (TB)', 'hmActions' => false])
                    @else
                        {{ $__r[1] }}
                    @endif
                </span>
            @endforeach
        </div>
    @endif

    <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
        <button type="button" class="btn btn-sm js-inh-save js-inh-inline-save" style="background:#6d28d9; color:#fff;">
            <i class="fa fa-save me-1"></i> บันทึก Lab ในคลินิก
        </button>
        <span class="small js-inh-msg" role="status"></span>
        @if(! empty($fullLink))
            <a href="{{ route('lab-sub.config', ['visit' => $visit->id, 'page' => request('page', 1)]) }}" class="small ms-auto">
                Gram / Culture / PCR ฯลฯ → หน้าสั่ง Lab เต็ม
            </a>
        @endif
    </div>
    <div class="small text-muted mt-1">
        ติ๊กแล้วบันทึก = ส่งเข้าห้อง Lab ทันที · ผลลงโดยห้อง Lab และเห็นเมื่อห้อง Lab อนุมัติ · ค่าตรวจคิดที่ห้องยา/การเงินตามราคากลาง
        · <b>DTX</b> เจาะเองแล้วพิมพ์ผลในช่องได้เลย กดบันทึก (ไม่ส่งห้อง Lab)
        · รายการที่มีผลแล้วถอดไม่ได้
    </div>
</div>
