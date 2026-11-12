{{-- ตารางติดตามการรักษา (บัตร TB 01 แท็บ "ติดตาม") — แถว = รายการตรวจ · คอลัมน์ = จุดเวลา เหมือนแบบฟอร์มกระดาษ (2569-10-05)
     ถูกสลับด้วย innerHTML หลังบันทึก — ⚠️ ห้ามมี <script> ห้ามมี <form>
     กดช่อง = popup รายละเอียด/ติ๊กมือ (#tbMonitorTickModal ท้ายหน้า เปิดด้วย data-bs-toggle)
     นิยามตาราง: App\Support\TbMonitorDefinitions · ตัวคำนวณ: App\Services\TbMonitor\TbMonitorEngine
     ตัวแปร: $reg --}}
@php
    $__TM = \App\Services\TbMonitor\TbMonitor::class;
    $__m = $__TM::safe($reg);
    $__g = $__m['grid'] ?? null;
@endphp

@php
    // สูตรกำหนดเอง (Individualized) = แพทย์กำหนดตารางเองได้ · สูตรมาตรฐานใช้ตารางมาตรฐาน (คลินิกตกลง 2569-10-22)
    try { $__rt = (string) ($reg->currentRegimenText() ?: $reg->regimen); } catch (\Throwable $e) { $__rt = (string) $reg->regimen; }
    $__canPlan = $__TM::isCustomRegimen($__rt) && \Illuminate\Support\Facades\Schema::hasColumn('tb_registrations', 'monitor_plan');
    $__hasPlan = $__canPlan && $__TM::planOf($reg);
@endphp
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
    <h6 class="fw-bold mb-0 flex-grow-1"><i class="fa fa-table-list me-1"></i> ตารางติดตามการรักษา
        @if($__g)<span class="small text-muted fw-normal">{{ $__g['title'] }}</span>@endif
    </h6>
    @if($__canPlan)
        <div class="d-flex gap-1">
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#tbMonitorPlanModal">
                <i class="fa fa-pen-ruler me-1"></i> {{ $__hasPlan ? 'แก้ตารางที่กำหนดเอง' : 'กำหนดตารางเอง' }}
            </button>
            @if($__hasPlan)
                <button type="button" class="btn btn-sm btn-outline-secondary js-tbm-plan-reset" data-url="{{ route('tb.monitor.plan.reset', $reg->id) }}">ใช้ตารางมาตรฐาน</button>
            @endif
        </div>
    @endif
</div>
@if($__canPlan && ! $__hasPlan)
    <div class="small text-muted mb-2"><i class="fa fa-circle-info me-1"></i>สูตรกำหนดเอง ({{ $__rt }}) — ตอนนี้ใช้ตาราง{{ $__g['title'] ?? 'มาตรฐาน' }}ไปก่อน · กด "กำหนดตารางเอง" เพื่อเลือกว่าตรวจอะไรเดือนไหน</div>
@endif

@if(! $__m)
    <div class="alert alert-warning py-2 mb-0">คำนวณตารางติดตามไม่สำเร็จ — แจ้งผู้ดูแลระบบ (ส่วนอื่นของบัตรใช้ได้ตามปกติ)</div>
@elseif(! $__g)
    <div class="alert alert-light border py-2 mb-0">ทะเบียนประเภทนี้ยังไม่มีตารางติดตาม</div>
@else
    @if(! $__g['start'])
        <div class="alert alert-warning py-2 small">
            <i class="fa fa-triangle-exclamation me-1"></i> ยังไม่ได้กรอก <b>วันเริ่มรักษา</b> ในแท็บข้อมูลบัตร — คำนวณวันที่ควรตรวจไม่ได้ ผลที่มีจะลงช่องแรกรับอย่างเดียว
        </div>
    @endif
    @if(! $__m['ready']['manual'])
        <div class="small text-muted mb-2"><i class="fa fa-hourglass-half me-1"></i>ติ๊กมือในตาราง (consent, EKG, ตรวจที่อื่น) <b>รอเปิดใช้</b> — ผู้ดูแลระบบยังไม่ได้สร้างตาราง ส่วนที่ระบบดึงผลเองใช้ได้แล้ว</div>
    @endif

    <div class="d-flex flex-wrap gap-2 small mb-2 tb-mon-legend">
        <span><span class="tb-mon-cell tb-mon-done">✓ 8/9</span> ตรวจแล้ว</span>
        <span><span class="tb-mon-cell tb-mon-due">ถึงกำหนด</span></span>
        <span><span class="tb-mon-cell tb-mon-overdue">เกินกำหนด</span></span>
        <span><span class="tb-mon-cell tb-mon-upcoming">✓</span> ต้องตรวจ (ยังไม่ถึง)</span>
        <span><span class="tb-mon-cell tb-mon-cond">?</span> ตรวจเมื่อมีเงื่อนไข</span>
        <span><span class="tb-mon-cell tb-mon-optional">พิจารณา</span></span>
        <span><span class="tb-mon-pos">บวก</span> ผลเชื้อบวก</span>
    </div>

    <div class="table-responsive tb-mon-wrap">
        <table class="table table-sm table-bordered align-middle mb-2 tb-mon-table">
            <thead>
                <tr>
                    <th class="tb-mon-first">รายการตรวจ</th>
                    @foreach($__g['points'] as $__pk => $__p)
                        <th class="text-center {{ $__g['current'] === $__pk ? 'tb-mon-now' : '' }} {{ $__p['flag'] === 'ext' ? 'tb-mon-extcol' : '' }}"
                            title="{{ $__p['expected'] && $__p['flag'] !== 'ext' ? 'ควรตรวจราว ' . $__TM::shortTh($__p['expected']) . ' (' . $__TM::shortTh($__p['from']) . ' – ' . $__TM::shortTh($__p['to']) . ')' : '' }}">
                            <div>{{ $__p['label'] }}</div>
                            {{-- ช่องขยายระยะเข้มข้น: วันที่จริงขึ้นกับว่าขยายเมื่อไร ระบบไม่รู้ — ไม่โชว์วันที่ (จะไปซ้ำกับ ด.5/ด.6 ให้งง) --}}
                            @if($__p['flag'] === 'ext')<div class="tb-mon-date">ถ้าขยาย</div>
                            @elseif($__p['expected'])<div class="tb-mon-date">{{ $__TM::shortTh($__p['expected']) }}</div>@endif
                            @if($__g['current'] === $__pk)<div class="tb-mon-date fw-bold">ตอนนี้</div>@endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
            @foreach($__g['rows'] as $__row)
                <tr>
                    <th class="tb-mon-first fw-normal">
                        <div class="fw-semibold">{{ $__row['label'] }}</div>
                        @if($__row['sub'])<div class="tb-mon-date">{{ $__row['sub'] }}</div>@endif
                        @if($__row['manualOnly'])<div class="tb-mon-date" style="color:#6d28d9;">ติ๊กเอง</div>@endif
                    </th>
                    @foreach($__g['points'] as $__pk => $__p)
                        @php
                            $__c  = $__row['cells'][$__pk];
                            $__st = $__c['status'];
                            $__ev = $__c['event'];
                            $__mn = $__c['manual'];
                            $__detail = '';
                            if ($__ev) {
                                $__detail = ($__ev['date'] ? $__TM::shortTh($__ev['date']) . ' · ' : '') . $__ev['text'] . ' (' . $__ev['src'] . ')'
                                          . (! empty($__ev['badDate']) ? ' · วันที่ในตารางเสมหะผิด จับคู่ตามป้ายเดือน' : '');
                            } elseif ($__mn) {
                                $__detail = 'ติ๊กมือ' . ($__mn['date'] ? ' · ' . $__TM::shortTh($__mn['date']) : '') . ($__mn['note'] ? ' · ' . $__mn['note'] : '');
                            }
                        @endphp
                        <td class="text-center p-1 {{ $__g['current'] === $__pk ? 'tb-mon-nowcol' : '' }}">
                            @if($__st !== 'na' || $__ev || $__mn)
                                <button type="button" class="tb-mon-cell tb-mon-{{ $__st }} {{ $__c['need'] === 'extra' ? 'tb-mon-extra' : '' }}"
                                        data-bs-toggle="modal" data-bs-target="#tbMonitorTickModal"
                                        data-check="{{ $__row['key'] }}" data-point="{{ $__pk }}"
                                        data-check-label="{{ $__row['label'] }}" data-point-label="{{ $__p['label'] }}"
                                        data-expected="{{ $__p['flag'] === 'ext' ? 'เดือนที่ขยายระยะเข้มข้น' : $__TM::shortTh($__c['expected']) }}"
                                        data-status="{{ \App\Support\TbMonitorDefinitions::STATUS_LABELS[$__st] ?? $__st }}"
                                        data-why="{{ $__c['why'] }}" data-detail="{{ $__detail }}" data-auto="{{ $__ev ? 1 : 0 }}"
                                        @if($__mn) data-manual="1" data-mdate="{{ $__mn['date'] ? \App\Utillity::db2thDate($__TM::toBe($__mn['date'])) : '' }}" data-mnote="{{ $__mn['note'] }}" @endif
                                        title="{{ $__detail ?: trim(($__c['why'] ?? '') . ' ' . (\App\Support\TbMonitorDefinitions::STATUS_LABELS[$__st] ?? '')) }}">
                                    @switch($__st)
                                        @case('done')
                                            ✓ {{ $__ev ? ($__ev['date'] ? $__TM::shortTh($__ev['date']) : '') : ($__mn['date'] ? $__TM::shortTh($__mn['date']) : 'ติ๊ก') }}
                                            @if($__ev && ($__ev['pos'] ?? null) === true)<span class="tb-mon-pos">บวก</span>@endif
                                            @if($__ev && ! empty($__ev['pending']))<span class="tb-mon-wait">รอผล</span>@endif
                                            @break
                                        @case('due') ถึงกำหนด @break
                                        @case('overdue') เกินกำหนด @break
                                        @case('upcoming') ✓ @break
                                        @case('optional') พิจารณา @break
                                        @case('cond') ? @break
                                    @endswitch
                                </button>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <ul class="small text-muted mb-0 ps-3">
        @foreach($__g['notes'] as $__n)<li>{{ $__n }}</li>@endforeach
        <li>ระบบดึงผลเองจาก ตารางเสมหะ · ใบสั่งตรวจ · X-ray · DST · Lab ในคลินิก/ส่งนอก · น้ำหนัก · การมาตรวจคลินิก TB — นับว่าตรวจตรงจุดไหนจากวันที่ (สัปดาห์ ±7 วัน · เดือน ±14 วัน · ก่อนเริ่ม ย้อนได้ 60 วัน)
            ผลเสมหะใช้ป้ายเดือนที่กรอกในตาราง</li>
        <li>สั่งตรวจแล้วรอผล = นับว่าตรวจแล้ว (ไม่เตือนซ้ำ) · กดช่องเพื่อดูรายละเอียด หรือติ๊กเองเมื่อไปตรวจที่อื่น</li>
    </ul>
@endif
