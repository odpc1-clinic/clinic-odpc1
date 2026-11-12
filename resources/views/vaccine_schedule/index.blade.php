@extends('layouts.app')

{{-- ตารางระยะนัดวัคซีน — ระบบใช้นัดเข็มถัดไปให้เอง (คลินิก TMC 2569-09-30) --}}
@section('content')
<div class="container py-3" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0"><i class="fa-solid fa-syringe me-1"></i> ตารางนัดวัคซีน</h4>
            <div class="small text-muted">หมอสั่งวัคซีนในหน้าสั่งยา → ระบบนับว่าเป็นเข็มที่เท่าไหร่จากประวัติฉีด แล้วใส่นัดเข็มถัดไปลงแผนนัดฉีดวัคซีนให้</div>
        </div>
    </div>

    <div class="alert alert-light border small">
        <b>วิธีเขียน:</b> ระยะของแต่ละเข็ม <b>นับจากวันฉีดเข็มแรก</b> คั่นด้วยจุลภาค ·
        หน่วย <code>d</code> วัน · <code>w</code> สัปดาห์ · <code>m</code> เดือน · <code>y</code> ปี<br>
        ตัวอย่าง <code>0, 1m, 6m</code> = เข็ม 1 วันนี้ · เข็ม 2 อีก 1 เดือน · เข็ม 3 ครบ 6 เดือนจากเข็มแรก ·
        <code>0</code> = เข็มเดียว ไม่นัดต่อ · เว้นว่าง = ไม่นัดอัตโนมัติ<br>
        วันนัดที่ตรงเสาร์-อาทิตย์หรือวันหยุดราชการ ระบบเลื่อนไปวันถัดไปที่คลินิกเปิดให้เอง
    </div>

    <form method="POST" action="{{ route('vaccine-schedule.save') }}" autocomplete="off">
        @csrf
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>วัคซีน</th>
                            <th style="width:13rem;">ระยะแต่ละเข็ม</th>
                            <th style="width:15rem;">อ่านว่า</th>
                            <th style="width:17rem;">หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($vaccines as $v)
                        {{-- วัคซีนตัวใหม่ (ติ๊ก "เป็นวัคซีน" ในคลังยา) ขึ้นที่นี่เอง แต่ยังไม่มีระยะ = ยังไม่นัดอัตโนมัติ — ทำสีแดงให้เห็น --}}
                        @php $__noSpec = trim((string) $v->vaccine_intervals) === ''; @endphp
                        <tr class="{{ $__noSpec ? 'table-danger' : ($v->vaccine_schedule_note && str_contains($v->vaccine_schedule_note, 'ยังไม่ยืนยัน') ? 'table-warning' : '') }}">
                            <td>{{ $v->name }}
                                @if($__noSpec)<div class="small text-danger">ยังไม่ได้ตั้งระยะ — ยังไม่นัดเข็มถัดไปให้</div>@endif
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm js-vs-spec" name="v[{{ $v->id }}][intervals]"
                                       value="{{ $v->vaccine_intervals }}" placeholder="เช่น 0, 1m, 6m" maxlength="100">
                            </td>
                            <td class="small text-muted js-vs-read">{{ \App\Services\VaccineSchedule::describe($v->vaccine_intervals) }}</td>
                            <td>
                                <input type="text" class="form-control form-control-sm" name="v[{{ $v->id }}][note]"
                                       value="{{ $v->vaccine_schedule_note }}" maxlength="255">
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex gap-2 align-items-center">
                <button class="btn btn-primary"><i class="fa fa-save"></i> บันทึกตาราง</button>
                <span class="small text-muted">แถวสีเหลือง = ค่าตั้งต้นที่ยังไม่ได้ยืนยัน แก้แล้วลบคำว่า "ยังไม่ยืนยัน" ในหมายเหตุออกได้เลย ·
                    แถวสีแดง = วัคซีนตัวใหม่ที่ยังไม่ได้ตั้งระยะ · เพิ่มวัคซีนใหม่: คลังยา → ติ๊ก "เป็นวัคซีน"</span>
            </div>
        </div>
    </form>
</div>
<script>
(function () {
    // อ่านสูตรสด ๆ ตอนพิมพ์ — ตรงกับ VaccineSchedule::describe()
    var U = { d: 'วัน', w: 'สัปดาห์', m: 'เดือน', y: 'ปี' };
    function describe(s) {
        var toks = (s || '').trim().split(/[,\s]+/).filter(Boolean);
        if (!toks.length) return '—';
        var out = [];
        for (var i = 0; i < toks.length; i++) {
            var m = toks[i].match(/^(\d{1,3})([dwmy]?)$/i);
            if (!m) return '⚠ รูปแบบไม่ถูก';
            out.push(i === 0 ? 'เข็ม 1' : '+' + m[1] + ' ' + U[(m[2] || 'd').toLowerCase()]);
        }
        return toks.length === 1 ? 'เข็มเดียว' : out.join(' → ');
    }
    document.addEventListener('input', function (e) {
        if (!e.target.classList.contains('js-vs-spec')) return;
        e.target.closest('tr').querySelector('.js-vs-read').textContent = describe(e.target.value);
    });
})();
</script>
@endsection
