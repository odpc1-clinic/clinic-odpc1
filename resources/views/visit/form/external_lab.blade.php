@php
    $__page  = request('page', $_GET['page'] ?? 1);
    $__count = \App\Models\VisitExternalLab::where('visit_id', $visit->id)->count();
@endphp

{{-- ⚠️ ห้ามใช้ <form> ในไฟล์นี้เด็ดขาด
     การ์ดนี้ถูก include อยู่ "ข้างใน" ฟอร์มหลักของหน้าตรวจ (#visit_edit_form)
     HTML ไม่อนุญาตให้ form ซ้อน form — เบราว์เซอร์จะเอา </form> ตัวในไปปิดฟอร์มหลัก
     ทำให้ช่องที่อยู่ถัดจากนี้ (สถานะ/วันนัด/เหตุผลนัด) หลุดออกนอกฟอร์มและไม่ถูกส่งตอน Save

     บันทึกทุกอย่างด้วย fetch แล้วสลับตารางในที่ (2569-10-02) — เดิม submit ฟอร์มชั่วคราว = โหลดหน้าใหม่
     พยาบาลเจอกล่อง "ออกจากหน้านี้?" (ฟอร์มหลักมีของค้าง) ทุกครั้ง และหน้าเด้งกลับไปบนสุด เพิ่มได้ทีละรายการ
     data-no-dirty = ช่องในการ์ดนี้ไม่นับเป็น "มีข้อมูลยังไม่ได้ Save" ของหน้าตรวจ (ดู visit/edit) และไม่มี name= จึงไม่ถูกส่งไปกับ Save --}}

<div class="card mt-3" id="ext-lab-card" data-no-dirty>
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-truck-medical me-1"></i> Lab ส่งตรวจภายนอก</span>
        <span class="d-flex align-items-center gap-2">
            <span class="small text-muted d-none d-md-inline">
                ผลจากแล็บนอกไม่ต้องรอห้อง Lab อนุมัติ · ราคาให้กรอกเอง
            </span>
            <a href="{{ route('external-lab.print', ['visit' => $visit->id, 'page' => $__page]) }}"
               target="_blank" class="btn btn-success btn-sm js-extlab-print {{ $__count ? '' : 'd-none' }}">
                <i class="fa fa-print"></i> Print ผล
            </a>
        </span>
    </div>
    <div class="card-body" id="ext-lab-box"
         data-update="{{ route('external-lab.update', $visit->id) }}"
         data-destroy="{{ url('/visit/' . $visit->id . '/external-lab') }}"
         data-page="{{ $__page }}">

        @if(! empty($hideExtPicker))
            {{-- สั่งรายการเพิ่มที่ปุ่ม "สั่งแล็บ" (แคตตาล็อกแล็บรวม) — ที่นี่เหลือลงผล/ราคา/ลบ --}}
            <div class="small text-muted mb-2"><i class="fas fa-circle-info me-1"></i>สั่งรายการเพิ่ม → กดปุ่ม <b>"สั่งแล็บ"</b> ด้านบนของการ์ด Lab</div>
        @else
            @include('visit.form._external_lab_picker', ['visit' => $visit, 'fragment' => 'opd', 'page' => $__page])
        @endif

        <div class="small mb-2 d-none js-extlab-status" role="status"
             style="color:#047857; font-weight:600;"></div>

        <div class="js-extlab-rows">
            @include('visit.form._external_lab_rows', ['visit' => $visit])
        </div>
    </div>
</div>

@include('partials.external_lab_picker_js')

<script>
(function () {
    if (window.__extLabInit) return;
    window.__extLabInit = true;

    var box = document.getElementById('ext-lab-box');
    if (!box) return;
    var card = document.getElementById('ext-lab-card');
    var rowsWrap = box.querySelector('.js-extlab-rows');

    function token() {
        var m = document.querySelector('meta[name="csrf-token"]');
        if (m) return m.getAttribute('content');
        var i = document.querySelector('#visit_edit_form input[name=_token]');
        return i ? i.value : '{{ csrf_token() }}';
    }

    function send(url, fd) {
        fd.append('fragment', 'opd');
        fd.append('page', box.dataset.page || 1);
        return fetch(url, {
            method: 'POST', credentials: 'same-origin', body: fd,
            headers: { 'X-CSRF-TOKEN': token(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, status: r.status, data: j }; });
        });
    }
    function failMessage(status) {
        if (status === 419) return 'หน้าเปิดค้างนานจนหมดอายุ — โหลดหน้าใหม่ (F5) แล้วลองอีกครั้ง';
        if (status === 403) return 'ไม่มีสิทธิ์บันทึกส่วนนี้';
        return 'บันทึกไม่สำเร็จ (HTTP ' + status + ') ลองใหม่อีกครั้ง';
    }

    var statusTimer = null;
    function status(msg, isError) {
        var s = box.querySelector('.js-extlab-status');
        if (!s) return;
        clearTimeout(statusTimer);
        s.style.color = isError ? '#b91c1c' : '#047857';
        s.innerHTML = '<i class="fas ' + (isError ? 'fa-circle-xmark' : 'fa-circle-check') + ' me-1"></i>';
        s.appendChild(document.createTextNode(msg));
        s.classList.remove('d-none');
        if (!isError) statusTimer = setTimeout(function () { s.classList.add('d-none'); }, 4000);
    }

    // ช่องในตารางที่แก้ค้างไว้แต่ยังไม่กดบันทึก (เทียบกับค่าตอนเรนเดอร์)
    function dirtyFields() {
        var out = {};
        rowsWrap.querySelectorAll('tr[data-row] [data-f]').forEach(function (el) {
            if (el.value !== el.defaultValue) {
                var id = el.closest('tr').dataset.row;
                (out[id] = out[id] || {})[el.dataset.f] = el.value;
            }
        });
        return out;
    }

    // ช่องรายละเอียดผลยืดตามเนื้อหา (ผลจากแล็บนอกบางรายการมีหลายบรรทัด)
    function grow(el) {
        el.style.height = 'auto';
        el.style.height = el.scrollHeight + 'px';
    }
    function recalcTotal() {
        var sum = 0;
        rowsWrap.querySelectorAll('.js-extlab-row-price').forEach(function (el) { sum += parseFloat(el.value || 0) || 0; });
        var cell = rowsWrap.querySelector('.js-extlab-total');
        if (cell) cell.textContent = sum.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // สลับตารางใหม่เข้าที่เดิม — ถ้า keep ให้คืนค่าที่พิมพ์ค้างในแถวอื่น (เช่น กำลังคีย์ผลอยู่แล้วกดสั่งเพิ่ม)
    function swapRows(html, data, keep) {
        if (typeof html !== 'string') return;
        var pending = keep ? dirtyFields() : {};
        rowsWrap.innerHTML = html;
        Object.keys(pending).forEach(function (id) {
            var tr = rowsWrap.querySelector('tr[data-row="' + id + '"]');
            if (!tr) return;
            Object.keys(pending[id]).forEach(function (f) {
                var el = tr.querySelector('[data-f="' + f + '"]');
                if (el) el.value = pending[id][f];
            });
        });
        rowsWrap.querySelectorAll('.js-extlab-detail').forEach(grow);
        recalcTotal();
        if (window.bindThDatepicker) {
            rowsWrap.querySelectorAll('input.date').forEach(function (el) { window.bindThDatepicker(el, { language: 'th-th' }); });
        }
        var pr = card.querySelector('.js-extlab-print');
        if (pr && data) pr.classList.toggle('d-none', !(data.count > 0));
    }

    rowsWrap.querySelectorAll('.js-extlab-detail').forEach(grow);
    document.addEventListener('input', function (e) {
        if (!rowsWrap.contains(e.target)) return;
        if (e.target.matches('.js-extlab-detail')) grow(e.target);
        if (e.target.matches('.js-extlab-row-price')) recalcTotal();   // รวมราคาสดขณะแก้
    });

    // ยืนยันสั่งจากตัวเลือกสำเร็จ -> สลับตารางใหม่
    box.addEventListener('extlab:saved', function (e) {
        var d = e.detail || {};
        swapRows(d.html, d, true);
        status('บันทึกแล้ว — สั่งเพิ่ม ' + (d.created || 0) + ' รายการ (เข้าบิลให้การเงินแล้ว)');
    });

    // Enter ในช่องของตาราง ห้ามไปกดส่งฟอร์มหลักของหน้าตรวจ
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && rowsWrap.contains(e.target) && e.target.tagName === 'INPUT') e.preventDefault();
    });

    document.addEventListener('click', function (e) {
        if (!rowsWrap.contains(e.target)) return;

        // บันทึกผล/ราคา ทุกแถว
        var save = e.target.closest('#ext-save-btn');
        if (save) {
            var fd = new FormData();
            rowsWrap.querySelectorAll('tr[data-row]').forEach(function (tr) {
                tr.querySelectorAll('[data-f]').forEach(function (inp) {
                    fd.append('rows[' + tr.dataset.row + '][' + inp.dataset.f + ']', inp.value);
                });
            });
            save.disabled = true;
            send(box.dataset.update, fd).then(function (res) {
                save.disabled = false;
                if (res.ok && res.data.ok) { swapRows(res.data.html, res.data, false); status('บันทึกผล / ราคา แล็บนอกแล้ว'); }
                else status(failMessage(res.status), true);
            }).catch(function () { save.disabled = false; status('บันทึกไม่สำเร็จ — ตรวจสอบการเชื่อมต่อแล้วลองใหม่', true); });
            return;
        }

        // ลบรายการ
        var del = e.target.closest('[data-extlab-del]');
        if (del) {
            if (!confirm('ลบรายการนี้?' + (del.dataset.name ? '\n\n' + del.dataset.name : ''))) return;
            var f2 = new FormData();
            f2.append('_method', 'DELETE');
            del.disabled = true;
            send(box.dataset.destroy + '/' + del.dataset.extlabDel, f2).then(function (res) {
                if (res.ok && res.data.ok) { swapRows(res.data.html, res.data, true); status('ลบรายการแล้ว'); }
                else { del.disabled = false; status(failMessage(res.status), true); }
            }).catch(function () { del.disabled = false; status('ลบไม่สำเร็จ — ตรวจสอบการเชื่อมต่อแล้วลองใหม่', true); });
        }
    });

    // ของในการ์ดไม่นับเป็น "ยังไม่ได้ Save" ของหน้าตรวจแล้ว จึงต้องเตือนเองถ้าจะทิ้ง
    // (หยิบรายการไว้แต่ยังไม่ยืนยันสั่ง / แก้ผล-ราคาแต่ยังไม่กดบันทึก)
    function unsaved() {
        var n = window.ExtLabPicker ? window.ExtLabPicker.pendingCount(box) : 0;
        var d = Object.keys(dirtyFields()).length;
        if (!n && !d) return '';
        return 'Lab ส่งตรวจภายนอก: '
            + (n ? 'มี ' + n + ' รายการที่หยิบไว้แต่ยังไม่ได้กด "ยืนยันสั่ง"' : '')
            + (n && d ? ' และ' : '')
            + (d ? 'มีผล/ราคาที่แก้แต่ยังไม่ได้กด "บันทึกผล / ราคา แล็บนอก"' : '');
    }
    var leaving = false;
    // ดักก่อนตัวอื่น (capture ที่ document) — ปุ่ม Save หน้าตรวจไม่ได้บันทึกของในการ์ดนี้
    document.addEventListener('submit', function (e) {
        if (!e.target || e.target.id !== 'visit_edit_form') return;
        var msg = unsaved();
        if (msg && !confirm(msg + '\n\nปุ่ม Save ของหน้าตรวจไม่ได้บันทึกส่วนนี้ — กด OK เพื่อ Save ต่อโดยทิ้งส่วนนี้ / Cancel เพื่อกลับไปกดบันทึกก่อน')) {
            e.preventDefault();
            e.stopPropagation();
            card.scrollIntoView({ block: 'center' });
            return;
        }
        leaving = true;
        // ตัวดักอื่น (เช่น ยืนยัน "กลับบ้าน") ยกเลิก submit ทีหลังได้ -> กลับมาเตือนตามเดิม
        setTimeout(function () { if (e.defaultPrevented) leaving = false; }, 0);
    }, true);
    window.addEventListener('beforeunload', function (e) {
        if (!leaving && unsaved()) { e.preventDefault(); e.returnValue = ''; }
    });
})();
</script>
