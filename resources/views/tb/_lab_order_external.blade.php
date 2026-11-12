{{-- เนื้อใน popup "Lab ภายนอก" ของบัตร TB 01 — โหลดด้วย fetch ทุกครั้งที่เปิด/หลังบันทึก (2569-10-02)
     ใช้ endpoint เดียวกับการ์ด Lab ส่งตรวจภายนอกของหน้าตรวจ (ExternalLabController store/update/destroy + syncBilling)
     ⚠️ ห้ามมี <script> — ตัวจัดการอยู่ใน tb/_lab_order_js (หน้าบัตร) ผูกแบบ delegate
     มีฟอร์มเดียว (บันทึกผล-ราคา) ส่วนเพิ่มรายการเป็นตัวเลือกแบบไม่มี <form> · ตัว popup เองไม่ใช่ฟอร์ม จึงไม่ซ้อนกัน
     ตัวแปร: $reg · $tv (visit วันนี้) --}}
@php
    $__rows  = \App\Models\VisitExternalLab::where('visit_id', $tv->id)->orderBy('id')->get();
    $__total = $__rows->sum(fn ($r) => (float) $r->price);
@endphp

<div class="alert alert-danger py-2 d-none js-lo-errors" role="alert"></div>

@if(\App\Models\LabCatalogItem::availableFor(2))
    {{-- แคตตาล็อกแล็บรวมเปิดใช้แล้ว: การสั่งย้ายไปปุ่ม "สั่งแล็บ" ปุ่มเดียว (2569-10-02) · popup นี้เหลือลงผล/ราคา/ลบ --}}
    <div class="small text-muted mb-2"><i class="fa fa-circle-info me-1"></i>สั่งรายการเพิ่ม → ปิด popup แล้วกด <b>"สั่งแล็บ"</b> ในกล่องสั่งตรวจครั้งนี้</div>
@else
<div class="tb-lo-section">
    <div class="tb-lo-section-title"><i class="fa fa-plus-circle me-1"></i> เพิ่มรายการส่งตรวจ</div>
    {{-- ตัวเลือกหลายรายการ ใช้ร่วมกับการ์ดในหน้าตรวจ (2569-10-02) — ตัวจัดการ partials/external_lab_picker_js อยู่ในหน้าบัตร --}}
    @include('visit.form._external_lab_picker', ['visit' => $tv])
</div>
@endif

<div class="tb-lo-section">
    <div class="tb-lo-section-title d-flex justify-content-between flex-wrap gap-2">
        <span><i class="fa fa-list-check me-1"></i> รายการของ visit วันนี้ · ลงผล / ราคา</span>
        @if($__rows->count())
            <a href="{{ route('external-lab.print', ['visit' => $tv->id]) }}" target="_blank" class="btn btn-outline-success btn-sm py-0">
                <i class="fa fa-print"></i> พิมพ์ผล
            </a>
        @endif
    </div>
    @if($__rows->isEmpty())
        <div class="text-center text-muted small py-3"><i class="fa fa-inbox me-1"></i> ยังไม่มีรายการส่งตรวจภายนอกใน visit นี้</div>
    @else
        <form id="tbExtRowsForm" data-url="{{ route('external-lab.update', $tv->id) }}" autocomplete="off" novalidate>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead style="background:#fff7ed;">
                        <tr>
                            <th style="min-width:200px;">รายการ</th>
                            <th style="width:110px;">ราคา</th>
                            <th style="min-width:240px;">ผลตรวจ</th>
                            <th style="width:130px;">วันที่ได้ผล</th>
                            <th style="width:46px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($__rows as $r)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $r->name }}</div>
                                <div class="small text-muted">
                                    @if($r->vendor && $r->vendor !== 'N Health')<span style="color:#b45309;">{{ $r->vendor }}</span> · @endif
                                    @if($r->code){{ $r->code }}@endif
                                    @if($r->ref_price) · ใบเสนอราคา {{ number_format($r->ref_price, 0) }}.-@endif
                                </div>
                            </td>
                            <td><input type="number" step="0.01" min="0" name="rows[{{ $r->id }}][price]" value="{{ $r->price }}"
                                       class="form-control form-control-sm"></td>
                            <td>
                                <input type="text" name="rows[{{ $r->id }}][result]" value="{{ $r->result }}"
                                       class="form-control form-control-sm" placeholder="คีย์ผลที่ได้จากแล็บนอก">
                                <textarea name="rows[{{ $r->id }}][remark]" rows="1" class="form-control form-control-sm mt-1"
                                          placeholder="รายละเอียดผล เช่น ค่าที่วัดได้ / หน่วย / ช่วงอ้างอิง">{{ $r->remark }}</textarea>
                            </td>
                            <td><input type="text" name="rows[{{ $r->id }}][result_date]" value="{{ $r->result_date_th }}"
                                       class="form-control form-control-sm date" placeholder="วว/ดด/ปปปป" autocomplete="off"></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-danger btn-sm js-ext-del"
                                        data-url="{{ route('external-lab.destroy', [$tv->id, $r->id]) }}" data-name="{{ $r->name }}" title="ลบรายการนี้">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background:#fffbeb;">
                            <td class="text-end fw-bold">รวมค่าตรวจแล็บนอก</td>
                            <td class="fw-bold">{{ number_format($__total, 2) }}</td>
                            <td colspan="3" class="small text-muted">ราคาเข้าบิลของ visit วันนี้ให้การเงินอัตโนมัติ</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </form>
    @endif
</div>
