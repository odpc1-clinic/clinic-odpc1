{{--
    ตัวทำงานของตัวเลือก ICD-10 — เรียกครั้งเดียวจาก layout

    ทำไมต้องแยกจากตัวแสดงผล: ตัวเลือกนี้ไปโผล่ในแถววินิจฉัยที่ถูกโคลนจาก <template>
    ด้วย JS สคริปต์ที่ติดไปกับ HTML ที่โคลนมาจะไม่ถูกรัน (CLAUDE.md ข้อ 2)
    จึงผูก event ที่ document ทีเดียว แล้วแถวใหม่ใช้ได้เองโดยไม่ต้องสั่งอะไรเพิ่ม

    พจนานุกรมรหัสส่งมาเป็น JSON ก้อนเดียวต่อหน้า ไม่ใช่ฝังซ้ำในทุกตัวเลือก
    หน้าวินิจฉัยมีได้หลายแถว ถ้าฝังซ้ำทุกแถวก็คือส่งรหัสชุดเดิมซ้ำไปเรื่อย ๆ โดยเปล่าประโยชน์
--}}
@once
@php
    // ส่งเฉพาะรหัสที่หมอคัดไว้ — ICD-10-TM ชุดเต็ม (~15,700) ค้นจากเซิร์ฟเวอร์ตอนพิมพ์ (2569-10-30)
    $__icdAll = \App\Models\Icd10Code::where('active', true)->where('source', 'clinic')
        ->orderBy('sort')->orderBy('code')->get()
        ->map->toPicker()->values();
@endphp
<style>
    .icd10-picker { position: relative; }
    .icd10-chips { display: flex; flex-wrap: wrap; gap: 4px; }
    .icd10-chips:not(:empty) { margin-bottom: 4px; }
    .icd10-chip {
        display: inline-flex; align-items: center; gap: 5px;
        background: #eef2ff; border: 1px solid #c7d2fe; color: #1e3a8a;
        border-radius: 12px; padding: 1px 6px 1px 8px; font-size: .78rem; line-height: 1.35;
        max-width: 100%;            /* ชื่อเต็มยาว -> ตัดบรรทัดในชิป แทนการล้นช่อง */
    }
    .icd10-chip.is-primary { background: #fef3c7; border-color: #fcd34d; color: #78350f; }
    .icd10-chip.is-secondary { background: #fee2e2; border-color: #fca5a5; color: #7f1d1d; }
    .icd10-chip b { font-weight: 700; }
    .icd10-chip .x {
        cursor: pointer; font-weight: 700; opacity: .55; padding: 0 2px;
    }
    .icd10-chip .x:hover { opacity: 1; }
    .icd10-box { position: relative; }
    .icd10-menu {
        display: none; position: absolute; z-index: 1060; left: 0; right: 0; top: 100%;
        background: #fff; border: 1px solid #cbd5e1; border-radius: 6px;
        box-shadow: 0 6px 18px rgba(0,0,0,.12); max-height: 260px; overflow-y: auto;
    }
    .icd10-menu.show { display: block; }
    .icd10-menu .g {
        padding: 3px 9px; font-size: .72rem; font-weight: 700; color: #475569;
        background: #f1f5f9; position: sticky; top: 0;
    }
    .icd10-menu .o { padding: 4px 9px; cursor: pointer; font-size: .85rem; }
    .icd10-menu .o:hover, .icd10-menu .o.on { background: #e0e7ff; }
    .icd10-menu .o b { color: #1e3a8a; }
    .icd10-menu .o.sec b { color: #b91c1c; }
    .icd10-menu .none { padding: 8px 9px; color: #64748b; font-size: .85rem; }
</style>
<script>
(function () {
    'use strict';
    if (window.__icd10Ready) return;
    window.__icd10Ready = true;

    var ALL = @json($__icdAll);
    var SEARCH_URL = @json(route('icd10.search'));
    // รหัสจากชุดเต็มที่เคยเห็นในหน้านี้ (ผลค้น + ป้ายที่เซิร์ฟเวอร์วาดมา) — ใช้วาดป้ายใหม่ตอนเพิ่ม/ลบรหัส
    var EXTRA = {};

    function norm(s) { return (s || '').toString().toLowerCase(); }

    function byCode(code) {
        for (var i = 0; i < ALL.length; i++) if (ALL[i].c === code) return ALL[i];
        return EXTRA[code] || null;
    }

    // ค้นชุดเต็มจากเซิร์ฟเวอร์ — หน่วงไว้นิดให้พิมพ์จบก่อน และทิ้งผลของคำค้นเก่าที่ตอบช้ากว่า
    var remoteTimer = null, remoteSeq = 0;
    function searchRemote(box, term) {
        clearTimeout(remoteTimer);
        if (norm(term).replace(/\s/g, '').length < 2) return;
        remoteTimer = setTimeout(function () {
            var my = ++remoteSeq;
            fetch(SEARCH_URL + '?q=' + encodeURIComponent(term), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (list) {
                    if (my !== remoteSeq) return;
                    var inp = box.querySelector('.icd10-input');
                    if (!inp || inp.value !== term) return;          // พิมพ์ต่อไปแล้ว
                    (list || []).forEach(function (it) { if (!byCode(it.c)) EXTRA[it.c] = it; });
                    box.__remote = { term: term, list: list || [] };
                    openMenu(box, term, true);
                })
                .catch(function () {});
        }, 250);
    }

    function selectedOf(box) {
        try { return JSON.parse(box.getAttribute('data-selected') || '[]'); }
        catch (e) { return []; }
    }

    function setSelected(box, list) {
        box.setAttribute('data-selected', JSON.stringify(list));
        render(box);
        // แจ้งหน้าตรวจว่ามีการเปลี่ยนแปลง ไม่งั้นตัวเตือน "ยังไม่ได้บันทึก" ไม่รู้เรื่อง
        // other = ช่องที่ต้องกด Save เอง (ไม่ใช่ช่อง autosave) — คลิกชิปไม่มี input event ให้จับ (รีวิว 2569-09-30)
        document.dispatchEvent(new CustomEvent('visit:dirty', { detail: { other: true } }));
    }

    function render(box) {
        var name  = box.getAttribute('data-name') || 'icd10[]';
        var list  = selectedOf(box);
        var chips = box.querySelector('.icd10-chips');
        if (!chips) return;

        chips.innerHTML = '';

        // ส่งค่าว่างไปเสมอหนึ่งตัว เพื่อให้ฝั่งเซิร์ฟเวอร์รู้ว่า "ฟอร์มนี้มีช่อง ICD อยู่"
        // ถ้าไม่ส่ง พอผู้ใช้เอารหัสออกจนหมด ฝั่งเซิร์ฟเวอร์จะไม่เห็นคีย์นี้เลย
        // แล้วแยกไม่ออกระหว่าง "ลบรหัสออกหมด" กับ "ฟอร์มนี้ไม่มีช่องนี้" -> ของเก่าจะค้าง
        var keep = document.createElement('input');
        keep.type = 'hidden'; keep.name = name; keep.value = '';
        chips.appendChild(keep);

        list.forEach(function (code, i) {
            var info = byCode(code);
            var chip = document.createElement('span');
            chip.className = 'icd10-chip' + (i === 0 ? ' is-primary' : (info && info.s ? ' is-secondary' : ''));
            chip.title = info ? (info.t || info.e) : code;

            var label = document.createElement('span');
            label.innerHTML = (i === 0 ? '★ ' : '') + '<b>' + escapeHtml(code) + '</b>';
            if (info && (info.t || info.e)) {
                // ชื่อเต็ม ไม่ตัด — ชื่อใน ICD-10-TM ยาว ตัดแล้วต้องเอาเมาส์ชี้อ่าน (พยาบาลขอ 2569-10-30)
                label.innerHTML += ' ' + escapeHtml(info.t || info.e);
            }
            chip.appendChild(label);

            var x = document.createElement('span');
            x.className = 'x'; x.textContent = '×'; x.setAttribute('data-code', code);
            x.title = 'เอารหัสนี้ออก';
            chip.appendChild(x);

            var hid = document.createElement('input');
            hid.type = 'hidden'; hid.name = name; hid.value = code;
            chip.appendChild(hid);

            chips.appendChild(chip);
        });
    }

    function escapeHtml(s) {
        return (s || '').replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function openMenu(box, term, fromRemote) {
        var menu   = box.querySelector('.icd10-menu');
        var prefix = box.getAttribute('data-prefix') || '';
        var chosen = selectedOf(box);
        if (!menu) return;

        var q = norm(term);
        var hits = ALL.filter(function (it) {
            if (prefix && it.c.indexOf(prefix) !== 0) return false;
            if (chosen.indexOf(it.c) !== -1) return false;     // เลือกไปแล้วไม่ต้องโชว์ซ้ำ
            if (!q) return true;
            return norm(it.c).indexOf(q) !== -1
                || norm(it.c).replace('.', '').indexOf(q.replace('.', '')) !== -1
                || norm(it.t).indexOf(q) !== -1
                || norm(it.e).indexOf(q) !== -1
                || norm(it.g).indexOf(q) !== -1;
        });

        // ต่อท้ายด้วยผลจาก ICD-10-TM ชุดเต็ม (ไม่ซ้ำกับรายการของหมอที่โชว์ไปแล้ว)
        var remote = box.__remote && box.__remote.term === term ? box.__remote.list : [];
        var seen = {};
        hits.forEach(function (it) { seen[it.c] = 1; });
        remote.forEach(function (it) {
            if (seen[it.c] || chosen.indexOf(it.c) !== -1) return;
            if (prefix && it.c.indexOf(prefix) !== 0) return;
            seen[it.c] = 1;
            hits.push({ c: it.c, t: it.t, e: it.e, g: 'ICD-10-TM ทั้งหมด (สนย.)', s: it.s });
        });
        if (!fromRemote) searchRemote(box, term);

        if (!hits.length) {
            menu.innerHTML = '<div class="none">' + (q.replace(/\s/g, '').length >= 2 && !fromRemote
                ? 'กำลังค้นใน ICD-10-TM ทั้งหมด…'
                : 'ไม่พบรหัสที่ตรงกับ "' + escapeHtml(term) + '"') + '</div>';
        } else {
            var html = '', group = null;
            hits.slice(0, 90).forEach(function (it) {
                if (it.g !== group) {
                    group = it.g;
                    html += '<div class="g">' + escapeHtml(group) + '</div>';
                }
                html += '<div class="o' + (it.s ? ' sec' : '') + '" data-code="' + it.c + '">'
                      + '<b>' + it.c + '</b> ' + escapeHtml(it.t || it.e)
                      + (it.s ? ' <span style="font-size:.72rem">(รหัสรอง)</span>' : '')
                      + '</div>';
            });
            menu.innerHTML = html;
        }
        menu.classList.add('show');
    }

    function closeMenus(except) {
        document.querySelectorAll('.icd10-menu.show').forEach(function (m) {
            if (m !== except) m.classList.remove('show');
        });
    }

    function add(box, code) {
        var list = selectedOf(box);
        if (list.indexOf(code) !== -1) return;
        list.push(code);
        setSelected(box, list);
        var inp = box.querySelector('.icd10-input');
        if (inp) { inp.value = ''; inp.focus(); }
        closeMenus();
    }

    // ---- ผูก event ที่ document ทั้งหมด แถวที่โคลนมาใหม่จึงใช้ได้ทันที ----

    document.addEventListener('focusin', function (e) {
        var inp = e.target.closest && e.target.closest('.icd10-input');
        if (!inp) return;
        var box = inp.closest('.icd10-picker');
        if (box) { closeMenus(); openMenu(box, inp.value); }
    });

    document.addEventListener('input', function (e) {
        var inp = e.target.closest && e.target.closest('.icd10-input');
        if (!inp) return;
        var box = inp.closest('.icd10-picker');
        if (box) openMenu(box, inp.value);
    });

    document.addEventListener('click', function (e) {
        var opt = e.target.closest && e.target.closest('.icd10-menu .o');
        if (opt) {
            var box = opt.closest('.icd10-picker');
            if (box) add(box, opt.getAttribute('data-code'));
            return;
        }
        var x = e.target.closest && e.target.closest('.icd10-chip .x');
        if (x) {
            var b = x.closest('.icd10-picker');
            if (b) {
                var code = x.getAttribute('data-code');
                setSelected(b, selectedOf(b).filter(function (c) { return c !== code; }));
            }
            return;
        }
        if (!(e.target.closest && e.target.closest('.icd10-picker'))) closeMenus();
    });

    // Enter = เลือกตัวแรกในรายการ · Esc = ปิด — ห้ามให้ Enter ไปกดบันทึกทั้งฟอร์ม
    document.addEventListener('keydown', function (e) {
        var inp = e.target.closest && e.target.closest('.icd10-input');
        if (!inp) return;
        var box = inp.closest('.icd10-picker');
        if (!box) return;
        if (e.key === 'Escape') { closeMenus(); return; }
        if (e.key === 'Enter') {
            e.preventDefault();
            var first = box.querySelector('.icd10-menu.show .o');
            if (first) add(box, first.getAttribute('data-code'));
        }
    });

    function boot(root) {
        (root || document).querySelectorAll('.icd10-picker').forEach(function (box) {
            if (box.getAttribute('data-icd-on')) return;
            box.setAttribute('data-icd-on', '1');
            // ป้ายรหัสจากชุดเต็มที่เซิร์ฟเวอร์วาดมา -> จำชื่อไว้ ไม่งั้นเพิ่ม/ลบรหัสอื่นแล้วป้ายนี้เหลือแต่รหัส
            box.querySelectorAll('.icd10-chip').forEach(function (chip) {
                var x = chip.querySelector('.x'), code = x && x.getAttribute('data-code');
                if (code && !byCode(code) && chip.title) {
                    EXTRA[code] = { c: code, t: chip.title, e: '', g: '', s: chip.classList.contains('is-secondary') ? 1 : 0 };
                }
            });
            // ตัวที่เรนเดอร์มาจากฝั่งเซิร์ฟเวอร์แล้วไม่ต้องวาดซ้ำ
            // วาดซ้ำจะสร้าง input ชุดใหม่ ซึ่งทำให้ตัวจำค่าของหน้าตรวจเห็นเป็น "ถูกแก้"
            // ส่วนแถววินิจฉัยที่โคลนมาจาก <template> ไม่มีป้ายมาด้วย จึงต้องวาดให้
            if (!box.querySelector('.icd10-chips input[type=hidden]')) render(box);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { boot(document); });
    } else {
        boot(document);
    }

    // แถววินิจฉัยที่เพิ่งกด "เพิ่มอีกรายการ" ถูกโคลนเข้ามาทีหลัง ต้องวาดป้ายให้ด้วย
    if ('MutationObserver' in window) {
        new MutationObserver(function (muts) {
            muts.forEach(function (m) {
                Array.prototype.forEach.call(m.addedNodes, function (n) {
                    if (!n || n.nodeType !== 1) return;
                    if (n.classList && n.classList.contains('icd10-picker')) boot(n.parentNode);
                    else boot(n);
                });
            });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }
})();
</script>
@endonce
