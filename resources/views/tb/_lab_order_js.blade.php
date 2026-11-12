{{-- ตัวจัดการกล่อง "สั่งตรวจครั้งนี้" + popup ในบัตร TB 01 (2569-10-02)
     อยู่ในหน้าที่โหลดเต็ม ผูกกับ document/ตัว popup — เนื้อใน popup และกล่องสรุปถูกโหลดด้วย fetch (สคริปต์ในนั้นไม่รัน)
     เปิด/ปิด popup ผ่าน data-bs-toggle / data-bs-dismiss เท่านั้น: Bootstrap ถูกรวมใน app.js โดยไม่มี window.bootstrap
     (เคยเรียก bootstrap.Modal ตรง ๆ แล้วปุ่มเงียบ — ดูหมายเหตุใน visit/form/index) --}}
@include('partials.external_lab_picker_js')
<script>
(function () {
    if (window.__tbLabOrderInit) return;
    window.__tbLabOrderInit = true;

    var meta  = document.querySelector('meta[name="csrf-token"]');
    var TOKEN = meta ? meta.getAttribute('content') : '{{ csrf_token() }}';
    var LOADING = '<div class="tb-lo-loading"><span class="spinner-border spinner-border-sm me-2"></span> กำลังโหลด…</div>';

    function headers() {
        return { 'X-CSRF-TOKEN': TOKEN, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // ---- แจ้งเตือนมุมขวาล่าง (ทำเอง ไม่พึ่ง bootstrap.Toast ซึ่งไม่มีให้เรียกในหน้านี้) ----
    function toast(msg, kind) {
        var wrap = document.getElementById('tbLoToastWrap');
        if (!wrap) return;
        var bg = kind === 'error' ? '#b91c1c' : (kind === 'info' ? '#475569' : '#047857');
        var t = document.createElement('div');
        t.className = 'toast show align-items-center text-white border-0 mb-2';
        t.setAttribute('role', 'status');
        t.style.background = bg;
        t.innerHTML = '<div class="d-flex"><div class="toast-body"><i class="fa ' + (kind === 'error' ? 'fa-circle-xmark' : 'fa-circle-check')
                    + ' me-2"></i>' + esc(msg) + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" aria-label="ปิด"></button></div>';
        t.querySelector('.btn-close').addEventListener('click', function () { t.remove(); });
        wrap.appendChild(t);
        setTimeout(function () { t.style.transition = 'opacity .4s'; t.style.opacity = '0'; setTimeout(function () { t.remove(); }, 450); }, 3500);
    }

    // ---- กล่องสรุป: สลับเนื้อในที่ (ไม่ต้องโหลดหน้าใหม่ ข้อมูลที่กรอกค้างในกล่องอื่นของบัตรไม่หาย) ----
    function refreshSummary(html) {
        var box = document.getElementById('tb-lab-order-body');
        if (!box) return Promise.resolve();
        if (typeof html === 'string') { box.innerHTML = html; return Promise.resolve(); }
        return fetch(box.dataset.summaryUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.text() : null; })
            .then(function (h) { if (h !== null) box.innerHTML = h; });
    }

    // ปฏิทิน พ.ศ. ผ่านตัวรอไฟล์ภาษา th-th — ลอยเหนือ popup ได้เพราะ .datepicker ตั้ง z-index 1090 (> modal 1055)
    // และต่อกับ body (ค่าเริ่มต้น) จึงไม่โดน overflow ของ modal-body ตัด
    function bindDates(scope) {
        if (!window.bindThDatepicker) return;
        scope.querySelectorAll('input.date').forEach(function (el) {
            window.bindThDatepicker(el, { autoclose: true, todayHighlight: true });
        });
    }

    function closeModal(modalEl) {
        var x = modalEl.querySelector('[data-bs-dismiss="modal"]');
        if (x) x.click();
    }

    function loadBody(modalEl) {
        var src  = modalEl.querySelector('[data-load]');
        var body = modalEl.querySelector('.js-lo-body');
        body.innerHTML = LOADING;
        return fetch(src.dataset.load, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.text().then(function (t) { return { ok: r.ok, text: t }; }); })
            .then(function (x) {
                body.innerHTML = x.text;
                return x.ok;
            })
            .catch(function () {
                body.innerHTML = '<div class="alert alert-danger mb-0">โหลดไม่สำเร็จ — ตรวจสอบการเชื่อมต่อแล้วลองเปิดใหม่</div>';
                return false;
            });
    }

    function focusFirst(scope) {
        var el = scope.querySelector('input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled])');
        if (el) { try { el.focus({ preventScroll: false }); } catch (e) { el.focus(); } }
    }

    // ---- error รายช่อง: tb_lab.lab_specimens.0.date -> tb_lab[lab_specimens][0][date] ----
    function fieldName(key) {
        var p = key.split('.');
        return p[0] + p.slice(1).map(function (s) { return '[' + s + ']'; }).join('');
    }
    function clearErrors(scope) {
        scope.querySelectorAll('.js-lo-fb').forEach(function (n) { n.remove(); });
        scope.querySelectorAll('.is-invalid').forEach(function (n) { n.classList.remove('is-invalid'); });
        var box = scope.querySelector('.js-lo-errors');
        if (box) { box.classList.add('d-none'); box.innerHTML = ''; }
    }
    function showErrors(scope, data, fallback) {
        var box = scope.querySelector('.js-lo-errors');
        var list = [], first = null;
        var errs = (data && data.errors) || {};
        Object.keys(errs).forEach(function (key) {
            var msg = [].concat(errs[key])[0];
            list.push(msg);
            var el = scope.querySelector('[name="' + fieldName(key).replace(/"/g, '\\"') + '"]');
            if (!el) return;
            el.classList.add('is-invalid');
            var fb = document.createElement('div');
            fb.className = 'invalid-feedback d-block js-lo-fb';
            fb.textContent = msg;
            var after = el.closest('.input-group') || el;
            after.parentNode.insertBefore(fb, after.nextSibling);
            if (!first) first = el;
        });
        if (box) {
            box.innerHTML = '<b><i class="fa fa-triangle-exclamation me-1"></i>' + esc((data && data.message) || fallback || 'บันทึกไม่สำเร็จ') + '</b>'
                + (list.length ? '<ul class="mb-0 small mt-1">' + list.map(function (m) { return '<li>' + esc(m) + '</li>'; }).join('') + '</ul>' : '');
            box.classList.remove('d-none');
        }
        var target = first || box;
        if (target && target.scrollIntoView) target.scrollIntoView({ block: 'center', behavior: 'smooth' });
        if (first) { try { first.focus({ preventScroll: true }); } catch (e) {} }
    }
    function failMessage(status) {
        if (status === 419) return 'หน้าเปิดค้างนานจนหมดอายุ — โหลดหน้าใหม่ (F5) แล้วบันทึกอีกครั้ง';
        if (status === 403) return 'ไม่มีสิทธิ์บันทึกส่วนนี้';
        return 'บันทึกไม่สำเร็จ (HTTP ' + status + ') ลองใหม่อีกครั้ง';
    }
    function post(url, fd) {
        return fetch(url, { method: 'POST', headers: headers(), body: fd, credentials: 'same-origin' })
            .then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (j) { return { status: r.status, ok: r.ok, data: j }; });
            });
    }
    function busy(btn, on) {
        if (!btn) return;
        if (on) { btn.dataset.html = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังบันทึก…'; }
        else { btn.disabled = false; if (btn.dataset.html) btn.innerHTML = btn.dataset.html; }
    }

    // ===================== popup 1: สั่งตรวจ / ลงผล =====================
    var labModal = document.getElementById('tbLabModal');
    var labForm  = document.getElementById('tbLabModalForm');
    if (labModal && labForm) {
        var wantResults = false;
        labModal.addEventListener('show.bs.modal', function (e) {
            wantResults = !!(e.relatedTarget && e.relatedTarget.dataset && e.relatedTarget.dataset.focus === 'results');
            var saveBtn = labForm.querySelector('.js-lo-save');
            saveBtn.disabled = true;
            // โหลดค่าล่าสุดจากฐานทุกครั้งที่เปิด — อีกห้องอาจเพิ่งลงผล
            loadBody(labModal).then(function (ok) {
                if (!ok) return;
                var root = labForm.querySelector('.js-tb-lab-fields');
                if (root && window.TbLabFields) window.TbLabFields.init(root);   // เปิด/ปิดช่อง + จำค่าตั้งต้นไว้ตัดกลุ่มที่ไม่ได้แก้
                bindDates(labForm);
                saveBtn.disabled = false;
                var body = labModal.querySelector('.js-lo-body');
                if (wantResults) {
                    var sec = labForm.querySelector('[data-lab-group="sec2"]');
                    if (sec) {
                        sec.scrollIntoView({ block: 'start' });
                        sec.classList.add('tb-lo-focus');
                        var r = sec.querySelector('[data-dep]:not([disabled])');
                        if (r) r.focus(); else focusFirst(sec);
                    }
                } else {
                    focusFirst(body);
                }
            });
        });

        // Enter ในช่องใดก็ตาม = ส่งฟอร์มของ popup นี้เอง (ฟอร์มนี้อยู่นอกฟอร์มอื่นของบัตร) -> มาที่นี่แล้วบันทึกด้วย fetch
        labForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var root = labForm.querySelector('.js-tb-lab-fields');
            if (!root) return;
            clearErrors(labForm);
            // ส่งเฉพาะกลุ่มที่แก้ (เหมือนหน้าตรวจ) — ปิดกลุ่มที่ไม่ได้แก้ชั่วคราว เก็บข้อมูล แล้วเปิดคืนทันที
            var restore = window.TbLabFields ? window.TbLabFields.trim(root) : function () {};
            var fd = new FormData(labForm);
            restore();
            var btn = labForm.querySelector('.js-lo-save');
            busy(btn, true);
            post(labForm.action, fd).then(function (res) {
                busy(btn, false);
                if (res.ok && res.data.ok) {
                    if (window.TbLabFields) window.TbLabFields.snapshot(root);   // บันทึกแล้ว = ค่าตั้งต้นใหม่
                    refreshSummary(res.data.html);
                    closeModal(labModal);
                    toast(res.data.message || 'บันทึกแล้ว', res.data.message === 'ไม่มีอะไรเปลี่ยน' ? 'info' : 'ok');
                    return;
                }
                showErrors(labForm, res.data, res.status === 422 ? null : (res.data.message || failMessage(res.status)));
            }).catch(function () {
                busy(btn, false);
                showErrors(labForm, null, 'บันทึกไม่สำเร็จ — ตรวจสอบการเชื่อมต่อแล้วลองใหม่');
            });
        });
    }

    // ===================== popup 3: Lab ในคลินิก (HIV ฯลฯ) =====================
    // ตัวบันทึกอยู่ใน tb/_inhouse_lab_js (ใช้ร่วมกับหน้าตรวจ TB) — ที่นี่แค่โหลดแผงตอนเปิด และรับสัญญาณหลังบันทึก
    var inhModal = document.getElementById('tbInhLabModal');
    if (inhModal) {
        inhModal.addEventListener('show.bs.modal', function () {
            var save = inhModal.querySelector('.modal-footer .js-inh-save');
            if (save) save.disabled = true;
            loadBody(inhModal).then(function (ok) {
                if (save) save.disabled = !ok;
                if (ok) { var cb = inhModal.querySelector('.js-inh-key:not([disabled])'); if (cb) cb.focus(); }
            });
        });
        document.addEventListener('tb:inhouse-saved', function (e) {
            if (!inhModal.classList.contains('show')) return;
            var d = e.detail || {};
            refreshSummary();
            if (d.blocked && d.blocked.length) {   // ถอดตัวที่มีผลแล้วไม่ได้ — ให้เห็นข้อความในแผง ไม่ปิด popup
                toast(d.message, 'error');
                return;
            }
            closeModal(inhModal);
            toast(d.message || 'บันทึกแล้ว', d.message === 'ไม่มีอะไรเปลี่ยน' ? 'info' : 'ok');
        });
    }

    // ===================== popup 2: Lab ภายนอก =====================
    var extModal = document.getElementById('tbExtLabModal');
    function reloadExt(focus) {
        return loadBody(extModal).then(function (ok) {
            var save = extModal.querySelector('.js-ext-save');
            if (save) save.classList.toggle('d-none', !(ok && document.getElementById('tbExtRowsForm')));
            if (!ok) return;
            bindDates(extModal);
            if (focus) { var s = extModal.querySelector('.js-ext-search'); if (s) s.focus(); }
        });
    }
    if (extModal) {
        extModal.addEventListener('show.bs.modal', function () { reloadExt(false); });
        extModal.addEventListener('shown.bs.modal', function () {
            var s = extModal.querySelector('.js-ext-search'); if (s) s.focus();
        });

        // ค้นหา / เตือน 2 ชั้น / หยิบหลายรายการ / ยืนยันสั่ง -> partials/external_lab_picker_js (ใช้ร่วมกับการ์ดในหน้าตรวจ 2569-10-02)
        // ที่นี่รับแค่สัญญาณหลังสั่งสำเร็จ แล้วโหลดเนื้อ popup + กล่องสรุปใหม่
        extModal.addEventListener('extlab:saved', function (e) {
            var d = e.detail || {};
            toast('สั่งเพิ่ม ' + (d.created || 0) + ' รายการแล้ว');
            refreshSummary();
            reloadExt(true);
        });
        // ปิด popup ตอนยังมีรายการที่หยิบไว้แต่ยังไม่กดยืนยัน = หายตอนเปิดใหม่ (เนื้อในโหลดใหม่ทุกครั้ง) -> ถามก่อน
        extModal.addEventListener('hide.bs.modal', function (e) {
            var n = window.ExtLabPicker ? window.ExtLabPicker.pendingCount(extModal) : 0;
            if (n && !confirm('มี ' + n + ' รายการที่หยิบไว้แต่ยังไม่ได้กด "ยืนยันสั่ง"\n\nปิด popup แล้วรายการนี้จะหายไป ยืนยันปิดหรือไม่?')) e.preventDefault();
        });

        // ฟอร์ม "บันทึกผล/ราคา" ในเนื้อ popup ถูกโหลดทีหลัง -> จับ submit ที่ document
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || form.id !== 'tbExtRowsForm') return;
            e.preventDefault();
            clearErrors(extModal);

            var btn = e.submitter || form.querySelector('[type=submit]');
            busy(btn, true);
            post(form.dataset.url, new FormData(form)).then(function (res) {
                busy(btn, false);
                if (res.ok && res.data.ok) {
                    toast('บันทึกแล้ว');
                    refreshSummary();
                    reloadExt(false);
                    return;
                }
                showErrors(extModal, res.data, res.status === 422 ? null : failMessage(res.status));
            }).catch(function () {
                busy(btn, false);
                showErrors(extModal, null, 'บันทึกไม่สำเร็จ — ตรวจสอบการเชื่อมต่อแล้วลองใหม่');
            });
        });

        // ลบรายการ — ยืนยันก่อน แล้วส่ง DELETE (ผ่าน _method) ไป endpoint เดิม (คิดบิลใหม่ให้เอง)
        extModal.addEventListener('click', function (e) {
            var del = e.target.closest && e.target.closest('.js-ext-del');
            if (!del) return;
            if (!confirm('ลบรายการ "' + (del.dataset.name || '') + '" ?')) return;
            var fd = new FormData();
            fd.append('_method', 'DELETE');
            del.disabled = true;
            post(del.dataset.url, fd).then(function (res) {
                if (res.ok && res.data.ok) {
                    toast('ลบรายการแล้ว');
                    refreshSummary();
                    reloadExt(false);
                } else {
                    del.disabled = false;
                    showErrors(extModal, res.data, failMessage(res.status));
                }
            }).catch(function () { del.disabled = false; toast('ลบไม่สำเร็จ — ลองใหม่', 'error'); });
        });
    }
})();
</script>
