{{-- ไฟล์ผลตรวจจากที่อื่น (สแกน/รูปถ่าย/PDF ผลเลือดจาก รพ.อื่น/แล็บเอกชน) — คลินิกขอ 2569-10-05
     ฟอร์มอัปโหลดเป็น <form> ของตัวเอง วางนอกฟอร์มอื่นในบัตร (show.blade วางกล่องนี้ระหว่างฟอร์มเสมหะกับฟอร์มข้อมูลบัตร)
     ปุ่มลบไม่ใช้ <form> — ให้ JS สร้างฟอร์มต่อกับ body (กติกาห้ามฟอร์มซ้อน) · ข้อมูลดึงที่นี่เอง ใช้ $reg อย่างเดียว --}}
@php
    $__lfReady = \App\Models\TbLabFile::ready();
    $__lfRows  = $__lfReady
        ? \App\Models\TbLabFile::with('uploader')
            // ไฟล์ที่อัปจากหน้าตรวจ (ทุกคลินิก) ของคนไข้คนเดียวกันก็ขึ้นที่นี่ด้วย — ลบได้เฉพาะไฟล์ของบัตรนี้
            ->where(function ($q) use ($reg) {
                $q->where('tb_registration_id', $reg->id);
                if (\App\Models\TbLabFile::visitReady() && $reg->patient_id) { $q->orWhere('patient_id', $reg->patient_id); }
            })
            ->orderByRaw('test_date IS NULL, test_date DESC, id DESC')->get()
        : collect();
    $__lfErr   = $errors->getBag('labFiles');
    $__lfPostMax = \App\Models\TbLabFile::postMaxBytes();
@endphp
<style>
    .tb-lf-card { width: 12.5rem; }
    .tb-lf-thumb { height: 9rem; display: flex; align-items: center; justify-content: center; background: #f8fafc;
                   border-bottom: 1px solid #e5e7eb; overflow: hidden; border-radius: .5rem .5rem 0 0; }
    .tb-lf-thumb img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .tb-lf-thumb .fa-file-pdf { font-size: 3.5rem; color: #dc2626; }
</style>
<div class="card border-0 shadow-sm mb-3" id="tb-lab-files">
    <div class="card-body">
        <h6 class="fw-bold mb-2"><i class="fa fa-file-medical me-1"></i> ไฟล์ผลตรวจจากที่อื่น
            <span class="small text-muted fw-normal">สแกน / รูปถ่าย / PDF ใบผลจาก รพ.อื่น · แล็บเอกชน</span>
        </h6>

        @if(! $__lfReady)
            <div class="alert alert-secondary small mb-0">
                <i class="fa fa-clock me-1"></i> รอเปิดใช้ — ผู้ดูแลระบบยังไม่ได้อัปเดตฐานข้อมูลสำหรับกล่องนี้
            </div>
        @else
            @if($__lfErr->any())
                <ul class="alert alert-danger small mb-2">
                    @foreach($__lfErr->all() as $__e)<li>{{ $__e }}</li>@endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('tb.labFile.store', $reg->id) }}" enctype="multipart/form-data"
                  autocomplete="off" class="border rounded p-2 mb-3" style="background:#fffbeb;" id="tb-lab-files-form"
                  data-post-max="{{ $__lfPostMax }}" data-file-max="{{ \App\Models\TbLabFile::MAX_KB * 1024 }}">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-0">วันที่ตรวจ (ตามใบผล)</label>
                        <input type="text" name="test_date" class="form-control form-control-sm tb-date"
                               placeholder="วว/ดด/ปปปป" value="{{ old('test_date') }}">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-0">ตรวจที่</label>
                        <input type="text" name="source" class="form-control form-control-sm" maxlength="150" list="lab-file-sources" autocomplete="off"
                               placeholder="เช่น รพ.นครพิงค์" value="{{ old('source') }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small mb-0">รายการ</label>
                        <input type="text" name="title" class="form-control form-control-sm" maxlength="150" list="lab-file-titles" autocomplete="off"
                               placeholder="เช่น CBC, LFT" value="{{ old('title') }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-0">หมายเหตุ</label>
                        <input type="text" name="note" class="form-control form-control-sm" maxlength="500" value="{{ old('note') }}">
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                        {{-- เลือกไฟล์ได้หลายไฟล์ · accept ไม่ใส่ heic ไว้ iPhone จะแปลงเป็น JPEG ให้เองตอนเลือก --}}
                        <label class="btn btn-outline-primary btn-sm mb-0">
                            <i class="fa fa-folder-open me-1"></i> เลือกไฟล์
                            <input type="file" name="files[]" multiple class="d-none js-lf-input"
                                   accept="application/pdf,image/jpeg,image/png,image/webp,.pdf,.jpg,.jpeg,.png,.webp">
                        </label>
                        {{-- capture = เปิดกล้องหลังบนมือถือ/แท็บเล็ตตรงไปเลย (บนคอมจะเป็นเลือกไฟล์ธรรมดา) --}}
                        <label class="btn btn-outline-secondary btn-sm mb-0">
                            <i class="fa fa-camera me-1"></i> ถ่ายรูป
                            <input type="file" name="files[]" class="d-none js-lf-input" accept="image/*" capture="environment">
                        </label>
                        <span class="small text-muted js-lf-chosen">ยังไม่ได้เลือกไฟล์</span>
                        <button type="submit" class="btn btn-primary btn-sm ms-auto"><i class="fa fa-upload me-1"></i> อัปโหลด</button>
                    </div>
                    <div class="col-12 small text-muted">
                        PDF / JPG / PNG / WEBP · ไฟล์ละไม่เกิน 10 MB · ส่งรวมกันครั้งละไม่เกิน {{ floor($__lfPostMax / 1048576) }} MB
                        · ข้อมูลด้านบนใช้กับทุกไฟล์ที่ส่งรอบนี้
                    </div>
                </div>
            </form>

            @include('partials.lab_file_datalists')
            @if($__lfRows->isEmpty())
                <div class="small text-muted">— ยังไม่มีไฟล์</div>
            @else
                <div class="d-flex flex-wrap gap-2">
                    @foreach($__lfRows as $__f)
                        @php $__url = route('tb.labFile.show', [$reg->id, $__f->id]); @endphp
                        <div class="card tb-lf-card shadow-sm">
                            <a href="{{ $__url }}" target="_blank" rel="noopener" class="tb-lf-thumb text-decoration-none" title="เปิดดู">
                                @if($__f->isImage())
                                    <img src="{{ $__url }}?thumb=1" alt="" loading="lazy">
                                @else
                                    <i class="fa fa-file-pdf"></i>
                                @endif
                            </a>
                            <div class="card-body p-2 small">
                                <div class="fw-semibold text-truncate" title="{{ $__f->title }}">{{ $__f->title ?: '(ไม่ระบุรายการ)' }}</div>
                                <div>{{ $__f->test_date ? \App\Support\ThaiDate::show($__f->test_date) : 'ไม่ระบุวันที่' }}</div>
                                @if($__f->source)<div class="text-truncate" title="{{ $__f->source }}">{{ $__f->source }}</div>@endif
                                @if($__f->note)<div class="text-muted text-truncate" title="{{ $__f->note }}">{{ $__f->note }}</div>@endif
                                <div class="text-muted text-truncate" title="{{ $__f->original_name }}">{{ $__f->original_name }} · {{ $__f->sizeText() }}</div>
                                <div class="text-muted">โดย {{ optional($__f->uploader)->name ?: '—' }}</div>
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    <a href="{{ $__url }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm flex-fill">
                                        <i class="fa fa-eye"></i> ดู
                                    </a>
                                    @include('partials.lab_file_edit_panel', ['f' => $__f, 'url' => route('tb.labFile.update', [$reg->id, $__f->id])])
                                    @if((int) $__f->tb_registration_id === (int) $reg->id)
                                    <button type="button" class="btn btn-outline-danger btn-sm js-lf-delete"
                                            data-url="{{ route('tb.labFile.destroy', [$reg->id, $__f->id]) }}"
                                            data-name="{{ $__f->title ?: $__f->original_name }}">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                    @else
                                        <span class="badge bg-light text-dark border align-self-center" title="อัปจากหน้าตรวจ — ลบที่ visit นั้น">หน้าตรวจ</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
@if($__lfReady)
@include('partials.lab_file_edit_js')
<script>
// กล่องนี้อยู่ในหน้าบัตรที่โหลดเต็ม (ไม่ได้มาทาง AJAX) สคริปต์จึงรันได้ · ผูกที่ document
(function () {
    if (window.__tbLabFiles) return;
    window.__tbLabFiles = true;

    function fmt(b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }

    document.addEventListener('change', function (e) {
        if (!e.target.classList || !e.target.classList.contains('js-lf-input')) return;
        var form = e.target.closest('form');
        var files = [];
        form.querySelectorAll('.js-lf-input').forEach(function (i) {
            Array.prototype.forEach.call(i.files || [], function (f) { files.push(f); });
        });
        var out = form.querySelector('.js-lf-chosen');
        out.textContent = files.length
            ? 'เลือกแล้ว ' + files.length + ' ไฟล์: ' + files.map(function (f) { return f.name + ' (' + fmt(f.size) + ')'; }).join(', ')
            : 'ยังไม่ได้เลือกไฟล์';
    });

    // เช็คขนาดก่อนส่ง — เกิน post_max_size PHP จะทิ้งทั้งคำขอ ผู้ใช้จะเห็นหน้า 419 แทนข้อความที่อ่านรู้เรื่อง
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.id !== 'tb-lab-files-form') return;
        var max = +form.dataset.fileMax, postMax = +form.dataset.postMax, total = 0, n = 0, big = [];
        form.querySelectorAll('.js-lf-input').forEach(function (i) {
            Array.prototype.forEach.call(i.files || [], function (f) {
                n++; total += f.size;
                if (f.size > max) big.push(f.name + ' (' + fmt(f.size) + ')');
            });
        });
        var msg = null;
        if (!n) msg = 'ยังไม่ได้เลือกไฟล์';
        else if (big.length) msg = 'ไฟล์ใหญ่เกิน 10 MB:\n' + big.join('\n');
        else if (total > postMax - 512 * 1024) msg = 'ไฟล์รวมกัน ' + fmt(total) + ' ใหญ่เกินที่ส่งได้ในครั้งเดียว (' + fmt(postMax) + ') — แบ่งส่งหลายครั้ง';
        if (msg) { e.preventDefault(); alert(msg); return; }
        var b = form.querySelector('button[type=submit]');
        if (b) { b.disabled = true; b.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> กำลังอัปโหลด...'; }
    });

    // ลบ — สร้างฟอร์มต่อกับ body (ห้ามฟอร์มซ้อน)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-lf-delete');
        if (!btn) return;
        if (!confirm('ลบไฟล์ "' + (btn.dataset.name || '') + '" ทิ้ง? ไฟล์จะหายถาวร')) return;
        var f = document.createElement('form');
        f.method = 'POST';
        f.action = btn.dataset.url;
        f.style.display = 'none';
        f.innerHTML = '<input type="hidden" name="_method" value="DELETE">'
                    + '<input type="hidden" name="_token" value="{{ csrf_token() }}">';
        document.body.appendChild(f);
        f.submit();
    });
})();
</script>
@endif
