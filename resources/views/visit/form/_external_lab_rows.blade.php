{{-- ตารางรายการ Lab ส่งตรวจภายนอกของ visit (การ์ดในหน้าตรวจ OPD)
     ใช้ 2 ที่: เรนเดอร์ครั้งแรกในการ์ด และส่งกลับใน JSON (ExternalLabController fragment=opd) ให้สลับเนื้อในที่หลังบันทึก
     ⚠️ ห้ามมี <script> (ใส่ด้วย innerHTML สคริปต์จะไม่รัน) และห้ามมี <form> (อยู่ในฟอร์มหลักของหน้าตรวจ)
     ⚠️ ช่องกรอกห้ามมี name= — ไม่งั้นจะถูกส่งไปกับปุ่ม Save หน้าตรวจ และถูกนับใน _touched
     ตัวแปร: $visit --}}
@php
    $__rows  = \App\Models\VisitExternalLab::where('visit_id', $visit->id)->orderBy('id')->get();
    $__total = $__rows->sum(fn ($r) => (float) $r->price);
@endphp

@if($__rows->isEmpty())
    <div class="alert alert-light border text-center text-muted mb-0" data-ext-count="0">
        <i class="fas fa-inbox me-1"></i> ยังไม่มีรายการส่งตรวจภายนอกใน visit นี้
    </div>
@else
    <div class="table-responsive" data-ext-count="{{ $__rows->count() }}">
        <table class="table table-sm align-middle" id="ext-lab-table">
            <thead class="table-light">
                <tr>
                    <th style="width:34px;">#</th>
                    <th style="min-width:220px;">รายการ</th>
                    <th style="width:110px;">ราคา</th>
                    <th style="min-width:260px;">ผลตรวจ</th>
                    <th style="width:130px;">วันที่ได้ผล</th>
                    <th style="width:46px;"></th>
                </tr>
            </thead>
            <tbody>
            @foreach($__rows as $i => $r)
                <tr data-row="{{ $r->id }}">
                    <td class="text-muted">{{ $i + 1 }}</td>
                    <td>
                        <div class="fw-semibold">{{ $r->name }}</div>
                        @if($r->vendor && $r->vendor !== 'N Health')
                            <div class="small" style="color:#b45309;"><i class="fa fa-building me-1"></i>{{ $r->vendor }}</div>
                        @endif
                        <div class="small text-muted">
                            @if($r->code)<span class="badge bg-light text-muted border">{{ $r->code }}</span>@endif
                            @if($r->specimen) {{ $r->specimen }} @endif
                            @if($r->ref_price)
                                · ราคาตามใบเสนอราคา {{ number_format($r->ref_price, 0) }}.-
                            @endif
                            @if($r->sent_date_th ?? null) · ส่งตรวจ {{ $r->sent_date_th }} @endif
                        </div>
                        @if($r->hasResult())
                            <span class="badge bg-info text-dark mt-1">
                                <i class="fas fa-flask-vial me-1"></i> ผลจาก Lab นอก
                            </span>
                        @else
                            <span class="badge bg-secondary mt-1">รอผลจากแล็บนอก</span>
                        @endif
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" data-f="price"
                               value="{{ $r->price }}"
                               class="form-control form-control-sm js-extlab-row-price">
                    </td>
                    <td>
                        {{-- ผลหลัก + รายละเอียดผลใต้ช่องผล วางเหมือน Lab ในบ้าน (ผล/Remark ซ้อนกันในช่องเดียว)
                             เดิมรายละเอียดอยู่คนละคอลัมน์ (หมายเหตุ) ทำให้อ่านไม่ต่อกับผล --}}
                        <input type="text" data-f="result" value="{{ $r->result }}"
                               class="form-control form-control-sm" placeholder="คีย์ผลที่ได้จากแล็บนอก">
                        <textarea data-f="remark" rows="1"
                                  class="form-control form-control-sm mt-1 js-extlab-detail"
                                  placeholder="รายละเอียดผล เช่น ค่าที่วัดได้ / หน่วย / ช่วงอ้างอิง"
                                  style="overflow:hidden; resize:vertical;">{{ $r->remark }}</textarea>
                    </td>
                    <td>
                        <input type="text" data-f="result_date" value="{{ $r->result_date_th }}"
                               class="form-control form-control-sm date" placeholder="ว/ด/ป" autocomplete="off">
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-outline-danger btn-sm"
                                data-extlab-del="{{ $r->id }}" data-name="{{ $r->name }}" title="ลบรายการนี้">
                            <i class="fa fa-trash"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
                <tr class="table-light">
                    <td colspan="2" class="text-end fw-bold">รวมค่าตรวจแล็บนอก</td>
                    <td class="fw-bold js-extlab-total">{{ number_format($__total, 2) }}</td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="d-flex justify-content-end align-items-center gap-2">
        <button type="button" class="btn btn-primary btn-sm" id="ext-save-btn">
            <i class="fas fa-save"></i> บันทึกผล / ราคา แล็บนอก
        </button>
    </div>
@endif
