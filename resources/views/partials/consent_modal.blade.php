{{-- ============================================================
     ความยินยอม PDPA (2569-10-29) — แถบสถานะ + popup ให้พยาบาลสัมภาษณ์แล้วติ๊ก (paperless)
     ต้อง include "นอก" <form> ของหน้า (ปุ่มบันทึกยิง fetch เอง ไม่มี <form> ซ้อน)
     ตัวแปร: $cPatient (Patient)
     ============================================================ --}}
@php
    $__cLatest  = \App\Models\PatientConsent::latestFor((int) $cPatient->id);
    $__cStatus  = $__cLatest ? $__cLatest->status() : 'none';
    $__cHistory = \App\Models\PatientConsent::with('user:id,name')->where('patient_id', $cPatient->id)->orderByDesc('id')->limit(10)->get();
    $__cAge     = \App\Models\PatientConsent::ageOf($cPatient);
    $__cYouth   = $__cAge !== null && $__cAge < 25;   // เกณฑ์เยาวชนของคลินิก
    $__cMinor   = $__cAge !== null && $__cAge < 20;   // ผู้เยาว์ตามกฎหมาย
    $__cRefused = $__cLatest ? collect(\App\Models\PatientConsent::PURPOSES)->filter(fn ($p, $k) => $__cLatest->{$k} !== null && (int) $__cLatest->{$k} === 0)->pluck('label')->all() : [];
    $__cVal = fn ($k) => $__cLatest && $__cLatest->{$k} !== null ? (string) (int) $__cLatest->{$k} : '';
@endphp

<div id="pdpa-bar" class="d-flex flex-wrap align-items-center gap-2 mb-2 p-2 rounded"
     style="{{ $__cStatus === 'ok' ? 'background:#f0fdf4; border:1px solid #bbf7d0;' : ($__cStatus === 'refused' ? 'background:#fef2f2; border:1px solid #fecaca;' : 'background:#fffbeb; border:1px solid #fde68a;') }}">
    <span class="fw-bold" style="color:{{ $__cStatus === 'ok' ? '#166534' : ($__cStatus === 'refused' ? '#991b1b' : '#92400e') }};">
        <i class="fa fa-user-shield me-1"></i>
        @switch($__cStatus)
            @case('none') ยังไม่ได้ขอความยินยอม PDPA @break
            @case('outdated') ประกาศความเป็นส่วนตัวมีฉบับใหม่ — ต้องแจ้งคนไข้อีกครั้ง @break
            @case('refused') PDPA: คนไข้ไม่ยินยอม — {{ implode(' · ', $__cRefused) }} @break
            @default PDPA: ยินยอมแล้ว
        @endswitch
    </span>
    @if($__cLatest)
        <span class="small text-muted">บันทึกล่าสุด {{ $__cLatest->created_at->copy()->addYears(543)->format('d/m/Y') }}</span>
    @endif
    @if($__cYouth)
        <span class="badge" style="background:#dc2626; font-size:.85rem;">เยาวชน · อายุ {{ $__cAge }} ปี</span>
    @endif
    <button type="button" class="btn btn-sm {{ in_array($__cStatus, ['none', 'outdated']) ? 'btn-warning fw-bold' : 'btn-outline-secondary' }} ms-auto"
            data-bs-toggle="modal" data-bs-target="#pdpaModal">
        <i class="fa fa-clipboard-check me-1"></i>{{ in_array($__cStatus, ['none', 'outdated']) ? 'ขอความยินยอม' : 'ดู / แก้ความยินยอม' }}
    </button>
</div>

<div class="modal fade" id="pdpaModal" tabindex="-1" aria-labelledby="pdpaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" id="pdpa-box" data-url="{{ route('patient.consent.store', $cPatient->id) }}" data-minor="{{ $__cMinor ? 1 : 0 }}">
            <div class="modal-header" style="background:#1e3a8a; color:#fff;">
                <h5 class="modal-title" id="pdpaModalLabel" style="color:#fff !important;"><i class="fa fa-user-shield me-1"></i> ความยินยอม PDPA · HN {{ $cPatient->code }} {{ $cPatient->name }} {{ $cPatient->surname }}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if($__cYouth)
                    <div class="alert py-2 mb-3" style="background:#fef2f2; border:2px solid #dc2626; color:#7f1d1d;">
                        <b><i class="fa fa-triangle-exclamation me-1"></i>เยาวชน อายุ {{ $__cAge }} ปี</b>
                        @if($__cMinor)
                            — ผู้เยาว์ (ต่ำกว่า 20 ปี) ระบุด้านล่างว่าใครเป็นผู้ตอบ ถ้ามีหนังสือจากโรงเรียนให้แนบไฟล์
                            <div class="small mt-1">นโยบายกรม ข้อ 9: เรื่องที่ต้องขอความยินยอม (LINE · รูป · คู่สัมผัส · วิจัย) ต้องให้<b>ผู้ปกครอง</b>เป็นผู้ยินยอม —
                                ถ้าตอบเองหรือมีแค่หนังสือจากโรงเรียน ระบบบันทึกเป็น "ไม่ยินยอม" ทุกเรื่อง (ไม่ส่ง LINE · ไม่เก็บรูป · ไม่ติดต่อคู่สัมผัสแทน)
                                · การตรวจรักษาไม่ต้องใช้ความยินยอม ให้บริการได้ตามปกติ</div>
                        @else
                            — บรรลุนิติภาวะแล้ว ตอบเองได้
                        @endif
                    </div>
                @endif

                <div class="p-2 mb-3 rounded" style="background:#eff6ff; border:1px solid #bfdbfe;">
                    <label class="d-flex gap-2 align-items-start mb-0" style="cursor:pointer;">
                        <input type="checkbox" class="form-check-input mt-1" data-c="notice_informed" value="1">
                        <span><b>แจ้งประกาศความเป็นส่วนตัวแล้ว</b> (ฉบับ {{ \App\Models\PatientConsent::NOTICE_VERSION }})<br>
                            <span class="small text-muted">อธิบายให้คนไข้ฟัง หรือให้อ่านจากจอ —
                                <a href="{{ route('privacy.notice') }}" target="_blank">เปิดประกาศ (ไทย)</a> ·
                                <a href="{{ route('privacy.notice', ['lang' => 'en']) }}" target="_blank">English</a></span></span>
                    </label>
                </div>

                <p class="small text-muted mb-2">งานรักษา ตรวจ จ่ายยา และรายงานโรคตามกฎหมาย <b>ไม่ต้อง</b>ขอความยินยอม — ถามเฉพาะ 3 เรื่องนี้ (ตอบแยกกันได้):</p>
                @foreach(\App\Models\PatientConsent::PURPOSES as $__k => $__p)
                    <div class="border rounded p-2 mb-2">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <b style="min-width:14rem;">{{ $loop->iteration }}. {{ $__p['label'] }}</b>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="pdpa_{{ $__k }}" id="pdpa_{{ $__k }}_1" value="1" data-c="{{ $__k }}" {{ $__cVal($__k) === '1' ? 'checked' : '' }}>
                                <label class="btn btn-outline-success" for="pdpa_{{ $__k }}_1">ยินยอม</label>
                                <input type="radio" class="btn-check" name="pdpa_{{ $__k }}" id="pdpa_{{ $__k }}_0" value="0" data-c="{{ $__k }}" {{ $__cVal($__k) === '0' ? 'checked' : '' }}>
                                <label class="btn btn-outline-danger" for="pdpa_{{ $__k }}_0">ไม่ยินยอม</label>
                            </div>
                        </div>
                        <div class="small mt-1">{{ $__p['text'] }}</div>
                        <div class="small text-muted">ถ้าไม่ยินยอม: {{ $__p['no'] }}</div>
                        @if($__k === 'photo' && $cPatient->photo)
                            <div class="small fw-bold mt-1" style="color:#b91c1c;">มีรูปในระบบอยู่แล้ว — ถ้าเลือก "ไม่ยินยอม" ระบบจะลบรูปทันทีที่กดบันทึก</div>
                        @endif
                    </div>
                @endforeach

                <div class="border rounded p-2 mb-3">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <b style="min-width:14rem;">4. งานวิจัย <span class="fw-normal small text-muted">(เซ็นฟอร์มของโครงการแยก)</span></b>
                        <div class="btn-group btn-group-sm" role="group">
                            <input type="radio" class="btn-check" name="pdpa_research" id="pdpa_research_n" value="" data-c="research" {{ $__cVal('research') === '' ? 'checked' : '' }}>
                            <label class="btn btn-outline-secondary" for="pdpa_research_n">ไม่ได้เชิญ</label>
                            <input type="radio" class="btn-check" name="pdpa_research" id="pdpa_research_1" value="1" data-c="research" {{ $__cVal('research') === '1' ? 'checked' : '' }}>
                            <label class="btn btn-outline-success" for="pdpa_research_1">เข้าร่วม</label>
                            <input type="radio" class="btn-check" name="pdpa_research" id="pdpa_research_0" value="0" data-c="research" {{ $__cVal('research') === '0' ? 'checked' : '' }}>
                            <label class="btn btn-outline-danger" for="pdpa_research_0">ปฏิเสธ</label>
                        </div>
                        <input type="text" class="form-control form-control-sm" style="max-width:22rem;" data-c="research_project"
                               placeholder="ชื่อโครงการวิจัย" value="{{ $__cLatest->research_project ?? '' }}">
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-md-5">
                        <label class="form-label small mb-1">ผู้ตอบ</label>
                        <select class="form-select form-select-sm" data-c="signer">
                            @foreach(\App\Models\PatientConsent::SIGNERS as $__sk => $__sl)
                                <option value="{{ $__sk }}" {{ ($__cLatest->signer ?? 'self') === $__sk ? 'selected' : '' }}>{{ $__sl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-7 js-pdpa-signer-extra">
                        <label class="form-label small mb-1">ชื่อผู้ปกครอง / สถานศึกษา</label>
                        <input type="text" class="form-control form-control-sm" data-c="signer_name" value="{{ $__cLatest->signer_name ?? '' }}">
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small mb-1">แนบไฟล์ (ไม่บังคับ) เช่น หนังสือจากโรงเรียน · PDF/รูป ไม่เกิน 10 MB</label>
                        <input type="file" class="form-control form-control-sm" data-c="attachment" accept=".pdf,image/*">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small mb-1">หมายเหตุ</label>
                        <input type="text" class="form-control form-control-sm" data-c="note">
                    </div>
                </div>
                <div class="small text-muted">บันทึกในนาม <b>{{ Auth::user()->name ?? '-' }}</b> (เจ้าหน้าที่ผู้สัมภาษณ์) พร้อมวันเวลา — ใช้แทนลายเซ็นบนกระดาษ</div>

                @if($__cHistory->count())
                    <hr>
                    <div class="small fw-bold mb-1">ประวัติ (ล่าสุด 10 ครั้ง)</div>
                    <div style="overflow-x:auto;">
                        <table class="table table-sm small mb-0">
                            <thead class="table-light"><tr><th>วันที่</th><th>LINE</th><th>รูป</th><th>คู่สัมผัส</th><th>วิจัย</th><th>ผู้ตอบ</th><th>เจ้าหน้าที่</th><th></th></tr></thead>
                            <tbody>
                            @foreach($__cHistory as $__h)
                                @php $__yn = fn ($v) => $v === null ? '–' : ((int) $v ? '✔' : '✘'); @endphp
                                <tr>
                                    <td class="text-nowrap">{{ $__h->created_at->copy()->addYears(543)->format('d/m/Y H:i') }}</td>
                                    <td>{{ $__yn($__h->line) }}</td><td>{{ $__yn($__h->photo) }}</td><td>{{ $__yn($__h->contact) }}</td>
                                    <td>{{ $__yn($__h->research) }}@if($__h->research_project) <span class="text-muted">{{ $__h->research_project }}</span>@endif</td>
                                    <td>{{ \App\Models\PatientConsent::SIGNERS[$__h->signer] ?? $__h->signer }}@if($__h->signer_name) ({{ $__h->signer_name }})@endif</td>
                                    <td>{{ $__h->user->name ?? '-' }}</td>
                                    <td>@if($__h->attachment)<a href="{{ route('patient.consent.attachment', $__h->id) }}" target="_blank">ไฟล์แนบ</a>@endif
                                        @if($__h->note)<span class="text-muted" title="{{ $__h->note }}"><i class="fa fa-comment"></i></span>@endif</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <span class="me-auto small fw-bold js-pdpa-msg"></span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                <button type="button" class="btn btn-primary js-pdpa-save"><i class="fa fa-save me-1"></i>บันทึกความยินยอม</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.__pdpaInit) return; window.__pdpaInit = true;
    var box = document.getElementById('pdpa-box');
    if (!box) return;
    var q = function (k) { return box.querySelectorAll('[data-c="' + k + '"]'); };
    function syncSigner() {
        var s = q('signer')[0].value;
        box.querySelector('.js-pdpa-signer-extra').style.display = s === 'self' ? 'none' : '';
        // ผู้เยาว์ที่ผู้ปกครองไม่ได้ยินยอม: ล็อกเป็น "ไม่ยินยอม" ให้เห็นตรงกับที่เซิร์ฟเวอร์จะบันทึก
        var lock = box.dataset.minor === '1' && s !== 'guardian';
        ['line', 'photo', 'contact', 'research'].forEach(function (k) {
            Array.prototype.forEach.call(q(k), function (e) {
                if (e.value === '1') { e.disabled = lock; if (lock && e.checked) e.checked = false; }
                if (lock && e.value === '0' && (k !== 'research' || !Array.prototype.some.call(q(k), function (x) { return x.value === '' && x.checked; }))) e.checked = true;
            });
        });
    }
    q('signer')[0].addEventListener('change', syncSigner); syncSigner();

    box.querySelector('.js-pdpa-save').addEventListener('click', function () {
        var btn = this, msg = box.querySelector('.js-pdpa-msg');
        var fd = new FormData();
        fd.append('_token', '{{ csrf_token() }}');
        fd.append('notice_informed', q('notice_informed')[0].checked ? '1' : '0');
        ['line', 'photo', 'contact', 'research'].forEach(function (k) {
            var r = Array.prototype.find.call(q(k), function (e) { return e.checked; });
            fd.append(k, r ? r.value : '');
        });
        ['research_project', 'signer', 'signer_name', 'note'].forEach(function (k) { fd.append(k, q(k)[0].value); });
        var f = q('attachment')[0].files[0]; if (f) fd.append('attachment', f);

        var ph = Array.prototype.find.call(q('photo'), function (e) { return e.checked; });
        if (ph && ph.value === '0' && {{ $cPatient->photo ? 'true' : 'false' }} && !confirm('คนไข้ไม่ยินยอมเก็บรูป — ระบบจะลบรูปถ่ายในเวชระเบียนทันที ยืนยัน?')) return;

        btn.disabled = true; msg.style.color = '#334155'; msg.textContent = 'กำลังบันทึก...';
        fetch(box.dataset.url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.j.ok) {
                    var m = res.j.message || 'บันทึกไม่สำเร็จ';
                    if (res.j.errors) m = Object.values(res.j.errors).flat().join(' · ');
                    throw new Error(m);
                }
                msg.style.color = '#166534'; msg.textContent = res.j.message;
                // หน้าตรวจ: ห้ามโหลดใหม่ (ช่องที่กรอกค้างจะหาย) — เปลี่ยนแค่แถบสถานะแล้วปิด popup
                // หน้าอื่นโหลดใหม่ให้ประวัติ/รูปตรงกับที่บันทึก
                if (document.getElementById('visit_edit_form')) {
                    var bar = document.getElementById('pdpa-bar');
                    if (bar) {
                        var bad = res.j.status === 'refused';
                        bar.style.background = bad ? '#fef2f2' : '#f0fdf4';
                        bar.style.borderColor = bad ? '#fecaca' : '#bbf7d0';
                        var t = bar.querySelector('span.fw-bold');
                        t.style.color = bad ? '#991b1b' : '#166534';
                        t.innerHTML = '<i class="fa fa-user-shield me-1"></i>' + (bad ? 'PDPA: บันทึกแล้ว — คนไข้ไม่ยินยอมบางเรื่อง' : 'PDPA: ยินยอมแล้ว') + ' (เพิ่งบันทึก)';
                    }
                    setTimeout(function () {
                        // ไม่มี window.bootstrap (รวมอยู่ใน app.js) — ปิดด้วยปุ่ม dismiss แทน
                        var x = box.querySelector('[data-bs-dismiss="modal"]'); if (x) x.click();
                        btn.disabled = false;
                    }, 900);
                } else {
                    setTimeout(function () { location.reload(); }, 900);
                }
            })
            .catch(function (e) { msg.style.color = '#b91c1c'; msg.textContent = e.message || 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้'; btn.disabled = false; });
    });
})();
</script>
