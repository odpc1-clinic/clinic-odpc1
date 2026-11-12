@extends('layouts.app') @section('content')
<div class="container-fluid">
    <div class="row mb-4 align-items-center">
        <div class="col-md-5">
            <h3 class="text-primary"><i class="fa fa-medkit"></i> คิวห้องยา (จ่ายยา · เก็บเงิน)</h3>
            <p class="text-muted">
                รายการผู้ป่วยรอรับยา/ชำระเงิน ประจำวันที่:
                <strong class="text-dark">{{ \Carbon\Carbon::parse($filterDate)->addYears(543)->format('d/m/Y') }}</strong>
            </p>
        </div>
        <div class="col-md-7 text-right">
            {{-- ค้นหาแบบไม่ต้องกดปุ่ม: เลือกวันแล้วค้นทันที พิมพ์แล้วกรองให้เอง (พี่กานต์ขอ 2569-09-11)
                 ยังเป็น <form> อยู่ เผื่อ JS ไม่ทำงานจะได้กด Enter ค้นแบบเดิมได้ --}}
            <form method="GET" action="{{ url('/pharmacy') }}" class="form-inline float-right" id="queue-filter">
                <div class="input-group">
                    <input type="date" class="form-control" name="filter_date" id="f-date" value="{{ $filterDate }}" title="เลือกวันที่ต้องการดูคิว">

                    <input type="text" class="form-control" name="search" id="f-search" autocomplete="off"
                           placeholder="ค้นหา HN, ชื่อ, นามสกุล, ปชช..." value="{{ request('search') }}">

                    <span class="input-group-btn">
                        <button class="btn btn-secondary" type="button" id="f-clear" title="ล้างคำค้นหา">
                            <i class="fa fa-times"></i>
                        </button>
                        <span class="input-group-text bg-white border-0 ps-2" id="f-status" style="min-width:74px; font-size:.85rem;"></span>
                    </span>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">

        <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%) !important; border-radius: 5px 5px 0 0;">
    <h5 class="card-title mb-0" style="color: #ffffff !important;">
        <i class="fa fa-list-alt text-white"></i> คิวรอรับยา ({{ $visits->total() }} คิว)
    </h5>
    <span class="badge badge-pill text-dark" style="background-color: #ffc107; font-size: 0.9rem;" id="auto-refresh-badge" title="ระบบดึงข้อมูลอัตโนมัติทุก 10 วินาที">
        <i class="fa fa-sync fa-spin me-1"></i> อัปเดตข้อมูลอัตโนมัติ (10s)
    </span>
</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th class="text-center" style="width: 8%;">คิวที่</th>
                            <th style="width: 12%;">เวลาลงทะเบียน</th>
            <th width="110">HN</th>
            <th>ชื่อ-นามสกุล</th>
            <th>รายการยาที่สั่ง</th>
            <th width="260" class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($visits as $item)
                            <tr>
                                <td class="text-center align-middle">
                                    <span style="background-color: #ff0000 !important; color: #ffffff !important; font-size: 18px; padding: 8px 16px; border-radius: 20px; font-weight: bold; display: inline-block; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
    {{ $item->queue ?? '-' }}
</span>
								 </td>
                                <td class="align-middle">
                                    {{ \Carbon\Carbon::parse($item->created_at)->format('H:i') }} น.
                                </td>
                                <td class="align-middle fw-bold text-info">
                                    {{ $item->patient2->code ?? 'ไม่มีข้อมูล' }}
                                </td>
                                <td class="align-middle">
    {{ $item->patient2->prefix_name }}{{ $item->patient2->name ?? '' }} {{ $item->patient2->surname ?? '' }}
    {{-- ห้องยารับคนไข้ทุกคลินิก จึงบอกต้นทางไว้ --}}
    @include('partials.clinic-badge', ['clinic' => $item->clinic])
</td>
{{-- ส่วนแสดงรายการยา --}}
<td>
    @if($item->visitMedicines && $item->visitMedicines->count() > 0)
        @foreach($item->visitMedicines as $vm)
            <span class="badge badge-light border text-dark mb-1">
                {{ $vm->medicine->name ?? 'ไม่ทราบชื่อ' }}
                {{-- บังคับให้เป็น float ก่อนเข้า number_format --}}
                <strong class="text-primary">x {{ number_format((float)$vm->amount, 0) }}</strong>
                @if($vm->sigText() !== '')
                    <span class="d-block text-start fw-normal" style="color:#0f766e; white-space:normal;">{{ $vm->sigText() }}</span>
                @endif
            </span>
        @endforeach
    @else
        <span class="text-muted small">--- ไม่มีรายการยา ---</span>
    @endif
</td>
                            <td class="text-center">
    <div class="btn-group">
        <form action="{{ route('pharmacy.callQueue', $item->id) }}" method="POST" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-dark btn-sm" title="เรียกคิวนี้ที่ห้องยา (ห้อง 10)">
                <i class="fa fa-bullhorn"></i> เรียกคิว
            </button>
        </form>
        <a href="{{ url('/pharmacy/'.$item->id.'/edit') }}" class="btn btn-primary btn-sm">
            <i class="fa fa-calculator"></i> จ่ายยา&คิดเงิน
        </a>
        {{-- ปุ่มลัด: ปิดเคสโดยไม่เปิดหน้าจ่ายยา (ไม่ตัดสต๊อก) เช่น คนไข้ไม่มียาให้รับ --}}
        <form action="{{ url('/pharmacy/'.$item->id.'/status') }}" method="POST" style="display:inline;">
            @csrf
            <input type="hidden" name="status" value="6">
            <button type="submit" class="btn btn-success btn-sm" title="ปิดเคสโดยไม่ตัดสต๊อก (ไม่มียาให้รับ)">
                <i class="fa fa-home"></i> กลับบ้าน
            </button>
        </form>
    </div>
</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa fa-check-circle fa-4x text-success mb-3 opacity-50"></i>
                                    <h5>ไม่มีคิวผู้ป่วยตกค้างในห้องยาของวันที่เลือก</h5>
                                    <p class="text-muted small">ลองเปลี่ยนวันที่หรือคำค้นหาด้านบน</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{-- กล่องนี้ต้องมีอยู่เสมอ แม้ยังไม่มีหลายหน้า
             เพราะตอนค้นแบบ AJAX เราแทนที่เฉพาะข้างในกล่อง ถ้าไม่มีกล่องไว้ก่อน
             พอเปลี่ยนไปวันที่มีคิวเยอะ ปุ่มเปลี่ยนหน้าจะไม่โผล่จนกว่าจะโหลดหน้าใหม่ --}}
        <div class="card-footer bg-white border-top-0 {{ $visits->hasPages() ? '' : 'd-none' }}" id="pager-box">
            <div class="pagination-wrapper float-right">
                @if($visits->hasPages())
                    {!! $visits->appends([
                        'search' => request('search'),
                        'filter_date' => $filterDate
                    ])->render() !!}
                @endif
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function () {
    var BASE = @json(url('/pharmacy'));
    var timer = null;       // debounce ตอนพิมพ์
    var req   = null;       // ยกเลิก request เก่าถ้ามีอันใหม่มา
    var auto  = null;

    function status(text, spin) {
        $('#f-status').html(text ? (spin ? '<i class="fa fa-spinner fa-spin me-1"></i>' : '') + text : '');
    }

    // ประกอบ URL จากช่องกรอกปัจจุบัน (page ส่งมาเฉพาะตอนกดเปลี่ยนหน้า)
    function buildUrl(page) {
        var p = [];
        var d = $('#f-date').val();
        var s = $.trim($('#f-search').val());
        if (d) { p.push('filter_date=' + encodeURIComponent(d)); }
        p.push('search=' + encodeURIComponent(s));
        if (page && page > 1) { p.push('page=' + page); }
        return BASE + '?' + p.join('&');
    }

    // โหลดตารางใหม่ — ดึงทั้งหน้าแล้วหยิบเฉพาะส่วนที่เปลี่ยน (วิธีเดียวกับตัวรีเฟรชอัตโนมัติเดิม)
    function load(page, opts) {
        opts = opts || {};
        var url = buildUrl(page);

        if (req) { req.abort(); }
        if (!opts.quiet) { status('กำลังค้นหา', true); }

        req = $.ajax({ url: url, type: 'GET', cache: false })
            .done(function (html) {
                var $d = $('<div>').html(html);
                $('tbody').html($d.find('tbody').html());
                $('.card-title').html($d.find('.card-title').html());

                // เปลี่ยนหน้า: ซ่อน/โชว์กล่องตามว่าผลลัพธ์รอบนี้มีหลายหน้าไหม
                var $newPager = $d.find('.pagination-wrapper');
                $('.pagination-wrapper').html($newPager.html());
                $('#pager-box').toggleClass('d-none', $.trim($newPager.html() || '') === '');

                // ให้ปุ่ม back/refresh ของเบราว์เซอร์ยังใช้ได้ตรงกับที่เห็นอยู่
                if (!opts.quiet && window.history && history.replaceState) {
                    history.replaceState(null, '', url);
                }
                if (!opts.quiet) {
                    var n = ($('tbody tr').length === 1 && $('tbody td[colspan]').length) ? 0 : $('tbody tr').length;
                    status(n ? 'พบ ' + n + ' คิว' : 'ไม่พบ');
                    setTimeout(function () { status(''); }, 2500);
                }
            })
            .fail(function (x) {
                if (x.statusText === 'abort') { return; }
                status('ค้นไม่สำเร็จ');
            });
    }

    // เลือกวัน -> ค้นทันที
    $('#f-date').on('change', function () { load(1); });

    // พิมพ์ -> หน่วงสักครู่ค่อยค้น จะได้ไม่ยิงทุกตัวอักษร
    $('#f-search').on('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { load(1); }, 400);
    });

    // กด Enter ในช่องค้นหา -> ค้นเลย ไม่ต้องรอหน่วง และไม่ให้ฟอร์ม submit ทั้งหน้า
    $('#queue-filter').on('submit', function (e) { e.preventDefault(); clearTimeout(timer); load(1); });

    $('#f-clear').on('click', function () { $('#f-search').val(''); load(1); });

    // เปลี่ยนหน้าแบบไม่ต้องโหลดทั้งหน้า
    $(document).on('click', '.pagination-wrapper a', function (e) {
        e.preventDefault();
        var m = /[?&]page=(\d+)/.exec($(this).attr('href') || '');
        load(m ? m[1] : 1);
    });

    // รีเฟรชอัตโนมัติทุก 10 วิ — เงียบ ๆ ไม่รบกวนตอนกำลังพิมพ์/เลือกวัน
    // ต่อ Reverb ติด: คนไข้เข้า/ออกห้องยา ขึ้นจอทันทีจากสัญญาณ รอบนี้เหลือไว้เก็บตกทุก 30 วิ
    var tick = 0;
    function quietReload() {
        if ($('#f-search').is(':focus') || $('#f-date').is(':focus')) { return; }
        var m = /[?&]page=(\d+)/.exec(window.location.search);
        load(m ? m[1] : 1, { quiet: true });
    }
    auto = setInterval(function () {
        if (window.ClinicRT && ClinicRT.connected && (++tick % 3)) { return; }
        quietReload();
    }, 10000);
    var rtWait = null;
    ClinicRT.on('visit', function (d) {
        if (d.from !== 5 && d.to !== 5) { return; }
        clearTimeout(rtWait);
        rtWait = setTimeout(quietReload, 800);
    });
});
</script>

@endsection
