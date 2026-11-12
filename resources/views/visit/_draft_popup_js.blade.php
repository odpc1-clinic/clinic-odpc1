{{-- หน้าตรวจ: (1) จำข้อมูลที่พิมพ์ค้างไว้ในเครื่อง เติมกลับให้ตอนกลับมา  (2) เปิดหน้าเพิ่มยา/วินิจฉัย/Lab ฯลฯ เป็น popup — 2569-10-07
     หมอขอ: "กดสั่งยา popup ขึ้นมาเลือก ๆ ๆ กด OK ยาไปอยู่หน้าหลัก" ไม่ต้องกดบันทึกก่อนออก และที่พิมพ์ไว้ต้องไม่หาย

     (2) popup = iframe โหลดหน้าเดิมของระบบ (ไม่ต้องเขียนหน้าใหม่) · ทุกฟอร์มใน iframe ถูกเติม _popup=1
         พอหน้านั้นบันทึกเสร็จแล้ว redirect กลับหน้าตรวจ App\Http\Middleware\PopupFrame เปลี่ยนเป็นหน้า "เสร็จแล้ว" เล็ก ๆ
         หน้านี้เห็นแล้วปิด popup → จำที่พิมพ์ค้าง → โหลดหน้าตรวจใหม่ (รายการยา/วินิจฉัยใหม่ขึ้น) → เติมที่พิมพ์ค้างกลับ
     (1) จำเฉพาะช่องที่ผู้ใช้แก้จริง (ต่างจากค่าตอนเปิดหน้า) เก็บ localStorage 2 ชม. ไม่ได้บันทึกอะไรลงฐานจนกว่าจะกดบันทึกเอง
         เติมกลับเฉพาะช่องที่ค่าในฐานยังเท่าตอนที่จำไว้ — ห้องอื่นแก้ช่องนั้นไปแล้ว = ไม่ทับ แจ้งจำนวนไว้
     ไฟล์นี้อยู่ใน @push('scripts') ของหน้าที่โหลดเต็ม (ไม่ได้มาทาง AJAX) สคริปต์จึงรันได้ --}}
<script>
(function () {
    var form = document.getElementById('visit_edit_form');
    if (!form) return;
    var KEY = 'visitDraft:{{ $visit->id }}';
    var SCROLL_KEY = 'visitScroll:{{ $visit->id }}';
    var EDIT_PATH = location.pathname;
    var TTL = 2 * 3600 * 1000;
    var initial = null, submitting = false, discard = false;

    // ---------- (1) จำ / เติมกลับ ----------
    function fields() {
        return Array.prototype.filter.call(form.elements, function (el) {
            if (!el.name || el.disabled || el.closest('[data-no-dirty]')) return false;
            if (!/^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName)) return false;
            // hidden = ค่าที่สคริปต์คำนวณจากช่องอื่น (เติมช่องต้นทางแล้วมันคำนวณเอง) · ไฟล์/รหัสผ่านเก็บไม่ได้
            return ['hidden', 'file', 'submit', 'button', 'reset', 'image', 'password'].indexOf((el.type || '').toLowerCase()) < 0;
        });
    }
    // ชื่อซ้ำกันได้ (ช่องแบบ array) — ใช้ "ชื่อ#ลำดับ" · radio แยกตามค่า
    function keyed() {
        var counts = {}, out = [];
        fields().forEach(function (el) {
            var n = el.name + (el.type === 'radio' ? '=' + el.value : '');
            counts[n] = (counts[n] || 0) + 1;
            out.push([n + '#' + counts[n], el]);
        });
        return out;
    }
    function valOf(el) {
        if (el.type === 'checkbox' || el.type === 'radio') return el.checked ? '1' : '0';
        if (el.multiple) return Array.prototype.filter.call(el.options, function (o) { return o.selected; }).map(function (o) { return o.value; }).join('\u0001');
        return el.value;
    }
    function setVal(el, v) {
        if (el.type === 'checkbox' || el.type === 'radio') el.checked = v === '1';
        else if (el.multiple) { var set = v.split('\u0001'); Array.prototype.forEach.call(el.options, function (o) { o.selected = set.indexOf(o.value) >= 0; }); }
        else el.value = v;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // คืน true = ไม่มีอะไรค้าง หรือจำไว้สำเร็จ (ออกจากหน้าได้ไม่ต้องเตือน)
    function stash() {
        if (!initial || submitting || discard) return true;
        var base = {}, val = {}, n = 0;
        keyed().forEach(function (p) {
            var v = valOf(p[1]);
            if (initial[p[0]] !== undefined && v !== initial[p[0]]) { base[p[0]] = initial[p[0]]; val[p[0]] = v; n++; }
        });
        try {
            if (!n) { localStorage.removeItem(KEY); return true; }
            localStorage.setItem(KEY, JSON.stringify({ t: Date.now(), base: base, val: val }));
            return true;
        } catch (e) { return false; }
    }
    window.__visitDraftStash = stash;   // ตัวเตือน "ออกจากหน้านี้?" ใน visit/edit ถามก่อน — จำได้ = ไม่ต้องเตือน
    window.addEventListener('pagehide', stash);

    // กดบันทึก = ข้อมูลเข้าฐานแล้ว ไม่ต้องจำ
    form.addEventListener('submit', function () {
        submitting = true;
        try { localStorage.removeItem(KEY); } catch (e) {}
    });

    function banner(html, kind) {
        var b = document.createElement('div');
        b.className = 'd-print-none';
        b.style.cssText = 'position:fixed;top:0;left:50%;transform:translateX(-50%);z-index:1085;max-width:96vw;'
            + 'padding:.5rem 1rem;border-radius:0 0 .6rem .6rem;box-shadow:0 2px 10px rgba(0,0,0,.2);font-weight:600;'
            + (kind === 'warn' ? 'background:#fff7ed;color:#9a3412;border:1px solid #fdba74;' : 'background:#fffbeb;color:#854d0e;border:1px solid #fcd34d;');
        b.innerHTML = html;
        document.body.appendChild(b);
        return b;
    }

    function restore() {
        var d = null;
        try { d = JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) {}
        if (!d || !d.val) return;
        if (Date.now() - d.t > TTL) { try { localStorage.removeItem(KEY); } catch (e) {} return; }
        var map = {}; keyed().forEach(function (p) { map[p[0]] = p[1]; });
        var ok = 0, skipped = 0;
        Object.keys(d.val).forEach(function (k) {
            var el = map[k];
            // ช่องหาย หรือค่าในฐานเปลี่ยนไปแล้ว (ห้องอื่นแก้) = ไม่ทับ
            if (!el || initial[k] !== d.base[k]) { skipped++; return; }
            setVal(el, d.val[k]);
            var mark = (el.type === 'checkbox' || el.type === 'radio') ? (el.closest('.form-check') || el) : el;
            mark.style.outline = '2px solid #f59e0b'; mark.style.outlineOffset = '1px';
            if (mark === el) el.style.background = '#fffbeb';
            el.addEventListener('input', function clear(e) {
                if (!e.isTrusted) return;
                mark.style.outline = ''; el.style.background = ''; el.removeEventListener('input', clear);
            });
            ok++;
        });
        if (!ok) { try { localStorage.removeItem(KEY); } catch (e) {} return; }
        // ให้ตัวเตือน/ป้าย "ยังไม่ได้ Save" ของหน้ารู้ว่ามีของค้าง
        document.dispatchEvent(new CustomEvent('visit:dirty', { detail: { other: true } }));
        var b = banner('↺ เติมข้อมูลที่พิมพ์ค้างไว้ ' + ok + ' ช่อง (กรอบสีเหลือง) — ยังไม่ได้บันทึก กด "บันทึก" เพื่อเก็บ'
            + (skipped ? ' <span style="font-weight:400">· ข้าม ' + skipped + ' ช่องที่มีคนแก้ไปแล้ว</span>' : '')
            + ' <button type="button" class="btn btn-sm btn-outline-secondary ms-2 py-0">ไม่เอา</button>'
            + ' <button type="button" class="btn btn-sm btn-link py-0" title="ซ่อน">✕</button>');
        var btns = b.querySelectorAll('button');
        btns[0].addEventListener('click', function () {
            if (!confirm('ทิ้งข้อมูลที่พิมพ์ค้างไว้ ' + ok + ' ช่อง แล้วกลับไปใช้ค่าที่บันทึกไว้?')) return;
            discard = true;
            try { localStorage.removeItem(KEY); } catch (e) {}
            window.__visitDraftStashed = true;   // ไม่ต้องเตือน "ออกจากหน้านี้?"
            location.reload();
        });
        btns[1].addEventListener('click', function () { b.remove(); });
    }

    // จำค่าตอนเปิดหน้า "หลัง" สคริปต์อื่นตั้งค่าเสร็จ (ตัวคำนวณ _touched จำที่ 400ms) แล้วค่อยเติมกลับ
    // เติมหลังตัวคำนวณ _touched จำค่า = ช่องที่เติมกลับนับเป็น "แก้แล้ว" ถูกบันทึกตอนกด Save
    window.addEventListener('load', function () {
        setTimeout(function () {
            initial = {};
            keyed().forEach(function (p) { initial[p[0]] = valOf(p[1]); });
            restore();
        }, 450);
    });

    // ---------- (2) popup ----------
    // หน้าที่เปิดเป็น popup — เพิ่ม/แก้ยา วินิจฉัย Lab คู่สัมผัส ติดตาม (หน้าใหญ่อื่น ๆ เช่น ข้อมูลผู้ป่วย ยังเปลี่ยนหน้าเหมือนเดิม แต่ข้อ 1 จำที่พิมพ์ไว้ให้)
    var POPUP = /^\/(diagnosis\/create|diagnosis\/\d+\/edit|visit-medicine\/add|visit-medicine\/\d+\/edit|lab-sub\/config|lab\/\d+\/edit|contact-person\/create|contact-person\/\d+\/edit|followups\/create\/\d+|followups\/\d+\/edit)\/?$/;
    var modalEl = null, modal = null, frame = null, changed = false;

    // กล่อง popup ทำเอง ไม่ใช้ bootstrap.Modal — Bootstrap ถูกรวมใน app.js โดยไม่มี window.bootstrap (ดูหมายเหตุใน visit/form/index)
    function ensureModal() {
        if (modalEl) return;
        modalEl = document.createElement('div');
        modalEl.id = 'visit-popup';
        modalEl.className = 'd-print-none';
        modalEl.style.cssText = 'display:none;position:fixed;inset:0;z-index:2000;background:rgba(15,23,42,.55);';
        modalEl.innerHTML =
            '<div style="position:absolute;top:2vh;left:50%;transform:translateX(-50%);width:min(1200px,96vw);height:96vh;'
          + 'background:#fff;border-radius:.6rem;box-shadow:0 10px 40px rgba(0,0,0,.35);display:flex;flex-direction:column;overflow:hidden;">'
          + '<div style="display:flex;align-items:center;gap:.75rem;padding:.5rem .9rem;border-bottom:1px solid #e5e7eb;">'
          + '<b class="js-pp-title"></b>'
          + '<span class="small text-muted">กดบันทึกในหน้านี้แล้วจะปิดเองกลับหน้าตรวจ · ที่พิมพ์ค้างในหน้าตรวจไม่หาย</span>'
          // ปุ่มปิดต้องเห็นชัด — คลิกนอกกล่องปิดไม่ได้ (คลินิกขอ 2569-10-07)
          + '<button type="button" class="btn btn-danger ms-auto js-pp-close" style="font-weight:700;padding:.4rem 1.1rem;border-radius:2rem;">'
          + '<i class="fa fa-times me-1"></i> ปิดหน้าต่าง</button></div>'
          + '<div style="position:relative;flex:1 1 auto;">'
          + '<div class="js-pp-loading" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#64748b;">กำลังโหลด…</div>'
          + '<iframe style="position:absolute;inset:0;border:0;width:100%;height:100%;background:#fff;"></iframe></div>'
          + '</div>';
        document.body.appendChild(modalEl);   // ต่อกับ body — ไม่อยู่ในฟอร์มหลัก
        frame = modalEl.querySelector('iframe');
        // คลิกพื้นหลังไม่ปิด (กันเผลอปิดตอนเลือกยาอยู่) — ปิดได้ทางปุ่มเท่านั้น
        modalEl.querySelector('.js-pp-close').addEventListener('click', function () { finish(changed); });
        frame.addEventListener('load', onFrameLoad);
        modal = {
            show: function () { modalEl.style.display = 'block'; document.body.style.overflow = 'hidden'; },
            hide: function () { modalEl.style.display = 'none'; document.body.style.overflow = ''; }
        };
    }

    function finish(reload) {
        modal.hide();
        frame.src = 'about:blank';
        if (!reload) return;
        // มีการบันทึกใน popup = โหลดหน้าตรวจใหม่ให้รายการใหม่ขึ้น · จำที่พิมพ์ค้าง + ตำแหน่งเลื่อนไว้ก่อน
        stash();
        window.__visitDraftStashed = true;
        try { sessionStorage.setItem(SCROLL_KEY, JSON.stringify({ y: window.scrollY, t: Date.now() })); } catch (e) {}
        location.reload();
    }

    function tag(f) {
        if (!f || f.querySelector('input[name="_popup"]')) return;
        var i = f.ownerDocument.createElement('input');
        i.type = 'hidden'; i.name = '_popup'; i.value = '1';
        f.appendChild(i);
    }

    function onFrameLoad() {
        var W, doc;
        try { W = frame.contentWindow; doc = frame.contentDocument; } catch (e) { return; }
        if (!doc || !doc.body || W.location.href === 'about:blank') return;
        // บันทึกเสร็จ (PopupFrame) หรือหลุดมาหน้าตรวจเอง = จบ
        if (doc.body.getAttribute('data-popup-done') === '1') return finish(true);
        if (W.location.pathname === EDIT_PATH) return finish(true);

        // ซ่อนแถบเมนูด้านบนของหน้าใน popup ให้เหลือแต่เนื้อหา
        var st = doc.createElement('style');
        st.textContent = 'nav.navbar, .navbar-modern { display: none !important; } body { padding-top: 0 !important; }';
        doc.head.appendChild(st);
        frame.style.visibility = 'visible';
        modalEl.querySelector('.js-pp-loading').style.display = 'none';

        // ทุกทางที่หน้าใน popup ส่งข้อมูล: submit ปกติ / form.submit() ที่สคริปต์สร้าง (ปุ่มลบ) / fetch / XHR
        // กันกดบันทึกซ้ำ (ดับเบิลคลิก = ยาขึ้น 2 ตัว — เจอจริง 2569-10-07)
        // ฟอร์มที่ "ส่งจริง" ไปแล้ว (ไม่มีสคริปต์ของหน้าเรียก preventDefault) = ห้ามส่งซ้ำจนกว่าหน้าจะเปลี่ยน
        // ฟอร์มที่หน้าส่งเองด้วย fetch (preventDefault) ไม่ถูกล็อก ส่งได้หลายครั้งตามปกติ
        doc.addEventListener('submit', function (e) {
            if (e.target.__ppSent) { e.preventDefault(); e.stopImmediatePropagation(); return; }
            tag(e.target); changed = true;
        }, true);
        W.addEventListener('submit', function (e) {
            if (e.defaultPrevented) return;
            var f = e.target;
            f.__ppSent = true;
            // ปิดปุ่มหลังเบราว์เซอร์เก็บค่าฟอร์มไปแล้ว — ปิดทันทีชื่อ/ค่าของปุ่มที่กดจะไม่ถูกส่ง
            setTimeout(function () {
                Array.prototype.forEach.call(f.querySelectorAll('button[type=submit], button:not([type]), input[type=submit]'), function (b) {
                    b.disabled = true;
                    if (b.tagName === 'BUTTON') b.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> กำลังบันทึก…';
                });
            }, 0);
        });
        var origSubmit = W.HTMLFormElement.prototype.submit;
        W.HTMLFormElement.prototype.submit = function () {
            if (this.__ppSent) return;   // สคริปต์ของหน้าเรียก submit() ซ้ำจากการกดสองที
            this.__ppSent = true;
            tag(this); changed = true;
            return origSubmit.call(this);
        };
        if (W.fetch) {
            var origFetch = W.fetch;
            W.fetch = function (u, o) { if (o && o.method && !/^get$/i.test(o.method)) changed = true; return origFetch.apply(this, arguments); };
        }
        var origOpen = W.XMLHttpRequest.prototype.open;
        W.XMLHttpRequest.prototype.open = function (m) { if (!/^get$/i.test(m)) changed = true; return origOpen.apply(this, arguments); };

        // ปุ่ม "ย้อนกลับ/ยกเลิก" ในหน้าพวกนี้ลิงก์กลับหน้าตรวจ = ปิด popup เฉย ๆ (ไม่ต้องโหลดหน้าตรวจซ้อนใน popup)
        doc.addEventListener('click', function (e) {
            var a = e.target.closest && e.target.closest('a[href]');
            if (!a || (a.target && a.target !== '_self')) return;
            var u;
            try { u = new URL(a.href, W.location.href); } catch (x) { return; }
            if (u.origin === location.origin && u.pathname === EDIT_PATH) { e.preventDefault(); finish(changed); }
        }, true);
    }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a || (a.target && a.target !== '_self') || a.closest('#visit-popup')) return;
        var u;
        try { u = new URL(a.href, location.href); } catch (x) { return; }
        if (u.origin !== location.origin || !POPUP.test(u.pathname)) return;
        e.preventDefault();
        ensureModal();
        changed = false;
        modalEl.querySelector('.js-pp-title').textContent = (a.getAttribute('title') || a.textContent || '').replace(/\s+/g, ' ').trim();
        modalEl.querySelector('.js-pp-loading').style.display = 'flex';
        frame.style.visibility = 'hidden';   // ซ่อนไว้จนซ่อนเมนูของหน้าใน popup เสร็จ ไม่ให้เห็นกระพริบ
        frame.src = u.href;
        modal.show();
    }, true);
})();
</script>
