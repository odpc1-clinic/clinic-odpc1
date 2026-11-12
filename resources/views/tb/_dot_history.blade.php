{{-- ประวัติการกำกับกินยา (DOT) ในบัตร TB 01 — พยาบาลขอ 2569-09-30
     ส่งเวร: แต่ละเรื่องมีวันเริ่ม-วันสิ้นสุด → ดูได้ว่า "มีไข้ถึงวันไหน · หายไข้มากี่วันแล้ว"
     หมายเหตุรายวัน: ทุกวันที่เคยจดไว้ (ไม่ใช่แค่เดือนที่เปิดปฏิทินอยู่)
     คนไข้ที่ DOT โดย อสม./รพ.สต. ไม่ขึ้นในหน้า DOT — ข้อมูล DOT ของคนกลุ่มนี้ดูที่นี่ที่เดียว
     ตัวแปร: $reg, $dotHandovers, $dotNotesAll --}}
@php
    $__today = \Carbon\Carbon::today();
    $__th = fn ($d) => $d ? $d->format('d/m/') . ($d->year + 543) : '';
@endphp
<div class="card border-0 shadow-sm mb-3" id="tb-dot-history">
    <div class="card-body">
        <h6 class="fw-bold mb-2"><i class="fa fa-clock-rotate-left me-1"></i> ประวัติการกำกับกินยา (DOT)</h6>

        {{-- เปิด/ปิด DOT (คลินิกขอ 2569-10-12 — ปุ่มเดิมหายไปตอนเอาแถบบัตรออกจากหน้าตรวจ) --}}
        <div class="mb-2 p-2 rounded" style="background:#fff7ed; border:1px solid #fed7aa;">@include('tb._dot_toggle', ['reg' => $reg])</div>
        <div class="d-flex flex-wrap gap-2 small mb-3">
            <span class="badge {{ $reg->dot_active ? 'bg-success' : 'bg-secondary' }}">{{ $reg->dot_active ? 'กำลัง DOT' : 'Off DOT' }}</span>
            @if(! $reg->dot_active && $reg->dot_off_date)
                <span class="badge bg-light text-dark border">ปิดเมื่อ {{ \App\Utillity::db2thDate($reg->dot_off_date) }}{{ $reg->dot_off_reason ? ' · ' . $reg->dot_off_reason : '' }}</span>
            @endif
            <span class="badge bg-light text-dark border">ระยะ: {{ $reg->dot_reason ?: '—' }}</span>
            <span class="badge bg-light text-dark border">ช่องทาง: {{ $reg->dot_channel ?: '—' }}</span>
            <span class="badge bg-light text-dark border">ผู้รับผิดชอบ: {{ $reg->dot_owner ?: '—' }}</span>
            @if($reg->isCommunityDot())
                <span class="badge" style="background:#fff3cd; color:#664d03; border:1px solid #ffda6a;">DOT โดย อสม./รพ.สต. — ไม่แสดงในหน้า DOT</span>
            @endif
        </div>

        <div class="fw-semibold small mb-1">เรื่องส่งเวร</div>
        @if($dotHandovers->isEmpty())
            <div class="small text-muted mb-3">— ยังไม่มี</div>
        @else
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered align-middle mb-0 small">
                    <thead class="table-light">
                        <tr><th>เรื่อง</th><th style="width:9.5rem;">เริ่ม</th><th style="width:9.5rem;">สิ้นสุด</th><th style="width:12rem;">ระยะเวลา</th></tr>
                    </thead>
                    <tbody>
                    @foreach($dotHandovers as $h)
                        @php
                            $__s = $h->created_at->copy()->startOfDay();
                            $__e = $h->resolved_at ? $h->resolved_at->copy()->startOfDay() : null;
                        @endphp
                        <tr class="{{ $__e ? '' : 'table-warning' }}">
                            <td>
                                <div class="d-flex align-items-start gap-2">
                                    <span class="js-ho-text flex-grow-1">{{ $h->note }}</span>
                                    {{-- แก้ข้อความ (พิมพ์ผิดตอนรีบกดส่งเวร — พยาบาลขอ 2569-10-07) · ปุ่มธรรมดา + fetch ไม่ใช้ฟอร์ม --}}
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 d-print-none js-ho-edit" title="แก้ข้อความ"
                                            data-url="{{ route('tb.dotHandover.update', $h->id) }}"><i class="fa fa-pen"></i> แก้</button>
                                </div>
                            </td>
                            <td>{{ $__th($h->created_at) }}@if($h->forDateText())<div class="fw-bold">{{ $h->forDateText() }}</div>@endif<div class="text-muted">{{ optional($h->creator)->name }}</div></td>
                            <td>
                                @if($__e)
                                    {{ $__th($h->resolved_at) }}<div class="text-muted">{{ optional($h->resolver)->name }}</div>
                                @else
                                    <span class="text-danger fw-bold">ยังค้างอยู่</span>
                                @endif
                            </td>
                            <td>
                                @if($__e)
                                    {{ (int) $__s->diffInDays($__e, true) + 1 }} วัน
                                    <div class="text-muted">สิ้นสุดมาแล้ว {{ (int) $__e->diffInDays($__today, true) }} วัน</div>
                                @else
                                    ค้างมา {{ (int) $__s->diffInDays($__today, true) + 1 }} วัน
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="fw-semibold small mb-1">หมายเหตุรายวัน</div>
        @if($dotNotesAll->isEmpty())
            <div class="small text-muted">— ยังไม่มี</div>
        @else
            <div class="small" style="max-height: 16rem; overflow-y: auto;">
                @foreach($dotNotesAll as $n)
                    <div class="py-1 border-bottom">
                        <b>{{ \App\Utillity::db2thDate($n->dot_date) }}</b>
                        @if($n->mark)<span class="badge bg-light text-dark border ms-1">{{ \App\Models\TbDotDay::$markOption[$n->mark] ?? $n->mark }}</span>@endif
                        — {{ $n->note }}
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
<script>
// แก้ข้อความส่งเวรจากบัตร TB 01 — หน้าโหลดเต็ม สคริปต์รันได้ · ผูกที่ document ครั้งเดียว
(function () {
    if (window.__dotHoEdit) return;
    window.__dotHoEdit = true;
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.js-ho-edit');
        if (!b) return;
        var txt = b.closest('td').querySelector('.js-ho-text');
        var nv = prompt('แก้ข้อความส่งเวร', txt.textContent.trim());
        if (nv === null || nv.trim() === '' || nv.trim() === txt.textContent.trim()) return;
        var body = new URLSearchParams();
        body.append('_token', @json(csrf_token()));
        body.append('note', nv.trim());
        b.disabled = true;
        fetch(b.dataset.url, { method: 'POST', body: body, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { if (!r.ok || !j.ok) throw j; return j; }); })
            .then(function (j) { txt.textContent = j.note; })
            .catch(function (j) { alert((j && j.message) || 'แก้ไม่สำเร็จ ลองใหม่อีกครั้ง'); })
            .then(function () { b.disabled = false; });
    });
})();
</script>
