{{-- สคริปต์ของกล่อง "สั่งยาครั้งนี้" — อยู่ในหน้าที่โหลดเต็ม (บัตร TB 01 / หน้าตรวจ ไม่ได้มาทาง AJAX) จึงรันได้ปกติ
     หน้าตรวจ (data-embed=1): กล่องเป็น <div> ไม่ใช่ฟอร์ม — ปุ่มบันทึกส่งด้วย fetch แล้วเอารายการยาใหม่มาแทนที่ในหน้า --}}
<script>
(function () {
    var form = document.getElementById('tb-order');
    if (!form) return;

    var EMBED  = form.dataset.embed === '1';
    var RULES  = JSON.parse(form.dataset.rules || '{}');            // { adult: {key: rule}, ped: {...} }
    // B6 เฉพาะสูตรที่มี Isoniazid / Cycloserine ตามมาตรฐาน — ตรงกับ TbRegistration::needsB6() (2569-10-12)
    var B6_WITH = JSON.parse(form.dataset.b6With || '[]');
    var PER    = { OD: 1, BID: 2, TID: 3, QID: 4, QW: 1 / 7, TIW: 3 / 7, PRN: null };
    var PDAY   = { 1: 'OD', 2: 'BID', 3: 'TID', 4: 'QID' };
    var wEl    = document.getElementById('tb-ord-weight');
    var typeEl = document.getElementById('tb-ord-type');
    var dateEl = document.getElementById('tb-ord-date');
    var daysEl = document.getElementById('tb-ord-days');
    var lblEl  = document.getElementById('tb-ord-label');
    var regEl  = document.getElementById('tb-ord-regimen');
    var custom = document.getElementById('tb-ord-custom');
    var body   = document.getElementById('tb-ord-body');
    var others = document.getElementById('tb-ord-others');
    var ORIG_DRUGS = JSON.parse(form.dataset.origDrugs || '[]').slice().sort().join(',');

    function num(v) {
        v = String(v == null ? '' : v).replace(/\s+/g, '').replace(',', '.');
        var f = v.match(/^(\d+)\/(\d+)$/);
        if (f) return +f[2] > 0 ? f[1] / f[2] : null;
        return /^\d+(\.\d+)?$/.test(v) ? parseFloat(v) : null;
    }
    function fmt(n) { return String(Math.round(n * 100) / 100); }
    function inputs(tr) { return tr.querySelectorAll('input, select, textarea'); }

    /* ---------- ② วันนัด ↔ จำนวนวัน (วันที่ พ.ศ. วว/ดด/ปปปป) ---------- */
    function parseTh(s) {
        var m = String(s || '').trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (!m) return null;
        var y = +m[3]; if (y > 2400) y -= 543;
        var d = new Date(y, m[2] - 1, +m[1]);
        return d.getMonth() === m[2] - 1 ? d : null;
    }
    function toTh(d) {
        return ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + (d.getFullYear() + 543);
    }
    var TODAY = parseTh(form.dataset.today) || new Date();
    function days() { var d = parseInt(daysEl.value, 10); return d > 0 ? d : 0; }

    function fromDate() {
        var d = parseTh(dateEl.value), warn = document.getElementById('tb-ord-date-warn');
        if (!d) return;
        var n = Math.round((d - TODAY) / 86400000);
        warn.classList.toggle('d-none', n >= 1);
        if (n >= 1) { daysEl.value = Math.min(n, 120); }
        afterDays();
    }
    function fromDays() {
        var n = days();
        if (n < 1) return;
        var d = new Date(TODAY.getTime()); d.setDate(d.getDate() + n);
        dateEl.value = toTh(d);
        if (window.jQuery && jQuery.fn.datepicker && jQuery(dateEl).data('datepicker')) { jQuery(dateEl).datepicker('update', dateEl.value); }
        document.getElementById('tb-ord-date-warn').classList.add('d-none');
        afterDays();
    }
    function afterDays() { autoLabel(); recalcAll(); }
    dateEl.addEventListener('change', fromDate);
    dateEl.addEventListener('blur', fromDate);
    daysEl.addEventListener('input', fromDays);
    // หน้าตรวจไม่มีตัวผูกปฏิทินให้ .tb-date (หน้าบัตรผูกเองท้ายหน้า) — ใช้ตัวผูกกลางที่รอไฟล์ภาษา th-th ให้
    if (EMBED && window.bindThDatepicker) { window.bindThDatepicker(dateEl, { autoclose: true, todayHighlight: true }); }
    // ปฏิทินยิง event ผ่าน jQuery (addEventListener ไม่ได้ยิน) — รอ jQuery/ปฏิทินพร้อมก่อนค่อยผูก
    // หน้าตรวจมี jQuery 2 ตัว (app.js + CDN) — ปฏิทินอยู่บนตัวที่โหลดทีหลัง ถ้าผูกตอน jQuery ตัวแรกพร้อม event ปฏิทินจะไม่ถึง
    // (เลือก 1 ธ.ค. จากปฏิทิน วันที่ขึ้นแต่จำนวนวัน/เม็ดยังคิดจากวันเดิม · บั๊ก 2569-10-29) → รอจนตัวที่มีปฏิทินภาษาไทยพร้อมค่อยผูก
    (function hook(t) {
        var J = window.jQuery;
        if (J && J.fn.datepicker && (J.fn.datepicker.dates || {})['th-th']) {
            J(dateEl).on('changeDate hide', function () { setTimeout(fromDate, 0); });
            return;
        }
        if (t < 100) setTimeout(function () { hook(t + 1); }, 200);
    })(0);

    /* หน้าตรวจ: ลง/แก้วันในกล่อง "วันนัด" → วันนัดครั้งหน้าของกล่องนี้ตามทันที ไม่ต้องโหลดหน้าใหม่ (คลินิกขอ 2569-10-29)
       ใช้นัดที่ใกล้ที่สุดหลังวันนี้ · ไม่มีวันนัดหลังวันนี้ = ไม่แตะ · ปฏิทินยิงผ่าน jQuery ตัวที่มีปฏิทิน (ดู hook ข้างบน) */
    function fromApptBlock() {
        if (form.dataset.locked === '1') return;
        var best = null;
        document.querySelectorAll('#appt-table input[name="appt_date[]"]').forEach(function (i) {
            var d = parseTh(i.value);
            if (d && d > TODAY && (!best || d < best)) best = d;
        });
        if (!best || toTh(best) === dateEl.value.trim()) return;
        dateEl.value = toTh(best);
        if (window.jQuery && jQuery.fn.datepicker && jQuery(dateEl).data('datepicker')) { jQuery(dateEl).datepicker('update', dateEl.value); }
        var src = document.getElementById('tb-ord-date-src');
        if (src) src.textContent = 'ตามกล่องวันนัด ' + dateEl.value;
        fromDate();
    }
    if (EMBED) {
        document.addEventListener('change', function (e) {
            if (e.target && e.target.matches && e.target.matches('#appt-table input[name="appt_date[]"]')) setTimeout(fromApptBlock, 0);
        });
        // ลบแถวนัด = นัดที่เหลือใกล้สุดอาจเปลี่ยน
        document.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('#appt-table .js-appt-del')) setTimeout(fromApptBlock, 50);
        });
        (function hookAppt(t) {
            var J = window.jQuery;
            if (J && J.fn.datepicker && (J.fn.datepicker.dates || {})['th-th']) {
                // เฉพาะ changeDate (เลือกวันจริง) — ไม่ใช้ hide: แค่เปิดปิดปฏิทินนัดจะไปทับวันที่พยาบาลตั้งใจแก้ในกล่องนี้
                J(document).on('changeDate', '#appt-table .appt-date', function () { setTimeout(fromApptBlock, 0); });
                return;
            }
            if (t < 100) setTimeout(function () { hookAppt(t + 1); }, 200);
        })(0);
    }

    /* รอบตั้งต้นเป็นแถวถัดไปแบบตารางเดิม · ถ้าแถวถัดไปคือ "2 สัปดาห์" แต่จ่ายเกิน 3 สัปดาห์ = รอบเดือนที่ 1 */
    // แก้รอบที่สั่งไว้วันนี้ (data-fixed) หรือเลือกรอบเองแล้ว = ไม่เปลี่ยนให้ · ไม่มีบัตร = ไม่มีช่องรอบ
    var lblTouched = !lblEl || lblEl.dataset.fixed === '1' || lblEl.value !== lblEl.dataset.default;
    function autoLabel() {
        if (lblTouched) return;
        var def = lblEl.dataset.default, want = def;
        if (def === '2wk' && days() > 21 && lblEl.querySelector('option[value="1"]')) want = '1';
        if (lblEl.querySelector('option[value="' + want + '"]')) lblEl.value = want;
    }
    if (lblEl) lblEl.addEventListener('change', function () {
        lblTouched = true;
        var c = lblEl.value === '__custom';
        document.getElementById('tb-ord-label-custom').classList.toggle('d-none', !c);
        document.getElementById('tb-ord-month').classList.toggle('d-none', !c);
        if (c) document.getElementById('tb-ord-label-custom').focus();
    });
    // Off สูตรเดิมแล้วเริ่มนับใหม่ → รอบ 0 (รอบ 0 เดิมถูกใช้ไปแล้วในช่วงก่อน จึงอาจไม่มีในรายการ ต้องเติมให้) · ยกเลิก = กลับรอบตั้งต้น
    var rsEl = document.getElementById('tb-ord-restart');
    if (rsEl && lblEl) rsEl.addEventListener('change', function () {
        var zero = lblEl.querySelector('option[value="0"]');
        if (rsEl.checked) {
            if (!zero) { zero = new Option('เดือนที่ 0 (เริ่มนับใหม่)', '0'); zero.dataset.restart = '1'; lblEl.insertBefore(zero, lblEl.firstChild); }
            lblEl.value = '0';
        } else {
            if (zero && zero.dataset.restart === '1') zero.remove();
            if (lblEl.querySelector('option[value="' + lblEl.dataset.default + '"]')) lblEl.value = lblEl.dataset.default;
        }
        lblTouched = rsEl.checked;
        document.getElementById('tb-ord-label-custom').classList.add('d-none');
        document.getElementById('tb-ord-month').classList.add('d-none');
    });

    /* ---------- ③ สูตรยา ---------- */
    function isCustom() { return regEl.value === 'custom'; }
    function activeGroup() { return custom.dataset.active; }
    function selectedKeys() {
        var keys = [];
        if (isCustom()) {
            custom.querySelectorAll('.js-ord-drug:checked:not([disabled])').forEach(function (c) {
                if (keys.indexOf(c.value) === -1) keys.push(c.value);
            });
        } else if (regEl.value) {
            var o = regEl.options[regEl.selectedIndex];
            try { keys = JSON.parse(o.dataset.drugs || '[]'); } catch (e) { keys = []; }
        }
        keys = keys.filter(function (k) { return k !== 'B6'; });
        var needB6 = keys.some(function (k) { return B6_WITH.indexOf(k) !== -1; });
        if (needB6 && form.dataset.b6Off !== '1') keys.push('B6');
        var b6on = document.getElementById('tb-ord-b6-on');
        if (b6on) b6on.classList.toggle('d-none', !(needB6 && form.dataset.b6Off === '1'));
        return keys;
    }
    function setGroup(g) {
        custom.dataset.active = g;
        custom.querySelectorAll('.js-ord-grp').forEach(function (box) {
            var on = box.dataset.group === g;
            box.classList.toggle('d-none', !on);
            box.querySelector('.js-ord-grp-switch').classList.toggle('d-none', !on);
            box.querySelector('.js-ord-grp-use').classList.toggle('d-none', on);
            box.querySelectorAll('.js-ord-drug').forEach(function (c) {
                c.disabled = !on || !isCustom();
                if (!on) c.checked = false;   // ผู้ป่วยหนึ่งคนใช้ยากลุ่มเดียว (2569-09-29)
            });
        });
    }
    function applyRegimen() {
        var cu = isCustom();
        custom.classList.toggle('d-none', !cu);
        custom.querySelectorAll('input[name="regimen_text"]').forEach(function (i) { i.disabled = !cu; });
        custom.querySelectorAll('.js-ord-grp-extra .js-ord-drug').forEach(function (c) { c.disabled = !cu; });
        setGroup(activeGroup());

        var keys = selectedKeys();
        body.querySelectorAll('tr.js-ord-row').forEach(function (tr) {
            var on = keys.indexOf(tr.dataset.key) !== -1;
            tr.classList.toggle('d-none', !on);
            inputs(tr).forEach(function (i) { i.disabled = !on; });
        });
        // เรียงแถวตามลำดับยาในสูตร (B6 ท้ายสุด)
        keys.forEach(function (k) {
            var tr = body.querySelector('tr.js-ord-row[data-key="' + k + '"]');
            if (tr) body.appendChild(tr);
        });

        // เลือกต่างจากสูตรที่ใช้อยู่ = จะขึ้นช่วงสูตรใหม่ → โชว์ช่องเหตุผล
        var changed = regEl.value !== form.dataset.orig
            || (cu && keys.filter(function (k) { return k !== 'B6'; }).slice().sort().join(',') !== ORIG_DRUGS);
        var rw = document.getElementById('tb-ord-reason-wrap');   // ไม่มีบัตร = ไม่มีช่องเหตุผล
        if (rw) rw.classList.toggle('d-none', !changed || !regEl.value);
        recalcAll();
    }
    regEl.addEventListener('change', applyRegimen);
    // ปุ่ม "สิ้นสุดระยะเข้มข้น → ระยะต่อเนื่อง" (2569-10-28) — เลือกสูตรระยะต่อเนื่องให้ แล้วคำนวณยาใหม่ (ยังไม่บันทึกจนกดบันทึก)
    form.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('.js-ord-cont');
        if (!b) return;
        regEl.value = b.dataset.key;
        regEl.dispatchEvent(new Event('change', { bubbles: true }));
        b.classList.remove('btn-warning'); b.classList.add('btn-success');
        b.innerHTML = '<i class="fa fa-check me-1"></i> เลือกระยะต่อเนื่องแล้ว — ตรวจยาแล้วกดบันทึกการสั่งยา';
    });
    custom.addEventListener('change', function (e) {
        var c = e.target;
        if (!c.classList.contains('js-ord-drug')) return;
        // 4FDC (เดือน 1-2) กับ 2FDC (เดือน 3-6) รอบเดียวกันใช้ได้ตัวเดียว
        var pair = { '4FDC': '2FDC', '2FDC': '4FDC' }[c.value];
        if (pair && c.checked) {
            custom.querySelectorAll('.js-ord-drug[value="' + pair + '"]').forEach(function (o) { o.checked = false; });
        }
        applyRegimen();
    });
    custom.addEventListener('click', function (e) {
        var a = e.target.closest('.js-ord-grp-switch, .js-ord-grp-use');
        if (!a) return;
        e.preventDefault();
        if (a.classList.contains('js-ord-grp-switch')) {
            // โชว์กลุ่มอื่นให้เลือก (ยังไม่เปลี่ยนจนกว่าจะกด "ใช้กลุ่มนี้แทน")
            custom.querySelectorAll('.js-ord-grp').forEach(function (b) { b.classList.remove('d-none'); });
            return;
        }
        var g = a.closest('.js-ord-grp').dataset.group;
        if (!confirm('ใช้ยากลุ่มนี้แทน? ยาที่ติ๊กไว้ในกลุ่มเดิมจะถูกล้าง')) return;
        setGroup(g);
        applyRegimen();
    });

    /* ---------- ④ เกณฑ์ตามน้ำหนัก → จำนวนต่อครั้ง → จ่ายทั้งหมด ---------- */
    // ยาป้องกัน (TPT) ตัดอายุตามสูตร (age_cut) ไม่ใช่ 18 ปี — ไม่ทราบอายุ = ใช้เกณฑ์ตามกล่องเดิม
    var AGE = form.dataset.age === '' || form.dataset.age == null ? null : parseInt(form.dataset.age, 10);
    function rule(key) {
        var base = ((RULES[typeEl.value] || {})[key]) || null;
        var any = base || (RULES.adult || {})[key] || (RULES.ped || {})[key];
        if (any && any.age_cut && AGE !== null && !isNaN(AGE)) {
            return ((RULES[AGE >= any.age_cut ? 'adult' : 'ped'] || {})[key]) || null;
        }
        return base;
    }
    function inBand(b, w) { return (b.min === null || w >= b.min) && (b.max === null || w <= b.max); }
    // คิดแบบเดียวกับตัวคำนวณเดิม: ข้อความรายช่วงน้ำหนัก ("3 เม็ด/วัน") → ข้อความรวม (Bdq) → มก. ตามช่วง → มก./กก. (มีเพดาน)
    function recommend(r, w) {
        if (!r || !w) return null;
        var t = null;
        (r.bands || []).forEach(function (b) { if (t === null && b.text && inBand(b, w)) t = b.text; });
        // ข้อความรวมใช้เฉพาะเกณฑ์ที่ไม่แบ่งช่วงน้ำหนัก — มีช่วงแต่น้ำหนักไม่เข้าช่วงไหน = ไม่แนะนำ
        // (เม็ดรวม 3HP ตั้งไว้เฉพาะ ≥30 กก. เดิมขึ้น "3 เม็ด" ให้เด็ก 14 กก. ด้วย — 2569-11-03)
        var banded = (r.bands || []).some(function (b) { return b.min !== null || b.max !== null; });
        if (t === null && r.dose_text && !banded) t = r.dose_text;
        if (t !== null) {
            var m = String(t).match(/^\s*([\d.]+)\s*เม็ด\/(วัน|ครั้ง)\s*$/);
            return { text: t, tabs: m ? parseFloat(m[1]) : null, mg: null };
        }
        var dose = null, capped = false;
        (r.bands || []).forEach(function (b) { if (dose === null && b.dose && inBand(b, w)) dose = b.dose; });
        if (dose === null && r.mgkg_min) dose = Math.round(w * r.mgkg_min / 10) * 10;
        if (dose === null) return null;
        if (r.max_mg && dose > r.max_mg) { dose = r.max_mg; capped = true; }
        return { text: dose + ' มก./วัน' + (capped ? ' (เพดาน)' : ''), tabs: null, mg: dose };
    }
    function freqOf(tr) { var s = tr.querySelector('.js-ord-freq'); return s ? s.value : 'OD'; }
    /* จำนวนครั้งถึงวันนัด — ตรงกับ TbRegistration::doseTimes ฝั่งเซิร์ฟเวอร์
       รายสัปดาห์นับวันนี้เป็นครั้งที่ 1 ถึงวันนัดรวมสองหัว (2569-10-27: 3HP อังคาร นัดอังคารที่ 12 = 12 ครั้ง)
       ติ๊กวันไว้ = นับตามปฏิทินจริง */
    var WD_NUM = { 'อา': 0, 'จ': 1, 'อ': 2, 'พ': 3, 'พฤ': 4, 'ศ': 5, 'ส': 6 };
    function timesOf(tr, p, d) {
        if (!p || !d) return null;
        if (p >= 1) return p * d;
        var n = tr.querySelector('[name$="[note]"]'), parsed = n && typeof wdParse === 'function' ? wdParse(n.value) : null;
        if (parsed && parsed.days.length) {
            var want = parsed.days.map(function (x) { return WD_NUM[x]; }), c = 0;
            for (var i = 0; i <= d; i++) { var dt = new Date(TODAY.getFullYear(), TODAY.getMonth(), TODAY.getDate() + i); if (want.indexOf(dt.getDay()) !== -1) c++; }
            return c;
        }
        return freqOf(tr) === 'QW' ? Math.floor(d / 7) + 1 : Math.ceil(p * (d + 1) - 1e-9);
    }
    function mulOf(tr) { var p = PER[freqOf(tr)]; return p && p >= 1 ? p : 1; }   // รายสัปดาห์: ขนาดต่อครั้ง
    // ความแรงที่หารลงตัว: ที่คลินิกใช้ก่อน (INH 100/300) → ใหญ่สุดที่ลงตัว → คงที่เลือกไว้
    function pickStrength(tr, mg) {
        var s = tr.querySelector('.js-ord-str');
        if (!s) return null;
        if (tr.dataset.strTouched === '1' || !mg) return parseFloat(s.value);
        var opts = Array.prototype.map.call(s.options, function (o) { return parseFloat(o.value); });
        var even = function (x) { return Math.abs(mg / x - Math.round(mg / x)) < 1e-9; };
        var pref = parseFloat(tr.dataset.prefer);
        var pick = (pref && opts.indexOf(pref) !== -1 && even(pref)) ? pref
            : opts.slice().sort(function (a, b) { return b - a; }).filter(even)[0];
        if (pick) s.value = fmt(pick);
        return parseFloat(s.value);
    }

    function calcRow(tr, auto) {
        var kind = tr.dataset.kind, key = tr.dataset.key;
        var w = parseFloat(wEl.value), r = rule(key), rec = recommend(r, w);
        var qEl = tr.querySelector('.js-ord-qty'), mul = mulOf(tr);
        var ruleEl = tr.querySelector('.js-ord-rule'), warnEl = tr.querySelector('.js-ord-warn');

        // เติมจำนวนต่อครั้งให้เฉพาะแถวที่ยังไม่ได้พิมพ์เอง
        if (auto && tr.dataset.touched !== '1') {
            var q = null;
            if (rec) {
                if (kind === 'tab' && rec.tabs !== null) q = rec.tabs / mul;
                else if (kind === 'strength' && rec.mg) { var st = pickStrength(tr, rec.mg); if (st) q = rec.mg / st / mul; }
                else if (kind === 'mg' && rec.mg) q = rec.mg / mul;
            }
            if (q === null && tr.dataset.fallback) q = num(tr.dataset.fallback);
            if (q !== null) { qEl.value = fmt(q); tr.classList.add('tb-ord-auto'); }
        }

        // เกณฑ์ใต้ชื่อยา เช่น "เกณฑ์ 52 กก.: 3 เม็ด/วัน" · "600 มก./วัน (= 2 × 300 มก.)"
        ruleEl.innerHTML = '';
        if (rec) {
            var b = document.createElement('span');
            b.className = 'text-success fw-semibold';
            var extra = '';
            if (kind === 'strength' && rec.mg) {
                var s2 = tr.querySelector('.js-ord-str'), sv = s2 ? parseFloat(s2.value) : null;
                if (sv) extra = ' (= ' + fmt(rec.mg / sv) + ' × ' + fmt(sv) + ' มก.)';
            }
            b.textContent = 'เกณฑ์ ' + w + ' กก.: ' + rec.text + extra;
            ruleEl.appendChild(b);
        } else if (r && !w) {
            ruleEl.innerHTML = '<span class="text-muted">กรอกน้ำหนักเพื่อดูเกณฑ์</span>';
        } else if (!r) {
            ruleEl.innerHTML = '<span class="text-muted">ไม่มีเกณฑ์ตามน้ำหนัก — กรอกเอง</span>';
        }

        // ต่อวันที่สั่ง เทียบเกณฑ์ (เตือนเท่านั้น หมอสั่งต่างจากเกณฑ์ได้)
        var qn = num(qEl.value), perDay = null, recDay = null, unit = '';
        if (qn !== null) {
            if (kind === 'tab') { perDay = qn * mul; recDay = rec ? rec.tabs : null; unit = 'เม็ด'; }
            else if (kind === 'strength') { var s3 = tr.querySelector('.js-ord-str'); perDay = qn * parseFloat(s3 ? s3.value : 0) * mul; recDay = rec ? rec.mg : null; unit = 'มก.'; }
            else { perDay = qn * mul; recDay = rec ? rec.mg : null; unit = 'มก.'; }
        }
        var off = perDay !== null && recDay && Math.abs(perDay - recDay) / recDay > 0.10;
        warnEl.textContent = off ? 'สั่ง ' + fmt(perDay) + ' ' + unit + (mul === 1 && PER[freqOf(tr)] < 1 ? '/ครั้ง' : '/วัน') + ' — ไม่ตรงเกณฑ์' : '';

        pharmHint(tr);

        // จ่ายทั้งหมด = ครั้งละ × ครั้งต่อวัน × วัน (รายสัปดาห์ปัดจำนวนครั้งขึ้น) — ยา มก. ให้ห้องยาคิดเอง
        var tot = tr.querySelector('.js-ord-total'), p = PER[freqOf(tr)], d = days();
        if (qn === null || qn <= 0) { tot.textContent = '—'; return; }
        if (kind === 'mg') { tot.textContent = fmt(qn * mul) + ' มก./' + (p && p < 1 ? 'ครั้ง' : 'วัน'); return; }
        if (!p || !d) { tot.textContent = '—'; return; }
        var times = timesOf(tr, p, d);
        // เม็ดครึ่ง/ครึ่งเม็ด: ตัดสต๊อกเต็มเม็ดต่อครั้ง (หักครึ่งที่เหลือทิ้ง — คลินิกขอ 2569-10-12)
        tot.textContent = fmt(Math.ceil(qn - 1e-9) * times) + ' เม็ด';
    }
    /* หน้าตรวจ: ยาตัวนี้ตรงกับยาไหนในคลังยา — ไม่มี = เตือนก่อนกดบันทึก (ไม่ลงรายการยา การเงินไม่เห็น) */
    var PHARM = {};
    try { PHARM = JSON.parse(form.dataset.pharm || '{}'); } catch (e) { PHARM = {}; }
    var MEDS = {};
    (function () {
        var dl = document.getElementById('tb-ord-medlist');
        if (dl) Array.prototype.forEach.call(dl.options, function (o) { MEDS[collapse(o.value)] = o.value; });
    })();
    function collapse(s) { return String(s || '').toLowerCase().replace(/[^a-z0-9]/g, ''); }
    function setHint(el, ok, text) {
        el.className = 'small js-ord-pharm ' + (ok ? 'text-muted' : 'text-danger fw-semibold');
        el.textContent = text;
    }
    function pharmHint(tr) {
        var el = tr.querySelector('.js-ord-pharm');
        if (!EMBED || !el) return;
        var map = PHARM[tr.dataset.key] || {}, s = tr.querySelector('.js-ord-str');
        var hit = s ? map[fmt(parseFloat(s.value))] : map['*'];
        if (hit && hit.id) setHint(el, true, 'คลังยา: ' + hit.name);
        else setHint(el, false, '⚠ ' + ((hit && hit.why) || 'ยังไม่มีในคลังยา') + ' — จะไม่ลงรายการยา/คิดเงิน ห้องยาต้องเพิ่มเอง');
    }
    function otherHint(tr) {
        var el = tr.querySelector('.js-ord-pharm'), n = tr.querySelector('.js-ord-other-name');
        if (!EMBED || !el || !n) return;
        if (!n.value.trim()) { el.textContent = ''; return; }
        var c = collapse(n.value);
        if (c && MEDS[c]) setHint(el, true, 'คลังยา: ' + MEDS[c]);
        else setHint(el, false, '⚠ ไม่ตรงกับชื่อยาในคลังยา — เลือกจากรายการ ไม่งั้นไม่ลงรายการยา');
    }

    function calcOther(tr) {
        otherHint(tr);
        var qEl = tr.querySelector('.js-ord-qty');
        // ว่าง = ครั้งละ 1 (ตามเลขจางในช่อง) — ตรงกับฝั่งเซิร์ฟเวอร์ buildOrder
        var qn = qEl.value.trim() === '' ? 1 : num(qEl.value), p = PER[freqOf(tr)], d = days();
        var unit = tr.querySelector('[name$="[unit]"]').value;
        var disp = tr.querySelector('.js-ord-dispense'), tot = tr.querySelector('.js-ord-total');
        var qd = unit === 'เม็ด' && qn ? Math.ceil(qn - 1e-9) : qn;   // เม็ดครึ่งตัดเต็มเม็ด
        var auto = (qn && p && d) ? fmt(qd * timesOf(tr, p, d)) : '';
        // หลอด/ขวด (ยาทา ยาน้ำ) ไม่ใส่จำนวนจ่าย = 1 — ตรงกับฝั่งเซิร์ฟเวอร์ TbVisitOrder::visitOrderLines
        if (unit === 'หลอด' || unit === 'ขวด') auto = '1';
        disp.placeholder = auto || 'จำนวน';
        tot.textContent = disp.value.trim() ? 'จ่าย ' + disp.value.trim() + ' ' + unit : (auto ? 'คิดได้ ' + auto + ' ' + unit : 'เมื่อมีอาการ: ใส่จำนวนจ่าย');
    }
    function recalcAll(auto) {
        body.querySelectorAll('tr.js-ord-row:not(.d-none)').forEach(function (tr) { calcRow(tr, auto !== false); });
        others.querySelectorAll('tr.js-ord-other').forEach(calcOther);
    }

    // ติ๊กช่วงเวลา n ช่อง = วันละ n ครั้ง (ไม่ยุ่งถ้าตั้งเป็นรายสัปดาห์/เมื่อมีอาการไว้) — เหมือนกล่องวิธีใช้เดิม
    function syncFreq(tr) {
        var n = tr.querySelectorAll('[name$="[period][]"]:checked').length, sel = tr.querySelector('.js-ord-freq');
        if (sel && PDAY[n] && ['OD', 'BID', 'TID', 'QID'].indexOf(sel.value) !== -1) sel.value = PDAY[n];
    }

    form.addEventListener('input', function (e) {
        var tr = e.target.closest('tr');
        if (e.target === wEl) { recalcAll(); return; }
        if (!tr) return;
        if (e.target.classList.contains('js-ord-qty') && tr.classList.contains('js-ord-row')) {
            tr.dataset.touched = '1'; tr.classList.remove('tb-ord-auto');
        }
        if (tr.classList.contains('js-ord-row')) calcRow(tr, false); else if (tr.classList.contains('js-ord-other')) calcOther(tr);
    });
    /* ---------- ติ๊กวันที่กิน → ความถี่ + เขียนวันไว้หน้าช่อง "พิมพ์เพิ่มเอง" (2569-10-22) ----------
       พยาบาลสั่ง BDQ จ-พ-ศ แล้วหา TIW ในรายการไม่เจอ ได้วันละครั้งไปทั้งที่ตั้งใจสัปดาห์ละ 3
       จำนวนจ่ายคิดได้เฉพาะ 1 วัน (QW) / 3 วัน (TIW) / ทุกวัน — วันอื่น ๆ บอกให้รู้ ไม่เดาความถี่ให้ */
    var WD = ['จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส', 'อา'];
    // ของเดิมที่พิมพ์มือมีทั้ง "จ-พ-ศ" และ "จันทร์ พุธ ศุกร์" — อ่านได้ทั้งสองแบบ (ชื่อยาวต้องมาก่อนชื่อย่อ)
    var WD_FULL = { 'จันทร์': 'จ', 'อังคาร': 'อ', 'พุธ': 'พ', 'พฤหัสบดี': 'พฤ', 'พฤหัส': 'พฤ', 'ศุกร์': 'ศ', 'เสาร์': 'ส', 'อาทิตย์': 'อา' };
    var WD_TOK = '(?:จันทร์|อังคาร|พุธ|พฤหัสบดี|พฤหัส|ศุกร์|เสาร์|อาทิตย์|อา|พฤ|จ|อ|พ|ศ|ส)';
    var WD_RE = new RegExp('^(' + WD_TOK + '(?:\\s*[-,]\\s*|\\s+)?)+(?=\\s|$)');
    function wdNote(tr) { return tr.querySelector('[name$="[note]"]'); }
    function wdChecks(tr) { return tr.querySelectorAll('.js-ord-wd input'); }
    function wdHint(tr, html) { var h = tr.querySelector('.js-ord-wd-hint'); if (h) h.innerHTML = html || ''; }
    function wdParse(v) {
        var m = String(v || '').match(WD_RE);
        if (!m) return null;
        var days = (m[0].match(new RegExp(WD_TOK, 'g')) || []).map(function (t) { return WD_FULL[t] || t; });
        return { days: days, rest: String(v).slice(m[0].length).trim() };
    }
    function wdSetNote(tr, days) {
        var n = wdNote(tr); if (!n) return;
        var p = wdParse(n.value), rest = p ? p.rest : n.value.trim();
        n.value = (days.length && days.length < 7 ? days.join('-') + (rest ? ' ' + rest : '') : rest);
    }
    function wdInit(tr) {
        // ติ๊กให้เฉพาะแถวที่ตั้งเป็นรายสัปดาห์อยู่แล้ว — ความถี่รายวันแต่หมายเหตุค้างวันไว้ (เช่น ดึงยาเดิมมา) ไม่ติ๊ก กันกดแล้วกลายเป็นเอาออก
        var sel = tr.querySelector('.js-ord-freq'), weekly = sel && ['QW', 'TIW'].indexOf(sel.value) !== -1;
        var n = wdNote(tr), p = n && weekly ? wdParse(n.value) : null, on = p ? p.days : [];
        wdChecks(tr).forEach(function (c) { c.checked = on.indexOf(c.value) !== -1; });
    }
    function wdChanged(tr) {
        var days = Array.prototype.filter.call(wdChecks(tr), function (c) { return c.checked; }).map(function (c) { return c.value; });
        var sel = tr.querySelector('.js-ord-freq'), daily = ['OD', 'BID', 'TID', 'QID'];
        wdSetNote(tr, days);
        wdHint(tr, '');
        if (!sel) return;
        if (days.length === 0 || days.length === 7) { if (daily.indexOf(sel.value) === -1) sel.value = 'OD'; }
        else if (days.length === 3) sel.value = 'TIW';
        else if (days.length === 1) sel.value = 'QW';
        else wdHint(tr, '<span class="text-danger">' + days.length + ' วัน/สัปดาห์ ระบบคิดจำนวนจ่ายไม่ได้ — เลือก 1 วัน, 3 วัน หรือไม่ติ๊ก (ทุกวัน)</span>');
    }
    function freqChanged(tr) {
        var sel = tr.querySelector('.js-ord-freq'); if (!sel) return;
        if (['OD', 'BID', 'TID', 'QID'].indexOf(sel.value) !== -1) {
            wdChecks(tr).forEach(function (c) { c.checked = false; }); wdSetNote(tr, []); wdHint(tr, '');
        } else if (sel.value === 'TIW' && !Array.prototype.some.call(wdChecks(tr), function (c) { return c.checked; })) {
            // เลือก TIW จากรายการ ยังไม่ได้ติ๊กวัน — ตั้ง จ-พ-ศ ให้ (ที่คลินิกใช้จริง) แก้ได้
            ['จ', 'พ', 'ศ'].forEach(function (d) { wdChecks(tr).forEach(function (c) { if (c.value === d) c.checked = true; }); });
            wdSetNote(tr, ['จ', 'พ', 'ศ']);
        }
    }
    body.querySelectorAll('tr.js-ord-row').forEach(wdInit);

    form.addEventListener('change', function (e) {
        if (e.target === typeEl) { recalcAll(); return; }
        var tr = e.target.closest('tr');
        if (!tr) return;
        if (e.target.closest('.js-ord-wd')) wdChanged(tr);
        if (e.target.classList.contains('js-ord-freq')) freqChanged(tr);
        if (/\[period\]\[\]$/.test(e.target.name || '')) syncFreq(tr);
        if (e.target.classList.contains('js-ord-str')) tr.dataset.strTouched = '1';
        // เปลี่ยนความถี่/ความแรง: แถวที่ยังเป็นค่าแนะนำคิดจำนวนต่อครั้งใหม่ · แถวที่พิมพ์เองคงไว้ แค่คิดยอดจ่ายใหม่
        if (tr.classList.contains('js-ord-row')) calcRow(tr, true); else if (tr.classList.contains('js-ord-other')) calcOther(tr);
    });

    /* B6: หมอยืนยันไม่ให้ตามสูตร = ซ่อนแถว (ไม่ส่งไปบันทึก) · กดให้กลับมาได้ (2569-10-14) */
    form.addEventListener('click', function (e) {
        if (e.target.closest('.js-ord-b6-off')) { form.dataset.b6Off = '1'; applyRegimen(); }
        else if (e.target.closest('#tb-ord-b6-on')) { form.dataset.b6Off = '0'; applyRegimen(); }
    });

    /* ยาอื่น: เพิ่ม/ลบแถว */
    var seq = 0;
    document.getElementById('tb-ord-add-other').addEventListener('click', function () {
        var html = document.getElementById('tb-ord-other-tpl').innerHTML.replace(/__N__/g, 'n' + Date.now() + (seq++));
        var holder = document.createElement('tbody');
        holder.innerHTML = html;
        var tr = holder.querySelector('tr');
        others.appendChild(tr);
        calcOther(tr);
        tr.querySelector('[name$="[name]"]').focus();
    });
    others.addEventListener('click', function (e) {
        var b = e.target.closest('.js-ord-other-del');
        if (b) b.closest('tr').remove();
    });

    /* ส่งฟอร์ม: ต้องเลือกสูตรและมีจำนวนยาอย่างน้อยหนึ่งตัว · Enter ในช่องตัวเลขไม่ไปกดบันทึกเอง */
    form.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT') e.preventDefault();
    });
    function validOrder() {
        if (!regEl.value) { alert('กรุณาเลือกสูตรยา (ขั้นที่ 1)'); regEl.focus(); return false; }
        if (isCustom() && !selectedKeys().length) { alert('Individualized — ติ๊กยาอย่างน้อย 1 ตัว'); return false; }
        var any = Array.prototype.some.call(form.querySelectorAll('.js-ord-qty:not([disabled])'), function (i) { return num(i.value); });
        if (!any) { alert('ยังไม่มีจำนวนยาเลย — กรอก "ครั้งละ" ของยาอย่างน้อย 1 ตัว'); return false; }
        if (days() < 1) { alert('กรุณาระบุจำนวนวันที่จ่ายยา'); daysEl.focus(); return false; }
        return true;
    }
    form.addEventListener('submit', function (e) {
        if (!validOrder()) e.preventDefault();
    });

    /* ---------- หน้าตรวจ: บันทึกด้วย fetch (กล่องไม่ใช่ฟอร์ม — อยู่ในฟอร์มหลักของหน้าตรวจ) ---------- */
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function showMsg(kind, html) {
        var box = document.getElementById('tb-ord-msg');
        if (box) box.innerHTML = '<div class="alert alert-' + kind + ' py-2 px-3 mb-0 mt-1">' + html + '</div>';
    }
    // ช่องของกล่องไม่ได้เป็นของฟอร์มใด (form="tb-ord-detached") — เก็บค่าเองแบบเดียวกับที่เบราว์เซอร์ส่งฟอร์ม
    function collect() {
        var fd = new FormData();
        form.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (el) {
            if (el.disabled) return;
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
            fd.append(el.name, el.value);
        });
        fd.append('page', form.dataset.page || '1');
        return fd;
    }
    var saveBtn = document.getElementById('tb-ord-save');
    // visit เก่า: กดปลดล็อก (ยืนยัน) แล้วสั่ง/แก้ยาได้ — ส่ง backfill=1 ให้เซิร์ฟเวอร์รู้ว่าตั้งใจ (2569-10-27)
    var unlockBtn = form.querySelector('.js-ord-unlock');
    if (EMBED && unlockBtn) unlockBtn.addEventListener('click', function () {
        if (!confirm('visit นี้เก่าแล้ว — ปลดล็อกเพื่อลงยาย้อนหลังตามวันของ visit นี้?\nรอบยาในบัตร TB 01 จะลงวันที่ของ visit นี้')) return;
        form.dataset.locked = '0';
        var h = document.createElement('input'); h.type = 'hidden'; h.name = 'backfill'; h.value = '1'; form.appendChild(h);
        if (saveBtn) saveBtn.disabled = false;
        var m = form.querySelector('.js-ord-lock-msg');
        if (m) m.innerHTML = '<span><i class="fa fa-unlock me-1"></i> ปลดล็อกแล้ว — ลงยาย้อนหลังตามวันของ visit นี้ กด "บันทึกการสั่งยา" เมื่อเสร็จ</span>';
        boxSnap = snapStr();
    });
    if (EMBED && saveBtn) saveBtn.addEventListener('click', function () { saveBox(null); });
    // after(ok) = เรียกต่อหลังบันทึก (ปุ่ม Save ของหน้าตรวจ) — ไม่มี = ปุ่มของกล่องเอง (โหลดหน้าใหม่แบบเดิม)
    function saveBox(after) {
        if (form.dataset.locked === '1' || !validOrder()) { if (after) after(false); return; }
        saveBtn.disabled = true;
        var old = saveBtn.innerHTML;
        saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> กำลังบันทึก…';
        var done = false;   // เซิร์ฟเวอร์ตอบว่าบันทึกแล้ว
        fetch(form.dataset.url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': form.dataset.token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin', body: collect()
        })
        .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'เซิร์ฟเวอร์ตอบผิดรูปแบบ (' + r.status + ')' }; }); })
        .then(function (res) {
            if (!res.ok) {
                // 422 ของ Laravel (validate) มี errors · ของเรามี message
                var m = res.message || 'บันทึกไม่สำเร็จ';
                if (res.errors) { var k = Object.keys(res.errors)[0]; if (k) m = res.errors[k][0]; }
                showMsg('danger', '<i class="fa fa-triangle-exclamation me-1"></i> ' + esc(m));
                if (after) after(false);
                return;
            }
            done = true;
            var html ='<i class="fa fa-circle-check me-1"></i> ' + esc(res.message);
            if (res.warnings && res.warnings.length) {
                html += '<div class="mt-1 text-danger"><b>ต้องจัดการเอง:</b><ul class="mb-0">'
                    + res.warnings.map(function (w) { return '<li>' + esc(w) + '</li>'; }).join('') + '</ul></div>';
            }
            showMsg(res.warnings && res.warnings.length ? 'warning' : 'success', html);
            // ตารางเดียว: ราคา/แถวในรายการยาคิดที่เซิร์ฟเวอร์ — โหลดหน้าใหม่ (หน้าตรวจจำที่พิมพ์ค้างไว้ให้) แล้วโชว์ข้อความเดิมต่อ
            if (form.dataset.unified === '1') {
                try { sessionStorage.setItem('tbOrdMsg', JSON.stringify({ kind: res.warnings && res.warnings.length ? 'warning' : 'success', html: html })); } catch (e) {}
                if (after) { after(true); return; }   // หน้าตรวจกำลังจะบันทึกแล้วโหลดใหม่เองอยู่แล้ว
                if (window.__visitDraftStash) { window.__visitDraftStash(); window.__visitDraftStashed = true; }
                location.reload();
                return;
            }
            // นัดที่ระบบลงให้ → ใส่ในกล่องวันนัดของหน้านี้ด้วย (ไม่โหลดหน้าใหม่) กันพยาบาลลงซ้ำ
            if (res.appointment && res.appointment.created && window.__apptInject) window.__apptInject(res.appointment.id, res.appointment.date);
            // รายการยาของ visit (ตารางเดียวกับปุ่ม Add New) — แทนที่ในหน้า ไม่ต้องโหลดใหม่
            var t = document.getElementById('visit-med-table');
            if (t && res.table) t.innerHTML = res.table;
            if (res.print) {
                document.getElementById('tb-ord-print').innerHTML = '<span class="small text-muted ms-2">พิมพ์ของรอบที่เพิ่งบันทึก:</span>'
                    + ' <a href="' + esc(res.print.labels) + '" target="_blank" class="btn btn-sm btn-success"><i class="fa fa-tag"></i> พิมพ์สติกเกอร์</a>'
                    + ' <a href="' + esc(res.print.sheet) + '" target="_blank" class="btn btn-sm btn-outline-success"><i class="fa fa-print"></i> พิมพ์ใบกินยา (มีรูป)</a>';
            }
            // ค่าที่บันทึกแล้วถือว่าพิมพ์เอง — เปลี่ยนน้ำหนักทีหลังจะไม่คิดทับ
            body.querySelectorAll('tr.js-ord-row:not(.d-none)').forEach(function (tr) { tr.dataset.touched = '1'; });
            boxSnap = snapStr();
            if (after) after(true);
        })
        .catch(function (err) {
            // แยก "ส่งไม่ถึงเซิร์ฟเวอร์" ออกจาก "บันทึกแล้วแต่หน้าจอสะดุด" — เดิมขึ้นว่าเชื่อมต่อไม่ได้ทั้งที่เซิร์ฟเวอร์บันทึกแล้ว (2569-10-27)
            console.error('TBORD', err && (err.stack || err.message));
            var net = !done && err && err.name === 'TypeError' && /fetch|network|load/i.test(err.message || '');
            showMsg(done ? 'warning' : 'danger', done
                ? 'บันทึกแล้ว แต่หน้าจอแสดงผลไม่สำเร็จ — กด F5 โหลดหน้าใหม่เพื่อดูรายการยา (ไม่ต้องกดบันทึกซ้ำ)'
                : (net ? 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ — ยังไม่ได้บันทึก ลองกดอีกครั้ง' : 'บันทึกไม่สำเร็จ (' + esc(err && err.message || 'ไม่ทราบสาเหตุ') + ') — กด F5 แล้วลองใหม่'));
            try { if (after) after(false); } catch (e2) {}
        })
        .then(function () { saveBtn.disabled = false; saveBtn.innerHTML = old; });
    }

    /* กดปุ่ม "บันทึก" ของหน้าตรวจ ทั้งที่แก้กล่องสั่งยาค้างไว้ (คลินิกแจ้ง 2569-10-27 — เพิ่มยาทา balm แล้วกดบันทึกหน้า ยาหาย)
       ช่องในกล่องไม่ได้อยู่ในฟอร์มหลัก (form="tb-ord-detached") ปุ่มของหน้าจึงไม่ส่งไปด้วย
       → บันทึกกล่องก่อน สำเร็จแล้วค่อยปล่อยให้หน้าบันทึกต่อ · ไม่สำเร็จ = หยุด บอกเหตุผลที่กล่อง (ไม่ทิ้งยาเงียบ ๆ) */
    var boxSnap = null, passMain = false;
    function snapStr() { var a = []; collect().forEach(function (v, k) { if (k !== 'page') a.push(k + '=' + v); }); return a.join('&'); }
    if (EMBED && saveBtn) document.addEventListener('submit', function (e) {
        var main = e.target;
        if (!main || main.id !== 'visit_edit_form' || passMain) { passMain = false; return; }
        if (form.dataset.locked === '1' || boxSnap === null || snapStr() === boxSnap) return;
        // ยังไม่เลือกสูตร + ไม่มีจำนวนยาสักตัว = ไม่ได้สั่งยา ปล่อยหน้าบันทึกไปเลย
        // กล่องเปลี่ยนเองได้จากวันนัดที่ตามบล็อกนัดหมาย — คนมาคัดกรอง (ไม่มีสูตรยา) เคยบันทึกหน้า/วันนัดไม่ได้ เด้ง "กรุณาเลือกสูตรยา" (คลินิกเจอ 2569-11-05)
        var anyQty = Array.prototype.some.call(form.querySelectorAll('.js-ord-qty:not([disabled])'), function (i) { return num(i.value); });
        if (!regEl.value && !anyQty) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        var submitter = e.submitter || null;
        form.scrollIntoView({ block: 'center' });
        saveBox(function (ok) {
            if (!ok) return;   // เหตุผลขึ้นในกล่องแล้ว (validOrder / ข้อความจากเซิร์ฟเวอร์) — หน้านี้ยังไม่ได้บันทึก
            passMain = true;
            if (main.requestSubmit) { main.requestSubmit(submitter && submitter.form === main ? submitter : undefined); } else { main.submit(); }
        });
    }, true);

    // ข้อความผลบันทึกรอบก่อนโหลดหน้าใหม่ (ตารางเดียว)
    try {
        var keep = sessionStorage.getItem('tbOrdMsg');
        if (keep) { sessionStorage.removeItem('tbOrdMsg'); keep = JSON.parse(keep); showMsg(keep.kind, keep.html); }
    } catch (e) {}
    // เริ่มต้น: แถวที่สั่งไว้แล้ววันนี้ (data-preset) ถือว่าพิมพ์เองแล้ว ไม่คิดทับ
    body.querySelectorAll('tr.js-ord-row[data-preset="1"]').forEach(function (tr) { tr.dataset.touched = '1'; });
    autoLabel();
    applyRegimen();
    // ค่าตั้งต้นหลังเติมอัตโนมัติ — ต่างจากนี้ตอนกดบันทึกหน้า = มีของในกล่องที่ยังไม่บันทึก
    if (EMBED) { try { boxSnap = snapStr(); } catch (e) { boxSnap = null; } }
})();
</script>
