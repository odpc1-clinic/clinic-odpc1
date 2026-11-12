{{-- ตัวจัดการแผง "Lab ในคลินิก" ของคลินิก TB (tb/_inhouse_lab_panel) — หน้าตรวจ TB และ popup ในบัตร TB 01 (2569-10-02)
     ต้องอยู่ในหน้าที่โหลดเต็ม ผูกแบบ delegate ที่ document (แผงในบัตรถูกโหลดเข้า popup ทีหลัง สคริปต์ในนั้นไม่รัน)
     บันทึกเสร็จ: แทนแผงด้วยของใหม่จากเซิร์ฟเวอร์ แล้วส่งสัญญาณ tb:inhouse-saved ให้หน้าบัตรรีเฟรชกล่องสรุป/ปิด popup --}}
<style>
    .tb-inh-panel { background:#faf5ff; border:1px solid #e9d5ff; border-radius:.75rem; padding:.75rem .9rem; margin-bottom:.75rem; }
    .tb-inh-item { display:flex; justify-content:space-between; align-items:center; gap:.5rem; background:#fff; border:1px solid #ede9fe;
                   border-radius:.6rem; padding:.45rem .7rem; min-height:2.6rem; }
    .tb-inh-item.is-on { border-color:#c4b5fd; background:#f5f3ff; }
    .tb-inh-item.is-hiv { border-width:2px; }
    .tb-inh-panel .tb-chip { display:inline-flex; align-items:center; gap:.3rem; border-radius:999px; padding:.15rem .6rem; font-size:.8rem; border:1px solid; }
    .tb-inh-panel .tb-chip-wait { background:#fefce8; border-color:#fde68a; color:#854d0e; }
    .tb-inh-panel .tb-chip-done { background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
    /* ใน popup ใช้ปุ่มบันทึกที่ท้าย popup แทนปุ่มในแผง */
    .tb-lo-modal .js-inh-inline-save { display:none; }
</style>
<script>
(function () {
    if (window.__tbInhouseInit) return;
    window.__tbInhouseInit = true;

    var meta  = document.querySelector('meta[name="csrf-token"]');
    var TOKEN = meta ? meta.getAttribute('content') : '{{ csrf_token() }}';

    function panelOf(btn) {
        return (btn.closest && btn.closest('.js-inh-panel'))
            || (btn.dataset.panel ? document.querySelector(btn.dataset.panel) : null);
    }
    function say(panel, text, kind) {
        var m = panel && panel.querySelector('.js-inh-msg');
        if (!m) return;
        m.textContent = text || '';
        m.style.color = kind === 'error' ? '#b91c1c' : (kind === 'warn' ? '#92400e' : '#047857');
    }

    // แสดงทั้งหมด / แสดงเฉพาะที่ใช้บ่อย
    document.addEventListener('click', function (e) {
        var t = e.target.closest && e.target.closest('.js-inh-more-toggle');
        if (!t) return;
        var panel = t.closest('.js-inh-panel');
        var showAll = t.dataset.open !== '1';
        panel.querySelectorAll('.js-inh-more').forEach(function (el) { el.classList.toggle('d-none', !showAll); });
        t.dataset.open = showAll ? '1' : '0';
        t.textContent = showAll ? 'แสดงเฉพาะที่ใช้บ่อย' : 'แสดงทั้งหมด';
    });

    // Enter ในช่อง "อื่น ๆ" / ผล DTX ห้ามไปกดบันทึกฟอร์มหลักของหน้าตรวจ (แผงนี้อยู่ในฟอร์มนั้น) — ให้บันทึกแผงแทน
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || !e.target.classList || !(e.target.classList.contains('js-inh-other') || e.target.classList.contains('js-inh-poc'))) return;
        e.preventDefault();
        var p = e.target.closest('.js-inh-panel');
        var b = p && p.querySelector('.js-inh-save');
        if (b) b.click();
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.js-inh-save');
        if (!btn) return;
        var panel = panelOf(btn);
        if (!panel) return;

        var orig = [];
        try { orig = JSON.parse(panel.getAttribute('data-orig') || '[]'); } catch (err) { orig = []; }
        var now = [];
        panel.querySelectorAll('.js-inh-key').forEach(function (cb) { if (cb.checked) now.push(cb.value); });
        var other = panel.querySelector('.js-inh-other');
        if (other && other.value.trim() !== '') now.push('10|' + other.value.trim());

        var fd = new FormData();
        orig.forEach(function (k) { fd.append('orig[]', k); });
        now.forEach(function (k) { fd.append('new[]', k); });
        // ผลตรวจข้างเตียง (DTX) — กรอกผลแล้วไม่ต้องติ๊กก็ได้ เซิร์ฟเวอร์สั่งให้เอง
        panel.querySelectorAll('.js-inh-poc').forEach(function (i) {
            if (i.value.trim() !== '') fd.append('poc[' + i.dataset.method + ']', i.value.trim());
        });

        btn.disabled = true;
        say(panel, 'กำลังบันทึก…', 'warn');
        fetch(panel.dataset.url, {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': TOKEN, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, status: r.status, data: j }; });
        }).then(function (res) {
            btn.disabled = false;
            if (!(res.ok && res.data.ok)) {
                var msg = (res.data && res.data.message) || (res.status === 419 ? 'หน้าเปิดค้างนานจนหมดอายุ — โหลดหน้าใหม่แล้วลองอีกครั้ง' : 'บันทึกไม่สำเร็จ (HTTP ' + res.status + ')');
                if (res.data && res.data.errors) {
                    var all = []; Object.keys(res.data.errors).forEach(function (k) { all = all.concat(res.data.errors[k]); });
                    if (all.length) msg += ' — ' + all.join(' · ');
                }
                say(panel, msg, 'error');
                return;
            }
            // แทนแผงด้วยของใหม่ (ค่าตั้งต้นใหม่สำหรับเทียบส่วนต่างครั้งถัดไป)
            var holder = document.createElement('div');
            holder.innerHTML = res.data.panel || '';
            var fresh = holder.querySelector('.js-inh-panel');
            if (fresh) { panel.parentNode.replaceChild(fresh, panel); panel = fresh; }
            say(panel, res.data.message + (document.getElementById('visit_edit_form') ? ' (ตาราง Lab ด้านล่างอัปเดตเมื่อโหลดหน้าใหม่)' : ''),
                res.data.blocked && res.data.blocked.length ? 'warn' : 'ok');
            document.dispatchEvent(new CustomEvent('tb:inhouse-saved', { detail: res.data }));
        }).catch(function () {
            btn.disabled = false;
            say(panel, 'บันทึกไม่สำเร็จ — ตรวจสอบการเชื่อมต่อแล้วลองใหม่', 'error');
        });
    });
})();
</script>
{{-- ผล HIV ในแผงนี้ถูกปิดไว้ — ตัวจัดการ "กดดู" ต้องมากับหน้าที่ใช้แผงนี้ทุกหน้า (2569-10-06) --}}
@include('partials.hiv_mask_js')
