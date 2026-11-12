@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
        <h4 class="fw-bold mb-0"><i class="fa-solid fa-magnifying-glass-chart me-2"></i> ดึงรายชื่อคนไข้เอง</h4>
        <span class="text-muted small">เลือกเงื่อนไขแล้วกดค้นหา · ดาวน์โหลดเป็น Excel ส่งต่อได้เลย</span>
    </div>
    <p class="text-muted">ไม่ต้องรอให้ใครเขียนรายงานให้ — เลือกจากช่องด้านล่างได้เอง</p>

    <form method="GET" action="{{ route('report.explore') }}" class="card shadow-sm mb-4">
        <div class="card-body">
            {{-- ค้นจากผลตรวจ Lab (เดิม) หรือจากการวินิจฉัยโรค (เพิ่ม 2569-10-01) — เลือกจากตัวเลือกเท่านั้น ไม่เปิดให้พิมพ์เงื่อนไขเอง --}}
            <div class="btn-group mb-3" role="group" aria-label="ค้นหาจาก">
                @foreach($modes as $k => $v)
                    <input type="radio" class="btn-check js-explore-mode" name="mode" id="mode-{{ $k }}" value="{{ $k }}" {{ $filters['mode'] === $k ? 'checked' : '' }}>
                    <label class="btn btn-outline-primary" for="mode-{{ $k }}">
                        <i class="fa {{ $k === 'dx' ? 'fa-stethoscope' : 'fa-vial' }} me-1"></i> ค้นจาก{{ $v }}
                    </label>
                @endforeach
            </div>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <label class="form-label fw-semibold small">ตั้งแต่วันที่</label>
                    <input type="date" name="from" class="form-control"
                           value="{{ \Carbon\Carbon::parse($filters['from'])->subYears(543)->format('Y-m-d') }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label fw-semibold small">ถึงวันที่</label>
                    <input type="date" name="to" class="form-control"
                           value="{{ \Carbon\Carbon::parse($filters['to'])->subYears(543)->format('Y-m-d') }}">
                </div>

                {{-- ช่องของโหมดวินิจฉัย --}}
                <div class="col-12 col-md-3 js-mode-dx {{ $filters['mode'] === 'dx' ? '' : 'd-none' }}">
                    <label class="form-label fw-semibold small">การวินิจฉัย</label>
                    <select name="disease" class="form-select">
                        <option value="">ทุกโรค</option>
                        @foreach($diseases as $k => $v)
                            <option value="{{ $k }}" {{ (string)$filters['disease'] === (string)$k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 js-mode-dx {{ $filters['mode'] === 'dx' ? '' : 'd-none' }}">
                    <label class="form-label fw-semibold small">นับแบบไหน</label>
                    <select name="case" class="form-select">
                        @foreach($cases as $k => $v)
                            <option value="{{ $k }}" {{ $filters['case'] === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-3 js-mode-lab {{ $filters['mode'] === 'lab' ? '' : 'd-none' }}">
                    <label class="form-label fw-semibold small">รายการตรวจ</label>
                    <select name="method" class="form-select">
                        <option value="">ทุกรายการตรวจ</option>
                        @foreach($methods as $k => $v)
                            @if($v)
                            <option value="{{ $k }}" {{ (string)$filters['method'] === (string)$k ? 'selected' : '' }}>{{ $v }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 js-mode-lab {{ $filters['mode'] === 'lab' ? '' : 'd-none' }}">
                    <label class="form-label fw-semibold small">ผลตรวจ</label>
                    <select name="result" class="form-select">
                        @foreach($results as $k => $v)
                            <option value="{{ $k }}" {{ (string)$filters['result'] === (string)$k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label fw-semibold small">คลินิก</label>
                    <select name="clinic" class="form-select">
                        @foreach($clinics as $k => $v)
                            <option value="{{ $k }}" {{ (string)$filters['clinic'] === (string)$k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label fw-semibold small">เพศ</label>
                    <select name="sex" class="form-select">
                        @foreach($sexes as $k => $v)
                            <option value="{{ $k }}" {{ (string)$filters['sex'] === (string)$k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold small">กลุ่มเป้าหมาย</label>
                    <select name="target" class="form-select">
                        <option value="">ทุกกลุ่ม</option>
                        @foreach($targets as $k => $v)
                            @if($v)
                            <option value="{{ $k }}" {{ (string)$filters['target'] === (string)$k ? 'selected' : '' }}>{{ $v }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label fw-semibold small">สัญชาติ</label>
                    <select name="nation" class="form-select">
                        @foreach($nations as $k => $v)
                            <option value="{{ $k }}" {{ (string)$filters['nation'] === (string)$k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label fw-semibold small">อายุ (ปี)</label>
                    <div class="input-group">
                        <input type="number" name="age_min" class="form-control" placeholder="ตั้งแต่" min="0" max="120" value="{{ $filters['age_min'] }}">
                        <span class="input-group-text">–</span>
                        <input type="number" name="age_max" class="form-control" placeholder="ถึง" min="0" max="120" value="{{ $filters['age_max'] }}">
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3 flex-wrap">
                <a href="{{ route('report.explore') }}" class="btn btn-outline-secondary">
                    <i class="fa fa-rotate-left"></i> ล้างเงื่อนไข
                </a>
                <button type="submit" name="run" value="1" class="btn btn-primary px-4">
                    <i class="fa fa-magnifying-glass"></i> ค้นหา
                </button>
                @if($ran && $rows->count())
                <button type="submit" name="run" value="1" class="btn btn-success px-4"
                        formaction="{{ route('report.explore.export') }}">
                    <i class="fa fa-file-excel"></i> ดาวน์โหลด Excel
                </button>
                @endif
            </div>
        </div>
    </form>

    @if($ran)
        @php $ctl = new \App\Http\Controllers\ReportExploreController(); @endphp

        <div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <strong><i class="fa fa-filter me-1"></i> เงื่อนไข:</strong>
                <span class="text-muted">{{ $ctl->describe($filters) }}</span>
            </div>
            <span class="badge bg-primary" style="font-size:1rem;">พบ {{ number_format($rows->count()) }}{{ $rows->count() >= 2000 ? '+' : '' }} รายการ</span>
        </div>

        {{-- สรุปยอด — นับจากเงื่อนไขทั้งหมด ไม่จำกัด 2,000 แถวแบบตาราง --}}
        @if($summary && $summary['total']['people'])
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 text-center mb-2">
                    <div class="col-6 col-md-3"><div class="small text-muted">จำนวนคน (ไม่ซ้ำ)</div><div class="fs-3 fw-bold">{{ number_format($summary['total']['people']) }}</div></div>
                    <div class="col-6 col-md-3"><div class="small text-muted">จำนวนครั้งที่มา (visit)</div><div class="fs-3 fw-bold">{{ number_format($summary['total']['visits']) }}</div></div>
                    <div class="col-6 col-md-3"><div class="small text-muted">ชาย</div><div class="fs-3 fw-bold">{{ number_format($summary['total']['male']) }}</div></div>
                    <div class="col-6 col-md-3"><div class="small text-muted">หญิง</div><div class="fs-3 fw-bold">{{ number_format($summary['total']['female']) }}</div></div>
                </div>
                @if(count($summary['byYear']) > 1)
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered text-center mb-1" style="max-width:640px;">
                            <thead class="table-light"><tr><th>ปีงบประมาณ</th><th>คน (ไม่ซ้ำ)</th><th>ครั้ง</th><th>ชาย</th><th>หญิง</th></tr></thead>
                            <tbody>
                            @foreach($summary['byYear'] as $y => $s)
                                <tr><td>{{ $y }}</td><td class="fw-bold">{{ $s['people'] }}</td><td>{{ $s['visits'] }}</td><td>{{ $s['male'] }}</td><td>{{ $s['female'] }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <div class="small text-muted">
                    คนไม่ซ้ำของแต่ละปีบวกกันไม่ได้ เพราะคนเดียวกันมาได้หลายปี (ยอดรวมด้านบนนับคนครั้งเดียว) ·
                    ปีงบประมาณ = 1 ต.ค. – 30 ก.ย.
                    @if($filters['mode'] === 'dx' && $filters['case'] === 'new')
                        · นับเฉพาะใบวินิจฉัยที่ลงสถานะ "ผู้ป่วยใหม่" หรือ "เกิดโรคซ้ำ"
                    @endif
                </div>
            </div>
        </div>
        @endif

        @if($rows->isEmpty())
            <div class="card shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    <i class="fa fa-folder-open fa-3x mb-3 opacity-50"></i>
                    <h5>ไม่พบข้อมูลตามเงื่อนไขนี้</h5>
                    <p class="small mb-0">ลองขยายช่วงวันที่ หรือเปลี่ยนผลตรวจเป็น "ทั้งหมด"</p>
                </div>
            </div>
        @else
            @if($rows->count() >= 2000)
            <div class="alert alert-warning small">
                <i class="fa fa-triangle-exclamation"></i> แสดงบนหน้าจอได้สูงสุด 2,000 รายการ —
                ถ้าต้องการครบให้กดดาวน์โหลด Excel (ได้ถึง 10,000 รายการ) หรือแคบช่วงวันที่ลง
            </div>
            @endif

            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px;">#</th>
                                <th style="width:110px;">วันที่</th>
                                <th style="width:110px;">HN</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th style="width:70px;">เพศ</th>
                                <th style="width:70px;">อายุ</th>
                                <th style="width:110px;">สัญชาติ</th>
                                <th style="width:120px;">โทรศัพท์</th>
                                <th>{{ $filters['mode'] === 'dx' ? 'การวินิจฉัย' : 'รายการตรวจ' }}</th>
                                <th style="width:{{ $filters['mode'] === 'dx' ? '200' : '120' }}px;">{{ $filters['mode'] === 'dx' ? 'สถานะ' : 'ผล' }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($rows as $i => $r)
                            <tr>
                                <td class="text-muted">{{ $i + 1 }}</td>
                                <td>{{ $ctl->beDate($r->visit_date) }}</td>
                                <td class="fw-bold text-info">
                                    <a href="{{ url('/visit/'.$r->visit_id.'/edit?page=1') }}" target="_blank" class="text-decoration-none">
                                        {{ $r->hn ?: '-' }}
                                    </a>
                                </td>
                                <td>{{ trim($r->name.' '.$r->surname) ?: '-' }}</td>
                                <td>{{ $sexes[$r->sex] ?? '-' }}</td>
                                <td>{{ $ctl->age($r->birth_date, $r->visit_date) }}</td>
                                <td>{{ $r->nationality ?: '-' }}</td>
                                <td>{{ $r->tel ?: '-' }}</td>
                                @if($filters['mode'] === 'dx')
                                <td>{{ $ctl->dxText($r) }}</td>
                                <td class="small">{{ $dxStates[$r->disease_state] ?? '-' }}</td>
                                @else
                                <td>{{ $methods[$r->method] ?? '-' }}</td>
                                <td>
                                    @if(trim((string)$r->result) === '')
                                        <span class="badge bg-light text-muted border">ยังไม่มีผล</span>
                                    @elseif(strcasecmp(trim($r->result), 'Positive') === 0)
                                        <span class="badge bg-danger">{{ $r->result }}</span>
                                    @elseif(strcasecmp(trim($r->result), 'Negative') === 0)
                                        <span class="badge bg-success">{{ $r->result }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ mb_substr($r->result, 0, 30) }}</span>
                                    @endif
                                    @unless($r->approve_by)
                                        <span class="badge bg-warning text-dark ms-1" title="ห้อง Lab ยังไม่อนุมัติผลนี้">รออนุมัติ</span>
                                    @endunless
                                </td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @else
        <div class="card shadow-sm">
            <div class="card-body text-muted py-5 text-center">
                <i class="fa fa-hand-pointer fa-3x mb-3 opacity-50"></i>
                <h5>เลือกเงื่อนไขด้านบนแล้วกด "ค้นหา"</h5>
                <p class="small mb-0">
                    ตัวอย่างที่ใช้บ่อย: รายการตรวจ = HBs Ag · ผลตรวจ = Positive เท่านั้น · ช่วงวันที่ = ปีงบนี้<br>
                    ยอดผู้ป่วยโรค: ค้นจากการวินิจฉัยโรค · การวินิจฉัย = Gonorrhea · นับแบบ ผู้ป่วยใหม่ + ติดเชื้อซ้ำ · ช่วง 3 ปี → ได้ยอดแยกปีงบประมาณ
                </p>
            </div>
        </div>
    @endif

    <script>
    // สลับช่องตามโหมด (หน้านี้โหลดเต็ม สคริปต์ทำงานได้) — ช่องของอีกโหมดซ่อนไว้ เซิร์ฟเวอร์ไม่ใช้อยู่แล้ว
    document.querySelectorAll('.js-explore-mode').forEach(function (r) {
        r.addEventListener('change', function () {
            var dx = document.getElementById('mode-dx').checked;
            document.querySelectorAll('.js-mode-dx').forEach(function (el) { el.classList.toggle('d-none', !dx); });
            document.querySelectorAll('.js-mode-lab').forEach(function (el) { el.classList.toggle('d-none', dx); });
        });
    });
    </script>

    <div class="alert alert-light border mt-4 small mb-0">
        <i class="fa fa-circle-info me-1"></i>
        คลิกที่ HN เพื่อเปิดหน้าตรวจของครั้งนั้นในแท็บใหม่ ·
        ผลที่ห้อง Lab ยังไม่อนุมัติจะมีป้าย "รออนุมัติ" กำกับไว้ ให้ระวังก่อนนำไปใช้ ·
        รายชื่อนี้เป็นข้อมูลคนไข้ กรุณาอย่าส่งต่อออกนอกหน่วยงาน
    </div>
</div>
@endsection
