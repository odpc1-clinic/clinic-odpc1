@extends('layouts.app')

@section('content')
<style>
    .lh-hero{ border-radius:18px; color:#fff; }
    .lh-card{ border:0; border-left:5px solid var(--edge,#94a3b8); border-radius:14px; transition:.18s; }
    .lh-card:hover{ transform:translateX(3px); box-shadow:0 14px 34px -18px rgba(15,40,30,.4); }
    .lh-ico{ width:46px; height:46px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.25rem; }
    .lh-ok{ --edge:#059669; } .lh-bad{ --edge:#dc2626; } .lh-warn{ --edge:#d97706; }
</style>

<div class="container-fluid py-4">

    {{-- สรุปรวม --}}
    <div class="lh-hero p-4 mb-4 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-3"
         style="background: {{ $allOk ? 'linear-gradient(135deg,#047857,#10b981)' : 'linear-gradient(135deg,#b91c1c,#f97316)' }};">
        <div>
            <h3 class="mb-1 fw-bold">
                <i class="fab fa-line me-2"></i> สถานะระบบแจ้งเตือนนัดหมาย LINE
            </h3>
            <div class="opacity-75">
                ตรวจ {{ count($checks) }} จุดเชื่อมต่อ · ณ {{ now()->format('d/m/Y H:i') }} น.
            </div>
        </div>
        <div class="text-end">
            @if($allOk)
                <div class="fw-bold" style="font-size:1.6rem;"><i class="fas fa-circle-check"></i> ระบบพร้อมใช้งาน</div>
                <div class="opacity-75 small">{{ $warned ? 'จุดที่ต้องใช้ทำงานครบ · จุดไม่บังคับมีปัญหา ' . $warned . ' จุด' : 'เชื่อมต่อสำเร็จทุกจุด' }}</div>
            @else
                <div class="fw-bold" style="font-size:1.6rem;"><i class="fas fa-triangle-exclamation"></i> พบปัญหา {{ $failed }} จุด</div>
                <div class="opacity-75 small">ดูวิธีแก้ในรายการด้านล่าง</div>
            @endif
        </div>
    </div>

    <div class="row g-4">
        {{-- รายการเช็ค --}}
        <div class="col-lg-8">
            @foreach($checks as $i => $c)
            @php $__st = $c['ok'] ? 'ok' : ($c['optional'] ? 'warn' : 'bad'); @endphp
            <div class="card lh-card shadow-sm mb-3 lh-{{ $__st }}">
                <div class="card-body d-flex gap-3">
                    <div class="lh-ico" style="background: {{ ['ok' => '#d1fae5', 'warn' => '#fef3c7', 'bad' => '#fee2e2'][$__st] }}; color: {{ ['ok' => '#047857', 'warn' => '#b45309', 'bad' => '#b91c1c'][$__st] }};">
                        <i class="fas {{ ['ok' => 'fa-circle-check', 'warn' => 'fa-circle-exclamation', 'bad' => 'fa-circle-xmark'][$__st] }}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div class="fw-bold">{{ $i + 1 }}. {{ $c['name'] }}</div>
                            <span class="badge {{ ['ok' => 'bg-success', 'warn' => 'bg-warning text-dark', 'bad' => 'bg-danger'][$__st] }}">
                                {{ ['ok' => 'เชื่อมต่อสำเร็จ', 'warn' => 'ใช้ไม่ได้ (ไม่บังคับ)', 'bad' => 'มีปัญหา'][$__st] }}
                            </span>
                        </div>
                        <div class="text-muted small">{{ $c['why'] }}</div>
                        <div class="mt-2 {{ ['ok' => 'text-success', 'bad' => 'text-danger'][$__st] ?? '' }}" @if($__st === 'warn') style="color:#92400e;" @endif>
                            <i class="fas fa-angle-right"></i> {{ $c['detail'] }}
                        </div>
                        @unless($c['ok'])
                            <div class="alert alert-warning py-2 px-3 mt-2 mb-0 small">
                                <b><i class="fas fa-screwdriver-wrench"></i> วิธีแก้:</b> {{ $c['fix'] }}
                            </div>
                        @endunless
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- ด้านขวา: งานวันนี้ + ข้อความล่าสุด --}}
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 mb-3">
                <div class="card-header bg-white fw-bold text-secondary py-3">
                    <i class="fas fa-calendar-day me-1"></i> การแจ้งเตือนวันนี้
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>นัดหมายวันนี้</span><b>{{ number_format($today['appt']) }}</b>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">ในนั้น ผูก LINE ไว้</span><b class="text-info">{{ number_format($today['linked']) }}</b>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">ส่งเตือนแล้ว</span><b class="text-success">{{ number_format($today['reminded']) }}</b>
                    </div>
                    @if($today['form'] !== null)
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">ผูกผ่านฟอร์มวันนี้</span>
                            <b><span class="text-success">{{ $today['form']['ok'] }} สำเร็จ</span>@if($today['form']['bad']) · <span class="text-danger">{{ $today['form']['bad'] }} รหัสผิด</span>@endif</b>
                        </div>
                    @endif

                    @if($today['linked'] > $today['reminded'])
                        <div class="alert alert-info py-2 px-3 mt-3 mb-0 small">
                            เหลืออีก <b>{{ $today['linked'] - $today['reminded'] }}</b> ราย ระบบจะทยอยส่งให้ในรอบถัดไป
                            (ทุก 30 นาที ช่วง 08:30–16:30)
                        </div>
                    @elseif($today['appt'] > 0 && $today['linked'] === 0)
                        <div class="alert alert-warning py-2 px-3 mt-3 mb-0 small">
                            นัดวันนี้ยังไม่มีใครผูก LINE เลย จึงส่งเตือนไม่ได้
                        </div>
                    @endif

                    <div class="text-muted small mt-3">
                        <i class="fas fa-circle-info"></i> เสาร์–อาทิตย์ไม่ส่ง (คลินิกปิด) · นัดวันจันทร์จะส่งเช้าวันจันทร์
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white fw-bold text-secondary py-3">
                    <i class="fas fa-clock-rotate-left me-1"></i> ข้อความล่าสุด
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 align-middle">
                        <tbody>
                        @forelse($recent as $r)
                            <tr>
                                <td class="small text-muted" style="width:95px;">
                                    {{ \Carbon\Carbon::parse($r->created_at)->format('d/m H:i') }}
                                </td>
                                <td class="small">
                                    {{ $r->hn ?? '-' }}
                                    <span class="text-muted">· {{ $purposeLabel[$r->purpose] ?? ($r->purpose ?? $r->type) }}</span>
                                    @if($r->error)
                                        <div class="text-danger" style="font-size:.75rem;">{{ \Illuminate\Support\Str::limit($r->error, 60) }}</div>
                                    @endif
                                </td>
                                <td class="text-end" style="width:70px;">
                                    <span class="badge {{ $r->success ? 'bg-success' : 'bg-danger' }}">
                                        {{ $r->success ? 'สำเร็จ' : 'ล้มเหลว' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-3">ยังไม่มีประวัติ</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="d-grid mt-3">
                <a href="{{ route('line.health') }}" class="btn btn-outline-primary">
                    <i class="fas fa-rotate"></i> ตรวจสอบใหม่อีกครั้ง
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
