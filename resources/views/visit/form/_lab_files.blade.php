{{-- ไฟล์ผลตรวจจากที่อื่น ในหมวด Lab ของหน้าตรวจ — ทุกคลินิก (2569-10-07; เดิมมีแค่บัตร TB 01)
     อยู่ในฟอร์มหลัก → ห้ามเป็น <form> · ช่องกรอกไม่มี name (ไม่ไปกับปุ่ม Save ของหน้าตรวจ)
     ส่งด้วย fetch ไป VisitLabFileController แล้วเอารายการที่ตอบกลับมาแทนที่ · data-no-dirty = ไม่นับเป็น "ยังไม่ได้บันทึก"
     หน้าตรวจโหลดเต็ม (ไม่ใช่ AJAX) สคริปต์ท้ายไฟล์จึงรันได้ --}}
<div class="border rounded p-2 mt-3" id="visit-lab-files" data-no-dirty
     data-url="{{ route('visit.labFile.store', $visit->id) }}"
     data-max="{{ \App\Models\TbLabFile::postMaxBytes() }}" data-file-max="{{ \App\Models\TbLabFile::MAX_KB * 1024 }}">
    <div class="fw-bold mb-1"><i class="fa fa-file-medical me-1"></i> ไฟล์ผลตรวจจากที่อื่น
        <span class="small text-muted fw-normal">สแกน / รูปถ่าย / PDF ใบผลจาก รพ.อื่น · แล็บเอกชน · บันทึกทันทีที่กดอัปโหลด</span>
    </div>
    @if(\App\Models\TbLabFile::visitReady())
        <div class="row g-2 align-items-end p-2 rounded mb-2" style="background:#fffbeb;">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0" for="vlf-date">วันที่ตรวจ (ตามใบผล)</label>
                <input type="text" class="form-control form-control-sm" id="vlf-date" placeholder="วว/ดด/ปปปป" autocomplete="off">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-0" for="vlf-source">ตรวจที่</label>
                <input type="text" class="form-control form-control-sm" id="vlf-source" maxlength="150" list="lab-file-sources" autocomplete="off" placeholder="เช่น รพ.นครพิงค์">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small mb-0" for="vlf-title">รายการ</label>
                <input type="text" class="form-control form-control-sm" id="vlf-title" maxlength="150" list="lab-file-titles" autocomplete="off" placeholder="เช่น CBC, HIV, VDRL">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small mb-0" for="vlf-note">หมายเหตุ</label>
                <input type="text" class="form-control form-control-sm" id="vlf-note" maxlength="500">
            </div>
            <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                <label class="btn btn-outline-primary btn-sm mb-0">
                    <i class="fa fa-folder-open me-1"></i> เลือกไฟล์
                    <input type="file" multiple class="d-none js-vlf-input"
                           accept="application/pdf,image/jpeg,image/png,image/webp,.pdf,.jpg,.jpeg,.png,.webp">
                </label>
                {{-- capture = เปิดกล้องหลังบนแท็บเล็ต/มือถือ (บนคอมเป็นเลือกไฟล์ธรรมดา) --}}
                <label class="btn btn-outline-secondary btn-sm mb-0">
                    <i class="fa fa-camera me-1"></i> ถ่ายรูป
                    <input type="file" class="d-none js-vlf-input" accept="image/*" capture="environment">
                </label>
                <span class="small text-muted js-vlf-chosen">ยังไม่ได้เลือกไฟล์</span>
                <button type="button" class="btn btn-primary btn-sm ms-auto" id="vlf-upload"><i class="fa fa-upload me-1"></i> อัปโหลด</button>
            </div>
            <div class="col-12 small text-muted">PDF / JPG / PNG / WEBP · ไฟล์ละไม่เกิน 10 MB · ข้อมูลด้านบนใช้กับทุกไฟล์ที่ส่งรอบนี้</div>
        </div>
        <div class="small mb-1 js-vlf-status"></div>
        @include('partials.lab_file_datalists')
        <div class="js-vlf-list">@include('visit.form._lab_files_list', ['visit' => $visit])</div>
    @else
        <div class="small text-muted">กล่องนี้ยังไม่เปิดใช้ (รอผู้ดูแลระบบอัปเดตฐานข้อมูล)</div>
    @endif
</div>
@if(\App\Models\TbLabFile::visitReady())
@include('partials.lab_file_edit_js')
<script>
(function () {
    var box = document.getElementById('visit-lab-files');
    if (!box || box.dataset.bound) return;
    box.dataset.bound = '1';

    function fmt(b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
    function chosen() {
        var files = [];
        box.querySelectorAll('.js-vlf-input').forEach(function (i) {
            Array.prototype.forEach.call(i.files || [], function (f) { files.push(f); });
        });
        return files;
    }
    function status(msg, ok) {
        var st = box.querySelector('.js-vlf-status');
        st.textContent = msg || '';
        st.className = 'small mb-1 js-vlf-status ' + (ok ? 'text-success' : 'text-danger');
    }
    function send(url, fd) {
        return fetch(url, { method: 'POST', credentials: 'same-origin', body: fd,
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) } })
            .then(function (r) { return r.json().catch(function () { return { ok: false, msg: 'เซิร์ฟเวอร์ตอบกลับผิดพลาด (' + r.status + ')' }; }); })
            .then(function (j) {
                if (j.html !== undefined) box.querySelector('.js-vlf-list').innerHTML = j.html;
                status(j.msg, j.ok);
                return j;
            })
            .catch(function () { status('ส่งไม่สำเร็จ (เชื่อมต่อเซิร์ฟเวอร์ไม่ได้)', false); return { ok: false }; });
    }

    box.addEventListener('change', function (e) {
        if (!e.target.classList.contains('js-vlf-input')) return;
        var files = chosen();
        box.querySelector('.js-vlf-chosen').textContent = files.length
            ? 'เลือกแล้ว ' + files.length + ' ไฟล์: ' + files.map(function (f) { return f.name + ' (' + fmt(f.size) + ')'; }).join(', ')
            : 'ยังไม่ได้เลือกไฟล์';
    });

    // Enter ในช่องกรอกของกล่องนี้ห้ามไปกด Save ฟอร์มหลัก
    box.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); }
    });

    document.getElementById('vlf-upload').addEventListener('click', function () {
        var btn = this, files = chosen(), total = 0, big = [];
        files.forEach(function (f) { total += f.size; if (f.size > +box.dataset.fileMax) big.push(f.name + ' (' + fmt(f.size) + ')'); });
        if (!files.length) { status('ยังไม่ได้เลือกไฟล์', false); return; }
        if (big.length) { status('ไฟล์ใหญ่เกิน 10 MB: ' + big.join(', '), false); return; }
        // เกิน post_max_size PHP ทิ้งทั้งคำขอ (รวม CSRF) ได้หน้า 419 งง ๆ — เช็คก่อนส่ง
        if (total > +box.dataset.max - 512 * 1024) { status('ไฟล์รวมกัน ' + fmt(total) + ' ใหญ่เกินที่ส่งได้ในครั้งเดียว — แบ่งส่งหลายครั้ง', false); return; }
        var fd = new FormData();
        files.forEach(function (f) { fd.append('files[]', f); });
        fd.append('test_date', document.getElementById('vlf-date').value);
        fd.append('source', document.getElementById('vlf-source').value);
        fd.append('title', document.getElementById('vlf-title').value);
        fd.append('note', document.getElementById('vlf-note').value);
        btn.disabled = true; status('กำลังอัปโหลด…', true);
        send(box.dataset.url, fd).then(function (j) {
            btn.disabled = false;
            if (j.ok) {
                box.querySelectorAll('.js-vlf-input').forEach(function (i) { i.value = ''; });
                ['vlf-date', 'vlf-source', 'vlf-title', 'vlf-note'].forEach(function (id) { document.getElementById(id).value = ''; });
                box.querySelector('.js-vlf-chosen').textContent = 'ยังไม่ได้เลือกไฟล์';
            }
        });
    });

    box.addEventListener('click', function (e) {
        var b = e.target.closest('.js-vlf-delete');
        if (!b) return;
        if (!confirm('ลบไฟล์ "' + (b.dataset.name || '') + '" ทิ้ง? ไฟล์จะหายถาวร')) return;
        var fd = new FormData(); fd.append('_method', 'DELETE');
        b.disabled = true;
        send(b.dataset.url, fd);
    });
})();
</script>
@endif
