{{-- แถบค้นหา Visit (ปรับใหม่ให้สะอาด รองรับจอเล็ก คงชื่อฟิลด์เดิมทุกช่อง) --}}
<style>
    .visit-filter { background:#fff; border:1px solid #e5e7eb; border-radius:14px; }
    .visit-filter label { font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.03em; margin-bottom:2px; }
</style>
<form method="GET" action="{{ route('visit.index') }}" accept-charset="UTF-8" role="search" class="visit-filter p-3 shadow-sm">
    <div class="row g-3 align-items-end">
        {{-- ค้นทั้งฐานที่ฝั่งเซิร์ฟเวอร์ ต่างจากช่อง "ค้นหาในตาราง" ของ DataTables
             ที่ค้นได้เฉพาะแถวที่โหลดมาแล้ว (จำกัด 500 แถว จึงมักไม่เจอคนไข้คลินิกเล็ก) --}}
        <div class="col-lg-3 col-md-6">
            <label for="search">HN / ชื่อ / นามสกุล</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="fa fa-magnifying-glass text-primary"></i></span>
                <input class="form-control" name="search" type="text" id="search"
                       placeholder="พิมพ์ HN หรือชื่อ" value="{{ request()->search }}" autocomplete="off">
                @if(request()->filled('search'))
                    <a class="btn btn-outline-secondary"
                       href="{{ route('visit.index', array_merge(request()->except(['search','page']), [])) }}"
                       title="ล้างคำค้น">&times;</a>
                @endif
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <label>วันที่มารับบริการ (ช่วง)</label>
            <div class="input-group">
                <input class="form-control date js-realtime-date" name="start" type="text" id="start" placeholder="เริ่ม" value="{{ request()->start }}">
                <span class="input-group-text">ถึง</span>
                <input class="form-control date js-realtime-date" name="end" type="text" id="end" placeholder="สิ้นสุด" value="{{ request()->end }}">
            </div>
        </div>

        <div class="col-lg-2 col-md-6">
            <label for="appointment">วันนัด</label>
            <input class="form-control date js-realtime-date" name="appointment" type="text" id="appointment" value="{{ request()->appointment }}">
        </div>

        <div class="col-lg-3 col-md-6">
            <label for="disease">วินิจฉัย</label>
            <select class="form-select js-realtime-submit" name="disease" id="disease">
                <option value="0"> </option>
                @foreach (App\Models\Diagnosi::$diseaseOption as $key => $value)
                    <option value="{{ $key }}" @if ($key == request()->disease) selected="selected" @endif>{{ $value }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-lg-2 col-md-6">
            <label for="diseaseState">สถานะโรค</label>
            <select class="form-select js-realtime-submit" name="diseaseState" id="diseaseState">
                <option value="0"> </option>
                @foreach (App\Models\Diagnosi::$diseaseStateOption as $key => $value)
                    <option value="{{ $key }}" @if ($key == request()->diseaseState) selected="selected" @endif>{{ $value }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-lg-2 col-md-6 d-grid">
            <button class="btn btn-primary fw-bold" type="submit">
                <i class="fa fa-search me-1"></i> ค้นหา
            </button>
        </div>
    </div>
</form>

<script src="/js/bootstrap-datepicker.js"></script>
<script src="/js/bootstrap-datepicker-thai.js"></script>
<script src="/js/bootstrap-datepicker.th.js"></script>
<script type="text/javascript">
    $(function() {
        $('.date').datepicker({
            language: 'th-th',
        });
    });
</script>
@include('partials.realtime-search')
