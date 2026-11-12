{{-- สมุดเหลือง "ครั้งนี้" (คลินิกขอ 2569-11-04 — พยาบาลงงขั้นตอนออกเล่มแบบเดิม)
     เลือก 1 ข้อว่าครั้งนี้ทำอะไรกับสมุด → กรอกเฉพาะช่องของข้อนั้น → ระบบอัปเดตเลขเล่มปัจจุบัน + ประวัติให้เอง
     อยู่ในฟอร์มหลักของหน้าตรวจ: ช่องทั้งหมดไม่มี name (ไม่ไปกับปุ่ม Save หลัก) บันทึกด้วย fetch ที่ปุ่มของกล่องนี้ --}}
@php
    $__ybAct = (string) ($visit->tmc_book_action ?? '');
    $__ybSrc = (string) ($visit->tmc_book_old_src ?? '');
    // สมุดหายที่บันทึกไว้แล้วแบบไม่มีเลขเดิม = เลือก "หาเลขเดิมไม่ได้" ไว้
    $__ybLostUnknown = $__ybAct === 'lost' && trim((string) $visit->tmc_book_old_no) === '';
@endphp
<div class="yellow-book-box p-2 mb-2" id="yb2-box" data-no-dirty
     data-url="{{ route('tmc.yellowBook.save', $visit->id) }}" data-token="{{ csrf_token() }}"
     data-del-url="{{ route('tmc.yellowBook.historyDelete', $visit->id) }}"
     data-action="{{ $__ybAct }}" data-src="{{ $__ybSrc }}">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
        <i class="fa-solid fa-book-medical fa-2x text-warning"></i>
        <div>
            <div class="small text-muted">เลขสมุดเหลืองปัจจุบันของคนไข้ · 1 คน/เล่ม</div>
            <div class="fw-bold" style="font-size:1.2rem" id="yb2-current">{{ $__pt->yellow_book_no ?: '— ยังไม่มี —' }}</div>
        </div>
        <div class="small text-muted flex-grow-1" style="min-width:220px">
            <div>ประวัติเล่มเก่า <span class="text-muted">· ลงเกิน/ผิด กด ✕ ลบทีละรายการ</span></div>
            {{-- สคริปต์ด้านล่างวาดรายการ + ปุ่มลบจาก data-hist (ข้อความคั่น " | ") --}}
            <div id="yb2-hist" class="text-dark d-flex flex-wrap gap-1 mt-1" data-hist="{{ $__pt->yellow_book_history }}">—</div>
        </div>
    </div>

    {{-- บันทึกสมุดเหลืองแต่ละครั้งที่มา พร้อมวันที่ (คลินิกขอ 2569-11-05 — คนไข้มาหลายรอบ ไม่รู้ว่าลงไว้รอบไหน) --}}
    @php
        $__ybShort = ['old' => 'ใช้เล่มเดิม', 'new' => 'ออกเล่มใหม่', 'replace' => 'เสีย/ชำรุด — ออกใหม่ทดแทน',
                      'lost' => 'สมุดหาย — ออกใหม่ทดแทน', 'spoiled' => 'สมุดเสีย — ออกเล่มใหม่', 'none' => 'ไม่ใช้สมุด'];
        $__ybLog = \App\Models\Visit::where('patient_id', $visit->patient_id)->where('clinic', 3)->whereNotNull('tmc_book_action')
            ->orderBy('date')->orderBy('id')->get(array_merge(['id', 'date', 'tmc_book_action', 'tmc_book_no', 'tmc_book_old_no'],
                \App\Models\Visit::bookSpoiledReady() ? ['tmc_book_spoiled_nos'] : []));
    @endphp
    <div class="small mb-2">
        <span class="text-muted">ลงสมุดเหลืองไว้แล้ว:</span>
        @forelse($__ybLog as $__l)
            @php $__isThis = (int) $__l->id === (int) $visit->id; @endphp
            <span class="d-inline-block border rounded px-2 me-1 mb-1 {{ $__isThis ? 'fw-bold' : 'bg-white' }}"
                  style="{{ $__isThis ? 'background:#fff3cd;border-color:#ffc107 !important;' : '' }}">
                @if($__isThis)
                    ครั้งนี้
                @else
                    <a href="{{ route('visit.edit', $__l->id) }}" target="_blank" title="เปิด visit นั้น">{{ \App\Support\ThaiDate::show($__l->getRawOriginal('date')) }}</a>
                @endif
                @if($__isThis) ({{ \App\Support\ThaiDate::show($__l->getRawOriginal('date')) }}) @endif
                · {{ $__ybShort[$__l->tmc_book_action] ?? $__l->tmc_book_action }}
                @if($__l->tmc_book_no) · <b>{{ $__l->tmc_book_no }}</b>@endif
                @php $__spN = count(array_filter(explode(',', (string) ($__l->tmc_book_spoiled_nos ?? '')))); @endphp
                @if($__spN) · <span class="text-danger" title="{{ str_replace(',', ', ', $__l->tmc_book_spoiled_nos) }}">เขียนเสีย {{ $__spN }} เล่ม</span>@endif
            </span>
        @empty
            <span class="text-muted">— ยังไม่เคยลง —</span>
        @endforelse
    </div>

    <div class="fw-bold mb-1">สมุดเหลืองครั้งนี้</div>
    <div class="d-flex flex-wrap gap-2 mb-2" id="yb2-actions" role="group" aria-label="สมุดเหลืองครั้งนี้">
        @foreach(\App\Http\Controllers\TmcYellowBookController::ACTIONS as $__k => $__l)
            {{-- ปุ่ม "สมุดเสีย" เอาออก ให้ใช้ "ออกทดแทน — ชำรุด/เสีย" แทน (คลินิกขอ 2569-11-04)
                 ยังโชว์เฉพาะ visit ที่เคยบันทึกเป็นสมุดเสียไว้แล้ว ให้เห็นว่าเลือกอะไรและเปลี่ยนได้ --}}
            @continue($__k === 'spoiled' && $__ybAct !== 'spoiled')
            <button type="button" class="btn btn-sm {{ $__ybAct === $__k ? 'btn-warning' : 'btn-outline-secondary' }} js-yb2-act"
                    data-act="{{ $__k }}" aria-pressed="{{ $__ybAct === $__k ? 'true' : 'false' }}">{{ $__l }}</button>
        @endforeach
    </div>

    <div class="row g-2 align-items-end {{ $__ybAct && $__ybAct !== 'none' ? '' : 'd-none' }}" id="yb2-fields">
        {{-- เล่มเดิมออกโดยใคร — ใช้เล่มเดิม / ทดแทน --}}
        <div class="col-md-3 js-yb2-f" data-for="old replace lost">
            <label class="form-label small mb-1" id="yb2-src-label">เล่มนี้ออกโดย</label>
            <select class="form-select form-select-sm" id="yb2-src">
                <option value="">— เลือก —</option>
                <option value="ours" {{ $__ybSrc === 'ours' ? 'selected' : '' }}>คลินิกเรา (สคร.1)</option>
                <option value="other" {{ $__ybSrc === 'other' ? 'selected' : '' }}>ที่อื่น</option>
            </select>
        </div>
        {{-- สมุดหาย: คนไข้มีเลขเล่มเดิม (จำได้/มีรูป) หรือหาเลขไม่ได้ (คลินิกขอ 2569-11-04) --}}
        <div class="col-md-3 js-yb2-f" data-for="lost">
            <div class="form-label small mb-1">เลขเล่มเดิม</div>
            <div class="form-check form-check-inline mb-0">
                <input class="form-check-input" type="radio" name="yb2_lost_known_ui" id="yb2-lost-known" value="1" form="yb2-noform" {{ $__ybLostUnknown ? '' : 'checked' }}>
                <label class="form-check-label small" for="yb2-lost-known">มีเลข</label>
            </div>
            <div class="form-check form-check-inline mb-0">
                <input class="form-check-input" type="radio" name="yb2_lost_known_ui" id="yb2-lost-unknown" value="0" form="yb2-noform" {{ $__ybLostUnknown ? 'checked' : '' }}>
                <label class="form-check-label small" for="yb2-lost-unknown">หาเลขเดิมไม่ได้</label>
            </div>
        </div>
        <div class="col-md-3 js-yb2-f" data-for="spoiled lost" id="yb2-old-wrap">
            <label class="form-label small mb-1" for="yb2-old-no" id="yb2-old-label">เลขเล่มเดิม (ถ้ามี)</label>
            <input type="text" class="form-control form-control-sm" id="yb2-old-no" value="{{ $visit->tmc_book_old_no }}">
        </div>
        {{-- วันที่ออก/ใช้เล่ม — ค่าตั้งต้น = วันที่ของ visit นี้ (ลงย้อนหลังได้ คลินิกขอ 2569-11-04) · ใช้เขียนในประวัติเล่มเก่า --}}
        <div class="col-md-2 js-yb2-f" data-for="old new replace lost spoiled">
            <label class="form-label small mb-1" for="yb2-date">วันที่</label>
            <input type="text" class="form-control form-control-sm" id="yb2-date" autocomplete="off" placeholder="วว/ดด/ปปปป"
                   value="{{ \App\Utillity::db2thDate($visit->getRawOriginal('date')) }}">
        </div>
        <div class="col-md-3 js-yb2-f" data-for="old new replace lost spoiled">
            <label class="form-label small mb-1 fw-bold" for="yb2-no" id="yb2-no-label">เลขเล่ม</label>
            <input type="text" class="form-control form-control-sm fw-bold" id="yb2-no"
                   value="{{ $visit->tmc_book_no ?: ($__ybAct === 'old' || $__ybAct === '' ? $__pt->yellow_book_no : '') }}">
        </div>
        {{-- เลขเล่มที่เสีย/ชำรุด — เล่มละช่อง กด "+ เพิ่มอีกเล่ม" ได้ (คลินิกขอ 2569-11-06)
             ใช้เฉพาะปุ่ม "สมุดเสีย/ชำรุด — ออกใหม่ทดแทน" · ออกเล่มใหม่/สมุดหาย ไม่มีช่องนี้
             เล่มแรกเก็บที่ tmc_book_old_no เดิม เล่มถัดไปเก็บที่ tmc_book_spoiled_nos · ทุกเล่มเข้ารายการส่งทำลาย
             ช่องเดียวพิมพ์ 2 เลขเว้นวรรค (รุ่นก่อน) แยกเป็นเล่มละช่องให้ตอนเปิดหน้า --}}
        @php
            $__spList = $__ybAct === 'replace'
                ? array_values(array_filter(preg_split('/[\s,]+/u', trim($visit->tmc_book_old_no . ' ' . str_replace(',', ' ', (string) ($visit->tmc_book_spoiled_nos ?? ''))))))
                : [];
            $__spList = $__spList ?: [''];
        @endphp
        <div class="col-12 js-yb2-f" data-for="replace">
            <div class="form-label small mb-1">เลขเล่มที่เสีย/ชำรุด <span class="text-muted">(เล่มละช่อง · เสียหลายเล่ม กด "+ เพิ่มอีกเล่ม")</span></div>
            <div class="d-flex flex-wrap align-items-center gap-2" id="yb2-spoiled-list">
                @foreach($__spList as $__i => $__sp)
                    <div class="input-group input-group-sm js-yb2-sp-row" style="width:14rem;">
                        <span class="input-group-text js-yb2-sp-no">เล่มที่ {{ $__i + 1 }}</span>
                        <input type="text" class="form-control js-yb2-sp" autocomplete="off" value="{{ $__sp }}" placeholder="เช่น 2411-019579">
                        <button type="button" class="btn btn-outline-danger js-yb2-sp-del" title="ลบช่องนี้">✕</button>
                    </div>
                @endforeach
                <button type="button" class="btn btn-sm btn-outline-secondary" id="yb2-sp-add"><i class="fa fa-plus me-1"></i> เพิ่มอีกเล่ม</button>
            </div>
        </div>
    </div>

    {{-- เล่มจากที่อื่นมักมีวัคซีนเก่าเขียนอยู่ — เตือนให้ลงเป็นประวัติเดิม (คลินิกขอ 2569-11-04) --}}
    <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small d-none" id="yb2-other-warn">
        <i class="fa fa-triangle-exclamation me-1"></i>
        เล่มจากที่อื่น: ถ้าในเล่มมีวัคซีนที่ฉีดมาก่อน ให้ลงเป็น <b>"ประวัติเดิม/ที่อื่น"</b> ด้วย
        <a href="#tmcVaccine" class="ms-1" id="yb2-goto-vac">ไปที่บันทึกการฉีดวัคซีน</a>
    </div>

    <div class="d-flex align-items-center gap-2 mt-2">
        <button type="button" class="btn btn-warning btn-sm" id="yb2-save"><i class="fa fa-save me-1"></i> บันทึกสมุดเหลือง</button>
        <span class="small" id="yb2-status">
            @if($__ybAct)<span class="text-success">✓ บันทึกไว้แล้ว: {{ \App\Http\Controllers\TmcYellowBookController::ACTIONS[$__ybAct] ?? $__ybAct }}</span>@endif
        </span>
    </div>
</div>

<script>
(function () {
    var box = document.getElementById('yb2-box');
    if (!box || box.dataset.init) return;
    box.dataset.init = '1';
    var act = box.dataset.action || '';
    var $ = function (id) { return document.getElementById(id); };
    var LABEL_NO = { old: 'เลขเล่มที่ใช้', 'new': 'เลขเล่มใหม่', replace: 'เลขเล่มใหม่ที่ออกให้', lost: 'เลขเล่มใหม่ที่ออกให้', spoiled: 'เลขเล่มใหม่ที่ออกให้' };
    var lostKnown = function () { return !$('yb2-lost-unknown').checked; };

    function paint() {
        box.querySelectorAll('.js-yb2-act').forEach(function (b) {
            var on = b.dataset.act === act;
            b.classList.toggle('btn-warning', on); b.classList.toggle('btn-outline-secondary', !on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        $('yb2-fields').classList.toggle('d-none', !act || act === 'none');
        var other = $('yb2-src').value === 'other';
        box.querySelectorAll('.js-yb2-f').forEach(function (f) {
            f.classList.toggle('d-none', (' ' + f.dataset.for + ' ').indexOf(' ' + act + ' ') === -1);
        });
        // หายแบบหาเลขไม่ได้ ซ่อนช่องเลขเล่มเดิม
        if (act === 'lost' && !lostKnown()) $('yb2-old-wrap').classList.add('d-none');
        $('yb2-no-label').textContent = LABEL_NO[act] || 'เลขเล่ม';
        $('yb2-src-label').textContent = act === 'replace' || act === 'lost' ? 'เล่มเดิมออกโดย' : 'เล่มนี้ออกโดย';
        $('yb2-old-label').textContent = { spoiled: 'เลขเล่มที่เสีย', replace: 'เลขเล่มที่เสีย/ชำรุด', lost: 'เลขเล่มที่หาย' }[act] || 'เลขเล่มเดิม';
        $('yb2-other-warn').classList.toggle('d-none', !(other && (act === 'old' || act === 'replace')));
    }

    box.addEventListener('click', function (e) {
        var b = e.target.closest('.js-yb2-act');
        if (!b) return;
        act = b.dataset.act;
        // ออกใหม่/ทดแทน/เสีย = ต้องพิมพ์เลขเล่มใหม่ — ไม่เติมเลขเล่มเดิมไว้ให้ กันลืมแก้
        if (act !== 'old' && $('yb2-no').value === ($('yb2-current').textContent || '').trim()) $('yb2-no').value = '';
        if (act === 'old' && !$('yb2-no').value) {
            var cur = ($('yb2-current').textContent || '').trim();
            if (cur && cur.indexOf('ยังไม่มี') === -1) $('yb2-no').value = cur;
        }
        paint();
    });
    $('yb2-src').addEventListener('change', paint);
    // เลขสมุดเหลืองรูปแบบ 1900-0000 — พิมพ์ตัวเลขครบ 4 ตัวแล้วเติม "-" ให้ (คลินิกขอ 2569-11-04)
    // เฉพาะตอนพิมพ์เพิ่ม: ลบถอยหลังผ่านขีดได้ปกติ · วางเลขติดกันมา (19000000) ก็แทรกขีดให้
    // มีตัวอักษร/ขีดอยู่แล้ว = รูปแบบของที่อื่น ไม่ยุ่ง
    // ช่องเล่มเขียนเสีย: เล่มละช่อง · เพิ่ม/ลบช่องได้ · เติมขีดแบบเดียวกับช่องเลขเล่ม
    function bookDash(el, e) {
        if (e.inputType && e.inputType.indexOf('delete') === 0) return;
        var v = el.value;
        if (/-{2,}/.test(v)) { el.value = v.replace(/-{2,}/g, '-'); return; }
        if (/^\d{4}$/.test(v)) { el.value = v + '-'; }
        else if (/^\d{5,}$/.test(v)) { el.value = v.slice(0, 4) + '-' + v.slice(4); }
    }
    var spList = $('yb2-spoiled-list');
    function spRenumber() {
        spList.querySelectorAll('.js-yb2-sp-no').forEach(function (n, i) { n.textContent = 'เล่มที่ ' + (i + 1); });
    }
    if (spList) {
        spList.addEventListener('input', function (e) { if (e.target.classList.contains('js-yb2-sp')) bookDash(e.target, e); });
        spList.addEventListener('click', function (e) {
            var del = e.target.closest('.js-yb2-sp-del');
            if (!del) return;
            var rows = spList.querySelectorAll('.js-yb2-sp-row');
            if (rows.length > 1) { del.closest('.js-yb2-sp-row').remove(); } else { rows[0].querySelector('.js-yb2-sp').value = ''; }
            spRenumber();
        });
        $('yb2-sp-add').addEventListener('click', function () {
            var first = spList.querySelector('.js-yb2-sp-row'), row = first.cloneNode(true);
            row.querySelector('.js-yb2-sp').value = '';
            spList.insertBefore(row, $('yb2-sp-add'));
            spRenumber();
            row.querySelector('.js-yb2-sp').focus();
        });
    }
    ['yb2-no', 'yb2-old-no'].forEach(function (id) {
        $(id).addEventListener('input', function (e) {
            if (e.inputType && e.inputType.indexOf('delete') === 0) return;
            var v = this.value;
            // คนที่เคยชินพิมพ์ขีดเอง จะได้ "2411--019074" — ขีดที่ระบบเติมแล้ว พิมพ์ซ้ำ = ไม่นับ (คลินิกเจอ 2569-11-05)
            if (/-{2,}/.test(v)) { this.value = v.replace(/-{2,}/g, '-'); return; }
            if (/^\d{4}$/.test(v)) { this.value = v + '-'; }
            else if (/^\d{5,}$/.test(v)) { this.value = v.slice(0, 4) + '-' + v.slice(4); }
        });
    });
    $('yb2-lost-known').addEventListener('change', paint);
    $('yb2-lost-unknown').addEventListener('change', paint);
    $('yb2-goto-vac').addEventListener('click', function () {
        var s = document.getElementById('tmc-vac-source');
        if (s) { s.value = '2'; s.dispatchEvent(new Event('change')); }
    });

    $('yb2-save').addEventListener('click', function () {
        var st = $('yb2-status'), btn = this;
        if (!act) { st.innerHTML = '<span class="text-danger">เลือกก่อนว่าครั้งนี้ทำอะไรกับสมุด</span>'; return; }
        var fd = new FormData();
        fd.append('_token', box.dataset.token);
        fd.append('action', act);
        fd.append('no', $('yb2-no').value);
        // เสีย/ชำรุด: เลขเล่มอยู่ในช่องเล่มละช่อง — ส่งรวมไป เซิร์ฟเวอร์แยกเล่มแรก/เล่มถัดไปเอง
        fd.append('old_no', act === 'replace' ? '' : $('yb2-old-no').value);
        fd.append('old_src', act === 'old' || act === 'replace' || act === 'lost' ? $('yb2-src').value : '');
        fd.append('lost_known', lostKnown() ? '1' : '0');
        if (spList && act === 'replace') fd.append('spoiled_nos', Array.prototype.map.call(spList.querySelectorAll('.js-yb2-sp'), function (i) { return i.value.trim(); }).filter(Boolean).join(' '));
        fd.append('date', $('yb2-date').value);
        btn.disabled = true;
        fetch(box.dataset.url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                btn.disabled = false;
                st.innerHTML = '';
                var s = document.createElement('span');
                s.className = j.ok ? 'text-success' : 'text-danger';
                s.textContent = (j.ok ? '✓ ' : '') + (j.message || (j.ok ? 'บันทึกแล้ว' : 'บันทึกไม่สำเร็จ'));
                st.appendChild(s);
                if (j.ok) applyResult(j);
            })
            .catch(function () { btn.disabled = false; st.innerHTML = '<span class="text-danger">บันทึกไม่สำเร็จ ลองใหม่อีกครั้ง</span>'; });
    });

    function applyResult(j) {
        $('yb2-current').textContent = j.no || '— ยังไม่มี —';
        renderHist(j.history || '');
        var fl = document.getElementById('yb-float-no'); if (fl) fl.textContent = j.no || '';
    }

    // ประวัติเล่มเก่า: แต่ละรายการมีปุ่ม ✕ ลบ (ลงเกิน/ผิด — คลินิกขอ 2569-11-04) · ใส่ข้อความด้วย textContent กันโค้ดแปลกในประวัติ
    function renderHist(str) {
        var el = $('yb2-hist');
        el.innerHTML = '';
        var items = String(str || '').split('|').map(function (x) { return x.trim(); }).filter(Boolean);
        if (!items.length) { el.textContent = '—'; return; }
        items.forEach(function (txt) {
            var chip = document.createElement('span');
            chip.className = 'border rounded px-2 py-0 bg-white d-inline-flex align-items-center gap-1';
            var t = document.createElement('span'); t.textContent = txt;
            var x = document.createElement('button');
            x.type = 'button'; x.className = 'btn btn-link btn-sm p-0 text-danger js-yb2-del';
            x.title = 'ลบรายการนี้'; x.setAttribute('aria-label', 'ลบรายการ ' + txt); x.textContent = '✕';
            x.dataset.entry = txt;
            chip.appendChild(t); chip.appendChild(x); el.appendChild(chip);
        });
    }
    $('yb2-hist').addEventListener('click', function (e) {
        var x = e.target.closest('.js-yb2-del');
        if (!x || !confirm('ลบประวัติรายการนี้?\n' + x.dataset.entry)) return;
        var fd = new FormData();
        fd.append('_token', box.dataset.token);
        fd.append('entry', x.dataset.entry);
        fetch(box.dataset.delUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var st = $('yb2-status'); st.innerHTML = '';
                var s = document.createElement('span');
                s.className = j.ok ? 'text-success' : 'text-danger';
                s.textContent = (j.ok ? '✓ ' : '') + (j.message || '');
                st.appendChild(s);
                if (j.ok) applyResult(j);
            })
            .catch(function () { alert('ลบไม่สำเร็จ ลองใหม่อีกครั้ง'); });
    });

    // ปฏิทินแบบเดียวกับช่องวันที่ฉีด — bindThDatepicker รอไฟล์ภาษาไทยก่อน (ผูกเร็วไปได้ ค.ศ.)
    var dpOpts = { format: 'dd/mm/yyyy', language: 'th-th', thaiyear: true, autoclose: true, todayHighlight: true };
    (function bindDp() {
        if (window.bindThDatepicker) { window.bindThDatepicker('#yb2-date', dpOpts); return; }
        if (window.jQuery && jQuery.fn.datepicker && (jQuery.fn.datepicker.dates || {})['th-th']) { jQuery('#yb2-date').datepicker(dpOpts); return; }
        if (document.readyState !== 'complete') window.addEventListener('load', bindDp, { once: true });
    })();

    renderHist($('yb2-hist').dataset.hist);
    paint();
})();
</script>
