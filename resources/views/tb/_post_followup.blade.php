{{-- ติดตามหลังจบการรักษา (คลินิกขอ 2569-10-27) — บัตรที่ลงผล "หาย/ครบ" แล้ว ยังต้องนัดมาตรวจ (X-ray) ตามรอบมาตรฐานของสูตร
     การมาติดตามเป็น visit ธรรมดา · รอบ/สถานะคิดที่ App\Services\TbPostFollowup · ตัวแปร: $reg
     ปุ่มลงนัด: ปุ่มธรรมดา + JS สร้างฟอร์มต่อ body (หน้าบัตรมีหลายฟอร์ม ห้ามซ้อน) --}}
@php
    $__pf = \App\Services\TbPostFollowup::plan($reg);
    $__pfSt = [
        'done'     => ['มาแล้ว', '#166534', '#dcfce7'],
        'booked'   => ['นัดแล้ว', '#1e40af', '#dbeafe'],
        'due'      => ['ถึงรอบ — ยังไม่นัด', '#9a3412', '#ffedd5'],
        'upcoming' => ['ยังไม่นัด', '#475569', '#f1f5f9'],
        'missed'   => ['เลยรอบ — ไม่ได้มา', '#991b1b', '#fee2e2'],
    ];
    $__pfNeed = collect($__pf)->contains(fn ($p) => in_array($p['status'], ['due', 'upcoming'], true));
@endphp
@if($__pf)
<div class="card border-0 shadow-sm mb-3" id="tb-post-followup" style="border-left:4px solid #0f766e !important;">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <span class="fw-bold" style="color:#0f766e;"><i class="fa fa-calendar-check me-1"></i> ติดตามหลังจบการรักษา</span>
            <span class="small text-muted">จบการรักษา {{ \App\Utillity::db2thDate(\App\Models\TbRegistration::beDate($reg->outcome_date)) }} · รอบตามมาตรฐานของสูตร · มาตรวจเป็น visit ธรรมดา (X-ray)</span>
            @if($__pfNeed)
                <button type="button" class="btn btn-sm ms-auto js-tb-pf-book" style="background:#0f766e;color:#fff;"
                        data-url="{{ route('tb.postFollowup', $reg->id) }}">
                    <i class="fa fa-calendar-plus me-1"></i> ลงนัดติดตามทั้งหมด
                </button>
            @endif
        </div>
        <div class="d-flex flex-wrap gap-2">
            @foreach($__pf as $__p)
                @php [$__t, $__fg, $__bg] = $__pfSt[$__p['status']]; @endphp
                <div class="px-2 py-1 rounded small" style="background:{{ $__bg }};color:{{ $__fg }};border:1px solid {{ $__fg }}33;">
                    <b>{{ $__p['label'] }}</b> · ครบ {{ \App\Utillity::db2thDate($__p['due']) }}
                    · {{ $__t }}
                    @if($__p['appt']) ({{ \App\Utillity::db2thDate($__p['appt']->appointment_date) }}) @endif
                    @if($__p['visit']) (<a href="{{ route('visit.edit', ['visit' => $__p['visit']['id']]) }}" target="_blank">{{ \App\Utillity::db2thDate($__p['visit']['date']) }}</a>) @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
<script>
(function () {
    if (window.__tbPfInit) return;
    window.__tbPfInit = true;
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('.js-tb-pf-book');
        if (!b) return;
        if (!confirm('ลงนัดติดตามหลังรักษาทุกรอบที่ยังไม่มีนัด (เตรียม CXR)?\nนัดผูกกับ visit วัณโรคล่าสุด · แก้วัน/ลบได้ที่หน้าตรวจ')) return;
        var f = document.createElement('form');
        f.method = 'POST'; f.action = b.dataset.url; f.style.display = 'none';
        f.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}">';
        document.body.appendChild(f);   // ต่อกับ body เท่านั้น — ห้ามซ้อนในฟอร์มอื่น
        f.submit();
    });
})();
</script>
@endif
