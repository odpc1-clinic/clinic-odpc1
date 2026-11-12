{{-- ช่องของส่วน "ส่งตรวจทางห้องปฏิบัติการ" วัณโรค — ใช้ร่วมกัน 2 ที่ (2569-10-02)
       - หน้าตรวจ OPD (visit/form/tb_screening @section tbp_lab) อยู่ในฟอร์มหลักของหน้าตรวจ
       - บัตร TB 01 กล่อง "สั่งตรวจครั้งนี้" (tb/_lab_order) อยู่ในฟอร์มของกล่องเอง
     ไฟล์นี้ห้ามมี <form> และห้ามมี <script> (สคริปต์อยู่ที่ tb/_lab_fields_js ซึ่ง include ในหน้าที่โหลดเต็ม)

     ชื่อช่องเป็น {prefix}[...] — ค่าเริ่มต้น tb_lab แยกจาก tb[...] ของใบคัดกรอง
     หน้าตรวจจะได้รู้ (_touched) ว่าแก้ส่วนนี้หรือแก้ส่วนอื่น ไม่ทับกัน · ฝั่งเซิร์ฟเวอร์: TbScreening::saveLabSection

     data-lab-group = กลุ่มที่เซิร์ฟเวอร์เขียนแยกกัน (ดู TbScreening::fillLabInput)
     ตอนกดบันทึก กลุ่มที่ไม่ได้แก้จะถูกปิดไม่ให้ส่ง (tb/_lab_fields_js) — สองที่เปิดพร้อมกันก็ไม่ทับกลุ่มของอีกฝั่ง

     ตัวแปร: $sc (TbScreening หรือใบเปล่า) · $prefix (ไม่ส่ง = tb_lab) · $useOld (true = ใช้ค่าที่กรอกค้างตอนบันทึกไม่ผ่าน)
            $labVisit (ไม่ส่ง = ใช้ $tv ของ popup / $visit ของหน้าตรวจ) — ใช้หาแถวในตารางเสมหะของบัตร TB 01

     ผล AFB Spot / Collect 1 / Collect 2 + Molecular + Culture (2569-10-05)
       มีบัตร TB 01 ที่ยังรักษาอยู่ = อ่าน/เขียนแถวของรอบนี้ในตารางผลเสมหะของบัตร (ค่าชุดเดียวกัน แก้ที่ไหนก็เห็นทั้งสองที่)
       ยังไม่มีบัตร = เก็บที่ใบคัดกรอง — กติกาหาแถว: TbSputumExam::findForVisit --}}
@php
    $__p   = $prefix ?? 'tb_lab';
    $__old = ! empty($useOld) ? old($__p) : null;

    $__visit = $labVisit ?? ($tv ?? ($visit ?? null));
    if (! $__visit instanceof \App\Models\Visit) {
        $__visit = ! empty($sc->visit_id) ? \App\Models\Visit::find($sc->visit_id) : null;
    }
    $__link    = \App\Models\TbSputumExam::labValues($sc, $__visit);
    $__linkRow = $__link['row'];
    // Collect 1/2 กรอกได้เมื่อมีที่เก็บ: บัตร TB 01 (ตารางเสมหะ) หรือคอลัมน์ใหม่ของใบคัดกรอง (migration 2026_10_05_100000)
    $__hasC1 = $__link['reg'] ? true : \App\Models\TbScreening::hasAfbCollect();
    $__hasC2 = $__link['reg'] ? \App\Models\TbSputumExam::hasCollect2() : \App\Models\TbScreening::hasAfbCollect();

    if (is_array($__old)) {
        // บันทึกไม่ผ่าน -> แสดงค่าที่พยาบาลกรอกไว้ตามที่พิมพ์ ไม่ต้องกรอกใหม่
        $__specs = array_values(array_map(fn ($r) => [
            'type' => $r['type'] ?? '', 'other' => $r['other'] ?? '', 'date' => $r['date'] ?? '',
        ], array_filter((array) ($__old['lab_specimens'] ?? []), 'is_array')));
        $__d = $__old + ['lab_methods' => [], 'lab_molecular' => []];
        $__d['lab_methods']   = (array) $__d['lab_methods'];
        $__d['lab_molecular'] = (array) $__d['lab_molecular'];
    } else {
        $__specs = array_map(fn ($r) => [
            'type'  => $r['type'] ?? '',
            'other' => $r['other'] ?? '',
            'date'  => ! empty($r['date']) ? \App\Utillity::db2thDate($r['date']) : '',
        ], (array) ($sc->lab_specimens ?? []));
        if (! $__specs && $sc->lab_specimen) {
            $__specs = [['type' => $sc->lab_specimen, 'other' => $sc->lab_specimen_other,
                         'date' => $sc->lab_sent_date ? \App\Utillity::db2thDate($sc->lab_sent_date) : '']];
        }
        $__d = [
            'lab_methods'          => (array) ($sc->lab_methods ?? []),
            'lab_molecular'        => (array) ($sc->lab_molecular ?? []),
            'lab_afb_result'       => $__link['spot'],
            'lab_afb_collect1'     => $__link['collect1'],
            'lab_afb_collect2'     => $__link['collect2'],
            'lab_molecular_result' => $__link['molecular'],
            'lab_culture_result'   => $__link['culture'],
            'tst_done'  => $sc->tst_done,  'tst_mm'  => $sc->tst_mm,
            'cytb_done' => $sc->cytb_done, 'cytb_mm' => $sc->cytb_mm,
            'skin_other_name' => $sc->skin_other_name, 'skin_other_mm' => $sc->skin_other_mm,
            'igra_done' => $sc->igra_done, 'igra_result' => $sc->igra_result,
            'afb_status' => $sc->afb_status,
        ];
        // ผลที่ลงมาจากตารางเสมหะในบัตร (ใบนี้ยังไม่ได้ติ๊กวิธีตรวจ) — ติ๊กให้ ไม่งั้นช่องผลถูกปิด แก้จากตรงนี้ไม่ได้
        $__filled = fn ($v) => $v !== null && $v !== '';
        $__autoOn = [
            'afb'         => $__filled($__d['lab_afb_result']) || $__filled($__d['lab_afb_collect1']) || $__filled($__d['lab_afb_collect2']),
            'molecular'   => $__filled($__d['lab_molecular_result']),
            'culture_dst' => $__filled($__d['lab_culture_result']),
        ];
        foreach ($__autoOn as $__mk => $__on) {
            if ($__on && ! in_array($__mk, $__d['lab_methods'], true)) { $__d['lab_methods'][] = $__mk; }
        }
        // ข้อ 2 เปิดได้ก็ต่อเมื่อมีสิ่งส่งตรวจในข้อ 1 — แถวเสมหะในบัตรที่มีผลแล้วแต่ใบยังไม่มีสิ่งส่งตรวจ ให้โชว์เป็นเสมหะของวันที่เก็บ
        if (! $__specs && $__linkRow) {
            $__specs = [['type' => 'sputum_spot', 'other' => '',
                         'date' => $__linkRow->collected_date ? \App\Utillity::db2thDate(\App\Models\TbRegistration::beDate($__linkRow->collected_date)) : '']];
        }
    }
    if (! $__specs) { $__specs = [['type' => '', 'other' => '', 'date' => '']]; }
    // ค่าเก่าในตารางเสมหะที่ไม่อยู่ในตัวเลือก (เช่น NG, -) — โผล่เป็นตัวเลือกพิเศษ บันทึกแล้วไม่หาย
    $__afbOpts = function ($cur) {
        $opts = \App\Models\TbScreening::$afbResultOption;
        $cur  = trim((string) $cur);
        if ($cur !== '' && ! isset($opts[$cur])) { $opts = [$cur => $cur . ' (ค่าเดิม)'] + $opts; }
        return $opts;
    };
    $__v = fn ($k) => $__d[$k] ?? '';
@endphp
<div class="border rounded p-3 js-tb-lab-fields" id="tb-lab-fields" style="background:#f6faff; border-color:#b6d4fe !important;">
    <div class="fw-bold mb-2 tb-lab-fields-title" style="color:#0a58ca;">
        <i class="fa fa-vial me-1"></i> ส่งตรวจทางห้องปฏิบัติการ
    </div>

    {{-- 1) สิ่งส่งตรวจ — ส่งได้หลายอย่างในวันเดียว กด "เพิ่ม" ได้ (คลินิกขอ 2569-09-29)
         เช่น เสมหะ Spot + Collect หรือ เสมหะ + น้ำลาย แต่ละแถวมีวันที่ส่งของตัวเอง --}}
    <div data-lab-group="specs">
        <div class="fw-bold small mb-1">1. สิ่งส่งตรวจ (Specimen)</div>
        {{-- ตัวบอกว่าส่งชุดสิ่งส่งตรวจมา — ไม่มี = เซิร์ฟเวอร์ไม่แตะของเดิม --}}
        <input type="hidden" name="{{ $__p }}[lab_specimens_on]" value="1">
        <div id="tb-spec-rows" class="js-spec-rows" data-next="{{ count($__specs) }}">
            @foreach($__specs as $__si => $__sp)
                <div class="row g-2 align-items-center mb-1 tb-spec-row">
                    <div class="col-md-4">
                        <select name="{{ $__p }}[lab_specimens][{{ $__si }}][type]" class="form-select form-select-sm js-spec-type">
                            <option value="">— เลือกสิ่งส่งตรวจ —</option>
                            @foreach(\App\Models\TbScreening::$specimenOption as $k => $label)
                                <option value="{{ $k }}" {{ ($__sp['type'] ?? '') === $k ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="{{ $__p }}[lab_specimens][{{ $__si }}][other]" class="form-control form-control-sm"
                               value="{{ $__sp['other'] ?? '' }}" placeholder="ระบุ (ถ้าเลือกอื่น ๆ) เช่น น้ำเจาะปอด">
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="{{ $__p }}[lab_specimens][{{ $__si }}][date]" class="form-control form-control-sm date"
                               autocomplete="off" placeholder="วันที่ส่ง วว/ดด/ปปปป" value="{{ $__sp['date'] ?? '' }}">
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger js-spec-del" title="เอาแถวนี้ออก">&times;</button>
                    </div>
                </div>
            @endforeach
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary mb-3 js-spec-add" id="tb-spec-add">
            <i class="fa fa-plus"></i> เพิ่มสิ่งส่งตรวจ
        </button>
    </div>

    {{-- 2) วิธีตรวจหาเชื้อ + ช่องลงผล — พ่วงกับข้อ 1: ยังไม่เลือกสิ่งส่งตรวจ = เลือกวิธีตรวจไม่ได้
         (ไม่มีสิ่งส่งตรวจก็ไม่มีอะไรไปตรวจหาเชื้อ) ส่วนข้อ 3 เป็นการตรวจทางผิวหนัง/เลือด ไม่ผูกกัน --}}
    <div id="tb-lab-sec2" data-lab-group="sec2">
        {{-- ถูกปิดพร้อมข้อ 2 — ส่งมา = ข้อ 2 เปิดให้แก้อยู่ จึงเขียนวิธีตรวจ/ผลได้
             ไม่ส่งมา (ยังไม่เลือกสิ่งส่งตรวจ หรือไม่ได้แก้) = ไม่แตะวิธีตรวจ/ผลเดิม (รีวิว 2569-10-01) --}}
        <input type="hidden" name="{{ $__p }}[lab_sec2_on]" value="1">
        <div class="fw-bold small mb-1">2. วิธีการตรวจ
            <span class="fw-normal text-danger js-sec2-hint" id="tb-lab-sec2-hint" style="display:none;">— เลือกสิ่งส่งตรวจในข้อ 1 ก่อน</span>
        </div>
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="tb_lab_afb"
                           name="{{ $__p }}[lab_methods][]" value="afb" {{ in_array('afb', $__d['lab_methods'], true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-bold" for="tb_lab_afb">2.1 AFB</label>
                </div>
                {{-- Spot / Collect 1 / Collect 2 ตรงกับคอลัมน์ในตารางผลเสมหะของบัตร TB 01 (คลินิกขอ 2569-10-05)
                     Spot ใช้ชื่อช่องเดิม lab_afb_result — หน้าเก่าที่เปิดค้างส่งมาแค่ช่องนี้ Collect เดิมไม่ถูกล้าง --}}
                @foreach(['lab_afb_result' => 'Spot', 'lab_afb_collect1' => 'Collect 1', 'lab_afb_collect2' => 'Collect 2'] as $__ak => $__al)
                    @continue($__ak === 'lab_afb_collect1' && ! $__hasC1)
                    @continue($__ak === 'lab_afb_collect2' && ! $__hasC2)
                    <div class="input-group input-group-sm mb-1">
                        <span class="input-group-text" style="min-width:5.2rem;">{{ $__al }}</span>
                        <select name="{{ $__p }}[{{ $__ak }}]" class="form-select" data-dep="tb_lab_afb">
                            <option value="">—</option>
                            @foreach($__afbOpts($__v($__ak)) as $k => $label)
                                <option value="{{ $k }}" {{ (string) $__v($__ak) === (string) $k ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                @if($__link['reg'])
                    {{-- id แถวที่โชว์อยู่ — บันทึกแล้วลงแถวเดิมแน่นอน แม้แก้วันที่ส่งในข้อ 1 --}}
                    <input type="hidden" name="{{ $__p }}[sputum_row_id]" value="{{ $__linkRow->id ?? '' }}">
                    <div class="small" style="color:#0a58ca;">
                        <i class="fa fa-link me-1"></i>ผลชุดเดียวกับตารางเสมหะในบัตร TB 01 — รอบ <b>{{ $__link['round'] }}</b>{{ $__linkRow ? '' : ' (ยังไม่มีแถว จะสร้างให้ตอนบันทึก)' }}
                    </div>
                @elseif(! $__hasC1)
                    <div class="small text-muted">Collect 1/2 กรอกได้หลังผู้ดูแลอัปเดตฐานข้อมูล — ตอนนี้ลงในบัตร TB 01 ได้</div>
                @endif
            </div>

            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="tb_lab_mol"
                           name="{{ $__p }}[lab_methods][]" value="molecular" {{ in_array('molecular', $__d['lab_methods'], true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-bold" for="tb_lab_mol">2.2 Molecular</label>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-1">
                    @foreach(\App\Models\TbScreening::$molecularOption as $k => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="tb_mol_{{ $k }}"
                                   name="{{ $__p }}[lab_molecular][]" data-dep="tb_lab_mol" value="{{ $k }}" {{ in_array($k, $__d['lab_molecular'], true) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="tb_mol_{{ $k }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
                {{-- ตัวช่วยพิมพ์ผลใช้รายการเดียวกับตารางเสมหะใน TB 01 (TbSputumExam::$molecularOption) ยังพิมพ์อิสระได้ --}}
                <input type="text" name="{{ $__p }}[lab_molecular_result]" class="form-control form-control-sm" data-dep="tb_lab_mol"
                       list="tb-lab-molecular-result-list"
                       value="{{ $__v('lab_molecular_result') }}" placeholder="ผล เช่น MTB detected, RIF resistance not detected">
                {{-- ผลดื้อยา INH / RIF (LPA / Xpert) — คลินิกขอ 2569-10-28 · ไม่มี name: เลือกแล้ว JS เขียนต่อท้ายช่องผลด้านบน
                     ("· INH: Susceptible · RIF: Resistant") ช่องผลยังเป็นตัวที่บันทึก จึงไหลไปตารางเสมหะ/รายงานเหมือนเดิม --}}
                @php $__res = \App\Models\TbScreening::molResistance($__v('lab_molecular_result')); @endphp
                <div class="row g-1 mt-1 js-mol-res">
                    @php $__grpShown = null; @endphp
                    @foreach(\App\Models\TbScreening::molExtraFields() as $__drug => [$__lbl, $__opts, $__grp])
                        @if($__grp !== $__grpShown)
                            @php $__grpShown = $__grp; @endphp
                            <div class="col-12 small fw-bold mt-1" style="color:#0f766e;">{{ $__grp }}</div>
                        @endif
                        <div class="col-6">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text fw-bold" style="min-width:5.6rem;">{{ $__lbl }}</span>
                                <select class="form-select js-mol-res-sel" data-drug="{{ $__drug }}" data-dep="tb_lab_mol" title="{{ $__lbl }}">
                                    <option value="">—</option>
                                    @foreach($__opts as $__rk => $__rl)
                                        <option value="{{ $__rk }}" {{ $__res[$__drug] === $__rk ? 'selected' : '' }}>{{ $__rl }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endforeach
                </div>
                @once
                    <datalist id="tb-lab-molecular-result-list">
                        @foreach(\App\Models\TbSputumExam::$molecularOption as $__mOpt)<option value="{{ $__mOpt }}">@endforeach
                    </datalist>
                @endonce
            </div>

            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="tb_lab_cul"
                           name="{{ $__p }}[lab_methods][]" value="culture_dst" {{ in_array('culture_dst', $__d['lab_methods'], true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-bold" for="tb_lab_cul">2.3 Culture / DST</label>
                </div>
                <input type="text" name="{{ $__p }}[lab_culture_result]" class="form-control form-control-sm" data-dep="tb_lab_cul"
                       value="{{ $__v('lab_culture_result') }}" placeholder="ผลเพาะเชื้อ / ผลความไวต่อยา">
            </div>
        </div>
    </div>{{-- /#tb-lab-sec2 --}}

    {{-- 3) การตรวจหาการติดเชื้อวัณโรค (ใช้กับผู้สัมผัส / LTBI)
         checkbox ที่ไม่ได้ติ๊กไม่ถูกส่งมา จึงต้องมีตัวบอก lab_sec3_on ว่าส่งข้อ 3 มาทั้งข้อ (ติ๊กออก = ล้างได้) --}}
    <div data-lab-group="sec3">
        <input type="hidden" name="{{ $__p }}[lab_sec3_on]" value="1">
        <div class="fw-bold small mb-1">3. การตรวจหาการติดเชื้อวัณโรค</div>
        <div class="row g-2">
            <div class="col-md-8">
                <div class="small text-muted mb-1">3.1 Skin test</div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <div class="input-group-text">
                                <input class="form-check-input mt-0 me-1" type="checkbox"
                                       name="{{ $__p }}[tst_done]" value="1" id="tb_tst" {{ $__v('tst_done') ? 'checked' : '' }}>
                                <label class="form-check-label" for="tb_tst">TST</label>
                            </div>
                            <input type="number" step="0.1" min="0" max="99" name="{{ $__p }}[tst_mm]" data-dep="tb_tst"
                                   class="form-control" value="{{ $__v('tst_mm') }}" placeholder="ผล">
                            <span class="input-group-text">mm</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <div class="input-group-text">
                                <input class="form-check-input mt-0 me-1" type="checkbox"
                                       name="{{ $__p }}[cytb_done]" value="1" id="tb_cytb" {{ $__v('cytb_done') ? 'checked' : '' }}>
                                <label class="form-check-label" for="tb_cytb">Cy-TB</label>
                            </div>
                            <input type="number" step="0.1" min="0" max="99" name="{{ $__p }}[cytb_mm]" data-dep="tb_cytb"
                                   class="form-control" value="{{ $__v('cytb_mm') }}" placeholder="ผล">
                            <span class="input-group-text">mm</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <input type="text" name="{{ $__p }}[skin_other_name]" class="form-control" maxlength="60"
                                   value="{{ $__v('skin_other_name') }}" placeholder="อื่น ๆ ระบุ">
                            <input type="number" step="0.1" min="0" max="99" name="{{ $__p }}[skin_other_mm]"
                                   class="form-control" value="{{ $__v('skin_other_mm') }}" placeholder="ผล">
                            <span class="input-group-text">mm</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="small text-muted mb-1">3.2 IGRA</div>
                <div class="input-group input-group-sm">
                    <div class="input-group-text">
                        <input class="form-check-input mt-0 me-1" type="checkbox"
                               name="{{ $__p }}[igra_done]" value="1" id="tb_igra" {{ $__v('igra_done') ? 'checked' : '' }}>
                        <label class="form-check-label" for="tb_igra">ส่ง IGRA</label>
                    </div>
                    <select name="{{ $__p }}[igra_result]" class="form-select" data-dep="tb_igra">
                        <option value="">— ผล —</option>
                        @foreach(\App\Models\TbScreening::$igraOption as $k => $label)
                            <option value="{{ $k }}" {{ (string) $__v('igra_result') === (string) $k ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- ช่อง "การตรวจอื่น ๆ ที่ส่งพร้อมกัน" เอาออกแล้ว — สั่งผ่านปุ่ม "สั่งแล็บ" แทน (2569-10-05)
         ช่อง "ผลเสมหะ (วัณโรคปอด) M+/M−" เอาออกแล้ว — ซ้ำกับผล AFB ในข้อ 2 (คลินิกขอ 2569-10-02)
         ค่าเดิมในฐานยังอยู่ ไม่ถูกลบ (ไม่ส่งคีย์ labs_other / afb_status = ไม่เขียน) ค่าเดิมโชว์เป็นข้อความอ่านอย่างเดียว --}}
    @if(trim((string) ($sc->labs_other ?? '')) !== '')
        <div class="small text-muted mt-2">การตรวจอื่น ๆ ที่บันทึกไว้เดิม: {{ $sc->labs_other }}</div>
    @endif

    <div class="form-text mt-2">
        ห้องแล็บลงผลในระบบ NTIP ช่องผลตรงนี้พยาบาลกรอกเองได้เลย · ผลเสมหะรายเดือนของผู้ป่วยที่ขึ้นทะเบียนแล้ว ให้ลงในบัตร TB 01 (ตารางผลตรวจเสมหะรายเดือน)
    </div>
</div>
