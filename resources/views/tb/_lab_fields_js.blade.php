{{-- สคริปต์ของ tb/_lab_fields (ส่วนส่งตรวจทางห้องปฏิบัติการวัณโรค) — ใช้ทั้งหน้าตรวจ OPD และ popup ในบัตร TB 01
     ต้อง include ในหน้าที่โหลดเต็มเท่านั้น (สคริปต์ใน innerHTML ไม่รัน — CLAUDE.md ข้อ 2)
     ผูกแบบ delegate ที่ document ทั้งหมด เพราะในบัตร TB 01 ช่องถูกโหลดเข้า popup ทีหลัง
     ช่องที่โหลดทีหลังให้เรียก TbLabFields.init(root) เอง (หน้าตรวจ OPD ทำให้อัตโนมัติตอนโหลดหน้า) --}}
<script>
(function () {
    if (window.TbLabFields) return;

    function setOn(el, on, why) {
        el.disabled = ! on;
        el.style.opacity = on ? '' : '.55';
        el.title = on ? '' : why;
    }

    // ---- ช่องผลของแต่ละวิธีตรวจ กรอกได้ก็ต่อเมื่อติ๊กวิธีนั้นแล้ว (คลินิกขอ 2569-09-29)
    //      ข้อ 2 ทั้งข้อเลือกได้ก็ต่อเมื่อเลือกสิ่งส่งตรวจในข้อ 1 อย่างน้อยหนึ่งแถว
    //      ปิดไว้ด้วย disabled จะได้ไม่ถูกส่งไปบันทึก — ฝั่งเซิร์ฟเวอร์ตรวจกติกาเดียวกันซ้ำอีกชั้น
    function apply(root) {
        var sec2 = root.querySelector('[data-lab-group="sec2"]');
        var hint = root.querySelector('.js-sec2-hint');
        var gate = Array.prototype.some.call(root.querySelectorAll('.js-spec-type'), function (s) { return s.value !== ''; });
        if (sec2) {
            sec2.querySelectorAll('input, select, textarea').forEach(function (el) {
                if (! el.hasAttribute('data-dep')) { setOn(el, gate, 'เลือกสิ่งส่งตรวจในข้อ 1 ก่อน'); }
            });
            if (hint) { hint.style.display = gate ? 'none' : ''; }
        }
        root.querySelectorAll('[data-dep]').forEach(function (el) {
            var box    = root.querySelector('#' + el.dataset.dep);
            var inSec2 = sec2 && sec2.contains(el);
            var on     = box && box.checked && (! inSec2 || gate);
            setOn(el, on, inSec2 && ! gate ? 'เลือกสิ่งส่งตรวจในข้อ 1 ก่อน' : 'ติ๊กหัวข้อด้านบนก่อนถึงจะกรอกได้');
        });
    }

    function rootOf(el) { return el && el.closest ? el.closest('.js-tb-lab-fields') : null; }

    document.addEventListener('change', function (e) {
        var root = rootOf(e.target), t = e.target;
        if (!root) return;
        if ((t.id && t.id.indexOf('tb_') === 0) || t.classList.contains('js-spec-type')) { apply(root); }
    });

    // ---- เพิ่ม / ลบ แถวสิ่งส่งตรวจ ----
    document.addEventListener('click', function (e) {
        var root = rootOf(e.target);
        if (!root) return;
        var rows = root.querySelector('.js-spec-rows');
        if (!rows) return;

        if (e.target.closest('.js-spec-add')) {
            var tpl = rows.querySelector('.tb-spec-row');
            if (! tpl) { return; }
            var idx = parseInt(rows.getAttribute('data-next') || '0', 10);
            rows.setAttribute('data-next', idx + 1);
            var row = tpl.cloneNode(true);
            row.querySelectorAll('select, input').forEach(function (el) {
                el.name = el.name.replace(/\[lab_specimens\]\[\d+\]/, '[lab_specimens][' + idx + ']');
                if (el.tagName === 'SELECT') { el.selectedIndex = 0; } else { el.value = ''; }
                el.classList.remove('hasDatepicker', 'is-invalid');
            });
            row.querySelectorAll('.invalid-feedback').forEach(function (n) { n.remove(); });
            rows.appendChild(row);
            // ช่องวันที่ของแถวใหม่ต้องผูกปฏิทินเอง — ผ่าน bindThDatepicker (รอไฟล์ภาษา th-th ก่อน ไม่งั้นได้ปฏิทิน ค.ศ.)
            var d = row.querySelector('input.date');
            if (d && window.bindThDatepicker) { window.bindThDatepicker(d, { autoclose: true, todayHighlight: true }); }
            apply(root);
            return;
        }

        var del = e.target.closest('.js-spec-del');
        if (del) {
            var r = del.closest('.tb-spec-row');
            // เหลือแถวเดียวไม่ลบทิ้ง แค่ล้างค่า — ต้องมีแถวต้นแบบไว้ให้ปุ่ม "เพิ่ม" โคลน
            if (rows.querySelectorAll('.tb-spec-row').length <= 1) {
                r.querySelectorAll('select, input').forEach(function (el) {
                    if (el.tagName === 'SELECT') { el.selectedIndex = 0; } else { el.value = ''; }
                });
            } else {
                r.parentNode.removeChild(r);
            }
            apply(root);
        }
    });

    // ---- ส่งเฉพาะกลุ่มที่แก้ (2569-10-02) ----
    // หน้าตรวจ OPD กับบัตร TB 01 เปิดค้างพร้อมกันได้ ถ้าส่งทุกกลุ่ม ค่าเก่าในหน้าหนึ่งจะไปทับผลที่อีกหน้าเพิ่งลง
    // จำค่าแต่ละกลุ่มตอนเปิด แล้วตอนบันทึกปิด (disabled) กลุ่มที่ไม่ได้แก้ -> ไม่ถูกส่ง -> เซิร์ฟเวอร์ไม่แตะ (TbScreening::fillLabInput)
    function groupValue(g) {
        var out = [];
        g.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (!el.name || el.disabled) return;
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
            out.push(el.name.replace(/\[lab_specimens\]\[\d+\]/, '[lab_specimens][]') + '=' + el.value);
        });
        return out.join('\n');
    }
    function snapshot(root) {
        root.__labSnap = {};
        root.querySelectorAll('[data-lab-group]').forEach(function (g) { root.__labSnap[g.dataset.labGroup] = groupValue(g); });
    }
    // ปิดกลุ่มที่ไม่ได้แก้ -> คืนฟังก์ชันเปิดคืน (ต้องเปิดคืนเสมอ เผื่อการส่งถูกยกเลิก ช่องจะได้ไม่ค้างเป็นกรอกไม่ได้)
    function trim(root) {
        var off = [];
        if (root.__labSnap) {
            root.querySelectorAll('[data-lab-group]').forEach(function (g) {
                if (groupValue(g) !== root.__labSnap[g.dataset.labGroup]) return;   // กลุ่มนี้แก้ -> ส่ง
                g.querySelectorAll('input, select, textarea').forEach(function (el) {
                    if (el.name && !el.disabled) { el.disabled = true; off.push(el); }
                });
            });
        }
        return function () { off.forEach(function (el) { el.disabled = false; }); };
    }

    // ฟอร์มธรรมดา (หน้าตรวจ OPD): ปิดกลุ่มตอน submit — ทำหลังสคริปต์ _touched ของหน้าตรวจ (มันผูกแบบ capture ที่ฟอร์ม)
    // ฟอร์มที่ส่งด้วย fetch (popup ในบัตร) จัดการเองใน tb/_lab_order_js — ข้าม
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || e.defaultPrevented || form.hasAttribute('data-lab-ajax')) return;
        form.querySelectorAll('.js-tb-lab-fields').forEach(function (root) {
            var restore = trim(root);
            setTimeout(restore, 0);
        });
    });

    // ---- ตัวเลือก "สั่งแล็บ" สั่งส่งตรวจวัณโรคไปแล้วทางเซิร์ฟเวอร์ (2569-10-02) ----
    // ติ๊กช่องในหน้าให้ตรงกับฐาน (เพิ่มอย่างเดียว ไม่เอาติ๊กออก) ไม่งั้นกด Save หน้าตรวจทีหลัง ค่าเก่าในหน้าจะทับที่เพิ่งสั่ง
    // กลุ่มที่ยังไม่ได้แก้ในหน้า = จำค่าใหม่เป็นค่าตั้งต้น (Save แล้วไม่ส่งกลุ่มนั้น) · กลุ่มที่แก้ค้างอยู่ = ไม่จำ
    // (Save จะส่งทั้งกลุ่มซึ่งตอนนี้รวมที่เพิ่งสั่งแล้ว — งานที่พิมพ์ค้างไม่หาย และไม่ทับของใหม่)
    function syncFromServer(root, st) {
        if (!root || !st) return;
        var clean = {};
        root.querySelectorAll('[data-lab-group]').forEach(function (g) {
            clean[g.dataset.labGroup] = !root.__labSnap || groupValue(g) === root.__labSnap[g.dataset.labGroup];
        });
        var tick = function (sel) { var el = root.querySelector(sel); if (el && !el.checked) el.checked = true; };

        var rows = root.querySelector('.js-spec-rows');
        (st.specimens || []).forEach(function (sp) {
            if (!rows || !sp.type) return;
            var have = Array.prototype.some.call(rows.querySelectorAll('.js-spec-type'), function (s) { return s.value === sp.type; });
            if (have) return;
            var empty = Array.prototype.filter.call(rows.querySelectorAll('.tb-spec-row'), function (r) {
                var s = r.querySelector('.js-spec-type'); return s && s.value === '';
            })[0];
            if (!empty) {
                var add = root.querySelector('.js-spec-add');
                if (add) add.click();
                var all = rows.querySelectorAll('.tb-spec-row');
                empty = all[all.length - 1];
            }
            if (!empty) return;
            empty.querySelector('.js-spec-type').value = sp.type;
            var other = empty.querySelector('input[name$="[other]"]'); if (other) other.value = sp.other || '';
            var date = empty.querySelector('input[name$="[date]"]');  if (date) date.value = sp.date || '';
        });
        var m = st.methods || [];
        if (m.indexOf('afb') !== -1) tick('#tb_lab_afb');
        if (m.indexOf('molecular') !== -1) tick('#tb_lab_mol');
        if (m.indexOf('culture_dst') !== -1) tick('#tb_lab_cul');
        (st.molecular || []).forEach(function (k) { tick('#tb_mol_' + k); });
        if (st.tst) tick('#tb_tst');
        if (st.cytb) tick('#tb_cytb');
        if (st.igra) tick('#tb_igra');
        apply(root);

        if (root.__labSnap) {
            root.querySelectorAll('[data-lab-group]').forEach(function (g) {
                if (clean[g.dataset.labGroup]) root.__labSnap[g.dataset.labGroup] = groupValue(g);
            });
        }
    }

    window.TbLabFields = {
        apply: apply,
        snapshot: snapshot,
        trim: trim,
        syncFromServer: syncFromServer,
        // ช่องที่โหลดเข้ามาทีหลัง (popup) — เปิด/ปิดช่องตามที่ติ๊ก แล้วจำค่าเริ่มต้นทันที
        init: function (root) { apply(root); snapshot(root); }
    };

    // ผลดื้อยา INH / RIF (2569-10-28): เลือกแล้วเขียนต่อท้ายช่องผล Molecular "· INH: xxx · RIF: xxx" (ช่องผลคือตัวที่บันทึก)
    // ผูกที่ document — ฟอร์มใน popup โหลดทีหลังก็ใช้ได้
    if (!window.__molResInit) {
        window.__molResInit = true;
        document.addEventListener('change', function (e) {
            var sel = e.target.closest && e.target.closest('.js-mol-res-sel');
            if (!sel) return;
            var wrap = sel.closest('.js-mol-res'), col = wrap && wrap.parentNode;
            var txt = col && col.querySelector('input[name$="[lab_molecular_result]"]');
            if (!txt) return;
            var base = txt.value.replace(/\s*[·,;]?\s*(Level|INH|RIF|FLQ|AG\/CP):\s*(Susceptible|Resistant|Resistance|Inconclusive|Indeterminate|Very low|Low|Medium|High|Trace)(?![A-Za-z])/gi, '')
                                .replace(/\s*[·,;]?\s*RIF resistance:\s*(Not detected|Detected|Indeterminate)(?![A-Za-z])/gi, '').trim();
            var add = [];
            wrap.querySelectorAll('.js-mol-res-sel').forEach(function (s) { if (s.value) add.push(s.dataset.drug + ': ' + s.value); });
            txt.value = [base].concat(add).filter(function (x) { return x; }).join(' · ');
            txt.dispatchEvent(new Event('input', { bubbles: true }));
            txt.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    // ช่องที่มากับหน้า (หน้าตรวจ OPD) — จำค่าหลังปฏิทิน/สคริปต์อื่นตั้งค่าเสร็จ เหมือน _touched ของหน้าตรวจ
    document.querySelectorAll('.js-tb-lab-fields').forEach(apply);
    window.addEventListener('load', function () {
        setTimeout(function () { document.querySelectorAll('.js-tb-lab-fields').forEach(snapshot); }, 400);
    });
})();
</script>
