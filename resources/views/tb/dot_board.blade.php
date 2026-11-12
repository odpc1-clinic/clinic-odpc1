@extends('layouts.app')

{{-- ทะเบียน DOT รวม — เปิดจากเมนู ไม่ต้องผ่าน visit
     หน้าตาตามตารางที่พยาบาลทำใน Excel: ชื่อ · วันเริ่มรักษา · เหตุผล DOT · สูตรยา · ช่องทาง · กำหนดการ
     แยกสองกลุ่ม: กินยาทุกวัน (TB) กับสัปดาห์ละ 1 ครั้ง (TPT 3HP)
     เลือกวันย้อนหลังได้ เพราะเซิร์ฟเวอร์เปิด จ.-ศ. 8.30-16.30 ของวันหยุดต้องมาลงวันจันทร์ --}}

@section('content')
<style>
    .tb-hero { background: linear-gradient(135deg,#b45309 0%,#f59e0b 100%); color:#fff; border-radius:.75rem; }
    .tb-pill { border-radius:999px; padding:.35rem .9rem; font-weight:600; text-decoration:none; display:inline-block; }
    .tb-pill.off { background:#fff; color:#b45309; border:1px solid #f0c088; }
    .tb-pill.on  { background:#b45309; color:#fff; }
    .dot-mark { width:2.6rem; height:2.4rem; font-size:1.05rem; }
    .dot-row-done { background:#f3fbf5; }
    .dot-table td { vertical-align: top; }
    .dot-reason { color:#c1121f; font-weight:600; }
    .dot-regimen { font-size:.8rem; line-height:1.35; white-space:pre-wrap; }
    .dot-cell-input { font-size:.82rem; }
    .dot-group-head { background:#fff4e2; border-left:4px solid #b45309; }
    /* ส่งเวร — สีเหลืองเด่นกว่าหมายเหตุรายวัน เพราะเป็นเรื่องที่คนเวรต่อไปต้องทำ */
    .dot-handover { background:#fff3cd; border:1px solid #ffda6a; border-radius:.375rem; padding:.35rem .5rem; margin-bottom:.35rem; font-size:.82rem; }
</style>

@php
    $thMonth = \App\Utillity::TH_MONTH_SHORT;
    $monthLabel = $thMonth[(int) substr($date, 5, 2)] . ' ' . substr($date, 0, 4);
@endphp

<div class="container container-wide">
    <div class="tb-hero p-3 mb-3 shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <div class="opacity-75 small"><i class="fa-solid fa-lungs me-1"></i> คลินิกวัณโรค</div>
            <h4 class="mb-0 fw-bold"><i class="fa-solid fa-list-check me-1"></i> ติดตามผู้ป่วย DOT
                <span class="fs-6 fw-normal opacity-75 ms-2">ประจำเดือน {{ $monthLabel }}</span>
            </h4>
        </div>
        <a href="{{ route('tb.index') }}" class="btn btn-light shadow-sm">
            <i class="fa fa-table-list"></i> ทะเบียนวัณโรค
        </a>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('tb.dotBoard', ['date' => \App\Utillity::db2thDate($date)]) }}"
           class="tb-pill {{ $view === 'on' ? 'on' : 'off' }}">กำลังกำกับกินยา {{ $counts['on'] }}</a>
        <a href="{{ route('tb.dotBoard', ['date' => \App\Utillity::db2thDate($date), 'view' => 'off']) }}"
           class="tb-pill {{ $view === 'off' ? 'on' : 'off' }}">พัก DOT ไว้ {{ $counts['off'] }}</a>
    </div>

    <form method="POST" action="{{ route('tb.dotBoard.save') }}" autocomplete="off" id="dot-board-form">
        @csrf
        <input type="hidden" name="view" value="{{ $view }}">

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-1">วันที่ลง DOT</label>
                        <div class="input-group">
                            {{-- data-saved = วันที่ของข้อมูลที่แสดงอยู่ หมายเหตุต้องบันทึกลงวันนี้ ไม่ใช่วันที่เพิ่งพิมพ์เปลี่ยนในช่อง --}}
                            {{-- ช่องที่เห็นไม่มี name — ปุ่มบันทึกทั้งหน้าใช้วันที่ของหน้าที่เปิดอยู่ (hidden ข้างล่าง)
                                 เดิมช่องนี้ชื่อ date: เปลี่ยนวันในช่องแล้วไม่ได้กดเปิด → กดบันทึก ติ๊กของวันที่แสดงอยู่ไปลงวันที่เพิ่งพิมพ์ (2569-10-30) --}}
                            <input type="text" class="form-control tb-date" id="dot-board-date" autocomplete="off"
                                   value="{{ \App\Utillity::db2thDate($date) }}" data-saved="{{ \App\Utillity::db2thDate($date) }}" placeholder="วว/ดด/ปปปป">
                            <input type="hidden" name="date" value="{{ \App\Utillity::db2thDate($date) }}">
                            {{-- เลือกวันจากปฏิทิน = เปิดหน้าวันนั้นให้เลย (สัญลักษณ์/หมายเหตุ/ส่งเวร ลงวันที่ของหน้าที่เปิดอยู่)
                                 ปุ่มนี้ไว้กดหลังพิมพ์วันที่เอง · ปุ่มธรรมดา ไม่ใช่ submit เพราะอยู่ในฟอร์มบันทึก (ห้ามฟอร์มซ้อน) --}}
                            <button type="button" class="btn btn-outline-secondary" id="dot-board-go"
                                    data-base="{{ route('tb.dotBoard') }}" data-view="{{ $view }}">เปิดวันที่นี้</button>
                        </div>
                        <div class="small text-danger fw-bold d-none" id="dot-board-date-warn">
                            ⚠ ยังแสดงข้อมูลวันที่ {{ \App\Utillity::db2thDate($date) }} — กด "เปิดวันที่นี้" ก่อน ไม่งั้นบันทึกลงวันที่ {{ \App\Utillity::db2thDate($date) }}
                        </div>
                        <div class="form-text">
                            {{ \App\Models\TbRegistration::$weekdayOption[$weekday] ?? '' }} ·
                            เลือกย้อนหลังได้ ของเสาร์-อาทิตย์มาลงวันจันทร์ได้เลย
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="small text-muted">
                            ความหมายของสัญลักษณ์:
                            @foreach(\App\Models\TbDotDay::$markOption as $k => $label)
                                <span class="badge bg-light text-dark border me-1">{{ $label }}</span>
                            @endforeach
                        </div>
                        <div class="small text-muted mt-1">
                            ช่องเหตุผล · ช่องทาง · กำหนดการ · สูตรยา แก้ได้ตรงนี้เลย แล้วกดบันทึกครั้งเดียว
                        </div>
                    </div>
                </div>

                @if($communityCount)
                    <div class="small text-muted mb-2">
                        <i class="fa fa-people-arrows me-1"></i> ไม่แสดง {{ $communityCount }} ราย ที่ DOT โดย อสม./รพ.สต. — ดูข้อมูลในบัตร TB 01 ของคนไข้
                    </div>
                @endif
                @php $__hoCount = collect($handovers)->filter(fn ($l) => $l->count())->count(); @endphp
                @if($view === 'on' && $__hoCount)
                    <div class="alert py-2 px-3" style="background:#fff3cd; border:1px solid #ffda6a;">
                        <i class="fa fa-bullhorn me-1"></i> <b>มีเรื่องส่งเวรค้างอยู่ {{ $__hoCount }} ราย</b>
                        — ดูกล่องสีเหลืองในคอลัมน์ขวาสุด ทำเสร็จแล้วกด "✓ ปัญหาสิ้นสุด"
                    </div>
                @endif

                {{-- คนที่พัก DOT ไว้แต่ยังส่งคลิปเข้ามา — ถ้าไม่เตือน พยาบาลจะไม่เห็นเลยเพราะไม่อยู่ในตารางนี้ --}}
                @if($view === 'on' && $offButSent->isNotEmpty())
                    <div class="alert alert-warning py-2 px-3">
                        <i class="fa fa-triangle-exclamation me-1"></i>
                        <b>มีคนไข้ที่พัก DOT ไว้ส่งหลักฐานเข้ามาวันนี้ {{ $offButSent->count() }} ราย:</b>
                        @foreach($offButSent as $__o)
                            <a href="{{ route('tb.show', $__o->id) }}" target="_blank" class="me-2">
                                {{ optional($__o->patient)->name }} {{ optional($__o->patient)->surname }}
                            </a>
                        @endforeach
                        <a href="{{ route('tb.dotBoard', ['date' => \App\Utillity::db2thDate($date), 'view' => 'off']) }}">
                            ดูในแท็บพัก DOT
                        </a>
                    </div>
                @endif

                @if($view === 'off')
                    @include('tb._dot_board_off', ['rows' => $rows])
                @else
                    @foreach($groups as $freq => $list)
                        <div class="dot-group-head px-3 py-2 mb-2 mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="fw-bold" style="color:#b45309;">
                                <i class="fa fa-{{ $freq == \App\Models\TbRegistration::DOT_WEEKLY ? 'calendar-week' : 'calendar-day' }} me-1"></i>
                                {{ \App\Models\TbRegistration::$dotFrequencyOption[$freq] }}
                                <span class="text-muted fw-normal">({{ $list->count() }} ราย)</span>
                            </div>
                            @if($freq == \App\Models\TbRegistration::DOT_WEEKLY)
                                {{-- รายสัปดาห์: โชว์เฉพาะคนที่ถึงคิววันนี้ (คลินิกขอ 2569-10-27 — กินวันพฤหัส วันอื่นไม่ต้องขึ้นชื่อ)
                                     คนที่ยังไม่ได้ตั้งวันกิน ยังโชว์ไว้ให้ตั้ง · ปุ่มนี้เปิดดูทั้งหมดได้ --}}
                                @php $__notDue = $list->filter(fn ($r) => $r->dot_weekday && (int) $r->dot_weekday !== $weekday)->count(); @endphp
                                <div class="small text-muted d-flex align-items-center gap-2">
                                    แสดงเฉพาะคนที่ถึงคิววันนี้ ({{ $list->count() - $__notDue }} ราย)
                                    @if($__notDue)
                                        <button type="button" class="btn btn-sm btn-outline-secondary js-dot-show-notdue">
                                            ดูคนที่ไม่ใช่วันนี้ ({{ $__notDue }})
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>

                        @if($list->isEmpty())
                            <div class="text-muted small mb-3 px-2">— ยังไม่มีคนไข้ในกลุ่มนี้</div>
                        @else
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered align-middle dot-table mb-0">
                                <thead class="table-light">
                                    <tr class="text-center">
                                        <th style="width:2.5rem">ลำดับ</th>
                                        <th style="width:13%">ชื่อ-สกุล</th>
                                        <th style="width:7%">วันที่เริ่มรักษา</th>
                                        <th style="width:9%">ระยะ / เหตุผล DOT</th>
                                        <th style="width:17%">สูตรยา</th>
                                        <th style="width:10%">ช่องทางการ DOT<div class="fw-normal small text-muted">+ ผู้รับผิดชอบ</div></th>
                                        <th style="width:9%">กำหนดการ</th>
                                        <th style="width:11%">หลักฐานที่ส่งมา</th>
                                        <th style="width:8%">ลง DOT วันนี้</th>
                                        <th style="width:18%">บันทึกปัญหา / หมายเหตุ
                                            <div class="fw-normal small text-muted">บันทึกเองทันที ไม่ต้องกดปุ่ม</div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($list as $i => $reg)
                                    @php
                                        $mark = $marks->get($reg->id)->mark ?? '';
                                        $mine = $subs[$reg->id] ?? collect();
                                        $due  = $freq != \App\Models\TbRegistration::DOT_WEEKLY
                                                || (int) $reg->dot_weekday === $weekday;
                                        // ยังไม่มีใครลง: รอเช็ค / กินยาเอง (วันหยุด) / ไม่ได้บันทึก — กติกาอยู่ที่ TbRegistration::dotUnrecordedState
                                        $__state = $mark === '' ? $reg->dotUnrecordedState($date, $dotSince[$reg->id] ?? null) : null;
                                    @endphp
                                    <tr class="{{ $mark === 'taken' ? 'dot-row-done' : '' }} {{ $freq == \App\Models\TbRegistration::DOT_WEEKLY && ! $due && $reg->dot_weekday ? 'dot-row-notdue d-none' : '' }}">
                                        <td class="text-center text-muted">{{ $i + 1 }}</td>

                                        <td>
                                            {{-- เปิดในแท็บเดิม แล้วในบัตรมีปุ่ม "กลับหน้าติดตาม DOT" (พยาบาลขอ 2569-09-30) --}}
                                            <a href="{{ route('tb.show', ['id' => $reg->id, 'from' => 'dot', 'date' => \App\Utillity::db2thDate($date)]) }}"
                                               class="fw-bold text-decoration-none js-dot-leave">
                                                {{ optional($reg->patient)->name }} {{ optional($reg->patient)->surname }}
                                            </a>
                                            <div class="small text-muted">
                                                HN {{ optional($reg->patient)->code }}
                                                @if($reg->tb_no) · {{ $reg->tb_no }} @endif
                                            </div>
                                            {{-- นัดครั้งต่อไป + ต้องตรวจอะไรก่อน (จากใบนัดวัณโรค) — พยาบาลขอ 2569-09-30 --}}
                                            @if($__na = $nextAppts[$reg->patient_id] ?? null)
                                                <div class="small mt-1 p-1 rounded" style="background:#eef6ff; border:1px solid #cfe2ff;">
                                                    <i class="fa fa-calendar-day me-1 text-primary"></i>
                                                    นัด <b>{{ \App\Utillity::db2thDate($__na->appointment_date) }}</b>@if($__na->appt_time) {{ $__na->appt_time }} น.@endif
                                                    @if($__na->pre_visit)
                                                        <div class="text-danger"><i class="fa fa-flask me-1"></i>ก่อนพบแพทย์: {{ $__na->pre_visit }}</div>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="small text-muted mt-1">ยังไม่มีนัดครั้งต่อไป</div>
                                            @endif
                                            @if($freq == \App\Models\TbRegistration::DOT_WEEKLY && $due)
                                                <span class="badge bg-warning text-dark">ถึงคิววันนี้</span>
                                            @endif
                                        </td>

                                        <td class="text-center small">
                                            {{ $reg->treatment_start_date ? \App\Utillity::db2thDate($reg->treatment_start_date) : '—' }}
                                            <div class="text-muted">เดือนที่ {{ $reg->monthsOnTreatment() }}</div>
                                        </td>

                                        {{-- เหตุผลที่ต้อง DOT: Active phase · MDR · Pre-XDR · LTBI (พิมพ์เองได้) --}}
                                        <td>
                                            <input type="text" list="dot-reasons" class="form-control form-control-sm dot-cell-input dot-reason"
                                                   name="plan[{{ $reg->id }}][reason]" value="{{ $reg->dot_reason }}" placeholder="เช่น MDR">
                                        </td>

                                        {{-- สูตรยาแบบรายเม็ดตามที่เขียนในตาราง
                                             ดึงจากการสั่งยาครั้งล่าสุดของหมอ (ตารางขนาดยาในบัตร TB 01) — พยาบาลขอ 2569-09-30
                                             ช่องว่าง = เติมให้เลย · มีข้อความเดิมแล้ว = ไม่ทับ แต่โชว์ของหมอไว้ข้างล่างพร้อมปุ่มใช้แทน --}}
                                        @php
                                            $__order   = $reg->latestOrderDotText();
                                            $__regText = trim((string) $reg->dot_regimen_text) !== ''
                                                ? $reg->dot_regimen_text : ($__order['text'] ?? '');
                                        @endphp
                                        <td>
                                            <textarea name="plan[{{ $reg->id }}][regimen_text]" rows="{{ max(2, substr_count((string) $__regText, "\n") + 1) }}"
                                                      class="form-control form-control-sm dot-regimen js-dot-regimen"
                                                      @if(trim((string) $reg->dot_regimen_text) === '' && $__order) data-autofill="1" @endif
                                                      placeholder="เช่น BDQ 100 mg. 1 tab (จ-พ-ศ)">{{ $__regText }}</textarea>
                                            @if($__order)
                                                @if(trim((string) $reg->dot_regimen_text) === '')
                                                    <div class="small text-success">↑ ดึงจากที่หมอสั่งล่าสุด ({{ $__order['label'] }}) — กดบันทึกด้านล่างเพื่อเก็บ</div>
                                                @elseif(trim((string) $reg->dot_regimen_text) !== trim($__order['text']))
                                                    <details class="small mt-1">
                                                        <summary class="text-primary" style="cursor:pointer;">ดูที่หมอสั่งล่าสุด ({{ $__order['label'] }})</summary>
                                                        <div class="dot-regimen text-muted border rounded p-1 mt-1">{{ $__order['text'] }}</div>
                                                        <button type="button" class="btn btn-link btn-sm p-0 js-fill-regimen" data-text="{{ $__order['text'] }}">
                                                            ใช้ข้อความนี้แทน
                                                        </button>
                                                    </details>
                                                @endif
                                            @else
                                                <div class="small text-muted">ยังไม่มีการสั่งยาในบัตร TB 01</div>
                                            @endif
                                        </td>

                                        <td>
                                            <input type="text" list="dot-channels" class="form-control form-control-sm dot-cell-input"
                                                   name="plan[{{ $reg->id }}][channel]" value="{{ $reg->dot_channel }}" placeholder="เช่น Line OA">
                                            {{-- ผู้รับผิดชอบเคส — บางรายมีคนรับเป็นเคสประจำ คนอยู่เวรไม่ใช่คน DOT (พยาบาลขอ 2569-09-30) --}}
                                            <input type="text" list="dot-staff" class="form-control form-control-sm dot-cell-input mt-1"
                                                   name="plan[{{ $reg->id }}][owner]" value="{{ $reg->dot_owner }}" placeholder="ผู้รับผิดชอบเคส">
                                        </td>

                                        {{-- กำหนดการ: ความถี่ + วันในสัปดาห์ (ถ้ารายสัปดาห์) + เวลานัด --}}
                                        <td>
                                            <select name="plan[{{ $reg->id }}][frequency]" class="form-select form-select-sm dot-cell-input mb-1 js-dot-freq">
                                                @foreach(\App\Models\TbRegistration::$dotFrequencyOption as $k => $label)
                                                    <option value="{{ $k }}" {{ (int) $reg->dot_frequency === $k ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <select name="plan[{{ $reg->id }}][weekday]"
                                                    class="form-select form-select-sm dot-cell-input mb-1 js-dot-weekday {{ $reg->isWeeklyDot() ? '' : 'd-none' }}">
                                                <option value="">— วันไหน —</option>
                                                @foreach(\App\Models\TbRegistration::$weekdayOption as $k => $label)
                                                    <option value="{{ $k }}" {{ (int) $reg->dot_weekday === $k ? 'selected' : '' }}>วัน{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            {{-- เลือกเวลาแทนพิมพ์อิสระ — ระบบใช้เวลานี้ตัดสิน "รอเช็ค" (กินหลัง 16:00) (2569-10-01) --}}
                                            <input type="time" class="form-control form-control-sm dot-cell-input"
                                                   name="plan[{{ $reg->id }}][time]" value="{{ $reg->dotTimeHm() }}" title="เวลากินยา">
                                        </td>

                                        {{-- คลิป/รูปที่คนไข้ส่งเข้าไลน์ของวันนั้น — ดูก่อนติ๊ก
                                             AI ช่วยนับเม็ดยาเป็นแค่ข้อมูลประกอบ คนตัดสินคือพยาบาล --}}
                                        <td>
                                            @forelse($mine as $sub)
                                                <div class="mb-1">
                                                    <a href="{{ route('tb.dotMedia', $sub->id) }}" target="_blank" class="small">
                                                        <i class="fa {{ $sub->kind === 'image' ? 'fa-image' : 'fa-video' }} me-1"></i>
                                                        {{ $sub->received_at ? $sub->received_at->format('H:i') : 'ไม่ทราบเวลา' }}
                                                    </a>
                                                    @if($sub->ai_status === 'ok')
                                                        <span class="badge bg-success">AI: ตรง</span>
                                                    @elseif($sub->ai_status === 'warn')
                                                        <span class="badge bg-warning text-dark" title="{{ $sub->ai_note }}">AI: ต้องดู</span>
                                                    @elseif($sub->ai_status)
                                                        <span class="badge bg-light text-dark border" title="{{ $sub->ai_note }}">AI: {{ $sub->ai_status }}</span>
                                                    @endif
                                                    @if($sub->isConfirmed())
                                                        <i class="fa fa-circle-check text-success" title="ยืนยันแล้ว"></i>
                                                    @endif
                                                </div>
                                            @empty
                                                <span class="small text-muted">— ยังไม่ส่งเข้ามา</span>
                                            @endforelse
                                        </td>

                                        {{-- ติ๊กสัญลักษณ์ของวันที่เลือก — กดวนเหมือนปฏิทินในบัตร --}}
                                        <td class="text-center">
                                            <input type="hidden" name="mark[{{ $reg->id }}]" value="{{ $mark }}" class="js-board-input">
                                            <button type="button" class="btn btn-sm dot-mark js-board-cell" data-state="{{ $__state }}">{{ \App\Models\TbDotDay::$markChar[$mark] ?? '' }}</button>
                                            <div class="small text-muted js-board-text">{{ \App\Models\TbDotDay::$markOption[$mark] ?? 'ยังไม่ลง' }}</div>
                                            {{-- ยังไม่มีใครลง (แสดงอย่างเดียว ไม่บันทึกลงฐาน) — ติ๊กแล้วข้อความนี้หายไปเอง --}}
                                            @if($__state === 'missed')
                                                <div class="small fw-bold text-danger js-board-state">✗ ไม่ได้บันทึก</div>
                                            @elseif($__state === 'pending')
                                                <div class="small fw-bold text-secondary js-board-state">⏱ รอเช็ค · {{ \App\Models\TbRegistration::dotCheckDeadlineText($date) }}</div>
                                            @elseif($__state === 'self')
                                                <div class="small fw-bold js-board-state" style="color:#0369a1;">⌂ คนไข้กินยาเอง (วันหยุด)</div>
                                            @endif
                                        </td>

                                        {{-- หมายเหตุ/ปัญหาของวันนั้น — ช่องใหญ่ท้ายแถว บันทึกเองทันที (พยาบาลขอ 2569-09-30)
                                             ไม่มี name จึงไม่ถูกส่งไปกับปุ่มบันทึกทั้งหน้า (กันค่าเก่าทับค่าที่เพิ่งบันทึก)
                                             หมอเห็นเป็น hover + รายการใต้ปฏิทินในบัตร TB 01 --}}
                                        <td>
                                            {{-- ส่งเวร — ค้างโชว์ทุกวันจนกว่าจะติ๊ก "ปัญหาสิ้นสุด" (พยาบาลขอ 2569-09-30) --}}
                                            <div class="js-handover-box mb-2" data-reg="{{ $reg->id }}">
                                                @foreach($handovers[$reg->id] ?? [] as $__h)
                                                    <div class="dot-handover js-handover-item">
                                                        <div><i class="fa fa-bullhorn me-1"></i><span class="js-handover-text">{{ $__h->note }}</span></div>
                                                        <div class="d-flex justify-content-between align-items-center gap-1 mt-1">
                                                            <span class="text-muted me-auto">@if($__h->forDateText())<b class="text-dark">{{ $__h->forDateText() }}</b> · @endif{{ optional($__h->creator)->name }} · {{ $__h->created_at->format('d/m H:i') }}</span>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 js-handover-edit" title="แก้ข้อความ (พิมพ์ผิด)"
                                                                    data-url="{{ route('tb.dotHandover.update', $__h->id) }}"><i class="fa fa-pen"></i> แก้</button>
                                                            <button type="button" class="btn btn-sm btn-outline-success py-0 js-handover-done"
                                                                    data-url="{{ route('tb.dotHandover.resolve', $__h->id) }}">✓ ปัญหาสิ้นสุด</button>
                                                        </div>
                                                    </div>
                                                @endforeach
                                                <div class="input-group input-group-sm">
                                                    <input type="text" maxlength="500" class="form-control js-handover-input" placeholder="+ ส่งเวร (ทีละเรื่อง)" title="ใส่ทีละเรื่อง แต่ละเรื่องติ๊กสิ้นสุดแยกกันได้">
                                                    <button type="button" class="btn btn-outline-warning js-handover-add">ส่งเวร</button>
                                                </div>
                                            </div>
                                            <div class="small text-muted mb-1">หมายเหตุของวันนี้</div>
                                            <textarea rows="3" maxlength="255" class="form-control form-control-sm js-dot-note"
                                                      data-reg="{{ $reg->id }}"
                                                      placeholder="เช่น ส่งคลิปช้า โทรตามแล้ว / อาเจียนหลังกินยา">{{ optional($marks->get($reg->id))->note }}</textarea>
                                            <div class="small mt-1 js-dot-note-status text-muted">
                                                {{ optional($marks->get($reg->id))->note ? '✓ บันทึกไว้แล้ว' : '' }}
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    @endforeach
                @endif
            </div>

            @if($rows->isNotEmpty())
                <div class="card-footer bg-white d-flex flex-wrap gap-2">
                    <button class="btn btn-primary"><i class="fa fa-save"></i> บันทึก</button>
                    @if($view === 'on')
                        <button type="button" class="btn btn-outline-secondary" id="board-fill">ติ๊ก ✓ ให้ทุกคน</button>
                        <button type="button" class="btn btn-outline-danger" id="board-clear">ล้างการติ๊กทั้งหน้า</button>
                    @endif
                    <span class="ms-auto small text-muted align-self-center">
                        ติ๊กกินยาและหมายเหตุบันทึกเองทันที · ปุ่มบันทึกใช้กับช่องแผน DOT (ระยะ/สูตรยา/ช่องทาง/เวลา) · เปิด/ปิด DOT ทำที่หน้าตรวจ (แถบบัตร TB 01)
                    </span>
                </div>
            @endif
        </div>
    </form>

    <datalist id="dot-reasons">
        @foreach(\App\Models\TbRegistration::$dotReasonOption as $o)<option value="{{ $o }}">@endforeach
    </datalist>
    <datalist id="dot-staff">
        @foreach($staffNames as $o)<option value="{{ $o }}">@endforeach
    </datalist>
    <datalist id="dot-channels">
        @foreach(\App\Models\TbRegistration::$dotChannelOption as $o)<option value="{{ $o }}">@endforeach
    </datalist>
</div>

<style>
    .dot-mark.dot-st-pending { border: 2px dashed #9ca3af !important; background: #f9fafb !important; color: #6b7280 !important; }
    .dot-mark.dot-st-self    { border: 2px dashed #38bdf8 !important; background: #f0f9ff !important; color: #0369a1 !important; }
    .dot-mark.dot-st-missed  { border: 2px dashed #dc3545 !important; background: #fff5f5 !important; color: #dc3545 !important; }
</style>
<script>
// รายสัปดาห์: เปิด/ปิดแถวของคนที่ไม่ใช่วันกินวันนี้ (2569-10-27) — เปิดดูแล้วแถวจางลง แยกจากคนที่ถึงคิว
document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('.js-dot-show-notdue');
    if (!b) return;
    var rows = document.querySelectorAll('.dot-row-notdue'), show = rows.length > 0 && rows[0].classList.contains('d-none');
    rows.forEach(function (r) { r.classList.toggle('d-none', !show); r.style.opacity = show ? '.6' : ''; });
    b.textContent = show ? 'ซ่อนคนที่ไม่ใช่วันนี้' : 'ดูคนที่ไม่ใช่วันนี้ (' + rows.length + ')';
});
// ดึงคนไข้ที่พัก DOT กลับมา (แท็บพัก DOT) — ปุ่มอยู่ในฟอร์มของหน้า จึงสร้างฟอร์มใหม่ต่อกับ body (ห้ามซ้อน <form>)
document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('.js-dot-resume');
    if (!b) return;
    if (!confirm('ดึง ' + (b.dataset.name || 'คนไข้') + ' กลับมา DOT ?')) return;
    var f = document.createElement('form');
    f.method = 'POST'; f.action = b.dataset.url; f.style.display = 'none';
    f.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}">'
                + '<input type="hidden" name="dot_active" value="1">';
    document.body.appendChild(f);
    f.submit();
});

// ปุ่มติ๊กของแต่ละแถว — วนสัญลักษณ์เหมือนปฏิทินกินยาในบัตร
(function () {
    var ORDER = ['', 'taken', 'self', 'missed', 'dispensed', 'start'];   // ⌂ กินยาเอง ต่อจาก ✓ (วันหยุดกดสองทีถึง)
    var CHAR  = @json(\App\Models\TbDotDay::$markChar);
    var TEXT  = @json(\App\Models\TbDotDay::$markOption);
    var CLASS = { taken: 'btn-success', self: 'btn-info', start: 'btn-primary', dispensed: 'btn-secondary', missed: 'btn-danger', '': 'btn-outline-secondary' };

    function paint(btn, mark) {
        btn.textContent = CHAR[mark] || '';
        Object.keys(CLASS).forEach(function (k) { btn.classList.remove(CLASS[k]); });
        btn.classList.add(CLASS[mark] || CLASS['']);
        // ยังไม่ลง: กรอบตามสถานะ (รอเช็ค = เทาเส้นประ · วันหยุด = ฟ้าเส้นประ · ไม่ได้บันทึก = แดงเส้นประ ✗)
        var st = mark === '' ? (btn.dataset.state || '') : '';
        btn.classList.toggle('dot-st-pending', st === 'pending');
        btn.classList.toggle('dot-st-self', st === 'self');
        btn.classList.toggle('dot-st-missed', st === 'missed');
        if (st === 'missed') { btn.textContent = '✗'; }
        var stx = btn.parentNode.querySelector('.js-board-state');
        if (stx) { stx.classList.toggle('d-none', mark !== ''); }
        var t = btn.parentNode.querySelector('.js-board-text');
        if (t) { t.textContent = TEXT[mark] || 'ยังไม่ลง'; }
        var tr = btn.closest('tr');
        if (tr) { tr.classList.toggle('dot-row-done', mark === 'taken'); }
    }
    // ---- ติ๊กแล้วบันทึกเองทันที (พยาบาลขอ 2569-10-01) — กดวนหลายครั้งติดกัน ส่งค่าสุดท้ายครั้งเดียว
    var MARK_URL = @json(route('tb.dotBoard.mark'));
    var MARK_TOKEN = @json(csrf_token());
    window.__dotMarkPending = {};
    function status(btn, cls, text) {
        var st = btn.parentNode.querySelector('.js-board-status');
        if (!st) {
            st = document.createElement('div');
            st.className = 'small js-board-status';
            btn.parentNode.appendChild(st);
        }
        st.className = 'small js-board-status ' + cls;
        st.textContent = text;
    }
    function saveMark(btn) {
        var inp = btn.parentNode.querySelector('.js-board-input');
        var reg = (inp.name.match(/^mark\[(\d+)\]$/) || [])[1];
        if (!reg) return;
        var sent = inp.value;
        status(btn, 'text-muted', 'กำลังบันทึก…');
        var body = new URLSearchParams();
        body.append('_token', MARK_TOKEN);
        body.append('reg', reg);
        body.append('date', document.getElementById('dot-board-date').dataset.saved);
        body.append('mark', sent);
        fetch(MARK_URL, { method: 'POST', body: body, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { if (!r.ok || !j.ok) throw j; return j; }); })
            .then(function (j) {
                if (inp.value === sent) { delete window.__dotMarkPending[reg]; status(btn, 'text-success', '✓ บันทึกแล้ว ' + j.saved_at + ' น.'); }
            })
            .catch(function () { status(btn, 'text-danger fw-bold', '✗ บันทึกไม่สำเร็จ — กดปุ่มบันทึกด้านล่าง'); });
    }
    function setCell(btn, mark) {
        var inp = btn.parentNode.querySelector('.js-board-input');
        inp.value = mark;
        paint(btn, mark);
        var reg = (inp.name.match(/^mark\[(\d+)\]$/) || [])[1];
        if (reg) { window.__dotMarkPending[reg] = true; }
        status(btn, 'text-warning', 'ยังไม่บันทึก…');
        clearTimeout(btn._t);
        btn._t = setTimeout(function () { saveMark(btn); }, 600);
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-board-cell');
        if (!btn) return;
        var cur = btn.parentNode.querySelector('.js-board-input').value || '';
        setCell(btn, ORDER[(ORDER.indexOf(cur) + 1) % ORDER.length]);
    });

    document.querySelectorAll('.js-board-cell').forEach(function (b) {
        paint(b, b.parentNode.querySelector('.js-board-input').value || '');
    });

    var fill = document.getElementById('board-fill');
    var clear = document.getElementById('board-clear');
    if (fill) fill.addEventListener('click', function () {
        document.querySelectorAll('.js-board-cell').forEach(function (b) { setCell(b, 'taken'); });
    });
    if (clear) clear.addEventListener('click', function () {
        if (!confirm('ล้างสัญลักษณ์ของวันนี้ทั้งหน้า?')) return;
        document.querySelectorAll('.js-board-cell').forEach(function (b) { setCell(b, ''); });
    });

    // เปิดดูวันอื่น — โหลดหน้าใหม่พร้อมวันที่เลือก
    // เลือกจากปฏิทินแล้วเปิดให้เลย (ผูกท้ายหน้า) · พิมพ์เองแล้วกด Enter/ปุ่ม · ยังไม่เปิด = ขึ้นแดงเตือนว่าบันทึกลงวันไหน (2569-10-30)
    var go = document.getElementById('dot-board-go');
    var dEl = document.getElementById('dot-board-date');
    window.__dotOpenDate = function () {
        var d = dEl.value.trim();
        if (!d || d === dEl.dataset.saved) { dateWarn(); return; }
        window.location = go.dataset.base + '?date=' + encodeURIComponent(d) + '&view=' + go.dataset.view;
    };
    function dateWarn() {
        var w = document.getElementById('dot-board-date-warn');
        if (w) w.classList.toggle('d-none', dEl.value.trim() === dEl.dataset.saved);
    }
    if (go) go.addEventListener('click', window.__dotOpenDate);
    if (dEl) {
        dEl.addEventListener('input', dateWarn);
        dEl.addEventListener('change', dateWarn);
        // Enter ในช่องวันที่ = เปิดวันนั้น (ไม่ให้ไป submit ฟอร์มบันทึกทั้งหน้าด้วยวันที่ผิด)
        dEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); window.__dotOpenDate(); } });
    }

    document.addEventListener('change', function (e) {
        // รายสัปดาห์เท่านั้นที่ต้องเลือกว่าวันไหน
        if (e.target.classList.contains('js-dot-freq')) {
            var wd = e.target.closest('td').querySelector('.js-dot-weekday');
            if (wd) { wd.classList.toggle('d-none', e.target.value !== '2'); }
        }
    });

    // ---- หมายเหตุ: บันทึกเองทันที (หยุดพิมพ์ 0.8 วิ หรือออกจากช่อง) — พยาบาลขอ 2569-09-30
    var NOTE_URL = @json(route('tb.dotBoard.note'));
    var TOKEN    = @json(csrf_token());
    var pending  = {};   // ช่องที่พิมพ์แล้วยังไม่ได้บันทึก
    function saveNote(ta) {
        var st = ta.parentNode.querySelector('.js-dot-note-status');
        var reg = ta.dataset.reg;
        clearTimeout(ta._t);
        if (st) { st.className = 'small mt-1 js-dot-note-status text-muted'; st.textContent = 'กำลังบันทึก…'; }
        var body = new URLSearchParams();
        body.append('_token', TOKEN);
        body.append('reg', reg);
        body.append('date', document.getElementById('dot-board-date').dataset.saved);
        body.append('note', ta.value);
        var sent = ta.value;
        return fetch(NOTE_URL, { method: 'POST', body: body, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { if (!r.ok || !j.ok) throw j; return j; }); })
            .then(function (j) {
                if (ta.value === sent) { delete pending[reg]; }
                if (st) { st.className = 'small mt-1 js-dot-note-status text-success'; st.textContent = '✓ บันทึกแล้ว ' + j.saved_at + ' น.'; }
            })
            .catch(function () {
                if (st) { st.className = 'small mt-1 js-dot-note-status text-danger fw-bold'; st.textContent = '✗ บันทึกไม่สำเร็จ — ลองพิมพ์ใหม่ หรือกดปุ่มบันทึกด้านล่าง'; }
            });
    }
    document.addEventListener('input', function (e) {
        var ta = e.target.closest && e.target.closest('.js-dot-note');
        if (!ta) return;
        pending[ta.dataset.reg] = true;
        var st = ta.parentNode.querySelector('.js-dot-note-status');
        if (st) { st.className = 'small mt-1 js-dot-note-status text-warning'; st.textContent = 'ยังไม่บันทึก…'; }
        clearTimeout(ta._t);
        ta._t = setTimeout(function () { saveNote(ta); }, 800);
    });
    document.addEventListener('focusout', function (e) {
        var ta = e.target.closest && e.target.closest('.js-dot-note');
        if (ta && pending[ta.dataset.reg]) { saveNote(ta); }
    });

    // ---- ส่งเวร: เพิ่ม / ติ๊กสิ้นสุด ด้วย fetch (อยู่ในฟอร์มบันทึกทั้งหน้า ห้ามฟอร์มซ้อน)
    var HO_URL = @json(route('tb.dotHandover.add'));
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
    function post(url, data) {
        var body = new URLSearchParams(); body.append('_token', TOKEN);
        Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
        return fetch(url, { method: 'POST', body: body, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { if (!r.ok || !j.ok) throw j; return j; }); });
    }
    function addHandover(box) {
        var inp = box.querySelector('.js-handover-input');
        var text = inp.value.trim();
        if (!text) { inp.focus(); return; }
        inp.disabled = true;
        post(HO_URL, { reg: box.dataset.reg, note: text, date: document.getElementById('dot-board-date').dataset.saved }).then(function (j) {
            var el = document.createElement('div');
            el.className = 'dot-handover js-handover-item';
            el.innerHTML = '<div><i class="fa fa-bullhorn me-1"></i><span class="js-handover-text">' + esc(j.note) + '</span></div>'
                + '<div class="d-flex justify-content-between align-items-center gap-1 mt-1"><span class="text-muted me-auto">' + (j.for ? '<b class="text-dark">' + esc(j.for) + '</b> · ' : '') + esc(j.by) + ' · ' + esc(j.at) + '</span>'
                + '<button type="button" class="btn btn-sm btn-outline-secondary py-0 js-handover-edit" title="แก้ข้อความ (พิมพ์ผิด)" data-url="' + esc(j.update_url) + '"><i class="fa fa-pen"></i> แก้</button>'
                + '<button type="button" class="btn btn-sm btn-outline-success py-0 js-handover-done" data-url="' + esc(j.resolve_url) + '">✓ ปัญหาสิ้นสุด</button></div>';
            box.insertBefore(el, inp.closest('.input-group'));
            inp.value = '';
        }).catch(function (j) {
            alert((j && j.message) || 'ส่งเวรไม่สำเร็จ ลองใหม่อีกครั้ง');
        }).then(function () { inp.disabled = false; });
    }
    document.addEventListener('click', function (e) {
        var add = e.target.closest('.js-handover-add');
        if (add) { addHandover(add.closest('.js-handover-box')); return; }
        // แก้ข้อความส่งเวร (พิมพ์ผิดตอนรีบกด — พยาบาลขอ 2569-10-07)
        var edit = e.target.closest('.js-handover-edit');
        if (edit) {
            var txt = edit.closest('.js-handover-item').querySelector('.js-handover-text');
            var nv = prompt('แก้ข้อความส่งเวร', txt.textContent);
            if (nv === null || nv.trim() === '' || nv.trim() === txt.textContent.trim()) return;
            edit.disabled = true;
            post(edit.dataset.url, { note: nv.trim() }).then(function (j) { txt.textContent = j.note; })
                .catch(function (j) { alert((j && j.message) || 'แก้ไม่สำเร็จ ลองใหม่อีกครั้ง'); })
                .then(function () { edit.disabled = false; });
            return;
        }
        var done = e.target.closest('.js-handover-done');
        if (!done) return;
        if (!confirm('ปัญหานี้สิ้นสุดแล้ว? (จะหายจากหน้านี้ แต่ยังเก็บเป็นประวัติ)')) return;
        done.disabled = true;
        post(done.dataset.url).then(function () {
            done.closest('.js-handover-item').remove();
        }).catch(function () { done.disabled = false; alert('บันทึกไม่สำเร็จ ลองใหม่อีกครั้ง'); });
    });
    document.addEventListener('keydown', function (e) {
        // Enter ในช่องส่งเวร = กดส่งเวร (ไม่ให้ไป submit ฟอร์มบันทึกทั้งหน้า)
        if (e.key === 'Enter' && e.target.classList.contains('js-handover-input')) {
            e.preventDefault();
            addHandover(e.target.closest('.js-handover-box'));
        }
    });

    // ---- เตือนก่อนออกจากหน้า ถ้ามีอะไรแก้แล้วยังไม่ได้กดบันทึกทั้งหน้า (พยาบาลเจอข้อความหาย 2569-09-30)
    var form = document.getElementById('dot-board-form');
    var dirty = false, submitting = false;
    if (form) {
        // หมายเหตุ/ส่งเวรบันทึกเอง ไม่นับว่าค้าง — ยกเว้นพิมพ์ส่งเวรไว้แล้วยังไม่กดส่ง
        var own = function (el) { return el.classList.contains('js-dot-note') || el.classList.contains('js-handover-input') || el.id === 'dot-board-date'; };
        form.addEventListener('input', function (e) { if (!own(e.target)) dirty = true; });
        form.addEventListener('change', function (e) { if (!own(e.target)) dirty = true; });
        // ติ๊กกินยาบันทึกเองแล้ว ไม่นับว่าค้าง — ที่ยังส่งไม่เสร็จดูจาก __dotMarkPending ตอนออกจากหน้า
        form.addEventListener('submit', function () {
            submitting = true;
            // ติ๊กกินยาบันทึกเองไปแล้ว — ส่งเฉพาะช่องที่ยังบันทึกไม่สำเร็จ ไม่งั้นค่าตอนเปิดหน้าไปทับที่อีกเครื่องเพิ่งติ๊ก
            form.querySelectorAll('.js-board-input').forEach(function (inp) {
                var reg = (inp.name.match(/^mark\[(\d+)\]$/) || [])[1];
                if (reg && !(window.__dotMarkPending || {})[reg]) { inp.disabled = true; inp.dataset.skipSend = '1'; }
            });
            // ส่งเฉพาะช่องแผน DOT ที่แก้จริง — แท็บที่เปิดค้างจะไม่ทับค่าที่อีกเครื่องเพิ่งแก้ (รีวิว 2569-09-30)
            // ความถี่กับวันในสัปดาห์ผูกกัน แก้ตัวใดตัวหนึ่งส่งทั้งคู่
            var changed = function (el) {
                if (el.tagName === 'SELECT') {
                    return Array.prototype.some.call(el.options, function (o) { return o.selected !== o.defaultSelected; });
                }
                return el.value !== el.defaultValue || el.dataset.autofill === '1';
            };
            form.querySelectorAll('[name^="plan["]').forEach(function (el) {
                if (el.disabled) return;
                var keep = changed(el);
                if (!keep && /\[(frequency|weekday)\]$/.test(el.name)) {
                    var cell = el.closest('td');
                    keep = Array.prototype.some.call(cell.querySelectorAll('.js-dot-freq, .js-dot-weekday'), changed);
                }
                if (!keep) { el.disabled = true; el.dataset.skipSend = '1'; }
            });
        });
        // กดย้อนกลับมาจาก bfcache ช่องที่ปิดไว้ตอนส่งต้องกลับมาใช้ได้
        window.addEventListener('pageshow', function () {
            submitting = false;
            form.querySelectorAll('[data-skip-send]').forEach(function (el) { el.disabled = false; delete el.dataset.skipSend; });
        });
    }
    window.addEventListener('beforeunload', function (e) {
        if (submitting) return;
        // หมายเหตุที่ยังค้างอยู่ ส่งไปก่อนเลย แล้วยังเตือนด้วย
        document.querySelectorAll('.js-dot-note').forEach(function (ta) { if (pending[ta.dataset.reg]) saveNote(ta); });
        var hoTyped = Array.prototype.some.call(document.querySelectorAll('.js-handover-input'), function (i) { return i.value.trim() !== ''; });
        if (dirty || hoTyped || Object.keys(pending).length || Object.keys(window.__dotMarkPending || {}).length) { e.preventDefault(); e.returnValue = ''; }
    });

    // เติมสูตรยาจากบัตรให้ก่อน แล้วพยาบาลแก้รายละเอียด (เช่น จ,พ,ศ) เอง
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-fill-regimen');
        if (!btn) return;
        var box = btn.closest('td').querySelector('.js-dot-regimen');
        if (!box) return;
        if (box.value.trim() && !confirm('ทับข้อความเดิมในช่องสูตรยา?')) return;
        box.value = btn.dataset.text || '';
    });
})();
</script>
@endsection

@push('scripts')
{{-- ปฏิทิน พ.ศ. ไม่ได้อยู่ใน layout กลาง แต่ละหน้าต้องดึงเอง (เหมือนหน้าบัตร TB 01) --}}
<script src="/js/bootstrap-datepicker.js"></script>
<script src="/js/bootstrap-datepicker-thai.js"></script>
<script src="/js/bootstrap-datepicker.th.js"></script>
<script>
$(function () {
    $('.tb-date').datepicker({ language: 'th-th', autoclose: true, todayHighlight: true });
    // เลือกวันจากปฏิทินด้านบน = เปิดหน้าวันนั้นทันที (เดิมต้องกดปุ่มเพิ่ม พยาบาลไม่ได้กด แล้วบันทึกไปลงวันเดิม 2569-10-30)
    $('#dot-board-date').on('changeDate', function () { if (window.__dotOpenDate) setTimeout(window.__dotOpenDate, 0); });
});
</script>
@endpush
