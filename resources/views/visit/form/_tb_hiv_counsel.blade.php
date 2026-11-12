{{-- ให้คำปรึกษา HIV (Pre-test / Post-test) ของคลินิกวัณโรค — ทะเบียน RIHIS-VCT นับจากตาราง diagnoses (โรค 13/14)
     ย้ายจากการ์ดวินิจฉัยมาต่อบันทึกทางการพยาบาล (คลินิกขอ 2569-10-14) · มีการสั่งตรวจ HIV ใน visit นี้แต่ยังไม่ติ๊ก = เตือนสีเหลือง
     ไม่มี <form> (อยู่ในฟอร์มหลัก) · บันทึกทันทีด้วย fetch · ตัวแปร: $visit --}}
@php
    $__dgAll = $visit->diagnosi();
    $__dgHas = fn ($d) => $__dgAll->contains(fn ($x) => (int) $x->disease === $d);
    $__hivOrdered = collect($visit->labSub())->contains(fn ($ls) => (int) $ls->method === 6);
    $__hivWarn = $__hivOrdered && ! $__dgHas(13) && ! $__dgHas(14);
@endphp
    <div class="d-flex flex-wrap align-items-center gap-3 mb-2 p-2 rounded" style="{{ $__hivWarn ? 'background:#fef9c3; border:2px solid #facc15;' : 'background:#f5f3ff; border:1px solid #ddd6fe;' }}" data-no-dirty
         id="tb-hiv-counsel" data-url="{{ route('diagnosis.hivCounsel', $visit->id) }}">
        <span class="fw-bold">ให้คำปรึกษา HIV:</span>
        <label class="form-check mb-0"><input type="checkbox" class="form-check-input js-hc" value="13" {{ $__dgHas(13) ? 'checked' : '' }}> <span class="form-check-label">Pre-test (ตรวจเลือด HIV)</span></label>
        <label class="form-check mb-0"><input type="checkbox" class="form-check-input js-hc" value="14" {{ $__dgHas(14) ? 'checked' : '' }}> <span class="form-check-label">Post-test (มาฟังผลเลือด HIV)</span></label>
        <span class="small text-muted js-hc-msg">ติ๊กแล้วบันทึกทันที</span>
        @if($__hivWarn)<span class="small fw-bold" style="color:#a16207;"><i class="fa fa-triangle-exclamation me-1"></i>visit นี้สั่งตรวจ HIV แล้ว — ติ๊ก Pre-test ด้วย</span>@endif
    </div>
    <script>
    (function () {
        if (window.__tbHcInit) return;
        window.__tbHcInit = true;
        document.addEventListener('change', function (e) {
            if (!e.target.classList || !e.target.classList.contains('js-hc')) return;
            var box = document.getElementById('tb-hiv-counsel'), cb = e.target, msg = box.querySelector('.js-hc-msg');
            var body = new URLSearchParams();
            body.append('_token', @json(csrf_token())); body.append('disease', cb.value); body.append('on', cb.checked ? '1' : '0');
            cb.disabled = true; msg.textContent = 'กำลังบันทึก…'; msg.className = 'small text-muted js-hc-msg';
            fetch(box.dataset.url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().then(function (j) { if (!r.ok || !j.ok) throw j; return j; }); })
                .then(function () { msg.textContent = '✓ บันทึกแล้ว'; msg.className = 'small text-success js-hc-msg'; })
                .catch(function () { cb.checked = !cb.checked; msg.textContent = 'บันทึกไม่สำเร็จ ลองใหม่'; msg.className = 'small text-danger js-hc-msg'; })
                .then(function () { cb.disabled = false; });
        });
    })();
    </script>
