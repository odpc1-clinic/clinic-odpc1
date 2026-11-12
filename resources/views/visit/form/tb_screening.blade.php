{{-- ============================================================
     โซนซักประวัติ/คัดกรองวัณโรค — แสดงเฉพาะคลินิก TB (clinic = 2)
     include อยู่ "ใน" ฟอร์มหลักของหน้าตรวจ ห้ามเปิด <form> ซ้อน (CLAUDE.md ข้อ 1)
     ช่องทั้งหมดใช้ชื่อ tb[...] แล้ว VisitController@update ส่งต่อให้ TbScreeningController
     น้ำหนัก/ส่วนสูง/สัญญาณชีพ ใช้ช่องเดิมของ visit ไม่เก็บซ้ำ
     ============================================================ --}}
@php
    $__sc = \App\Models\TbScreening::where('visit_id', $visit->id ?? 0)->first() ?: new \App\Models\TbScreening;
    $__labs = (array) ($__sc->labs_ordered ?? []);

    // ยังไม่เคยกรอกใบของ visit นี้ -> ดึงข้อมูลที่ไม่เปลี่ยนทุกครั้งมาจากครั้งก่อนให้เลย
    // (โรคประจำตัว · ประวัติสัมผัส · ผู้ป่วยต้นทาง · ผู้สัมผัสร่วมบ้าน)
    // อาการ/ผลตรวจ/Impression ไม่ยกมา เพราะเป็นของการมาครั้งนั้น
    // "เริ่มกรอกแล้ว" = ใบนี้เคยบันทึกผ่านฟอร์มซักประวัติ — ใบที่เกิดจากสั่งแล็บในบัตร TB 01 / ผลเอกซเรย์ / รหัสโรค
    // ยังนับว่าไม่ได้เริ่ม จะได้ยังยกข้อมูลครั้งก่อนและเดาประเภทการมาได้ (2569-10-02)
    $__started = $__sc->hasFormData();
    $__prev = ! $__started && isset($visit)
        ? \App\Models\TbScreening::latestForPatient($visit->patient_id, $visit->id)
        : null;
    $__pf = $__started ? $__sc : ($__prev ?: $__sc);   // ใช้กรอกค่าเริ่มต้นของ block ที่ยกยอด

    // ประวัติสัมผัส / เคยรักษาวัณโรค / โรคประจำตัว (ARV·DM): ใบนี้ยังว่างเรื่องไหน ยกจาก visit ล่าสุดที่กรอกเรื่องนั้นไว้มาโชว์
    // แก้ได้ · ไม่แก้อะไรในใบคัดกรอง = ไม่บันทึก (ระบบเซฟเฉพาะช่องที่แตะ) (คลินิกขอ 2569-10-27 — F/U ทุกครั้งขึ้น "ไม่ระบุ")
    $__hxFrom = [];
    if (isset($visit) && $visit->id) {
        $__hxGroups = [
            'contact'  => [fn ($s) => $s->contact_tb !== null || trim((string) $s->contact_tb_detail) !== '',
                           ['contact_tb', 'contact_tb_detail', 'index_name', 'index_age', 'index_id_card', 'index_diagnosis', 'index_dx_hospital', 'index_treat_hospital', 'index_registration_id']],
            'previous' => [fn ($s) => $s->previous_tb !== null || trim((string) $s->previous_tb_detail) !== '',
                           ['previous_tb', 'previous_tb_detail']],
            'disease'  => [fn ($s) => (int) $s->has_arv === 1 || (int) $s->has_dm === 1 || trim((string) $s->arv_detail) !== '',
                           ['has_arv', 'arv_detail', 'has_dm', 'dm_hba1c']],
        ];
        $__hxNeed = array_filter($__hxGroups, fn ($g) => ! $g[0]($__pf));
        if ($__hxNeed) {
            $__hxList = \App\Models\TbScreening::with('visit:id,date')->where('patient_id', $visit->patient_id)
                ->where('visit_id', '<>', $visit->id)->orderByDesc('id')->limit(40)->get()
                ->sortByDesc(fn ($s) => ($s->visit ? (string) $s->visit->getRawOriginal('date') : '') . sprintf('%010d', $s->id))->values();
            $__pf = clone $__pf;   // ไม่แตะตัวจริงของใบนี้
            foreach ($__hxNeed as $__g => [$__has, $__cols]) {
                $__src = $__hxList->first(fn ($s) => $__has($s));
                if (! $__src) { continue; }
                foreach ($__cols as $__c) { $__pf->setAttribute($__c, $__src->getAttribute($__c)); }
                $__hxFrom[$__g] = $__src->visit ? \App\Utillity::db2thDate($__src->visit->getRawOriginal('date')) : 'ครั้งก่อน';
            }
        }
    }
    $__hxNote = fn ($g) => isset($__hxFrom[$g])
        ? '<div class="small mt-1" style="color:#0f766e;"><i class="fa fa-clock-rotate-left me-1"></i>ยกมาจาก visit ' . e($__hxFrom[$g]) . ' — แก้ได้ · บันทึกเมื่อกดบันทึกหน้า</div>'
        : '';

    // บัตรวัณโรคของคนไข้ — ใช้ทั้งเดาประเภทการมา และปุ่ม "เปิดบัตร TB 01" ตอนเลือก F/U
    $__regs     = \App\Models\TbRegistration::where('patient_id', $visit->patient_id)->orderByDesc('id')->get();
    // visit ย้อนหลัง = บัตรที่ครอบวันนั้น แม้วันนี้ปิดบัตรแล้ว (2569-10-27)
    $__tbActive = \App\Models\TbRegistration::forVisit($visit);

    // มาแบบไหน — ครั้งแรก/walk-in ต้องให้คะแนนอาการ ส่วน F/U ไม่ต้องให้คะแนนซ้ำ
    // เดาให้จากประวัติ: เคยมีใบคัดกรองหรือมีบัตรวัณโรคแล้ว = F/U (คลินิกขอ 2569-09-29)
    // ต้องคิดไว้ตรงนี้ ก่อนทุก @section — ส่วนคัดกรองหลายส่วนซ่อนตามค่านี้ตั้งแต่โหลดหน้า
    $__guessFu = $__started ? (int) $__sc->visit_type : (($__prev || $__regs->count()) ? \App\Models\TbScreening::VISIT_FU : \App\Models\TbScreening::VISIT_FIRST);
    $__isFu    = $__guessFu === \App\Models\TbScreening::VISIT_FU;

    // ผู้สัมผัสของแฟ้มนี้ตามมาตรวจครบหรือยัง (แสดงตอน F/U)
    $__allMembers = \App\Models\TbHouseholdMember::whereIn('tb_screening_id',
            \App\Models\TbScreening::where('patient_id', $visit->patient_id)->pluck('id'))
        ->get()->filter(fn ($m) => trim((string) $m->name) !== '')
        ->unique(fn ($m) => trim($m->name));
    $__came   = $__allMembers->filter(fn ($m) => trim((string) $m->hn) !== '')->count();
    $__missed = $__allMembers->count() - $__came;
@endphp

<style>
    [data-tabtoggle][href="#tbScreening"], .tb-part-btn { background:#b45309 !important; color:#fff !important; }
    [data-tabtoggle][href="#tbScreening"] font, .tb-part-btn font { color:#fff !important; }
    .tb-score-box { border:2px solid #f0c088; background:#fffbea; border-radius:.5rem; }
</style>

{{-- ============================================================
     ใบคัดกรองวัณโรคถูกแบ่งเป็นหลายส่วน แล้วหน้าตรวจ (visit/form/index) วางแต่ละส่วนตามลำดับงานจริงของคลินิก (2569-10-01):
       การมาครั้งนี้ → โรคประจำตัว/สัมผัส/เคยป่วย (2569-10-02) → ซักประวัติ/คัดกรอง → สัญญาณชีพ → ตรวจร่างกาย → เอกซเรย์
       → Lab + ส่งตรวจ → วินิจฉัย → Impression/ICD-10 → การรักษา → Plan
     เลือก F/U = ซ่อนส่วนที่มีคลาส js-tb-screen-part (ซ่อนเฉย ๆ ค่ายังส่งไปพร้อมฟอร์ม)
     ไฟล์นี้ "ไม่พิมพ์อะไรออกมาเอง" — นิยามเป็น @section แล้ว index ใช้ @yield('tbp_...') ตรงตำแหน่งที่ต้องการ
     ต้อง include ไฟล์นี้ก่อน @yield ตัวแรกเสมอ · สคริปต์อยู่ใน tbp_scripts ต้อง yield หลังทุกส่วน (สคริปต์หาช่องด้วย id ตอนรัน)
     ทุกช่องยังอยู่ในฟอร์มหลักของหน้าตรวจ ชื่อ tb[...] เดิม — การบันทึกไม่เปลี่ยน
     ============================================================ --}}

@section('tbp_visit_type')
{{-- การมาครั้งนี้ — ย้ายขึ้นมาก่อนซักประวัติ (คลินิกขอ 2569-10-02) เลือก F/U แล้วงานไปต่อที่บัตร TB 01
     ชื่อช่อง tb[visit_type] เดิม บันทึกพร้อมฟอร์มหลักเหมือนเดิม --}}
<div class="card card-body mb-2 mt-2" id="tb-visit-type-box" style="border-color:#f0c088;">
    <div class="row g-2">
        <div class="col-12">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="fw-bold" style="font-size:1.1rem;">การมาครั้งนี้</span>
                @foreach(\App\Models\TbScreening::$visitTypeOption as $k => $label)
                    <div class="form-check">
                        <input class="form-check-input js-tb-visit-type" type="radio" name="tb[visit_type]"
                               id="tb_vt_{{ $k }}" value="{{ $k }}" {{ $__guessFu === $k ? 'checked' : '' }}>
                        <label class="form-check-label" for="tb_vt_{{ $k }}">{{ $label }}</label>
                    </div>
                @endforeach
                <span class="text-muted small">ครั้งแรก/Walk-in = กรอกใบคัดกรองด้านล่าง · F/U = ไปบันทึกต่อที่บัตร TB 01</span>
            </div>
        </div>

        {{-- มาด้วยเรื่อง (ผู้ป่วยใหม่) — รายงานประจำเดือนข้อ 1.1.1–1.1.5 (คลินิกขอ 2569-11-04) · โผล่เฉพาะคัดกรองครั้งแรก
             ใบเก่าที่ยังไม่เลือก: ติ๊กผู้สัมผัสไว้ = เลือก "ผู้สัมผัส" ให้ก่อน (ตรงกับที่รายงานเคยนับ) --}}
        @if(\App\Models\TbScreening::comeReasonReady())
            @php $__come = $__sc->come_reason ?: ((int) $__sc->contact_tb === 1 ? 2 : null); @endphp
            <div class="col-12 js-tb-score-zone {{ $__isFu ? 'd-none' : '' }}">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <span class="fw-bold">มาด้วยเรื่อง</span>
                    @foreach(\App\Models\TbScreening::$comeReasonOption as $k => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tb[come_reason]" id="tb_come_{{ $k }}" value="{{ $k }}"
                                   {{ (int) $__come === $k ? 'checked' : '' }}>
                            <label class="form-check-label" for="tb_come_{{ $k }}">{{ $label }}</label>
                        </div>
                    @endforeach
                    <span class="text-muted small">ใช้ในรายงานประจำเดือน (ชนิดผู้ป่วยใหม่)</span>
                </div>
            </div>
        @endif

        {{-- เคสเก่าจากทะเบียนกระดาษ (คลินิกขอ 2569-11-05) — ระบบแยกใหม่/เก่าจาก visit ในระบบ คนที่เคยมาตอนลงกระดาษจะถูกนับเป็นใหม่ผิด
             โชว์เฉพาะ visit คลินิกวัณโรคแรกในระบบของคนนี้ (หรือติ๊กไว้แล้ว) · ช่องไม่มี name — บันทึกด้วย fetch ทันที (ตัวจัดการท้ายไฟล์นี้) --}}
        @if(\App\Models\Patient::tbPaperOldReady() && isset($visit) && $visit->patient_id)
            @php
                $__paperOld = (bool) \App\Models\Patient::whereKey($visit->patient_id)->value('tb_paper_old');
                $__firstTb = ! \App\Models\Visit::where('patient_id', $visit->patient_id)->where('clinic', 2)
                    ->where('date', '<', $visit->getRawOriginal('date'))->exists();
            @endphp
            @if($__paperOld || $__firstTb)
                <div class="col-12" data-no-dirty>
                    <div class="form-check d-inline-block px-2 py-1 rounded ps-4" style="background:#f1f5f9;">
                        <input class="form-check-input" type="checkbox" id="tb-paper-old" form="tb-paper-old-detached"
                               data-url="{{ route('patient.tbPaperOld', $visit->patient_id) }}" {{ $__paperOld ? 'checked' : '' }}>
                        <label class="form-check-label" for="tb-paper-old">
                            <b>เคสเก่าจากทะเบียนกระดาษ</b> — เคยมาคลินิกวัณโรคก่อนใช้ระบบ (รายงานนับเป็นผู้ป่วยเก่า)
                        </label>
                        <span class="small ms-2" id="tb-paper-old-msg"></span>
                    </div>
                </div>
            @endif
        @endif
{{-- เคสเก่าจากทะเบียนกระดาษ: ติ๊กแล้วบันทึกทันที (ช่องไม่อยู่ในฟอร์มหลัก) --}}
<script>
(function () {
    var cb = document.getElementById('tb-paper-old');
    if (!cb || cb.dataset.init) return;
    cb.dataset.init = '1';
    cb.addEventListener('change', function () {
        var msg = document.getElementById('tb-paper-old-msg');
        var fd = new FormData();
        fd.append('_token', '{{ csrf_token() }}');
        fd.append('on', cb.checked ? '1' : '0');
        cb.disabled = true;
        fetch(cb.dataset.url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                cb.disabled = false;
                if (!j.ok) throw new Error(j.message || '');
                cb.checked = !!j.on;
                msg.className = 'small ms-2 text-success';
                msg.textContent = j.on ? '✓ บันทึกแล้ว: นับเป็นผู้ป่วยเก่า' : '✓ เอาออกแล้ว';
            })
            .catch(function (e) {
                cb.disabled = false; cb.checked = !cb.checked;
                msg.className = 'small ms-2 text-danger';
                msg.textContent = 'บันทึกไม่สำเร็จ ' + (e && e.message ? '(' + e.message + ')' : '') + ' — ลองอีกครั้ง';
            });
    });
})();
</script>

        {{-- F/U = คนไข้ที่ขึ้นทะเบียนแล้ว ผลตรวจ/ขนาดยา/DOT บันทึกที่บัตร ใบคัดกรองไม่ต้องกรอกซ้ำ
             ส่วนคัดกรองแค่ซ่อนไว้ (ค่าเดิมยังอยู่และยังถูกส่งไปพร้อมฟอร์ม) กดแสดงกลับมาได้ --}}
        <div class="col-12 js-tb-fu-zone {{ $__isFu ? '' : 'd-none' }}">
            <div class="d-flex flex-wrap align-items-center gap-2 p-2 rounded" style="background:#fff7ed; border:2px solid #b45309;">
                <div class="fw-bold" style="color:#b45309; font-size:1.1rem;">
                    <i class="fa-solid fa-route me-1"></i> เคสติดตามการรักษา (F/U) — บันทึกต่อที่บัตร TB 01
                </div>
                {{-- สรุปบัตรย้ายมาจากแถบส้มด้านบนที่เอาออก (ซ้ำกัน) — เห็นเลขทะเบียน/เดือนที่/สูตร ก่อนกดเปิดบัตร --}}
                @if($__tbActive)
                    <span class="badge bg-light text-dark border">{{ $__tbActive->typeName() }}</span>
                    @if($__tbActive->treatment_start_date)
                        <span class="badge bg-light text-dark border">เดือนที่ {{ $__tbActive->monthsOnTreatment() }}</span>
                    @endif
                    @if($__tbActive->currentRegimenText())
                        <span class="badge bg-light text-dark border">{{ $__tbActive->currentRegimenText() }}</span>
                    @endif
                @endif
                {{-- เปิด/ปิด DOT ย้ายไปไว้ใต้สั่งยา (คลินิกขอ 2569-10-14) — visit/form/medicine --}}
                @if($__tbActive)
                    <a href="{{ route('tb.show', ['id' => $__tbActive->id, 'from' => 'visit', 'visit' => $visit->id, 'page' => request('page', 1)]) }}" target="_blank"
                       class="btn btn-lg ms-auto" style="background:{{ $__tbActive->cardColor() }}; color:#fff;">
                        <i class="fa fa-id-card me-1"></i> เปิด{{ $__tbActive->cardName() }} ({{ $__tbActive->tb_no ?: 'ยังไม่มีเลขทะเบียน' }})
                    </a>
                @elseif($__pfCard = \App\Services\TbPostFollowup::activeCardFor((int) $visit->patient_id))
                    {{-- จบการรักษาแล้ว มาตามนัดติดตามหลังจบ (X-ray) — visit ธรรมดา ไม่ต้องเปิดบัตรใหม่ (คลินิกขอ 2569-10-27) --}}
                    @php $__pfNext = collect(\App\Services\TbPostFollowup::plan($__pfCard))->first(fn ($p) => in_array($p['status'], ['booked', 'due', 'upcoming'], true)); @endphp
                    <span class="badge" style="background:#ccfbf1;color:#0f766e;border:1px solid #5eead4;">
                        ติดตามหลังรักษา · {{ $__pfCard->outcomeName() }}{{ $__pfNext ? ' · ' . $__pfNext['label'] . ' ครบ ' . \App\Utillity::db2thDate($__pfNext['due']) : '' }}
                    </span>
                    <a href="{{ route('tb.show', ['id' => $__pfCard->id, 'from' => 'visit', 'visit' => $visit->id, 'page' => request('page', 1)]) }}" target="_blank"
                       class="btn ms-auto" style="background:#0f766e; color:#fff;">
                        <i class="fa fa-id-card me-1"></i> ดู{{ $__pfCard->cardName() }} ({{ $__pfCard->tb_no ?: 'ไม่มีเลขทะเบียน' }})
                    </a>
                @else
                    <span class="text-danger fw-bold">คนไข้รายนี้ยังไม่มีบัตร TB 01 / บัตรวัณโรคแฝง ที่กำลังรักษาอยู่</span>
                    <a href="{{ route('tb.create', ['patient_id' => $visit->patient_id]) }}" target="_blank"
                       class="btn btn-outline-dark ms-auto">
                        <i class="fa fa-plus me-1"></i> เปิดบัตร TB 01 / LTBI ใหม่
                    </a>
                @endif
                {{-- บัตรเดิมที่จำหน่ายแล้ว — ไม่ผูกกับ visit นี้ แต่เปิดดูได้ตลอด (คลินิกขอ 2569-10-27) --}}
                @php $__tbOld = $__regs->filter(fn ($r) => ! $__tbActive || $r->id !== $__tbActive->id); @endphp
                @if($__tbOld->count())
                    <div class="w-100 small text-muted">
                        <i class="fa fa-folder-open me-1"></i> บัตรเดิมของคนไข้ (เปิดดูได้):
                        @foreach($__tbOld as $__o)
                            <a href="{{ route('tb.show', ['id' => $__o->id, 'from' => 'visit', 'visit' => $visit->id, 'page' => request('page', 1)]) }}" target="_blank"
                               class="badge text-decoration-none ms-1" style="background:{{ $__o->cardColor() }};">
                                {{ $__o->cardName() }} {{ $__o->tb_no ?: '#' . $__o->id }} · {{ $__o->outcomeName() }}{{ $__o->outcome_date ? ' ' . \App\Utillity::db2thDate($__o->outcome_date) : '' }}
                            </a>
                        @endforeach
                    </div>
                @endif
                {{-- F/U ไม่ซ่อนซักประวัติ/ตรวจร่างกาย/X-ray/Impression แล้ว — ต้องบันทึกทุกครั้งที่มา ซ่อนแค่ "คะแนนคัดกรองอาการ" (คลินิกขอ 2569-10-08) --}}
            </div>
        </div>

        {{-- ผู้สัมผัสของแฟ้มนี้ตามมาตรวจครบหรือยัง — ใช้ตอนคนไข้ TB มาติดตาม --}}
        <div class="col-12 js-tb-fu-zone {{ $__isFu ? '' : 'd-none' }}">
            <div class="alert {{ $__missed > 0 ? 'alert-warning' : 'alert-success' }} py-2 px-3 mb-0">
                <i class="fa fa-people-group me-1"></i>
                @if($__allMembers->isEmpty())
                    แฟ้มนี้ยังไม่ได้บันทึกรายชื่อผู้สัมผัสร่วมบ้าน
                @else
                    ผู้สัมผัสร่วมบ้านในแฟ้มนี้ <b>{{ $__allMembers->count() }}</b> คน ·
                    มาตรวจแล้ว <b>{{ $__came }}</b> คน ·
                    <span class="{{ $__missed > 0 ? 'text-danger fw-bold' : '' }}">ยังไม่มา {{ $__missed }} คน</span>
                    @if($__missed > 0)
                        <div class="small">
                            ยังไม่มา:
                            {{ $__allMembers->filter(fn ($m) => trim((string) $m->hn) === '')->pluck('name')->implode(' · ') }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('tbp_background')
{{-- โรคประจำตัว · ประวัติสัมผัส · เคยป่วย — ย้ายออกมาไว้ต่อจาก "การมาครั้งนี้" (คลินิกขอ 2569-10-02)
     ใช้ทั้งคนมาคัดกรองและคนมา F/U จึงไม่ซ่อนตามส่วนคัดกรอง · Form 2 ตามมาด้วยเพราะผูกกับช่องประวัติสัมผัส --}}
<div class="card card-body mb-2" id="tb-background-box">
    @if($__prev)
        <div class="alert alert-info py-2 px-3">
            <i class="fa fa-arrow-down me-1"></i>
            ดึง <b>โรคประจำตัว · ประวัติสัมผัส · ข้อมูลผู้สัมผัส</b> มาจากการมาครั้งก่อน
            @if(optional($__prev->visit)->date) (วันที่ {{ \App\Utillity::db2thDate($__prev->visit->date) }}) @endif
            ให้แล้ว — ตรวจทาน แก้ไข หรือเพิ่มรายการใหม่ได้ แล้วกด Save
            <div class="small">อาการ ผลตรวจ และ Impression ไม่ได้ดึงมา เพราะเป็นของการมาครั้งนี้</div>
        </div>
    @endif
    <div class="row g-3">
    <div class="col-md-4">
        <label class="form-label fw-bold">โรคประจำตัว</label>
        {!! $__hxNote('disease') !!}
        {{-- สองโรคนี้แยกช่องเพราะมีผลกับการรักษาวัณโรคโดยตรง (ตามใบ Form 1 Smart Clinic) --}}
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="tb_has_arv" name="tb[has_arv]" value="1" {{ $__pf->has_arv ? 'checked' : '' }}>
            <label class="form-check-label" for="tb_has_arv">ได้รับยา ARV</label>
        </div>
        <input type="text" name="tb[arv_detail]" class="form-control form-control-sm mb-1"
               value="{{ $__pf->arv_detail }}" placeholder="ระบุสูตรยา">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="tb_has_dm" name="tb[has_dm]" value="1" {{ $__pf->has_dm ? 'checked' : '' }}>
            <label class="form-check-label" for="tb_has_dm">เบาหวาน (DM)</label>
        </div>
        <select name="tb[dm_hba1c]" class="form-select form-select-sm mb-1">
            <option value="">— ระดับ HbA1c —</option>
            @foreach(\App\Models\TbScreening::$dmHba1cOption as $k => $label)
                <option value="{{ $k }}" {{ (int) $__pf->dm_hba1c === $k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        {{-- โรคประจำตัวอื่น ๆ / ปฏิเสธ ใช้ช่อง "โรคประจำตัว" ของทะเบียนคนไข้หลักด้านบนช่องเดียว
             (คลินิกขอ 2569-09-29) เดิมมีช่องแยกของวัณโรคซ้ำอีกช่อง กรอกสองที่แล้วไม่ตรงกัน
             ช่องหลักขึ้นแถบแดงบนหัวหน้าจอทุก visit ทุกคลินิกอยู่แล้ว ซึ่งเป็นสิ่งที่คลินิกต้องการ
             ARV กับเบาหวานยังแยกไว้ตรงนี้ เพราะเป็นข้อมูลเฉพาะที่มีผลกับการรักษาวัณโรคโดยตรง --}}
        @php $__congen = trim((string) optional($visit->patient2)->congenitaldisease); @endphp
        <div class="small border rounded px-2 py-1" style="background:#fff5f5;">
            <span class="text-muted">โรคประจำตัวอื่น ๆ:</span>
            <b class="text-danger" id="tb-congen-mirror">{{ $__congen !== '' ? $__congen : 'ยังไม่ได้กรอก' }}</b>
            <div>
                <a href="#congenitaldisease" class="small" id="tb-congen-goto">แก้ที่ช่อง "โรคประจำตัว" (สีแดง) ด้านบน</a>
                <span class="text-muted">· แสดงทุกครั้งที่มา</span>
            </div>
        </div>
        <script>
        (function () {
            // สะท้อนค่าช่องหลักมาโชว์ตรงนี้สด ๆ — พิมพ์ด้านบนแล้วตรงนี้เปลี่ยนตาม
            var src = document.getElementById('congenitaldisease');
            var out = document.getElementById('tb-congen-mirror');
            var go  = document.getElementById('tb-congen-goto');
            if (src && out) {
                src.addEventListener('input', function () {
                    out.textContent = src.value.trim() !== '' ? src.value.trim() : 'ยังไม่ได้กรอก';
                });
            }
            if (src && go) {
                go.addEventListener('click', function (e) {
                    e.preventDefault();
                    src.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    src.focus();
                });
            }
        })();
        </script>
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold">ประวัติสัมผัสผู้ป่วยวัณโรค</label>
        <select name="tb[contact_tb]" class="form-select form-select-sm mb-1">
            <option value="">— ไม่ระบุ —</option>
            <option value="1" {{ $__pf->contact_tb === 1 ? 'selected' : '' }}>มี</option>
            <option value="0" {{ $__pf->contact_tb === 0 ? 'selected' : '' }}>ไม่มี</option>
            {{-- ปฏิเสธ = คนไข้ไม่ยอมบอก/ปฏิเสธการให้ข้อมูล ต่างจาก "ไม่มี" (คลินิกขอ 2569-10-09) --}}
            <option value="2" {{ $__pf->contact_tb === 2 ? 'selected' : '' }}>ปฏิเสธ</option>
        </select>
        <input type="text" name="tb[contact_tb_detail]" class="form-control form-control-sm"
               value="{{ $__pf->contact_tb_detail }}" placeholder="สัมผัสกับใคร เมื่อไหร่">
        {!! $__hxNote('contact') !!}
        <div class="form-text">ตอบ "มี" แล้วจะมีช่องข้อมูลผู้สัมผัส (Form 2) ให้กรอกด้านล่าง</div>
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold">เคยป่วย/กำลังรักษาวัณโรค</label>
        <select name="tb[previous_tb]" class="form-select form-select-sm mb-1">
            <option value="">— ไม่ระบุ —</option>
            <option value="1" {{ $__pf->previous_tb === 1 ? 'selected' : '' }}>มี</option>
            <option value="0" {{ $__pf->previous_tb === 0 ? 'selected' : '' }}>ไม่มี</option>
            {{-- ปฏิเสธ = คนไข้ไม่ยอมบอก/ปฏิเสธการให้ข้อมูล ต่างจาก "ไม่มี" (คลินิกขอ 2569-10-09) --}}
            <option value="2" {{ $__pf->previous_tb === 2 ? 'selected' : '' }}>ปฏิเสธ</option>
        </select>
        <input type="text" name="tb[previous_tb_detail]" class="form-control form-control-sm"
               value="{{ $__pf->previous_tb_detail }}" placeholder="รักษาที่ไหน เมื่อไหร่">
        {!! $__hxNote('previous') !!}
    </div>

    {{-- ====== Form 2 ข้อ 2, 3, 5 — ผู้สัมผัสผู้ป่วยวัณโรค ======
         โผล่เมื่อระบุว่า "มีประวัติสัมผัสผู้ป่วยวัณโรค" (ข้อ 4 อาการ/คะแนน ใช้ของส่วนซักประวัติ ไม่ทำซ้ำ) --}}
    <div class="col-12 {{ $__pf->contact_tb === 1 ? '' : 'd-none' }}" id="tb-form2">
        <div class="border rounded p-3" style="background:#fffbea; border-color:#f0c088 !important;">
            <div class="fw-bold mb-2" style="color:#b45309;">
                <i class="fa fa-people-arrows me-1"></i> ข้อมูลผู้สัมผัสผู้ป่วยวัณโรค (Form 2)
            </div>

            {{-- ข้อ 2: ผู้ป่วยต้นทาง --}}
            <div class="fw-bold small mb-1">2. ข้อมูลผู้ป่วยวัณโรคที่สัมผัส (index case)</div>
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <input type="text" name="tb[index_name]" class="form-control form-control-sm"
                           value="{{ $__pf->index_name }}" placeholder="ชื่อ-สกุล ผู้ป่วยวัณโรค">
                </div>
                <div class="col-md-2">
                    <div class="input-group input-group-sm">
                        <input type="number" min="0" max="120" name="tb[index_age]" class="form-control"
                               value="{{ $__pf->index_age }}" placeholder="อายุ">
                        <span class="input-group-text">ปี</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <input type="text" name="tb[index_id_card]" class="form-control form-control-sm"
                           value="{{ $__pf->index_id_card }}" placeholder="เลขบัตรประชาชน" maxlength="20">
                </div>
                <div class="col-md-3">
                    <input type="text" name="tb[index_diagnosis]" class="form-control form-control-sm"
                           list="tb-index-dx" value="{{ $__pf->index_diagnosis }}" placeholder="วินิจฉัยเป็นวัณโรคที่...">
                    <datalist id="tb-index-dx">
                        @foreach(\App\Models\TbScreening::$indexDiagnosisOption as $__d)<option value="{{ $__d }}">@endforeach
                    </datalist>
                </div>
                @if(\App\Services\TbContacts::ready())
                    {{-- ผูกบัตร TB ของ index ที่รักษาที่นี่ (2569-10-22) — บัตร index จะเห็นผู้สัมผัสรายนี้ ไม่ว่าจะติดเชื้อหรือไม่ --}}
                    @php $__idx = $__pf->index_registration_id ? \App\Models\TbRegistration::with('patient')->find($__pf->index_registration_id) : null; @endphp
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fa fa-link"></i></span>
                            <input type="text" name="tb[index_ref]" class="form-control" maxlength="30"
                                   value="{{ $__idx ? ($__idx->tb_no ?: optional($__idx->patient)->code) : '' }}"
                                   placeholder="ผูกบัตร TB ของผู้ป่วย: HN หรือเลขทะเบียน (ถ้ารักษาที่นี่)">
                        </div>
                        @if($__idx)
                            <a href="{{ route('tb.show', $__idx->id) }}" target="_blank" class="small"><i class="fa fa-up-right-from-square me-1"></i>{{ \App\Services\TbContacts::label($__idx) }}</a>
                        @else
                            <div class="small text-muted">ว่าง = ระบบลองจับจากเลขบัตรประชาชนให้เอง</div>
                        @endif
                    </div>
                @endif
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">วินิจฉัยจาก</span>
                        <input type="text" name="tb[index_dx_hospital]" class="form-control" value="{{ $__pf->index_dx_hospital }}" placeholder="รพ.">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">ปัจจุบันรักษาที่</span>
                        <input type="text" name="tb[index_treat_hospital]" class="form-control" value="{{ $__pf->index_treat_hospital }}" placeholder="รพ.">
                    </div>
                </div>
            </div>

            {{-- ข้อ 3: ลักษณะการสัมผัส --}}
            <div class="fw-bold small mb-1">3. ลักษณะการสัมผัส</div>
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="ct_same_house" name="tb[ct_same_house]" value="1" {{ $__pf->ct_same_house ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="ct_same_house">3.1 อยู่บ้านเดียวกันในช่วง 3 เดือนที่ผ่านมา</label>
                    </div>
                    <div class="ms-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ct_same_room" name="tb[ct_same_room]" value="1" {{ $__pf->ct_same_room ? 'checked' : '' }}>
                            <label class="form-check-label" for="ct_same_room">นอนห้องเดียวกัน</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ct_aircon" name="tb[ct_aircon]" value="1" {{ $__pf->ct_aircon ? 'checked' : '' }}>
                            <label class="form-check-label" for="ct_aircon">บ้านเป็นห้องแอร์ และใช้แอร์ร่วมกัน</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="ct_close" name="tb[ct_close]" value="1" {{ $__pf->ct_close ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="ct_close">3.2 อยู่ใกล้ชิดกัน (ไม่ได้อยู่บ้านเดียวกัน)</label>
                    </div>
                    <div class="ms-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ct_close_8h" name="tb[ct_close_8h]" value="1" {{ $__pf->ct_close_8h ? 'checked' : '' }}>
                            <label class="form-check-label" for="ct_close_8h">มากกว่า 8 ชั่วโมงใน 1 วัน</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ct_close_120h" name="tb[ct_close_120h]" value="1" {{ $__pf->ct_close_120h ? 'checked' : '' }}>
                            <label class="form-check-label" for="ct_close_120h">ตั้งแต่ 120 ชั่วโมงใน 1 เดือน</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    {{-- เสี่ยงต่ำ = เจอกันบ้าง แต่ไม่เข้าเกณฑ์ 3.1/3.2 — แยกช่องเพราะแนวทางติดตามคนละแบบ --}}
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="ct_low_risk" name="tb[ct_low_risk]" value="1" {{ $__pf->ct_low_risk ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="ct_low_risk">3.3 ผู้สัมผัสความเสี่ยงต่ำ</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">3.4 อื่นๆ</span>
                        <input type="text" name="tb[ct_other]" class="form-control" value="{{ $__pf->ct_other }}">
                    </div>
                </div>
            </div>

            {{-- ข้อ 5: สมาชิกที่อยู่บ้านเดียวกัน --}}
            <div class="fw-bold small mb-1">5. สมาชิกที่อยู่บ้านเดียวกันกับผู้ป่วยวัณโรคในช่วง 3 เดือนที่ผ่านมา</div>
            @php $__members = $__pf->exists ? $__pf->householdMembers : collect(); @endphp
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-1" id="tb-member-table" style="background:#fff;">
                    <thead class="table-light">
                        <tr>
                            <th style="width:2.5rem">#</th>
                            <th style="min-width:9rem">ชื่อ-สกุล</th>
                            <th style="width:7rem">HN</th>
                            <th style="width:4.5rem">อายุ</th>
                            <th style="width:7rem">ความสัมพันธ์</th>
                            <th style="width:8rem">อาการ</th>
                            <th style="width:7rem">ผล X-ray</th>
                            <th style="width:7rem">ผลเสมหะ</th>
                            <th style="width:7rem">IGRA / Skin test</th>
                            <th style="width:8rem">สถานะการรักษา</th>
                            <th style="width:8rem">โรคประจำตัว</th>
                            <th style="width:8rem">วันที่คัดกรอง</th>
                            <th style="width:8rem">หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                    @php $__rows = $__members->count() ? $__members : collect([null, null, null]); @endphp
                    @foreach($__rows as $__i => $__mb)
                        <tr>
                            <td class="text-center text-muted">{{ $__i + 1 }}
                                {{-- แถวที่ยกมาจากครั้งก่อนไม่ส่ง id เพื่อให้สร้างเป็นแถวของ visit นี้ ไม่ไปแก้ของครั้งก่อน --}}
                                <input type="hidden" name="tb[members][{{ $__i }}][id]" value="{{ $__started ? ($__mb->id ?? '') : '' }}"></td>
                            <td><input type="text" name="tb[members][{{ $__i }}][name]" class="form-control form-control-sm" value="{{ $__mb->name ?? '' }}"></td>
                            <td>
                                <input type="text" name="tb[members][{{ $__i }}][hn]" class="form-control form-control-sm js-member-hn"
                                       value="{{ $__mb->hn ?? '' }}" placeholder="ยังไม่มา">
                                @if($__mb && $__mb->patient_id)
                                    <a href="{{ route('patient.edit', $__mb->patient_id) }}" target="_blank" class="small">ดูประวัติ</a>
                                @endif
                            </td>
                            <td><input type="number" min="0" max="120" name="tb[members][{{ $__i }}][age]" class="form-control form-control-sm" value="{{ $__mb->age ?? '' }}"></td>
                            <td><input type="text" name="tb[members][{{ $__i }}][relation]" class="form-control form-control-sm" value="{{ $__mb->relation ?? '' }}" placeholder="เช่น บุตร"></td>
                            <td><input type="text" name="tb[members][{{ $__i }}][symptoms]" class="form-control form-control-sm" value="{{ $__mb->symptoms ?? '' }}" placeholder="ไอ/ไม่มี"></td>
                            <td><input type="text" name="tb[members][{{ $__i }}][cxr_result]" class="form-control form-control-sm" value="{{ $__mb->cxr_result ?? '' }}"></td>
                            <td><input type="text" name="tb[members][{{ $__i }}][sputum_result]" class="form-control form-control-sm" value="{{ $__mb->sputum_result ?? '' }}"></td>
                            <td><input type="text" name="tb[members][{{ $__i }}][igra_result]" class="form-control form-control-sm" value="{{ $__mb->igra_result ?? '' }}" placeholder="+/− หรือ mm"></td>
                            <td><input type="text" name="tb[members][{{ $__i }}][treatment_status]" class="form-control form-control-sm" value="{{ $__mb->treatment_status ?? '' }}" placeholder="เช่น รักษา LTBI"></td>
                            <td><input type="text" name="tb[members][{{ $__i }}][disease]" class="form-control form-control-sm" value="{{ $__mb->disease ?? '' }}"></td>
                            <td><input type="text" name="tb[members][{{ $__i }}][screened_date]" class="form-control form-control-sm date"
                                       value="{{ $__mb && $__mb->screened_date ? \App\Utillity::db2thDate($__mb->screened_date) : '' }}" placeholder="วว/ดด/ปปปป"></td>
                            <td><input type="text" name="tb[members][{{ $__i }}][note]" class="form-control form-control-sm" value="{{ $__mb->note ?? '' }}"></td>
                        </tr>
                    @endforeach
                    {{-- แถวว่างเผื่อเพิ่มคนใหม่ --}}
                    @for($__j = 0; $__j < 2; $__j++)
                        @php $__k = $__rows->count() + $__j; @endphp
                        <tr>
                            <td class="text-center text-muted">{{ $__k + 1 }}
                                <input type="hidden" name="tb[members][{{ $__k }}][id]" value=""></td>
                            <td><input type="text" name="tb[members][{{ $__k }}][name]" class="form-control form-control-sm"></td>
                            <td><input type="text" name="tb[members][{{ $__k }}][hn]" class="form-control form-control-sm js-member-hn" placeholder="ยังไม่มา"></td>
                            <td><input type="number" min="0" max="120" name="tb[members][{{ $__k }}][age]" class="form-control form-control-sm"></td>
                            <td><input type="text" name="tb[members][{{ $__k }}][relation]" class="form-control form-control-sm"></td>
                            <td><input type="text" name="tb[members][{{ $__k }}][symptoms]" class="form-control form-control-sm"></td>
                            <td><input type="text" name="tb[members][{{ $__k }}][cxr_result]" class="form-control form-control-sm"></td>
                            <td><input type="text" name="tb[members][{{ $__k }}][sputum_result]" class="form-control form-control-sm"></td>
                            <td><input type="text" name="tb[members][{{ $__k }}][igra_result]" class="form-control form-control-sm"></td>
                            <td><input type="text" name="tb[members][{{ $__k }}][treatment_status]" class="form-control form-control-sm"></td>
                            <td><input type="text" name="tb[members][{{ $__k }}][disease]" class="form-control form-control-sm"></td>
                            <td><input type="text" name="tb[members][{{ $__k }}][screened_date]" class="form-control form-control-sm date" placeholder="วว/ดด/ปปปป"></td>
                            <td><input type="text" name="tb[members][{{ $__k }}][note]" class="form-control form-control-sm"></td>
                        </tr>
                    @endfor
                    </tbody>
                </table>
            </div>
            {{-- บางบ้านมีผู้สัมผัสมากกว่าแถวที่เตรียมไว้ — กดเพิ่มได้เลย ไม่ต้องบันทึกก่อน (2569-09-29) --}}
            <button type="button" class="btn btn-outline-primary btn-sm js-tb-add-row" data-table="tb-member-table">
                <i class="fa fa-plus"></i> เพิ่มช่อง
            </button>

            <div class="small text-muted mt-1">
                ล้างชื่อจนว่าง = ลบคนนั้นออกจากรายชื่อ · อาการและคะแนน (ข้อ 4) ใช้ช่องในส่วนซักประวัติ ไม่ต้องกรอกซ้ำ ·
                <b>ช่อง HN ว่าง = ยังไม่ได้มาตรวจ</b> <span id="tb-member-pending" class="fw-bold text-danger"></span>
            </div>

            {{-- ข้อ 6: ผังครอบครัว — ถ่ายรูปที่วาดไว้แล้วอัปโหลด (เก็บเข้ารหัส ไม่ได้วางไว้ใน public) --}}
            <div class="fw-bold small mt-3 mb-1">6. ผังครอบครัว (Family Tree)</div>
            <div class="row g-2 align-items-center">
                <div class="col-md-6">
                    <input type="file" name="tb_family_tree" accept="image/*" class="form-control form-control-sm">
                    <div class="form-text">ถ่ายรูปผังที่วาดไว้แล้วอัปโหลด (3 generation)</div>
                </div>
                @if(! $__sc->family_tree_photo && $__prev && $__prev->family_tree_photo)
                    <div class="col-md-6 small">
                        <a href="{{ route('tb.familyTree', $__prev->visit_id) }}" target="_blank">
                            <i class="fa fa-image me-1"></i> ดูผังครอบครัวที่แนบไว้ครั้งก่อน
                        </a>
                        <div class="text-muted">ถ้าผังเปลี่ยน (มีสมาชิกเพิ่ม) ให้ถ่ายรูปใหม่แล้วอัปโหลดทับ</div>
                    </div>
                @endif
                @if($__sc->family_tree_photo)
                    <div class="col-md-6 d-flex align-items-center gap-2">
                        <a href="{{ route('tb.familyTree', $visit->id) }}" target="_blank">
                            <img src="{{ route('tb.familyTree', $visit->id) }}" alt="ผังครอบครัว"
                                 style="max-height:120px; border:1px solid #dee2e6; border-radius:.25rem;">
                        </a>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="tb_ft_remove" name="tb_family_tree_remove" value="1">
                            <label class="form-check-label small text-danger" for="tb_ft_remove">ลบรูปนี้ตอนบันทึก</label>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    </div>
</div>
@endsection

@section('tbp_history')
<div class="js-tb-screen-part">
<br>
<div class="d-grid">
    <a class="btn btn-light tb-part-btn" data-tabtoggle="1" href="#tbHistory" role="button"
       aria-expanded="false" style="width:100%" aria-controls="tbHistory">
        <font size="4"><i class="fa-solid fa-notes-medical me-1"></i> ซักประวัติ</font>
    </a>
</div>

{{-- แยก "ซักประวัติ" (CC/PI) กับ "คัดกรองวัณโรค" (อาการ + คะแนน) เป็นสองแถบ (คลินิกขอ 2569-10-14) --}}
<div class="collapse" id="tbHistory">
    <div class="card card-body">
        {{-- ถ้าคนไข้มีบัตร TB อยู่แล้ว บอกให้รู้ทันที จะได้ไม่คัดกรองซ้ำคนที่ขึ้นทะเบียนแล้ว
             มีบัตรที่ยังรักษาอยู่ = กล่อง F/U ด้านบนบอกแล้ว — เหลือกล่องนี้ไว้เฉพาะคนที่มีแต่บัตรที่จบการรักษาแล้ว --}}
        @if($__regs->count() && ! $__regs->contains(fn ($r) => $r->outcome === null))
            <div class="alert alert-warning py-2 px-3">
                <i class="fa fa-id-card me-1"></i> คนไข้รายนี้มีบัตรวัณโรคแล้ว:
                @foreach($__regs as $__r)
                    <a href="{{ route('tb.show', ['id' => $__r->id, 'from' => 'visit', 'visit' => $visit->id, 'page' => request('page', 1)]) }}" class="me-2">
                        {{ $__r->tb_no ?: 'ไม่มีเลขทะเบียน' }} ({{ $__r->typeName() }} · {{ $__r->outcomeName() }})
                    </a>
                @endforeach
                <div class="small">ใบคัดกรองใช้กับคนที่ยังไม่ขึ้นทะเบียน ถ้าขึ้นทะเบียนแล้วให้บันทึกที่บัตรแทน</div>
            </div>
        @endif
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label fw-bold" for="tb_cc">CC — อาการสำคัญที่มา</label>
                <input type="text" name="tb[chief_complaint]" id="tb_cc" class="form-control"
                       value="{{ $__sc->chief_complaint }}" placeholder="เช่น ไอมา 3 สัปดาห์ น้ำหนักลด">
            </div>
            <div class="col-md-7">
                <label class="form-label fw-bold">PI — ประวัติการเจ็บป่วยปัจจุบัน (Present Illness)</label>
                <textarea name="tb[illness_history]" rows="2" class="form-control">{{ $__sc->illness_history }}</textarea>
            </div>
        </div>
    </div>
</div>
<br>

<div class="d-grid">
    <a class="btn btn-light" data-tabtoggle="1" href="#tbScreening" role="button"
       aria-expanded="false" style="width:100%" aria-controls="tbScreening">
        <font size="4"><i class="fa-solid fa-lungs me-1"></i> คัดกรองวัณโรค
            @if($__sc->exists)
                <span class="badge bg-light text-dark ms-1">คะแนน {{ $__sc->symptom_score }}</span>
                @if($__sc->impression)<span class="badge bg-light text-dark ms-1">{{ \App\Models\TbScreening::$impressionOption[$__sc->impression] ?? '' }}</span>@endif
            @endif
        </font>
    </a>
</div>

<div class="collapse" id="tbScreening">
    <div class="card card-body">
        {{-- F/U ไม่บังคับให้คะแนน แต่ถ้ามีอาการให้ลงได้ (คลินิกขอ 2569-11-05) — เดิมซ่อนทั้งกล่องตอน F/U เหลือแต่ปุ่มบันทึก ดูเหมือนข้อมูลหาย --}}
        <div class="small mb-2 js-tb-fu-zone {{ $__isFu ? '' : 'd-none' }}" style="color:#b45309;">
            <i class="fa fa-circle-info me-1"></i> F/U ไม่ต้องให้คะแนน — ติ๊กเฉพาะเมื่อมีอาการ
        </div>

        <div class="row g-3">
            {{-- อาการ 1 เดือน + คะแนน (เกณฑ์กรมควบคุมโรค ≥3 = สงสัยวัณโรค) --}}
            <div class="col-md-7">
                <label class="form-label fw-bold">อาการภายใน 1 เดือนที่ผ่านมา</label>
                @foreach(\App\Models\TbScreening::$symptomScore as $field => $info)
                    <div class="form-check">
                        <input class="form-check-input js-tb-sym" type="checkbox" id="tb_{{ $field }}"
                               name="tb[{{ $field }}]" value="1" data-score="{{ $info['score'] }}"
                               {{ $__sc->$field ? 'checked' : '' }}>
                        <label class="form-check-label" for="tb_{{ $field }}">
                            {{ $info['label'] }} <span class="text-muted">({{ $info['score'] }} คะแนน)</span>
                        </label>
                    </div>
                @endforeach
            </div>

            <div class="col-md-5">
                <div class="tb-score-box p-3 text-center">
                    <div class="text-muted small">คะแนนรวม</div>
                    <div id="tb-score" style="font-size:2.5rem; font-weight:800; color:#b45309;">{{ $__sc->symptom_score ?? 0 }}</div>
                    <div id="tb-score-msg" class="fw-bold"></div>
                    <div class="small text-muted mt-1">เกณฑ์กรมควบคุมโรค: ตั้งแต่ 3 คะแนนขึ้นไป = ผู้มีอาการสงสัยวัณโรค</div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>{{-- /.js-tb-screen-part --}}
<br>
@endsection

@section('tbp_pe')
<div class="js-tb-screen-part">
<div class="d-grid">
    <a class="btn btn-light tb-part-btn" data-tabtoggle="1" href="#tbPe" role="button"
       aria-expanded="false" style="width:100%" aria-controls="tbPe">
        <font size="4"><i class="fa-solid fa-stethoscope me-1"></i> ตรวจร่างกาย (วัณโรค)</font>
    </a>
</div>
<div class="collapse" id="tbPe">
    <div class="card card-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-bold">การตรวจร่างกาย</label>
                <div class="row g-2">
                    @foreach([
                        'pe_ga' => 'GA', 'pe_heent' => 'HEENT', 'pe_heart' => 'Heart',
                        'pe_chest' => 'Chest / Lung', 'pe_abdomen' => 'Abdomen', 'pe_ext' => 'Ext.', 'pe_neuro' => 'Neuro',
                    ] as $field => $label)
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text" style="min-width:7.5rem;">{{ $label }}</span>
                                {{-- textarea 1 บรรทัด ยืดตามข้อความเอง (partials.textarea_autogrow) — เดิม input บรรทัดเดียว ข้อความยาวถูกตัดมองไม่เห็น (2569-10-08) --}}
                                <textarea name="tb[{{ $field }}]" rows="1" class="form-control" style="resize:vertical;">{{ $__sc->$field }}</textarea>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- บันทึกอิสระของแพทย์ (คลินิก TB ขอ 2569-10-08) — ยังไม่ migrate = ไม่แสดง (ไม่มีที่เก็บ) --}}
                @if(\App\Models\TbScreening::peNoteReady())
                    <label class="form-label fw-bold mt-3 mb-1" for="tb-pe-note">บันทึกแพทย์ <span class="small text-muted fw-normal">เขียนอะไรก็ได้</span></label>
                    <textarea name="tb[pe_note]" id="tb-pe-note" rows="3" class="form-control"
                              placeholder="บันทึกเพิ่มเติมของแพทย์">{{ $__sc->pe_note }}</textarea>
                @endif
            </div>
        </div>
    </div>
</div>
<br>
</div>{{-- /.js-tb-screen-part --}}
@endsection

@section('tbp_cxr')
@php
    // พับไว้ตอนเปิดหน้าถ้ายังไม่มีอะไรบันทึก — รูปปอดใหญ่ กินที่ทั้งจอ ทั้งที่หลายเคสไม่ได้เอกซเรย์ (คลินิก TB ขอ 2569-10-07)
    // มีข้อมูลแล้ว (มาร์ค/บรรยาย/ผล) = กางให้เห็นเหมือนเดิม · กดหัวข้อเพื่อเปิดเมื่อจะลงผลเอกซเรย์
    $__cxrMarks = is_array($__sc->lung_marks) ? $__sc->lung_marks : json_decode((string) $__sc->lung_marks, true);
    $__cxrHasData = ! empty($__cxrMarks) || trim((string) $__sc->lung_note) !== ''
        || collect($__sc->cxrRows())->contains(fn ($r) => ! empty($r['view']) || ! empty($r['result']) || trim((string) ($r['note'] ?? '')) !== '');
@endphp
<div class="js-tb-screen-part">
<div class="d-grid">
    <a class="btn btn-light tb-part-btn" data-tabtoggle="1" href="#tbCxr" role="button"
       aria-expanded="{{ $__cxrHasData ? 'true' : 'false' }}" style="width:100%" aria-controls="tbCxr">
        <font size="4"><i class="fa-solid fa-x-ray me-1"></i> เอกซเรย์ปอด
            @unless($__cxrHasData)<span style="font-size:.8em;opacity:.8;">(ยังไม่ได้ลงผล · กดเพื่อเปิด)</span>@endunless</font>
    </a>
</div>
@unless($__cxrHasData)
    {{-- พับแล้วหัวข้อเฉย ๆ ดูกลืนกับหัวข้ออื่น หาไม่เจอ (คลินิกบอก 2569-10-07) — ปุ่มใหญ่ไว้เปิด/ปิด
         กดแล้วต้องปิดกลับได้ (เดิมกดแล้วปุ่มหายไปเลย — แจ้ง 2569-10-08) · ข้อความปุ่มตามสถานะกล่องเสมอ (กดหัวข้อ/ย่อทั้งหมดก็ตาม) --}}
    <div class="js-cxr-placeholder text-center p-2 mt-1 rounded" style="border:2px dashed #ea580c; background:#fff7ed;">
        <button type="button" class="btn fw-bold text-white px-4 js-cxr-toggle" style="background:#ea580c; border-radius:2rem;"
                onclick="document.getElementById('tbCxr').classList.toggle('show');">
            <i class="fa-solid fa-x-ray me-1"></i> <span class="js-cxr-label">ลงผลเอกซเรย์ / มาร์คตำแหน่งที่ปอด</span>
        </button>
        <span class="small text-muted ms-2 js-cxr-hint">พับไว้เพราะรูปปอดใหญ่ — กดเพื่อเปิด</span>
    </div>
    <script>
    (function () {
        var box = document.getElementById('tbCxr') || null;
        function sync() {
            box = box || document.getElementById('tbCxr');
            if (!box) return;
            var open = box.classList.contains('show');
            var wrap = document.querySelector('.js-cxr-placeholder');
            wrap.querySelector('.js-cxr-label').textContent = open ? 'ซ่อนเอกซเรย์ปอด' : 'ลงผลเอกซเรย์ / มาร์คตำแหน่งที่ปอด';
            wrap.querySelector('.js-cxr-hint').style.display = open ? 'none' : '';
            wrap.style.borderStyle = open ? 'solid' : 'dashed';
        }
        function bind() {
            box = document.getElementById('tbCxr');
            if (!box) return;
            new MutationObserver(sync).observe(box, { attributes: true, attributeFilter: ['class'] });
            sync();
        }
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind); else setTimeout(bind, 0);
    })();
    </script>
@endunless
{{-- data-keep-collapsed = ตัว "เปิดทุกแท็บตอนโหลด" ใน visit/form/index ข้ามกล่องนี้ --}}
<div class="collapse" id="tbCxr" @unless($__cxrHasData) data-keep-collapsed="1" @endunless>
    <div class="card card-body">
        {{-- รูปปอดไว้มาร์คตำแหน่งที่ตรวจพบ (เส้นในรูปคือร่องแบ่งกลีบปอด)
             เก็บเป็น JSON แบบเดียวกับการ์ดมาร์คร่างกายของ STI — คลินิกขอ 2569-09-29 --}}
        <label class="form-label fw-bold">มาร์คตำแหน่งที่ปอด</label>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2 small">
            <label class="mb-0">โหมด</label>
            <select id="lung-mode" class="form-select form-select-sm" style="width:auto;">
                <option value="point">มาร์คจุด</option>
                <option value="free">วาดเส้น</option>
                <option value="text">พิมพ์ข้อความ</option>
            </select>
            <label class="mb-0 ms-2">สี</label>
            <input type="color" id="lung-color" value="#e63946">
            <label class="mb-0 ms-2">ขนาด</label>
            {{-- รูปจริง 1254px แต่แสดงบนจอราว 430px จุดจึงต้องใหญ่กว่าปกติถึงจะเห็น --}}
            <input type="range" id="lung-size" min="8" max="48" value="20" style="width:6rem;">
            <button type="button" class="btn btn-warning btn-sm" id="lung-undo">ย้อนกลับ</button>
            <button type="button" class="btn btn-danger btn-sm" id="lung-clear">ล้างทั้งหมด</button>
            <span class="text-muted">ปอดขวาของคนไข้อยู่ทางซ้ายของรูป · เลือก “พิมพ์ข้อความ” แล้วคลิกบนรูปเพื่อเขียนคำอธิบาย</span>
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                {{-- ขนาดผืนผ้าใบเท่ากับรูปจริง (1254x1254) พิกัดที่มาร์คจะได้ตรงกับรูปเสมอ
                     ย่อเฉพาะขนาดที่แสดงบนจอ (เดิม 640px ใหญ่เกิน คลินิกขอ 2569-09-29)
                     จุดที่มาร์คไว้แล้วไม่เพี้ยน เพราะพิกัดอ้างขนาดรูปจริง ไม่ใช่ขนาดบนจอ --}}
                <canvas id="lung-canvas" width="1254" height="1254"
                        style="border:1px solid #dee2e6; border-radius:.25rem; cursor:crosshair; width:100%; max-width:360px; height:auto;"></canvas>
                <input type="hidden" name="tb[lung_marks]" id="lung-marks"
                       value="{{ is_array($__sc->lung_marks) ? json_encode($__sc->lung_marks) : ($__sc->lung_marks ?? '') }}">
            </div>

            {{-- คอลัมน์ขวา: บรรยายผลตรวจปอด + ผลเอกซเรย์ + ปุ่ม PACS — ย้ายผลเอกซเรย์มาไว้ข้างรูปปอด
                 สองคอลัมน์จะได้สูงพอ ๆ กัน ไม่เหลือที่ว่างใต้ช่องบรรยาย (คลินิกขอ 2569-10-02) --}}
            <div class="col-lg-7">
                <label class="form-label small text-muted mb-1" for="tb-lung-note">บรรยายผลตรวจปอด</label>
                <textarea name="tb[lung_note]" id="tb-lung-note" rows="4" class="form-control"
                          placeholder="เช่น RUL: crepitation ฟังได้ชัด&#10;LLL: breath sound เบาลง&#10;ไม่มี wheezing">{{ $__sc->lung_note }}</textarea>

                {{-- ผลเอกซเรย์ลงได้หลายแถวในวันเดียว (หลายท่าถ่าย) — ชื่อช่อง tb_cxr แยกจาก tb[...]
                     เพื่อให้ _touched แยกได้ว่าแก้ผลเอกซเรย์หรือไม่ (VisitController::saveTbCxr)
                     มีแถวอย่างน้อย 1 แถวเสมอ ชื่อ tb_cxr จะได้อยู่ในฟอร์ม --}}
                @php
                    $__cxrRows  = $__sc->cxrRows();
                    $__cxrMulti = \App\Models\TbScreening::hasCxrRows();
                    $__cxrDocs  = \App\Models\User::physicians()->pluck('name');
                @endphp
                <label class="form-label fw-bold mb-1 mt-3">ผลเอกซเรย์ปอด</label>
                <div id="tb-cxr-rows">
                    @foreach($__cxrRows as $__ci => $__cr)
                        <div class="row g-1 align-items-center mb-1 tb-cxr-row">
                            <div class="col-md-4">
                                <select name="tb_cxr[{{ $__ci }}][view]" class="form-select form-select-sm" title="ท่าถ่าย">
                                    <option value="">— ท่าถ่าย —</option>
                                    @foreach(\App\Models\TbXray::$viewOption as $__vk => $__vl)
                                        <option value="{{ $__vk }}" {{ ($__cr['view'] ?? null) === $__vk ? 'selected' : '' }}>{{ $__vl }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="tb_cxr[{{ $__ci }}][result]" class="form-select form-select-sm" title="ผล">
                                    <option value="">— ยังไม่มีผล —</option>
                                    @foreach(\App\Models\TbScreening::$cxrOption as $k => $label)
                                        <option value="{{ $k }}" {{ (int) ($__cr['result'] ?? 0) === $k ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="{{ $__cxrMulti ? 'col-md-4' : 'col-md-5' }}">
                                <input type="text" name="tb_cxr[{{ $__ci }}][note]" class="form-control form-control-sm" maxlength="255"
                                       value="{{ $__cr['note'] ?? '' }}" placeholder="รายละเอียด / เหตุผลที่ไม่ได้เอกซเรย์">
                            </div>
                            {{-- แพทย์ผู้สั่งตรวจ/อ่านผล (คลินิก TB ขอ 2569-10-09) — เก็บชื่อเป็นข้อความใน cxr_results ของแถวนั้น
                                 ชื่อเก่าที่ไม่อยู่ในรายชื่อแพทย์ตอนนี้ยังโชว์เป็นตัวเลือก ไม่หายตอนบันทึก --}}
                            @php $__cd = trim((string) ($__cr['doctor'] ?? '')); @endphp
                            <div class="col-md-12">
                                <select name="tb_cxr[{{ $__ci }}][doctor]" class="form-select form-select-sm" title="แพทย์ผู้สั่งตรวจ / อ่านผล">
                                    <option value="">— แพทย์ผู้สั่งตรวจ / อ่านผล —</option>
                                    @if($__cd !== '' && ! $__cxrDocs->contains($__cd))<option value="{{ $__cd }}" selected>{{ $__cd }}</option>@endif
                                    @foreach($__cxrDocs as $__dn)
                                        <option value="{{ $__dn }}" {{ $__cd === $__dn ? 'selected' : '' }}>{{ $__dn }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @if($__cxrMulti)
                                <div class="col-md-1 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger js-cxr-del" title="เอาแถวนี้ออก">&times;</button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if($__cxrMulti)
                    <button type="button" class="btn btn-sm btn-outline-primary" id="tb-cxr-add">
                        <i class="fa fa-plus"></i> เพิ่มผลเอกซเรย์ (ท่าอื่น)
                    </button>
                @endif
                @include('partials.pacs_link', ['hn' => optional($visit->patient2)->code])
            </div>
        </div>
    </div>
</div>
<br>
</div>{{-- /.js-tb-screen-part --}}
@endsection

@section('tbp_lab')
<div class="row g-3 mb-3">
            {{-- ====== ส่งตรวจทางห้องปฏิบัติการ (คลินิกขอโครงใหม่ 2569-09-29)
                 แยกเป็น สิ่งส่งตรวจ -> วิธีตรวจหาเชื้อ -> การตรวจหาการติดเชื้อ (skin test / IGRA)
                 ผลส่วนใหญ่พยาบาลกรอกเอง เพราะห้องแล็บลงผลในระบบ NTIP ====== --}}
            {{-- ช่องของส่วนนี้ย้ายไป tb/_lab_fields (2569-10-02) ใช้ร่วมกับกล่อง "สั่งตรวจครั้งนี้" ในบัตร TB 01
                 ชื่อช่องเป็น tb_lab[...] แยกจาก tb[...] — _touched แยกกัน แก้ประวัติแล้วไม่ทับผลแล็บที่อีกที่เพิ่งลง
                 ช่องผลเสมหะ M+/M− (afb_status) เอาออกแล้ว ซ้ำกับผล AFB · ตำแหน่งนอกปอดยังเป็นของใบคัดกรอง --}}
            <div class="col-12">
                @include('tb._lab_fields', ['sc' => $__sc, 'prefix' => 'tb_lab'])
            </div>

</div>
@endsection

@section('tbp_impression')
{{-- Impression + ICD-10 ย้ายเข้าไปอยู่ในการ์ด "วินิจฉัย" ของหน้าตรวจ (คลินิกบอกว่าเป็นอันเดียวกัน 2569-10-08)
     visit/form/index เรียก @yield('tbp_impression') ภายในการ์ดวินิจฉัย — ไม่มีหัวข้อแยกแล้ว --}}
<div class="mb-3 pb-3 border-bottom" id="tbImpression">
        <div class="row g-3">
            {{-- การวินิจฉัยแบบมีประเภท (คลินิกขอ 2569-10-01) — แทนช่องรหัส ICD-10 แบบชิปเดิม
                 แต่ละแถว: ประเภท (Principal / Comorbidity / Complication / Other) + รหัส ICD-10 จากรายการ หรือพิมพ์ชื่อโรคเอง
                 ฝั่งเซิร์ฟเวอร์: Icd10Code::syncTyped — ขึ้นต้นด้วยรหัสในรายการ = เก็บเป็นรหัส · ไม่ใช่ = เก็บเป็นข้อความ
                 มีแถวอย่างน้อย 1 แถวเสมอ ชื่อ tb_dx จะได้อยู่ในฟอร์ม (_touched เทียบตามชื่อช่อง) --}}
            @php
                $__dxRows = \App\Models\Icd10Code::typedEntriesFor(\App\Models\TbScreening::icd10Owner(), $__sc->id);
                // รายการตั้งต้น = รหัสที่หมอคัดไว้ · ICD-10-TM ชุดเต็มค้นจากเซิร์ฟเวอร์ตอนพิมพ์ (2569-10-30)
                $__dxDict = \App\Models\Icd10Code::where('active', true)->where('source', 'clinic')->orderBy('sort')->orderBy('code')->get();
                $__dxLabel = fn ($code) => optional(\App\Models\Icd10Code::info($code), fn ($c) => $c->code . ' — ' . ($c->description_th ?: $c->description_en)) ?? $code;
                if (! $__dxRows) { $__dxRows = [['type' => 'principal', 'code' => null, 'text' => null]]; }

                // วินิจฉัยครั้งก่อนของคนไข้คนนี้ ให้ปุ่ม "ดึงวินิจฉัยครั้งก่อน" (ผู้ใช้ขอ 2569-10-30)
                // ข้อมูลจริง: มาซ้ำ 91 ครั้ง ลงเหมือนครั้งก่อนทุกช่อง 54 ครั้ง · กดเองเท่านั้น ไม่เติมตอนเปิดหน้า
                // (ครั้งก่อนอาจเป็นคัดกรอง ครั้งนี้ยืนยันวัณโรคแล้ว — เติมเองจะได้วินิจฉัยเก่าที่ไม่มีใครดู)
                $__dxPrev = null;
                $__prevSc = \DB::table('tb_screenings as s')->join('visits as v', 'v.id', '=', 's.visit_id')
                    ->join('icd10_entries as e', function ($j) {
                        $j->on('e.owner_id', '=', 's.id')->where('e.owner_type', \App\Models\TbScreening::icd10Owner());
                    })
                    ->where('v.patient_id', $visit->patient_id)->where('s.id', '!=', $__sc->id ?: 0)
                    ->where(function ($w) use ($visit) {
                        $w->where('v.date', '<', $visit->getRawOriginal('date'))
                          ->orWhere(function ($w2) use ($visit) { $w2->where('v.date', $visit->getRawOriginal('date'))->where('v.id', '<', $visit->id); });
                    })
                    ->orderByDesc('v.date')->orderByDesc('v.id')
                    ->first(['s.id', 'v.date']);
                if ($__prevSc) {
                    $__dxPrev = [
                        'date' => \App\Utillity::db2thDate($__prevSc->date),
                        // รหัสที่ปิดใช้แล้ว (เช่น T36.6X5- แบบ CM ที่เลิกใช้ 2569-10-30) ไม่ดึงมาต่อ — ให้เลือกรหัส TM ใหม่แทน
                        'rows' => array_map(fn ($r) => [
                            'type'  => $r['type'],
                            'code'  => $r['code'],
                            'off'   => $r['code'] && ! optional(\App\Models\Icd10Code::info($r['code']))->active,
                            'value' => $r['code'] ? $__dxLabel($r['code']) : (string) $r['text'],
                        ], \App\Models\Icd10Code::typedEntriesFor(\App\Models\TbScreening::icd10Owner(), $__prevSc->id)),
                    ];
                }
            @endphp
            <div class="col-12">
                <label class="form-label fw-bold">การวินิจฉัย / รหัสโรค ICD-10</label>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-1" id="tb-dx-table">
                        <thead class="table-light">
                            <tr><th style="width:17rem;">ประเภท</th><th>รหัส ICD-10 หรือชื่อโรค (พิมพ์เองได้)</th><th style="width:3rem;"></th></tr>
                        </thead>
                        <tbody>
                        @foreach($__dxRows as $__i => $__r)
                            <tr class="js-dx-row">
                                <td>
                                    <select name="tb_dx[{{ $__i }}][type]" class="form-select form-select-sm">
                                        @foreach(\App\Models\Icd10Code::DX_TYPES as $__k => $__l)
                                            <option value="{{ $__k }}" {{ $__r['type'] === $__k ? 'selected' : '' }}>{{ $__l }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="tb_dx[{{ $__i }}][value]" class="form-control form-control-sm js-dx-value" autocomplete="off" maxlength="255"
                                           value="{{ $__r['code'] ? $__dxLabel($__r['code']) : $__r['text'] }}"
                                           placeholder="พิมพ์รหัสหรือชื่อโรค เช่น A15.0 หรือ K71.6 — หรือพิมพ์ชื่อโรคเองถ้าไม่มีในรายการ">
                                    {{-- ชื่อโรคยาวเกินช่อง (ICD-10-TM ชุดเต็ม) -> โชว์เต็มใต้ช่อง ไม่ต้องคลิกเข้าไปเลื่อนอ่าน (พยาบาลขอ 2569-10-30)
                                         สคริปต์ด้านล่างซ่อนไว้ถ้าข้อความพอดีช่อง --}}
                                    <div class="tb-dx-full" style="display:none; font-size:.78rem; line-height:1.35; color:#334155; margin-top:2px; white-space:normal;"></div>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-outline-danger btn-sm js-dx-del" title="ลบแถว">&times;</button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        {{-- วินิจฉัยแบบเดิม (ตาราง diagnoses) ของ visit นี้ รวมไว้ตารางเดียวกัน (คลินิกขอ 2569-10-14) — อ่านอย่างเดียว ลบได้
                             tbody แยก: สคริปต์เพิ่มแถวด้านล่างใช้ tBodies[0] เท่านั้น · Pre/Post-test HIV อยู่ที่กล่องให้คำปรึกษา ไม่ขึ้นที่นี่ --}}
                        @php $__dxOld = $visit->diagnosi()->reject(fn ($x) => in_array((int) $x->disease, [13, 14], true))->values(); @endphp
                        @if($__dxOld->count())
                        <tbody>
                            @foreach($__dxOld as $__od)
                                <tr>
                                    <td><span class="badge bg-light text-dark border" title="ลงแบบเดิม (ตารางวินิจฉัยเดิม)">ลงแบบเดิม</span></td>
                                    <td>{{ $__od->disease() }}@if($__od->diseaseState()) <span class="small text-muted">· {{ $__od->diseaseState() }}</span>@endif</td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-danger btn-sm js-row-delete" title="ลบวินิจฉัยนี้"
                                                data-action="{{ route('diagnosis.destroy', ['diagnosi' => $__od->id, 'page' => request('page')]) }}">&times;</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        @endif
                    </table>
                </div>
                {{-- รายการให้เลือกวาดเอง (ไม่ใช้ <datalist>) — Chrome วาด datalist เองแล้วตัดชื่อยาวเป็น "…" ปรับความกว้างไม่ได้ (พยาบาลส่งรูป 2569-10-30)
                     เมนูต่อกับ body เพราะตารางอยู่ใน .table-responsive ที่ตัดของล้นทิ้ง --}}
                <style>
                    #tb-dx-menu { position:absolute; z-index:1060; display:none; background:#fff; border:1px solid #cbd5e1; border-radius:6px;
                                  box-shadow:0 6px 18px rgba(0,0,0,.12); max-height:320px; overflow-y:auto; }
                    #tb-dx-menu .g { padding:3px 9px; font-size:.72rem; font-weight:700; color:#475569; background:#f1f5f9; position:sticky; top:0; }
                    #tb-dx-menu .o { padding:4px 9px; cursor:pointer; font-size:.82rem; line-height:1.35; white-space:normal; border-bottom:1px solid #f1f5f9; }
                    #tb-dx-menu .o:hover, #tb-dx-menu .o.on { background:#e0e7ff; }
                    #tb-dx-menu .o b { color:#1e3a8a; }
                    #tb-dx-menu .none { padding:8px 9px; color:#64748b; font-size:.82rem; }
                </style>
                <script type="application/json" id="tb-dx-dict">@json($__dxDict->map->toPicker()->values())</script>
                <button type="button" class="btn btn-outline-primary btn-sm" id="tb-dx-add"><i class="fa fa-plus"></i> เพิ่มการวินิจฉัย</button>
                @if($__dxPrev && $__dxPrev['rows'])
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="tb-dx-prev"
                            title="{{ collect($__dxPrev['rows'])->pluck('value')->implode(' · ') }}">
                        <i class="fa fa-clock-rotate-left"></i> ดึงวินิจฉัยครั้งก่อน ({{ $__dxPrev['date'] }})
                    </button>
                    <script type="application/json" id="tb-dx-prev-data">@json($__dxPrev['rows'])</script>
                @endif
                <span id="tb-dx-prev-msg" class="small ms-1"></span>
                <span class="small text-muted ms-2">
                    Principal = โรคหลักที่มาครั้งนี้ · Comorbidity = โรคที่เป็นอยู่เดิมและส่งผลต่อการรักษา ·
                    Complication = ภาวะแทรกซ้อนที่เกิดใหม่ · Other = โรคอื่นที่บันทึกไว้เป็นข้อมูล
                </span>
                <script>
                (function () {
                    // เพิ่ม/ลบแถว — หน้านี้โหลดเต็ม สคริปต์ทำงานได้ · ปุ่มเป็น type=button (อยู่ในฟอร์มหลัก)
                    var table = document.getElementById('tb-dx-table');
                    if (!table) return;
                    var tbody = table.tBodies[0], seq = 1000;
                    document.getElementById('tb-dx-add').addEventListener('click', function () {
                        var last = tbody.querySelector('tr.js-dx-row:last-child');
                        var tr = last.cloneNode(true), i = seq++;
                        tr.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/tb_dx\[[^\]]+\]/, 'tb_dx[' + i + ']'); });
                        tr.querySelector('input').value = '';
                        tr.querySelector('select').value = 'comorbidity';
                        tbody.appendChild(tr);
                        tr.querySelector('input').focus();
                    });
                    // Impression อัตโนมัติจากรหัสโรคหลัก (แถวแรกที่เป็น Principal) — 2569-10-09
                    // A15-A16 ปอด · A17-A19 นอกปอด · Z22.7/R76.11/R76.12 LTBI · Z20.1/Z11.1/Z11.7 (สัมผัส/คัดกรอง) normal · รหัสอื่น อื่นๆ
                    // พิมพ์ชื่อโรคเอง (ไม่มีรหัส) = ไม่เปลี่ยน · พยาบาล/หมอเลือก Impression เองแล้ว = ไม่ทับ
                    // ช่อง Impression อยู่ "ใต้" ตารางนี้ (ย้ายลงไป 2569-10-09) — ตอนสคริปต์นี้รันยังไม่มีในหน้า ต้องหาตอนใช้
                    function getImp() { return document.getElementById('tb-impression'); }
                    function mapImp(code) {
                        if (/^A1[56]/.test(code)) return '4';
                        if (/^A1[789]/.test(code)) return '5';
                        if (/^(Z22\.7|R76\.1[12])/.test(code)) return '2';
                        if (/^(Z20\.1|Z11\.[17])/.test(code)) return '1';
                        return '6';
                    }
                    function autoImp() {
                        var imp = getImp();
                        if (!imp || imp.dataset.manual === '1') return;
                        var rows = tbody.querySelectorAll('tr.js-dx-row'), code = null;
                        for (var k = 0; k < rows.length; k++) {
                            if (rows[k].querySelector('select').value !== 'principal') continue;
                            var m = /^\s*([A-Z]\d{2}(?:\.[0-9A-Z]+)?)\b/.exec(rows[k].querySelector('input').value || '');
                            if (m) { code = m[1]; }
                            break;
                        }
                        if (!code) return;
                        var v = mapImp(code);
                        if (imp.value !== v) { imp.value = v; imp.dataset.auto = '1'; imp.dispatchEvent(new Event('change', { bubbles: true })); }
                    }
                    document.addEventListener('change', function (e) { if (e.isTrusted && e.target.id === 'tb-impression') { e.target.dataset.manual = '1'; } });
                    table.addEventListener('change', autoImp);
                    table.addEventListener('input', autoImp);

                    // ICD-10-TM ชุดเต็ม: พิมพ์ 2 ตัวขึ้นไป -> ค้นจากเซิร์ฟเวอร์แล้วต่อท้าย datalist (2569-10-30)
                    // ไม่ฝังทั้ง ~15,700 รหัสไว้ในหน้า (หน้าตรวจจะหนักขึ้นเป็นเมก)
                    // ค่าที่เลือกเป็น "รหัส — ชื่อ" เหมือนเดิม ฝั่งเซิร์ฟเวอร์ (syncTyped) เช็คกับพจนานุกรมทั้งชุดอยู่แล้ว
                    // ข้อความยาวเกินช่อง -> แสดงเต็มใต้ช่อง (ตัดบรรทัดได้) · พอดีช่อง = ซ่อน
                    function fitFull(inp) {
                        var box = inp.parentNode.querySelector('.tb-dx-full');
                        if (!box) return;
                        var over = inp.value && inp.scrollWidth > inp.clientWidth + 1;
                        box.textContent = over ? inp.value : '';
                        box.style.display = over ? '' : 'none';
                    }
                    function fitAll() { tbody.querySelectorAll('tr.js-dx-row input[type=text]').forEach(fitFull); }
                    // วัดทุกแถว (ถูก) — ครอบทั้งตอนพิมพ์ เลือกจากรายการ เพิ่มแถว และล้างแถวสุดท้าย
                    table.addEventListener('input', fitAll);
                    table.addEventListener('change', fitAll);
                    document.getElementById('tb-dx-add').addEventListener('click', function () { setTimeout(fitAll, 0); });
                    window.addEventListener('resize', fitAll);
                    // การ์ดวินิจฉัยอาจซ่อนอยู่ตอนโหลด (ความกว้างเป็น 0) -> วัดใหม่ตอนแท็บ/การ์ดถูกเปิด
                    document.addEventListener('shown.bs.tab', fitAll);
                    document.addEventListener('shown.bs.collapse', fitAll);
                    if ('IntersectionObserver' in window) {
                        new IntersectionObserver(function (es) { if (es.some(function (x) { return x.isIntersecting; })) fitAll(); }).observe(table);
                    }
                    fitAll();

                    // ---- ดึงวินิจฉัยครั้งก่อน: เติมลงตาราง ยังไม่บันทึก ให้คนดูแล้วกด Save เอง ----
                    var prevBtn = document.getElementById('tb-dx-prev');
                    if (prevBtn) prevBtn.addEventListener('click', function () {
                        var data = [];
                        try { data = JSON.parse(document.getElementById('tb-dx-prev-data').textContent || '[]'); } catch (err) {}
                        var keyOf = function (v) { return (v || '').split(' — ')[0].trim().toUpperCase(); };
                        var have = {};
                        tbody.querySelectorAll('tr.js-dx-row .js-dx-value').forEach(function (i) { if (i.value.trim()) have[keyOf(i.value)] = 1; });
                        var added = 0, skipped = 0, off = [];
                        data.forEach(function (r) {
                            if (r.off) { off.push(r.code); return; }
                            if (!r.value || have[keyOf(r.value)]) { skipped++; return; }
                            // ใช้แถวว่างก่อน ไม่มีค่อยเพิ่มแถว (ปุ่มเพิ่มแถวโคลนแถวสุดท้ายให้)
                            var empty = Array.prototype.find.call(tbody.querySelectorAll('tr.js-dx-row'), function (tr) { return !tr.querySelector('.js-dx-value').value.trim(); });
                            if (!empty) { document.getElementById('tb-dx-add').click(); empty = tbody.querySelector('tr.js-dx-row:last-child'); }
                            empty.querySelector('select').value = r.type;
                            var inp = empty.querySelector('.js-dx-value');
                            inp.value = r.value;
                            inp.dispatchEvent(new Event('input', { bubbles: true }));
                            inp.dispatchEvent(new Event('change', { bubbles: true }));
                            have[keyOf(r.value)] = 1; added++;
                        });
                        hide();
                        var msg = document.getElementById('tb-dx-prev-msg');
                        if (msg) {
                            msg.className = 'small ms-1 ' + (added ? 'text-success fw-bold' : 'text-muted');
                            msg.textContent = (added
                                ? 'เติมจากครั้งก่อน ' + added + ' แถว' + (skipped ? ' (ข้ามที่มีอยู่แล้ว ' + skipped + ')' : '') + ' — ตรวจดูแล้วกด Save'
                                : (skipped ? 'วินิจฉัยครั้งก่อนมีอยู่ในตารางแล้วทุกแถว' : 'ไม่มีรหัสให้ดึง'))
                                + (off.length ? ' · ไม่ดึง ' + off.join(', ') + ' (เลิกใช้แล้ว เลือกรหัส TM แทน เช่น Y40.6)' : '');
                        }
                    });

                    // ---- รายการให้เลือก (วาดเอง แทน datalist) ----
                    // ว่าง = รหัสที่หมอคัดไว้ทั้งหมด · พิมพ์ 2 ตัวขึ้นไป = กรองรายการหมอ + ค้น ICD-10-TM ชุดเต็มจากเซิร์ฟเวอร์ (2569-10-30)
                    var DICT = [];
                    try { DICT = JSON.parse(document.getElementById('tb-dx-dict').textContent || '[]'); } catch (err) {}
                    var SEARCH = @json(route('icd10.search'));
                    var menu = document.createElement('div');
                    menu.id = 'tb-dx-menu';
                    document.body.appendChild(menu);
                    var cur = null, remote = { term: null, list: [] }, dxTimer = null, dxSeq = 0;

                    function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
                    function low(s) { return (s || '').toString().toLowerCase(); }
                    function place() {
                        if (!cur) return;
                        var r = cur.getBoundingClientRect();
                        menu.style.left = (r.left + window.scrollX) + 'px';
                        menu.style.top = (r.bottom + window.scrollY + 2) + 'px';
                        menu.style.width = r.width + 'px';
                    }
                    function hide() { menu.style.display = 'none'; cur = null; }
                    function show(inp) {
                        cur = inp;
                        var term = inp.value.trim(), q = low(term), qn = q.split('.').join('');
                        // ค่าที่เลือกจากรายการแล้ว ("A15.0 — ...") ไม่ต้องกรองด้วยข้อความเต็ม — โชว์ทั้งหมดให้เปลี่ยนได้
                        if (term.indexOf(' — ') !== -1) { q = ''; qn = ''; }
                        var hits = DICT.filter(function (it) {
                            if (!q) return true;
                            return low(it.c).indexOf(q) !== -1 || low(it.c).split('.').join('').indexOf(qn) !== -1
                                || low(it.t).indexOf(q) !== -1 || low(it.e).indexOf(q) !== -1 || low(it.g).indexOf(q) !== -1;
                        });
                        var seen = {}, html = '', group = null;
                        hits.forEach(function (it) { seen[it.c] = 1; });
                        if (q && remote.term === term) {
                            remote.list.forEach(function (it) {
                                if (seen[it.c]) return;
                                seen[it.c] = 1;
                                hits.push({ c: it.c, t: it.t, e: it.e, g: 'ICD-10-TM ทั้งหมด (สนย.)', s: it.s });
                            });
                        }
                        hits.slice(0, 120).forEach(function (it) {
                            if (it.g !== group) { group = it.g; html += '<div class="g">' + esc(group || 'รหัสที่หมอคัดไว้') + '</div>'; }
                            html += '<div class="o" data-v="' + esc(it.c + ' — ' + (it.t || it.e)) + '"><b>' + esc(it.c) + '</b> ' + esc(it.t || it.e) + '</div>';
                        });
                        if (!html) {
                            html = '<div class="none">' + (q.length >= 2 && remote.term !== term
                                ? 'กำลังค้นใน ICD-10-TM ทั้งหมด…'
                                : 'ไม่พบในรายการ — พิมพ์ชื่อโรคเองได้ ระบบเก็บเป็นข้อความ') + '</div>';
                        }
                        menu.innerHTML = html;
                        menu.style.display = 'block';
                        place();
                    }
                    function searchRemote(inp) {
                        var term = inp.value.trim();
                        clearTimeout(dxTimer);
                        if (term.length < 2 || term.indexOf(' — ') !== -1) return;
                        dxTimer = setTimeout(function () {
                            var my = ++dxSeq;
                            fetch(SEARCH + '?q=' + encodeURIComponent(term), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                                .then(function (r) { return r.ok ? r.json() : []; })
                                .then(function (list) {
                                    if (my !== dxSeq) return;
                                    remote = { term: term, list: list || [] };
                                    if (cur === inp && inp.value.trim() === term) show(inp);
                                })
                                .catch(function () {});
                        }, 250);
                    }
                    function pick(v) {
                        var inp = cur;
                        if (!inp) return;
                        inp.value = v;
                        hide();
                        // ให้ตัวจำค่าของหน้าตรวจ (_touched) + Impression อัตโนมัติ + บรรทัดชื่อเต็ม รู้ว่าค่าเปลี่ยน
                        inp.dispatchEvent(new Event('input', { bubbles: true }));
                        inp.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    table.addEventListener('focusin', function (e) { if (e.target.matches('.js-dx-value')) show(e.target); });
                    table.addEventListener('input', function (e) {
                        if (!e.isTrusted || !e.target.matches('.js-dx-value')) return;
                        show(e.target); searchRemote(e.target);
                    });
                    // mousedown (ไม่ใช่ click) — ไม่งั้นช่องเสีย focus แล้วเมนูปิดก่อนเลือกทัน
                    menu.addEventListener('mousedown', function (e) {
                        var o = e.target.closest('.o');
                        e.preventDefault();
                        if (o) pick(o.getAttribute('data-v'));
                    });
                    table.addEventListener('keydown', function (e) {
                        if (!cur || e.target !== cur || menu.style.display === 'none') return;
                        var opts = Array.prototype.slice.call(menu.querySelectorAll('.o'));
                        var i = opts.indexOf(menu.querySelector('.o.on'));
                        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                            e.preventDefault();
                            if (!opts.length) return;
                            if (i >= 0) opts[i].classList.remove('on');
                            i = e.key === 'ArrowDown' ? Math.min(opts.length - 1, i + 1) : Math.max(0, i - 1);
                            opts[i].classList.add('on');
                            opts[i].scrollIntoView({ block: 'nearest' });
                        } else if (e.key === 'Enter') {
                            // Enter = เลือกตัวที่ไฮไลต์ (ห้ามไปกดบันทึกทั้งฟอร์ม)
                            if (i >= 0) { e.preventDefault(); pick(opts[i].getAttribute('data-v')); }
                        } else if (e.key === 'Escape') {
                            hide();
                        }
                    });
                    table.addEventListener('focusout', function (e) { if (e.target === cur) setTimeout(function () { if (document.activeElement !== cur) hide(); }, 120); });
                    window.addEventListener('resize', place);
                    window.addEventListener('scroll', place, true);
                    table.addEventListener('click', function (e) {
                        var b = e.target.closest('.js-dx-del');
                        if (!b) return;
                        var tr = b.closest('tr');
                        // แถวสุดท้ายล้างค่าแทนการลบ — ชื่อ tb_dx ต้องอยู่ในฟอร์มเสมอ
                        if (tbody.querySelectorAll('tr.js-dx-row').length <= 1) { tr.querySelector('input').value = ''; }
                        else { tr.remove(); }
                        tbody.dispatchEvent(new Event('input', { bubbles: true }));
                    });
                })();
                </script>
            </div>

            {{-- Impression = ตัวตัดสินว่าต้องเปิดบัตรต่อไหม --}}
            <div class="col-md-5">
                {{-- เติมให้เองจากรหัสโรคหลัก (คลินิกขอ 2569-10-09: หมอกรอกช่องเดียว) — เลือกเองทับได้ เลือกเองแล้วระบบไม่เปลี่ยนให้อีก --}}
                <label class="form-label fw-bold">Impression <span class="small text-muted fw-normal">เติมให้จากรหัสโรคหลัก · แก้ได้</span></label>
                <select name="tb[impression]" id="tb-impression" class="form-select">
                    <option value="">— ยังไม่สรุป —</option>
                    @foreach(\App\Models\TbScreening::$impressionOption as $k => $label)
                        <option value="{{ $k }}" {{ (int) $__sc->impression === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Impression = อื่นๆ → ระบุว่าเป็นโรคอะไร (คลินิกถาม 2569-10-01) — ช่องเดิม impression_other ย้ายมาจากแถวตำแหน่งนอกปอด --}}
            <div class="col-md-7 {{ (int) $__sc->impression === 6 || $__sc->impression_other ? '' : 'd-none' }}" id="tb-impression-other-box">
                <label class="form-label fw-bold">โรคอื่น ๆ ระบุ</label>
                <input type="text" name="tb[impression_other]" class="form-control"
                       value="{{ $__sc->impression_other }}" placeholder="ระบุว่าเป็นโรคอะไร เช่น ปอดอักเสบจากเชื้อแบคทีเรีย">
            </div>

            {{-- ตำแหน่งที่เป็น (นอกปอด) — เดิมลอยอยู่ท้ายส่วนส่งตรวจแล็บ ไม่เกี่ยวกับอะไรรอบข้าง (คลินิกขอ 2569-10-02)
                 ย้ายมาคู่ Impression และโผล่เฉพาะตอนเลือก "Extrapulmonary TB" (5) แบบเดียวกับช่อง "อื่นๆ ระบุ" --}}
            <div class="col-md-7 {{ (int) $__sc->impression === 5 || $__sc->impression_site ? '' : 'd-none' }}" id="tb-impression-site-box">
                <label class="form-label fw-bold">ตำแหน่งที่เป็น (นอกปอด)</label>
                <input type="text" name="tb[impression_site]" class="form-control"
                       value="{{ $__sc->impression_site }}" placeholder="เช่น ต่อมน้ำเหลือง">
            </div>

            {{-- ปุ่มเปิดบัตร: โผล่เมื่อสรุปว่าเป็น TB/LTBI แล้ว
                 เป็นลิงก์ ไม่ใช่ปุ่ม submit เพราะอยู่ในฟอร์มหลักของหน้าตรวจ --}}
            <div class="col-12">
                @if($__sc->tb_registration_id && $__sc->registration)
                    <a href="{{ route('tb.show', ['id' => $__sc->tb_registration_id, 'from' => 'visit', 'visit' => $visit->id, 'page' => request('page', 1)]) }}" class="btn btn-success">
                        <i class="fa fa-id-card"></i> เปิดบัตรที่ผูกไว้ ({{ $__sc->registration->tb_no ?: 'ไม่มีเลขทะเบียน' }})
                    </a>
                @else
                    <a id="tb-open-card"
                       href="{{ route('tb.create', ['patient_id' => $visit->patient_id, 'from_screening' => $visit->id]) }}"
                       class="btn btn-warning {{ $__sc->needsRegistration() ? '' : 'd-none' }}">
                        <i class="fa fa-id-card"></i> เปิดบัตรวัณโรคให้คนไข้รายนี้
                    </a>
                    <span class="text-muted small ms-2 {{ $__sc->needsRegistration() ? '' : 'd-none' }}" id="tb-open-hint">
                        กดบันทึกหน้าตรวจก่อน แล้วค่อยกดเปิดบัตร ระบบจะดึงประวัติจากใบคัดกรองไปให้
                    </span>
                @endif

                {{-- จำหน่าย (ลงผลการรักษา) จากหน้าตรวจ — ข้างปุ่มเปิดบัตร (คลินิกขอ 2569-10-27)
                     ไม่มี name (ไม่ไปกับปุ่ม Save ของหน้า) · ยิงด้วย fetch ที่ tb.discharge · แก้ทีหลังที่บัตร --}}
                @if($__tbActive && $__tbActive->isActive())
                    @php $__dcOpts = collect(\App\Models\TbRegistration::outcomeOptionsFor($__tbActive->registry_type))->except([\App\Models\TbRegistration::OUTCOME_VOID]); @endphp
                    <button type="button" class="btn btn-outline-danger ms-2 js-tb-dc-toggle">
                        <i class="fa fa-flag-checkered me-1"></i> จำหน่าย ({{ $__tbActive->cardName() }} {{ $__tbActive->tb_no ?: '#' . $__tbActive->id }})
                    </button>
                    <div class="border rounded p-2 mt-2 d-none js-tb-dc-box" data-no-dirty style="background:#fff5f5;border-color:#fca5a5 !important;"
                         data-url="{{ route('tb.discharge', $__tbActive->id) }}">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold mb-1">ผลการรักษา</label>
                                <select class="form-select form-select-sm js-tb-dc-outcome">
                                    <option value="">— เลือก —</option>
                                    @foreach($__dcOpts as $__k => $__l)
                                        <option value="{{ $__k }}">{{ $__l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">วันที่จำหน่าย</label>
                                <input type="text" class="form-control form-control-sm js-tb-dc-date" placeholder="วว/ดด/ปปปป"
                                       value="{{ \App\Utillity::db2thDate($visit->getRawOriginal('date')) }}">
                            </div>
                            <div class="col-md-3 d-grid">
                                <button type="button" class="btn btn-danger btn-sm js-tb-dc-save"><i class="fa fa-check me-1"></i> ยืนยันจำหน่าย</button>
                            </div>
                            <div class="col-12 small js-tb-dc-msg text-muted">
                                บันทึกลงบัตรทันที (ไม่ต้องกด Save ท้ายหน้า) · บัตรย้ายไปกลุ่ม "จบการรักษาแล้ว" · แก้/ยกเลิกได้ที่บัตร ช่อง "ผลการรักษา"
                            </div>
                        </div>
                    </div>
                @endif
<script>
(function () {
    if (window.__tbDcInit) return;
    window.__tbDcInit = true;
    document.addEventListener('click', function (e) {
        var t = e.target.closest && e.target.closest('.js-tb-dc-toggle');
        if (t) { var bx = t.parentNode.querySelector('.js-tb-dc-box'); if (bx) bx.classList.toggle('d-none'); return; }
        var s = e.target.closest && e.target.closest('.js-tb-dc-save');
        if (!s) return;
        var box = s.closest('.js-tb-dc-box'), sel = box.querySelector('.js-tb-dc-outcome'), dt = box.querySelector('.js-tb-dc-date'), msg = box.querySelector('.js-tb-dc-msg');
        if (!sel.value) { msg.className = 'col-12 small js-tb-dc-msg text-danger'; msg.textContent = 'เลือกผลการรักษาก่อน'; return; }
        if (!confirm('ยืนยันจำหน่ายบัตรนี้: ' + sel.options[sel.selectedIndex].text + ' วันที่ ' + dt.value + ' ?')) return;
        var fd = new FormData(); fd.append('outcome', sel.value); fd.append('outcome_date', dt.value);
        s.disabled = true; msg.className = 'col-12 small js-tb-dc-msg text-muted'; msg.textContent = 'กำลังบันทึก…';
        fetch(box.dataset.url, { method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'บันทึกไม่สำเร็จ (' + r.status + ')' }; }); })
            .then(function (j) {
                if (!j.ok) { msg.className = 'col-12 small js-tb-dc-msg text-danger'; msg.textContent = j.message || 'บันทึกไม่สำเร็จ'; s.disabled = false; return; }
                msg.className = 'col-12 small js-tb-dc-msg text-success fw-bold'; msg.textContent = '✓ ' + j.message;
                box.querySelectorAll('select, input, button').forEach(function (el) { el.disabled = true; });
                var tg = box.parentNode.querySelector('.js-tb-dc-toggle'); if (tg) { tg.disabled = true; tg.innerHTML = '<i class="fa fa-check me-1"></i> จำหน่ายแล้ว'; }
            })
            .catch(function () { msg.className = 'col-12 small js-tb-dc-msg text-danger'; msg.textContent = 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ ลองใหม่'; s.disabled = false; });
    });
})();
</script>
            </div>
        </div>
</div>
@endsection

@section('tbp_plan')
<div class="form-group mt-3">
    {{-- Plan ของใบคัดกรองวัณโรค — วางต่อจากการรักษา (คลินิกขอ 2569-10-01) ช่องเดิม tb[plan] --}}
    <label class="form-label fw-bold">Plan (วัณโรค)</label>
    <textarea name="tb[plan]" rows="2" class="form-control">{{ $__sc->plan }}</textarea>
</div>
{{-- บันทึกทางการพยาบาล (คลินิกขอ 2569-10-14) — ช่องอิสระของพยาบาล · ต้องรัน migration 2026_10_14_100000 ก่อนถึงโผล่ --}}
@if(\App\Models\TbScreening::nursingNoteReady())
    <div class="form-group mt-3">
        <label class="form-label fw-bold"><i class="fa fa-user-nurse me-1"></i> บันทึกทางการพยาบาล</label>
        <textarea name="tb[nursing_note]" rows="3" class="form-control js-autogrow"
                  placeholder="เช่น ให้สุขศึกษาการกินยา · ติดตามอาการข้างเคียง · ประสานส่งต่อ">{{ $__sc->nursing_note }}</textarea>
    </div>
@endif
{{-- ให้คำปรึกษา HIV (Pre/Post-test) ย้ายมาต่อบันทึกทางการพยาบาล (2569-10-14 — เดิมอยู่ในการ์ดวินิจฉัย) --}}
<div class="mt-3">@include('visit.form._tb_hiv_counsel', ['visit' => $visit])</div>
@endsection

@section('tbp_scripts')
<script>
(function () {
    if (window.__tbScreeningInit) return;
    window.__tbScreeningInit = true;

    // คะแนนรวมอัปเดตทันทีที่ติ๊ก — พยาบาลจะได้รู้เลยว่าเข้าเกณฑ์ส่งตรวจไหม
    function recalc() {
        var total = 0;
        document.querySelectorAll('.js-tb-sym').forEach(function (el) {
            if (el.checked) total += parseInt(el.dataset.score, 10) || 0;
        });
        var box = document.getElementById('tb-score');
        var msg = document.getElementById('tb-score-msg');
        if (!box) return;
        box.textContent = total;
        if (total >= 3) {
            box.style.color = '#dc3545';
            msg.textContent = '⚠ เข้าเกณฑ์ผู้มีอาการสงสัยวัณโรค — ต้องส่งตรวจต่อ';
            msg.style.color = '#dc3545';
        } else {
            box.style.color = '#b45309';
            msg.textContent = total > 0 ? 'ยังไม่เข้าเกณฑ์สงสัยวัณโรค' : '';
            msg.style.color = '#6c757d';
        }
    }
    document.addEventListener('change', function (e) {
        if (e.target.classList && e.target.classList.contains('js-tb-sym')) recalc();
    });
    recalc();

    // ผู้สัมผัสที่ยังไม่มีเลข HN = ยังไม่ได้มาตรวจ — ไฮไลต์ไว้ให้เห็นว่าต้องตามใคร
    function markPending() {
        var pending = 0;
        document.querySelectorAll('#tb-member-table tbody tr').forEach(function (tr) {
            var nameEl = tr.querySelector('input[name*="[name]"]');
            var hnEl   = tr.querySelector('.js-member-hn');
            if (!nameEl || !hnEl) return;
            var waiting = nameEl.value.trim() !== '' && hnEl.value.trim() === '';
            tr.style.background = waiting ? '#fff5f5' : '';
            hnEl.style.borderColor = waiting ? '#dc3545' : '';
            if (waiting) pending++;
        });
        var box = document.getElementById('tb-member-pending');
        if (box) box.textContent = pending ? '· ยังไม่มาตรวจ ' + pending + ' คน' : '';
    }
    document.addEventListener('input', function (e) {
        if (e.target.closest && e.target.closest('#tb-member-table')) markPending();
    });
    markPending();

    // บล็อก Form 2 โผล่เมื่อระบุว่า "มีประวัติสัมผัสผู้ป่วยวัณโรค"
    var ctSel = document.querySelector('select[name="tb[contact_tb]"]');
    var form2 = document.getElementById('tb-form2');
    if (ctSel && form2) {
        ctSel.addEventListener('change', function () {
            form2.classList.toggle('d-none', this.value !== '1');
        });
    }

    // ช่องวันที่คัดกรองใช้คลาส .date เพื่อให้ตัวผูกปฏิทิน พ.ศ. ของหน้าตรวจจัดการให้เอง
    // (สคริปต์ในโซนนี้รันตั้งแต่ตอนแปลง HTML ซึ่งเร็วกว่าที่ปลั๊กอิน datepicker จะโหลดเสร็จ)

    // BMI / O2 sat ย้ายไปอยู่ในการ์ดสัญญาณชีพแล้ว — ไม่มีอะไรต้องคิดตรงนี้

    // เลือก Impression เป็น LTBI/TB แล้วปุ่มเปิดบัตรค่อยโผล่ · เลือก "อื่นๆ" แล้วช่องระบุโรคโผล่
    var imp = document.getElementById('tb-impression');
    if (imp) {
        imp.addEventListener('change', function () {
            var ob = document.getElementById('tb-impression-other-box');
            if (ob) ob.classList.toggle('d-none', this.value !== '6');
            var sb = document.getElementById('tb-impression-site-box');
            if (sb) sb.classList.toggle('d-none', this.value !== '5');
            var need = ['2', '3', '4', '5'].indexOf(this.value) !== -1;
            ['tb-open-card', 'tb-open-hint'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.classList.toggle('d-none', !need);
            });
        });
    }
})();
</script>

<script>
// มาร์คตำแหน่งบนรูปปอด (ใบคัดกรองวัณโรค) — เก็บเป็น JSON ในช่อง tb[lung_marks]
// รูปพื้นหลังเป็นโครงปอดพร้อมเส้นแบ่งกลีบ ไม่มีสีพื้น จะได้เห็นจุดที่มาร์คชัด
(function () {
    var canvas = document.getElementById('lung-canvas');
    var out    = document.getElementById('lung-marks');
    if (!canvas || !out) { return; }

    var ctx   = canvas.getContext('2d');
    var img   = new Image();
    var marks = [];
    var drawing = false;

    try { marks = JSON.parse(out.value || '[]') || []; } catch (e) { marks = []; }

    img.onload = redraw;
    img.src = '{{ asset("images/tb/lungs.png") }}';

    function redraw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (img.complete && img.naturalWidth) { ctx.drawImage(img, 0, 0, canvas.width, canvas.height); }
        marks.forEach(function (m) {
            ctx.strokeStyle = ctx.fillStyle = m.color || '#e63946';
            if (m.type === 'free' && m.points && m.points.length > 1) {
                ctx.lineWidth = m.size || 20;
                ctx.lineCap = 'round';
                ctx.beginPath();
                ctx.moveTo(m.points[0].x, m.points[0].y);
                m.points.forEach(function (p) { ctx.lineTo(p.x, p.y); });
                ctx.stroke();
            } else if (m.type === 'text' && m.text) {
                // ข้อความบนรูป — ใส่พื้นขาวจาง ๆ ไว้ข้างหลัง จะได้อ่านออกแม้ทับเส้นปอด
                var fs = Math.max(18, (m.size || 20) * 1.6);
                ctx.font = 'bold ' + fs + 'px "Sarabun", Arial, sans-serif';
                ctx.textBaseline = 'middle';
                var w = ctx.measureText(m.text).width;
                ctx.save();
                ctx.globalAlpha = 0.75;
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(m.x - 4, m.y - fs / 2 - 3, w + 8, fs + 6);
                ctx.restore();
                ctx.fillStyle = m.color || '#e63946';
                ctx.fillText(m.text, m.x, m.y);
            } else if (m.x !== undefined) {
                ctx.beginPath();
                ctx.arc(m.x, m.y, (m.size || 20) / 2 + 2, 0, Math.PI * 2);
                ctx.fill();
            }
        });
        out.value = marks.length ? JSON.stringify(marks) : '';
    }

    function pos(e) {
        var r = canvas.getBoundingClientRect();
        var t = e.touches ? e.touches[0] : e;
        return {
            x: (t.clientX - r.left) * (canvas.width / r.width),
            y: (t.clientY - r.top) * (canvas.height / r.height),
        };
    }

    function mode()  { return document.getElementById('lung-mode').value; }
    function color() { return document.getElementById('lung-color').value; }
    function size()  { return parseInt(document.getElementById('lung-size').value, 10) || 20; }

    canvas.addEventListener('mousedown', function (e) {
        var p = pos(e);
        if (mode() === 'free') {
            drawing = true;
            marks.push({ type: 'free', color: color(), size: size(), points: [p] });
        } else if (mode() === 'text') {
            var t = (window.prompt('พิมพ์ข้อความที่จะเขียนลงบนรูป เช่น RUL crepitation') || '').trim();
            if (!t) { return; }
            marks.push({ type: 'text', color: color(), size: size(), x: p.x, y: p.y, text: t });
        } else {
            marks.push({ type: 'point', color: color(), size: size(), x: p.x, y: p.y });
        }
        redraw();
    });

    canvas.addEventListener('mousemove', function (e) {
        if (!drawing) { return; }
        marks[marks.length - 1].points.push(pos(e));
        redraw();
    });

    ['mouseup', 'mouseleave'].forEach(function (ev) {
        canvas.addEventListener(ev, function () { drawing = false; });
    });

    document.getElementById('lung-undo').addEventListener('click', function () { marks.pop(); redraw(); });
    document.getElementById('lung-clear').addEventListener('click', function () {
        if (!marks.length || confirm('ล้างจุดที่มาร์คบนรูปปอดทั้งหมด?')) { marks = []; redraw(); }
    });

    redraw();
})();
</script>

<script>
// สลับ ครั้งแรก/Walk-in กับ F/U (คลินิกขอ 2569-10-02)
// F/U = งานไปต่อที่บัตร TB 01 → โชว์กล่องปุ่มเปิดบัตร + สรุปผู้สัมผัส แล้วซ่อนส่วนคัดกรอง (ซักประวัติ/ตรวจร่างกาย/เอกซเรย์/Impression)
// ซ่อนด้วย d-none เท่านั้น ไม่ disable ไม่ล้างค่า — ค่าเดิมยังถูกส่งไปพร้อมฟอร์ม ไม่มีอะไรหาย
(function () {
    // F/U ซ่อนแค่คะแนนคัดกรองอาการ — ซักประวัติ/ตรวจร่างกาย/X-ray/Impression ต้องบันทึกทุกครั้ง (คลินิกขอ 2569-10-08)
    function apply() {
        var fu = document.querySelector('.js-tb-visit-type:checked');
        var isFu = !!(fu && fu.value === '{{ \App\Models\TbScreening::VISIT_FU }}');
        document.querySelectorAll('.js-tb-score-zone').forEach(function (el) { el.classList.toggle('d-none', isFu); });
        document.querySelectorAll('.js-tb-fu-zone').forEach(function (el) { el.classList.toggle('d-none', !isFu); });
    }
    document.addEventListener('change', function (e) {
        if (e.target.classList && e.target.classList.contains('js-tb-visit-type')) { apply(); }
    });
    apply();
})();
</script>

{{-- ช่องส่งตรวจทางห้องปฏิบัติการ: ซ่อน/เปิดช่องผล · เพิ่มแถวสิ่งส่งตรวจ · ส่งเฉพาะกลุ่มที่แก้ (ใช้ร่วมกับบัตร TB 01) --}}
@include('tb._lab_fields_js')
<script>
// ผลเอกซเรย์หลายแถว (หลายท่าถ่ายในวันเดียว) — ปุ่มเป็น type=button เพราะอยู่ในฟอร์มหลักของหน้าตรวจ
// ผูกแบบ delegate ที่ document — สคริปต์นี้อยู่ในหน้าที่โหลดเต็ม
(function () {
    if (window.__tbCxrRowsInit) return;
    window.__tbCxrRowsInit = true;
    var seq = 1000;   // เลขแถวใหม่ไม่ชนของเดิม — ฝั่งเซิร์ฟเวอร์ไม่สนลำดับเลข
    document.addEventListener('click', function (e) {
        var rows = document.getElementById('tb-cxr-rows');
        if (!rows || !e.target.closest) return;

        if (e.target.closest('#tb-cxr-add')) {
            var tpl = rows.querySelector('.tb-cxr-row:last-child');
            if (!tpl) return;
            var row = tpl.cloneNode(true), i = seq++;
            row.querySelectorAll('[name]').forEach(function (el) {
                el.name = el.name.replace(/^tb_cxr\[[^\]]*\]/, 'tb_cxr[' + i + ']');
                if (el.tagName === 'SELECT') { el.selectedIndex = 0; } else { el.value = ''; }
            });
            rows.appendChild(row);
            row.querySelector('select').focus();
            return;
        }

        var del = e.target.closest('.js-cxr-del');
        if (del && rows.contains(del)) {
            var r = del.closest('.tb-cxr-row');
            // แถวสุดท้ายล้างค่าแทนการลบ — ชื่อ tb_cxr ต้องอยู่ในฟอร์มเสมอ ไม่งั้นระบบจะไม่รู้ว่าล้างผลทิ้ง
            if (rows.querySelectorAll('.tb-cxr-row').length <= 1) {
                r.querySelectorAll('select, input').forEach(function (el) {
                    if (el.tagName === 'SELECT') { el.selectedIndex = 0; } else { el.value = ''; }
                });
            } else {
                r.parentNode.removeChild(r);
            }
        }
    });
})();
</script>
@include('tb._add_row_js')
@include('tb._filled_js')
@endsection

