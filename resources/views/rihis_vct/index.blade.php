@extends('layouts.app')

@section('content')
<style>
    #rv-page { font-size: 1.02rem; }
    #rv-page table.rv td, #rv-page table.rv th { padding: .35rem .4rem; vertical-align: middle; white-space: nowrap; }
    #rv-page table.rv th { font-size: .88rem; background: #f8f9fa; position: sticky; top: 0; z-index: 2; }
    #rv-page .form-control-sm, #rv-page .form-select-sm { font-size: .95rem; min-width: 90px; }
    #rv-page .w-sm { min-width: 74px; }
    #rv-page .w-md { min-width: 130px; }
    #rv-page .w-lg { min-width: 190px; }
    /* ช่องที่ยังรอกรอก — ผู้ใช้ขอให้เน้นสีไว้ ไม่ใช่ปล่อยว่างเงียบๆ */
    #rv-page .rv-pending { background: #fff3cd !important; border-color: #ffc107 !important; }
    /* กลับมาฟังผลคนละวันกับวันตรวจ — ไม่ใช่ความผิด แค่ให้สังเกตได้ว่าไม่ใช่ SDR */
    #rv-page .rv-later   { background: #cfe8ff !important; border-color: #6ea8fe !important; }
    /* ช่องที่ค่าเป็น 0 / "ไม่ได้" — ให้มองเห็นว่าเคสนี้ไม่ได้รับบริการส่วนนั้น */
    #rv-page .rv-zero    { background: #f8d7da !important; border-color: #ea868f !important; }
    #rv-page .rv-saved   { box-shadow: 0 0 0 .18rem rgba(25,135,84,.35); transition: box-shadow .2s; }
    #rv-page .rv-scroll  { max-height: 68vh; overflow: auto; }

    /* ตรึง 4 คอลัมน์แรก (# / วันที่ / ชื่อ / นามสกุล) ไว้ตอนเลื่อนแนวนอน
       ตารางกว้าง 30 คอลัมน์ ถ้าไม่ตรึง เลื่อนไปขวาแล้วไม่รู้ว่ากำลังแก้ของใคร
       ต้องกำหนดความกว้างตายตัว เพราะ sticky ต้องรู้ระยะ left ที่แน่นอน */
    #rv-page table.rv th:nth-child(1), #rv-page table.rv td:nth-child(1) { position: sticky; left: 0;     width: 52px;  min-width: 52px;  max-width: 52px; }
    #rv-page table.rv th:nth-child(2), #rv-page table.rv td:nth-child(2) { position: sticky; left: 52px;  width: 112px; min-width: 112px; max-width: 112px; }
    #rv-page table.rv th:nth-child(3), #rv-page table.rv td:nth-child(3) { position: sticky; left: 164px; width: 148px; min-width: 148px; max-width: 148px; }
    #rv-page table.rv th:nth-child(4), #rv-page table.rv td:nth-child(4) { position: sticky; left: 312px; width: 132px; min-width: 132px; max-width: 132px; }

    #rv-page table.rv td:nth-child(-n+4) { background: #fff; z-index: 1; }
    #rv-page table.rv th:nth-child(-n+4) { z-index: 3; }          /* หัวตาราง + ตรึงซ้าย ต้องอยู่บนสุด */
    #rv-page table.rv tr:hover td:nth-child(-n+4) { background: #f2f6fc; }
    /* เส้นคั่นบอกขอบเขตส่วนที่ตรึงไว้ */
    #rv-page table.rv th:nth-child(4), #rv-page table.rv td:nth-child(4) { box-shadow: 3px 0 5px -2px rgba(0,0,0,.3); }

    /* ช่องกรอกในคอลัมน์ที่ตรึง ต้องไม่ดันความกว้างเกินที่กำหนดไว้ */
    #rv-page table.rv td:nth-child(-n+4) .form-control,
    #rv-page table.rv td:nth-child(-n+4) .w-md { min-width: 0; width: 100%; }
</style>

<div class="container-fluid py-3" id="rv-page">

    <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
        <h4 class="mb-0"><i class="fas fa-hand-holding-medical me-2"></i>ทะเบียน RIHIS-VCT</h4>
        <span class="text-muted">บริการปรึกษาและตรวจเอชไอวี · กรอกในตารางแล้วบันทึกอัตโนมัติ</span>
    </div>

    {{-- ตัวกรอง + ปุ่ม --}}
    <div class="card card-body py-2 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <form method="GET" action="{{ route('rihis-vct.index') }}" class="d-flex align-items-end gap-2 flex-wrap" id="rv-filter">
                    {{-- ช่องค้นหาไว้ซ้ายสุด — ผู้ใช้ค้นหาคนบ่อยกว่าเปลี่ยนช่วงวันที่ (ขอ 2569-10-01) --}}
                    <div>
                        <label class="form-label mb-0 small">ค้นหา HN / ชื่อ</label>
                        {{-- ปุ่ม "ดู" ติดกับช่องค้นหาให้อ่านเป็นปุ่มค้นหา แต่ยังเป็นปุ่ม submit ของฟอร์มกรองเดิม
                             จึงส่งช่วงวันที่/แถวต่อหน้าไปด้วยทุกครั้ง --}}
                        <div class="input-group input-group-sm flex-nowrap">
                            <input type="search" name="q" value="{{ $q }}" autocomplete="off"
                                   class="form-control form-control-sm" placeholder="HN หรือชื่อ-สกุล" style="width:180px">
                            <button type="submit" class="btn btn-secondary btn-sm"><i class="fa fa-filter me-1"></i> ดู</button>
                        </div>
                    </div>
                    <div>
                        <label class="form-label mb-0 small">ตั้งแต่วันที่</label>
                        <input type="text" name="from" value="{{ $fromTh }}" autocomplete="off"
                               class="form-control form-control-sm date" placeholder="ว/ด/ป" style="width:120px">
                    </div>
                    <div>
                        <label class="form-label mb-0 small">ถึงวันที่</label>
                        <input type="text" name="to" value="{{ $toTh }}" autocomplete="off"
                               class="form-control form-control-sm date" placeholder="ว/ด/ป" style="width:120px">
                    </div>
                    <div>
                        <label class="form-label mb-0 small">แถว/หน้า</label>
                        <select name="per" class="form-select form-select-sm" style="width:85px">
                            @foreach([25,50,100,200] as $p)
                                <option value="{{ $p }}" @if($per === $p) selected @endif>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                    {{-- ปุ่มลัดช่วงที่ใช้บ่อย จะได้ไม่ต้องกดปฏิทินทุกครั้ง --}}
                    <span class="d-flex gap-1">
                        <button type="button" class="btn btn-outline-secondary btn-sm js-rv-quick" data-q="thismonth">เดือนนี้</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm js-rv-quick" data-q="lastmonth">เดือนที่แล้ว</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm js-rv-quick" data-q="fy">ปีงบนี้</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm js-rv-quick" data-q="all">ทั้งหมด</button>
                    </span>
                    {{-- ดูปีงบย้อนหลัง (พยาบาลแจ้ง 2569-10-30 กรองปีก่อนไม่ขึ้น) — เลือกแล้วเปิดเลย ไม่ต้องพิมพ์วันที่เอง --}}
                    @php
                        $curFy = now()->month >= 10 ? now()->year + 544 : now()->year + 543;
                    @endphp
                    <select class="form-select form-select-sm js-rv-fy" style="width:120px" title="ดูทั้งปีงบประมาณ">
                        <option value="">ปีงบ…</option>
                        @for($fy = $curFy; $fy >= 2565; $fy--)
                            <option value="{{ $fy }}" @if($fromTh === '1/10/' . ($fy - 1) && $toTh === '30/9/' . $fy) selected @endif>ปีงบ {{ $fy }}</option>
                        @endfor
                    </select>
                </form>
            </div>

            @if($q !== '')
                {{-- ต้องบอกให้ชัดว่าตอนนี้ไม่ได้ดูตามช่วงวันที่แล้ว ไม่งั้นตัวเลขสรุปจะดูขัดกับวันที่ที่ค้างอยู่ในช่อง --}}
                <div class="col-auto">
                    <div class="alert alert-warning py-1 px-2 mb-0 small d-flex align-items-center gap-2">
                        <i class="fas fa-magnifying-glass"></i>
                        <span>ค้นหา <b>{{ $q }}</b> — ค้นทั้งทะเบียน ไม่จำกัดช่วงวันที่</span>
                        <a href="{{ route('rihis-vct.index', ['from' => $fromTh, 'to' => $toTh, 'per' => $per]) }}"
                           class="btn btn-outline-secondary btn-sm py-0">ล้างการค้นหา</a>
                    </div>
                </div>
            @endif

            <div class="col-auto">
                {{-- ดึงเคสจากระบบตามกติกาของพี่อิ๋ว: วินิจฉัย Pre/Post-test + แล็บ Anti-HIV ของเรา --}}
                <form method="POST" action="{{ route('rihis-vct.sync') }}" class="d-inline">
                    {{ csrf_field() }}
                    <input type="hidden" name="from" value="{{ $fromTh }}">
                    <input type="hidden" name="to" value="{{ $toTh }}">
                    <input type="hidden" name="per" value="{{ $per }}">
                    <input type="hidden" name="q" value="{{ $q }}">
                    <button class="btn btn-warning btn-sm"
                            onclick="return confirm('ดึงเคสจากระบบเข้าทะเบียน ช่วง {{ $fromTh }} – {{ $toTh }}?\n\nช่องที่ระบบเป็นเจ้าของจะถูกคำนวณใหม่จากข้อมูลใน visit\nช่องที่พยาบาลกรอกเอง (กลุ่มเป้าหมาย หมายเหตุ CD4) ไม่ถูกแตะ');">
                        <i class="fa fa-rotate me-1"></i> ดึงข้อมูลจากระบบ
                    </button>
                </form>
            </div>

            <div class="col-auto ms-auto">
                <form method="POST" action="{{ route('rihis-vct.store') }}" class="d-flex align-items-end gap-2">
                    {{ csrf_field() }}
                    <input type="hidden" name="from" value="{{ $fromTh }}">
                    <input type="hidden" name="to" value="{{ $toTh }}">
                    <input type="hidden" name="q" value="{{ $q }}">
                    <div>
                        <label class="form-label mb-0 small">HN (ใส่แล้วดึงข้อมูลคนไข้ให้)</label>
                        <input type="text" name="hn" class="form-control form-control-sm" placeholder="ไม่ใส่ก็ได้" style="width:170px">
                    </div>
                    <div>
                        <label class="form-label mb-0 small">วันที่รับบริการ</label>
                        <input type="text" name="service_date" class="form-control form-control-sm date" placeholder="ว/ด/ป" style="width:120px">
                    </div>
                    <button class="btn btn-success btn-sm"><i class="fa fa-plus me-1"></i> เพิ่มแถว</button>
                </form>
            </div>

            <div class="col-auto">
                <a href="{{ route('rihis-vct.export', ['from' => $fromTh, 'to' => $toTh]) }}" class="btn btn-primary btn-sm">
                    <i class="fa fa-file-excel me-1"></i> ส่งออก Excel
                </a>
            </div>
        </div>
    </div>

    {{-- สรุปรายงาน (ไฟล์ต้นฉบับสูตรพัง จึงคำนวณให้ที่นี่) --}}
    <div class="card mb-3">
        <div class="card-header bg-white d-flex align-items-center gap-2">
            <i class="fas fa-chart-simple text-primary"></i> <b>สรุปรายงาน</b>
            <span class="text-muted small">
                @if($q !== '')
                    นับจากผลค้นหา "{{ $q }}" ทั้งหมด ({{ number_format($rangeCnt) }} ราย) ไม่ใช่แค่หน้านี้ · ทุกช่วงวันที่
                @else
                    นับจากช่วงที่เลือกทั้งหมด ({{ number_format($rangeCnt) }} ราย) ไม่ใช่แค่หน้านี้ ·
                    {{ $fromTh }} – {{ $toTh }}
                @endif
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 text-center">
                <thead class="table-light">
                    <tr>
                        <th class="text-start">กิจกรรม</th><th>รวม</th><th>ช</th><th>ญ</th>
                        <th>FSW</th><th>MSW</th><th>MSM</th><th>PWID</th>
                        <th>แรงงานข้ามชาติ</th><th>เยาวชน</th><th>อื่นๆ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach([['Pre-test counseling','pre'],['HIV Testing','test'],['Post-test counseling','post'],['HIV Positive','positive'],['HIV Positive ได้รับการส่งต่อ','referred']] as [$label,$key])
                        @php $s = $summary[$key]; @endphp
                        <tr @if($key === 'positive') class="table-warning" @endif>
                            <td class="text-start">{{ $label }}</td>
                            <td class="fw-bold">{{ $s['total'] }}</td>
                            <td>{{ $s['male'] }}</td><td>{{ $s['female'] }}</td>
                            <td>{{ $s['fsw'] }}</td><td>{{ $s['msw'] }}</td><td>{{ $s['msm'] }}</td>
                            <td>{{ $s['pwid'] }}</td><td>{{ $s['migrants'] }}</td>
                            <td>{{ $s['youth'] }}</td><td>{{ $s['other'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3 mb-2 small flex-wrap">
        <span class="badge bg-secondary">
            แสดง {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} จาก {{ number_format($rows->total()) }} ราย
        </span>
        <span><span class="badge" style="background:#fff3cd;color:#664d03;border:1px solid #ffc107;">สีเหลือง</span> = ยังรอกรอก</span>
        <span><span class="badge" style="background:#cfe8ff;color:#084298;border:1px solid #6ea8fe;">สีฟ้า</span> = วันฟังผลคนละวันกับวันตรวจ</span>
        <span><span class="badge" style="background:#f8d7da;color:#842029;border:1px solid #ea868f;">สีชมพู</span> = ค่าเป็น 0 / ไม่ได้</span>
        <span class="text-muted">แก้ในช่องแล้วบันทึกให้อัตโนมัติ (ขอบเขียว = บันทึกแล้ว)</span>
    </div>

    @if($rows->isEmpty())
        <div class="alert alert-light border text-center text-muted">
            @if($q !== '')
                <i class="fas fa-magnifying-glass me-1"></i> ไม่พบ "{{ $q }}" ในทะเบียน (ค้นจากทั้ง {{ number_format($total) }} แถว ไม่จำกัดวันที่)
                — ลองพิมพ์สั้นลง หรือกด "ดึงข้อมูลจากระบบ" เผื่อเคสนี้ยังไม่ถูกดึงเข้าทะเบียน
            @else
                <i class="fas fa-inbox me-1"></i> ไม่มีข้อมูลในช่วง {{ $fromTh }} – {{ $toTh }} (ทั้งทะเบียนมี {{ number_format($total) }} แถว) — ลองขยายช่วงวันที่ หรือกด "ดึงข้อมูลจากระบบ"
            @endif
        </div>
    @else
    <div class="rv-scroll border rounded">
        <table class="table table-sm table-bordered table-hover rv mb-0" id="rv-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>วันที่รับบริการ</th>
                    <th>ชื่อ</th><th>นามสกุล</th>
                    <th>HN</th><th>เลขบัตร ปชช.</th>
                    <th>อายุ</th><th>วันเกิด</th><th>เพศ</th>
                    <th>สถานภาพสมรส</th><th>สัญชาติ</th>
                    <th>กลุ่มเป้าหมาย</th><th>กลุ่มย่อย MSW</th><th>รูปแบบ SW</th>
                    <th>ส่งต่อมาจาก</th><th>ประวัติการตรวจ HIV</th>
                    <th>ปรึกษาก่อนตรวจ</th><th>ตรวจเลือด</th><th>ฟังผลเลือด</th>
                    <th>วันฟังผล</th><th>ผลเลือด</th>
                    <th>วิธีการตรวจ</th>
                    <th>ส่งต่อรับบริการอื่น</th>
                    <th>ส่งต่อโรงพยาบาล</th>
                    <th>วันโทรตาม</th>
                    <th>ส่งตรวจ CD4</th><th>ค่า CD4</th>
                    <th>หมายเหตุ</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($rows as $r)
                @php
                    $pending = $r->pendingFields();
                    $vlink = $r->visit_id ? route('visit.edit', ['visit' => $r->visit_id, 'page' => 2]) : null;
                @endphp
                <tr data-id="{{ $r->id }}">
                    <td class="text-muted">
                        {{ $loop->index + ($rows->firstItem() ?? 1) }}
                        {{-- ตัวเลขไม่ตรง = เข้าไปแก้ที่ visit ต้นทางแล้วกดดึงใหม่ (พี่อิ๋วขอ) --}}
                        @if($r->visit_id)
                            <a href="{{ route('visit.edit', ['visit' => $r->visit_id, 'page' => 2]) }}"
                               target="_blank" class="ms-1 text-decoration-none"
                               title="เปิด visit #{{ $r->visit_id }} เพื่อแก้ไข (แท็บใหม่)">
                                <i class="fas fa-up-right-from-square small"></i>
                            </a>
                        @endif
                    </td>

                    @include('rihis_vct.cell_date',   ['r' => $r, 'f' => 'service_date', 'pending' => $pending])
                    @include('rihis_vct.cell_text',   ['r' => $r, 'f' => 'name',      'pending' => $pending, 'w' => 'w-md', 'link' => $vlink])
                    @include('rihis_vct.cell_text',   ['r' => $r, 'f' => 'surname',   'pending' => $pending, 'w' => 'w-md'])
                    @include('rihis_vct.cell_text',   ['r' => $r, 'f' => 'hn',        'pending' => $pending, 'w' => 'w-md', 'link' => $vlink])
                    @include('rihis_vct.cell_text',   ['r' => $r, 'f' => 'id_card',   'pending' => $pending, 'w' => 'w-md'])
                    @include('rihis_vct.cell_text',   ['r' => $r, 'f' => 'age',       'pending' => $pending, 'w' => 'w-sm'])
                    @include('rihis_vct.cell_date',   ['r' => $r, 'f' => 'birth_date','pending' => $pending])
                    @include('rihis_vct.cell_select', ['r' => $r, 'f' => 'sex',       'pending' => $pending, 'opts' => App\Models\RihisVct::$sexOption])
                    @include('rihis_vct.cell_select', ['r' => $r, 'f' => 'marital',   'pending' => $pending, 'opts' => App\Models\RihisVct::$maritalOption])
                    @include('rihis_vct.cell_select', ['r' => $r, 'f' => 'nationality','pending' => $pending,'opts' => App\Models\RihisVct::$nationalityOption])
                    @include('rihis_vct.cell_select', ['r' => $r, 'f' => 'target_group','pending' => $pending,'opts' => App\Models\RihisVct::$targetGroupOption])
                    @include('rihis_vct.cell_select', ['r' => $r, 'f' => 'msm_sub',   'pending' => $pending, 'opts' => App\Models\RihisVct::$msmSubOption])
                    @include('rihis_vct.cell_select', ['r' => $r, 'f' => 'sw_mode',   'pending' => $pending, 'opts' => App\Models\RihisVct::$swModeOption])
                    {{-- ส่งต่อมาจาก: ข้อความจากช่อง "ส่งต่อมาจาก" ของ visit --}}
                    @include('rihis_vct.cell_text',   ['r' => $r, 'f' => 'referral_unit', 'pending' => $pending, 'w' => 'w-lg',
                                                      'ph' => 'ดึงจากช่องส่งต่อมาจากใน visit'])
                    {{-- ประวัติการตรวจ HIV: เป็นข้อความ ไม่ใช่ dropdown (พี่อิ๋วขอ) --}}
                    @include('rihis_vct.cell_text',   ['r' => $r, 'f' => 'past_service','pending' => $pending, 'w' => 'w-lg'])
                    @include('rihis_vct.cell_select', ['r' => $r, 'f' => 'pre_test',  'pending' => $pending, 'opts' => App\Models\RihisVct::$yesNoOption])
                    @include('rihis_vct.cell_select', ['r' => $r, 'f' => 'hiv_test',  'pending' => $pending, 'opts' => App\Models\RihisVct::$yesNoOption])
                    @include('rihis_vct.cell_select', ['r' => $r, 'f' => 'post_test', 'pending' => $pending, 'opts' => App\Models\RihisVct::$postTestOption])
                    {{-- กลับมาฟังผลคนละวันกับวันตรวจ = มาร์กฟ้าอ่อนให้เห็น (พี่อิ๋วขอ) --}}
                    @include('rihis_vct.cell_date',   ['r' => $r, 'f' => 'result_date','pending' => $pending,
                                                      'extra' => ($r->result_date && $r->service_date && $r->result_date !== $r->service_date) ? 'rv-later' : ''])
                    @include('rihis_vct.cell_select', ['r' => $r, 'f' => 'blood_result','pending' => $pending,'opts' => App\Models\RihisVct::$bloodResultOption])
                    @include('rihis_vct.cell_select', ['r' => $r, 'f' => 'test_method','pending' => $pending,'opts' => App\Models\RihisVct::$testMethodOption])
                    @include('rihis_vct.cell_select', ['r' => $r, 'f' => 'refer_other','pending' => $pending,'opts' => App\Models\RihisVct::$sentOption])
                    {{-- ชื่อโรงพยาบาลที่ส่งต่อ (ข้อความอิสระ) — ไปลงคอลัมน์ AV ของฟอร์มส่วนกลางตอนส่งออก --}}
                    @include('rihis_vct.cell_text',   ['r' => $r, 'f' => 'refer_hospital', 'pending' => $pending, 'w' => 'w-lg',
                                                      'ph' => 'เช่น รพ.นครพิงค์'])
                    {{-- เดิมคือ "วันตรวจซ้ำ" พี่อิ๋วขอเปลี่ยนความหมายเป็นวันที่โทรตาม · ย้ายมาต่อจากการส่งต่อตามที่ผู้ใช้ขอ --}}
                    @include('rihis_vct.cell_date',   ['r' => $r, 'f' => 'repeat_date','pending' => $pending])
                    @include('rihis_vct.cell_select', ['r' => $r, 'f' => 'cd4_sent',  'pending' => $pending, 'opts' => App\Models\RihisVct::$sentOption])
                    @include('rihis_vct.cell_text',   ['r' => $r, 'f' => 'cd4',       'pending' => $pending, 'w' => 'w-sm'])
                    @include('rihis_vct.cell_text',   ['r' => $r, 'f' => 'note', 'pending' => $pending, 'w' => 'w-lg',
                                                      'ph' => 'เช่น ส่งต่อไปรับยา ARV / อื่นๆ'])

                    <td class="text-end text-nowrap">
                        {{-- ผลบวก = ต้องส่งไปตรวจ CD4/รักษาต่อ พิมพ์ใบส่งตัวได้จากตรงนี้เลย --}}
                        @if($r->visit_id && (int) $r->blood_result === 2)
                            <a href="{{ route('visit.referForm', ['id' => $r->visit_id]) }}" target="_blank"
                               class="btn btn-outline-primary btn-sm" title="พิมพ์แบบฟอร์มส่งผู้ป่วยรับการรักษาต่อ">
                                <i class="fa fa-print"></i>
                            </a>
                        @endif
                        <button type="button" class="btn btn-outline-danger btn-sm js-rv-del"
                                data-id="{{ $r->id }}" title="ลบแถวนี้"><i class="fa fa-trash"></i></button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center mt-3">
        {{ $rows->links() }}
    </div>
    @endif
</div>

{{-- ฟอร์มลบ วางไว้นอกตาราง ไม่ให้ซ้อนกับฟอร์มอื่น --}}
<form method="POST" action="" id="rv-del-form" class="d-none">
    {{ csrf_field() }}
    {{ method_field('DELETE') }}
    <input type="hidden" name="from" value="{{ $fromTh }}">
    <input type="hidden" name="to" value="{{ $toTh }}">
    <input type="hidden" name="q" value="{{ $q }}">
</form>

{{-- ปฏิทินไทย (พ.ศ.) — ชุดเดียวกับที่หน้าทะเบียนคู่สัมผัสใช้ --}}
<script src="/js/bootstrap-datepicker.js"></script>
<script src="/js/bootstrap-datepicker-thai.js"></script>
<script src="/js/bootstrap-datepicker.th.js"></script>
<script>
$(function () {
    // ช่องกรองช่วงวันที่ + ช่องวันที่ในตาราง (แก้แล้ว autosave ผ่าน event change เดิม)
    $('#rv-page .date').datepicker({
        language: 'th-th', format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true
    });

    // datepicker เปลี่ยนค่าผ่าน jQuery ซึ่ง addEventListener ของ autosave จับไม่ได้
    // จึงต้องยิง event change แบบ native ต่อให้เอง ไม่งั้นเลือกวันแล้วไม่บันทึก
    $('#rv-table').on('changeDate', '.date', function () {
        this.defaultValue = this.value;                       // กัน focusout ยิงซ้ำอีกรอบ
        this.dispatchEvent(new Event('change', { bubbles: true }));
    });
});
</script>
<script>
(function () {
    var URL_SAVE = @json(route('rihis-vct.save'));
    var TOKEN    = @json(csrf_token());
    var DEL_BASE = @json(url('/rihis-vct'));

    function flash(el, ok) {
        el.classList.toggle('rv-saved', ok);
        if (ok) setTimeout(function () { el.classList.remove('rv-saved'); }, 900);
    }

    // ทาสีช่องที่ยังรอกรอกใหม่ทุกครั้งที่บันทึกสำเร็จ (เช่น เปลี่ยนเป็น "ได้ฟังผล" แล้วผลเลือดต้องกรอกต่อ)
    function repaint(tr, pending) {
        tr.querySelectorAll('[data-f]').forEach(function (el) {
            var isPending = pending.indexOf(el.dataset.f) !== -1;
            el.classList.toggle('rv-pending', isPending);
            // ค่า 0 / "ไม่ได้" ทาชมพู แต่ถ้ายังรอกรอกอยู่ให้เหลืองมาก่อน (สำคัญกว่า)
            el.classList.toggle('rv-zero', !isPending && el.tagName === 'SELECT' && el.value === '0');
        });
    }

    function save(el) {
        var tr = el.closest('tr');
        var body = new URLSearchParams({
            _token: TOKEN, id: tr.dataset.id, field: el.dataset.f, value: el.value
        });

        fetch(URL_SAVE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            if (!j || !j.ok) { el.classList.add('is-invalid'); return; }
            el.classList.remove('is-invalid');
            flash(el, true);
            if (j.pending) repaint(tr, j.pending);
            // ระบบเติมวันฟังผลให้เองเมื่อกด "ได้ฟังผล" -> อัปเดตช่องบนจอด้วย
            if (j.row) {
                var rd = tr.querySelector('[data-f="result_date"]');
                if (rd && j.row.result_date !== undefined) rd.value = j.row.result_date;
                var br = tr.querySelector('[data-f="blood_result"]');
                if (br && j.row.blood_result === null) br.value = '';
            }
        })
        .catch(function () { el.classList.add('is-invalid'); });
    }

    // ปุ่มลัดช่วงวันที่ — เติมค่าลงช่องแล้ว submit เลย (วันที่เป็น พ.ศ. แบบ ว/ด/ป)
    var filter = document.getElementById('rv-filter');
    if (filter) {
        var TODAY = @json((now()->year + 543) . now()->format('-m-d'));
        function th(y, m, d) { return d + '/' + m + '/' + y; }
        function lastDay(y, m) { return new Date(y - 543, m, 0).getDate(); }

        filter.addEventListener('click', function (e) {
            var b = e.target.closest('.js-rv-quick');
            if (!b) return;
            var y = +TODAY.slice(0, 4), m = +TODAY.slice(5, 7);
            var f, t;

            if (b.dataset.q === 'thismonth') {
                f = th(y, m, 1); t = th(y, m, lastDay(y, m));
            } else if (b.dataset.q === 'lastmonth') {
                var pm = m - 1, py = y; if (pm === 0) { pm = 12; py--; }
                f = th(py, pm, 1); t = th(py, pm, lastDay(py, pm));
            } else if (b.dataset.q === 'fy') {
                var fy = m >= 10 ? y + 1 : y;                 // ปีงบ = ต.ค. ปีก่อน ถึง ก.ย.
                f = th(fy - 1, 10, 1); t = th(fy, 9, 30);
            } else {                                          // ทั้งหมด
                f = th(2550, 1, 1); t = th(y + 5, 12, 31);
            }

            filter.querySelector('[name=from]').value = f;
            filter.querySelector('[name=to]').value = t;
            // กดปุ่มลัดช่วงวันที่ = อยากดูตามวันที่ ไม่ใช่ผลค้นหา -> ล้างคำค้นให้
            // ไม่งั้นกดแล้วหน้าไม่เปลี่ยน เพราะการค้นหาข้ามช่วงวันที่อยู่
            var qBox = filter.querySelector('[name=q]');
            if (qBox) { qBox.value = ''; }
            filter.submit();
        });

        // เลือกปีงบจากรายการ -> ใส่ช่วง 1 ต.ค. ปีก่อน – 30 ก.ย. แล้วเปิดเลย
        filter.addEventListener('change', function (e) {
            var s = e.target.closest('.js-rv-fy');
            if (!s || !s.value) return;
            var fy = +s.value;
            filter.querySelector('[name=from]').value = th(fy - 1, 10, 1);
            filter.querySelector('[name=to]').value = th(fy, 9, 30);
            var qBox = filter.querySelector('[name=q]');
            if (qBox) { qBox.value = ''; }
            filter.submit();
        });

        // พิมพ์วันที่แล้วกด Enter: ปฏิทินกิน Enter ไว้ปิดตัวเอง ฟอร์มเลยไม่ส่ง
        // หน้าค้างช่วงเดิม (ปีงบปัจจุบัน) ทั้งที่ช่องโชว์วันที่ใหม่ -> ส่งฟอร์มให้เอง
        filter.querySelectorAll('[name=from], [name=to]').forEach(function (inp) {
            inp.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                try { $(inp).datepicker('hide'); } catch (err) {}
                filter.submit();
            });
        });
    }

    var table = document.getElementById('rv-table');
    if (table) {
        table.addEventListener('change', function (e) {
            if (e.target.matches('[data-f]')) save(e.target);
        });
        // ช่องพิมพ์: บันทึกตอนออกจากช่อง จะได้ไม่ยิงทุกตัวอักษร
        table.addEventListener('focusout', function (e) {
            if (e.target.matches('input[data-f]') && e.target.value !== e.target.defaultValue) {
                e.target.defaultValue = e.target.value;
                save(e.target);
            }
        });
        table.addEventListener('click', function (e) {
            var del = e.target.closest('.js-rv-del');
            if (!del) return;
            if (!confirm('ลบแถวนี้ออกจากทะเบียน RIHIS-VCT?')) return;
            var f = document.getElementById('rv-del-form');
            f.action = DEL_BASE + '/' + del.dataset.id;
            f.submit();
        });
    }
})();
</script>
@endsection
