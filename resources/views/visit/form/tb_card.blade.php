{{-- ============================================================
     บัตร TB 01 ของคนไข้ในหน้าตรวจ — แสดงเป็น "แถบสรุป + ปุ่มเปิดบัตร" เท่านั้น (2569-09-29)

     ประวัติ: 2569-09-24 เคยฝังบัตรทั้งใบไว้ในหน้าตรวจ พยาบาลจะได้ทำงานจบหน้าเดียว
     แต่หน้าตรวจยาวมากจนหมอเลื่อนผ่านแล้วเผลอกดโดนของในบัตร โดยเฉพาะปฏิทินกินยา
     ที่กดทีเดียวก็เปลี่ยนสถานะวันนั้นเลย คลินิกจึงขอให้ซ่อนออกจากหน้าตรวจ
     ตอนนี้เหลือแค่แถบบอกสถานะ ส่วนการแก้บัตรทำในหน้าบัตรที่เปิดเป็นแท็บใหม่

     ข้อดีอีกอย่าง: หน้าตรวจไม่ต้องโหลดข้อมูลบัตรทั้งชุด (ผลเสมหะ ขนาดยา ปฏิทิน เกณฑ์ขนาดยา)
     ทุกครั้งที่เปิด ซึ่งเดิมทำให้หน้าตรวจของคลินิกวัณโรคช้ากว่าคลินิกอื่น

     2569-10-02: ย้ายมาอยู่ใต้ข้อมูลคนไข้ = อยู่ "ใน" ฟอร์มหลัก <form id="visit_edit_form"> แล้ว
     ห้ามมี <form> ในไฟล์นี้ (CLAUDE.md ข้อ 1) — ช่อง DOT ไม่มี name (ไม่ให้ติดไปกับปุ่ม Save ของหน้าตรวจ)
     ปุ่มบันทึก DOT ให้ JS สร้างฟอร์มต่อกับ body แล้วส่งไป tb.dotStatus เหมือนเดิม
     ============================================================ --}}
@php
    $__tbReg = \App\Models\TbRegistration::forVisit($visit);   // visit ย้อนหลัง = บัตรที่ครอบวันนั้น (2569-10-27)
    // บัตรอื่นของคนไข้ (เช่น บัตรเก่าที่รักษาจบแล้ว) — เดิมขึ้นเป็นกล่องเหลืองซ้ำในใบคัดกรอง รวมมาไว้แถบนี้ (คลินิกขอ 2569-10-01)
    $__tbOther = $__tbReg
        ? \App\Models\TbRegistration::where('patient_id', $visit->patient_id)->where('id', '!=', $__tbReg->id)->orderByDesc('id')->get()
        : collect();
@endphp

@if($__tbReg)
    <div class="d-flex flex-wrap align-items-center gap-2 p-2 mb-2 rounded" id="tb-card-bar"
         style="background:#fff7ed; border:1px solid #f0c088;">
        <span class="fw-bold" style="color:#b45309;">
            <i class="fa-solid fa-id-card me-1"></i> บัตรวัณโรค TB 01
        </span>
        <span class="badge bg-light text-dark border">{{ $__tbReg->tb_no ?: 'ยังไม่มีเลขทะเบียน' }}</span>
        <span class="badge bg-light text-dark border">{{ $__tbReg->typeName() }}</span>
        @if($__tbReg->treatment_start_date)
            <span class="badge bg-light text-dark border">เดือนที่ {{ $__tbReg->monthsOnTreatment() }}</span>
        @endif
        @if($__tbReg->currentRegimenText())
            <span class="badge bg-light text-dark border">{{ $__tbReg->currentRegimenText() }}</span>
        @endif
        {{-- เปิด/ปิด DOT — กำหนดจากหน้าตรวจเท่านั้น (คลินิกขอ 2569-09-30 เอาออกจากหน้าติดตาม DOT)
             เป็น div ไม่ใช่ form (อยู่ในฟอร์มหลัก) · ช่องใช้ data-field แทน name --}}
        <div class="d-flex flex-wrap align-items-center gap-1 js-dot-status-form"
             data-url="{{ route('tb.dotStatus', $__tbReg->id) }}">
            <span class="small fw-bold" style="color:#b45309;">DOT:</span>
            <select data-field="dot_active" class="form-select form-select-sm w-auto js-dot-status">
                <option value="1" {{ $__tbReg->dot_active ? 'selected' : '' }}>กำลังกำกับกินยา (DOT)</option>
                <option value="0" {{ $__tbReg->dot_active ? '' : 'selected' }}>Off DOT</option>
            </select>
            <input type="text" data-field="dot_channel" list="tb-card-dot-channels" maxlength="255"
                   class="form-control form-control-sm js-dot-channel" style="width:10rem;"
                   value="{{ $__tbReg->dot_channel }}" placeholder="ช่องทาง DOT" title="ช่องทาง DOT — อสม./รพ.สต. จะไม่แสดงในหน้า DOT">
            <datalist id="tb-card-dot-channels">
                @foreach(\App\Models\TbRegistration::$dotChannelOption as $__o)<option value="{{ $__o }}">@endforeach
            </datalist>
            <input type="text" data-field="dot_off_reason" maxlength="255"
                   class="form-control form-control-sm js-dot-off-reason {{ $__tbReg->dot_active ? 'd-none' : '' }}"
                   style="width:14rem;" value="{{ $__tbReg->dot_active ? '' : $__tbReg->dot_off_reason }}"
                   placeholder="เหตุผล เช่น AFB เป็นลบแล้ว" {{ $__tbReg->dot_active ? '' : 'disabled' }}>
            <button type="button" class="btn btn-sm btn-outline-dark d-none js-dot-status-save">บันทึก DOT</button>
            @if(! $__tbReg->dot_active && $__tbReg->dot_off_date)
                <span class="small text-muted">ปิดเมื่อ {{ \App\Utillity::db2thDate($__tbReg->dot_off_date) }}</span>
            @endif
        </div>
        {{-- ไม่ใส่ rel="noopener" — บัตรต้องอ้างถึงแท็บหน้าตรวจนี้ได้ เพื่อให้ปุ่ม "กลับหน้าตรวจ" ปิดแท็บบัตรแล้วกลับมาที่นี่ (เว็บเดียวกัน ปลอดภัย) --}}
        <a href="{{ route('tb.show', ['id' => $__tbReg->id, 'from' => 'visit', 'visit' => $visit->id, 'page' => request('page', 1)]) }}" target="_blank"
           class="btn btn-sm ms-auto" style="background:{{ $__tbReg->cardColor() }}; color:#fff;">
            <i class="fa fa-up-right-from-square me-1"></i> เปิด{{ $__tbReg->cardName() }} (แท็บใหม่)
        </a>
        <div class="w-100 small text-muted">
            @if($__tbOther->count())
                บัตรอื่นของคนไข้:
                @foreach($__tbOther as $__o)
                    <a href="{{ route('tb.show', ['id' => $__o->id, 'from' => 'visit', 'visit' => $visit->id, 'page' => request('page', 1)]) }}" target="_blank" class="me-2">
                        {{ $__o->tb_no ?: 'ไม่มีเลขทะเบียน' }} ({{ $__o->typeName() }} · {{ $__o->outcomeName() }})
                    </a>
                @endforeach
                ·
            @endif
            ขึ้นทะเบียนแล้ว — ผลตรวจ/ขนาดยา/DOT บันทึกที่บัตร ใบคัดกรองใช้กับคนที่ยังไม่ขึ้นทะเบียน
        </div>
    </div>

    <script>
    // ไฟล์นี้ include ในหน้าตรวจที่โหลดเต็ม (ไม่ได้มาทาง AJAX) สคริปต์จึงรันได้ — ผูกแบบ delegate ที่ document
    (function () {
        if (window.__tbDotBarInit) return;
        window.__tbDotBarInit = true;

        var orig = null;
        function box() { return document.querySelector('.js-dot-status-form'); }
        function val(b, f) { var el = b.querySelector('[data-field="' + f + '"]'); return el ? el.value : ''; }
        function refresh(b) {
            if (orig === null) return;
            var sel = b.querySelector('.js-dot-status'), why = b.querySelector('.js-dot-off-reason'),
                btn = b.querySelector('.js-dot-status-save'), off = sel.value === '0';
            why.classList.toggle('d-none', !off); why.disabled = !off;
            btn.classList.toggle('d-none', sel.value === orig.active && val(b, 'dot_channel') === orig.channel);
        }
        // จำค่าตอนเปิดหน้า — เลือกค่าใหม่แล้วค่อยโผล่ปุ่มบันทึก + ช่องเหตุผลตอนปิด
        (function init() { var b = box(); if (b) orig = { active: val(b, 'dot_active'), channel: val(b, 'dot_channel') }; })();

        function save(b) {
            // หน้าจะโหลดใหม่หลังบันทึก DOT — ถ้ามีอะไรในหน้าตรวจที่ยังไม่ได้กด Save จะหาย ถามก่อน
            if (window.__visitDirty && !confirm('มีข้อมูลในหน้าตรวจที่ยังไม่ได้กด Save\nบันทึก DOT แล้วหน้าจะโหลดใหม่ ข้อมูลที่ยังไม่ Save จะหาย\n\nบันทึก DOT ต่อเลยไหม?')) return;
            var f = document.createElement('form');
            f.method = 'POST'; f.action = b.dataset.url; f.style.display = 'none';
            var add = function (n, v) { var i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; f.appendChild(i); };
            add('_token', '{{ csrf_token() }}');
            add('dot_active', val(b, 'dot_active'));
            add('dot_channel', val(b, 'dot_channel'));
            if (val(b, 'dot_active') === '0') add('dot_off_reason', val(b, 'dot_off_reason'));
            document.body.appendChild(f);   // ต่อกับ body เท่านั้น — ห้ามอยู่ในฟอร์มหลัก
            f.submit();
        }

        document.addEventListener('change', function (e) {
            var b = e.target.closest && e.target.closest('.js-dot-status-form');
            if (!b) return;
            refresh(b);
            if (e.target.classList.contains('js-dot-status') && e.target.value === '0' && e.target.value !== orig.active) {
                b.querySelector('.js-dot-off-reason').focus();
            }
        });
        document.addEventListener('input', function (e) {
            var b = e.target.closest && e.target.closest('.js-dot-status-form');
            if (b) { refresh(b); return; }
            // จดว่าหน้าตรวจมีการแก้ที่ยังไม่ Save (ใช้เตือนก่อนบันทึก DOT)
            if (e.target.form && e.target.form.id === 'visit_edit_form') window.__visitDirty = true;
        });
        document.addEventListener('click', function (e) {
            var btn = e.target.closest && e.target.closest('.js-dot-status-save');
            if (btn) { e.preventDefault(); save(btn.closest('.js-dot-status-form')); }
        });
        // กด Enter ในช่อง DOT = บันทึก DOT ไม่ใช่กด Save หน้าตรวจ (ช่องอยู่ในฟอร์มหลัก Enter จะส่งฟอร์มหลักแทน)
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            var b = e.target.closest && e.target.closest('.js-dot-status-form');
            if (!b) return;
            e.preventDefault();
            if (!b.querySelector('.js-dot-status-save').classList.contains('d-none')) save(b);
        });
    })();
    </script>
@endif
