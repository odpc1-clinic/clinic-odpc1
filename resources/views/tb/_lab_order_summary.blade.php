{{-- เนื้อในกล่อง "สั่งตรวจครั้งนี้" ของบัตร TB 01 — สรุปสิ่งที่สั่ง/ผลของ visit TB วันนี้ + ปุ่มเปิด popup (2569-10-02)
     ใช้ 2 ทาง: วาดตอนโหลดหน้า (tb/_lab_order) และส่งกลับเป็น HTML ให้สลับในที่หลังบันทึกใน popup
     ⚠️ ห้ามมี <script> (ถูกใส่ด้วย innerHTML สคริปต์จะไม่รัน) — ปุ่มทำงานผ่าน data-bs-toggle + ตัวจัดการใน tb/_lab_order_js
     ตัวแปร: $reg · $tv (visit TB วันนี้ หรือ null) --}}
@php
    $tv = $tv ?? \App\Http\Controllers\TbController::todayTbVisit($reg);
    $__chips = []; $__ext = collect(); $__subs = collect(); $__lab = null;
    if ($tv) {
        $__sc = \App\Models\TbScreening::where('visit_id', $tv->id)->first();
        $__ext = \App\Models\VisitExternalLab::where('visit_id', $tv->id)->orderBy('id')->get();
        $__lab = $tv->lab();
        $__subs = $__lab ? \App\Models\LabSub::where('lab_id', $__lab->id)->orderBy('id')->get() : collect();

        // ชิปละรายการ: [ป้าย, ผล(หรือ null = รอผล), ไอคอน] — ผลว่าง = ยังรอผล
        if ($__sc) {
            foreach ((array) ($__sc->lab_specimens ?? []) as $__sp) {
                $__t = \App\Models\TbScreening::$specimenOption[$__sp['type'] ?? ''] ?? null;
                if (! $__t) { continue; }
                if (! empty($__sp['other'])) { $__t .= ': ' . $__sp['other']; }
                $__chips[] = ['spec', $__t, ! empty($__sp['date']) ? 'ส่ง ' . \App\Utillity::db2thDate($__sp['date']) : 'ยังไม่ระบุวันส่ง', 'fa-vial'];
            }
            $__m = (array) ($__sc->lab_methods ?? []);
            // ผลอ่านจากที่เดียวกับช่องในส่วนส่งตรวจ (ตารางเสมหะของบัตร ถ้ามีแถวของรอบนี้) — 2569-10-05
            $__lv = \App\Models\TbSputumExam::labValues($__sc, $tv);
            $__afbTxt = collect(['Spot' => $__lv['spot'], 'C1' => $__lv['collect1'], 'C2' => $__lv['collect2']])
                ->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v, $k) => $k . ' ' . $v)->implode(' · ');
            if (in_array('afb', $__m, true) || $__afbTxt !== '') {
                $__chips[] = ['test', 'AFB', $__afbTxt !== '' ? $__afbTxt : null, 'fa-microscope'];
            }
            if (in_array('molecular', $__m, true)) {
                $__mol = array_map(fn ($k) => \App\Models\TbScreening::$molecularOption[$k] ?? $k, (array) ($__sc->lab_molecular ?? []));
                $__chips[] = ['test', $__mol ? implode(' / ', $__mol) : 'Molecular', $__lv['molecular'], 'fa-dna'];
            }
            if (in_array('culture_dst', $__m, true)) {
                $__chips[] = ['test', 'Culture / DST', $__lv['culture'], 'fa-bacterium'];
            }
            $__mm = fn ($v) => $v !== null && $v !== '' ? rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.') . ' mm' : null;
            if ($__sc->tst_done)  { $__chips[] = ['test', 'TST', $__mm($__sc->tst_mm), 'fa-syringe']; }
            if ($__sc->cytb_done) { $__chips[] = ['test', 'Cy-TB', $__mm($__sc->cytb_mm), 'fa-syringe']; }
            if ($__sc->skin_other_name) { $__chips[] = ['test', $__sc->skin_other_name, $__mm($__sc->skin_other_mm), 'fa-syringe']; }
            if ($__sc->igra_done) { $__chips[] = ['test', 'IGRA', \App\Models\TbScreening::$igraOption[$__sc->igra_result] ?? null, 'fa-droplet']; }
            if ($__sc->labs_other) { $__chips[] = ['other', 'ตรวจอื่น: ' . $__sc->labs_other, '', 'fa-list']; }
            if ($__sc->afb_status) {
                $__chips[] = ['status', 'ผลเสมหะ', \App\Models\TbScreening::$afbStatusOption[$__sc->afb_status] ?? null, 'fa-lungs'];
            }
        }
    }
    $__labErr = isset($errors) ? $errors->getBag('tbLab') : new \Illuminate\Support\MessageBag;
@endphp

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
    <h6 class="fw-bold mb-0 flex-grow-1"><i class="fa fa-flask me-1"></i> สั่งตรวจครั้งนี้
        <span class="small text-muted fw-normal">
            @if($tv)
                Visit #{{ $tv->id }} · {{ \App\Utillity::db2thDate($tv->date) }} — ลงใน visit เดียวกับหน้าตรวจ OPD
            @else
                ยังไม่มี visit TB วันนี้
            @endif
        </span>
    </h6>
</div>

@if(! $tv)
    <div class="alert alert-warning py-2 mb-2">
        <i class="fa fa-circle-exclamation me-1"></i>
        ยังไม่ได้ลงทะเบียนวันนี้ — ลงทะเบียนเปิด visit ก่อนจึงจะสั่งตรวจได้
    </div>
@endif

{{-- แคตตาล็อกแล็บรวมเปิดใช้แล้ว (2569-10-02): สั่งทุกอย่างที่ปุ่ม "สั่งแล็บ" ปุ่มเดียว
     popup เดิมยังเปิดได้ แต่เปลี่ยนชื่อเป็นงานลงผล/แก้ไข · ตาราง lab_catalog_items ยังไม่ขึ้น = ปุ่มชุดเดิมทุกอย่าง --}}
@php $__lcat = \App\Models\LabCatalogItem::availableFor(2); @endphp
@if($__lcat)
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    @include('partials.lab_catalog_button', ['visit' => $tv, 'context' => 'tb01', 'tbReg' => $reg->id,
             'btnClass' => 'tb-lo-act tb-lo-act-tb', 'disabledTitle' => 'ลงทะเบียนเปิด visit ก่อน'])
    <button type="button" class="btn tb-lo-act tb-lo-act-inh" data-bs-toggle="modal" data-bs-target="#tbLabModal" data-focus="results"
            @if(! $tv) disabled title="ลงทะเบียนเปิด visit ก่อน" @endif>
        <i class="fa fa-vial me-1"></i> ลงผล / แก้ไข ส่งตรวจวัณโรค
    </button>
    <button type="button" class="btn tb-lo-act tb-lo-act-ext" data-bs-toggle="modal" data-bs-target="#tbExtLabModal"
            @if(! $tv) disabled title="ลงทะเบียนเปิด visit ก่อน" @endif>
        <i class="fa fa-truck-medical me-1"></i> ผล / ราคา Lab ภายนอก
    </button>
    @if($tv)
        <a href="{{ route('visit.edit', ['visit' => $tv->id, 'page' => 1]) }}" class="small ms-auto text-decoration-none" style="color:#92400e;">
            <i class="fa fa-arrow-up-right-from-square me-1"></i>เปิดหน้าตรวจ OPD ของวันนี้
        </a>
    @endif
</div>
@else
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <button type="button" class="btn tb-lo-act tb-lo-act-tb" data-bs-toggle="modal" data-bs-target="#tbLabModal"
            @if(! $tv) disabled title="ลงทะเบียนเปิด visit ก่อน" @endif>
        <i class="fa fa-vial me-1"></i> สั่งตรวจ / ลงผล
    </button>
    <button type="button" class="btn tb-lo-act tb-lo-act-inh" data-bs-toggle="modal" data-bs-target="#tbInhLabModal"
            @if(! $tv) disabled title="ลงทะเบียนเปิด visit ก่อน" @endif>
        <i class="fa fa-flask-vial me-1"></i> Lab ในคลินิก (HIV)
    </button>
    <button type="button" class="btn tb-lo-act tb-lo-act-ext" data-bs-toggle="modal" data-bs-target="#tbExtLabModal"
            @if(! $tv) disabled title="ลงทะเบียนเปิด visit ก่อน" @endif>
        <i class="fa fa-truck-medical me-1"></i> Lab ภายนอก
    </button>
    @if($tv)
        <a href="{{ route('visit.edit', ['visit' => $tv->id, 'page' => 1]) }}" class="small ms-auto text-decoration-none" style="color:#92400e;">
            <i class="fa fa-arrow-up-right-from-square me-1"></i>เปิดหน้าตรวจ OPD ของวันนี้
        </a>
    @endif
</div>
@endif

{{-- ช่วงนี้ต้องตรวจอะไรตามตารางติดตามการรักษา + ปุ่มสั่งแล็บที่ถึงกำหนด (ติ๊กให้ล่วงหน้า) — 2569-10-05 --}}
@include('tb._monitor_due', ['reg' => $reg, 'tv' => $tv])

@if($__labErr->any())
    {{-- กรณีส่งแบบไม่มี JS แล้วบันทึกไม่ผ่าน --}}
    <div class="alert alert-danger py-2 small">
        <b>ยังไม่ได้บันทึก:</b> @foreach($__labErr->all() as $__e){{ $__e }}@if(! $loop->last) · @endif @endforeach
    </div>
@endif

@if($tv)
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="tb-lo-panel">
                <div class="tb-lo-panel-title"><i class="fa fa-vial me-1"></i> ส่งตรวจทางห้องปฏิบัติการ</div>
                @if($__chips)
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($__chips as [$__kind, $__label, $__res, $__icon])
                            @php $__pending = $__kind === 'test' && ($__res === null || $__res === ''); @endphp
                            <span class="tb-chip {{ $__kind === 'spec' ? 'tb-chip-spec' : ($__pending ? 'tb-chip-wait' : 'tb-chip-done') }}">
                                <i class="fa {{ $__icon }} me-1"></i><b>{{ $__label }}</b>
                                @if($__kind === 'spec')
                                    <span class="tb-chip-sub">{{ $__res }}</span>
                                @elseif($__pending)
                                    <span class="tb-chip-sub">รอผล</span>
                                @elseif($__res !== '')
                                    <span class="tb-chip-sub">{{ $__res }}</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                @else
                    <div class="text-muted small">ยังไม่ได้สั่งใน visit นี้ — กด "{{ $__lcat ? 'สั่งแล็บ' : 'สั่งตรวจ / ลงผล' }}"</div>
                @endif
            </div>

            {{-- Lab ในคลินิก — กดชิป/ปุ่มเพื่อสั่งเพิ่มหรือถอด (popup "Lab ในคลินิก") · ผลโชว์เมื่อห้อง Lab อนุมัติแล้วเท่านั้น --}}
            @php $__vis = $__lab ? $__lab->isResultVisible() : false; @endphp
            <div class="tb-lo-panel mt-2">
                <div class="tb-lo-panel-title d-flex justify-content-between">
                    <span><i class="fa fa-flask-vial me-1"></i> Lab ในคลินิก</span>
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" style="color:#6d28d9;"
                            data-bs-toggle="modal" data-bs-target="#tbInhLabModal">@if($__lcat)<i class="fa fa-pen me-1"></i>แก้ไข / ถอด@else<i class="fa fa-plus me-1"></i>สั่ง / แก้@endif</button>
                </div>
                @if($__subs->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($__subs->sortBy(fn ($s) => (int) $s->method === 6 ? 0 : 1) as $s)
                            @php
                                $__has = trim((string) $s->result) !== '';
                                $__txt = ! $__has ? 'รอผล' : ($__vis ? $s->result : 'มีผลแล้ว · รอห้อง Lab อนุมัติ');
                                $__hiv = (int) $s->method === 6;
                            @endphp
                            <button type="button" class="tb-chip {{ $__has && $__vis ? 'tb-chip-done' : 'tb-chip-wait' }}"
                                    data-bs-toggle="modal" data-bs-target="#tbInhLabModal"
                                    @if($__hiv) style="border-width:2px; font-size:.95rem;" @endif>
                                @if($__hiv)<span class="badge me-1" style="background:#7c3aed;">HIV</span>@endif
                                <b>{{ ltrim(\App\Models\LabSub::$methodOption[$s->method] ?? 'Lab', '# ') }}{{ (int) $s->method === 10 && $s->method_other ? ': ' . $s->method_other : '' }}</b>
                                @if($__hiv && $__has && $__vis)
                                    {{-- ผล HIV ปิดไว้ ไม่วาดค่าลง HTML (2569-10-06) --}}
                                    <span class="tb-chip-sub">@include('partials.hiv_mask', ['hmPatient' => $reg->patient_id, 'hmKind' => 'sub', 'hmRef' => $s->id,
                                        'hmReg' => $reg->id, 'hmVisit' => $tv->id, 'hmWhere' => 'บัตร TB 01 · สั่งตรวจครั้งนี้', 'hmActions' => false])</span>
                                @else
                                    <span class="tb-chip-sub">{{ \Illuminate\Support\Str::limit($__txt, 40) }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="text-muted small">ยังไม่ได้สั่ง — กด "{{ $__lcat ? 'สั่งแล็บ' : 'Lab ในคลินิก (HIV)' }}" เพื่อสั่งตรวจ HIV / เลือด</div>
                @endif
            </div>
        </div>

        <div class="col-lg-5">
            <div class="tb-lo-panel">
                <div class="tb-lo-panel-title d-flex justify-content-between">
                    <span><i class="fa fa-truck-medical me-1"></i> Lab ภายนอก</span>
                    @if($__ext->count())
                        <span class="fw-normal">รวม {{ number_format($__ext->sum(fn ($r) => (float) $r->price), 2) }} บาท</span>
                    @endif
                </div>
                @forelse($__ext as $e)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-1 small">
                        <div>
                            <b>{{ $e->name }}</b>
                            @if($e->vendor && $e->vendor !== 'N Health')<span class="text-muted"> · {{ $e->vendor }}</span>@endif
                            <div>
                                @if($e->hasResult())
                                    <span class="tb-chip tb-chip-done py-0">{{ \Illuminate\Support\Str::limit($e->result, 40) }}</span>
                                @else
                                    <span class="tb-chip tb-chip-wait py-0">รอผล</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-end text-nowrap ms-2">{{ $e->price !== null ? number_format((float) $e->price, 2) : '—' }}</div>
                    </div>
                @empty
                    <div class="text-muted small">ยังไม่มีรายการ — กด "{{ $__lcat ? 'สั่งแล็บ' : 'Lab ภายนอก' }}"</div>
                @endforelse
            </div>
        </div>
    </div>
@endif
