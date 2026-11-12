{{-- ประวัติ Lab ของคนไข้ในบัตร TB 01 (คลินิกขอ 2569-09-30) — ดูอย่างเดียว
     รวม Lab ในบ้าน (lab_subs) + Lab ส่งตรวจภายนอก (NAT2 / WGS / LAMP / N Health) ทุกคลินิก ใหม่ -> เก่า
     ผล Lab ในบ้านโชว์เฉพาะที่ห้อง Lab อนุมัติแล้ว (Lab::isResultVisible) เหมือนหน้าตรวจ
     ข้อมูลดึงที่นี่เอง ไม่ต้องให้ TbController ส่งมา — ใช้ $reg อย่างเดียว --}}
@php
    $__pid    = $reg->patient_id;
    $__visits = \App\Models\Visit::where('patient_id', $__pid)->get(['id', 'date', 'clinic'])->keyBy('id');
    $__labs   = \App\Models\Lab::where('patient_id', $__pid)->orderByDesc('id')->limit(30)->get();
    $__subs   = \App\Models\LabSub::whereIn('lab_id', $__labs->pluck('id'))->orderBy('id')->get()->groupBy('lab_id');
    $__ext    = \App\Models\VisitExternalLab::whereIn('visit_id', $__visits->keys())->orderByDesc('id')->limit(50)->get();
    // ไฟล์ผลที่แนบกับแต่ละรายการสั่งตรวจ (2569-10-30) — แนบแล้วแถวไม่ขึ้น "รอผล"
    $__extOn    = \App\Models\TbLabFile::externalReady();
    $__extFiles = $__extOn
        ? \App\Models\TbLabFile::whereIn('external_lab_id', $__ext->pluck('id'))->orderBy('id')->get()->groupBy('external_lab_id')
        : collect();
    $__clinic = [1 => 'STI', 2 => 'TB', 3 => 'TMC'];
    $__vdate  = function ($visitId) use ($__visits) {
        $d = optional($__visits->get($visitId))->date;
        if (! $d) { return '—'; }
        // visits.date เป็น พ.ศ. แต่แถวยุคแรกบางแถวเป็น ค.ศ. (CLAUDE.md ข้อ 4)
        $y = (int) substr($d, 0, 4);
        return \App\Utillity::db2thDate(($y < 2500 ? $y + 543 : $y) . substr($d, 4));
    };
@endphp
<div class="card border-0 shadow-sm mb-3" id="tb-lab-history">
    <div class="card-body">
        <h6 class="fw-bold mb-2"><i class="fa fa-flask-vial me-1"></i> ประวัติ Lab
            <span class="small text-muted fw-normal">ทุกคลินิก · ใหม่ → เก่า · ดูอย่างเดียว (สั่ง Lab วันนี้ที่กล่อง "สั่งตรวจครั้งนี้" ด้านบน)</span>
        </h6>

        {{-- ผลที่คนไข้ถือมาจากที่อื่นเป็นไฟล์ ไม่ได้อยู่ในตารางด้านล่าง — บอกไว้ตรงนี้ คนดูประวัติจะได้ไม่พลาด (2569-10-05) --}}
        @if(\App\Models\TbLabFile::ready())
            {{-- นับทุกไฟล์ของคนไข้ (รวมที่อัปจากหน้าตรวจ) ให้ตรงกับกล่องไฟล์ด้านล่าง --}}
            @php $__lfCount = \App\Models\TbLabFile::where(function ($q) use ($reg) {
                $q->where('tb_registration_id', $reg->id);
                if (\App\Models\TbLabFile::visitReady() && $reg->patient_id) { $q->orWhere('patient_id', $reg->patient_id); }
            })->count(); @endphp
            <div class="small mb-2">
                <i class="fa fa-file-medical me-1 text-secondary"></i> ไฟล์ผลตรวจจากที่อื่น:
                @if($__lfCount)
                    <a href="#tb-lab-files" class="fw-semibold">{{ $__lfCount }} ไฟล์ — ดูด้านล่าง</a>
                @else
                    <span class="text-muted">ยังไม่มี</span> · <a href="#tb-lab-files">อัปโหลด</a>
                @endif
            </div>
        @endif

        <div class="fw-semibold small mb-1">Lab ส่งตรวจภายนอก</div>
        @if($__ext->isEmpty())
            <div class="small text-muted mb-3">— ยังไม่มี</div>
        @else
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered align-middle mb-0 small">
                    <thead class="table-light">
                        <tr><th style="width:6.5rem;">วันที่มา</th><th>รายการ</th><th style="width:10rem;">แล็บ</th>
                            <th style="width:6.5rem;">ส่งตรวจ</th><th>ผล</th>
                            @if($__extOn)<th style="width:8.5rem;">ไฟล์ผล</th>@endif</tr>
                    </thead>
                    <tbody>
                    @foreach($__ext as $e)
                        @php $__ef = $__extFiles->get($e->id, collect()); @endphp
                        <tr class="{{ $e->hasResult() || $__ef->isNotEmpty() ? '' : 'table-warning' }}">
                            <td>{{ $__vdate($e->visit_id) }}
                                <span class="badge bg-light text-dark border">{{ $__clinic[optional($__visits->get($e->visit_id))->clinic] ?? '' }}</span></td>
                            <td>{{ $e->code ? $e->code . ' · ' : '' }}{{ $e->name }}</td>
                            <td>{{ $e->vendor ?: '—' }}</td>
                            <td>{{ $e->sent_date_th ?: '—' }}</td>
                            <td>
                                {{-- แนบไฟล์แล้ว = ผลอยู่ในไฟล์ ไม่ต้องพิมพ์ซ้ำ · ผลที่พิมพ์ไว้ยังโชว์คู่ไฟล์ได้ --}}
                                @foreach($__ef as $__f)
                                    <a href="{{ route('tb.labFile.show', [$reg->id, $__f->id]) }}" target="_blank" rel="noopener"
                                       class="d-inline-block me-1 fw-semibold" title="{{ $__f->original_name }}">
                                        <i class="fa {{ $__f->isPdf() ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary' }}"></i> ดูไฟล์ผล{{ $__ef->count() > 1 ? ' ' . ($loop->iteration) : '' }}
                                    </a>
                                @endforeach
                                @if($e->hasResult())
                                    @if($e->result !== \App\Models\VisitExternalLab::FILE_RESULT || $__ef->isEmpty()){{ $e->result }}@endif
                                    @if($e->result_date)<div class="text-muted">ผลวันที่ {{ \App\Models\VisitExternalLab::toThDate($e->result_date) }}</div>@endif
                                @elseif($__ef->isEmpty())
                                    <span class="text-muted">รอผล</span>
                                @endif
                            </td>
                            @if($__extOn)
                                <td class="text-nowrap">
                                    {{-- ปุ่มธรรมดา + JS ส่งไฟล์ (ไม่ใช้ <form> ในตาราง ตามกติกาของโปรเจกต์) --}}
                                    <button type="button" class="btn btn-outline-primary btn-sm py-0 js-ext-attach"
                                            data-ext="{{ $e->id }}" data-name="{{ $e->code ? $e->code . ' · ' : '' }}{{ $e->name }}">
                                        <i class="fa fa-paperclip"></i> {{ $__ef->isEmpty() ? 'แนบไฟล์ผล' : 'เพิ่มไฟล์' }}
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="fw-semibold small mb-1">Lab ในคลินิก</div>
        @if($__labs->isEmpty())
            <div class="small text-muted">— ยังไม่มี</div>
        @else
            <div class="table-responsive" style="max-height: 24rem; overflow-y: auto;">
                <table class="table table-sm table-bordered align-middle mb-0 small">
                    <thead class="table-light" style="position:sticky; top:0;">
                        <tr><th style="width:6.5rem;">วันที่มา</th><th>รายการตรวจ</th><th style="width:9rem;">สิ่งส่งตรวจ</th><th>ผล</th></tr>
                    </thead>
                    <tbody>
                    @foreach($__labs as $lab)
                        @php $__vis = $lab->isResultVisible(); $__items = $__subs[$lab->id] ?? collect(); @endphp
                        @foreach($__items as $i => $s)
                            <tr>
                                @if($i === 0)
                                    <td rowspan="{{ $__items->count() }}">
                                        {{ $__vdate($lab->visit_id) }}
                                        <span class="badge bg-light text-dark border">{{ $__clinic[optional($__visits->get($lab->visit_id))->clinic] ?? '' }}</span>
                                        @if($lab->LN)<div class="text-muted">LN {{ $lab->LN }}</div>@endif
                                    </td>
                                @endif
                                <td>{{ \App\Models\LabSub::$methodOption[$s->method] ?? '' }}{{ (int) $s->method === 10 && $s->method_other ? ': ' . $s->method_other : '' }}</td>
                                <td>{{ $s->specimenFrom() }}</td>
                                <td>
                                    @if(! $__vis)
                                        <span class="text-muted">รอห้อง Lab อนุมัติ</span>
                                    @elseif(trim((string) $s->result) === '')
                                        <span class="text-muted">รอผล</span>
                                    @elseif((int) $s->method === 6)
                                        {{-- ผล HIV ปิดไว้ กดดูแล้วมีปุ่มพิมพ์ผล / ใบ refer (2569-10-06) --}}
                                        @include('partials.hiv_mask', ['hmPatient' => $__pid, 'hmKind' => 'sub', 'hmRef' => $s->id,
                                            'hmReg' => $reg->id, 'hmWhere' => 'บัตร TB 01 · ประวัติ Lab'])
                                    @else
                                        {{ $s->result }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($__labs->count() >= 30)
                <div class="small text-muted mt-1">แสดง 30 ครั้งล่าสุด</div>
            @endif
        @endif
    </div>
</div>
@if(\App\Models\TbLabFile::externalReady())
<script>
// แนบไฟล์ผลกับรายการสั่งตรวจนอก (2569-10-30) — กล่องนี้อยู่ในหน้าบัตรที่โหลดเต็ม สคริปต์รันได้ · ผูกที่ document
// เลือกไฟล์แล้วส่งทันที (ชื่อรายการ/แล็บ/วันที่ผลระบบเติมให้จากใบสั่ง) แล้วรีโหลดกลับมาที่ตารางนี้
(function () {
    if (window.__extAttach) return;
    window.__extAttach = true;
    var URL = @json(route('tb.labFile.store', $reg->id)), TOKEN = @json(csrf_token());
    var MAX = {{ \App\Models\TbLabFile::MAX_KB * 1024 }}, POST_MAX = {{ \App\Models\TbLabFile::postMaxBytes() }};

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-ext-attach');
        if (!btn) return;
        var inp = document.createElement('input');
        inp.type = 'file'; inp.multiple = true;
        inp.accept = 'application/pdf,image/jpeg,image/png,image/webp,.pdf,.jpg,.jpeg,.png,.webp';
        inp.addEventListener('change', function () {
            var files = Array.prototype.slice.call(inp.files || []);
            if (!files.length) return;
            var total = 0, big = [];
            files.forEach(function (f) { total += f.size; if (f.size > MAX) big.push(f.name); });
            if (big.length) { alert('ไฟล์ใหญ่เกิน 10 MB: ' + big.join(', ')); return; }
            if (total > POST_MAX - 512 * 1024) { alert('ไฟล์รวมกันใหญ่เกินที่ส่งได้ในครั้งเดียว — แบ่งส่งทีละไฟล์'); return; }

            var fd = new FormData();
            fd.append('_token', TOKEN);
            fd.append('external_lab_id', btn.dataset.ext);
            files.forEach(function (f) { fd.append('files[]', f); });
            var old = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> กำลังแนบ...';
            fetch(URL, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().catch(function () { return { ok: false, msg: 'ส่งไฟล์ไม่สำเร็จ (' + r.status + ')' }; }); })
                .then(function (j) {
                    if (!j.ok) { alert(j.msg || 'แนบไฟล์ไม่สำเร็จ'); btn.disabled = false; btn.innerHTML = old; return; }
                    location.hash = 'tb-lab-history';
                    location.reload();
                })
                .catch(function () { alert('ส่งไฟล์ไม่สำเร็จ ลองใหม่อีกครั้ง'); btn.disabled = false; btn.innerHTML = old; });
        });
        inp.click();
    });
})();
</script>
@endif
