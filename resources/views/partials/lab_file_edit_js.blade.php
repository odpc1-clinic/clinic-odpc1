{{-- ตัวจัดการปุ่มแก้ข้อมูลไฟล์ผลตรวจ (partials/lab_file_edit_panel) — อยู่ในหน้าที่โหลดเต็มเท่านั้น ผูกที่ document ครั้งเดียว
     หน้าตรวจ: ตอบกลับมี html รายการใหม่ → แทนที่ใน .js-vlf-list · บัตร TB 01: โหลดหน้าใหม่กลับมาที่กล่องไฟล์ --}}
<script>
(function () {
    if (window.__labFileEditInit) return;
    window.__labFileEditInit = true;
    document.addEventListener('click', function (e) {
        var open = e.target.closest('.js-lfe-open');
        if (open) {
            var p = open.parentNode.querySelector('.js-lfe-panel') || open.closest('.card-body').querySelector('.js-lfe-panel');
            if (p) { p.classList.toggle('d-none'); var f = p.querySelector('[data-f="title"]'); if (f && !p.classList.contains('d-none')) f.focus(); }
            return;
        }
        var cancel = e.target.closest('.js-lfe-cancel');
        if (cancel) { cancel.closest('.js-lfe-panel').classList.add('d-none'); return; }
        var save = e.target.closest('.js-lfe-save');
        if (!save) return;
        var panel = save.closest('.js-lfe-panel');
        var body = new URLSearchParams();
        body.append('_token', @json(csrf_token()));
        panel.querySelectorAll('[data-f]').forEach(function (i) { body.append(i.dataset.f, i.value); });
        save.disabled = true;
        fetch(panel.dataset.url, { method: 'POST', body: body, credentials: 'same-origin',
                                   headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().then(function (j) { j.__ok = r.ok; return j; }); })
            .then(function (j) {
                if (!j.__ok || !j.ok) { alert(j.msg || 'แก้ไม่สำเร็จ ลองใหม่อีกครั้ง'); save.disabled = false; return; }
                var list = panel.closest('.js-vlf-list');
                if (list && j.html !== undefined) { list.innerHTML = j.html; return; }
                location.hash = 'tb-lab-files'; location.reload();
            })
            .catch(function () { alert('แก้ไม่สำเร็จ (เชื่อมต่อเซิร์ฟเวอร์ไม่ได้)'); save.disabled = false; });
    });
    // Enter ในช่องแก้ = กดบันทึกช่องนี้ ไม่ใช่ submit ฟอร์มหลักของหน้า
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || !e.target.closest || !e.target.closest('.js-lfe-panel')) return;
        e.preventDefault();
        e.target.closest('.js-lfe-panel').querySelector('.js-lfe-save').click();
    });
})();
</script>
