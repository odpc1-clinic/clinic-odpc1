{{-- หนึ่งแถวของการวินิจฉัย ใช้ทั้งแถวจริงและ <template> สำหรับปุ่ม "เพิ่มอีกรายการ"
     ตัวแปร: $i = ลำดับ (เป็น __IDX__ เมื่อใช้เป็น template), $row = ค่าเดิม (ตอน validate ไม่ผ่าน)

     ⚠️ ห้ามใช้ id ในนี้ เพราะแถวถูกโคลนได้หลายชุด — id ซ้ำแล้ว JS จะไปจับแถวแรกเสมอ
        ใช้ class + ค้นหาภายในแถวตัวเอง (closest('.dg-row')) แทน --}}
@php
    $row = $row ?? [];
    $v = fn ($k, $d = '') => $row[$k] ?? $d;
    $used = $usedDiseases ?? [];   // รหัสโรคที่ลงไว้แล้วใน visit นี้
@endphp

<div class="card mb-3 dg-row">
    <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
        <b><i class="fas fa-notes-medical me-1 text-danger"></i> รายการที่ <span class="dg-no">1</span></b>
        <button type="button" class="btn btn-outline-danger btn-sm js-dg-del" title="ลบรายการนี้">
            <i class="fa fa-trash"></i>
        </button>
    </div>
    <div class="card-body">
        <div class="row g-3">

            <div class="col-md-6">
                <label class="form-label">โรคที่วินิจฉัย (Disease)</label>
                <select class="form-select js-dg-disease" name="rows[{{ $i }}][disease]">
                    <option value="">— เลือกโรค —</option>
                    @foreach (App\Models\Diagnosi::$diseaseOption as $key => $value)
                        @php $isUsed = in_array((string) $key, $used, true); @endphp
                        <option value="{{ $key }}" @if($isUsed) data-used="1" @endif
                                @if((string) $v('disease') === (string) $key) selected @endif>
                            {{ $value }}@if($isUsed) — ลงไว้แล้ว @endif
                        </option>
                    @endforeach
                </select>
                <div class="form-text">เว้นว่างไว้ = ไม่บันทึกแถวนี้</div>
                <div class="alert alert-warning py-2 px-3 mt-2 mb-0 js-dg-dup" style="display:none;">
                    <i class="fas fa-triangle-exclamation me-1"></i>
                    <b>โรคนี้ลงไว้ใน visit นี้แล้ว</b> — ถ้าเป็นคนละระยะ/คนละรายละเอียด/คนละสถานะของโรค บันทึกต่อได้ตามปกติ
                    ถ้าซ้ำของเดิมทั้งหมด (รวมสถานะ) ระบบจะข้ามให้เอง
                </div>
            </div>

            {{-- ช่องเสริม โผล่ตามโรคที่เลือกเท่านั้น จะได้ไม่รกจอ --}}
            <div class="col-md-6 dg-extra js-dg-other" style="display:none;">
                <label class="form-label">ระบุโรค (Other Disease)</label>
                <input class="form-control" type="text" name="rows[{{ $i }}][other_disease]" value="{{ $v('other_disease') }}">
            </div>

            {{-- ไม่มีช่อง ICD-10 ในหน้าวินิจฉัยแล้ว (คลินิกขอ 2569-10-01) — รหัสเดิมที่เคยลงยังอยู่ในฐาน --}}

            <div class="col-md-6 dg-extra js-dg-term" style="display:none;">
                <label class="form-label">ซิฟิลิสระยะ</label>
                <select class="form-select" name="rows[{{ $i }}][term_syphilis]">
                    @foreach (App\Models\Diagnosi::$termSyphilisOption as $key => $value)
                        <option value="{{ $key }}" @if((string) $v('term_syphilis', 0) === (string) $key) selected @endif>{{ $value }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6 dg-extra js-dg-egasp" style="display:none;">
                <label class="form-label">Egasp Information</label>
                <input class="form-control" type="text" name="rows[{{ $i }}][egasp_info]" value="{{ $v('egasp_info') }}">
            </div>

            <div class="col-md-6 dg-extra js-dg-gf" style="display:none;">
                <label class="form-label">GF Information</label>
                <input class="form-control" type="text" name="rows[{{ $i }}][gf]" value="{{ $v('gf') }}">
            </div>

            {{-- วันที่เริ่มป่วยย้ายไปอยู่หน้าตรวจ (ตรงประวัติการเจ็บป่วยปัจจุบัน) แล้ว
                 เป็นค่าเดียวต่อ visit ไม่ต้องกรอกซ้ำทีละวินิจฉัย (ผู้ใช้ขอ 2569-08-07) --}}

            {{-- สถานะของโรคเป็นของงาน STI — visit TB ไม่ต้องกรอก (ไม่ส่งช่องนี้ = เก็บว่าง) --}}
            @unless($hideState ?? false)
            <div class="col-md-6">
                <label class="form-label">สถานะของโรค</label>
                <select class="form-select js-dg-state" name="rows[{{ $i }}][disease_state]">
                    @foreach (App\Models\Diagnosi::$diseaseStateOption as $key => $value)
                        <option value="{{ $key }}" @if((string) $v('disease_state') === (string) $key) selected @endif>{{ $value }}</option>
                    @endforeach
                </select>
            </div>
            @endunless

            {{-- ไม่มีช่อง "สถานะของโรค อื่น ๆ" ในหน้านี้
                 ของเดิมผูกไว้กับ disease_state == 10 ซึ่งตอนนี้คือ "มาฟังผลเลือด RPR หลังรักษา 6 เดือน"
                 (เป็นเศษกติกาจากรายการสถานะชุดเก่าที่เคยมี "อื่น ๆ" อยู่ตำแหน่งนั้น)
                 ช่องเลยไปโผล่ผิดจังหวะ จึงไม่เอามาไว้ที่นี่ — คอลัมน์ในฐานข้อมูลยังอยู่ครบ
                 และหน้าแก้ไขวินิจฉัยยังใช้ของเดิมได้เหมือนเดิม --}}

        </div>
    </div>
</div>
