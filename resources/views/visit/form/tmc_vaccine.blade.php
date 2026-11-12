{{-- ============================================================
     โซนฉีดวัคซีน TMC + สมุดเหลือง — แยกจาก tmc.blade มาไว้ "หลัง Lab"
     include ใน visit/form/index.blade.php (ภายในฟอร์มหลัก แต่ปุ่มเพิ่มยิงฟอร์มชั่วคราวเอง)
     ============================================================ --}}
@php
    $__page = request('page', $_GET['page'] ?? 1);
    $__vaccines = \App\Models\Medicine::where('is_vaccine', 1)->orderBy('name')->get();
    // Lot ที่เคยพิมพ์ของวัคซีนแต่ละตัว — เลือกวัคซีนแล้วเติมล็อตล่าสุดให้ (2569-10-14)
    $__vacLots = \App\Models\VisitVaccine::recentLots($__vaccines->pluck('id')->all());
    $__shots = \App\Models\VisitVaccine::where('patient_id', $visit->patient_id)
        ->orderByRaw('vaccinated_date IS NULL, vaccinated_date DESC, id DESC')->get();
    $__thisVisit = $__shots->where('visit_id', $visit->id);
    // ค่าวัคซีนครั้งนี้ — คิดแบบเดียวกับ Billing::syncVaccineItems (ราคาแคตตาล็อก, ฉีดที่นี่ + จ่ายให้ไปทานเอง)
    $__vacPrice = $__vaccines->pluck('price', 'id');
    $__billed = $__thisVisit->filter(fn ($s) => \App\Models\VisitVaccine::isGiven($s->source))->map(function ($s) use ($__vacPrice) {
        return ['name' => $s->name, 'dose' => $s->dose_number, 'price' => (float) ($__vacPrice[$s->medicine_id] ?? 0)];
    })->values();
    $__billedTotal = $__billed->sum('price');
    $__pt = \App\Models\Visit::getPatient($visit);
    // ไข้เหลือง: เข็มเดียวรับรองตลอดชีพ (WHO 2016) — เห็นแล้วไม่ต้องเปิดทุก visit หา (คลินิกขอ 2569-10-07)
    $__shots->loadMissing('medicine:id,name');
    $__yf = \App\Models\VisitVaccine::yellowFeverShots((int) $visit->patient_id, $__shots);
    $__summary = \App\Models\VisitVaccine::summaryFor((int) $visit->patient_id, $__shots);
@endphp

<style>
    [data-tabtoggle][href="#tmcVaccine"] { background: #7c3aed !important; color:#fff !important; }
    [data-tabtoggle][href="#tmcVaccine"] font { color:#fff !important; }
    .tmc-hist-old { background: #f8fafc; }
    .yellow-book-box { border: 2px solid #f0ad4e; background: #fffbea; border-radius: .5rem; }
    .tmc-obs-panel { display:grid; grid-template-columns: 1.3fr 1fr 1.2fr; gap:0; border:2px solid #14b8a6; border-radius:.5rem; background:#f0fdfa; overflow:hidden; }
    .tmc-obs-panel .tmc-obs-cell { padding:.6rem .8rem; }
    .tmc-obs-panel .tmc-obs-cell + .tmc-obs-cell { border-left:1px dashed #5eead4; }
    .tmc-obs-panel .tmc-obs-h { font-weight:700; color:#0f766e; margin-bottom:.15rem; }
    .tmc-obs-panel .tmc-obs-leave { font-size:1.35rem; font-weight:800; color:#0f766e; line-height:1.2; margin-bottom:.35rem; font-variant-numeric:tabular-nums; }
    .tmc-obs-panel label { color:#0f766e; }
    .tmc-obs-panel .obs-cell { margin-bottom:0 !important; }
    @media (max-width: 767.98px) { .tmc-obs-panel { grid-template-columns:1fr; } .tmc-obs-panel .tmc-obs-cell + .tmc-obs-cell { border-left:0; border-top:1px dashed #5eead4; } }
</style>

<br>
<div class="d-grid">
    <a class="btn btn-light" data-tabtoggle="1" href="#tmcVaccine" role="button"
       aria-expanded="false" style="width:100%" aria-controls="tmcVaccine">
        <font size="4"><i class="fa-solid fa-syringe me-1"></i> ฉีดวัคซีน (TMC)
            @if($__thisVisit->count())<span class="badge bg-light text-dark ms-1">ครั้งนี้ {{ $__thisVisit->count() }}</span>@endif
            @if($__shots->count())<span class="badge bg-light text-dark ms-1">ประวัติรวม {{ $__shots->count() }}</span>@endif
        </font>
    </a>
</div>
<div class="collapse show" id="tmcVaccine">

    {{-- ไข้เหลือง + สรุปวัคซีนรายคน (2569-10-07) — ป้ายเดียวกับหัวหน้าตรวจ (partials/yellow_fever_badge) --}}
    @include('partials.yellow_fever_badge', ['yfShots' => $__yf])
    @if(! empty($__summary))
        <details class="border rounded mb-2" id="tmc-vac-summary" style="background:#faf5ff;border-color:#c4b5fd !important;">
            <summary class="px-2 py-1 fw-bold" style="color:#6d28d9;cursor:pointer;">
                <i class="fa-solid fa-list-check me-1"></i> สรุปวัคซีนทั้งหมดของคนไข้ (ทุกครั้งที่มา) · {{ count($__summary) }} ชนิด / {{ $__shots->count() }} เข็ม
                <span class="small text-muted fw-normal">— กดเพื่อเปิด/ปิด</span>
            </summary>
            <div class="table-responsive px-2 pb-2">
                <table class="table table-sm align-middle mb-0" style="background:#fff;">
                    <thead><tr><th style="width:35%">วัคซีน</th><th>เข็มที่ได้รับ (วันที่ · Dose · Lot)</th></tr></thead>
                    <tbody>
                        @foreach($__summary as $__vn => $__doses)
                            <tr>
                                <td>{{ $__vn }}
                                    @if(\App\Models\VisitVaccine::isYellowFever($__vn))<span class="badge bg-warning text-dark ms-1">ตลอดชีพ</span>@endif
                                </td>
                                <td class="small">
                                    @foreach($__doses as $__d)
                                        <span class="d-inline-block border rounded px-2 py-0 me-1 mb-1" style="background:#f8fafc;">
                                            {{ $__d['date'] ?? 'ไม่ทราบวันที่' }}
                                            @if($__d['dose']) · Dose {{ $__d['dose'] }}@endif
                                            @if($__d['lot']) · Lot {{ $__d['lot'] }}@endif
                                            @if($__d['source'] === \App\Models\VisitVaccine::HISTORY) <span class="badge bg-secondary">ที่อื่น</span>@endif
                                            @if($__d['source'] === \App\Models\VisitVaccine::TAKE_HOME) <span class="badge" style="background:#0e7490;color:#fff;">ไปทานเอง</span>@endif
                                        </span>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    {{-- สมุดเหลือง: แบบเลือก "ครั้งนี้" (2569-11-04) · ฐานข้อมูลยังไม่ migrate = กล่องแบบเดิม --}}
    @if(\App\Models\Visit::bookReady())
        @include('visit.form._tmc_yellow_book')
    @else
    {{-- ========== สมุดเหลือง (1 คน/เล่ม) — เด่นๆ ========== --}}
    <div class="yellow-book-box p-2 mb-2" id="yb-box"
         data-url="{{ route('patient.yellowBook', $visit->patient_id) }}"
         data-issue-url="{{ route('patient.yellowBook.issue', $visit->patient_id) }}"
         data-token="{{ csrf_token() }}">
        <div class="row g-2 align-items-center">
            <div class="col-md-1 text-center">
                <i class="fa-solid fa-book-medical fa-2x text-warning"></i>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold mb-1" for="yb-no"><i class="fa-solid fa-passport me-1"></i> เลขสมุดเหลือง (เล่มปัจจุบัน) · 1 คน/เล่ม</label>
                <input type="text" class="form-control fw-bold" id="yb-no" style="font-size:1.15rem"
                       value="{{ $__pt->yellow_book_no ?? '' }}" placeholder="เลขเล่มสมุดเหลือง">
            </div>
            <div class="col-md-2 d-grid">
                <button type="button" class="btn btn-outline-warning btn-sm mt-3" id="yb-issue-toggle">
                    <i class="fa-solid fa-repeat"></i> ออกเล่มใหม่
                </button>
            </div>
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1" for="yb-hist">ประวัติสมุดเดิม</label>
                <input type="text" class="form-control form-control-sm" id="yb-hist" readonly
                       value="{{ $__pt->yellow_book_history ?? '' }}" placeholder="ประวัติเล่มเก่า (ระบบบันทึกให้เมื่อออกเล่มใหม่)">
            </div>
        </div>

        {{-- แผงออกเล่มใหม่ (ทดแทนชำรุด/หาย/เสีย) --}}
        <div class="row g-2 mt-1 align-items-end d-none" id="yb-issue-panel">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">เหตุผลออกเล่มใหม่</label>
                <select class="form-select form-select-sm" id="yb-reason">
                    <option value="ชำรุด">ชำรุด</option>
                    <option value="หาย">หาย</option>
                    <option value="เสีย">สมุดเสีย</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">เลขเล่มใหม่</label>
                <input type="text" class="form-control form-control-sm" id="yb-new-no" placeholder="เลขสมุดเล่มใหม่">
            </div>
            <div class="col-md-3 d-grid">
                <button type="button" class="btn btn-warning btn-sm" id="yb-issue-confirm"><i class="fa fa-check"></i> ยืนยันออกเล่มใหม่</button>
            </div>
            <div class="col-md-2 d-grid">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="yb-issue-cancel">ยกเลิก</button>
            </div>
            <div class="col-12"><div class="form-text mb-0">ระบบจะเก็บเลขเล่มเดิมเข้าประวัติอัตโนมัติ แล้วตั้งเลขใหม่เป็นเล่มปัจจุบัน</div></div>
        </div>
        <div class="form-text js-yb-status mb-0"></div>
    </div>
    @endif

    {{-- กล่องสังเกตอาการ ย้ายไปแสดงเหนือ "การให้คำแนะนำ" (คลินิกขอ 2569-11-06) — index @yield('tmc_obs') --}}
    @section('tmc_obs')
    {{-- ===== สังเกตอาการหลังฉีด — นาฬิกาเดียวต่อการมาครั้งนี้ (TMC ขอ 2569-10-27) =====
         เดิมมีเวลา "กลับได้" แยกทุกเข็ม + ปุ่มจับเวลาแยก + ช่องเวลากลับ คนละที่ ฉีดหลายเข็มแล้วงงว่าใช้อันไหน
         ตอนนี้: นับ 30 นาทีจาก "เข็มสุดท้ายของวันนี้" ตัวเดียว → ทุกเข็มครบพร้อมกัน กลับพร้อมกัน
         บันทึกเข็มใหม่ (หน้าโหลดใหม่) = นาฬิกาเลื่อนไปนับจากเข็มนั้นเอง · ยังไม่ได้ลงเข็ม = กดเริ่มจับเวลาเองได้ --}}
    @php
        $__obsName = trim(($__pt->name ?? '').' '.($__pt->surname ?? ''));
        $__obsName = $__obsName !== '' ? $__obsName : ('HN '.($__pt->code ?? ''));
        $__hereNow = $__thisVisit->filter(fn ($s) => (int) $s->source === \App\Models\VisitVaccine::HERE
            && preg_match('/^\d{1,2}:\d{2}/', (string) $s->vaccinated_time));
        $__lastShot = $__hereNow->sortBy(fn ($s) => optional($s->vaccinated_date)->format('Ymd') . sprintf('%05s', $s->vaccinated_time))->last();
        $__lastTime = $__lastShot ? substr(sprintf('%05s', $__lastShot->vaccinated_time), 0, 5) : null;
        $__suggest = null; $__obsEndMs = null;
        if ($__lastTime) {
            [$__h, $__m] = array_map('intval', explode(':', $__lastTime));
            $__mm = $__h * 60 + $__m + 30;
            $__suggest = sprintf('%02d:%02d', intdiv($__mm, 60) % 24, $__mm % 60);
            // นาฬิกานับถอยหลังใช้เฉพาะเข็มที่ฉีด "วันนี้" — เปิด visit เก่าไม่ต้องมีนาฬิกาเด้ง
            if ($__lastShot->vaccinated_date && $__lastShot->vaccinated_date->isToday()) {
                $__obsEndMs = \Carbon\Carbon::today()->setTime($__h, $__m)->addMinutes(30)->getTimestamp() * 1000;
            }
        }
    @endphp
    <div class="tmc-obs-panel mb-2" id="tmc-obs-panel" data-no-dirty
         data-pid="{{ $visit->patient_id }}" data-vid="{{ $visit->id }}" data-name="{{ $__obsName }}" data-end="{{ $__obsEndMs }}">
        <div class="tmc-obs-cell">
            <div class="tmc-obs-h"><i class="fa-solid fa-stopwatch me-1"></i> สังเกตอาการหลังฉีด 30 นาที</div>
            @if($__lastTime)
                <div class="small">เข็มสุดท้ายของครั้งนี้ <b>{{ $__lastTime }} น.</b> · ฉีดครั้งนี้ {{ $__hereNow->count() }} เข็ม</div>
                <div class="small text-muted">นับจากเข็มสุดท้ายเข็มเดียว — ทุกเข็มครบพร้อมกัน กลับพร้อมกัน</div>
            @else
                <div class="small text-muted">ยังไม่ได้บันทึกเข็มที่ฉีดครั้งนี้ — บันทึกการฉีดแล้วระบบเริ่มนับให้เอง หรือกดเริ่มจับเวลาเอง</div>
            @endif
        </div>
        <div class="tmc-obs-cell text-center">
            @if($__suggest)
                <div class="tmc-obs-leave">กลับได้ {{ $__suggest }} น.</div>
            @endif
            @include('partials.obs_button', [
                'obsPatientId' => $visit->patient_id,
                'obsVisitId'   => $visit->id,
                'obsName'      => $__obsName,
                'obsBlock'     => true,
            ])
        </div>
        {{-- เวลากลับจริง (คลินิกขอ 2569-10-09: บางคนกลับก่อนครบ 30 นาที) — บันทึกพร้อมปุ่มบันทึกของหน้าตรวจ --}}
        @if(\App\Models\Visit::leaveTimeReady())
            <div class="tmc-obs-cell">
                <label class="fw-bold mb-1 d-block" for="tmc_leave_time"><i class="fa fa-person-walking-arrow-right me-1"></i> เวลากลับจริง</label>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="time" class="form-control form-control-sm" name="tmc_leave_time" id="tmc_leave_time" style="width:auto;"
                           value="{{ $visit->tmc_leave_time }}">
                    @if($__suggest)
                        <button type="button" class="btn btn-sm btn-outline-success"
                                onclick="var t=document.getElementById('tmc_leave_time'); t.value={{ json_encode($__suggest) }}; t.dispatchEvent(new Event('input',{bubbles:true})); t.dispatchEvent(new Event('change',{bubbles:true}));">
                            ใช้ {{ $__suggest }} น.
                        </button>
                    @endif
                </div>
                <div class="small text-muted mt-1">กลับก่อนครบ ให้ใส่เวลาที่กลับจริง · บันทึกพร้อมปุ่ม "บันทึก"</div>
            </div>
        @endif
    </div>
    <script>
    // ตั้งนาฬิกากลาง (partials/obs_timer) ให้ตรงกับเข็มสุดท้าย — ทับของเดิมเงียบ ๆ ไม่ถาม เพราะเป็นค่าจากข้อมูลจริง
    // ครบเวลาไปแล้วไม่ตั้ง (เปิดดูทีหลังจะได้ไม่มีป้าย "ครบแล้ว" เด้ง)
    // นาฬิกากลางโหลดท้ายหน้า (layout) จึงต้องรอหน้าโหลดเสร็จก่อน
    document.addEventListener('DOMContentLoaded', function () {
        var p = document.getElementById('tmc-obs-panel'), end = +(p && p.dataset.end);
        if (!end || end <= Date.now() || !window.setObsTimerEnd) return;
        var cur = window.obsTimerFor && window.obsTimerFor(p.dataset.pid);
        if (!cur || Math.abs(cur.end - end) > 1000) window.setObsTimerEnd(p.dataset.pid, p.dataset.name, end, p.dataset.vid);
    });
    </script>
    @endsection

    {{-- #8 สมุดเหลืองลอยบนจอ (ถ้าคนนี้มี) — เป็นแท็บหนึ่งในกลุ่มป้ายลอยขวาบน (QUEUE/HISTORY/NOTE/LINE)
         เดิมลอยอยู่ขวาล่าง (bottom 90px) แล้วบังปุ่มแก้ไขท้ายแถวตาราง (คลินิกแจ้ง 2569-09-30)
         ย้ายเข้ากลุ่มด้วย JS เพราะกลุ่มป้ายอยู่ใน edit.blade คนละไฟล์ · หาไม่เจอก็ลอยขวาบนแทน --}}
    @if(!empty($__pt->yellow_book_no))
    <div id="yb-float" class="float-tab" style="position:fixed; right:0; top:88px; z-index:1042; background:#fffbea; color:#000; border:2px solid #f0ad4e; border-right:0; font-weight:bold;">
        <div class="ft-label"><i class="fa-solid fa-book-medical text-warning me-1"></i>สมุดเหลือง</div>
        <div class="ft-value"><span id="yb-float-no">{{ $__pt->yellow_book_no }}</span></div>
    </div>
    <script>
    (function () {
        var yb = document.getElementById('yb-float'), tabs = document.getElementById('float-tabs');
        if (yb && tabs) { yb.style.position = ''; yb.style.top = ''; yb.style.right = ''; yb.style.zIndex = ''; tabs.appendChild(yb); }
    })();
    </script>
    @endif

    {{-- ตัวล็อกวันหยุดของปฏิทิน ต้องมาก่อนสคริปต์ของแท็บนี้ (@once — เรียกซ้ำที่อื่นไม่ทำงานซ้ำ) --}}
    @include('partials.holiday_lock')

    <div class="card card-body" id="tmc-vac-box"
         data-store="{{ route('visit-vaccine.store', $visit->id) }}"
         data-page="{{ $__page }}"
         data-token="{{ csrf_token() }}">

        {{-- วัคซีนที่หมอสั่งในหน้าสั่งยาของ visit นี้ แต่ยังไม่ได้ลงฉีด — กดแล้วเติมวัคซีน/เข็มที่/นัดเข็มถัดไปให้
             ตัวแรกเลือกไว้ให้ตั้งแต่เปิดหน้า (คลินิกขอ 2569-09-30) --}}
        @php
            $__doneIds = $__thisVisit->filter(fn ($s) => \App\Models\VisitVaccine::isGiven($s->source))->pluck('medicine_id')->filter()->all();
            $__ordered = \App\Models\VisitMedicine::where('visit_id', $visit->id)->pluck('medicine_id')
                ->map(fn ($id) => (int) $id)->unique()
                ->filter(fn ($id) => ! in_array($id, $__doneIds) && $__vaccines->contains('id', $id))
                ->values();
            // วันที่หมอสั่ง (สั่งครั้งแรกใน visit นี้) — ใช้เป็น "วันที่ฉีด" ตั้งต้น (คลินิกขอ 2569-09-30)
            $__orderDate = \App\Models\VisitMedicine::where('visit_id', $visit->id)->whereIn('medicine_id', $__ordered->all())
                ->get(['medicine_id', 'created_at'])->groupBy(fn ($r) => (int) $r->medicine_id)
                ->map(fn ($g) => optional($g->min('created_at'), fn ($d) => $d->format('d/m/') . ($d->year + 543)));
        @endphp
        {{-- ยาอื่นที่หมอสั่ง (ไม่ใช่วัคซีน) — รายการยาอยู่ในส่วนแพทย์ พยาบาลไม่ได้เลื่อนไปดู เลยไม่รู้ว่ามียา
             สรุปไว้ตรงที่พยาบาลทำงาน + ปุ่มพาไปที่สั่งยา (2569-10-28) --}}
        @php
            $__otherMeds = \App\Models\VisitMedicine::where('visit_id', $visit->id)->orderBy('id')->get()
                ->filter(fn ($m) => ! $__vaccines->contains('id', (int) $m->medicine_id));
            $__otherNames = \App\Models\Medicine::whereIn('id', $__otherMeds->pluck('medicine_id')->all())->get(['id', 'name', 'unit'])->keyBy('id');
        @endphp
        @if($__otherMeds->isNotEmpty())
            <div class="alert py-2 px-3 mb-2" style="background:#fef9c3; border:2px solid #facc15; color:#422006;">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="fw-bold"><i class="fas fa-prescription-bottle-medical me-1"></i>หมอสั่งยา {{ $__otherMeds->count() }} รายการ (นอกจากวัคซีน)</span>
                    <button type="button" class="btn btn-sm btn-outline-dark py-0 js-goto-med-order">ดูที่สั่งยา</button>
                </div>
                <ul class="mb-0 ps-3 small">
                    @foreach($__otherMeds as $__m)
                        @php $__mn = $__otherNames[(int) $__m->medicine_id] ?? null; @endphp
                        <li><b>{{ $__mn->name ?? '-' }}</b>@if($__m->dose) · {{ $__m->dose }} @endif @if($__m->amount) · {{ $__m->amount }}{{ $__mn && $__mn->unit ? ' ' . $__mn->unit : '' }} @endif</li>
                    @endforeach
                </ul>
            </div>
            <script>
            (function () {
                if (window.__gotoMedOrder) return; window.__gotoMedOrder = true;
                document.addEventListener('click', function (e) {
                    if (!e.target.closest || !e.target.closest('.js-goto-med-order')) return;
                    var sec = document.getElementById('visitTreatment'), t = document.getElementById('tmc-med-order');
                    if (!t) return;
                    // ส่วนแพทย์อาจพับอยู่ — เปิดก่อนแล้วค่อยเลื่อนไป
                    // ไม่มี window.bootstrap ให้เรียก (รวมอยู่ใน app.js) → กดปุ่มหัวหมวดแทน
                    if (sec && !sec.classList.contains('show')) { var tg = document.querySelector('[href="#visitTreatment"]'); if (tg) tg.click(); }
                    // เว้นหัวไว้ ไม่ให้แถบบนสุดของหน้าบังหัวกล่องสั่งยา
                    setTimeout(function () { window.scrollTo({ top: t.getBoundingClientRect().top + window.pageYOffset - 90, behavior: 'smooth' }); }, 350);
                });
            })();
            </script>
        @endif

        @if($__ordered->isNotEmpty())
            <div class="alert py-2 px-3 mb-2 d-flex flex-wrap align-items-center gap-2" style="background:#f3e8ff; border:1px solid #c4b5fd;">
                <span class="small fw-bold" style="color:#6d28d9;"><i class="fa fa-prescription me-1"></i>หมอสั่งวัคซีนแล้ว ยังไม่ได้ลงฉีด:</span>
                @foreach($__ordered as $__oid)
                    @php
                        $__ov = $__vaccines->firstWhere('id', $__oid);
                        // นัดเข็มถัดไปนับจากวันที่หมอสั่ง (= วันที่ฉีด) ไม่ใช่วันที่เปิดหน้านี้
                        $__od = $__orderDate[$__oid] ?? null;
                        $__onx = \App\Services\VaccineSchedule::next((int) $visit->patient_id, $__ov, (int) $visit->id,
                            $__od ? \Carbon\Carbon::parse(\App\Models\VisitVaccine::toDbDate($__od)) : null);
                    @endphp
                    <button type="button" class="btn btn-sm btn-outline-primary js-vac-ordered" data-id="{{ $__oid }}"
                            data-date="{{ $__orderDate[$__oid] ?? '' }}"
                            data-next="{{ $__onx['date'] ? $__onx['date']->format('d/m/') . ($__onx['date']->year + 543) : '' }}" title="หมอสั่งเมื่อ {{ $__orderDate[$__oid] ?? '-' }}">{{ $__ov->name }}</button>
                @endforeach
            </div>
        @endif

        {{-- เพิ่มรายการ — เรียง "ประเภท" ขึ้นก่อน (ฉีดที่นี่ / ประวัติที่อื่น) --}}
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">ประเภท</label>
                <select class="form-select form-select-sm" id="tmc-vac-source">
                    @foreach(\App\Models\VisitVaccine::$sourceOption as $k => $lbl)
                        <option value="{{ $k }}">{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">วัคซีน</label>
                <select class="form-select form-select-sm" id="tmc-vac-pick" data-searchable>
                    <option value="">— เลือกวัคซีน —</option>
                    @foreach($__vaccines as $v)
                        @php $__nx = \App\Services\VaccineSchedule::next((int) $visit->patient_id, $v, (int) $visit->id); @endphp
                        <option value="{{ $v->id }}" data-route="{{ \App\Models\VisitVaccine::normalizeRoute($v->route) }}" data-price="{{ $v->price }}"
                                data-dose="{{ $__nx['dose_now'] }}"
                                data-lots="{{ json_encode($__vacLots[(string) $v->id] ?? [], JSON_UNESCAPED_UNICODE) }}"
                                data-next="{{ $__nx['date'] ? $__nx['date']->format('d/m/') . ($__nx['date']->year + 543) : '' }}">
                            {{ $v->name }}
                        </option>
                    @endforeach
                    {{-- ชื่อกลางสำหรับประวัติที่อื่น (ไม่ผูกคลัง) — เลือกได้เฉพาะประเภท "ประวัติเดิม/ที่อื่น" (2569-11-10) --}}
                    <optgroup label="ฉีดที่อื่น — ชื่อกลาง (ไม่ผูกคลัง ไม่คิดเงิน)" id="tmc-vac-hist-group">
                        {{-- เรียง A–Z ตามชื่ออังกฤษ (หลัง " / ") --}}
                        @php $__hns = \App\Models\VisitVaccine::HISTORY_NAMES; usort($__hns, fn ($x, $y) => strnatcasecmp(substr($x, strpos($x, ' / ') + 3), substr($y, strpos($y, ' / ') + 3))); @endphp
                        @foreach($__hns as $__hn)
                            <option value="h:{{ $__hn }}" class="js-vac-hist-opt" hidden disabled>{{ $__hn }}</option>
                        @endforeach
                    </optgroup>
                </select>
                <div class="form-text js-vac-price d-none"></div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Lot No.</label>
                {{-- ห้องคลังยายังไม่ลงล็อต — พยาบาลพิมพ์เอง แล้วระบบจำไว้ให้ครั้งถัดไป (เลือกวัคซีนแล้วเติมล็อตล่าสุดให้ · พื้นเหลือง = เติมให้) --}}
                <input type="text" class="form-control form-control-sm" id="tmc-vac-lot" placeholder="เช่น X3J012V Exp.01/2030" list="tmc-vac-lot-list" autocomplete="off">
                <datalist id="tmc-vac-lot-list"></datalist>
                <div class="form-text d-none js-vac-lot-hint">ล็อตที่ใช้ครั้งก่อน — เปิดล็อตใหม่ให้แก้</div>
            </div>
            <div class="col-3 col-md-1">
                <label class="form-label small text-muted mb-1">Dose</label>
                <input type="number" class="form-control form-control-sm" id="tmc-vac-dose" min="1" max="20">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">ตำแหน่งฉีด</label>
                <input type="text" class="form-control form-control-sm" id="tmc-vac-site" list="tmc-site-list">
                <datalist id="tmc-site-list">
                    @foreach(\App\Models\VisitVaccine::$siteOption as $s)<option value="{{ $s }}">@endforeach
                </datalist>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Route (วิธีฉีด)</label>
                {{-- เลือกวัคซีนแล้วตั้งค่าให้ตามแคตตาล็อก แก้เองได้ --}}
                <select class="form-select form-select-sm" id="tmc-vac-route">
                    <option value="">— ไม่ระบุ —</option>
                    @foreach(\App\Models\VisitVaccine::$routeOption as $code => $lbl)
                        <option value="{{ $code }}">{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">วันที่ฉีด</label>
                {{-- ไม่ใส่ class "date" เพื่อกันโดน global $('.date').datepicker() ซ้ำ (init เฉพาะ by id ด้านล่าง) --}}
                <input type="text" class="form-control form-control-sm" id="tmc-vac-date"
                       placeholder="วว/ดด/ปปปป" autocomplete="off">
            </div>
            {{-- เวลา: เติมเวลาปัจจุบันให้เอง (2569-10-07) และเดินตามนาฬิกาจนกว่าจะแก้เอง — เปิดหน้าค้างไว้แล้วค่อยฉีด เวลาจะไม่ค้างเป็นตอนเปิดหน้า
                 type=time = แท็บเล็ต/มือถือได้ตัวเลือกเวลาของเครื่อง · ปุ่ม "ตอนนี้" ตั้งกลับเป็นเวลาปัจจุบัน --}}
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1" for="tmc-vac-time">เวลา</label>
                <div class="input-group input-group-sm">
                    <input type="time" class="form-control" id="tmc-vac-time">
                    <button type="button" class="btn btn-outline-secondary" id="tmc-vac-time-now" title="ใช้เวลาตอนนี้">ตอนนี้</button>
                </div>
            </div>
            {{-- ช่อง "นัดเข็มถัดไป" และ "หมายเหตุ" เอาออกจากจอแล้ว (คลินิกขอ 2569-10-01 — หมอวางแผนนัดเองในการ์ดแผนนัด)
                 แต่วันนัดเข็มถัดไปยังคำนวณจากตารางนัดวัคซีนแล้วส่งไปเก็บใน next_due_date เหมือนเดิม
                 เพราะใบนัด/การ์ดนัดวัคซีนยังใช้เป็นค่าสำรอง — เลยเหลือเป็นช่องซ่อน (ไม่มี name = ไม่ไปกับฟอร์มหลัก) --}}
            <input type="hidden" id="tmc-vac-next" value="">
            <div class="col-md-2 d-grid align-self-end">
                {{-- type=button สำคัญมาก ถ้าเป็น submit จะไปยิงฟอร์มหลักแทน
                     เดิมชื่อ "+ เพิ่มการฉีด" พยาบาลไม่รู้ว่าเป็นปุ่มบันทึก ไปกด Save ท้ายหน้าแทนแล้วการฉีดไม่ถูกบันทึก (2569-10-07) --}}
                <button type="button" class="btn btn-success btn-save-th" id="tmc-vac-add">
                    <i class="fa fa-save me-1"></i> บันทึกการฉีด
                </button>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-1">
            <div class="form-text mb-0">
                กรอกแล้วกด <b>"บันทึกการฉีด"</b> = บันทึกเข็มนี้ทันที (ไม่ต้องกด Save ท้ายหน้า) ·
                เลือก "ประวัติเดิม/ที่อื่น" สำหรับเข็มที่คนไข้เคยฉีดมาก่อน — จะไม่ผูกกับการมาครั้งนี้
            </div>
            @if($__shots->count())
                <a href="{{ route('visit-vaccine.certificate', ['visit' => $visit->id, 'page' => $__page]) }}"
                   target="_blank" class="btn btn-success btn-sm">
                    <i class="fa fa-print"></i> ใบรับรองวัคซีน (EN)
                </a>
            @endif
        </div>

        {{-- กล่อง "คำนวณราคาวัคซีน" ย้ายไปใต้ "การให้คำแนะนำ" (TMC ขอ 2569-10-07) — ประกาศเป็น section ไว้ตรงนี้
             เพราะใช้ตัวแปรของไฟล์นี้ ($__vaccines / $__billed) · visit/form/index เรียก @yield('tmc_calc') ตรงตำแหน่งใหม่ --}}
@section('tmc_calc')
        {{-- ===== คำนวณราคาวัคซีน (TMC ขอ 2569-09-17) =====
             ไว้บอกราคาคนไข้ก่อนตัดสินใจฉีด — เป็นเครื่องคิดเลขล้วน ไม่บันทึก ไม่เข้าบิล
             ยอดที่เข้าบิลจริงคือบรรทัด "ฉีดครั้งนี้" ด้านล่าง ซึ่งคิดจากราคาแคตตาล็อกแบบเดียวกัน --}}
        <div class="border rounded p-2 mt-3" id="tmc-calc" style="background:#f5f3ff;border-color:#c4b5fd !important;">
            {{-- พยาบาลกด "ใส่รายการ" แล้วกด Save นึกว่าบันทึก แล้วหาไม่เจอ (2569-10-07) — บอกให้ชัดว่าเป็นเครื่องคิดเลขล้วน --}}
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="fw-bold" style="color:#6d28d9;"><i class="fa fa-calculator me-1"></i> คำนวณราคาวัคซีน</span>
                <span class="badge" style="background:#fff;color:#b91c1c;border:1px solid #fca5a5;">เครื่องคิดเลข · ไม่บันทึก · ไม่เข้าบิล</span>
            </div>
            <div class="small mb-2" style="color:#7f1d1d;">
                ใช้แจ้งราคาคนไข้ก่อนตัดสินใจเท่านั้น — รายการนี้<b>หายเมื่อกด Save / รีเฟรชหน้า</b> ·
                จะบันทึกว่าฉีดแล้ว ให้ใช้ปุ่ม <b>"บันทึกการฉีด"</b> ด้านบน
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <select class="form-select form-select-sm" id="tmc-calc-pick" data-searchable>
                        <option value="">— เลือกวัคซีน —</option>
                        @foreach($__vaccines as $v)
                            <option value="{{ $v->id }}" data-price="{{ (float) $v->price }}" data-name="{{ $v->name }}">
                                {{ $v->name }} — {{ (float) $v->price > 0 ? number_format((float) $v->price) . ' บาท' : 'ยังไม่ตั้งราคา' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-4 col-md-2">
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control" id="tmc-calc-qty" value="1" min="1" max="10">
                        <span class="input-group-text">เข็ม</span>
                    </div>
                </div>
                <div class="col-8 col-md-4 d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1" id="tmc-calc-add"><i class="fa fa-plus"></i> ใส่รายการคิดราคา</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="tmc-calc-clear">ล้าง</button>
                    <button type="button" class="btn btn-sm btn-outline-dark" id="tmc-calc-print" title="พิมพ์ใบแจ้งราคา (ไม่ใช่ใบเสร็จ)"><i class="fa fa-print"></i></button>
                </div>
            </div>
            <div class="small text-muted mt-2" id="tmc-calc-empty">ยังไม่มีรายการคิดราคา</div>
            <div class="table-responsive">
            <table class="table table-sm mb-0 mt-2 d-none" id="tmc-calc-table" style="background:#fff;">
                <thead><tr><th>วัคซีน</th><th class="text-end" style="width:8rem">ราคา/เข็ม <span class="text-muted fw-normal">(แก้ได้)</span></th><th class="text-center" style="width:5rem">เข็ม</th><th class="text-end" style="width:7rem">รวม</th><th style="width:2rem"></th></tr></thead>
                <tbody></tbody>
                <tfoot><tr class="fw-bold" style="background:#ede9fe;"><td colspan="3" class="text-end">รวมทั้งหมด <span class="small fw-normal text-muted">(ราคาประมาณการ ไม่บันทึก)</span></td><td class="text-end text-nowrap" id="tmc-calc-total" style="font-size:1.35rem;color:#6d28d9;">0</td><td></td></tr></tfoot>
            </table>
            </div>
            <div class="small text-danger mt-1 d-none" id="tmc-calc-noprice">⚠ มีวัคซีนที่ยังไม่ตั้งราคาในแคตตาล็อก — กรอกราคาในช่องสีแดงเพื่อให้ยอดรวมครบ</div>

            @if($__billed->count())
                <div class="small mt-2 pt-2 border-top">
                    <b>ฉีดครั้งนี้ (เข้าบิลแล้ว):</b>
                    @foreach($__billed as $b)
                        <span class="d-inline-block me-2">{{ $b['name'] }}@if($b['dose']) เข็ม {{ $b['dose'] }}@endif
                            = {{ $b['price'] > 0 ? number_format($b['price']) : 'ยังไม่ตั้งราคา' }}</span>
                    @endforeach
                    · <b>รวม {{ number_format($__billedTotal) }} บาท</b>
                </div>
            @endif
        </div>
@endsection

        <hr>

        {{-- ประวัติ --}}
        @if($__shots->isEmpty())
            <div class="text-muted small">ยังไม่มีประวัติวัคซีนของคนไข้รายนี้</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:6rem">วันที่ฉีด</th>
                            <th>วัคซีน</th>
                            <th style="width:4rem">Dose</th>
                            <th style="width:9rem">Lot</th>
                            <th style="width:8rem">ตำแหน่ง</th>
                            <th style="width:4rem">Route</th>
                            <th style="width:6rem">ที่มา</th>
                            <th style="width:3rem"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($__shots as $s)
                            <tr class="{{ $s->visit_id === $visit->id ? '' : 'tmc-hist-old' }}">
                                <td class="small">{{ $s->vaccinated_date_th ?? '—' }}@if($s->vaccinated_time)<br><span class="text-muted">{{ $s->vaccinated_time }} น.</span>@endif</td>
                                <td>
                                    {{ $s->name }}
                                    @if($s->visit_id === $visit->id)
                                        <span class="badge bg-primary ms-1">ครั้งนี้</span>
                                    @endif
                                    @if($s->note)<div class="form-text">{{ $s->note }}</div>@endif
                                </td>
                                <td class="text-center">{{ $s->dose_number ?? '—' }}</td>
                                <td class="small">{{ $s->lot_number ?? '—' }}</td>
                                <td class="small">{{ $s->site ?? '—' }}</td>
                                <td class="small" title="{{ \App\Models\VisitVaccine::$routeOption[$s->route] ?? '' }}">{{ $s->route ?: '—' }}</td>
                                <td class="small">
                                    @if($s->source == \App\Models\VisitVaccine::HISTORY)
                                        <span class="badge bg-secondary">ประวัติเดิม</span>
                                    @elseif($s->source == \App\Models\VisitVaccine::TAKE_HOME)
                                        <span class="badge" style="background:#0e7490;color:#fff;" title="คลินิกจ่ายให้ ไปทานเองที่บ้าน">ไปทานเอง</span>
                                    @else
                                        <span class="badge bg-success">ฉีดที่นี่</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    {{-- แก้ไขเข็มที่บันทึกแล้ว (TMC ขอ 2569-10-16) — เปิดแถวแก้ใต้แถวนี้ ไม่ใช่ฟอร์ม (อยู่ในฟอร์มหลัก) --}}
                                    <button type="button" class="btn btn-primary btn-sm js-vac-edit" title="แก้ไข" data-id="{{ $s->id }}">
                                        <i class="fa fa-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm js-row-delete" title="ลบ"
                                            data-action="{{ route('visit-vaccine.destroy', ['visit' => $visit->id, 'id' => $s->id, 'page' => $__page]) }}">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            {{-- แถวแก้ไข: ช่องใช้ data-field ไม่มี name — ไม่ไปกับปุ่ม Save ของหน้าตรวจ · บันทึกด้วย fetch (สคริปต์ท้ายไฟล์) --}}
                            <tr class="d-none js-vac-edit-row" data-id="{{ $s->id }}" data-no-dirty
                                data-url="{{ route('visit-vaccine.update', ['visit' => $visit->id, 'id' => $s->id]) }}" style="background:#eff6ff;">
                                <td colspan="8">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small mb-0">วันที่ฉีด</label>
                                            <input type="text" class="form-control form-control-sm" data-field="vaccinated_date" value="{{ $s->vaccinated_date_th }}" placeholder="วว/ดด/ปปปป">
                                        </div>
                                        <div class="col-6 col-md-1">
                                            <label class="form-label small mb-0">เวลา</label>
                                            <input type="text" class="form-control form-control-sm" data-field="vaccinated_time" value="{{ $s->vaccinated_time }}" placeholder="10:15">
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-0">Lot No.</label>
                                            <input type="text" class="form-control form-control-sm" data-field="lot_number" value="{{ $s->lot_number }}" list="tmc-vac-lot-edit-{{ $s->id }}" autocomplete="off">
                                            <datalist id="tmc-vac-lot-edit-{{ $s->id }}">
                                                @foreach(($__vacLots[(string) $s->medicine_id] ?? []) as $__l)<option value="{{ $__l }}">@endforeach
                                            </datalist>
                                        </div>
                                        <div class="col-3 col-md-1">
                                            <label class="form-label small mb-0">Dose</label>
                                            <input type="number" class="form-control form-control-sm" data-field="dose_number" value="{{ $s->dose_number }}" min="1" max="20">
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small mb-0">ตำแหน่ง</label>
                                            <input type="text" class="form-control form-control-sm" data-field="site" value="{{ $s->site }}" list="tmc-site-list">
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-0">Route</label>
                                            <select class="form-select form-select-sm" data-field="route">
                                                <option value="">— ไม่ระบุ —</option>
                                                @foreach(\App\Models\VisitVaccine::$routeOption as $__c => $__lbl)
                                                    <option value="{{ $__c }}" {{ $s->route === $__c ? 'selected' : '' }}>{{ $__lbl }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small mb-0">นัดเข็มถัดไป</label>
                                            <input type="text" class="form-control form-control-sm" data-field="next_due_date" value="{{ $s->next_due_date_th }}" placeholder="วว/ดด/ปปปป">
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-0">ผู้ผลิต</label>
                                            <input type="text" class="form-control form-control-sm" data-field="manufacturer" value="{{ $s->manufacturer }}">
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label small mb-0">หมายเหตุ</label>
                                            <input type="text" class="form-control form-control-sm" data-field="note" value="{{ $s->note }}">
                                        </div>
                                        <div class="col-12 col-md-3 d-flex gap-2">
                                            <button type="button" class="btn btn-primary btn-sm js-vac-edit-save"><i class="fa fa-save me-1"></i> บันทึกการแก้ไข</button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm js-vac-edit-cancel">ยกเลิก</button>
                                        </div>
                                        <div class="col-12 small js-vac-edit-msg"></div>
                                        <div class="col-12 small text-muted">เปลี่ยนชื่อวัคซีน / ที่มา ไม่ได้ (ผูกกับสต๊อกและบิล) — ถ้าผิดให้ลบแล้วบันทึกใหม่</div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>
</div>

{{-- ช่องพิมพ์ค้นหาวัคซีน (select[data-searchable]) — พิมพ์ "yel" เจอไข้เหลือง --}}
@include('partials.searchable_select')
<script>window.initSearchableSelects && window.initSearchableSelects(document.getElementById('tmcVaccine'));</script>

<script>
// รอทั้งหน้าโหลดก่อน — กล่องคำนวณราคา (#tmc-calc) ย้ายไปอยู่ใต้ "การให้คำแนะนำ" ซึ่งอยู่หลังสคริปต์นี้ในหน้า (2569-10-07)
(function (run) { if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run(); })(function () {
    if (window.__tmcVacInit) return;
    window.__tmcVacInit = true;

    // ---- สมุดเหลือง: autosave ระดับคนไข้ ----
    var ybBox = document.getElementById('yb-box');
    if (ybBox) {
        var saveYb = function () {
            var st = ybBox.querySelector('.js-yb-status');
            var fd = new FormData();
            fd.append('yellow_book_no', document.getElementById('yb-no').value);
            fd.append('yellow_book_history', document.getElementById('yb-hist').value);
            fd.append('_token', ybBox.dataset.token);
            fetch(ybBox.dataset.url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (j) { if (st) { st.textContent = j.ok ? '✓ บันทึกสมุดเหลืองแล้ว' : 'บันทึกไม่สำเร็จ'; st.className = 'form-text mb-0 ' + (j.ok ? 'text-success' : 'text-danger'); } })
                .catch(function () { if (st) { st.textContent = 'บันทึกไม่สำเร็จ'; st.className = 'form-text mb-0 text-danger'; } });
        };
        var ybNo = document.getElementById('yb-no');
        if (ybNo) ybNo.addEventListener('change', saveYb);

        // ---- ออกเล่มใหม่ (ทดแทนชำรุด/หาย/เสีย) ----
        var panel  = document.getElementById('yb-issue-panel');
        var tgl    = document.getElementById('yb-issue-toggle');
        var cancel = document.getElementById('yb-issue-cancel');
        var okBtn  = document.getElementById('yb-issue-confirm');
        if (tgl && panel)    tgl.addEventListener('click', function () { panel.classList.toggle('d-none'); });
        if (cancel && panel) cancel.addEventListener('click', function () { panel.classList.add('d-none'); });
        if (okBtn) {
            okBtn.addEventListener('click', function () {
                var newNo = (document.getElementById('yb-new-no').value || '').trim();
                if (!newNo) { alert('กรุณากรอกเลขสมุดเล่มใหม่'); return; }
                if (!confirm('ยืนยันออกสมุดเล่มใหม่? ระบบจะเก็บเล่มเดิมเข้าประวัติ')) return;
                var st = ybBox.querySelector('.js-yb-status');
                var fd = new FormData();
                fd.append('reason', document.getElementById('yb-reason').value);
                fd.append('new_no', newNo);
                fd.append('_token', ybBox.dataset.token);
                okBtn.disabled = true;
                fetch(ybBox.dataset.issueUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        okBtn.disabled = false;
                        if (j.ok) {
                            document.getElementById('yb-no').value   = j.no || '';
                            document.getElementById('yb-hist').value = j.history || '';
                            document.getElementById('yb-new-no').value = '';
                            panel.classList.add('d-none');
                            var fl = document.getElementById('yb-float-no'); if (fl) fl.textContent = j.no || '';
                            if (st) { st.textContent = '✓ ออกสมุดเล่มใหม่แล้ว'; st.className = 'form-text mb-0 text-success'; }
                        } else if (st) { st.textContent = 'ออกเล่มใหม่ไม่สำเร็จ'; st.className = 'form-text mb-0 text-danger'; }
                    })
                    .catch(function () { okBtn.disabled = false; if (st) { st.textContent = 'ออกเล่มใหม่ไม่สำเร็จ'; st.className = 'form-text mb-0 text-danger'; } });
            });
        }
    }

    // ปุ่มจับเวลาสังเกตอาการใช้ handler กลางใน partials/obs_timer แล้ว ไม่ต้องผูกซ้ำที่นี่
    // (ถ้าผูกซ้ำ กดครั้งเดียวจะเริ่มนาฬิกา 2 รอบ)

    var box = document.getElementById('tmc-vac-box');
    if (!box) return;

    // วันที่ฉีด: เลือกได้ทุกวัน (ประวัติที่ฉีดที่อื่นอาจเป็นวันหยุด)
    // ผูกผ่าน bindThDatepicker (index.blade.php) — รอไฟล์ภาษา th-th ก่อน ผูกเร็วไปจะได้ mm/dd/ค.ศ.
    // ช่องนัดเข็มถัดไปเป็นช่องซ่อนแล้ว (2569-10-01) ไม่ต้องมีปฏิทิน
    var __vacOpts = { format: 'dd/mm/yyyy', language: 'th-th', thaiyear: true, autoclose: true, todayHighlight: true };
    if (window.bindThDatepicker) {
        window.bindThDatepicker('#tmc-vac-date', __vacOpts);
    } else if (window.jQuery && jQuery.fn.datepicker && (jQuery.fn.datepicker.dates || {})['th-th']) {
        jQuery('#tmc-vac-date').datepicker(__vacOpts);
    }

    // ---- เวลาฉีด: เติมเวลาปัจจุบัน + เดินตามนาฬิกาจนกว่าพยาบาลจะแก้เอง (2569-10-07) ----
    // ประวัติจากที่อื่นไม่เติม (ไม่รู้เวลาจริง) · กดบันทึกโดยไม่ได้แก้เวลา = ใช้เวลาตอนกดบันทึก
    var vTime = document.getElementById('tmc-vac-time'), vTimeEdited = false;
    function nowHM() { var d = new Date(); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
    function isHistory() { return document.getElementById('tmc-vac-source').value === '2'; }
    function autoTime() { if (!vTimeEdited && !isHistory()) { vTime.value = nowHM(); } }
    vTime.addEventListener('input', function () { vTimeEdited = true; });
    vTime.addEventListener('change', function () { vTimeEdited = true; });
    document.getElementById('tmc-vac-time-now').addEventListener('click', function () {
        vTimeEdited = false;
        vTime.value = nowHM();
    });
    document.getElementById('tmc-vac-source').addEventListener('change', function () {
        if (isHistory()) { if (!vTimeEdited) vTime.value = ''; } else { autoTime(); }
    });
    // ชื่อกลางของวัคซีนที่ฉีดที่อื่น โชว์เฉพาะตอนเลือก "ประวัติเดิม/ที่อื่น" · เปลี่ยนกลับเป็นฉีดที่นี่ = ล้างตัวที่เลือกไว้
    function syncHistOpts() {
        var h = isHistory(), pick = document.getElementById('tmc-vac-pick');
        pick.querySelectorAll('.js-vac-hist-opt').forEach(function (o) { o.hidden = !h; o.disabled = !h; });
        document.getElementById('tmc-vac-hist-group').hidden = !h;
        if (!h && pick.value.indexOf('h:') === 0) { pick.value = ''; pick.dispatchEvent(new Event('change')); }
    }
    document.getElementById('tmc-vac-source').addEventListener('change', syncHistOpts);
    syncHistOpts();
    // เวลา "กลับได้" รายเข็มเอาออกแล้ว — ใช้บล็อกสังเกตอาการตัวเดียวด้านบน นับจากเข็มสุดท้าย (2569-10-27)
    // ฉีดหลายเข็มรอบเดียวกัน (คลินิกขอ 2569-11-06): เวลาที่แก้เองตอนบันทึกเข็มแรก ใช้ต่อให้เข็มถัดไปหลังหน้าโหลดใหม่
    // จำไว้ 2 ชม. ต่อ visit — กด "ตอนนี้" = กลับไปเดินตามนาฬิกา
    var TKEY = 'tmcVacTime:{{ $visit->id }}';
    try {
        var keep = JSON.parse(sessionStorage.getItem(TKEY) || 'null');
        if (keep && keep.t && Date.now() - keep.at < 7200000) { vTime.value = keep.t; vTimeEdited = true; }
    } catch (e) {}
    window.__tmcVacKeepTime = function () {
        try {
            if (vTimeEdited && vTime.value) { sessionStorage.setItem(TKEY, JSON.stringify({ t: vTime.value, at: Date.now() })); }
            else { sessionStorage.removeItem(TKEY); }
        } catch (e) {}
    };
    document.getElementById('tmc-vac-time-now').addEventListener('click', function () { try { sessionStorage.removeItem(TKEY); } catch (e) {} });
    autoTime();
    setInterval(autoTime, 30000);

    // เลือกวัคซีนที่หมอสั่งไว้ (ปุ่มด้านบน) — ตั้งเป็น "ฉีดที่คลินิกนี้" แล้วให้ตัวเลือกวัคซีนเติมเข็มที่/นัดถัดไปเอง
    function pickOrdered(id, orderDate, nextDate) {
        var src = document.getElementById('tmc-vac-source'), pick = document.getElementById('tmc-vac-pick');
        // วันที่ฉีด = วันที่หมอสั่งวัคซีนตัวนี้ (แก้ได้)
        if (orderDate) {
            var dt = document.getElementById('tmc-vac-date');
            dt.value = orderDate;
            if (window.jQuery && jQuery.fn.datepicker) { try { jQuery(dt).datepicker('update', orderDate); } catch (e) {} }
        }
        src.value = '1';
        src.dispatchEvent(new Event('change'));
        pick.value = id;
        pick.dispatchEvent(new Event('change'));
        // ตัวเลือกวัคซีนเติมนัดถัดไปนับจากวันนี้ — ของที่หมอสั่งไว้ให้นับจากวันที่สั่งแทน
        var nx = document.getElementById('tmc-vac-next');
        if (orderDate && nx) { nx.value = nextDate || ''; }
        box.querySelectorAll('.js-vac-ordered').forEach(function (b) {
            b.classList.toggle('btn-primary', b.dataset.id === String(id));
            b.classList.toggle('btn-outline-primary', b.dataset.id !== String(id));
        });
    }
    // เปลี่ยนเป็น "ฉีดที่อื่น (ประวัติ)" — ล้างเข็มที่/วันที่ฉีด/นัดถัดไปที่เติมมาจากวัคซีนที่หมอสั่ง
    // ไม่งั้นค่าของเข็มวันนี้ติดไปอยู่ในแถวประวัติ (รีวิว 2569-09-30)
    document.getElementById('tmc-vac-source').addEventListener('change', function () {
        if (this.value !== '2') return;
        ['tmc-vac-dose', 'tmc-vac-date', 'tmc-vac-next'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.value = '';
            // ช่องซ่อน (นัดเข็มถัดไป) ไม่มีปฏิทิน — เรียก datepicker กับมันไม่ได้
            if (id === 'tmc-vac-date' && window.jQuery && jQuery.fn.datepicker) { try { jQuery(el).datepicker('update', ''); } catch (e) {} }
        });
        box.querySelectorAll('.js-vac-ordered').forEach(function (b) {
            b.classList.remove('btn-primary'); b.classList.add('btn-outline-primary');
        });
    });
    box.addEventListener('click', function (e) {
        var b = e.target.closest('.js-vac-ordered');
        if (b) { pickOrdered(b.dataset.id, b.dataset.date, b.dataset.next); }
    });

    // พิมพ์ Lot เอง = ไม่ใช่ค่าที่ระบบเติมแล้ว (เลือกวัคซีนตัวใหม่จะไม่ทับ)
    // ---- แก้ไขเข็มที่บันทึกแล้ว (TMC 2569-10-16) — เปิด/ปิดแถวแก้ · บันทึกด้วย fetch แล้วโหลดหน้าใหม่ ----
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('.js-vac-edit, .js-vac-edit-cancel, .js-vac-edit-save');
        if (!b) return;
        if (b.classList.contains('js-vac-edit')) {
            var r = document.querySelector('.js-vac-edit-row[data-id="' + b.dataset.id + '"]');
            if (r) { r.classList.toggle('d-none'); if (!r.classList.contains('d-none')) { var f = r.querySelector('[data-field="lot_number"]'); if (f) f.focus(); } }
            return;
        }
        var row = b.closest('.js-vac-edit-row');
        if (b.classList.contains('js-vac-edit-cancel')) { row.classList.add('d-none'); return; }
        var fd = new FormData(), msg = row.querySelector('.js-vac-edit-msg');
        row.querySelectorAll('[data-field]').forEach(function (el) { fd.append(el.dataset.field, el.value); });
        b.disabled = true; msg.className = 'col-12 small js-vac-edit-msg text-muted'; msg.textContent = 'กำลังบันทึก…';
        fetch(row.dataset.url, { method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }).catch(function () { return { ok: false, j: {} }; }); })
            .then(function (res) {
                if (!res.ok) {
                    var m = res.j.message || 'บันทึกไม่สำเร็จ';
                    if (res.j.errors) { var k = Object.keys(res.j.errors)[0]; if (k) m = res.j.errors[k][0]; }
                    msg.className = 'col-12 small js-vac-edit-msg text-danger'; msg.textContent = m; b.disabled = false; return;
                }
                msg.className = 'col-12 small js-vac-edit-msg text-success'; msg.textContent = res.j.message + ' — กำลังโหลดใหม่';
                if (window.__visitDraftStash) { window.__visitDraftStash(); window.__visitDraftStashed = true; }
                location.hash = 'tmcVaccine'; location.reload();
            })
            .catch(function () { msg.className = 'col-12 small js-vac-edit-msg text-danger'; msg.textContent = 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้'; b.disabled = false; });
    });
    // Enter ในแถวแก้ไม่ไปกด Save ฟอร์มหลัก
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.closest && e.target.closest('.js-vac-edit-row')) e.preventDefault();
    });

    document.getElementById('tmc-vac-lot').addEventListener('input', function () {
        this.dataset.auto = ''; this.style.background = '';
        var h = box.querySelector('.js-vac-lot-hint'); if (h) h.classList.add('d-none');
    });
    document.getElementById('tmc-vac-pick').addEventListener('change', function () {
        var opt  = this.options[this.selectedIndex];
        var hint = box.querySelector('.js-vac-price');
        var p    = opt ? opt.dataset.price : '';
        if (p && parseFloat(p) > 0) {
            hint.textContent = 'ราคาอ้างอิง ' + parseFloat(p).toLocaleString() + ' บาท';
            hint.classList.remove('d-none');
        } else {
            hint.classList.add('d-none');
        }
        // ตั้ง Route ตามค่าตั้งต้นของวัคซีน · ตัวที่ไม่มีค่าตั้งต้นให้กลับเป็น "ไม่ระบุ"
        // (ถ้าคงค่าเดิมไว้ เปลี่ยนจากวัคซีน IM ไปตัวอื่น Route จะติด IM ของตัวก่อนมาโดยไม่รู้ตัว)
        document.getElementById('tmc-vac-route').value = opt ? (opt.dataset.route || '') : '';
        // Lot ที่เคยพิมพ์ของวัคซีนนี้: รายการให้เลือก + เติมตัวล่าสุด (เฉพาะฉีดที่นี่ และช่องยังว่าง/เป็นค่าที่ระบบเติมไว้)
        (function () {
            var lotEl = document.getElementById('tmc-vac-lot'), dl = document.getElementById('tmc-vac-lot-list');
            var lotHint = box.querySelector('.js-vac-lot-hint'), lots = [];
            try { lots = JSON.parse((opt && opt.dataset.lots) || '[]'); } catch (e) { lots = []; }
            dl.innerHTML = lots.map(function (l) { var o = document.createElement('option'); o.value = l; return o.outerHTML; }).join('');
            var auto = lotEl.dataset.auto === '1' || lotEl.value.trim() === '';
            if (auto) {
                var fill = lots.length && document.getElementById('tmc-vac-source').value !== '2' ? lots[0] : '';
                lotEl.value = fill;
                lotEl.dataset.auto = fill ? '1' : '';
                lotEl.style.background = fill ? '#fff3cd' : '';
                lotHint.classList.toggle('d-none', !fill);
            }
        })();
        // เข็มที่ + นัดเข็มถัดไป จากตารางนัดวัคซีน (นับจากประวัติฉีด เลี่ยงวันหยุด) — เติมเฉพาะฉีดที่นี่ แก้เองได้
        if (opt && document.getElementById('tmc-vac-source').value !== '2') {
            document.getElementById('tmc-vac-dose').value = opt.dataset.dose || '';
            var nx = document.getElementById('tmc-vac-next');
            if (nx) { nx.value = opt.dataset.next || ''; }
        }
    });

    // ---- เครื่องคิดเลขราคาวัคซีน (ไม่บันทึก ไม่เข้าบิล) ----
    // เลือกวัคซีนในช่อง "วัคซีน" ด้านบน -> ใส่ให้เองพร้อมราคา รวมยอดให้เลย (TMC ขอ 2569-09-18)
    // ตัวที่แคตตาล็อกยังไม่ตั้งราคา ให้พยาบาลพิมพ์ราคาในช่องได้เอง แล้วยอดรวมขยับตาม
    var calcRows = [];
    var cPick = document.getElementById('tmc-calc-pick');
    var cQty  = document.getElementById('tmc-calc-qty');
    var cTbl  = document.getElementById('tmc-calc-table');
    function money(n) { return n.toLocaleString('th-TH', { maximumFractionDigits: 2 }); }
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function calcTotals() {
        var total = 0, noPrice = false;
        calcRows.forEach(function (r, i) {
            var line = (r.price || 0) * r.qty;
            total += line;
            if (!(r.price > 0)) noPrice = true;
            var cell = cTbl.querySelector('tbody tr[data-i="' + i + '"] .js-calc-line');
            if (cell) cell.textContent = money(line);
        });
        document.getElementById('tmc-calc-total').textContent = money(total) + ' บาท';
        document.getElementById('tmc-calc-noprice').classList.toggle('d-none', !noPrice);
    }

    function renderCalc() {
        var body = cTbl.querySelector('tbody');
        body.innerHTML = '';
        calcRows.forEach(function (r, i) {
            var tr = document.createElement('tr');
            tr.dataset.i = i;
            tr.innerHTML = '<td>' + esc(r.name) + '</td>'
                + '<td><input type="number" min="0" step="1" class="form-control form-control-sm text-end js-calc-price"'
                    + ' value="' + (r.price > 0 ? r.price : '') + '" placeholder="กรอกราคา"'
                    + (r.price > 0 ? '' : ' style="border-color:#dc3545;background:#fff5f5;"') + '></td>'
                + '<td><input type="number" min="1" max="10" class="form-control form-control-sm text-center js-calc-qty" value="' + r.qty + '"></td>'
                + '<td class="text-end js-calc-line">0</td>'
                + '<td><button type="button" class="btn btn-sm btn-link text-danger p-0 js-calc-del" title="เอาออก">&times;</button></td>';
            body.appendChild(tr);
        });
        cTbl.classList.toggle('d-none', calcRows.length === 0);
        document.getElementById('tmc-calc-empty').classList.toggle('d-none', calcRows.length > 0);
        calcTotals();
    }

    // ใบแจ้งราคา (ไม่ใช่ใบเสร็จ) — เปิดหน้าต่างใหม่จากรายการในเครื่องคิดเลข ไม่ยิงอะไรไปเซิร์ฟเวอร์
    document.getElementById('tmc-calc-print').addEventListener('click', function () {
        if (!calcRows.length) { alert('ยังไม่มีรายการคิดราคา'); return; }
        var total = 0, rows = calcRows.map(function (r) {
            var line = (r.price || 0) * r.qty; total += line;
            return '<tr><td>' + esc(r.name) + '</td><td class="r">' + (r.price > 0 ? money(r.price) : '-') + '</td><td class="c">' + r.qty + '</td><td class="r">' + money(line) + '</td></tr>';
        }).join('');
        var now = new Date();
        var w = window.open('', '_blank');
        if (!w) { alert('เบราว์เซอร์บล็อกหน้าต่างใหม่ — อนุญาต pop-up แล้วลองอีกครั้ง'); return; }
        w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>ใบแจ้งราคาวัคซีน</title>'
            + '<style>body{font-family:sans-serif;margin:24px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #999;padding:6px}'
            + '.r{text-align:right}.c{text-align:center}tfoot td{font-weight:bold;font-size:1.15em}.note{color:#555;font-size:.9em;margin-top:12px}</style></head><body>'
            + '<h3 style="margin:0 0 4px">ใบแจ้งราคาวัคซีน (ประมาณการ)</h3>'
            + '<div>' + esc(@json(trim(($__pt->name ?? '') . ' ' . ($__pt->surname ?? '')))) + ' · HN ' + esc(@json((string) ($__pt->code ?? ''))) + '</div>'
            + '<div style="margin-bottom:10px">วันที่ ' + ('0' + now.getDate()).slice(-2) + '/' + ('0' + (now.getMonth() + 1)).slice(-2) + '/' + (now.getFullYear() + 543) + '</div>'
            + '<table><thead><tr><th>วัคซีน</th><th class="r">ราคา/เข็ม</th><th class="c">เข็ม</th><th class="r">รวม (บาท)</th></tr></thead><tbody>' + rows + '</tbody>'
            + '<tfoot><tr><td colspan="3" class="r">รวมทั้งหมด</td><td class="r">' + money(total) + '</td></tr></tfoot></table>'
            + '<div class="note">เอกสารนี้เป็นการแจ้งราคาเบื้องต้น ไม่ใช่ใบเสร็จรับเงิน ราคาอาจเปลี่ยนแปลงตามรายการที่ฉีดจริง</div>'
            + '</body></html>');
        w.document.close();
        w.focus();
        w.print();
    });

    // ใส่รายการ (ตัวเดิมซ้ำ = เพิ่มจำนวนเข็ม ไม่แตกแถว)
    function calcPut(id, name, price, qty) {
        if (!id) return;
        var same = calcRows.filter(function (r) { return r.id === String(id); })[0];
        if (same) { same.qty = Math.min(10, same.qty + qty); }
        else { calcRows.push({ id: String(id), name: name, price: parseFloat(price) || 0, qty: qty }); }
        renderCalc();
    }

    document.getElementById('tmc-calc-add').addEventListener('click', function () {
        if (!cPick.value) { cPick.focus(); return; }
        var opt = cPick.options[cPick.selectedIndex];
        calcPut(cPick.value, opt.dataset.name, opt.dataset.price, Math.max(1, Math.min(10, parseInt(cQty.value, 10) || 1)));
        cPick.value = ''; cQty.value = 1;
    });
    document.getElementById('tmc-calc-clear').addEventListener('click', function () { calcRows = []; renderCalc(); });

    cTbl.addEventListener('click', function (e) {
        var b = e.target.closest('.js-calc-del');
        if (!b) return;
        calcRows.splice(parseInt(b.closest('tr').dataset.i, 10), 1);
        renderCalc();
    });
    // แก้ราคา/จำนวนในแถว -> คิดยอดใหม่โดยไม่ render ใหม่ (ไม่งั้นช่องที่กำลังพิมพ์จะหลุด focus)
    cTbl.addEventListener('input', function (e) {
        var tr = e.target.closest('tr');
        if (!tr) return;
        var r = calcRows[parseInt(tr.dataset.i, 10)];
        if (!r) return;
        if (e.target.classList.contains('js-calc-price')) {
            r.price = parseFloat(e.target.value) || 0;
            e.target.style.borderColor = r.price > 0 ? '' : '#dc3545';
            e.target.style.background  = r.price > 0 ? '' : '#fff5f5';
        } else if (e.target.classList.contains('js-calc-qty')) {
            r.qty = Math.max(1, Math.min(10, parseInt(e.target.value, 10) || 1));
        }
        calcTotals();
    });

    document.getElementById('tmc-vac-add').addEventListener('click', function () {
        var pick = document.getElementById('tmc-vac-pick');
        if (!pick.value) { alert('กรุณาเลือกวัคซีน'); pick.focus(); return; }
        autoTime();   // ไม่ได้แก้เวลาเอง = ใช้เวลาตอนกดบันทึก ไม่ใช่ตอนเปิดหน้า

        var f = document.createElement('form');
        f.method = 'POST';
        f.action = box.dataset.store;
        f.style.display = 'none';

        var fields = {
            _token:          box.dataset.token,
            page:            box.dataset.page,
            // ชื่อกลาง (ประวัติที่อื่น) ส่งเป็นชื่อ ไม่มีรหัสยา
            medicine_id:     pick.value.indexOf('h:') === 0 ? '' : pick.value,
            name:            pick.value.indexOf('h:') === 0 ? pick.value.slice(2) : '',
            lot_number:      document.getElementById('tmc-vac-lot').value,
            dose_number:     document.getElementById('tmc-vac-dose').value,
            site:            document.getElementById('tmc-vac-site').value,
            route:           document.getElementById('tmc-vac-route').value,
            vaccinated_date: document.getElementById('tmc-vac-date').value,
            vaccinated_time: (window.__tmcVacKeepTime && window.__tmcVacKeepTime(), document.getElementById('tmc-vac-time').value),
            // นัดเข็มถัดไปยังเก็บเหมือนเดิม (คำนวณให้ ไม่ได้ให้กรอก) · หมายเหตุไม่ได้กรอกแล้ว ส่งว่าง
            next_due_date:   (document.getElementById('tmc-vac-next') || {}).value || '',
            source:          document.getElementById('tmc-vac-source').value,
            note:            ''
        };

        Object.keys(fields).forEach(function (k) {
            var i = document.createElement('input');
            i.type = 'hidden'; i.name = k;
            i.value = fields[k] == null ? '' : fields[k];
            f.appendChild(i);
        });

        document.body.appendChild(f);
        f.submit();
    });

    // เลือกวัคซีนที่จะฉีด (ช่องด้านบน) -> ใส่ในเครื่องคิดเลขพร้อมราคาให้เลย
    // ผูกแยกจาก handler ราคาอ้างอิงด้านบน เพราะ calcPut นิยามทีหลัง
    document.getElementById('tmc-vac-pick').addEventListener('change', function () {
        var opt = this.options[this.selectedIndex];
        if (!this.value || this.value.indexOf('h:') === 0) return;   // ประวัติที่อื่นไม่มีราคา
        calcPut(this.value, opt.textContent.trim(), opt.dataset.price, 1);
    });

    // เปิดหน้ามา มีวัคซีนที่หมอสั่งแล้วยังไม่ได้ลงฉีด -> เลือกตัวแรกไว้ให้ (วัคซีน · เข็มที่ · นัดเข็มถัดไป)
    // ทำท้ายสุด ให้ตัวจัดการ change ทุกตัวผูกครบก่อน
    var firstOrd = box.querySelector('.js-vac-ordered');
    if (firstOrd && !document.getElementById('tmc-vac-pick').value) { pickOrdered(firstOrd.dataset.id, firstOrd.dataset.date, firstOrd.dataset.next); }
});
</script>
