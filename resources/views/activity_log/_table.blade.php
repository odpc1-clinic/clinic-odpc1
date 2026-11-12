{{-- ตารางแสดง activity log ใช้ร่วมกันทั้งหน้ารวม (index) และหน้าเฉพาะเคส (patient) --}}
<style>
    .al-table thead th { background:#f1f5f9; color:#334155; font-size:.75rem; text-transform:uppercase; letter-spacing:.02em;
                         border-bottom:2px solid #e2e8f0; white-space:nowrap; }
    .al-table tbody tr:hover { background:#f8fafc; }
    .al-table td { vertical-align:middle; font-size:.9rem; }
    .chg { font-size:.82rem; }
    .chg .k { font-weight:600; color:#334155; }
    .chg .old { color:#b91c1c; text-decoration:line-through; opacity:.75; }
    .chg .new { color:#15803d; font-weight:600; }
    .mono { font-variant-numeric: tabular-nums; }
</style>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table al-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-3">เวลา</th>
                        <th>ผู้ทำ</th>
                        <th>การกระทำ</th>
                        <th>ชนิดข้อมูล</th>
                        <th>รายการ</th>
                        <th>สิ่งที่เปลี่ยน</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="ps-3 text-nowrap mono">
                            <div>{{ $log->created_at->format('d/m/Y') }}</div>
                            <div class="small text-muted">{{ $log->created_at->format('H:i:s') }}</div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $log->user_name ?? '—' }}</div>
                            @if($log->ip)<div class="small text-muted">{{ $log->ip }}</div>@endif
                        </td>
                        <td>{{-- Bootstrap 5.1 ไม่มี bg-*-subtle (ได้ป้ายใส ตัวหนังสือมองไม่เห็น) — ใช้สีทึบแทน --}}
                            <span class="badge bg-{{ $log->actionColor() }} {{ in_array($log->actionColor(), ['warning', 'info', 'light'], true) ? 'text-dark' : '' }}">{{ $log->actionLabel() }}</span></td>
                        <td>{{ $log->typeLabel() }}</td>
                        <td>
                            @if($log->subject_label)
                                <span class="fw-semibold">{{ $log->subject_label }}</span>
                            @else
                                <span class="text-muted">#{{ $log->subject_id }}</span>
                            @endif
                        </td>
                        <td>
                            @if(is_array($log->changes) && count($log->changes))
                                @foreach($log->changes as $field => $c)
                                    <div class="chg">
                                        <span class="k">{{ $field }}:</span>
                                        @if($log->action === 'updated')
                                            <span class="old">{{ \Illuminate\Support\Str::limit(is_array($c['old'] ?? null) ? json_encode($c['old'], JSON_UNESCAPED_UNICODE) : ($c['old'] ?? 'ว่าง'), 40) }}</span>
                                            <i class="fa fa-arrow-right text-muted mx-1" style="font-size:.7rem;"></i>
                                            <span class="new">{{ \Illuminate\Support\Str::limit(is_array($c['new'] ?? null) ? json_encode($c['new'], JSON_UNESCAPED_UNICODE) : ($c['new'] ?? 'ว่าง'), 40) }}</span>
                                        @elseif($log->action === 'deleted')
                                            {{-- แถวที่ถูกลบเก็บค่าเดิมไว้ฝั่ง old (ดู Auditable) — เดิมหน้านี้โชว์แต่ new จึงว่างเปล่า --}}
                                            <span class="old">{{ \Illuminate\Support\Str::limit(is_array($c['old'] ?? null) ? json_encode($c['old'], JSON_UNESCAPED_UNICODE) : ($c['old'] ?? ''), 40) }}</span>
                                        @else
                                            <span class="new">{{ \Illuminate\Support\Str::limit(is_array($c['new'] ?? null) ? json_encode($c['new'], JSON_UNESCAPED_UNICODE) : ($c['new'] ?? ''), 40) }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">
                        <i class="fas fa-clock-rotate-left fa-2x mb-2 d-block opacity-50"></i>
                        ยังไม่มีประวัติการแก้ไข หรือไม่พบตามเงื่อนไขที่กรอง
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-flex justify-content-center mt-3">
    {!! $logs->links() !!}
</div>
