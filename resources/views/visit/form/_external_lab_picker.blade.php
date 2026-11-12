{{-- ตัวเลือกรายการ Lab ส่งตรวจภายนอกแบบ "เลือกหลายรายการแล้วยืนยันทีเดียว" (2569-10-02)
     ใช้ 2 ที่: การ์ดในหน้าตรวจ OPD (visit/form/external_lab — อยู่ในฟอร์มหลัก) และ popup Lab ภายนอกของบัตร TB 01 (โหลดด้วย fetch)
     ⚠️ ห้ามมี <script> / <form> และช่องกรอกห้ามมี name= — ตัวจัดการอยู่ที่ partials/external_lab_picker_js (หน้าที่โหลดเต็ม)
     ตัวแปร: $visit · $fragment (ถ้าส่ง = ขอตารางรายการกลับมาใน JSON) · $page --}}
@php
    $__pkTests = \App\Models\ExternalLabTest::active()->orderBy('code')->get();
@endphp

<div class="js-ext-picker" data-url="{{ route('external-lab.store', $visit->id) }}"
     data-fragment="{{ $fragment ?? '' }}" data-page="{{ $page ?? 1 }}">
    <div class="row g-2 mb-2">
        <div class="col-md-8">
            <label class="form-label small text-muted mb-1">
                รายการตรวจ (N Health · แล็บอื่นมีชื่อแล็บในวงเล็บเหลี่ยมนำหน้า) — เลือกได้หลายรายการ แล้วกดยืนยันทีเดียว
            </label>
            <input type="text" class="form-control form-control-sm js-ext-search"
                   placeholder="🔍 พิมพ์ชื่อหรือรหัสเพื่อค้นหา เช่น HBs, N620, thyroid" autocomplete="off">
        </div>
        <div class="col-md-4 d-flex align-items-end pb-1">
            <span class="form-text mb-0 js-ext-count"></span>
        </div>
    </div>

    <div class="row g-2 align-items-start mb-2">
        <div class="col-md-9">
            <select class="form-select form-select-sm js-ext-pick" size="1">
                <option value="">— เลือกรายการ —</option>
                @foreach($__pkTests as $t)
                    @php $__cf = $t->confusableWith(); @endphp
                    <option value="{{ $t->id }}"
                            data-price="{{ $t->price }}"
                            data-vendor="{{ $t->vendor }}"
                            data-search="{{ mb_strtolower($t->code . ' ' . $t->name) }}"
                            @if($t->inHouseName()) data-inhouse="{{ $t->inHouseName() }}" @endif
                            @if($__cf) data-confuse="{{ $__cf['name'] }}" data-confuse-note="{{ $__cf['note'] }}" @endif
                    >{{ $t->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 d-grid">
            <button type="button" class="btn btn-outline-success btn-sm js-ext-add-pending">
                <i class="fas fa-plus"></i> ใส่ในรายการที่จะสั่ง
            </button>
        </div>
    </div>

    <div class="form-text js-ext-ref d-none mb-1"></div>
    {{-- เตือน 2 ชั้นก่อนสั่ง — ต้นเหตุที่เคยสั่งผิดจนคลินิกจ่ายเอง --}}
    <div class="alert alert-danger py-2 px-3 mb-2 d-none js-ext-warn-inhouse">
        <i class="fas fa-triangle-exclamation me-1"></i>
        <b>รายการนี้ห้องแล็บเราตรวจเองได้</b> (<span class="js-ext-warn-inhouse-name"></span>)
        — ส่งออกนอกคือเสียค่าใช้จ่ายโดยไม่จำเป็น ตรวจสอบก่อนว่าจำเป็นต้องส่งจริงหรือไม่
    </div>
    <div class="alert alert-warning py-2 px-3 mb-2 d-none js-ext-warn-confuse">
        <i class="fas fa-circle-exclamation me-1"></i>
        <b>ระวังสับสนกับ</b> <span class="js-ext-warn-confuse-name"></span>
        <div class="small mt-1 js-ext-warn-confuse-note"></div>
    </div>

    {{-- รายการที่จะสั่ง — ยังไม่บันทึกจนกว่าจะกด "ยืนยันสั่ง" (สีกำหนดเอง เพราะ Bootstrap 5.1 ไม่มีคลาส *-subtle) --}}
    <div class="js-ext-pending d-none mb-3 p-2 rounded" style="background:#f0fdf4; border:1px solid #86efac;">
        <div class="fw-semibold small mb-1" style="color:#166534;">
            <i class="fas fa-list-check me-1"></i> รายการที่จะสั่ง (<span class="js-ext-pending-n">0</span>) — ยังไม่บันทึก
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-2 bg-white">
                <thead class="table-light">
                    <tr>
                        <th style="min-width:220px;">รายการ</th>
                        <th style="width:130px;">ราคาที่เก็บคนไข้</th>
                        <th style="width:140px;">วันที่ส่งตรวจ</th>
                        <th style="width:40px;"></th>
                    </tr>
                </thead>
                <tbody class="js-ext-pending-body"></tbody>
            </table>
        </div>
        <div class="alert alert-danger py-2 px-3 mb-2 d-none js-ext-pick-error"></div>
        <div class="d-flex justify-content-end align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-light btn-sm border js-ext-pending-clear">ล้างรายการ</button>
            <button type="button" class="btn btn-success btn-sm js-ext-confirm">
                <i class="fas fa-check"></i> ยืนยันสั่ง <span class="js-ext-pending-n">0</span> รายการ
            </button>
        </div>
    </div>
</div>
