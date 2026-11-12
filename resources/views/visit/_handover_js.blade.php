{{-- สไตล์ + ตัวจัดการกล่องส่งเวร (visit/_handover) — ต้องอยู่ในหน้าโหลดเต็ม (edit) ผูกที่ document ครั้งเดียว --}}
<style>
    .ho-banner { background:#fff7e6; border:2px solid #f59e0b; border-radius:12px; color:#78350f; }
    @media (min-width: 992px) { .ho-banner { margin-right:84px; } }   /* ไม่ให้ป้ายลอยขวา (QUEUE/NOTE) บังปุ่มปิดเรื่อง */
    .ho-banner .ho-item { background:#fff; }
    .ho-box { border:2px solid #475569; border-radius:.6rem; background:#f8fafc; overflow:hidden; }
    .ho-box .ho-head { background:#475569; color:#fff; font-weight:700; padding:.45rem .8rem; }
    .ho-box .ho-col { padding:.7rem .8rem; }
    .ho-box .ho-col + .ho-col { border-left:1px dashed #94a3b8; }
    @media (max-width: 767.98px) { .ho-box .ho-col + .ho-col { border-left:0; border-top:1px dashed #94a3b8; } }
    .ho-item { display:flex; gap:.6rem; align-items:flex-start; background:#fffbeb; border:1px solid #fcd34d; border-left:5px solid #f59e0b;
               border-radius:.4rem; padding:.4rem .6rem; margin-bottom:.4rem; }
    .ho-item .ho-meta { color:#92400e; }
    .ho-hist { max-height:320px; overflow:auto; }
    .ho-hist-row { border-bottom:1px solid #e2e8f0; padding:.3rem 0; }
</style>
<script>
(function () {
    if (window.__hoInit) return;
    window.__hoInit = true;
    var TOKEN = @json(csrf_token());

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function itemHtml(n) {
        return '<div class="ho-item" data-ho-id="' + n.id + '"><div class="flex-grow-1">'
            + '<div style="white-space:pre-wrap;">' + esc(n.body) + '</div>'
            + '<div class="small ho-meta">' + esc(n.author) + ' · ' + esc(n.at) + '</div></div>'
            + '<button type="button" class="btn btn-sm btn-outline-success js-ho-resolve" data-url="' + esc(n.resolve_url) + '" title="เรื่องนี้จบแล้ว">'
            + '<i class="fa fa-check"></i> ปิดเรื่อง</button></div>';
    }
    // นับใหม่ทุกที่ (แถบบน + กล่องในหมวดวันนัด) ให้ตรงกัน
    function refresh() {
        var box = document.querySelector('#ho-box .js-ho-open') || document.querySelector('.js-ho-open');
        var n = box ? box.querySelectorAll('.ho-item').length : 0;
        document.querySelectorAll('.js-ho-count').forEach(function (c) { c.textContent = n; });
        var b = document.getElementById('ho-banner'); if (b) b.classList.toggle('d-none', n === 0);
        document.querySelectorAll('.js-ho-none').forEach(function (e) { e.classList.toggle('d-none', n > 0); });
    }
    function post(url, data) {
        var fd = new FormData(); fd.append('_token', TOKEN);
        Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
        return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); });
    }

    document.addEventListener('click', function (e) {
        var add = e.target.closest && e.target.closest('.js-ho-add');
        if (add) {
            var box = add.closest('#ho-box'), ta = box.querySelector('.js-ho-body'), msg = box.querySelector('.js-ho-msg');
            var body = ta.value.trim();
            if (!body) { ta.focus(); msg.className = 'small js-ho-msg text-danger'; msg.textContent = 'พิมพ์เรื่องที่จะส่งเวรก่อน'; return; }
            add.disabled = true; msg.className = 'small js-ho-msg text-muted'; msg.textContent = 'กำลังบันทึก…';
            post(box.dataset.store, { body: body }).then(function (j) {
                document.querySelectorAll('.js-ho-open').forEach(function (l) { l.insertAdjacentHTML('afterbegin', itemHtml(j.note)); });
                ta.value = ''; ta.dispatchEvent(new Event('input', { bubbles: true }));
                msg.className = 'small js-ho-msg text-success'; msg.textContent = '✓ ส่งเวรแล้ว ' + j.note.at.split(' ')[1] + ' น.';
                refresh();
            }).catch(function () {
                msg.className = 'small js-ho-msg text-danger'; msg.textContent = 'บันทึกไม่สำเร็จ ลองใหม่อีกครั้ง';
            }).then(function () { add.disabled = false; });
            return;
        }
        var res = e.target.closest && e.target.closest('.js-ho-resolve');
        if (res) {
            var item = res.closest('.ho-item');
            if (!confirm('ปิดเรื่องส่งเวรนี้? (ยังดูได้ในประวัติส่งเวร)')) return;
            res.disabled = true;
            post(res.dataset.url, {}).then(function () {
                document.querySelectorAll('.ho-item[data-ho-id="' + item.dataset.hoId + '"]').forEach(function (x) { x.remove(); });
                refresh();
            }).catch(function () { res.disabled = false; alert('ปิดเรื่องไม่สำเร็จ ลองใหม่อีกครั้ง'); });
            return;
        }
        // ลิงก์ "เขียนส่งเวร" บนแถบบน: กางหมวดวันนัดก่อนแล้วเลื่อนไป
        var jump = e.target.closest && e.target.closest('.js-ho-jump');
        if (jump) {
            e.preventDefault();
            var col = document.getElementById('visitOther');
            if (col && !col.classList.contains('show') && window.bootstrap) bootstrap.Collapse.getOrCreateInstance(col, { toggle: false }).show();
            setTimeout(function () {
                var b = document.getElementById('ho-box'); if (!b) return;
                b.scrollIntoView({ behavior: 'smooth', block: 'center' });
                var t = b.querySelector('.js-ho-body'); if (t) t.focus({ preventScroll: true });
            }, 350);
        }
    });
    // Enter ในช่องส่งเวรเป็นการขึ้นบรรทัด (textarea) ไม่ไปกด Save ฟอร์มหลักอยู่แล้ว · Ctrl+Enter = ส่งเวร
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && e.target.classList && e.target.classList.contains('js-ho-body')) {
            e.preventDefault(); var b = e.target.closest('#ho-box').querySelector('.js-ho-add'); if (b) b.click();
        }
    });
})();
</script>
