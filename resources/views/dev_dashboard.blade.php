@extends('layouts.app')

@section('content')
{{-- Dashboard งานพัฒนาระบบ (กลุ่ม IT ขอ 2569-10-19) — ข้อมูลจาก DevDashboardController · ไม่มีข้อมูลคนไข้ --}}
@php
    $thM = ['01'=>'ม.ค.','02'=>'ก.พ.','03'=>'มี.ค.','04'=>'เม.ย.','05'=>'พ.ค.','06'=>'มิ.ย.','07'=>'ก.ค.','08'=>'ส.ค.','09'=>'ก.ย.','10'=>'ต.ค.','11'=>'พ.ย.','12'=>'ธ.ค.'];
    $mLabel = fn ($ym) => ($thM[substr($ym, 5, 2)] ?? '') . ' ' . substr($ym, 2, 2);
    $peak = max(1, (int) collect($byMonth)->max('total'));
    $gPeak = max(1, (int) collect($byGroup)->max());
    $tagName = ['new' => 'ใหม่', 'fix' => 'แก้บั๊ก', 'change' => 'ปรับ'];
    $tagColor = ['new' => '#198754', 'fix' => '#dc3545', 'change' => '#0d6efd'];
    $isProd = $env !== 'staging';
@endphp
<style>
    #dd { font-size: 1rem; }
    #dd .tile { border: 1px solid #dee2e6; border-radius: .5rem; padding: .8rem 1rem; background: #fff; height: 100%; }
    #dd .tile .n { font-size: 1.8rem; font-weight: 700; line-height: 1.2; font-variant-numeric: tabular-nums; }
    #dd .tile .s { color: #6c757d; font-size: .88rem; }
    #dd .bar { display: flex; height: 1.1rem; border-radius: .25rem; overflow: hidden; background: #f1f3f5; }
    #dd .bar span { display: block; height: 100%; }
    #dd .mrow { display: grid; grid-template-columns: 4.5rem 1fr 2.5rem; gap: .5rem; align-items: center; margin-bottom: .3rem; }
    #dd .num { font-variant-numeric: tabular-nums; text-align: right; }
    #dd .tag { display: inline-block; min-width: 3.6rem; text-align: center; color: #fff; border-radius: .25rem; font-size: .8rem; padding: 0 .3rem; }
    #dd table td, #dd table th { vertical-align: top; }
</style>
<div class="container-fluid py-3" id="dd">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h3 class="mb-0 me-2"><i class="fa-solid fa-gauge-high me-1"></i> Dashboard งานพัฒนาระบบ</h3>
        <span class="badge {{ $isProd ? 'bg-success' : 'bg-danger' }}" style="font-size:.9rem;">{{ $isProd ? 'ระบบจริง' : 'ระบบทดสอบ' }}</span>
        <span class="text-muted small">commit <code>{{ $commit ?: '—' }}</code> · PHP {{ $php }} · Laravel {{ $laravel }} · ฐาน {{ $db }}</span>
        @if(config('pulse.enabled') && \Illuminate\Support\Facades\Route::has('pulse'))
            {{-- Laravel Pulse: หน้าไหนช้า error อะไร ใครใช้หนัก CPU/แรม/ดิสก์ — ข้อมูลสด 7 วันล่าสุด --}}
            <a href="{{ route('pulse') }}" class="btn btn-outline-primary btn-sm ms-auto"><i class="fa-solid fa-heart-pulse me-1"></i> สุขภาพระบบ (Pulse)</a>
        @endif
    </div>

    {{-- ตัวเลขสรุป --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="tile">
            <div class="s">งานที่พัฒนาทั้งหมด</div>
            <div class="n">{{ count($items) }}</div>
            <div class="s">ใหม่ {{ $tags['new'] }} · แก้บั๊ก {{ $tags['fix'] }} · ปรับ {{ $tags['change'] }}</div>
        </div></div>
        <div class="col-6 col-lg-3"><div class="tile">
            <div class="s">การเปลี่ยนโครงสร้างฐานข้อมูล (migration)</div>
            <div class="n">{{ $migrations['total'] }}</div>
            <div class="s {{ $migrations['pending']->count() ? 'text-danger fw-bold' : '' }}">ค้างรัน {{ $migrations['pending']->count() }} รายการ</div>
        </div></div>
        <div class="col-6 col-lg-3"><div class="tile">
            <div class="s">ช่องโหว่ในไลบรารีที่ใช้</div>
            @if($audit)
                <div class="n {{ $audit['total'] ? 'text-danger' : 'text-success' }}">{{ $audit['total'] }}</div>
                <div class="s">{{ count($audit['packages']) }} ไลบรารี · ตรวจ {{ $audit['run_at'] }}</div>
            @else
                <div class="n text-muted">—</div><div class="s">ยังไม่เคยตรวจ (php artisan clinic:security-audit)</div>
            @endif
        </div></div>
        <div class="col-6 col-lg-3"><div class="tile">
            <div class="s">ทดสอบเปิดหน้าอัตโนมัติ</div>
            @if($smoke)
                <div class="n {{ $smoke['failed'] ? 'text-danger' : 'text-success' }}">{{ $smoke['total'] - $smoke['failed'] - ($smoke['skipped'] ?? 0) }}/{{ $smoke['total'] - ($smoke['skipped'] ?? 0) }}</div>
                <div class="s">ผ่าน · {{ $smoke['run_at'] }} · commit {{ $smoke['commit'] ?? '—' }}</div>
            @else
                <div class="n text-muted">—</div><div class="s">ยังไม่เคยรัน (php artisan clinic:smoke-test)</div>
            @endif
        </div></div>
    </div>

    <div class="row g-3 mb-3">
        {{-- รายเดือน --}}
        <div class="col-lg-7"><div class="tile">
            <h6 class="fw-bold">งานที่พัฒนารายเดือน
                <span class="small fw-normal ms-2">
                    @foreach($tagName as $k => $l)<span class="tag me-1" style="background:{{ $tagColor[$k] }}">{{ $l }}</span>@endforeach
                </span>
            </h6>
            @foreach($byMonth as $ym => $m)
                <div class="mrow">
                    <div class="small">{{ $mLabel($ym) }}</div>
                    <div class="bar" style="width:{{ max(2, round($m['total'] * 100 / $peak)) }}%;" title="ใหม่ {{ $m['new'] }} · แก้บั๊ก {{ $m['fix'] }} · ปรับ {{ $m['change'] }}">
                        @foreach(['new', 'fix', 'change'] as $k)
                            @if($m[$k])<span style="width:{{ $m[$k] * 100 / $m['total'] }}%; background:{{ $tagColor[$k] }};"></span>@endif
                        @endforeach
                    </div>
                    <div class="num small">{{ $m['total'] }}</div>
                </div>
            @endforeach
            <div class="small text-muted mt-2">รายการก่อน ส.ค. 2569 ไล่บันทึกย้อนหลัง วันที่เป็นโดยประมาณ</div>
        </div></div>
        {{-- รายกลุ่มงาน --}}
        <div class="col-lg-5"><div class="tile">
            <h6 class="fw-bold">แยกตามกลุ่มงาน</h6>
            @foreach($byGroup as $g => $n)
                <div class="mrow" style="grid-template-columns: 11rem 1fr 2.5rem;">
                    <div class="small text-truncate" title="{{ $g }}">{{ $g }}</div>
                    <div class="bar" style="width:{{ max(2, round($n * 100 / $gPeak)) }}%;"><span style="width:100%; background:#495057;"></span></div>
                    <div class="num small">{{ $n }}</div>
                </div>
            @endforeach
        </div></div>
    </div>

    {{-- เฝ้าระบบ + แจ้ง LINE ผู้ดูแล (clinic:health-watch ทุกนาที) --}}
    <div class="tile mb-3">
        <h6 class="fw-bold"><i class="fa-solid fa-tower-broadcast me-1"></i> เฝ้าระบบ · แจ้งเตือนทาง LINE เมื่อระบบล่ม</h6>
        @if(session('health_msg'))<div class="alert alert-info py-1 px-2 small mb-2">{{ session('health_msg') }}</div>@endif
        <div class="row g-3">
            <div class="col-lg-5 small">
                @if($watch)
                    @php $down = ($watch['state'] ?? '') === 'down'; @endphp
                    <div class="mb-1">
                        <span class="badge {{ $down ? 'bg-danger' : (($watch['fails'] ?? 0) ? 'bg-warning text-dark' : 'bg-success') }}" style="font-size:.85rem;">
                            {{ $down ? 'ใช้งานไม่ได้' : (($watch['fails'] ?? 0) ? 'ตอบพลาด ' . $watch['fails'] . ' ครั้ง' : 'ปกติ') }}</span>
                        <span class="text-muted ms-1">เช็คล่าสุด {{ $watch['last_check'] ?? '—' }}</span>
                    </div>
                    @if($down)<div class="text-danger">ใช้ไม่ได้ตั้งแต่ {{ $watch['down_since'] }} · {{ $watch['last_result'] }}</div>@endif
                    @foreach(($watch['warnings'] ?? []) as $k => $v)
                        <div class="text-warning-emphasis" style="color:#997404;"><i class="fa-solid fa-triangle-exclamation"></i> {{ $k }}: {{ \Illuminate\Support\Str::after($v, 'warn: ') }}</div>
                    @endforeach
                    <div class="text-muted mt-1">เฝ้า <code>{{ $watch['url'] ?? '' }}</code></div>
                @else
                    <div class="text-muted">ยังไม่เคยเช็ค — ตัวตั้งเวลาจะรัน <code>clinic:health-watch</code> ทุกนาที</div>
                @endif
                <div class="text-muted mt-1">ล่มติดกัน 2 นาทีแจ้งครั้งเดียว และแจ้งอีกครั้งเมื่อกลับมาใช้ได้ (ประหยัดโควตา LINE 300 ข้อความ/เดือน ที่ใช้ร่วมกับแจ้งนัด)</div>
            </div>
            <div class="col-lg-7 small">
                <div class="fw-bold mb-1">ผู้รับแจ้งเตือน</div>
                @forelse($alert['recipients'] as $r)
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="fa-brands fa-line" style="color:#06c755;"></i> {{ $r['name'] }}
                        <span class="text-muted">ผูกเมื่อ {{ $r['linked_at'] }}</span>
                        <form method="POST" action="{{ route('health.remove') }}" class="ms-auto" onsubmit="return confirm('เอา {{ e($r['name']) }} ออกจากผู้รับแจ้งเตือน?')">
                            @csrf <input type="hidden" name="id" value="{{ $r['id'] }}">
                            <button class="btn btn-outline-danger btn-sm py-0">เอาออก</button>
                        </form>
                    </div>
                @empty
                    <div class="text-muted mb-1">ยังไม่มีผู้รับ — แจ้งเตือนจะไม่ถูกส่ง</div>
                @endforelse
                @if(session('health_code'))
                    <div class="alert alert-success py-2 px-2 my-2">
                        พิมพ์ข้อความนี้ในแชท LINE OA ของคลินิก (ภายใน 10 นาที):
                        <span class="fs-5 fw-bold ms-1" style="letter-spacing:.05em;">#{{ session('health_code') }}</span>
                    </div>
                @endif
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <form method="POST" action="{{ route('health.code') }}">@csrf
                        <button class="btn btn-outline-success btn-sm"><i class="fa-solid fa-link"></i> เพิ่มผู้รับ (สร้างรหัสผูก LINE)</button></form>
                    <form method="POST" action="{{ route('health.test') }}">@csrf
                        <button class="btn btn-outline-secondary btn-sm" @disabled(!$alert['recipients'])><i class="fa-solid fa-paper-plane"></i> ส่งข้อความทดสอบ</button></form>
                    <a href="{{ route('health.up') }}" target="_blank" class="btn btn-link btn-sm">เปิด /up</a>
                </div>
            </div>
        </div>
    </div>

    {{-- ความปลอดภัย --}}
    <div class="tile mb-3">
        <h6 class="fw-bold"><i class="fa-solid fa-shield-halved me-1"></i> ความปลอดภัยของไลบรารีและตัวระบบ</h6>
        @if(version_compare($laravel, '11', '<'))
        <div class="alert alert-warning py-2 small mb-2">
            <b>Laravel {{ $laravel }}</b> — Laravel 8 หมดระยะออกแพตช์ความปลอดภัยแล้ว (ม.ค. 2566) ช่องโหว่ใหม่ของตัวเฟรมเวิร์กจะไม่ได้รับการแก้
            · แผน: อัปเดตไลบรารีย่อยในรุ่นเดิมก่อน แล้วยกรุ่น Laravel ในระบบทดสอบ ทดสอบผ่านแล้วค่อยขึ้นระบบจริง
        </div>
        @endif
        @if($audit && $audit['packages'])
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>ไลบรารี</th><th class="text-end">จำนวน</th><th>ตัวอย่างช่องโหว่</th></tr></thead>
                    <tbody>
                    @foreach($audit['packages'] as $p)
                        <tr>
                            <td class="text-nowrap"><code>{{ $p['name'] }}</code></td>
                            <td class="num">{{ $p['count'] }}</td>
                            <td class="small">
                                @foreach(array_slice($p['items'], 0, 2) as $a)
                                    <div>{{ $a['cve'] ? $a['cve'] . ' · ' : '' }}{{ \Illuminate\Support\Str::limit($a['title'], 90) }}</div>
                                @endforeach
                                @if($p['count'] > 2)<div class="text-muted">และอีก {{ $p['count'] - 2 }} รายการ</div>@endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @elseif($audit)
            <div class="text-success">ไม่พบช่องโหว่ที่ประกาศไว้</div>
        @endif
        @if($migrations['pending']->count())
            <div class="mt-2 small text-danger"><b>migration ค้างรัน:</b> {{ $migrations['pending']->implode(', ') }}</div>
        @endif
    </div>

    {{-- ผลทดสอบเปิดหน้า --}}
    @if($smoke)
        <div class="tile mb-3">
            <h6 class="fw-bold"><i class="fa-solid fa-vial-circle-check me-1"></i> ผลทดสอบเปิดหน้าอัตโนมัติ
                <span class="small fw-normal text-muted">{{ $smoke['run_at'] }} · {{ $smoke['env'] === 'staging' ? 'ระบบทดสอบ' : 'ระบบจริง' }} · commit {{ $smoke['commit'] ?? '—' }}</span></h6>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>ผล</th><th>หน้า</th><th class="text-end">สถานะ</th><th class="text-end">เวลา (ms)</th><th>หมายเหตุ</th></tr></thead>
                    <tbody>
                    @foreach($smoke['results'] as $r)
                        <tr>
                            <td>@if(! $r['ok'])<span class="badge bg-danger">พัง</span>@elseif(! empty($r['skipped']))<span class="badge bg-secondary">ข้าม</span>@else<span class="badge bg-success">ผ่าน</span>@endif</td>
                            <td><code>{{ $r['url'] }}</code></td>
                            <td class="num">{{ $r['status'] }}</td>
                            <td class="num">{{ $r['ms'] }}</td>
                            <td class="small">{{ $r['nested'] ? 'ฟอร์มซ้อน' : '' }}{{ $r['leak'] ? 'มีข้อความ error ในหน้า' : '' }}{{ ! empty($r['skipped']) ? 'ผู้ใช้ที่ใช้ทดสอบไม่มีสิทธิ์หน้านี้' : '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- รายการงานทั้งหมด --}}
    <div class="tile">
        <h6 class="fw-bold"><i class="fa-solid fa-list-check me-1"></i> รายการงานที่พัฒนาทั้งหมด</h6>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <select class="form-select form-select-sm w-auto" id="dd-month">
                <option value="">ทุกเดือน</option>
                @foreach($byMonth->keys()->reverse() as $ym)<option value="{{ $ym }}">{{ $mLabel($ym) }} ({{ $byMonth[$ym]['total'] }})</option>@endforeach
            </select>
            <select class="form-select form-select-sm w-auto" id="dd-tag">
                <option value="">ทุกประเภท</option>
                @foreach($tagName as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
            </select>
            <select class="form-select form-select-sm w-auto" id="dd-group">
                <option value="">ทุกกลุ่มงาน</option>
                @foreach($byGroup->keys() as $g)<option value="{{ $g }}">{{ $g }}</option>@endforeach
            </select>
            <input type="text" class="form-control form-control-sm" id="dd-q" style="max-width:16rem;" placeholder="ค้นหาคำ">
            <span class="small text-muted align-self-center" id="dd-count"></span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th style="width:6.5rem;">วันที่</th><th style="width:5rem;">ประเภท</th><th style="width:14rem;">ส่วนที่เปลี่ยน</th><th>รายละเอียด</th></tr></thead>
                <tbody id="dd-body">
                @foreach($items as $i)
                    <tr data-month="{{ $i['month'] }}" data-tag="{{ $i['tag'] }}" data-group="{{ $i['group'] }}">
                        <td class="small text-nowrap">{{ \App\Utillity::db2thDate($i['date']) }}@if($i['approx'])<span class="text-muted">*</span>@endif</td>
                        <td><span class="tag" style="background:{{ $tagColor[$i['tag']] ?? '#6c757d' }}">{{ $tagName[$i['tag']] ?? $i['tag'] }}</span></td>
                        <td class="small">{{ $i['area'] }}</td>
                        <td class="small">{{ $i['text'] }}@if($i['note'])<div class="text-muted">{{ $i['note'] }}</div>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
(function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('#dd-body tr'));
    var f = { m: document.getElementById('dd-month'), t: document.getElementById('dd-tag'), g: document.getElementById('dd-group'), q: document.getElementById('dd-q') };
    function apply() {
        var q = f.q.value.trim().toLowerCase(), n = 0;
        rows.forEach(function (r) {
            var on = (!f.m.value || r.dataset.month === f.m.value) && (!f.t.value || r.dataset.tag === f.t.value)
                && (!f.g.value || r.dataset.group === f.g.value) && (!q || r.textContent.toLowerCase().indexOf(q) !== -1);
            r.style.display = on ? '' : 'none';
            if (on) n++;
        });
        document.getElementById('dd-count').textContent = 'แสดง ' + n + ' / ' + rows.length + ' รายการ';
    }
    ['change', 'input'].forEach(function (ev) { Object.keys(f).forEach(function (k) { f[k].addEventListener(ev, apply); }); });
    apply();
})();
</script>
@endsection
