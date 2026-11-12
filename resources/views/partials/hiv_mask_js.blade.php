{{-- ตัวจัดการ "กดดูผล HIV" (partials/hiv_mask) — ต้องอยู่ในหน้าที่โหลดเต็มเท่านั้น (สคริปต์ที่มากับ innerHTML ไม่รัน)
     include ซ้ำได้ มีตัวกันทำงานซ้ำ (บัตร TB 01 / หน้าตรวจ TB / ตาราง Lab ของ visit TB)
     - ผูกแบบ delegate ที่ document ช่วง capture: จุดที่ปิดไว้บางจุดอยู่ในชิป <button data-bs-toggle="modal">
       ต้องหยุดไม่ให้ popup เปิดตอนกด "กดดู"
     - เปิดดูครั้งเดียว = เปิดทุกจุดในหน้า และจุดที่โผล่ทีหลัง (popup / กล่องที่โหลดใหม่ด้วย AJAX) ก็เติมให้เองจากผลที่ได้มาแล้ว
       ไม่ยิงซ้ำ ไม่เกิด log ซ้ำ · โหลดหน้าใหม่ = ปิดใหม่ ต้องกดดูอีกครั้ง --}}
<style>
    .hiv-mask { display: inline-flex; align-items: center; gap: .3rem; flex-wrap: wrap; }
    .hiv-mask-label { font-weight: 600; }
    .hiv-mask-val { letter-spacing: .1em; color: #6b7280; }
    .hiv-mask-btn { cursor: pointer; color: #6d28d9; font-weight: 600; text-decoration: underline; white-space: nowrap; }
    .hiv-mask-btn:focus { outline: 2px solid #c4b5fd; outline-offset: 1px; }
    .hiv-mask.is-open .hiv-mask-val { letter-spacing: normal; color: #111827; font-weight: 600; }
    .hiv-mask.is-pos .hiv-mask-val { color: #b91c1c; }
    .hiv-mask-act { font-size: .8rem; padding: 0 .35rem; border: 1px solid #c4b5fd; border-radius: .25rem; color: #5b21b6; background: #fff; text-decoration: none; white-space: nowrap; }
    .hiv-mask-act:hover { background: #f5f3ff; color: #4c1d95; }
    .hiv-mask-act.is-refer { border-color: #fca5a5; color: #b91c1c; }
</style>
<script>
(function () {
    if (window.__hivMaskInit) return;
    window.__hivMaskInit = true;

    var data = null;      // ผลที่ได้จากเซิร์ฟเวอร์ (ของคนไข้คนเดียวในหน้านี้)
    var dataUrl = null;
    var busy = false;

    function token() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '{{ csrf_token() }}';
    }

    function link(href, text, cls) {
        var a = document.createElement('a');
        a.href = href; a.target = '_blank'; a.rel = 'noopener';
        a.className = 'hiv-mask-act ' + (cls || '');
        a.innerHTML = text;
        return a;
    }

    function fill(el) {
        if (el.dataset.open === '1' || el.dataset.url !== dataUrl) return;
        var kind = el.dataset.kind, ref = String(el.dataset.ref || '');
        var val = el.querySelector('.hiv-mask-val');
        var btn = el.querySelector('.js-hiv-reveal');
        var text = '', pos = false, sub = null;

        if (kind === 'reg') {
            var r = data.regs && data.regs[ref];
            text = r && r.text ? r.text : 'ยังไม่ได้บันทึก';
            pos = !!(r && r.positive);
            sub = (data.subs || [])[0] || null;   // ปุ่มพิมพ์ข้างช่องในบัตร = ผล Anti-HIV ล่าสุดในแล็บเรา
        } else if (kind === 'visit') {
            // ผล HIV ของ visit นั้นทั้งหมด (ตารางประวัติการมา) — กติกาเดียวกับ Visit::hivResultText()
            var seen = [];
            (data.subs || []).forEach(function (s) {
                if (String(s.visit_id) !== ref) return;
                if (!sub) sub = s;
                if (s.positive) pos = true;
                if (seen.indexOf(s.result) < 0) seen.push(s.result);
            });
            text = seen.length ? seen.join(', ') : '—';
        } else {
            for (var i = 0; i < (data.subs || []).length; i++) {
                if (String(data.subs[i].id) === ref) { sub = data.subs[i]; break; }
            }
            text = sub ? sub.result : '—';
            pos = !!(sub && sub.positive);
        }

        if (val) val.textContent = text;
        if (btn) btn.remove();
        el.dataset.open = '1';
        el.dataset.hivValue = text;        // ให้ปุ่ม "Print ผลที่เลือก" ของตาราง Lab หยิบไปใช้ได้หลังเปิดดู
        el.classList.add('is-open');
        if (pos) el.classList.add('is-pos');

        el.querySelectorAll('.hiv-mask-act').forEach(function (a) { a.remove(); });   // กันปุ่มซ้ำถ้าเนื้อหาถูกคัดลอกมาทั้งก้อน
        if (el.dataset.actions === '1' && sub) {
            el.appendChild(link(sub.print, '<i class="fa fa-print me-1"></i>พิมพ์ผล'
                + (kind === 'reg' && sub.date ? ' (' + sub.date + ')' : '')));
            if (sub.refer) el.appendChild(link(sub.refer, '<i class="fa fa-share-from-square me-1"></i>พิมพ์ใบ refer', 'is-refer'));
        }

        // ช่อง "ผลเลือด HIV" ในฟอร์มบัตร: ก่อนเปิดดูไม่มีช่อง name=hiv_result เลย (บันทึกฟอร์มแล้วค่าเดิมไม่ถูกล้าง)
        // เปิดดูแล้วค่อยสร้าง select พร้อมค่าจริง ให้แก้ได้ตามเดิม
        var field = el.closest('.js-hiv-reg-field');
        if (kind === 'reg' && field && !field.querySelector('select[name="hiv_result"]')) {
            var opts = {};
            try { opts = JSON.parse(field.dataset.options || '{}'); } catch (e) {}
            var sel = document.createElement('select');
            sel.name = 'hiv_result';
            sel.className = 'form-select';
            sel.add(new Option('— เลือก —', ''));
            Object.keys(opts).forEach(function (k) { sel.add(new Option(opts[k], k)); });
            var cur = data.regs && data.regs[ref] ? data.regs[ref].value : null;
            sel.value = cur === null || cur === undefined ? '' : String(cur);
            field.insertBefore(sel, field.firstChild);
            if (val) val.remove();
        }
    }

    function fillAll() {
        if (!data) return;
        document.querySelectorAll('.js-hiv-mask').forEach(fill);
    }

    function reveal(el) {
        if (busy) return;
        if (data && el.dataset.url === dataUrl) { fillAll(); return; }
        busy = true;
        var btn = el.querySelector('.js-hiv-reveal');
        if (btn) btn.textContent = 'กำลังโหลด…';
        var fd = new FormData();
        if (el.dataset.reg) fd.append('reg', el.dataset.reg);
        if (el.dataset.visit) fd.append('visit', el.dataset.visit);
        if (el.dataset.where) fd.append('where', el.dataset.where);
        fetch(el.dataset.url, {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).then(function (d) {
            data = d; dataUrl = el.dataset.url;
            fillAll();
        }).catch(function () {
            if (btn) btn.innerHTML = '<i class="fa fa-eye me-1"></i>กดดู';
            alert('เปิดดูผล HIV ไม่สำเร็จ — ลองใหม่อีกครั้ง (ถ้าหน้าเปิดค้างไว้นาน ให้รีเฟรชหน้าก่อน)');
        }).finally(function () { busy = false; });
    }

    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('.js-hiv-reveal');
        if (!b) return;
        e.preventDefault();
        e.stopPropagation();   // ช่วง capture: ชิปที่เปิด popup จะไม่เปิด
        reveal(b.closest('.js-hiv-mask'));
    }, true);
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var b = e.target.closest && e.target.closest('.js-hiv-reveal');
        if (!b) return;
        e.preventDefault();
        e.stopPropagation();
        reveal(b.closest('.js-hiv-mask'));
    }, true);

    // กล่องที่ถูกสลับเนื้อหาด้วย innerHTML (สรุปสั่งตรวจ / popup Lab ในคลินิก) — เติมผลที่เปิดดูไปแล้วให้เลย
    var t = null;
    new MutationObserver(function () {
        if (!data) return;
        clearTimeout(t);
        t = setTimeout(fillAll, 30);
    }).observe(document.documentElement, { childList: true, subtree: true });
})();
</script>
