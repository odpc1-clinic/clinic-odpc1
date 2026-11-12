{{-- Realtime search (ทะเบียนผู้ป่วย / Visit / Lab)
     - หน้าที่มี <div id="rt-results"> ครอบตารางผล -> ใช้ AJAX สลับเฉพาะผลลัพธ์ (ไม่ reload ทั้งหน้า)
       => ช่องพิมพ์ไม่เสียโฟกัส เลขที่พิมพ์ทีละตัว (HN/เลขบัตร) ไม่หาย ไม่ search ผิดคน
     - หน้าที่ไม่มี #rt-results (เช่น Visit) -> submit ฟอร์มปกติ
     คลาสที่ใช้:
       input.js-realtime-search : ช่องพิมพ์ (debounce)
       .js-realtime-submit      : select / native date (เปลี่ยนแล้วยิงทันที)
       .js-realtime-date        : ช่องวันที่ bootstrap-datepicker (jQuery changeDate) --}}
<style>#rt-results.rt-loading{opacity:.55;transition:opacity .12s;}</style>
<script>
(function () {
    var RESULTS_ID = 'rt-results';
    var controller = null;

    function buildUrl(form) {
        var params = new URLSearchParams();
        new FormData(form).forEach(function (v, k) { if (v !== null && v !== '') params.append(k, v); });
        var qs = params.toString();
        return form.getAttribute('action') + (qs ? ('?' + qs) : '');
    }

    function run(form) {
        if (!form) return;
        // หน้า Lab แปลงวันที่ AD->BE ก่อน (เหมือนปุ่มค้นหาเดิม)
        if (typeof window.convertDates === 'function') { try { window.convertDates(); } catch (e) {} }

        var results = document.getElementById(RESULTS_ID);
        var url = buildUrl(form);

        // ไม่มี container ผลลัพธ์ (เช่นหน้า Visit) -> submit ปกติ
        if (!results) { form.submit(); return; }

        if (controller) controller.abort();
        controller = new AbortController();
        results.classList.add('rt-loading');

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: controller.signal
        })
        .then(function (r) { return r.text(); })
        .then(function (html) {
            var fresh = new DOMParser().parseFromString(html, 'text/html').getElementById(RESULTS_ID);
            var cur = document.getElementById(RESULTS_ID);
            if (fresh && cur) {
                cur.innerHTML = fresh.innerHTML;
                cur.classList.remove('rt-loading');
                try { history.replaceState(null, '', url); } catch (e) {}
                // แจ้งให้หน้าที่มีตาราง (เช่น DataTables หน้าผู้ป่วย) init ใหม่หลังสลับผล
                document.dispatchEvent(new CustomEvent('rt:updated'));
            }
        })
        .catch(function () { if (results) results.classList.remove('rt-loading'); });
    }

    function wire() {
        document.querySelectorAll('input.js-realtime-search').forEach(function (input) {
            var form = input.form; if (!form) return;
            var delay = parseInt(input.getAttribute('data-delay') || '350', 10);
            var timer = null;
            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { run(form); }, delay);
            });
        });
        document.querySelectorAll('.js-realtime-submit').forEach(function (el) {
            var form = el.form; if (!form) return;
            el.addEventListener('change', function () { run(form); });
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', wire);
    else wire();

    // ช่องวันที่ bootstrap-datepicker (หน้า Visit) ผ่าน jQuery event changeDate
    if (window.jQuery) {
        jQuery(function ($) {
            $('.js-realtime-date').on('changeDate', function () {
                run(this.form || $(this).closest('form')[0]);
            });
        });
    }
})();
</script>
