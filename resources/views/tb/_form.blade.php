{{-- ช่องกรอกบัตร TB 01 — ใช้ร่วมกันทั้งหน้าเปิดบัตรใหม่และหน้าแก้ไขบัตร
     ตัวแปรที่ต้องส่งมา: $reg (TbRegistration ใหม่หรือของเดิม)
     ไฟล์นี้อยู่ "ใน" <form> ของหน้าที่ include ห้ามเปิด <form> ซ้ำ (ดู CLAUDE.md ข้อ 1) --}}

@php
    $d = fn ($v) => $v ? \App\Utillity::db2thDate($v) : '';
    $symptomsOn = collect($reg->symptoms ?? [])->keyBy('key');
    // ข้อความจากใบคัดกรอง OPD/ทะเบียนคนไข้ — เสนอปุ่ม "ดึงจาก OPD" ใต้ช่องที่ยังว่าง (2569-10-19)
    $__opdText = \App\Models\TbRegistration::opdCardText($reg->patient_id ?: optional($patient ?? null)->id);
    // บัตรวัณโรคแฝงหน้าตาแยกจาก TB 01 (2569-10-27) — ช่องของ TB ซ่อน ช่องของ LTBI โผล่ (สลับสดตามประเภททะเบียน)
    $__isLtbi = (int) old('registry_type', $reg->registry_type ?: 1) === \App\Models\TbRegistration::TYPE_LTBI;
@endphp

<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label fw-bold">ประเภททะเบียน <span class="text-danger">*</span></label>
        <select name="registry_type" class="form-select js-reg-type" required>
            @foreach(\App\Models\TbRegistration::$typeOption as $k => $label)
                <option value="{{ $k }}" {{ (int) old('registry_type', $reg->registry_type ?: 1) === $k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">เลขทะเบียน (จาก NTIP)</label>
        <input type="text" name="tb_no" class="form-control" value="{{ old('tb_no', $reg->tb_no) }}" placeholder="เช่น 1/67">
        <div class="form-text">คีย์เองจากระบบ NTIP</div>
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">วันขึ้นทะเบียนรักษา</label>
        <input type="text" name="register_date" class="form-control tb-date" autocomplete="off"
               value="{{ old('register_date', $d($reg->register_date)) }}" placeholder="วว/ดด/ปปปป">
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">วันเริ่มกินยา</label>
        <input type="text" name="treatment_start_date" class="form-control tb-date" autocomplete="off"
               value="{{ old('treatment_start_date', $d($reg->treatment_start_date)) }}" placeholder="วว/ดด/ปปปป">
        <div class="form-text">ใช้นับว่าตอนนี้รักษาเดือนที่เท่าไหร่</div>
    </div>

    <div class="col-12"><hr class="my-1"></div>

    {{-- ===== บัตรวัณโรคแฝง (LTBI) — ช่องเฉพาะ LTBI (คลินิกขอ 2569-10-27) ·
         เลือกประเภททะเบียนแล้วสลับกล่องด้วย JS ท้ายไฟล์ (.js-ltbi-only / .js-tb-only) ===== --}}
    @if(\App\Models\TbRegistration::ltbiReady())
        <div class="col-12 js-ltbi-only {{ $__isLtbi ? '' : 'd-none' }}">
            <div class="border rounded p-3" style="background:#faf5ff;border-color:#c4b5fd !important;">
                <div class="fw-bold mb-2" style="color:#6d28d9;"><i class="fa fa-shield-virus me-1"></i> ข้อมูลวัณโรคแฝง (LTBI) · ยาป้องกัน (TPT)</div>
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-bold">กลุ่มเป้าหมาย TPT</label>
                        <select name="tpt_target" class="form-select">
                            <option value="">— เลือก —</option>
                            @foreach(\App\Models\TbRegistration::$tptTargetOption as $k => $label)
                                <option value="{{ $k }}" {{ (int) old('tpt_target', $reg->tpt_target) === $k ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="tpt_target_other" class="form-control form-control-sm mt-1" maxlength="255"
                               value="{{ old('tpt_target_other', $reg->tpt_target_other) }}" placeholder="อื่น ๆ ระบุ / รายละเอียดเพิ่ม">
                    </div>
                    <div class="col-md-7">
                        <label class="form-label fw-bold">ผลตรวจการติดเชื้อ (TST / IGRA)</label>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <select name="ltbi_test" class="form-select">
                                    <option value="">— วิธีตรวจ —</option>
                                    @foreach(\App\Models\TbRegistration::$ltbiTestOption as $k => $label)
                                        <option value="{{ $k }}" {{ (int) old('ltbi_test', $reg->ltbi_test) === $k ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="ltbi_test_date" class="form-control tb-date" autocomplete="off" placeholder="วันที่ตรวจ"
                                       value="{{ old('ltbi_test_date', $d($reg->ltbi_test_date)) }}">
                            </div>
                            <div class="col-md-3">
                                <select name="ltbi_test_result" class="form-select">
                                    <option value="">— ผล —</option>
                                    @foreach(\App\Models\TbRegistration::$ltbiTestResultOption as $k => $label)
                                        <option value="{{ $k }}" {{ (int) old('ltbi_test_result', $reg->ltbi_test_result) === $k ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="input-group">
                                    <input type="number" step="0.1" min="0" max="99" name="tst_mm" class="form-control" placeholder="TST"
                                           value="{{ old('tst_mm', $reg->tst_mm !== null ? rtrim(rtrim((string) $reg->tst_mm, '0'), '.') : '') }}" title="ขนาดรอยนูน TST (มม.)">
                                    <span class="input-group-text">มม.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold">CXR คัดแยกวัณโรคระยะลุกลาม (ก่อนเริ่ม TPT)</label>
                        <div class="row g-2">
                            <div class="col-md-2">
                                <input type="text" name="cxr_date" class="form-control tb-date" autocomplete="off" placeholder="วันที่ถ่าย"
                                       value="{{ old('cxr_date', $d($reg->cxr_date)) }}">
                            </div>
                            <div class="col-md-4">
                                <select name="cxr_result" class="form-select js-ltbi-cxr">
                                    <option value="">— ผล CXR —</option>
                                    @foreach(\App\Models\TbRegistration::$cxrResultOption as $k => $label)
                                        <option value="{{ $k }}" {{ (int) old('cxr_result', $reg->cxr_result) === $k ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <input type="text" name="cxr_note" class="form-control" maxlength="255" placeholder="รายละเอียดผล CXR (ถ้ามี)"
                                       value="{{ old('cxr_note', $reg->cxr_note) }}">
                            </div>
                        </div>
                        <div class="small text-danger mt-1 js-ltbi-cxr-warn {{ (int) old('cxr_result', $reg->cxr_result) === 3 ? '' : 'd-none' }}">
                            <i class="fa fa-triangle-exclamation me-1"></i> CXR สงสัยวัณโรค — ต้องตรวจแยกวัณโรคระยะลุกลามก่อน ห้ามเริ่ม TPT จนกว่าจะตัดวัณโรคออก
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="col-md-4 js-tb-only {{ $__isLtbi ? 'd-none' : '' }}">
        <label class="form-label fw-bold">การจำแนกผู้ป่วย</label>
        @foreach(\App\Models\TbRegistration::$classificationOption as $k => $label)
            <div class="form-check">
                <input class="form-check-input" type="radio" name="classification" id="cls{{ $k }}" value="{{ $k }}"
                       {{ (int) old('classification', $reg->classification) === $k ? 'checked' : '' }}>
                <label class="form-check-label" for="cls{{ $k }}">{{ $label }}</label>
            </div>
        @endforeach
        <input type="text" name="extrapulmonary_site" class="form-control form-control-sm mt-1"
               value="{{ old('extrapulmonary_site', $reg->extrapulmonary_site) }}" placeholder="นอกปอด ระบุตำแหน่ง">
        <script>
        // ประเภททะเบียน = วัณโรคแฝง → หน้าตาบัตร LTBI: ซ่อนช่องของ TB (การจำแนก/ขึ้นทะเบียนรักษา/ตารางเสมหะ/จำนวนผู้สัมผัส)
        // โผล่กล่อง LTBI · ผลการรักษาสลับเป็นชุด TPT (ตัวที่ซ่อน disabled = ไม่ส่งไปบันทึก) · การจำแนกติ๊ก LTBI ให้
        (function () {
            var sel = document.querySelector('.js-reg-type'), ltbi = document.getElementById('cls3');
            if (!sel) return;
            var root = sel.closest('form') || document;
            sel.addEventListener('change', function () {
                var on = this.value === '{{ \App\Models\TbRegistration::TYPE_LTBI }}';
                root.querySelectorAll('.js-ltbi-only').forEach(function (e) { e.classList.toggle('d-none', !on); if (e.tagName === 'SELECT') e.disabled = !on; });
                root.querySelectorAll('.js-tb-only').forEach(function (e) { e.classList.toggle('d-none', on); if (e.tagName === 'SELECT' && e.name === 'outcome') e.disabled = on; });
                if (ltbi) { if (on) ltbi.checked = true; else if (ltbi.checked) ltbi.checked = false; }
            });
            root.addEventListener('change', function (e) {
                if (!e.target.classList || !e.target.classList.contains('js-ltbi-cxr')) return;
                var w = root.querySelector('.js-ltbi-cxr-warn'); if (w) w.classList.toggle('d-none', e.target.value !== '3');
            });
        })();
        </script>
    </div>

    {{-- รหัสโรค ICD-10 ของการขึ้นทะเบียนรักษา — เสริมจาก "การจำแนกผู้ป่วย" ไม่ได้มาแทน
         เปิดบัตรจากใบคัดกรอง -> ดึงรหัสที่หมอเลือกไว้ในใบคัดกรองมาตั้งต้นให้ ไม่ต้องเลือกซ้ำ --}}
    @php
        $__regIcd = old('reg_icd10') !== null
            ? array_filter((array) old('reg_icd10'))
            : \App\Models\Icd10Code::codesFor(\App\Models\TbRegistration::icd10Owner(), $reg->id);
        if (! $reg->exists && ! $__regIcd && request('from_screening')) {
            // ต้องเป็นใบคัดกรองของคนไข้คนเดียวกัน — เลข visit ใน URL พิมพ์ผิด/แก้เองได้ (รีวิว 2569-09-30)
            $__pid    = $reg->patient_id ?: (isset($patient) ? optional($patient)->id : null);
            $__fromSc = $__pid
                ? \App\Models\TbScreening::where('visit_id', (int) request('from_screening'))
                    ->where('patient_id', $__pid)->value('id')
                : null;
            $__regIcd = $__fromSc ? \App\Models\Icd10Code::codesFor(\App\Models\TbScreening::icd10Owner(), $__fromSc) : [];
        }
    @endphp
    <div class="col-12">
        <label class="form-label fw-bold">รหัสโรค ICD-10</label>
        @include('partials.icd10_picker', ['name' => 'reg_icd10[]', 'selected' => $__regIcd])
    </div>

    <div class="col-md-4 js-tb-only {{ $__isLtbi ? 'd-none' : '' }}">
        <label class="form-label fw-bold">การขึ้นทะเบียนรักษา</label>
        <select name="register_type" class="form-select">
            <option value="">— เลือก —</option>
            @foreach(\App\Models\TbRegistration::$registerTypeOption as $k => $label)
                <option value="{{ $k }}" {{ (int) old('register_type', $reg->register_type) === $k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <input type="text" name="register_type_other" class="form-control form-control-sm mt-1"
               value="{{ old('register_type_other', $reg->register_type_other) }}" placeholder="อื่นๆ ระบุ">
    </div>

    <div class="col-md-4 js-tb-only {{ $__isLtbi ? 'd-none' : '' }}">
        <label class="form-label fw-bold">ตารางตรวจเสมหะ</label>
        <select name="sputum_schedule" class="form-select">
            <option value="">— เลือก —</option>
            @foreach(\App\Models\TbRegistration::$sputumScheduleOption as $k => $label)
                <option value="{{ $k }}" {{ (int) old('sputum_schedule', $reg->sputum_schedule) === $k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <div class="form-text">เดือนที่ต้องตรวจจะถูกไฮไลต์ในตารางผลเสมหะ</div>
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold">สูตรยา</label>
        @php
            // สูตรยาครบทุกกลุ่ม + Other พิมพ์เอง (คลินิกขอ 2569-10-14 — เดิมเสนอเฉพาะสูตรตามประเภททะเบียน 2 สูตร)
            // ช่องที่บันทึกยังเป็น name="regimen" ตัวเดิม — dropdown แค่เติมค่าให้ · ค่าเดิมที่ไม่อยู่ในรายการ = Other
            $__regimens = \App\Models\TbRegimen::where('active', true)->orderBy('sort')->get();
            $__regVal   = (string) old('regimen', $reg->regimen);
            $__regIn    = $__regVal !== '' && $__regimens->contains('regimen', $__regVal);
        @endphp
        <select class="form-select" id="tb-regimen-pick"
                onchange="var t=document.getElementById('tb-regimen-text'); if(this.value==='__other'){t.classList.remove('d-none'); t.value=''; t.focus();} else {t.classList.add('d-none'); t.value=this.value;}">
            <option value="" {{ $__regVal === '' ? 'selected' : '' }}>— เลือกสูตรยา —</option>
            @foreach($__regimens->groupBy(fn ($r) => $r->category ?: 'อื่น ๆ') as $__cat => $__list)
                <optgroup label="{{ $__cat }}">
                    @foreach($__list->unique('regimen') as $__rg)
                        <option value="{{ $__rg->regimen }}" {{ $__regIn && $__regVal === $__rg->regimen ? 'selected' : '' }}>{{ $__rg->name ?: $__rg->regimen }}{{ $__rg->name && $__rg->name !== $__rg->regimen ? ' — ' . $__rg->regimen : '' }}</option>
                    @endforeach
                </optgroup>
            @endforeach
            <option value="__other" {{ $__regVal !== '' && ! $__regIn ? 'selected' : '' }}>Other — พิมพ์เอง</option>
        </select>
        <input type="text" name="regimen" id="tb-regimen-text" class="form-control mt-1 {{ $__regVal !== '' && ! $__regIn ? '' : 'd-none' }}"
               value="{{ $__regVal }}" maxlength="255" placeholder="พิมพ์สูตรยาเอง เช่น 2HRZE/10HR">
        <div class="form-text">เลือกจากสูตรมาตรฐาน {{ $__regimens->unique('regimen')->count() }} สูตร หรือ Other แล้วพิมพ์เอง</div>
    </div>

    <div class="col-md-2">
        <label class="form-label fw-bold">ส่วนสูง (ซม.)</label>
        <input type="number" step="0.1" name="height" class="form-control" value="{{ old('height', $reg->height) }}">
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">กำกับการกินยา</label>
        <select name="dot" class="form-select">
            <option value="">— เลือก —</option>
            <option value="1" {{ old('dot', $reg->dot) === 1 || old('dot', $reg->dot) === '1' ? 'selected' : '' }}>DOT (มีผู้กำกับ)</option>
            <option value="0" {{ (old('dot', $reg->dot) === 0 || old('dot', $reg->dot) === '0') && $reg->dot !== null ? 'selected' : '' }}>No DOT</option>
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">ผู้กำกับการกินยา</label>
        <select name="dot_by" class="form-select">
            <option value="">— เลือก —</option>
            @foreach(\App\Models\TbRegistration::$dotByOption as $k => $label)
                <option value="{{ $k }}" {{ (int) old('dot_by', $reg->dot_by) === $k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <input type="text" name="dot_by_other" class="form-control form-control-sm mt-1"
               value="{{ old('dot_by_other', $reg->dot_by_other) }}" placeholder="อื่นๆ ระบุ">
    </div>

    <div class="col-12"><hr class="my-1"></div>

    {{-- อาการแรกรับ + ระยะเวลา ตามช่องในบัตร --}}
    <div class="col-md-6">
        <label class="form-label fw-bold">อาการแรกรับ</label>
        <div class="row g-1">
            @foreach(\App\Models\TbRegistration::$symptomOption as $key => $label)
                @php $on = $symptomsOn->get($key); @endphp
                <div class="col-12 d-flex align-items-center gap-2">
                    <div class="form-check mb-0" style="min-width:12rem;">
                        <input class="form-check-input" type="checkbox" id="sym_{{ $key }}"
                               name="symptoms[{{ $key }}][on]" value="1" {{ $on ? 'checked' : '' }}>
                        <label class="form-check-label" for="sym_{{ $key }}">{{ $label }}</label>
                    </div>
                    {{-- เสมหะสีอะไร / อื่นๆ ระบุ (คลินิกขอ 2569-10-19) --}}
                    @if(isset(\App\Models\TbRegistration::$symptomDetail[$key]))
                        <input type="text" name="symptoms[{{ $key }}][detail]" class="form-control form-control-sm" style="max-width:11rem;"
                               maxlength="100" value="{{ $on['detail'] ?? '' }}" placeholder="{{ \App\Models\TbRegistration::$symptomDetail[$key] }}">
                    @endif
                    {{-- ระยะเวลา: ตัวเลข + หน่วย — ของเดิมมีแต่ช่องตัวเลข บันทึกแล้วไม่รู้ว่าวันหรือเดือน --}}
                    <div class="input-group input-group-sm" style="max-width:16rem;">
                        <input type="text" name="symptoms[{{ $key }}][duration]" class="form-control"
                               value="{{ $on['duration'] ?? '' }}" placeholder="ระยะเวลา">
                        <select name="symptoms[{{ $key }}][unit]" class="form-select" style="max-width:7rem;">
                            @foreach(\App\Models\TbRegistration::$durationUnitOption as $u => $uLabel)
                                <option value="{{ $u }}" {{ ($on['unit'] ?? 'month') === $u ? 'selected' : '' }}>{{ $uLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="col-md-6">
        <div class="row g-2">
            <div class="col-12">
                <label class="form-label fw-bold">ประวัติการรักษาวัณโรคมาก่อน</label>
                <textarea name="previous_tb_history" rows="2" class="form-control">{{ old('previous_tb_history', $reg->previous_tb_history) }}</textarea>
                @if(trim((string) old('previous_tb_history', $reg->previous_tb_history)) === '' && ($__opdText['previous_tb_history'] ?? '') !== '')
                    <button type="button" class="btn btn-link btn-sm p-0" data-target="tb-f-previous_tb_history"
                            onclick="var t=this.parentNode.querySelector('textarea[name=previous_tb_history]'); t.value={{ json_encode($__opdText['previous_tb_history']) }}; t.dispatchEvent(new Event('input',{bubbles:true})); this.remove();">
                        ดึงจาก OPD: {{ \Illuminate\Support\Str::limit($__opdText['previous_tb_history'], 80) }}
                    </button>
                @endif
            </div>
            <div class="col-12">
                <label class="form-label fw-bold">ประวัติการสัมผัสผู้ป่วยวัณโรค</label>
                <textarea name="contact_history" rows="2" class="form-control">{{ old('contact_history', $reg->contact_history) }}</textarea>
                @if(trim((string) old('contact_history', $reg->contact_history)) === '' && ($__opdText['contact_history'] ?? '') !== '')
                    <button type="button" class="btn btn-link btn-sm p-0" data-target="tb-f-contact_history"
                            onclick="var t=this.parentNode.querySelector('textarea[name=contact_history]'); t.value={{ json_encode($__opdText['contact_history']) }}; t.dispatchEvent(new Event('input',{bubbles:true})); this.remove();">
                        ดึงจาก OPD: {{ \Illuminate\Support\Str::limit($__opdText['contact_history'], 80) }}
                    </button>
                @endif
            </div>
            @if(\App\Services\TbContacts::ready())
                {{-- ผู้ป่วยต้นเหตุ (index case) — บัตร LTBI / ผู้สัมผัสที่ป่วยเอง ลิงก์ไปบัตรของ index (คลินิกขอ 2569-10-22)
                     ใส่ HN หรือเลขทะเบียน TB ของ index · ว่าง = ไม่ผูก · บัตร index เห็นชื่อคนนี้ในรายชื่อผู้สัมผัส --}}
                @php
                    $__idx = $reg->index_registration_id ? \App\Models\TbRegistration::with('patient')->find($reg->index_registration_id) : null;
                    $__idxRef = old('index_ref', $__idx ? ($__idx->tb_no ?: optional($__idx->patient)->code) : '');
                @endphp
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="tb-f-index_ref">ผู้ป่วยต้นเหตุ (Index case)</label>
                    <input type="text" name="index_ref" id="tb-f-index_ref" class="form-control" value="{{ $__idxRef }}"
                           placeholder="HN หรือเลขทะเบียน TB ของผู้ป่วยที่สัมผัส" maxlength="30">
                    <div class="form-text">
                        @if($__idx)
                            <a href="{{ route('tb.show', $__idx->id) }}"><i class="fa fa-link me-1"></i>{{ \App\Services\TbContacts::label($__idx) }}</a>
                        @else
                            สำหรับบัตร LTBI / ผู้สัมผัส — ผูกแล้วบัตรของผู้ป่วยต้นเหตุจะเห็นคนนี้ในรายชื่อผู้สัมผัส
                        @endif
                    </div>
                </div>
            @endif
            <div class="col-md-6">
                <label class="form-label fw-bold">โรคประจำตัว</label>
                <textarea name="other_disease" rows="2" class="form-control">{{ old('other_disease', $reg->other_disease) }}</textarea>
                @if(trim((string) old('other_disease', $reg->other_disease)) === '' && ($__opdText['other_disease'] ?? '') !== '')
                    <button type="button" class="btn btn-link btn-sm p-0" data-target="tb-f-other_disease"
                            onclick="var t=this.parentNode.querySelector('textarea[name=other_disease]'); t.value={{ json_encode($__opdText['other_disease']) }}; t.dispatchEvent(new Event('input',{bubbles:true})); this.remove();">
                        ดึงจาก OPD: {{ \Illuminate\Support\Str::limit($__opdText['other_disease'], 80) }}
                    </button>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">ประวัติแพ้ยา</label>
                <textarea name="drug_allergy" rows="2" class="form-control">{{ old('drug_allergy', $reg->drug_allergy) }}</textarea>
                @if(trim((string) old('drug_allergy', $reg->drug_allergy)) === '' && ($__opdText['drug_allergy'] ?? '') !== '')
                    <button type="button" class="btn btn-link btn-sm p-0" data-target="tb-f-drug_allergy"
                            onclick="var t=this.parentNode.querySelector('textarea[name=drug_allergy]'); t.value={{ json_encode($__opdText['drug_allergy']) }}; t.dispatchEvent(new Event('input',{bubbles:true})); this.remove();">
                        ดึงจาก OPD: {{ \Illuminate\Support\Str::limit($__opdText['drug_allergy'], 80) }}
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12"><hr class="my-1"></div>

    {{-- ====== ผู้สัมผัสโรค — แยกออกมาเป็นกล่องของตัวเอง (คลินิกขอ 2569-09-24)
         เดิมยัดอยู่แถวเดียวกับ BCG ช่องเบียดกันจนไม่รู้ว่าเลขไหนคือ "ทั้งหมด" เลขไหนคือ "ตรวจแล้ว"
         ชื่อช่องยังเป็นชื่อเดิมทุกช่อง ของที่บันทึกไว้แล้วไม่กระทบ ====== --}}
    <div class="col-12 js-tb-only {{ $__isLtbi ? 'd-none' : '' }}">
        <div class="border rounded p-3" style="background:#f6faff; border-color:#b6d4fe !important;">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <div class="fw-bold" style="color:#0a58ca;">
                    <i class="fa fa-people-group me-1"></i> ผู้สัมผัสโรค / ได้รับการตรวจคัดกรอง
                </div>
                <div class="small text-muted">
                    ช่อง <b>ทั้งหมด</b> = ผู้สัมผัสที่พบ · ช่อง <b>ตรวจแล้ว</b> = ที่มาตรวจคัดกรองแล้ว
                </div>

                {{-- ช่องว่างเปล่าแปลได้สองอย่าง: ยังไม่ได้ถาม หรือถามแล้วไม่มีจริง ๆ
                     ติ๊กช่องนี้ = ยืนยันว่าถามแล้วและไม่มีผู้สัมผัสร่วมบ้าน (คลินิกขอ 2569-09-28) --}}
                <div class="form-check ms-auto">
                    <input class="form-check-input" type="checkbox" id="no_household_contact" name="no_household_contact"
                           value="1" {{ old('no_household_contact', $reg->no_household_contact) ? 'checked' : '' }}>
                    <label class="form-check-label fw-bold" for="no_household_contact">
                        ยืนยันว่า <span class="text-danger">ไม่มีผู้สัมผัสร่วมบ้าน</span>
                    </label>
                </div>
            </div>

            @if($reg->no_household_contact)
                <div class="small text-muted mb-2">
                    <i class="fa fa-circle-check text-success me-1"></i>
                    บันทึกไว้ว่าถามแล้วและไม่มีผู้สัมผัสร่วมบ้าน — ถ้าภายหลังพบผู้สัมผัสเพิ่ม ให้เอาเครื่องหมายออกแล้วกรอกจำนวน
                </div>
            @endif

            {{-- ช่วงอายุตามที่คลินิกใช้จริง: 0-5 · 6-17 · 18 ปีขึ้นไป (เปลี่ยนจาก <5 / 5-14 ของบัตรกระดาษ 2569-09-23) --}}
            <div class="row g-3">
                @foreach([
                    ['contact_age0_5', 'อายุ 0-5 ปี'],
                    ['contact_age6_17', 'อายุ 6-17 ปี'],
                    ['contact_age18up', 'อายุ 18 ปีขึ้นไป'],
                ] as [$__f, $__label])
                    <div class="col-md-4">
                        <div class="border rounded bg-white h-100 p-2">
                            <div class="fw-bold small mb-2">{{ $__label }}</div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small text-muted mb-1" for="{{ $__f }}">ทั้งหมด (คน)</label>
                                    <input type="number" min="0" max="99" id="{{ $__f }}" name="{{ $__f }}"
                                           class="form-control form-control-sm text-center js-contact-total" data-no-highlight="1"
                                           value="{{ old($__f, $reg->$__f) }}" placeholder="0"
                                           title="จำนวนผู้สัมผัสทั้งหมดในช่วงอายุนี้">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-muted mb-1" for="{{ $__f }}_screened">ตรวจแล้ว (คน)</label>
                                    <input type="number" min="0" max="99" id="{{ $__f }}_screened" name="{{ $__f }}_screened"
                                           class="form-control form-control-sm text-center js-contact-done" data-no-highlight="1"
                                           value="{{ old($__f . '_screened', $reg->{$__f . '_screened'}) }}" placeholder="0"
                                           title="จำนวนที่ได้รับการตรวจคัดกรองแล้ว">
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-2 small" id="tb-contact-sum"></div>
        </div>
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">ประวัติวัคซีน BCG</label>
        <select name="bcg" class="form-select">
            <option value="">— เลือก —</option>
            @foreach(\App\Models\TbRegistration::$bcgOption as $k => $label)
                <option value="{{ $k }}" {{ (int) old('bcg', $reg->bcg) === $k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    {{-- ตรวจ HIV ที่ไหน + ผู้ให้คำปรึกษา (คลินิกขอ 2569-10-14)
         ตรวจที่อื่น: พิมพ์ชื่อ รพ. แล้วลงวันที่/ผลในบัตรเอง ไม่ต้องสั่ง Lab ในระบบ (ห้อง Lab จะไม่เห็นรายการที่ตัวเองไม่ได้ตรวจ)
         ใบผลแนบที่ "ไฟล์ผลตรวจจากที่อื่น" · ต้องรัน migration 2026_10_14_100000 ก่อนถึงโผล่ --}}
    @if(\App\Models\TbRegistration::hivPlaceReady())
        <div class="col-md-3">
            <label class="form-label fw-bold">ตรวจ HIV ที่</label>
            <input type="text" name="hiv_test_place" class="form-control" maxlength="150" list="tb-hiv-place-list"
                   value="{{ old('hiv_test_place', $reg->hiv_test_place) }}" placeholder="ว่าง = คลินิกนี้">
            <datalist id="tb-hiv-place-list">
                @foreach(\App\Models\TbRegistration::whereNotNull('hiv_test_place')->distinct()->pluck('hiv_test_place') as $__hp)<option value="{{ $__hp }}">@endforeach
            </datalist>
            <div class="form-text">ตรวจที่อื่น: พิมพ์ชื่อ รพ. แล้วลงวันที่/ผลเอง ไม่ต้องสั่ง Lab · แนบใบผลที่ "ไฟล์ผลตรวจจากที่อื่น"</div>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-bold">ผู้ให้คำปรึกษา</label>
            <input type="text" name="hiv_counselor" class="form-control" maxlength="150" list="tb-hiv-counselor-list"
                   value="{{ old('hiv_counselor', $reg->hiv_counselor) }}" placeholder="พิมพ์ชื่อ">
            <datalist id="tb-hiv-counselor-list">
                @foreach(\App\Models\TbRegistration::whereNotNull('hiv_counselor')->distinct()->pluck('hiv_counselor') as $__hc)<option value="{{ $__hc }}">@endforeach
            </datalist>
        </div>
    @endif

    <div class="col-md-3">
        <label class="form-label fw-bold">วันที่ได้รับคำปรึกษา</label>
        <input type="text" name="counsel_date" id="tb-counsel-date" class="form-control tb-date" autocomplete="off"
               value="{{ old('counsel_date', $d($reg->counsel_date)) }}" placeholder="วว/ดด/ปปปป">
        {{-- ดึงจาก OPD ครั้งแรกที่ลง Pre-test (2569-10-12) — เสนอเฉพาะตอนช่องยังว่าง กดแล้วเติม (บันทึกพร้อมปุ่มบันทึกบัตร) --}}
        @php $__opdHiv = \App\Models\TbRegistration::opdHivDates($reg->patient_id ?: optional($patient ?? null)->id); @endphp
        @if(! $reg->counsel_date && $__opdHiv['counsel'])
            <button type="button" class="btn btn-link btn-sm p-0 mt-1"
                    onclick="var i=document.getElementById('tb-counsel-date'); i.value={{ json_encode($d($__opdHiv['counsel'])) }}; i.dispatchEvent(new Event('change',{bubbles:true}));">
                ดึงจาก OPD: {{ $d($__opdHiv['counsel']) }} (Pre-test)
            </button>
        @endif
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">วันที่ส่งตรวจเลือด</label>
        <input type="text" name="blood_test_date" id="tb-blood-date" class="form-control tb-date" autocomplete="off"
               value="{{ old('blood_test_date', $d($reg->blood_test_date)) }}" placeholder="วว/ดด/ปปปป">
        @if(! $reg->blood_test_date && $__opdHiv['blood'])
            <button type="button" class="btn btn-link btn-sm p-0 mt-1"
                    onclick="var i=document.getElementById('tb-blood-date'); i.value={{ json_encode($d($__opdHiv['blood'])) }}; i.dispatchEvent(new Event('change',{bubbles:true}));">
                ดึงจาก OPD: {{ $d($__opdHiv['blood']) }} (สั่ง Anti-HIV)
            </button>
        @endif
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">ผลเลือด HIV</label>
        @if($reg->exists && $reg->patient_id)
            {{-- บัตรที่บันทึกแล้ว: ปิดผลไว้ กดดูแล้วค่อยมีช่องเลือก (partials/hiv_mask_js สร้าง select name=hiv_result ให้)
                 ก่อนเปิดดูไม่มีช่องนี้ในฟอร์มเลย -> TbController::validated() ไม่ได้รับ hiv_result -> ค่าเดิมในฐานไม่ถูกแตะ --}}
            <div class="js-hiv-reg-field d-flex flex-column gap-1"
                 data-options="{{ json_encode(\App\Models\TbRegistration::$hivResultOption, JSON_UNESCAPED_UNICODE) }}">
                @include('partials.hiv_mask', ['hmPatient' => $reg->patient_id, 'hmKind' => 'reg', 'hmRef' => $reg->id,
                    'hmReg' => $reg->id, 'hmWhere' => 'บัตร TB 01 · ข้อมูลบัตร'])
            </div>
        @else
            <select name="hiv_result" class="form-select">
                <option value="">— เลือก —</option>
                @foreach(\App\Models\TbRegistration::$hivResultOption as $k => $label)
                    <option value="{{ $k }}" {{ (int) old('hiv_result', $reg->hiv_result) === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        @endif
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">ได้รับยา ARV</label>
        <input type="text" name="arv" class="form-control" value="{{ old('arv', $reg->arv) }}" placeholder="ระบุสูตร">
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">ได้รับยา CPT</label>
        <input type="text" name="cpt" class="form-control" value="{{ old('cpt', $reg->cpt) }}">
    </div>

    <div class="col-12"><hr class="my-1"></div>

    {{-- ผลการรักษา = ปิดคอร์ส · ตราบใดที่ยังว่าง บัตรจะอยู่ในรายการ "กำลังรักษา" --}}
    <div class="col-md-5">
        <label class="form-label fw-bold">ผลการรักษา</label>
        <select name="outcome" class="form-select js-tb-only {{ $__isLtbi ? 'd-none' : '' }}" {{ $__isLtbi ? 'disabled' : '' }}>
            <option value="">— ยังรักษาอยู่ —</option>
            @foreach(\App\Models\TbRegistration::$outcomeOption as $k => $label)
                <option value="{{ $k }}" {{ ! $__isLtbi && (int) old('outcome', $reg->outcome) === $k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        {{-- ผลการรักษา TPT (บัตร LTBI) --}}
        <select name="outcome" class="form-select js-ltbi-only {{ $__isLtbi ? '' : 'd-none' }}" {{ $__isLtbi ? '' : 'disabled' }}>
            <option value="">— ยังกินยาป้องกันอยู่ —</option>
            @foreach(\App\Models\TbRegistration::$ltbiOutcomeOption as $k => $label)
                <option value="{{ $k }}" {{ $__isLtbi && (int) old('outcome', $reg->outcome) === $k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <div class="form-text">ใส่ผลแล้วบัตรจะย้ายไปกลุ่ม "จบการรักษาแล้ว"</div>
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">วันที่จบการรักษา</label>
        <input type="text" name="outcome_date" class="form-control tb-date" autocomplete="off"
               value="{{ old('outcome_date', $d($reg->outcome_date)) }}" placeholder="วว/ดด/ปปปป">
    </div>

    <div class="col-12">
        <label class="form-label fw-bold">บันทึกเพิ่มเติม</label>
        <textarea name="note" rows="2" class="form-control">{{ old('note', $reg->note) }}</textarea>
    </div>

    {{-- รายการยาของกลุ่มนี้ (อ้างอิงจากไฟล์รายการยาวัณโรคของคลินิก) — ดูเฉย ๆ ไม่ได้ผูกกับคลังยา --}}
    @php
        $__group = \App\Models\TbRegimen::drugGroupFor((int) old('registry_type', $reg->registry_type ?: 1));
        $__drugs = \App\Models\TbDrug::ofGroup($__group);
    @endphp
    @if($__drugs->count())
        <div class="col-12">
            <div class="border rounded p-2" style="background:#f8f9fa;">
                <div class="fw-bold small mb-1">
                    <i class="fa fa-pills me-1"></i> ยาที่ใช้ในกลุ่ม {{ \App\Models\TbDrug::$groupOption[$__group] ?? $__group }}
                    <span class="text-muted fw-normal">({{ $__drugs->count() }} รายการ — อ้างอิงจากรายการยาของคลินิก)</span>
                </div>
                <div class="d-flex flex-wrap gap-1">
                    @foreach($__drugs as $__d)
                        <span class="badge bg-light text-dark border" title="{{ $__d->note }}">
                            {{ $__d->label() }}@if($__d->note) <i class="fa fa-circle-info text-primary"></i>@endif
                        </span>
                    @endforeach
                </div>
                @php $__notes = $__drugs->filter(fn ($d) => $d->note); @endphp
                @if($__notes->count())
                    <div class="small text-muted mt-1">
                        @foreach($__notes as $__d)<span class="me-3">{{ $__d->name }}: <b>{{ $__d->note }}</b></span>@endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

{{-- สคริปต์อยู่คู่กับกล่องผู้สัมผัส เพื่อให้ทำงานทั้งหน้าเปิดบัตรใหม่และหน้าแก้ไขบัตร --}}
<script>
// รวมจำนวนผู้สัมผัสทั้ง 3 ช่วงอายุ และเตือนถ้ากรอก "ตรวจแล้ว" มากกว่า "ทั้งหมด"
(function () {
    var box = document.getElementById('tb-contact-sum');
    if (!box) return;
    function sum() {
        var totals = document.querySelectorAll('.js-contact-total');
        var dones  = document.querySelectorAll('.js-contact-done');
        var t = 0, d = 0, bad = false;
        totals.forEach(function (el, i) {
            var a = parseInt(el.value, 10) || 0, b = parseInt(dones[i].value, 10) || 0;
            t += a; d += b;

            var over    = b > a;              // กรอกเกิน = คีย์ผิด
            var pending = a > 0 && b < a;     // ยังตามมาตรวจไม่ครบ = ต้องสะดุดตา (คลินิกขอ 2569-09-29)

            dones[i].classList.toggle('tb-alert-value', pending || over);
            dones[i].classList.toggle('tb-filled', b > 0 && ! pending && ! over);
            el.classList.toggle('tb-filled', a > 0);
            dones[i].style.borderColor = over ? '#dc3545' : '';
            dones[i].title = pending ? 'ยังมาตรวจไม่ครบ ขาดอีก ' + (a - b) + ' คน' : '';

            // ป้ายบอกจำนวนที่ยังไม่มาตรวจ ต่อท้ายช่องของช่วงอายุนั้น
            var band = dones[i].closest('.border');
            if (band) {
                var tag = band.querySelector('.js-contact-pending');
                if (!tag) {
                    tag = document.createElement('div');
                    tag.className = 'js-contact-pending small fw-bold text-danger mt-1';
                    band.appendChild(tag);
                }
                tag.textContent = pending ? 'ยังไม่มาตรวจ ' + (a - b) + ' คน' : '';
            }

            if (over) bad = true;
        });
        box.innerHTML = t || d
            ? 'รวมผู้สัมผัส <b>' + t + '</b> คน · ตรวจแล้ว <b>' + d + '</b> คน'
              + (t > d ? ' · <span class="text-danger">ยังไม่ได้ตรวจ ' + (t - d) + ' คน</span>' : '')
              + (bad ? ' · <span class="text-danger">ช่องตรวจแล้วมากกว่าจำนวนทั้งหมด ตรวจทานอีกครั้ง</span>' : '')
            : '';
    }
    document.addEventListener('input', function (e) {
        if (e.target.classList && (e.target.classList.contains('js-contact-total') || e.target.classList.contains('js-contact-done'))) sum();
    });
    sum();
})();
</script>

{{-- ไฮไลต์ช่องที่กรอกแล้ว/ผลที่ต้องระวัง — หน้าเปิดบัตรใหม่ก็ใช้ได้ --}}
@include('tb._filled_js')
