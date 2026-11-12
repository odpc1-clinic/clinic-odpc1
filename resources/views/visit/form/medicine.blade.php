<div class="row">

<div class="col-md-12">
    <div class="card">
        {{-- หัวการ์ดเดิมเป็นตัวเล็กสีเทา หมอเลื่อนผ่านแล้วไม่เห็นว่าตรงนี้คือสั่งยา (คลินิกขอ 2569-09-30) --}}
        <div class="card-header d-flex align-items-center gap-2" style="background:#0f766e; color:#fff; font-size:1.2rem; font-weight:700;">
            <i class="fas fa-prescription-bottle-medical"></i> สั่งยา / Medicine
            <span class="small fw-normal opacity-75" style="font-size:.8rem;">Visit #{{ $visit->id }}</span>
            {{-- ตารางว่างกับมีของหน้าตาคล้ายกัน พยาบาลมองข้าม — บอกจำนวนที่หัวการ์ดเลย (2569-10-28) --}}
            @php $__medCount = \App\Models\VisitMedicine::where('visit_id', $visit->id)->count(); @endphp
            @if($__medCount)
                <span class="badge ms-auto" style="background:#fde047; color:#422006; font-size:.9rem;">สั่งแล้ว {{ $__medCount }} รายการ</span>
            @else
                <span class="badge ms-auto bg-light text-dark fw-normal" style="font-size:.8rem;">ยังไม่มียา</span>
            @endif
        </div>
        <div class="card-body">
            <?php $lab =$visit->lab() ?>



                <a href="{{ route('visit-medicine.add',['visit' => $visit->id,'page'=>request('page', 1)]) }}" class="btn btn-success btn-sm" title="สั่งยา (เพิ่มยา)">
                    <i class="fa fa-plus" aria-hidden="true"></i> Add New
                </a>

                {{-- ดึงยาเดิมของคนไข้รายนี้มาใช้ ไม่ต้องเลือกยาใหม่ทุกครั้ง --}}
                <button type="button" class="btn btn-outline-primary btn-sm js-mh-open"
                        data-bs-toggle="modal" data-bs-target="#medHistoryModal"
                        data-url="{{ route('visit-medicine.history', ['visit' => $visit->id, 'page' => request('page', 1)]) }}"
                        title="ดูยาที่เคยสั่งให้คนไข้รายนี้ แล้วดึงมาใช้">
                    <i class="fas fa-clock-rotate-left"></i> ยาเดิม
                </button>

                {{-- ใบสรุปรายการยากลับบ้าน/ส่งต่อ (คลินิก TB ขอ 2569-10-06 ตามแบบ รพ.นครพิงค์) — ลิงก์ ไม่ใช่ปุ่ม submit --}}
                <a href="{{ route('visit.drugSummary', $visit->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm"
                   title="พิมพ์ใบสรุปรายการยากลับบ้าน หรือส่งต่อสถานพยาบาลใกล้บ้าน">
                    <i class="fa fa-print"></i> ใบสรุปรายการยา
                </a>

                {{-- TB: ดึงยาวัณโรคทั้งชุด (สูตรปัจจุบัน × น้ำหนัก × จำนวนวัน) ลงรายการสั่งยานี้ในคลิกเดียว (คลินิกขอ 2569-10-08)
                     หมอจบที่หน้าตรวจ ไม่ต้องไปบัตร TB 01 — ใช้ค่าที่กล่อง "สั่งยาวัณโรคครั้งนี้" คำนวณไว้แล้ว แล้วกดบันทึกของกล่องให้
                     (กล่องเดิมยังอยู่ด้านล่าง ไว้ปรับสูตร/ขนาด) · ตัวจัดการอยู่ท้ายไฟล์นี้ (หน้าโหลดเต็ม) --}}
                @if((int) ($visit->clinic ?? 1) === 2)
                    <button type="button" class="btn btn-sm fw-bold text-white" id="tb-quick-order" style="background:#b45309;"
                            title="ดึงยาวัณโรคตามสูตรปัจจุบันและน้ำหนัก ลงรายการสั่งยาด้านล่าง">
                        <i class="fa fa-prescription"></i> ดึงชุดยาวัณโรค
                    </button>
                @endif

                {{-- ห้องเจาะเลือด/ฉีดยา: จับเวลาสังเกตอาการ 30 นาที หลังฉีดยา (เช่น Benzathine)
                     ใช้ชิ้นส่วนกลางเดียวกับตารางห้องและโซนวัคซีน TMC --}}
                @php $__medPt = \App\Models\Visit::getPatient($visit); @endphp
                <span class="d-inline-block align-middle ms-1">
                    @include('partials.obs_button', [
                        'obsPatientId' => $visit->patient_id,
                        'obsVisitId'   => $visit->id,
                        'obsName'      => trim(($__medPt->name ?? '').' '.($__medPt->surname ?? '')) ?: ('HN '.($__medPt->code ?? '')),
                    ])
                </span>

                @php
                    // TB: ข้อมูลกล่องสั่งยาคิดครั้งเดียว ใช้ทั้งตัดสินว่าซ่อนตารางเดิมไหม และวาดกล่อง (_tb_order_box)
                    $__box = (int) ($visit->clinic ?? 1) === 2 ? app(\App\Http\Controllers\TbController::class)->visitOrderBoxData($visit) : null;
                    $__unifiedMed = $__box && ! empty($__box['embed']['unified']);
                @endphp
                {{-- id นี้ให้กล่องสั่งยาวัณโรคเอารายการยาใหม่มาแทนที่หลังกดบันทึก (ไม่ต้องโหลดหน้า)
                     ตารางเดียว (2569-10-14): ซ่อนไว้ ใช้ตารางในกล่องแทน --}}
                <div id="visit-med-table" class="{{ $__unifiedMed ? 'd-none' : '' }}">
                @include ('visit-medicine.table', ['visitMedicine' => $visit->visitMedicine(), 'editable'=>1])
                </div>

                {{-- TB: กล่องสั่งยาวัณโรคตัวเดียวกับบัตร TB 01 — ลงรายการยาด้านบน (+ บัตร ถ้ามี) 2569-10-05
                     เดิมเป็นตารางยาวัณโรคแบบดูอย่างเดียว (_tb_drugs) ยาไม่เข้ารายการยา การเงิน/ห้องยาไม่เห็น --}}
                @if((int) ($visit->clinic ?? 1) === 2)
                    @include('visit.form._tb_order_box', ['__box' => $__box])
                    {{-- สั่ง DOT / Off DOT ใต้สั่งยาทุกครั้ง (คลินิกขอ 2569-10-14 — เดิมอยู่ในกล่อง F/U ด้านบน) --}}
                    @if(! empty($__box['embed']['registered']))
                        <div class="mt-3 p-2 rounded" style="background:#fff7ed; border:1px solid #fed7aa;" data-no-dirty>
                            @include('tb._dot_toggle', ['reg' => $__box['reg'], 'offDefault' => $visit->getRawOriginal('date')])
                        </div>
                    @endif
                @endif
         


        </div>
    </div>
</div>
</div>
{{-- Popup ยาเดิม: โหลดเนื้อหาด้วย AJAX ตอนกด, ปุ่มดึงยาเป็นฟอร์ม POST ธรรมดา (โหลดหน้าใหม่กลับมาที่แท็บยา) --}}
<div class="modal fade" id="medHistoryModal" tabindex="-1" aria-labelledby="medHistoryLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="medHistoryLabel"><i class="fas fa-pills me-1"></i> ยาเดิมของคนไข้</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="medHistoryBody">
                <div class="text-center text-muted py-4">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <div class="mt-2">กำลังโหลด...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.__medHistoryInit) return;
    window.__medHistoryInit = true;

    // ปุ่มจับเวลาสังเกตอาการใช้ handler กลางใน partials/obs_timer แล้ว ไม่ต้องผูกซ้ำที่นี่

    var ctrl = null;

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-mh-open');
        if (!btn) return;

        var body = document.getElementById('medHistoryBody');
        if (!body) return;
        body.innerHTML = '<div class="text-center text-muted py-4">'
            + '<i class="fas fa-spinner fa-spin fa-2x"></i><div class="mt-2">กำลังโหลด...</div></div>';

        if (ctrl) ctrl.abort();
        ctrl = new AbortController();

        fetch(btn.dataset.url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: ctrl.signal
        })
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
        .then(function (html) { body.innerHTML = html; })
        .catch(function (err) {
            if (err.name === 'AbortError') return;
            body.innerHTML = '<div class="alert alert-danger mb-0">'
                + '<i class="fas fa-triangle-exclamation me-1"></i> โหลดยาเดิมไม่สำเร็จ กรุณาลองใหม่</div>';
        });
    });
})();
</script>

@if((int) ($visit->clinic ?? 1) === 2)
<script>
(function () {
    if (window.__tbQuickOrder) return;
    window.__tbQuickOrder = true;
    document.addEventListener('click', function (e) {
        if (!e.target.closest || !e.target.closest('#tb-quick-order')) return;
        var box = document.getElementById('tb-order'), save = document.getElementById('tb-ord-save');
        if (!box || !save) { alert('ไม่พบกล่องสั่งยาวัณโรคในหน้านี้'); return; }
        if (box.dataset.locked === '1') {
            alert('สั่งยาจากกล่องนี้ไม่ได้ — ' + ((document.querySelector('#tb-order .alert') || {}).textContent || 'visit นี้ล็อกการสั่งยาแล้ว').trim());
            return;
        }
        var reg = document.getElementById('tb-ord-regimen');
        var rows = Array.prototype.filter.call(box.querySelectorAll('tr.js-ord-row'), function (tr) { return !tr.classList.contains('d-none'); });
        if (!rows.length) {
            alert('ยังไม่มีสูตรยาในกล่องสั่งยาวัณโรค — เลือกสูตรยาในกล่องด้านล่างก่อน');
            var w = box.closest('.js-tb-order-wrap'); if (w) { w.classList.remove('d-none'); }
            box.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }
        var lines = rows.map(function (tr) {
            var n = (tr.querySelector('.fw-bold') || {}).textContent || tr.dataset.key;
            var t = (tr.querySelector('.js-ord-total') || {}).textContent || '';
            return '• ' + n.trim() + (t.trim() ? '  — จ่าย ' + t.trim() : '');
        });
        var NL = String.fromCharCode(10);
        var msg = 'ดึงยาวัณโรคทั้งชุดลงรายการสั่งยา?' + NL + NL
            + 'สูตร: ' + (reg && reg.selectedOptions[0] ? reg.selectedOptions[0].textContent.trim() : '-')
            + '  ·  น้ำหนัก ' + ((document.getElementById('tb-ord-weight') || {}).value || '-') + ' กก.'
            + '  ·  ' + ((document.getElementById('tb-ord-days') || {}).value || '-') + ' วัน' + NL + NL
            + lines.join(NL) + NL + NL
            + 'ถ้าต้องปรับขนาด/สูตร ให้แก้ในกล่อง "สั่งยาวัณโรคครั้งนี้" ด้านล่างแล้วกดบันทึกของกล่อง';
        if (!confirm(msg)) return;
        save.click();
        var t = document.getElementById('visit-med-table');
        if (t) setTimeout(function () { t.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 1200);
    });
})();
</script>
@endif
