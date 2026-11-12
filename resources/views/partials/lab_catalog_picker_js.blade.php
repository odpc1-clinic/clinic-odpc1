{{-- ตัวจัดการตัวเลือก "สั่งแล็บ" (partials/lab_catalog_picker) — 2569-10-02
     ต้อง include ในหน้าที่โหลดเต็ม (ไม่ใช่เนื้อที่โหลดด้วย fetch — สคริปต์ใน innerHTML ไม่รัน)
     เปิด popup ผ่าน data-bs-toggle เท่านั้น (Bootstrap รวมใน app.js ไม่มี window.bootstrap ให้เรียกตรง ๆ)
     สั่งเสร็จ: ยิง event "labcatalog:ordered" ที่ document + อัปเดตส่วนที่เกี่ยวในหน้าให้เอง (แผง Lab ในคลินิก / ตาราง Lab นอก /
     ช่องส่งตรวจวัณโรค / กล่องสรุปในบัตร TB 01) ไม่ต้องโหลดหน้าใหม่ — หน้าตรวจมีของที่ยังไม่ Save ค้างได้ --}}
@once
<script>
(function () {
    if (window.LabCatalogPicker) return;

    var modal = null, ctx = {}, data = null, cat = '', uid = 0;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function token() {
        var m = document.querySelector('meta[name="csrf-token"]');
        if (m) return m.getAttribute('content');
        var i = document.querySelector('input[name=_token]');
        return i ? i.value : '{{ csrf_token() }}';
    }
    function $(sel) { return modal.querySelector(sel); }
    function money(n) { return Number(n).toLocaleString(undefined, { maximumFractionDigits: 2 }); }
    function itemById(id) {
        for (var i = 0; i < data.items.length; i++) { if (String(data.items[i].id) === String(id)) return data.items[i]; }
        return null;
    }
    function pendingRows() { return modal.querySelectorAll('.js-lcat-pending-body tr[data-id]'); }
    function isPending(id) {
        return Array.prototype.some.call(pendingRows(), function (tr) { return tr.dataset.id === String(id); });
    }

    // ---------------- วาดรายการ ----------------
    // ปุ่มที่มี data-scope (หน้าตรวจ TB 2569-10-14: วัณโรค / แล็บใน / แล็บนอก) = เปิดมาเห็นเฉพาะกลุ่มนั้น · กด "ดูทุกหมวด" ล้างได้
    var SCOPE_LABEL = { tb: 'วัณโรค', 'in': 'แล็บใน', out: 'แล็บนอก', cardio: 'ตรวจหัวใจ' };
    function inScope(it) {
        if (!ctx.scope) return true;
        if (ctx.scope === 'tb') return it.category === 'tb';
        if (ctx.scope === 'in') return it.kind === 'in_house' && it.category !== 'cardio';   // EKG ไม่ใช่แล็บ — อยู่ปุ่ม "ตรวจหัวใจ"
        if (ctx.scope === 'cardio') return it.category === 'cardio';
        if (ctx.scope === 'out') return it.kind === 'external';
        return true;
    }
    function drawScope() {
        var t = document.getElementById('labCatalogTitle');
        if (!t) return;
        t.innerHTML = '<i class="fa fa-flask me-1"></i> สั่งแล็บ' + (ctx.scope ? ' · ' + esc(SCOPE_LABEL[ctx.scope] || '')
            + ' <a href="#" class="small fw-normal ms-2 js-lcat-allscope">ดูทุกหมวด</a>' : '');
    }
    function drawCats() {
        var counts = {}, all = 0;
        data.items.forEach(function (it) { if (inScope(it)) { all++; counts[it.category] = (counts[it.category] || 0) + 1; } });
        var html = '<a class="list-group-item list-group-item-action ' + (cat === '' ? 'active' : '') + '" data-cat="">'
                 + '<span>ทั้งหมด</span><span class="lcat-n">' + all + '</span></a>';
        data.categories.forEach(function (c) {
            if (ctx.scope && !counts[c.key]) return;
            html += '<a class="list-group-item list-group-item-action ' + (cat === c.key ? 'active' : '') + '" data-cat="' + esc(c.key) + '">'
                  + '<span>' + esc(c.label) + '</span><span class="lcat-n">' + (ctx.scope ? counts[c.key] : c.count) + '</span></a>';
        });
        drawScope();
        $('.js-lcat-cats').innerHTML = html;
    }

    function priceText(it) {
        if (it.price != null) return money(it.price) + '.-';
        if (it.ref != null && it.kind === 'external') return '<span class="text-muted">ใบเสนอราคา ' + money(it.ref) + '</span>';
        return '<span class="text-muted">' + (it.kind === 'tb_sendout' ? 'ไม่คิดเงิน' : '—') + '</span>';
    }

    function drawList() {
        var q = ($('.js-lcat-search').value || '').trim().toLowerCase();
        var catLabel = {};
        data.categories.forEach(function (c) { catLabel[c.key] = c.label; });
        var html = '', shown = 0, lastCat = null;
        data.items.forEach(function (it) {
            // ค้นหา = ข้ามทุกหมวด (ไม่ต้องรู้ว่าอยู่หมวดไหน) · ไม่ค้นหา = ตามหมวดที่เลือก
            if (q) {
                var hay = (it.name + ' ' + (it.code || '') + ' ' + it.kindLabel).toLowerCase();
                if (hay.indexOf(q) === -1) return;
            } else if (!inScope(it) || (cat && it.category !== cat)) {
                return;
            }
            shown++;
            if (it.category !== lastCat && (q || !cat)) {
                html += '<div class="lcat-cat-head">' + esc(catLabel[it.category] || it.category) + '</div>';
            }
            lastCat = it.category;
            var done = it.ordered && it.ordered.full;
            var on = !done && isPending(it.id);
            var poc = it.ordered && it.ordered.poc;
            // DTX / EKG ที่สั่งแล้ว: ใช้ div (ไม่ใช่ label) กันคลิกช่องผลแล้วไปโดนกล่องติ๊ก
            if (poc) {
                var fl = poc.flag;
                html += '<div class="lcat-item is-done is-poc" data-id="' + it.id + '">'
                      + '<input type="checkbox" class="form-check-input mt-0" checked disabled>'
                      + '<span class="lcat-name"><span class="fw-semibold">' + esc(it.name) + '</span>'
                      + ' <span class="lcat-done-chip"><i class="fa fa-check me-1"></i>' + esc(it.ordered.text) + '</span></span>'
                      + '<span class="lcat-badge lcat-k-' + esc(it.kind) + '">' + esc(it.kindLabel) + '</span>'
                      + '<span class="lcat-price" style="min-width:5.5rem;">' + priceText(it) + '</span>'
                      + '<span class="lcat-poc"><span class="small fw-semibold">' + esc(poc.label) + '</span>'
                      + '<input type="number" class="form-control form-control-sm js-lcat-poc" data-method="' + poc.method + '" min="' + poc.min + '" max="' + poc.max + '" value="' + esc(poc.value) + '">'
                      + '<span class="small text-muted">' + esc(poc.unit) + '</span>'
                      + '<button type="button" class="btn btn-sm btn-success js-lcat-poc-save"><i class="fa fa-save me-1"></i>บันทึกผล</button>'
                      + (fl ? '<span class="small fw-semibold" style="color:' + (fl[1] === 'danger' ? '#b91c1c' : '#b45309') + ';">' + esc(fl[0]) + '</span>' : '')
                      + '<span class="small js-lcat-poc-msg"></span></span>'
                      + '</div>';
                return;
            }
            html += '<label class="lcat-item ' + (done ? 'is-done' : '') + (on ? ' is-on' : '') + '" data-id="' + it.id + '">'
                  + '<input type="checkbox" class="form-check-input mt-0 js-lcat-tick" value="' + it.id + '"'
                  + (done ? ' checked disabled' : '') + (on ? ' checked' : '') + '>'
                  + '<span class="lcat-name"><span class="fw-semibold">' + esc(it.name) + '</span>'
                  + (it.code ? ' <span class="text-muted small">' + esc(it.code) + '</span>' : '')
                  + (it.ordered ? ' <span class="lcat-done-chip"><i class="fa fa-check me-1"></i>' + esc(it.ordered.text) + '</span>' : '')
                  + (it.warn ? '<br><span class="lcat-warn"><i class="fa fa-triangle-exclamation me-1"></i>' + esc(it.warn.text) + '</span>' : '')
                  + '</span>'
                  + '<span class="lcat-badge lcat-k-' + esc(it.kind) + '">' + esc(it.kindLabel) + '</span>'
                  + '<span class="lcat-price" style="min-width:5.5rem;">' + priceText(it) + '</span>'
                  + '</label>';
        });
        $('.js-lcat-list').innerHTML = html || '<div class="text-center text-muted py-4">ไม่พบรายการ' + (q ? ' "' + esc(q) + '"' : '') + '</div>';
        $('.js-lcat-found').textContent = q ? ('พบ ' + shown) : (shown + ' รายการ');
    }

    // ---------------- รายการที่จะสั่ง ----------------
    function refreshCount() {
        var n = pendingRows().length;
        modal.querySelectorAll('.js-lcat-n').forEach(function (s) { s.textContent = n; });
        $('.js-lcat-pending').classList.toggle('d-none', n === 0);
        $('.js-lcat-confirm').disabled = n === 0;
    }

    function addPending(it, copyFrom) {
        // ด่านเตือนเดิมของ Lab นอก — ไม่บล็อก แค่ให้ยืนยันก่อน (เคยสั่งผิดจนคลินิกต้องจ่ายเอง)
        if (!copyFrom && it.warn && !confirm('⚠ ' + it.warn.text + '\n\nกำลังจะสั่ง: ' + it.name + '\n\nยืนยันหรือไม่?')) return false;

        var tr = document.createElement('tr');
        tr.dataset.id = it.id;
        tr.dataset.uid = ++uid;

        var spec = '';
        if (it.needsText) {
            spec = '<input type="text" class="form-control form-control-sm js-p-text" maxlength="255" placeholder="พิมพ์ชื่อการตรวจ">';
        } else if (it.needsSpec) {
            spec = '<select class="form-select form-select-sm js-p-spec"><option value="">— เลือก —</option>'
                 + it.specs.map(function (s) { return '<option value="' + esc(s.v) + '">' + esc(s.l) + '</option>'; }).join('')
                 + '</select><input type="text" class="form-control form-control-sm mt-1 d-none js-p-spec-other" maxlength="255" placeholder="ระบุ">';
            if (it.kind === 'in_house') {
                // Gram/Culture/PCR ตำแหน่งละแถว (เหมือนหน้าสั่ง Lab เต็มที่ติ๊กได้หลายตำแหน่ง)
                spec += '<button type="button" class="btn btn-link btn-sm p-0 mt-1 js-p-dup">+ อีกตำแหน่ง</button>';
            }
        } else if (it.specimenNote) {
            spec = '<span class="small text-muted">' + esc(it.specimenNote) + '</span>';
        }

        var price;
        if (it.priceEdit) {
            price = '<input type="number" step="0.01" min="0" class="form-control form-control-sm js-p-price" placeholder="ราคาเก็บคนไข้"'
                  + (it.price != null ? ' value="' + esc(it.price) + '"' : '') + '>'
                  + '<label class="small mt-1 d-flex align-items-center gap-1"><input type="checkbox" class="form-check-input mt-0 js-p-waive"> ยกเว้นค่าตรวจ</label>'
                  + '<div class="small text-muted">' + esc(it.priceNote) + '</div>';
        } else {
            price = '<div class="small">' + (it.price != null ? '<b>' + money(it.price) + '.-</b> ' : '') + '<span class="text-muted">' + esc(it.priceNote) + '</span></div>';
        }

        var date = it.needsDate
            ? '<input type="text" class="form-control form-control-sm date js-p-date" autocomplete="off" placeholder="ว/ด/ป" value="' + esc(lastDate() || data.today) + '">'
            : '<span class="small text-muted">—</span>';

        tr.innerHTML = '<td><div class="fw-semibold">' + esc(it.name) + '</div><span class="lcat-badge lcat-k-' + esc(it.kind) + '">' + esc(it.kindLabel) + '</span>'
                     + '<div class="small text-danger js-p-err"></div></td>'
                     + '<td>' + (spec || '<span class="small text-muted">—</span>') + '</td>'
                     + '<td>' + price + '</td><td>' + date + '</td>'
                     + '<td class="text-end"><button type="button" class="btn btn-outline-secondary btn-sm js-p-remove" title="เอาออก"><i class="fa fa-times"></i></button></td>';

        var body = $('.js-lcat-pending-body');
        if (copyFrom && copyFrom.nextSibling) body.insertBefore(tr, copyFrom.nextSibling); else body.appendChild(tr);
        var d = tr.querySelector('.js-p-date');
        if (d && window.bindThDatepicker) window.bindThDatepicker(d, { autoclose: true, todayHighlight: true });
        // สิ่งส่งตรวจวัณโรคตั้งต้นเป็นเสมหะ Spot (ใช้บ่อยสุด) — เปลี่ยนได้
        var sp = tr.querySelector('.js-p-spec');
        if (sp && it.kind === 'tb_sendout') sp.value = 'sputum_spot';
        refreshCount();
        return true;
    }
    function lastDate() {
        var rows = pendingRows();
        for (var i = rows.length - 1; i >= 0; i--) {
            var d = rows[i].querySelector('.js-p-date');
            if (d && d.value) return d.value;
        }
        return '';
    }
    function removePending(id) {
        Array.prototype.slice.call(pendingRows()).forEach(function (tr) { if (tr.dataset.id === String(id)) tr.remove(); });
        refreshCount();
    }

    function showError(msg, list) {
        var b = $('.js-lcat-error');
        b.innerHTML = '<b><i class="fa fa-triangle-exclamation me-1"></i>' + esc(msg) + '</b>'
            + (list && list.length ? '<ul class="mb-0 small mt-1">' + list.map(function (m) { return '<li>' + esc(m) + '</li>'; }).join('') + '</ul>' : '');
        b.classList.remove('d-none');
    }
    function hideError() {
        var b = $('.js-lcat-error');
        b.classList.add('d-none'); b.innerHTML = '';
        pendingRows().forEach(function (tr) { tr.classList.remove('is-bad'); var e = tr.querySelector('.js-p-err'); if (e) e.textContent = ''; });
    }

    // ปุ่มที่มี data-preset="id,id" (เช่น "สั่งแล็บที่ถึงกำหนด" จากตารางติดตาม TB 2569-10-05) = ติ๊กรายการให้ล่วงหน้า
    // ใช้ครั้งเดียวต่อการเปิด (load หลังสั่งเสร็จจะไม่ติ๊กซ้ำ) · รายการที่สั่งไปแล้ว/ติ๊กค้างอยู่แล้วข้าม · ผู้ใช้ยังต้องกดยืนยันเอง
    function applyPreset() {
        var ids = String(ctx.preset || '').split(',').filter(Boolean);
        ctx.preset = '';
        if (!ids.length) return;
        var added = 0;
        ids.forEach(function (id) {
            var it = itemById(id);
            if (!it || (it.ordered && it.ordered.full) || isPending(it.id)) return;
            if (addPending(it)) added++;
        });
        if (added) drawList();
    }

    // ---------------- โหลด / ส่ง ----------------
    function load(keepResults) {
        $('.js-lcat-loading').classList.remove('d-none');
        $('.js-lcat-main').classList.add('d-none');
        if (!keepResults) { var r = $('.js-lcat-results'); r.classList.add('d-none'); r.innerHTML = ''; }
        return fetch(ctx.pickerUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, data: j }; }); })
            .then(function (res) {
                if (!res.ok) throw new Error((res.data && res.data.message) || 'โหลดไม่สำเร็จ');
                data = res.data;
                var v = data.visit;
                $('.js-lcat-who').textContent = (v.who || '-') + ' · HN ' + (v.hn || '-') + ' · Visit #' + v.id + ' วันที่ ' + (v.date || '');
                $('.js-lcat-loading').classList.add('d-none');
                $('.js-lcat-main').classList.remove('d-none');
                drawCats(); drawList(); refreshCount();
                applyPreset();
            })
            .catch(function (e) {
                $('.js-lcat-loading').innerHTML = '<div class="alert alert-danger mb-0">' + esc(e.message || 'โหลดไม่สำเร็จ') + ' — ปิดแล้วลองเปิดใหม่</div>';
            });
    }

    // ลงผล DTX / EKG ที่สั่งไว้แล้ว — บันทึกทันที แล้วโหลดรายการใหม่ (สถานะ/คำเตือนอัปเดต)
    function savePoc(btn) {
        var box = btn.closest('.lcat-poc'), inp = box.querySelector('.js-lcat-poc'), msg = box.querySelector('.js-lcat-poc-msg');
        var fd = new FormData();
        fd.append('method', inp.dataset.method);
        fd.append('value', inp.value.trim());
        btn.disabled = true;
        msg.textContent = 'กำลังบันทึก…'; msg.style.color = '#92400e';
        fetch(ctx.orderUrl.replace(/\/order$/, '/result'), {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, data: j }; }); })
        .then(function (res) {
            btn.disabled = false;
            if (!res.ok || !res.data.ok) { msg.textContent = (res.data && res.data.message) || 'บันทึกไม่สำเร็จ'; msg.style.color = '#b91c1c'; return; }
            var r = $('.js-lcat-results');
            r.classList.remove('d-none');
            r.innerHTML = '<b style="color:#065f46;"><i class="fa fa-circle-check me-1"></i>' + esc(res.data.message) + '</b>';
            document.dispatchEvent(new CustomEvent('labcatalog:ordered', { detail: { poc: true } }));
            load(true);
        })
        .catch(function () { btn.disabled = false; msg.textContent = 'เชื่อมต่อไม่ได้ — ลองใหม่'; msg.style.color = '#b91c1c'; });
    }

    function confirmOrder(btn) {
        var rows = pendingRows();
        if (!rows.length) return;
        hideError();
        var fd = new FormData(), bad = [];
        Array.prototype.forEach.call(rows, function (tr, i) {
            var g = function (s) { var el = tr.querySelector(s); return el ? el : null; };
            fd.append('items[' + i + '][id]', tr.dataset.id);
            if (g('.js-p-spec'))       fd.append('items[' + i + '][specimen]', g('.js-p-spec').value);
            if (g('.js-p-spec-other')) fd.append('items[' + i + '][specimen_other]', g('.js-p-spec-other').value);
            if (g('.js-p-text'))       fd.append('items[' + i + '][text]', g('.js-p-text').value);
            if (g('.js-p-price')) {
                if (g('.js-p-price').validity && g('.js-p-price').validity.badInput) bad.push('แถวที่ ' + (i + 1) + ': ราคาไม่ใช่ตัวเลข');
                fd.append('items[' + i + '][price]', g('.js-p-price').value);
            }
            if (g('.js-p-waive') && g('.js-p-waive').checked) fd.append('items[' + i + '][waive]', '1');
            if (g('.js-p-date'))       fd.append('items[' + i + '][sent_date]', g('.js-p-date').value);
        });
        if (bad.length) { showError('ตรวจสอบราคาก่อน', bad); return; }
        if (ctx.context) fd.append('context', ctx.context);
        if (ctx.tbReg) fd.append('tb_reg', ctx.tbReg);
        fd.append('page', ctx.page || 1);

        var html = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังสั่ง…';
        fetch(ctx.orderUrl, {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, status: r.status, data: j }; }); })
        .then(function (res) {
            btn.innerHTML = html;
            if (res.ok && res.data.ok) {
                $('.js-lcat-pending-body').innerHTML = '';
                refreshCount();
                var r = $('.js-lcat-results');
                r.innerHTML = '<b style="color:#065f46;"><i class="fa fa-circle-check me-1"></i>' + esc(res.data.message) + '</b><ul class="mb-0 small mt-1">'
                    + (res.data.results || []).map(function (x) {
                        return '<li>' + esc(x.name) + ' — ' + esc(x.message) + '</li>';
                    }).join('') + '</ul>';
                r.classList.remove('d-none');
                applyToPage(res.data);
                document.dispatchEvent(new CustomEvent('labcatalog:ordered', { detail: res.data }));
                load(true);   // ให้เห็น "สั่งแล้ว" ของที่เพิ่งสั่ง
                return;
            }
            refreshCount();
            var errs = (res.data && res.data.errors) || {}, list = [];
            Object.keys(errs).forEach(function (k) {
                var tr = rows[+k];
                if (tr) { tr.classList.add('is-bad'); var e = tr.querySelector('.js-p-err'); if (e) e.textContent = [].concat(errs[k])[0]; }
                list.push('แถวที่ ' + (+k + 1) + ': ' + [].concat(errs[k])[0]);
            });
            var msg = (res.data && res.data.message) || (res.status === 419 ? 'หน้าเปิดค้างนานจนหมดอายุ — โหลดหน้าใหม่ (F5) แล้วสั่งอีกครั้ง'
                       : res.status === 403 ? 'ไม่มีสิทธิ์สั่งแล็บ' : 'สั่งไม่สำเร็จ (HTTP ' + res.status + ')');
            showError(msg, list);
        })
        .catch(function () {
            btn.innerHTML = html; refreshCount();
            showError('สั่งไม่สำเร็จ — ตรวจสอบการเชื่อมต่อแล้วลองใหม่ (รายการยังอยู่ กดยืนยันซ้ำได้)');
        });
    }

    // ---------------- อัปเดตส่วนที่เกี่ยวในหน้า ----------------
    function applyToPage(d) {
        // แผง Lab ในคลินิก (หน้าตรวจ TB) — ค่าตั้งต้นใหม่สำหรับเทียบส่วนต่างครั้งถัดไป
        if (d.inhouse_panel) {
            var old = document.querySelector('.js-inh-panel');
            if (old && !modal.contains(old)) {
                var h = document.createElement('div'); h.innerHTML = d.inhouse_panel;
                var fresh = h.querySelector('.js-inh-panel');
                if (fresh) old.parentNode.replaceChild(fresh, old);
            }
        }
        // ตาราง Lab นอกในหน้าตรวจ — ใช้ตัวสลับตารางเดิม (visit/form/external_lab) ค่าที่พิมพ์ค้างในแถวอื่นไม่หาย
        if (typeof d.external_rows === 'string') {
            var box = document.getElementById('ext-lab-box');
            if (box) box.dispatchEvent(new CustomEvent('extlab:saved', { detail: { html: d.external_rows, count: d.external_count, created: d.external_created } }));
        }
        // ช่องส่งตรวจวัณโรคในหน้าตรวจ — ติ๊กให้ตรงกับที่เพิ่งสั่ง ไม่งั้นกด Save หน้าตรวจทีหลังจะเอาค่าเก่าในหน้าไปทับ
        if (d.tb_state && window.TbLabFields && window.TbLabFields.syncFromServer) {
            document.querySelectorAll('.js-tb-lab-fields').forEach(function (root) {
                if (!modal.contains(root)) window.TbLabFields.syncFromServer(root, d.tb_state);
            });
        }
        // กล่อง "สั่งตรวจครั้งนี้" ในบัตร TB 01
        var sum = document.getElementById('tb-lab-order-body');
        if (sum && sum.dataset.summaryUrl) {
            fetch(sum.dataset.summaryUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.text() : null; })
                .then(function (h) { if (h !== null) sum.innerHTML = h; });
        }
    }

    // ---------------- ผูกเหตุการณ์ ----------------
    function init() {
        modal = document.getElementById('labCatalogModal');
        if (!modal) return;
        // อาจถูกวางในฟอร์มหลักของหน้าตรวจ — ย้ายไปต่อกับ body (popup ไม่ควรอยู่ในฟอร์ม และ z-index ไม่โดนตัด)
        if (modal.parentNode !== document.body) document.body.appendChild(modal);

        modal.addEventListener('show.bs.modal', function (e) {
            var b = e.relatedTarget;
            if (!b || !b.dataset || !b.dataset.pickerUrl) return;
            var same = ctx.pickerUrl === b.dataset.pickerUrl;
            ctx = { pickerUrl: b.dataset.pickerUrl, orderUrl: b.dataset.orderUrl, context: b.dataset.context || '',
                    tbReg: b.dataset.tbReg || '', page: b.dataset.page || 1, preset: b.dataset.preset || '', scope: b.dataset.scope || '' };
            if (!same) { $('.js-lcat-pending-body').innerHTML = ''; }
            cat = '';
            $('.js-lcat-search').value = '';
            hideError();
            load(false);
        });
        modal.addEventListener('shown.bs.modal', function () { var s = $('.js-lcat-search'); if (s) s.focus(); });
        modal.addEventListener('hide.bs.modal', function (e) {
            var n = pendingRows().length;
            if (n && !confirm('มี ' + n + ' รายการที่เลือกไว้แต่ยังไม่ได้กด "ยืนยันสั่ง"\n\nปิดแล้วรายการจะยังอยู่ถ้าเปิดใหม่ในหน้านี้ ยืนยันปิดหรือไม่?')) e.preventDefault();
        });

        modal.addEventListener('input', function (e) {
            if (e.target.classList.contains('js-lcat-search')) drawList();
        });
        modal.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target.tagName === 'INPUT') e.preventDefault();
        });
        modal.addEventListener('change', function (e) {
            var t = e.target;
            if (t.classList.contains('js-lcat-tick')) {
                var it = itemById(t.value);
                if (!it) return;
                if (t.checked) { if (!addPending(it)) t.checked = false; }
                else removePending(it.id);
                var lab = t.closest('.lcat-item'); if (lab) lab.classList.toggle('is-on', t.checked);
                return;
            }
            if (t.classList.contains('js-p-spec')) {
                var o = t.parentNode.querySelector('.js-p-spec-other');
                if (o) o.classList.toggle('d-none', !(t.value === '10' || t.value === 'other'));
            }
            if (t.classList.contains('js-p-waive')) {
                var p = t.closest('td').querySelector('.js-p-price');
                if (p) { p.disabled = t.checked; if (t.checked) p.value = '0'; }
            }
        });
        modal.addEventListener('click', function (e) {
            if (e.target.closest('.js-lcat-allscope')) { e.preventDefault(); ctx.scope = ''; cat = ''; drawCats(); drawList(); return; }
            var c = e.target.closest('[data-cat]');
            if (c && modal.contains(c) && c.closest('.js-lcat-cats')) {
                e.preventDefault();
                cat = c.dataset.cat; $('.js-lcat-search').value = '';
                drawCats(); drawList();
                return;
            }
            var rm = e.target.closest('.js-p-remove');
            if (rm) {
                var tr = rm.closest('tr'), id = tr.dataset.id;
                tr.remove(); refreshCount();
                if (!isPending(id)) drawList();
                return;
            }
            var dup = e.target.closest('.js-p-dup');
            if (dup) { var src = dup.closest('tr'), it = itemById(src.dataset.id); if (it) addPending(it, src); return; }
            var ps = e.target.closest('.js-lcat-poc-save');
            if (ps) { savePoc(ps); return; }
            var ok = e.target.closest('.js-lcat-confirm');
            if (ok && !ok.disabled) confirmOrder(ok);
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    window.LabCatalogPicker = { pendingCount: function () { return modal ? pendingRows().length : 0; } };
})();
</script>
@endonce
