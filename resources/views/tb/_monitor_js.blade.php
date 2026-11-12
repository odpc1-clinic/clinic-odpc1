{{-- สคริปต์แท็บ "ติดตาม" ของบัตร TB 01 (2569-10-05) — include ในหน้าที่โหลดเต็มเท่านั้น
     เนื้อในกล่องถูกสลับด้วย innerHTML (สคริปต์ในนั้นไม่รัน) จึงผูกทุกอย่างแบบ delegate ที่ document
     เปิด/ปิด popup ผ่าน data-bs-toggle / ปุ่ม data-bs-dismiss เท่านั้น (ไม่มี window.bootstrap ให้เรียก) --}}
<script>
(function () {
    if (window.__tbMonitorBound) return;
    window.__tbMonitorBound = true;

    function token() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '{{ csrf_token() }}';
    }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // สลับเนื้อในกล่องที่เปลี่ยน + กล่อง "สั่งตรวจครั้งนี้" (มีแถบเตือนของตารางอยู่ข้างใน)
    function applyHtml(html) {
        if (!html) return;
        var g = document.getElementById('tb-monitor-body');
        if (g && typeof html.grid === 'string') g.innerHTML = html.grid;
        var x = document.getElementById('tb-monitor-extra-body');
        if (x && typeof html.extra === 'string') x.innerHTML = html.extra;
        var h = document.getElementById('tb-monitor-header');
        if (h && typeof html.header === 'string') h.outerHTML = html.header;
        var sum = document.getElementById('tb-lab-order-body');
        if (sum && sum.dataset.summaryUrl) {
            fetch(sum.dataset.summaryUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.text() : null; })
                .then(function (t) { if (t !== null) sum.innerHTML = t; });
        }
    }
    function refresh() {
        var g = document.getElementById('tb-monitor-body');
        if (!g || !g.dataset.htmlUrl) return;
        fetch(g.dataset.htmlUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) { if (j && j.ok) applyHtml(j.html); });
    }

    function send(url, fd, errBox, onOk) {
        if (errBox) { errBox.classList.add('d-none'); errBox.textContent = ''; }
        return fetch(url, {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, status: r.status, data: j }; }); })
        .then(function (res) {
            if (res.ok && res.data.ok) { applyHtml(res.data.html); if (onOk) onOk(res.data); return true; }
            var msg = (res.data && res.data.message) || (res.status === 419 ? 'หน้าเปิดค้างนานจนหมดอายุ — โหลดหน้าใหม่ (F5)' : 'บันทึกไม่สำเร็จ (HTTP ' + res.status + ')');
            if (errBox) { errBox.textContent = msg; errBox.classList.remove('d-none'); } else { alert(msg); }
            return false;
        })
        .catch(function () {
            var msg = 'บันทึกไม่สำเร็จ — ตรวจสอบการเชื่อมต่อแล้วลองใหม่';
            if (errBox) { errBox.textContent = msg; errBox.classList.remove('d-none'); } else { alert(msg); }
            return false;
        });
    }
    function closeModal(el) {
        var b = el && el.querySelector('[data-bs-dismiss="modal"]');
        if (b) b.click();
    }

    // ---------- ช่องในตาราง: เติม popup ตามช่องที่กด ----------
    document.addEventListener('show.bs.modal', function (e) {
        var m = e.target;
        if (!m || m.id !== 'tbMonitorTickModal') return;
        var b = e.relatedTarget;
        if (!b || !b.dataset) return;
        var d = b.dataset;
        m.querySelector('.js-tbm-t-check').textContent = d.checkLabel || '';
        m.querySelector('.js-tbm-t-point').textContent = d.pointLabel || '';
        m.querySelector('.js-tbm-t-expected').textContent = d.expected || '—';
        m.querySelector('.js-tbm-t-status').textContent = d.status || '';
        m.querySelector('.js-tbm-t-why').textContent = d.why || '';
        var det = m.querySelector('.js-tbm-t-detail');
        det.textContent = d.detail || '';
        det.classList.toggle('d-none', !d.detail);
        m.querySelector('[name=check]').value = d.check || '';
        m.querySelector('[name=point]').value = d.point || '';
        var auto = d.auto === '1';
        // ระบบดึงผลเองแล้ว = ไม่ต้องติ๊ก (ดูรายละเอียดอย่างเดียว)
        m.querySelector('.js-tbm-t-form').classList.toggle('d-none', auto);
        var save = m.querySelector('.js-tbm-t-save');
        if (save) save.classList.toggle('d-none', auto);
        var dt = m.querySelector('[name=done_date]'), nt = m.querySelector('[name=note]');
        if (dt) dt.value = d.manual === '1' ? (d.mdate || '') : '';
        if (nt) nt.value = d.manual === '1' ? (d.mnote || '') : '';
        // EKG: ช่องตัวเลข QTcF — ค่าที่ติ๊กไว้เก็บเป็น "QTcF 430 ms" ในหมายเหตุ แยกตัวเลขกลับมาใส่ช่อง
        var qBox = m.querySelector('.js-tbm-qtcf'), qIn = m.querySelector('[name=qtcf]');
        if (qBox && qIn) {
            var isQt = d.check === 'qtcf';
            qBox.classList.toggle('d-none', !isQt);
            qIn.required = isQt && !auto;
            qIn.disabled = !isQt;
            var mm = isQt && nt ? nt.value.match(/^QTcF\s+(\d+)\s*ms\s*(?:·\s*)?/i) : null;
            qIn.value = mm ? mm[1] : '';
            if (mm) nt.value = nt.value.slice(mm[0].length);
            qtFlag(m);
        }
        var un = m.querySelector('.js-tbm-untick');
        if (un) un.classList.toggle('d-none', d.manual !== '1');
        var err = m.querySelector('.js-tbm-err'); err.classList.add('d-none');
    });

    // QTcF เกินเกณฑ์ — เกณฑ์เดียวกับสัญญาณชีพหน้าตรวจ (visit/form/vitals)
    function qtFlag(m) {
        var qIn = m.querySelector('[name=qtcf]'), f = m.querySelector('.js-tbm-qtcf-flag');
        if (!qIn || !f) return;
        var v = parseFloat(qIn.value);
        f.className = 'small js-tbm-qtcf-flag ' + (v > 500 ? 'text-danger fw-semibold' : (v >= 450 ? 'text-warning fw-semibold' : ''));
        f.textContent = v > 500 ? 'QTcF เกิน 500 ms — แจ้งแพทย์ก่อนให้ยาต่อ' : (v >= 450 ? 'QTcF ยาว (≥450 ms)' : '');
    }
    document.addEventListener('input', function (e) {
        if (e.target.name === 'qtcf' && e.target.closest('#tbMonitorTickModal')) qtFlag(e.target.closest('#tbMonitorTickModal'));
    });

    // Enter ในช่องพิมพ์ของ popup ไม่ส่งฟอร์ม — เคยกด Enter หลังพิมพ์วันที่ แล้วบันทึกไปทั้งที่ยังไม่ได้กรอก VA (คลินิกแจ้ง 2569-10-27)
    // ต้องกดปุ่ม "บันทึก" เท่านั้น
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || !e.target.closest || e.target.tagName === 'TEXTAREA') return;
        if (e.target.closest('.js-tbm-form') && e.target.tagName === 'INPUT') e.preventDefault();
    });

    // ---------- บันทึก (ติ๊ก / สายตา / ซึมเศร้า) ----------
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f.classList || !f.classList.contains('js-tbm-form')) return;
        e.preventDefault();
        var fd = new FormData(f);
        if (f.closest('#tbDepressionModal')) {
            // ส่วนที่ซ่อนอยู่ไม่ต้องส่ง (ติ๊กไว้แล้วเปลี่ยน 2Q เป็นไม่มี -> 9Q ไม่เกี่ยวแล้ว)
            var hide9 = f.querySelector('.js-dep-9q').classList.contains('d-none');
            var hide8 = f.querySelector('.js-dep-8q').classList.contains('d-none');
            var hide3a = f.querySelector('.js-dep-3a').classList.contains('d-none');
            Array.from(fd.keys()).forEach(function (k) {
                if ((hide9 && k.indexOf('q9[') === 0) || ((hide8 && k.indexOf('q8[') === 0)) || (hide3a && k === 'q8[3a]')) fd.delete(k);
            });
            // 8Q เปิดอยู่แต่ยังไม่ได้ตอบสักข้อ = ไม่มี q8 ในคำขอ -> เซิร์ฟเวอร์บันทึกได้ และหมายเหตุว่า "ยังไม่ได้ทำ 8Q"
        }
        var btn = f.querySelector('[type=submit]');
        if (btn) btn.disabled = true;
        send(f.dataset.url, fd, f.querySelector('.js-tbm-err'), function () {
            if (!f.closest('#tbMonitorTickModal')) {
                var keepDate = f.querySelector('.tb-date');
                var dv = keepDate ? keepDate.value : '';
                f.reset();
                if (keepDate) keepDate.value = dv;
                depEval();
            }
            closeModal(f);
        }).then(function () { if (btn) btn.disabled = false; });
    });

    // ---------- กำหนดตารางเอง (สูตร Individualized) ----------
    function planPoints(n) {
        var p = [['d0', 'ด.0']];
        for (var w = 1; w <= 4; w++) p.push(['w' + w, 'สป.' + w]);
        for (var m = 1; m <= n; m++) p.push(['m' + m, 'ด.' + m + (m === n ? ' (จบ)' : '')]);
        [6, 12, 18, 24].forEach(function (k) { p.push(['p' + k, 'หลังจบ ' + k + ' ด.']); });
        return p;
    }
    function planDraw(f, keep) {
        var cat = JSON.parse(f.dataset.cat || '[]'), cur = keep || JSON.parse(f.dataset.cur || '{}');
        var pts = planPoints(parseInt(f.querySelector('.js-tbm-plan-months').value, 10) || 6);
        var h = '<table class="table table-sm table-bordered align-middle mb-0" style="font-size:.85rem;"><thead class="table-light"><tr><th style="min-width:14rem;">รายการตรวจ</th>';
        pts.forEach(function (p) { h += '<th class="text-center" style="min-width:3.2rem;">' + p[1] + '</th>'; });
        h += '</tr></thead><tbody>';
        cat.forEach(function (c) {
            var on = cur[c.key] || [];
            h += '<tr><td><div class="fw-semibold">' + c.label.replace(/</g, '&lt;') + '</div>' + (c.sub ? '<div class="small text-muted">' + c.sub.replace(/</g, '&lt;') + '</div>' : '') + '</td>';
            pts.forEach(function (p) {
                h += '<td class="text-center"><input type="checkbox" class="form-check-input" name="cells[' + c.key + '][]" value="' + p[0] + '"' + (on.indexOf(p[0]) !== -1 ? ' checked' : '') + '></td>';
            });
            h += '</tr>';
        });
        f.querySelector('.js-tbm-plan-grid').innerHTML = h + '</tbody></table>';
    }
    function planRead(f) {
        var out = {};
        f.querySelectorAll('.js-tbm-plan-grid input:checked').forEach(function (i) {
            var k = i.name.replace(/^cells\[|\]\[\]$/g, '');
            (out[k] = out[k] || []).push(i.value);
        });
        return out;
    }
    document.addEventListener('show.bs.modal', function (e) {
        if (e.target && e.target.id === 'tbMonitorPlanModal') planDraw(e.target.querySelector('form'));
    });
    document.addEventListener('change', function (e) {
        if (e.target.classList && e.target.classList.contains('js-tbm-plan-months')) { var f = e.target.closest('form'); planDraw(f, planRead(f)); }
    });
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f.classList || !f.classList.contains('js-tbm-plan-form')) return;
        e.preventDefault();
        var btn = f.querySelector('[type=submit]'); btn.disabled = true;
        send(f.dataset.url, new FormData(f), f.querySelector('.js-tbm-err'), function () {
            f.dataset.cur = JSON.stringify(planRead(f));   // เปิดใหม่รอบหน้าเห็นค่าที่เพิ่งบันทึก
            closeModal(f);
        }).then(function () { btn.disabled = false; });
    });

    document.addEventListener('click', function (e) {
        // กลับไปใช้ตารางมาตรฐาน
        var rs = e.target.closest && e.target.closest('.js-tbm-plan-reset');
        if (rs) {
            if (!confirm('ล้างตารางที่กำหนดเอง แล้วใช้ตารางมาตรฐาน?')) return;
            var fr = new FormData(); fr.append('_method', 'DELETE');
            send(rs.dataset.url, fr, null);
            return;
        }
        // เอาติ๊กมือออก
        var un = e.target.closest && e.target.closest('.js-tbm-untick');
        if (un) {
            var f = un.closest('form');
            var fd = new FormData();
            fd.append('_method', 'DELETE');
            fd.append('check', f.querySelector('[name=check]').value);
            fd.append('point', f.querySelector('[name=point]').value);
            send(un.dataset.url, fd, f.querySelector('.js-tbm-err'), function () { closeModal(f); });
            return;
        }
        // ลบผลตรวจสายตา / ซึมเศร้า
        var del = e.target.closest && e.target.closest('.js-tbm-del');
        if (del) {
            if (!confirm('ลบผลนี้?')) return;
            var fd2 = new FormData();
            fd2.append('_method', 'DELETE');
            send(del.dataset.url, fd2, null);
            return;
        }
        // ลิงก์ "ดูตาราง" — เปิดแท็บติดตาม (กดซ้ำตอน hash เป็น #tb-monitor อยู่แล้ว hashchange ไม่ยิง)
        var a = e.target.closest && e.target.closest('a[href="#tb-monitor"]');
        if (a) {
            var tab = document.querySelector('.js-tb-tab[data-tab="monitor"]');
            if (tab) {
                e.preventDefault();
                tab.click();
                var box = document.getElementById('tb-monitor');
                if (box) setTimeout(function () { box.scrollIntoView({ block: 'start' }); }, 50);
            }
        }
    });

    // สั่งแล็บจากตัวเลือกแคตตาล็อกแล้ว -> ตารางนับว่าสั่งแล้ว
    document.addEventListener('labcatalog:ordered', refresh);

    // ---------- คิดคะแนน 2Q / 9Q / 8Q สด ๆ (เซิร์ฟเวอร์คิดซ้ำอีกรอบตอนบันทึก) ----------
    function val(f, name) {
        var r = f.querySelector('input[name="' + name + '"]:checked');
        return r ? parseInt(r.value, 10) : null;
    }
    function q9Level(s) { return s < 7 ? 'ไม่มีอาการ/น้อยมาก' : s <= 12 ? 'ระดับน้อย' : s <= 18 ? 'ระดับปานกลาง' : 'ระดับรุนแรง'; }
    function q8Level(s) { return s === 0 ? 'ไม่มีแนวโน้ม' : s <= 8 ? 'ระดับน้อย' : s <= 16 ? 'ระดับปานกลาง' : 'ระดับรุนแรง'; }
    var Q8PTS = {!! json_encode(array_map(fn ($x) => $x[1], \App\Services\TbMonitor\DepressionScale::Q8)) !!};
    function depEval() {
        var m = document.getElementById('tbDepressionModal');
        if (!m) return;
        var f = m.querySelector('form');
        var a = val(f, 'q2[1]'), b = val(f, 'q2[2]');
        var out = [];
        var pos = a === 1 || b === 1;
        m.querySelector('.js-dep-9q').classList.toggle('d-none', !pos);
        var show8 = false;
        if (a === null || b === null) {
            out.push('ตอบ 2Q ทั้ง 2 ข้อ');
        } else if (!pos) {
            out.push('2Q ลบ — ไม่เป็นโรคซึมเศร้า ไม่ต้องทำ 9Q');
        } else {
            out.push('2Q บวก — ทำ 9Q ต่อ');
            var s = 0, n = 0;
            for (var i = 1; i <= 9; i++) { var v = val(f, 'q9[' + i + ']'); if (v !== null) { s += v; n++; } }
            if (n) out.push('9Q = ' + s + (n < 9 ? ' (ตอบ ' + n + '/9)' : ' ' + q9Level(s)));
            show8 = s >= 7 || (val(f, 'q9[9]') || 0) > 0;
            if (show8) {
                out.push('ทำ 8Q ต่อ');
                var s8 = 0, n8 = 0;
                Object.keys(Q8PTS).forEach(function (k) {
                    if (k === '3a') return;
                    var v8 = val(f, 'q8[' + k + ']');
                    if (v8 !== null) { n8++; if (v8) s8 += Q8PTS[k]; }
                });
                var q3 = val(f, 'q8[3]') === 1;
                m.querySelector('.js-dep-3a').classList.toggle('d-none', !q3);
                if (q3 && val(f, 'q8[3a]') === 1) s8 += Q8PTS['3a'];
                if (n8) out.push('8Q = ' + s8 + ' ' + q8Level(s8));
                if (s8 >= 1 || s >= 13) out.push('⚠ ควรส่งต่อพบแพทย์');
            }
        }
        m.querySelector('.js-dep-8q').classList.toggle('d-none', !show8);
        m.querySelector('.js-dep-result').textContent = out.join(' · ');
    }
    document.addEventListener('change', function (e) {
        if (e.target.classList && e.target.classList.contains('js-dep')) depEval();
    });
})();
</script>
