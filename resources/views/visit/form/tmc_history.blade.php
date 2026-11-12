{{-- ============================================================
     ซักประวัติเวชศาสตร์การเดินทาง (TMC) — ตามใบซักประวัติ TMC 66
     include อยู่ใน visit/form/tmc.blade.php (ภายในฟอร์มหลัก #visit_edit_form)
     บันทึกพร้อมปุ่ม Save ของหน้าตรวจ — ห้ามมี <form> ในไฟล์นี้
     checkbox ใช้ hidden ค่า 0 นำหน้า เพื่อให้ "ไม่ติ๊ก" ส่งค่า 0 (ยกเลิกได้)
     ============================================================ --}}
@php $__pt = App\Models\Visit::getPatient($visit); @endphp

<br>
<div class="d-grid">
    <a class="btn btn-light" data-tabtoggle="1" href="#tmcHistory" role="button"
       aria-expanded="false" style="width:100%" aria-controls="tmcHistory">
        <font size="4"><i class="fa-solid fa-passport me-1"></i> ซักประวัติการเดินทาง (TMC)</font>
    </a>
</div>
<style>
    /* ห้องซักประวัติ TMC — ตัวใหญ่อ่านง่าย */
    #tmcHistory { font-size: 1.12rem; }
    #tmcHistory h6 { font-size: 1.25rem; }
    #tmcHistory .form-label { font-size: 1.02rem; }
    #tmcHistory .form-control, #tmcHistory .form-select { font-size: 1.08rem; padding: .4rem .6rem; }
    #tmcHistory .form-check-label { font-size: 1.06rem; }
    #tmcHistory .form-check-input { width: 1.25rem; height: 1.25rem; }
    /* แบ่งเป็นหมวดสีพาสเทล — เดิมทุกอย่างต่อกันเป็นพืดเดียว พยาบาลตาลาย (คลินิกขอ 2569-10-08) */
    #tmcHistory .tmc-part { border-radius: .6rem; padding: .75rem .9rem .9rem; margin-bottom: .9rem; border: 1px solid; }
    #tmcHistory .tmc-part > .tmc-part-title { font-weight: 700; font-size: 1.15rem; margin-bottom: .6rem; display: flex; align-items: center; gap: .4rem; }
    #tmcHistory .tmc-part-visit  { background: #eef4ff; border-color: #c7d7fb; }
    #tmcHistory .tmc-part-visit  > .tmc-part-title { color: #1e40af; }
    #tmcHistory .tmc-part-female { background: #fdf0f5; border-color: #f5c6d8; }
    #tmcHistory .tmc-part-female > .tmc-part-title { color: #9d174d; }
    #tmcHistory .tmc-part-travel { background: #eefaf2; border-color: #bfe8cd; }
    #tmcHistory .tmc-part-travel > .tmc-part-title { color: #166534; }
    #tmcHistory .tmc-part-files  { background: #fff9e8; border-color: #f3dfa2; }
    #tmcHistory .tmc-part-files  > .tmc-part-title { color: #854d0e; }
    #tmcHistory .tmc-sub { font-weight: 600; color: #6b7280; font-size: .98rem; margin: .35rem 0 .3rem; border-bottom: 1px dashed rgba(0,0,0,.12); padding-bottom: .15rem; }
    #tmcHistory .tmc-part .form-control, #tmcHistory .tmc-part .form-select { background-color: #fff; }
</style>
<div class="collapse show" id="tmcHistory">
    <div class="card card-body">

        {{-- ========== หมวด 1: การมาครั้งนี้ (สีฟ้า) ========== --}}
        <div class="tmc-part tmc-part-visit">
        <div class="tmc-part-title"><i class="fa-solid fa-notes-medical"></i> 1 · การมาครั้งนี้</div>
        {{-- ---------- ช่วงการเดินทาง (ก่อน Reason for visit) ---------- --}}
        {{-- บังคับเลือกก่อนกด Save (คลินิกขอ 2569-11-04) — รายงานส่ง ผอ. ข้อ 2.1–2.4 แยกก่อน/หลังเดินทางจากช่องนี้ --}}
        <div class="tmc-sub"><i class="fa-regular fa-clock me-1"></i> ช่วงการเดินทาง (Travel phase) <span class="text-danger fw-bold" title="ต้องเลือกก่อนบันทึก">* จำเป็น</span></div>
        <div class="d-flex flex-wrap gap-4 mb-3 rounded px-2" id="tmc-phase-box">
            {{-- "ไม่มีการเดินทาง" (คลินิกขอ 2569-11-05) — ส่วนใหญ่มาฉีดวัคซีนป้องกันเฉย ๆ ไม่ได้จะไปไหน --}}
            @foreach(['before'=>'ก่อนเดินทาง','during'=>'ระหว่างเดินทาง','after'=>'หลังเดินทาง','none'=>'ไม่มีการเดินทางไปต่างประเทศ'] as $__pv => $__pl)
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="tmc_travel_phase" value="{{ $__pv }}"
                           id="tmc_phase_{{ $__pv }}" {{ (string)$visit->tmc_travel_phase === $__pv ? 'checked' : '' }}>
                    <label class="form-check-label" for="tmc_phase_{{ $__pv }}">{{ $__pl }}</label>
                </div>
            @endforeach
        </div>

        {{-- ---------- สาเหตุการมาที่คลินิก (เพิ่มรายการเองได้ + เก็บ master list) ---------- --}}
        @php
            $__reasons  = \App\Models\TmcReason::orderBy('sort')->orderBy('id')->get();
            $__selected = array_filter(array_map('trim', explode(',', (string) $visit->tmc_reason)));
        @endphp
        <div class="tmc-sub">สาเหตุการมาที่คลินิก (Reason for visit)</div>
        <input type="hidden" name="tmc_reason" id="tmc_reason" value="{{ $visit->tmc_reason }}">
        <div class="row g-2 mb-1" id="tmc-reason-list">
            @foreach($__reasons as $r)
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input tmc-reason-cb" type="checkbox" value="{{ $r->name }}"
                               id="tmc_reason_{{ $r->id }}" {{ in_array($r->name, $__selected, true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="tmc_reason_{{ $r->id }}">{{ $r->name }}</label>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="row g-2 mb-2">
            <div class="col-md-7">
                <label class="form-label small text-muted mb-1">เพิ่มสาเหตุการมา (ถ้าไม่มีในรายการ)</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="tmc-reason-new" placeholder="เพิ่มสาเหตุใหม่...">
                    <button type="button" class="btn btn-outline-primary" id="tmc-reason-add"><i class="fa fa-plus"></i> เพิ่ม</button>
                </div>
                <div class="form-text mb-0">เพิ่มแล้วจะเก็บไว้ให้ครั้งต่อไปเลือกได้เลย (ไม่เพิ่มซ้ำ)</div>
            </div>
        </div>
        </div>{{-- /หมวด 1 --}}

        {{-- ========== หมวด 2: สำหรับสุภาพสตรี (สีชมพู) — เฉพาะเพศหญิง ==========
             จัดกลุ่มใหม่ (2569-10-08): ประจำเดือน · ตั้งครรภ์+อายุครรภ์+ให้นมบุตร · คุมกำเนิด+รายละเอียด+เพศสัมพันธ์ครั้งสุดท้าย --}}
        @if($__pt && (int) $__pt->sex === 2)
        <div class="tmc-part tmc-part-female">
        <div class="tmc-part-title"><i class="fa-solid fa-venus"></i> 2 · สำหรับสุภาพสตรี (For female)</div>
        @php
            // ช่องใหม่ (2569-10-07) รอ migration 2026_10_07_110000 — ยังไม่ขึ้น = ไม่แสดงช่อง (ส่งไปก็บันทึกไม่ได้)
            $__femaleReady = \App\Models\Visit::tmcFemaleReady();
            $__lmpEnd = $__femaleReady && ! $visit->tmc_menopause ? ($visit->tmc_lmp_end ?? '') : '';
            $__lmpDays = $__lmpEnd !== '' ? \App\Models\Visit::lmpDays($visit->LMP, $__lmpEnd) : null;
        @endphp
        <div class="tmc-sub">ประจำเดือน</div>
        <div class="row g-2 mb-1">
            {{-- LMP เป็นช่วง: วันแรก (คอลัมน์ LMP เดิม) – วันสุดท้าย · หรือพิมพ์จำนวนวันให้ระบบเติมวันสุดท้าย (คลินิกขอ 2569-10-07) --}}
            <div class="col-md-10">
                <label class="form-label mb-1" title="วันแรก – วันสุดท้าย ของประจำเดือนรอบล่าสุด">ประจำเดือนครั้งสุดท้าย (LMP)</label>
                <div class="d-flex flex-wrap align-items-start gap-2" id="tmc-lmp-range">
                    <div style="min-width:9.5rem;flex:1 1 9.5rem;">
                        <input class="form-control form-control-sm {{ $visit->tmc_menopause ? '' : 'tmc-hist-date' }}" name="LMP" id="tmc_lmp" type="text" autocomplete="off"
                               placeholder="วว/ดด/ปปปป" value="{{ $visit->tmc_menopause ? '' : $visit->LMP }}" {{ $visit->tmc_menopause ? 'readonly' : '' }}>
                        <div class="form-text mt-0">วันแรก</div>
                    </div>
                    @if($__femaleReady)
                        <span class="pt-1">–</span>
                        <div style="min-width:9.5rem;flex:1 1 9.5rem;">
                            <input class="form-control form-control-sm {{ $visit->tmc_menopause ? '' : 'tmc-hist-date' }}" name="tmc_lmp_end" id="tmc_lmp_end" type="text" autocomplete="off"
                                   placeholder="วว/ดด/ปปปป" value="{{ $__lmpEnd }}" {{ $visit->tmc_menopause ? 'readonly' : '' }}>
                            <div class="form-text mt-0">วันสุดท้าย</div>
                        </div>
                        <span class="pt-1">หรือ</span>
                        {{-- ช่องจำนวนวันไม่มี name — เป็นตัวช่วยคำนวณวันสุดท้ายเท่านั้น (เก็บจริงคือวันสุดท้าย) --}}
                        <div style="width:7.5rem;">
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control" id="tmc_lmp_days" min="1" max="15" inputmode="numeric"
                                       value="{{ $__lmpDays && $__lmpDays > 0 ? $__lmpDays : '' }}" {{ $visit->tmc_menopause ? 'readonly' : '' }}>
                                <span class="input-group-text">วัน</span>
                            </div>
                            <div class="form-text mt-0">จำนวนวัน</div>
                        </div>
                    @endif
                </div>
                <input type="hidden" name="tmc_menopause" value="0">
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" name="tmc_menopause" value="1" id="tmc_menopause" {{ $visit->tmc_menopause ? 'checked' : '' }}>
                    <label class="form-check-label" for="tmc_menopause">หมดประจำเดือนแล้ว (วัยทอง)</label>
                </div>
            </div>
        </div>
        <div class="tmc-sub">การตั้งครรภ์ / ให้นมบุตร</div>
        <div class="row g-2 mb-1">
            <div class="col-md-4">
                <label class="form-label mb-1">ตั้งครรภ์</label>
                <select class="form-select form-select-sm" name="tmc_pregnant">
                    <option value="" {{ $visit->tmc_pregnant==='' || $visit->tmc_pregnant===null ? 'selected':'' }}>—</option>
                    <option value="no"  {{ $visit->tmc_pregnant==='no'  ? 'selected':'' }}>ไม่มี (No)</option>
                    <option value="yes" {{ $visit->tmc_pregnant==='yes' ? 'selected':'' }}>มี (Yes)</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1">อายุครรภ์</label>
                <input class="form-control form-control-sm" name="tmc_pregnant_detail" type="text"
                       value="{{ $visit->tmc_pregnant_detail }}" placeholder="เช่น 12 สัปดาห์">
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1">ให้นมบุตร</label>
                <select class="form-select form-select-sm" name="tmc_breastfeeding">
                    <option value="" {{ $visit->tmc_breastfeeding==='' || $visit->tmc_breastfeeding===null ? 'selected':'' }}>—</option>
                    <option value="no"  {{ $visit->tmc_breastfeeding==='no'  ? 'selected':'' }}>ไม่ใช่ (No)</option>
                    <option value="yes" {{ $visit->tmc_breastfeeding==='yes' ? 'selected':'' }}>ใช่ (Yes)</option>
                </select>
            </div>
        </div>
        <div class="tmc-sub">การคุมกำเนิด / เพศสัมพันธ์</div>
        <div class="row g-2">
            @if($__femaleReady)
                @php $__cc = (string) ($visit->tmc_contraception ?? ''); @endphp
                <div class="col-md-4">
                    <label class="form-label mb-1" for="tmc_contraception">ประวัติการคุมกำเนิด</label>
                    <select class="form-select form-select-sm" name="tmc_contraception" id="tmc_contraception">
                        <option value="">—</option>
                        @foreach(\App\Models\Visit::$tmcContraceptionOption as $__o)
                            <option value="{{ $__o }}" {{ $__cc === $__o ? 'selected' : '' }}>{{ $__o }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1" for="tmc_contraception_other">รายละเอียดการคุมกำเนิด</label>
                    <input class="form-control form-control-sm" name="tmc_contraception_other" id="tmc_contraception_other" type="text" maxlength="255"
                           value="{{ $visit->tmc_contraception_other }}" placeholder="เช่น ชื่อยา / ฝังเมื่อ… / อื่น ๆ ระบุ">
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1" for="tmc_last_sex_date">มีเพศสัมพันธ์ครั้งสุดท้าย</label>
                    <input class="form-control form-control-sm tmc-hist-date" name="tmc_last_sex_date" id="tmc_last_sex_date" type="text" autocomplete="off"
                           placeholder="วว/ดด/ปปปป" value="{{ $visit->tmc_last_sex_date }}">
                </div>
            @else
                <div class="col-12 small text-muted">ช่อง LMP วันสุดท้าย / การคุมกำเนิด / มีเพศสัมพันธ์ครั้งสุดท้าย — รอผู้ดูแลระบบอัปเดตฐานข้อมูลก่อนจึงจะใช้ได้</div>
            @endif
        </div>
        </div>{{-- /หมวด 2 --}}
        @endif

        {{-- ========== หมวด 3: แผนการเดินทาง (สีเขียว) ========== --}}
        <div class="tmc-part tmc-part-travel">
        <div class="tmc-part-title"><i class="fa-solid fa-plane"></i> 3 · แผนการท่องเที่ยว (Travel plan)</div>
        <div class="row g-2">
            @if(\App\Models\Visit::destCountriesReady())
                {{-- ประเทศเลือกจากรายการ (กรอง/นับในรายงานได้) · ช่องเลือกไม่มี name — JS เขียนรหัสลง hidden ตัวเดียว
                     ตัวเทียบ _touched จะเห็นเป็นช่องเดียว และเอาออกหมดก็ยังส่งค่าว่างไปล้างได้ --}}
                @php
                    $__destList = \App\Models\Visit::destCountryList();
                    $__destSel  = array_keys(\App\Models\Visit::destCountryNames($visit->tmc_dest_countries));
                    $__destGuess = $__destSel ? [] : array_values(array_filter(
                        \App\Models\Visit::guessDestCountries($visit->tmc_destinations), fn ($c) => isset($__destList[$c])));
                @endphp
                <div class="col-md-7">
                    <label class="form-label small text-muted mb-1" for="tmc_dest_pick">ประเทศปลายทาง <span class="text-muted">(เลือกได้หลายประเทศ · พิมพ์ไทยหรืออังกฤษเพื่อค้นหา)</span></label>
                    <input type="hidden" name="tmc_dest_countries" id="tmc_dest_countries" value="{{ implode(',', $__destSel) }}">
                    <select id="tmc_dest_pick" class="form-select form-select-sm" multiple data-placeholder="พิมพ์ชื่อประเทศ เช่น เคนยา / kenya">
                        @foreach($__destList as $__code => $__c)
                            <option value="{{ $__code }}" data-search="{{ $__c['search'] }}" @selected(in_array($__code, $__destSel, true))>{{ $__c['label'] }}</option>
                        @endforeach
                    </select>
                    @if($__destGuess)
                        <div class="small mt-1" id="tmc-dest-guess">
                            <span class="text-muted">จากข้อความที่พิมพ์ไว้เดิม:</span>
                            <b>{{ implode(', ', array_map(fn ($c) => $__destList[$c]['th'], $__destGuess)) }}</b>
                            <button type="button" class="btn btn-outline-primary btn-sm py-0 ms-1" id="tmc-dest-guess-btn"
                                    data-codes="{{ implode(',', $__destGuess) }}">เลือกตามนี้</button>
                        </div>
                    @endif
                </div>
                <div class="col-md-5">
                    <label class="form-label small text-muted mb-1">เมือง / รายละเอียดเพิ่มเติม</label>
                    <input class="form-control form-control-sm" name="tmc_destinations" type="text"
                           value="{{ $visit->tmc_destinations }}" placeholder="เช่น ไนโรบี, ซาฟารี, ทำงานบนเรือ">
                </div>
            @else
                <div class="col-md-12">
                    <label class="form-label small text-muted mb-1">สถานที่ที่ต้องการไป (ประเทศ/เมือง)</label>
                    <input class="form-control form-control-sm" name="tmc_destinations" type="text"
                           value="{{ $visit->tmc_destinations }}">
                </div>
            @endif
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">กำหนดออกเดินทาง</label>
                @php $__depUnknown = ($visit->tmc_depart_date === 'ไม่ทราบ'); @endphp
                <input class="form-control form-control-sm {{ $__depUnknown ? '' : 'tmc-hist-date' }}" name="tmc_depart_date" id="tmc_depart_date" type="text"
                       value="{{ $visit->tmc_depart_date }}" placeholder="วว/ดด/ปปปป" autocomplete="off" {{ $__depUnknown ? 'readonly' : '' }}>
                <div class="form-check mt-1">
                    <input class="form-check-input js-date-unknown" data-target="tmc_depart_date" type="checkbox" id="tmc_depart_unknown" {{ $__depUnknown ? 'checked' : '' }}>
                    <label class="form-check-label small" for="tmc_depart_unknown">ไม่ทราบวันเดินทาง</label>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">กำหนดกลับ</label>
                @php $__retUnknown = ($visit->tmc_return_date === 'ไม่ทราบ'); @endphp
                <input class="form-control form-control-sm {{ $__retUnknown ? '' : 'tmc-hist-date' }}" name="tmc_return_date" id="tmc_return_date" type="text"
                       value="{{ $visit->tmc_return_date }}" placeholder="วว/ดด/ปปปป" autocomplete="off" {{ $__retUnknown ? 'readonly' : '' }}>
                <div class="form-check mt-1">
                    <input class="form-check-input js-date-unknown" data-target="tmc_return_date" type="checkbox" id="tmc_return_unknown" {{ $__retUnknown ? 'checked' : '' }}>
                    <label class="form-check-label small" for="tmc_return_unknown">ไม่ทราบวันกลับ</label>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1">ลักษณะสถานที่ (Visiting area)</label>
                <div class="d-flex flex-wrap gap-3 pt-1">
                    <input type="hidden" name="tmc_area_rural" value="0">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tmc_area_rural" value="1" id="tmc_area_rural" {{ $visit->tmc_area_rural ? 'checked':'' }}>
                        <label class="form-check-label" for="tmc_area_rural">ชนบท</label>
                    </div>
                    <input type="hidden" name="tmc_area_urban" value="0">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tmc_area_urban" value="1" id="tmc_area_urban" {{ $visit->tmc_area_urban ? 'checked':'' }}>
                        <label class="form-check-label" for="tmc_area_urban">ในเมือง</label>
                    </div>
                    <input type="hidden" name="tmc_area_altitude" value="0">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tmc_area_altitude" value="1" id="tmc_area_altitude" {{ $visit->tmc_area_altitude ? 'checked':'' }}>
                        <label class="form-check-label" for="tmc_area_altitude">ที่สูง</label>
                    </div>
                    {{-- แยกที่สูง/ดำน้ำ + เพิ่มเข้าป่า (2569-10-07: การดูแลต่างกัน) · ยังไม่ migrate = ไม่แสดง (ไม่มีคอลัมน์ให้บันทึก) --}}
                    @if(\App\Models\Visit::tmcTravelExtraReady())
                        @foreach(['tmc_area_diving' => 'ดำน้ำ', 'tmc_area_forest' => 'เข้าป่า'] as $__ak => $__al)
                            <input type="hidden" name="{{ $__ak }}" value="0">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="{{ $__ak }}" value="1" id="{{ $__ak }}" {{ $visit->$__ak ? 'checked':'' }}>
                                <label class="form-check-label" for="{{ $__ak }}">{{ $__al }}</label>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
            <div class="col-md-6">
                {{-- เลือกจากรายการ หรือ "อื่น ๆ" แล้วพิมพ์เอง (2569-10-07) — ค่าที่เก็บจริงคือช่องข้อความ name=tmc_trip_purpose
                     ตัวเลือกไม่มี name (ไม่ไปกับฟอร์ม) แค่เติมค่าให้ช่องข้อความ · ค่าเดิมที่ไม่อยู่ในรายการ = โชว์เป็น "อื่น ๆ" + ข้อความเดิม --}}
                @php
                    $__tp = trim((string) $visit->tmc_trip_purpose);
                    $__tpKnown = in_array($__tp, \App\Models\Visit::$tmcTripPurposeOption, true);
                @endphp
                <label class="form-label small text-muted mb-1" for="tmc-purpose-pick">จุดประสงค์ของการเดินทาง</label>
                <div class="d-flex flex-wrap gap-2">
                    <select class="form-select form-select-sm js-pick-other" data-target="tmc_trip_purpose" id="tmc-purpose-pick" style="flex:1 1 12rem;">
                        <option value="" {{ $__tp === '' ? 'selected' : '' }}>— เลือก —</option>
                        @foreach(\App\Models\Visit::$tmcTripPurposeOption as $__o)
                            <option value="{{ $__o }}" {{ $__tp === $__o ? 'selected' : '' }}>{{ $__o }}</option>
                        @endforeach
                        <option value="__other" {{ $__tp !== '' && ! $__tpKnown ? 'selected' : '' }}>อื่น ๆ (พิมพ์เอง)</option>
                    </select>
                    <input class="form-control form-control-sm {{ $__tp !== '' && ! $__tpKnown ? '' : 'd-none' }}" name="tmc_trip_purpose" id="tmc_trip_purpose"
                           type="text" maxlength="500" value="{{ $visit->tmc_trip_purpose }}" placeholder="พิมพ์จุดประสงค์" style="flex:1 1 12rem;">
                </div>
            </div>
            @if(\App\Models\Visit::tmcTravelExtraReady())
            <div class="col-md-6">
                {{-- ลักษณะการเดินทาง (2569-10-07) — กลไกเดียวกับจุดประสงค์: ตัวเลือกไม่มี name ค่าจริงอยู่ช่องข้อความ --}}
                @php
                    $__ts = trim((string) $visit->tmc_travel_style);
                    $__tsKnown = in_array($__ts, \App\Models\Visit::$tmcTravelStyleOption, true);
                @endphp
                <label class="form-label small text-muted mb-1" for="tmc-style-pick">ลักษณะการเดินทาง</label>
                <div class="d-flex flex-wrap gap-2">
                    <select class="form-select form-select-sm js-pick-other" data-target="tmc_travel_style" id="tmc-style-pick" style="flex:1 1 12rem;">
                        <option value="" {{ $__ts === '' ? 'selected' : '' }}>— เลือก —</option>
                        @foreach(\App\Models\Visit::$tmcTravelStyleOption as $__o)
                            <option value="{{ $__o }}" {{ $__ts === $__o ? 'selected' : '' }}>{{ $__o }}</option>
                        @endforeach
                        <option value="__other" {{ $__ts !== '' && ! $__tsKnown ? 'selected' : '' }}>อื่น ๆ (พิมพ์เอง)</option>
                    </select>
                    <input class="form-control form-control-sm {{ $__ts !== '' && ! $__tsKnown ? '' : 'd-none' }}" name="tmc_travel_style" id="tmc_travel_style"
                           type="text" maxlength="150" value="{{ $visit->tmc_travel_style }}" placeholder="พิมพ์ลักษณะการเดินทาง" style="flex:1 1 12rem;">
                </div>
            </div>
            @endif
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1">รูปแบบกิจกรรม</label>
                <input class="form-control form-control-sm" name="tmc_activities" type="text" value="{{ $visit->tmc_activities }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">สัมผัสสัตว์/แมลง</label>
                <select class="form-select form-select-sm" name="tmc_animal_exposure">
                    <option value="" {{ $visit->tmc_animal_exposure==='' || $visit->tmc_animal_exposure===null ? 'selected':'' }}>—</option>
                    <option value="yes" {{ $visit->tmc_animal_exposure==='yes' ? 'selected':'' }}>ใช่ (Yes)</option>
                    <option value="no"  {{ $visit->tmc_animal_exposure==='no'  ? 'selected':'' }}>ไม่ (No)</option>
                </select>
            </div>
            <div class="col-md-9">
                <label class="form-label small text-muted mb-1">สถานที่พัก (Accommodations)</label>
                <input class="form-control form-control-sm" name="tmc_accommodation" type="text" value="{{ $visit->tmc_accommodation }}">
            </div>
        </div>
        </div>{{-- /หมวด 3 --}}

        {{-- ---------- ไฟล์แนบแผนการเดินทาง (2569-10-07) ----------
             อยู่ในฟอร์มหลัก → ห้ามเป็น <form> · ช่องเลือกไฟล์/ชื่อเอกสารไม่มี name (ไม่ไปกับปุ่ม Save)
             ส่งด้วย fetch ไป TmcTravelFileController แล้วเอารายการที่ตอบกลับมาแทนที่ในกล่อง
             data-no-dirty = ไม่นับเป็น "ยังไม่ได้บันทึก" ของหน้าตรวจ --}}
        <div class="tmc-part tmc-part-files" id="tmc-travel-files" data-no-dirty
             data-url="{{ route('tmc.travelFile.store', $visit->id) }}"
             data-max="{{ \App\Models\TbLabFile::postMaxBytes() }}">
            <div class="tmc-part-title"><i class="fa-solid fa-paperclip"></i> 4 · ไฟล์แนบแผนการเดินทาง
                <span class="small text-muted fw-normal" style="font-size:.85rem;">กำหนดการ · ตั๋ว · จดหมายเชิญ · วีซ่า (PDF/รูป ไม่เกิน 10 MB ต่อไฟล์) · บันทึกทันทีที่กดอัปโหลด</span>
            </div>
            @if(\App\Models\TmcTravelFile::ready())
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <input type="file" class="form-control form-control-sm" id="tmc-tf-input" multiple
                               accept="application/pdf,image/jpeg,image/png,image/webp">
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" id="tmc-tf-title" list="tmc-tf-titles" maxlength="150" placeholder="ชื่อเอกสาร (ไม่บังคับ)">
                        <datalist id="tmc-tf-titles">
                            @foreach(\App\Models\TmcTravelFile::$titleOption as $__t)<option value="{{ $__t }}">@endforeach
                        </datalist>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="button" class="btn btn-sm btn-primary" id="tmc-tf-upload"><i class="fa fa-upload me-1"></i> อัปโหลด</button>
                    </div>
                </div>
                <div class="small mt-1 js-tf-status"></div>
                <div class="mt-1 js-tf-list">@include('visit.form._tmc_travel_files_list', ['visit' => $visit])</div>
            @else
                <div class="small text-muted">กล่องไฟล์แนบยังไม่เปิดใช้ (รอผู้ดูแลระบบอัปเดตฐานข้อมูล)</div>
            @endif
        </div>

        {{-- ส่วนของแพทย์ (ประเมินความเสี่ยง/การวางแผน) ย้ายไปโซน "ส่วนของแพทย์" ช่องใหญ่
             ในแท็บการรักษา (form/index) แล้ว — ห้ามใส่ tmc_risk_assessment/tmc_plan ซ้ำที่นี่
             เพราะ input ชื่อเดียวกัน 2 จุดในฟอร์มเดียว ค่าตัวหลังจะทับตัวแรกตอน Save --}}

        <div class="form-text mt-2">บันทึกพร้อมปุ่ม Save ของหน้าตรวจ</div>
    </div>
</div>

@if(\App\Models\Visit::destCountriesReady())
<link rel="stylesheet" href="/css/select2.min.css">
<style>
    #tmc_dest_pick + .select2-container .select2-selection--multiple { min-height: 31px; border-color: #ced4da; }
    #tmc_dest_pick + .select2-container .select2-selection__choice { background: #e8f5e9; border-color: #a5d6a7; }
    /* ช่องพิมพ์ค้นหาของ select2 เป็น textarea — สไตล์ textarea ทั้งหน้าทำให้มันเด้งขึ้นบรรทัดบนตัวโต · บังคับให้อยู่ต่อท้ายชิป */
    #tmc_dest_pick + .select2-container .select2-selection--multiple { display: flex; flex-wrap: wrap; align-items: center; padding: 0 4px 4px; }
    #tmc_dest_pick + .select2-container .select2-selection__rendered { display: contents; }
    #tmc_dest_pick + .select2-container .select2-search--inline { flex: 1 1 8rem; }
    #tmc_dest_pick + .select2-container .select2-search__field { display: inline-block; width: 100% !important; min-height: 0; height: 22px; margin: 4px 0 0 4px;
        font: inherit; font-size: .875rem; font-weight: normal; line-height: 22px; border: 0; padding: 0; box-shadow: none; }
</style>
<script src="/js/select2.min.js"></script>
<script>
// ประเทศปลายทาง: select2 หลายตัว -> เขียนรหัส "KEN,TZA" ลง hidden (ชื่อที่ฟอร์มส่งจริง)
(function () {
    var sel = document.getElementById('tmc_dest_pick'), hid = document.getElementById('tmc_dest_countries');
    if (!sel || !hid || !window.jQuery || !jQuery.fn.select2) return;
    var $s = jQuery(sel);
    $s.select2({
        width: '100%', placeholder: sel.dataset.placeholder, allowClear: false,
        // ค้นทั้งชื่อไทย อังกฤษ รหัส และชื่อที่คนพิมพ์กัน (อเมริกา, อังกฤษ, ซาอุ)
        matcher: function (params, data) {
            var q = jQuery.trim(params.term || '').toLowerCase();
            if (q === '') return data;
            var hay = ((data.element && data.element.dataset.search) || data.text).toLowerCase();
            return hay.indexOf(q) > -1 ? data : null;
        }
    });
    function sync() { hid.value = ($s.val() || []).join(','); }
    $s.on('change', sync);
    var g = document.getElementById('tmc-dest-guess-btn');
    if (g) g.addEventListener('click', function () {
        $s.val(g.dataset.codes.split(',')).trigger('change');
        document.getElementById('tmc-dest-guess').remove();
    });
})();
</script>
@endif
<script>
// ช่วงการเดินทาง บังคับเลือกก่อนกด Save หน้าหลัก (คลินิกขอ 2569-11-04)
// ผูกแบบ capture ให้ทำก่อนตัวจัดการ submit อื่น (นับช่องที่แก้ / ล็อกปุ่ม) — ยังไม่เลือก = ไม่ส่งฟอร์มเลย
(function () {
    var form = document.getElementById('visit_edit_form');
    var box = document.getElementById('tmc-phase-box');
    if (!form || !box || form.dataset.phaseGuard) return;
    form.dataset.phaseGuard = '1';
    form.addEventListener('submit', function (e) {
        if (form.querySelector('input[name="tmc_travel_phase"]:checked')) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        // กล่องอาจถูกพับ/ซ่อนอยู่ — เปิด details ที่ครอบไว้ก่อนเลื่อนไปหา
        for (var p = box.parentElement; p; p = p.parentElement) { if (p.tagName === 'DETAILS') p.open = true; }
        box.style.outline = '3px solid #dc3545';
        box.scrollIntoView({ block: 'center', behavior: 'smooth' });
        alert('กรุณาเลือก "ช่วงการเดินทาง" (ก่อน / ระหว่าง / หลังเดินทาง / ไม่มีการเดินทางไปต่างประเทศ) ในหัวข้อ "1 · การมาครั้งนี้" ก่อนบันทึก');
    }, true);
    box.addEventListener('change', function () { box.style.outline = ''; });
})();

(function () {
    if (window.__tmcHistInit) return;
    window.__tmcHistInit = true;
    // ผูกผ่าน bindThDatepicker (index.blade.php) — สคริปต์นี้รันก่อนไฟล์ปฏิทิน/ภาษาไทยท้ายหน้าโหลด
    // ผูกตรง ๆ บางเครื่องจะได้ปฏิทินผิดตัว วันที่ออกมาเป็น mm/dd/ค.ศ. แล้วบันทึกเพี้ยน
    var __histOpts = { format: 'dd/mm/yyyy', language: 'th-th', thaiyear: true, autoclose: true, todayHighlight: true };
    var __bindTh = window.bindThDatepicker || function (t, o) {
        if (window.jQuery && jQuery.fn.datepicker && (jQuery.fn.datepicker.dates || {})['th-th']) { jQuery(t).datepicker(o); }
    };
    __bindTh('.tmc-hist-date', __histOpts);

    // ไม่ทราบวัน (ออก/กลับ) -> ใส่ค่า "ไม่ทราบ" + ล็อกช่อง (ติ๊กออก = กรอกเองได้)
    document.querySelectorAll('.js-date-unknown').forEach(function (chk) {
        var inp = document.getElementById(chk.dataset.target);
        if (!inp) return;
        chk.addEventListener('change', function () {
            if (chk.checked) {
                inp.value = 'ไม่ทราบ';
                inp.setAttribute('readonly', 'readonly');
                if (window.jQuery && jQuery.fn.datepicker) { jQuery(inp).datepicker('destroy'); }
            } else {
                if (inp.value === 'ไม่ทราบ') inp.value = '';
                inp.removeAttribute('readonly');
                inp.classList.add('tmc-hist-date');
                __bindTh(inp, __histOpts);
            }
        });
    });

    // หมดประจำเดือน (วัยทอง) -> ล็อก/เคลียร์ LMP ทั้งวันแรก วันสุดท้าย และจำนวนวัน
    var __meno = document.getElementById('tmc_menopause');
    var __lmp  = document.getElementById('tmc_lmp');
    var __lmpEnd  = document.getElementById('tmc_lmp_end');
    var __lmpDays = document.getElementById('tmc_lmp_days');
    if (__meno && __lmp) {
        __meno.addEventListener('change', function () {
            [__lmp, __lmpEnd, __lmpDays].forEach(function (el) {
                if (!el) return;
                if (__meno.checked) {
                    el.value = '';
                    el.setAttribute('readonly', 'readonly');
                    if (el.type === 'text' && window.jQuery && jQuery.fn.datepicker) { jQuery(el).datepicker('destroy'); }
                } else {
                    el.removeAttribute('readonly');
                    if (el.type === 'text') { el.classList.add('tmc-hist-date'); __bindTh(el, __histOpts); }
                }
            });
        });
    }

    /*LMP-START*/
    // ช่วง LMP: วันแรก + จำนวนวัน <-> วันสุดท้าย (นับรวมวันแรก: 16/09 x 5 วัน = 16/09–20/09)
    // กติกาเดียวกับ Visit::lmpDays ฝั่งเซิร์ฟเวอร์ — แก้ที่หนึ่งต้องแก้อีกที่
    var TmcLmp = {
        parse: function (s) {
            var m = String(s || '').trim().match(/^(\d{1,2})\s*[\/\-.]\s*(\d{1,2})\s*[\/\-.]\s*(\d{4})$/);
            if (!m) return null;
            var d = +m[1], mo = +m[2], y = +m[3];
            if (y >= 2400) y -= 543; else if (y < 1900) return null;
            var dt = new Date(y, mo - 1, d);
            if (dt.getFullYear() !== y || dt.getMonth() !== mo - 1 || dt.getDate() !== d) return null;
            return dt;
        },
        fmt: function (dt) {
            return ('0' + dt.getDate()).slice(-2) + '/' + ('0' + (dt.getMonth() + 1)).slice(-2) + '/' + (dt.getFullYear() + 543);
        },
        endFromDays: function (start, days) {
            var a = TmcLmp.parse(start), n = parseInt(days, 10);
            if (!a || !(n >= 1)) return '';
            return TmcLmp.fmt(new Date(a.getFullYear(), a.getMonth(), a.getDate() + n - 1));
        },
        daysBetween: function (start, end) {
            var a = TmcLmp.parse(start), b = TmcLmp.parse(end);
            if (!a || !b) return null;
            return Math.round((Date.UTC(b.getFullYear(), b.getMonth(), b.getDate()) - Date.UTC(a.getFullYear(), a.getMonth(), a.getDate())) / 86400000) + 1;
        }
    };
    /*LMP-END*/
    if (__lmp && __lmpEnd && __lmpDays) {
        var __syncFromDays = function () {
            var out = TmcLmp.endFromDays(__lmp.value, __lmpDays.value);
            if (out) {
                __lmpEnd.value = out;
                if (window.jQuery && jQuery.fn.datepicker) { try { jQuery(__lmpEnd).datepicker('update', out); } catch (e) {} }
            }
        };
        var __syncFromEnd = function () {
            var n = TmcLmp.daysBetween(__lmp.value, __lmpEnd.value);
            __lmpDays.value = n && n > 0 ? n : '';
            __lmpEnd.classList.toggle('is-invalid', n !== null && n < 1);
            __lmpEnd.title = n !== null && n < 1 ? 'วันสุดท้ายอยู่ก่อนวันแรก' : '';
        };
        __lmpDays.addEventListener('input', __syncFromDays);
        __lmpEnd.addEventListener('change', __syncFromEnd);
        __lmpEnd.addEventListener('focusout', __syncFromEnd);
        // แก้วันแรกทีหลัง: ถ้ามีจำนวนวันอยู่แล้ว เลื่อนวันสุดท้ายตาม
        __lmp.addEventListener('change', function () { if (__lmpDays.value) { __syncFromDays(); } else { __syncFromEnd(); } });
    }

    // ---- จุดประสงค์ / ลักษณะการเดินทาง: เลือกจากรายการ หรือ "อื่น ๆ" แล้วพิมพ์เอง (ค่าจริงอยู่ช่องข้อความ data-target) ----
    document.querySelectorAll('select.js-pick-other').forEach(function (__pp) {
        var __pt = document.getElementById(__pp.dataset.target);
        if (!__pt) return;
        __pp.addEventListener('change', function () {
            if (__pp.value === '__other') {
                // ค่าเดิมเป็นตัวเลือกในรายการ -> ล้างให้พิมพ์ใหม่ · เป็นข้อความที่พิมพ์เองอยู่แล้ว -> คงไว้
                var known = Array.prototype.some.call(__pp.options, function (o) { return o.value === __pt.value && o.value !== ''; });
                if (known) __pt.value = '';
                __pt.classList.remove('d-none');
                __pt.focus();
            } else {
                __pt.value = __pp.value;
                __pt.classList.add('d-none');
            }
        });
    });

    // ---- ไฟล์แนบแผนการเดินทาง: อัปโหลด/ลบด้วย fetch (กล่องอยู่ในฟอร์มหลัก ห้ามใช้ฟอร์มซ้อน) ----
    var __tf = document.getElementById('tmc-travel-files');
    if (__tf && document.getElementById('tmc-tf-upload')) {
        var csrfToken = function () { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : @json(csrf_token()); };
        var tfStatus = function (msg, ok) {
            var st = __tf.querySelector('.js-tf-status');
            st.textContent = msg || '';
            st.className = 'small mt-1 js-tf-status ' + (ok ? 'text-success' : 'text-danger');
        };
        var tfSend = function (url, fd, method) {
            return fetch(url, { method: method, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken() }, credentials: 'same-origin', body: fd })
                .then(function (r) { return r.json().catch(function () { return { ok: false, msg: 'เซิร์ฟเวอร์ตอบกลับผิดพลาด (' + r.status + ')' }; }); })
                .then(function (j) {
                    if (j.html !== undefined) { __tf.querySelector('.js-tf-list').innerHTML = j.html; }
                    tfStatus(j.msg, j.ok);
                    return j;
                })
                .catch(function () { tfStatus('ส่งไม่สำเร็จ (เชื่อมต่อเซิร์ฟเวอร์ไม่ได้)', false); return { ok: false }; });
        };
        document.getElementById('tmc-tf-upload').addEventListener('click', function () {
            var btn = this, inp = document.getElementById('tmc-tf-input');
            if (!inp.files || !inp.files.length) { tfStatus('ยังไม่ได้เลือกไฟล์', false); inp.focus(); return; }
            var total = 0, fd = new FormData();
            Array.prototype.forEach.call(inp.files, function (f) { total += f.size; fd.append('files[]', f); });
            // เกิน post_max_size PHP ทิ้งทั้งคำขอ (รวม CSRF) ได้หน้า 419 งง ๆ — เช็คก่อนส่ง
            if (total > parseInt(__tf.dataset.max, 10)) { tfStatus('ไฟล์รวมใหญ่เกินที่ส่งได้ต่อครั้ง — ลองส่งทีละไฟล์', false); return; }
            fd.append('title', document.getElementById('tmc-tf-title').value);
            btn.disabled = true; tfStatus('กำลังอัปโหลด…', true);
            tfSend(__tf.dataset.url, fd, 'POST').then(function (j) {
                btn.disabled = false;
                if (j.ok) { inp.value = ''; document.getElementById('tmc-tf-title').value = ''; }
            });
        });
        __tf.addEventListener('click', function (e) {
            var b = e.target.closest('.js-tf-delete');
            if (!b) return;
            if (!confirm('ลบไฟล์ "' + (b.dataset.name || '') + '" ?')) return;
            var fd = new FormData(); fd.append('_method', 'DELETE');
            b.disabled = true;
            tfSend(b.dataset.url, fd, 'POST');
        });
    }

    // ---- สาเหตุการมา: checkbox -> hidden tmc_reason (comma) + เพิ่มรายการใหม่ ----
    var hidden = document.getElementById('tmc_reason');
    var list   = document.getElementById('tmc-reason-list');
    if (!hidden || !list) return;

    function sync() {
        var vals = [];
        list.querySelectorAll('.tmc-reason-cb:checked').forEach(function (cb) { vals.push(cb.value); });
        hidden.value = vals.join(',');
    }
    list.addEventListener('change', function (e) {
        if (e.target.classList.contains('tmc-reason-cb')) sync();
    });

    function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : ''; }

    function addCheckbox(id, name, checked) {
        var col = document.createElement('div'); col.className = 'col-md-4';
        var wrap = document.createElement('div'); wrap.className = 'form-check';
        var cb = document.createElement('input');
        cb.type = 'checkbox'; cb.className = 'form-check-input tmc-reason-cb';
        cb.value = name; cb.id = 'tmc_reason_' + id; cb.checked = !!checked;
        var lb = document.createElement('label');
        lb.className = 'form-check-label'; lb.setAttribute('for', cb.id); lb.textContent = name;
        wrap.appendChild(cb); wrap.appendChild(lb); col.appendChild(wrap); list.appendChild(col);
        return cb;
    }

    var newInput = document.getElementById('tmc-reason-new');
    var addBtn   = document.getElementById('tmc-reason-add');

    function addReason() {
        var name = (newInput.value || '').trim();
        if (!name) return;
        // มีอยู่แล้วบนจอ -> แค่ติ๊ก ไม่ยิงซ้ำ
        var existing = Array.prototype.find.call(list.querySelectorAll('.tmc-reason-cb'), function (cb) { return cb.value === name; });
        if (existing) { existing.checked = true; sync(); newInput.value = ''; return; }

        var fd = new FormData(); fd.append('name', name); fd.append('_token', csrf());
        fetch('{{ route('tmc-reason.store') }}', {
            method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin', body: fd
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            if (!j.ok) { alert(j.msg || 'เพิ่มไม่สำเร็จ'); return; }
            var already = Array.prototype.find.call(list.querySelectorAll('.tmc-reason-cb'), function (cb) { return cb.value === j.name; });
            if (already) { already.checked = true; } else { addCheckbox(j.id, j.name, true); }
            newInput.value = ''; sync();
        })
        .catch(function () { alert('เพิ่มไม่สำเร็จ'); });
    }
    if (addBtn) addBtn.addEventListener('click', addReason);
    if (newInput) newInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addReason(); } });

    sync();
})();
</script>
