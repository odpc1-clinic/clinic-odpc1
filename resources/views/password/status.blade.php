@extends('layouts.app')
@section('content')
@php
    // วันที่แบบไทย (พ.ศ.) — timestamp ในตารางเก็บเป็น ค.ศ.
    $thDt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/') . (\Carbon\Carbon::parse($d)->year + 543) . \Carbon\Carbon::parse($d)->format(' H:i') . ' น.' : '—';
    $thMonths = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    $now = now();
    $asOf = $now->day . ' ' . $thMonths[$now->month] . ' ' . ($now->year + 543) . ' เวลา ' . $now->format('H:i') . ' น.';
    $pct = $total ? round($done * 100 / $total) : 0;
@endphp
<style>
    /* ===== พิมพ์แนบหนังสือ (A4 แนวตั้ง) — พิมพ์เฉพาะรายงาน ไม่เอาแถบเมนู/ปุ่ม (2569-11-02) ===== */
    #pw-report .print-only { display: none; }
    @media print {
        @page { size: A4 portrait; margin: 15mm 14mm 16mm; }
        body * { visibility: hidden !important; }
        #pw-report, #pw-report * { visibility: visible !important; }
        #pw-report { position: absolute; left: 0; top: 0; width: 100%; margin: 0 !important; }
        #pw-report .card { border: 0 !important; box-shadow: none !important; margin: 0 !important; }
        #pw-report .card-header, #pw-report .screen-only, #pw-report .d-print-none { display: none !important; }
        #pw-report .card-body { padding: 0 !important; }
        #pw-report .print-only { display: block; }
        #pw-report { font-size: 11.5pt; color: #000; }
        #pw-report table { width: 100%; border-collapse: collapse; font-size: 10.5pt; }
        #pw-report th, #pw-report td { border: 1px solid #000 !important; padding: 3px 6px !important; background: #fff !important; color: #000 !important; }
        #pw-report thead th { background: #e9ecef !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        #pw-report tr { break-inside: avoid; page-break-inside: avoid; }
        #pw-report td.small { font-size: 9.5pt; word-break: break-all; }
        #pw-report thead { display: table-header-group; }
        #pw-report .badge { border: 0; color: #000 !important; background: none !important; padding: 0; font-weight: 600; font-size: 10.5pt; }
        #pw-report .table-responsive { overflow: visible !important; }
    }
</style>

<div class="container-fluid" id="pw-report">
    <div class="card mt-3">
        <div class="card-header bg-white d-flex align-items-center gap-2 flex-wrap">
            <i class="fas fa-shield-halved text-primary"></i>
            <b>สถานะการเปลี่ยนรหัสผ่าน</b>
            <span class="text-muted small">ตามหนังสือ สธ 0418.17/719 ลว. 27 ส.ค. 2569</span>
        </div>
        <div class="card-body">
            @if (session('success'))
                <div class="alert alert-success screen-only">{{ session('success') }}</div>
            @endif

            {{-- หัวรายงาน (เฉพาะตอนพิมพ์) --}}
            <div class="print-only text-center mb-3">
                <div style="font-size:15pt; font-weight:700;">รายงานสถานะการเปลี่ยนรหัสผ่านผู้ใช้งาน</div>
                <div style="font-size:13pt; font-weight:600;">ระบบเวชระเบียน ศูนย์บริการเวชศาสตร์ป้องกัน สำนักงานป้องกันควบคุมโรคที่ 1 เชียงใหม่</div>
                <div class="mt-2" style="text-align:left;">
                    <b>อ้างถึง</b> หนังสือกระทรวงสาธารณสุข ที่ สธ 0418.17/719 ลงวันที่ 27 สิงหาคม 2569
                    เรื่อง การเปลี่ยนรหัสผ่านตามที่ สกมช. แจ้งเตือนความเสี่ยงชื่อผู้ใช้และรหัสผ่าน<br>
                    <b>ข้อมูล ณ วันที่</b> {{ $asOf }}
                </div>
            </div>

            {{-- สรุปตัวเลข --}}
            <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                <div class="screen-only display-6 fw-bold {{ $done === $total ? 'text-success' : 'text-danger' }}">
                    {{ $done }} / {{ $total }}
                </div>
                <div class="flex-fill screen-only" style="min-width:220px">
                    <div class="progress" style="height:22px">
                        <div class="progress-bar {{ $done === $total ? 'bg-success' : 'bg-warning' }}"
                             style="width:{{ $pct }}%">{{ $pct }}%</div>
                    </div>
                </div>
                <button class="btn btn-outline-secondary d-print-none" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> พิมพ์แนบหนังสือ
                </button>
            </div>
            <div class="mb-2" style="line-height:1.7;">
                บัญชีที่ใช้งานอยู่ <b>{{ $total }}</b> บัญชี ·
                เปลี่ยนรหัสผ่านแล้ว <b>{{ $done }}</b> บัญชี ({{ $pct }}%) ·
                ยังไม่เปลี่ยน <b>{{ $total - $done }}</b> บัญชี
                @if ($forced) (ระบบบังคับให้ตั้งรหัสใหม่เมื่อเข้าใช้งานครั้งถัดไปแล้ว {{ $forced }} บัญชี) @endif
                @if ($disabled) · ปิดใช้งาน <b>{{ $disabled }}</b> บัญชี (เข้าระบบไม่ได้) @endif
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:6%" class="text-center">ลำดับ</th>
                            <th style="width:30%">ชื่อผู้ใช้งาน</th>
                            <th style="width:28%">บัญชี (อีเมล)</th>
                            <th style="width:16%">เปลี่ยนรหัสล่าสุด</th>
                            <th style="width:20%">สถานะ</th>
                            <th style="width:150px" class="d-print-none"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $i => $u)
                            <tr class="{{ $u->is_disabled ? 'text-muted' : ($u->is_done ? '' : 'table-warning') }}">
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $u->name }}</td>
                                <td class="small text-break">{{ $u->email }}</td>
                                <td class="small">{{ $thDt($u->password_changed_at) }}</td>
                                <td>
                                    @if ($u->is_disabled)
                                        <span class="badge bg-secondary">ปิดใช้งาน</span>
                                    @elseif ($u->is_done)
                                        <span class="badge bg-success">เปลี่ยนแล้ว</span>
                                    @elseif ($u->must_change_password)
                                        <span class="badge bg-warning text-dark">ยังไม่เปลี่ยน · บังคับเปลี่ยนแล้ว</span>
                                    @else
                                        <span class="badge bg-danger">ยังไม่เปลี่ยน</span>
                                    @endif
                                </td>
                                <td class="d-print-none">
                                    @unless ($u->is_disabled)
                                        <a href="{{ route('password.for-user', $u->id) }}" class="btn btn-sm btn-outline-danger">ตั้งรหัสให้</a>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="small text-muted mt-2">
                ระบบบันทึกเฉพาะ “ใครเปลี่ยนเมื่อไหร่” ลงในบันทึกการใช้งาน <b>ไม่มีการเก็บตัวรหัสผ่าน</b> ไม่ว่ากรณีใด ·
                รหัสผ่านใหม่ต้องยาวอย่างน้อย 12 ตัวอักษร มีตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก ตัวเลข และอักขระพิเศษ
            </div>

            {{-- ลงชื่อผู้รายงาน (เฉพาะตอนพิมพ์) --}}
            <div class="print-only" style="margin-top:18mm;">
                <div style="width:60%; margin-left:auto; text-align:center; line-height:2;">
                    ลงชื่อ ...................................................... ผู้รายงาน<br>
                    (......................................................)<br>
                    ตำแหน่ง ......................................................<br>
                    วันที่ ............/............/............
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
