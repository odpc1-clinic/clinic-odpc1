{{-- ตัวจัดการ "เลือก Lab นอกหลายรายการแล้วยืนยันทีเดียว" (visit/form/_external_lab_picker) — 2569-10-02
     ต้องอยู่ในหน้าที่โหลดเต็ม: ตัวเลือกใน popup บัตร TB 01 ถูกโหลดด้วย fetch (สคริปต์ที่มากับ innerHTML ไม่รัน)
     ผูกแบบ delegate ที่ document ทั้งหมด — ใช้ได้ทั้งตัวที่เรนเดอร์มากับหน้าและตัวที่โหลดทีหลัง
     บันทึกด้วย fetch (ไม่เปลี่ยนหน้า) แล้วยิง event "extlab:saved" จากตัวเลือก ให้หน้าที่ใช้ไปรีเฟรชตารางของตัวเอง --}}
<script>
(function () {
    if (window.ExtLabPicker) return;

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
    function rootOf(el) { return el && el.closest ? el.closest('.js-ext-picker') : null; }
    function pendingRows(root) { return root.querySelectorAll('.js-ext-pending-body tr[data-test]'); }

    function refreshCount(root) {
        var n = pendingRows(root).length;
        root.querySelectorAll('.js-ext-pending-n').forEach(function (s) { s.textContent = n; });
        root.querySelector('.js-ext-pending').classList.toggle('d-none', n === 0);
        if (n === 0) hideError(root);
    }
    function hideError(root) {
        var b = root.querySelector('.js-ext-pick-error');
        if (b) { b.classList.add('d-none'); b.innerHTML = ''; }
    }
    function showError(root, msg, list) {
        var b = root.querySelector('.js-ext-pick-error');
        if (!b) { alert(msg); return; }
        b.innerHTML = '<b><i class="fas fa-triangle-exclamation me-1"></i>' + esc(msg) + '</b>'
            + (list && list.length ? '<ul class="mb-0 small mt-1">' + list.map(function (m) { return '<li>' + esc(m) + '</li>'; }).join('') + '</ul>' : '');
        b.classList.remove('d-none');
    }
    function failMessage(status) {
        if (status === 419) return 'หน้าเปิดค้างนานจนหมดอายุ — โหลดหน้าใหม่ (F5) แล้วสั่งอีกครั้ง';
        if (status === 403) return 'ไม่มีสิทธิ์บันทึกส่วนนี้';
        return 'บันทึกไม่สำเร็จ (HTTP ' + status + ') ลองใหม่อีกครั้ง';
    }

    // ราคาอ้างอิง + เตือน 2 ชั้น ของรายการที่เลือกอยู่ใน dropdown
    function showHints(root) {
        var sel = root.querySelector('.js-ext-pick');
        var o = sel && sel.selectedIndex > 0 ? sel.options[sel.selectedIndex] : null;
        var hint = root.querySelector('.js-ext-ref');
        var p = o ? o.getAttribute('data-price') : null;
        if (hint) {
            if (p) {
                hint.textContent = 'ราคาตามใบเสนอราคา ' + (o.getAttribute('data-vendor') || 'N Health') + ': ' + Number(p).toLocaleString() + '.-  (ราคาที่เก็บคนไข้กรอกเองได้ตามจริง)';
                hint.classList.remove('d-none');
            } else { hint.classList.add('d-none'); }
        }
        // (1) รายการนี้ห้องแล็บเราตรวจเองได้ = ส่งนอกเสียเงินฟรี
        var inh = o ? o.getAttribute('data-inhouse') : null, wIn = root.querySelector('.js-ext-warn-inhouse');
        if (wIn) {
            if (inh) { root.querySelector('.js-ext-warn-inhouse-name').textContent = inh; wIn.classList.remove('d-none'); }
            else { wIn.classList.add('d-none'); }
        }
        // (2) ชื่อคล้ายกับอีกรายการ = เคยหยิบสลับกันมาแล้ว
        var cf = o ? o.getAttribute('data-confuse') : null, wCf = root.querySelector('.js-ext-warn-confuse');
        if (wCf) {
            if (cf) {
                root.querySelector('.js-ext-warn-confuse-name').textContent = cf;
                root.querySelector('.js-ext-warn-confuse-note').textContent = o.getAttribute('data-confuse-note') || '';
                wCf.classList.remove('d-none');
            } else { wCf.classList.add('d-none'); }
        }
    }

    function filter(root) {
        var input = root.querySelector('.js-ext-search');
        var sel = root.querySelector('.js-ext-pick');
        var cnt = root.querySelector('.js-ext-count');
        if (!input || !sel) return;
        var q = input.value.trim().toLowerCase(), shown = 0, only = null;
        sel.querySelectorAll('option[data-search]').forEach(function (o) {
            var hit = !q || o.getAttribute('data-search').indexOf(q) !== -1;
            o.hidden = !hit;       // hidden ใช้ได้กับ option ในเบราว์เซอร์ปัจจุบัน
            o.disabled = !hit;     // กันเลือกด้วยคีย์บอร์ดในบางเบราว์เซอร์
            if (hit) { shown++; only = o; }
        });
        // ถ้าเหลือตัวเดียว เลือกให้เลย จะได้ไม่ต้องกดซ้ำ (ยังไม่ใส่ในรายการจนกว่าจะกดปุ่ม)
        if (q && shown === 1 && only) { sel.value = only.value; }
        else if (sel.selectedIndex > 0 && sel.options[sel.selectedIndex].hidden) { sel.selectedIndex = 0; }
        if (cnt) cnt.textContent = q ? ('พบ ' + shown + ' รายการ') : '';
        sel.size = (q && shown > 1) ? Math.min(shown + 1, 8) : 1;   // กางให้เห็นผลค้นหา
        showHints(root);
    }

    function resetPick(root) {
        var input = root.querySelector('.js-ext-search');
        if (input) input.value = '';
        var sel = root.querySelector('.js-ext-pick');
        if (sel) sel.selectedIndex = 0;
        filter(root);
        if (input) { try { input.focus({ preventScroll: true }); } catch (e) { input.focus(); } }
    }

    function bindDate(el) {
        if (window.bindThDatepicker) window.bindThDatepicker(el, { autoclose: true, todayHighlight: true });
    }

    // ใส่รายการที่เลือกลงตาราง "รายการที่จะสั่ง" (ยังไม่บันทึก)
    function addPending(root) {
        var sel = root.querySelector('.js-ext-pick');
        if (!sel || !sel.value || sel.selectedIndex < 1) { alert('กรุณาเลือกรายการตรวจก่อน'); return; }
        var o = sel.options[sel.selectedIndex];
        var id = o.value, name = (o.textContent || '').trim();

        var dup = Array.prototype.some.call(pendingRows(root), function (tr) { return tr.dataset.test === id; });
        if (dup) { alert('รายการนี้อยู่ในรายการที่จะสั่งแล้ว'); return; }

        // ด่านเตือนเดิม ย้ายมาไว้ตอนหยิบเข้ารายการ — เคยสั่งผิดจนคลินิกต้องจ่ายค่าแล็บนอกเอง
        var inh = o.getAttribute('data-inhouse'), cf = o.getAttribute('data-confuse');
        if (inh) {
            if (!confirm('⚠ ห้องแล็บเราตรวจ "' + inh + '" เองได้\n\nรายการที่กำลังจะสั่ง:\n' + name
                       + '\n\nส่งออกนอกจะมีค่าใช้จ่ายที่อาจไม่จำเป็น ยืนยันจะส่งจริงหรือไม่?')) return;
        } else if (cf) {
            if (!confirm('⚠ รายการนี้ชื่อคล้ายกับ "' + cf + '"\n\nกำลังจะสั่ง:\n' + name
                       + '\n\nตรวจสอบให้แน่ใจว่าเป็นตัวที่ต้องการ ยืนยันหรือไม่?')) return;
        }

        // วันที่ส่งตรวจตั้งต้น = วันที่ของรายการก่อนหน้า (ส่วนใหญ่ส่งพร้อมกัน ไม่ต้องกรอกซ้ำ)
        var rows = pendingRows(root), lastDate = '';
        if (rows.length) { var d = rows[rows.length - 1].querySelector('.js-ext-p-sent'); lastDate = d ? d.value : ''; }

        var ref = o.getAttribute('data-price');
        var badge = inh ? ' <span class="badge bg-danger">ตรวจในคลินิกได้</span>'
                  : (cf ? ' <span class="badge bg-warning text-dark">ชื่อคล้าย ' + esc(cf) + '</span>' : '');
        var tr = document.createElement('tr');
        tr.dataset.test = id;
        tr.innerHTML =
              '<td><div class="fw-semibold">' + esc(name) + badge + '</div>'
            + (ref ? '<div class="small text-muted">ราคาตามใบเสนอราคา ' + esc(Number(ref).toLocaleString()) + '.-</div>' : '') + '</td>'
            + '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm js-ext-p-price" placeholder="กรอกราคา"></td>'
            + '<td><input type="text" class="form-control form-control-sm date js-ext-p-sent" placeholder="ว/ด/ป" autocomplete="off" value="' + esc(lastDate) + '"></td>'
            + '<td class="text-end"><button type="button" class="btn btn-outline-secondary btn-sm js-ext-pending-remove" title="เอาออกจากรายการ">'
            + '<i class="fa fa-times"></i></button></td>';
        root.querySelector('.js-ext-pending-body').appendChild(tr);
        bindDate(tr.querySelector('.js-ext-p-sent'));
        hideError(root);
        refreshCount(root);
        resetPick(root);
    }

    function confirmOrder(root, btn) {
        var rows = pendingRows(root);
        if (!rows.length) return;
        hideError(root);

        var fd = new FormData();
        var bad = [];
        Array.prototype.forEach.call(rows, function (tr, i) {
            var price = tr.querySelector('.js-ext-p-price');
            // ช่อง number ที่พิมพ์ไม่ใช่ตัวเลข value จะว่าง — ต้องจับเอง ไม่งั้นบันทึกเป็นไม่มีราคาเงียบ ๆ
            if (price && price.validity && price.validity.badInput) bad.push('แถวที่ ' + (i + 1) + ': ราคาไม่ใช่ตัวเลข');
            fd.append('items[' + i + '][external_lab_test_id]', tr.dataset.test);
            fd.append('items[' + i + '][price]', price ? price.value : '');
            fd.append('items[' + i + '][sent_date]', (tr.querySelector('.js-ext-p-sent') || {}).value || '');
        });
        if (bad.length) { showError(root, 'ตรวจสอบราคาก่อน', bad); return; }
        if (root.dataset.fragment) fd.append('fragment', root.dataset.fragment);
        fd.append('page', root.dataset.page || 1);

        var html = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังบันทึก…';
        fetch(root.dataset.url, {
            method: 'POST', credentials: 'same-origin', body: fd,
            headers: { 'X-CSRF-TOKEN': token(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, status: r.status, data: j }; }); })
        .then(function (res) {
            btn.disabled = false; btn.innerHTML = html;
            if (res.ok && res.data.ok) {
                root.querySelector('.js-ext-pending-body').innerHTML = '';
                refreshCount(root);
                root.dispatchEvent(new CustomEvent('extlab:saved', { bubbles: true, detail: res.data }));
                return;
            }
            var errs = (res.data && res.data.errors) || {}, list = [];
            Object.keys(errs).forEach(function (k) {
                var m = /^items\.(\d+)\./.exec(k);
                list.push((m ? 'แถวที่ ' + (+m[1] + 1) + ': ' : '') + [].concat(errs[k])[0]);
            });
            // 422 ของ Laravel ข้อความหัวเป็นภาษาอังกฤษ ("The given data was invalid.") -> ใช้หัวภาษาไทยเอง รายละเอียดอยู่ในรายการ
            showError(root, res.status === 422 ? 'ยังสั่งไม่ได้ — ตรวจสอบรายการก่อน (ยังไม่มีรายการไหนถูกบันทึก)' : failMessage(res.status), list);
        })
        .catch(function () {
            btn.disabled = false; btn.innerHTML = html;
            showError(root, 'บันทึกไม่สำเร็จ — ตรวจสอบการเชื่อมต่อแล้วลองใหม่ (รายการยังอยู่ กดยืนยันซ้ำได้)');
        });
    }

    document.addEventListener('input', function (e) {
        if (!e.target.classList || !e.target.classList.contains('js-ext-search')) return;
        var root = rootOf(e.target);
        if (root) filter(root);
    });
    document.addEventListener('change', function (e) {
        if (!e.target.classList || !e.target.classList.contains('js-ext-pick')) return;
        var root = rootOf(e.target);
        if (root) showHints(root);
    });
    // ดับเบิลคลิกรายการในผลค้นหา = ใส่ในรายการที่จะสั่ง (เลือกหลายตัวต่อกันได้เร็ว)
    document.addEventListener('dblclick', function (e) {
        var root = rootOf(e.target);
        if (!root || !e.target.closest('.js-ext-pick')) return;
        var sel = root.querySelector('.js-ext-pick');
        if (sel && sel.value) addPending(root);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        var root = rootOf(e.target);
        if (!root || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'BUTTON') return;
        // Enter ในช่องของตัวเลือก ห้ามไปกดส่งฟอร์มหลักของหน้าตรวจ (ตัวเลือกอยู่ในฟอร์มนั้น)
        e.preventDefault();
        // Enter ที่ dropdown = ใส่ในรายการ · ที่ช่องค้นหา = แค่ค้นหา ไม่หยิบให้เอง
        if (e.target.classList.contains('js-ext-pick') && e.target.value) addPending(root);
    });
    document.addEventListener('click', function (e) {
        var root = rootOf(e.target);
        if (!root) return;
        if (e.target.closest('.js-ext-add-pending')) { addPending(root); return; }
        var rm = e.target.closest('.js-ext-pending-remove');
        if (rm) { rm.closest('tr').remove(); refreshCount(root); return; }
        if (e.target.closest('.js-ext-pending-clear')) {
            if (pendingRows(root).length && !confirm('ล้างรายการที่ยังไม่ได้สั่งทั้งหมด?')) return;
            root.querySelector('.js-ext-pending-body').innerHTML = '';
            refreshCount(root);
            return;
        }
        var ok = e.target.closest('.js-ext-confirm');
        if (ok && !ok.disabled) confirmOrder(root, ok);
    });

    window.ExtLabPicker = {
        // จำนวนรายการที่หยิบไว้แต่ยังไม่ได้ยืนยันสั่ง (ใช้เตือนก่อนออกจากหน้า)
        pendingCount: function (scope) {
            var n = 0;
            (scope || document).querySelectorAll('.js-ext-picker').forEach(function (r) { n += pendingRows(r).length; });
            return n;
        }
    };
})();
</script>
