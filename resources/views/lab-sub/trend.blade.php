@php
    use App\Models\LabSub;

    $methodName = LabSub::$methodOption[$method] ?? 'รายการตรวจ';

    // จุดสำหรับวาดกราฟ: เอาเฉพาะผลที่ "เห็นได้" และแปลงเป็นตัวเลข titer ได้ เรียงเก่า -> ใหม่
    $points = $rows->filter(fn ($r) => $r->visible && $r->titer !== null)
                   ->sortBy('date_ad')
                   ->values();

    // มาตราส่วนกราฟใช้ log2 เพราะ titer เพิ่มทีละ 2 เท่า (1:2, 1:4, 1:8 ...)
    // Non-reactive (0) ให้อยู่ที่ระดับ 0, 1:1 = 1, 1:2 = 2 ... จะได้ห่างเท่ากันสวยงาม
    $level = fn ($t) => $t === null ? null : ($t <= 0 ? 0 : (int) round(log($t, 2)) + 1);
    $maxLevel = max(1, $points->max(fn ($p) => $level($p->titer)) ?? 1);
@endphp

<div class="lt-wrap">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <div class="fw-bold fs-5">{{ $methodName }}</div>
            @if($patient)
                <div class="text-muted small">
                    {{ $patient->name ?? '' }} {{ $patient->surname ?? '' }}
                    @if(!empty($patient->code)) · HN {{ $patient->code }} @endif
                </div>
            @endif
        </div>
        <span class="badge bg-secondary align-self-center">พบ {{ $rows->count() }} ครั้ง</span>
    </div>

    @if($rows->isEmpty())
        <div class="alert alert-light border text-center text-muted mb-0">
            <i class="fas fa-folder-open me-1"></i> ยังไม่เคยตรวจรายการนี้
        </div>
    @else

        {{-- กราฟ titer (เฉพาะ RPR) — เห็นภาพรวมว่าค่าลงถึงไหนแล้ว --}}
        @if($isTiter && $points->count() >= 2)
            @php
                $w = 100; $h = 38; $n = $points->count();
                $coords = $points->map(function ($p, $i) use ($level, $maxLevel, $n, $w, $h) {
                    $x = $n <= 1 ? 0 : round($i * ($w / ($n - 1)), 2);
                    $y = round($h - ($level($p->titer) / $maxLevel) * ($h - 6) - 3, 2);
                    return ['x' => $x, 'y' => $y, 'p' => $p];
                });
            @endphp
            <div class="lt-chart mb-3">
                <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" role="img"
                     aria-label="แนวโน้มค่า {{ $methodName }}">
                    <polyline fill="none" stroke="currentColor" stroke-width="1.2"
                              stroke-linejoin="round" stroke-linecap="round"
                              points="{{ $coords->map(fn ($c) => $c['x'] . ',' . $c['y'])->implode(' ') }}" />
                    @foreach($coords as $c)
                        <circle cx="{{ $c['x'] }}" cy="{{ $c['y'] }}" r="1.6" fill="currentColor" />
                    @endforeach
                </svg>
                <div class="d-flex justify-content-between small text-muted mt-1">
                    <span>เก่าสุด · {{ $points->first()->sub->result }}</span>
                    <span>ล่าสุด · <b class="text-dark">{{ $points->last()->sub->result }}</b></span>
                </div>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 lt-table">
                <thead class="table-light">
                    <tr>
                        <th style="width:110px;">วันที่</th>
                        <th>ผล</th>
                        @if($isTiter)<th style="width:150px;">เทียบครั้งก่อน</th>@endif
                        <th>Specimen</th>
                        <th style="width:110px;">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($rows as $i => $r)
                    @php
                        // แถวถัดไป (index มากกว่า) คือครั้งที่เก่ากว่า เพราะเรียงใหม่->เก่า
                        $prev = $rows[$i + 1] ?? null;
                        $fold = null; $dir = null;
                        if ($isTiter && $r->visible && $r->titer !== null && $prev && $prev->visible && $prev->titer !== null) {
                            if ($r->titer === $prev->titer) {
                                $dir = 'same';
                            } elseif ($r->titer === 0) {
                                $dir = 'clear';                       // กลายเป็น Non-reactive
                            } elseif ($prev->titer === 0) {
                                $dir = 'up'; $fold = null;            // จาก Non-reactive กลับมามีค่า
                            } else {
                                $fold = $r->titer > $prev->titer
                                    ? $r->titer / $prev->titer
                                    : $prev->titer / $r->titer;
                                $dir = $r->titer > $prev->titer ? 'up' : 'down';
                            }
                        }
                    @endphp
                    <tr class="{{ !empty($r->current) ? 'lt-current' : ($i === 0 ? 'lt-latest' : '') }}">
                        <td class="text-nowrap">
                            @if(!empty($r->current))<i class="fas fa-caret-right text-primary me-1"></i>@endif
                            {{ \App\Utillity::db2thDate(\App\Utillity::AD2BE($r->date_ad)) }}
                            @if(!empty($r->current))
                                <span class="badge bg-primary ms-1">ครั้งนี้</span>
                            @elseif($i === 0)
                                <span class="badge bg-dark ms-1">ล่าสุด</span>
                            @endif
                        </td>
                        <td>
                            @if($r->visible)
                                <span class="fw-bold">{{ $r->sub->result !== '' ? $r->sub->result : '—' }}</span>
                                @if($r->sub->remark)
                                    <div class="small text-muted">{{ $r->sub->remark }}</div>
                                @endif
                            @else
                                <span class="badge bg-warning text-dark">
                                    <i class="fas fa-hourglass-half me-1"></i> รอห้อง Lab อนุมัติผล
                                </span>
                            @endif
                        </td>
                        @if($isTiter)
                            <td>
                                @switch($dir)
                                    @case('down')
                                        {{-- เปลี่ยนแปลงน้อยใช้แบบขอบบาง จะได้ไม่แย่งสายตากับกรณีที่มีนัยสำคัญ --}}
                                        <span class="badge {{ $fold >= 4 ? 'bg-success' : 'bg-white text-success border border-success' }}">
                                            <i class="fas fa-arrow-down"></i> ลง {{ rtrim(rtrim(number_format($fold, 1), '0'), '.') }} เท่า
                                        </span>
                                        @if($fold >= 4)<div class="small text-success">ตอบสนองต่อการรักษา</div>@endif
                                        @break
                                    @case('up')
                                        <span class="badge {{ ($fold ?? 4) >= 4 ? 'bg-danger' : 'bg-white text-danger border border-danger' }}">
                                            <i class="fas fa-arrow-up"></i>
                                            {{ $fold ? 'ขึ้น ' . rtrim(rtrim(number_format($fold, 1), '0'), '.') . ' เท่า' : 'กลับมามีค่า' }}
                                        </span>
                                        @if(($fold ?? 4) >= 4)<div class="small text-danger">สงสัยติดเชื้อซ้ำ/รักษาไม่ได้ผล</div>@endif
                                        @break
                                    @case('clear')
                                        <span class="badge bg-success"><i class="fas fa-check"></i> เป็น Non-reactive</span>
                                        @break
                                    @case('same')
                                        <span class="text-muted small">เท่าเดิม</span>
                                        @break
                                    @default
                                        <span class="text-muted small">—</span>
                                @endswitch
                            </td>
                        @endif
                        <td class="small">{{ $r->sub->specimenFrom() ?: '—' }}</td>
                        <td>
                            @if($r->lab && $r->lab->isApproved())
                                <span class="badge bg-success">อนุมัติแล้ว</span>
                            @else
                                <span class="badge bg-light text-muted border">ไม่มีข้อมูลอนุมัติ</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        @if($isTiter)
            <div class="text-muted small mt-2">
                <i class="fas fa-circle-info"></i>
                เกณฑ์ที่ใช้กันทั่วไป: titer <b>ลดลง 4 เท่า</b> (2 dilution) = ตอบสนองต่อการรักษา ·
                <b>เพิ่มขึ้น 4 เท่า</b> = สงสัยติดเชื้อซ้ำหรือรักษาไม่ได้ผล — ใช้ประกอบการพิจารณาของแพทย์เท่านั้น
            </div>
        @endif
    @endif
</div>

<style>
    .lt-chart { color: #0d6efd; }
    .lt-chart svg { width: 100%; height: 90px; display: block; }
    .lt-table .lt-latest  { background: #f8fafc; }
    /* แถวของ visit ที่กำลังเปิดอยู่ ทำให้เด่นกว่าแถว "ล่าสุด" เพราะเป็นจุดอ้างอิงของคนใช้ */
    .lt-table .lt-current { background: #dbeafe; box-shadow: inset 3px 0 0 #2563eb; }
</style>
