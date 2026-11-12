@extends('layouts.app')

@php
    $thMonths = \App\Utillity::TH_MONTH_SHORT;
    $typeBadge = [
        'annual'       => 'bg-secondary',
        'substitution' => 'bg-info text-dark',
        'special'      => 'bg-warning text-dark',
        'gov'          => 'bg-light text-dark border',
    ];
    $clashTotal = array_sum($clash);
@endphp

@section('content')
<div class="container-fluid py-4">
  <div class="row justify-content-center">
    <div class="col-xxl-10">

      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
          <h5 class="mb-0 fw-bold">
            <i class="fa-solid fa-calendar-xmark text-danger me-2"></i>วันหยุดราชการ
          </h5>
          <div class="d-flex align-items-center gap-2">
            <form method="GET" action="{{ route('holiday.index') }}" class="d-flex align-items-center gap-2 mb-0">
              <label class="text-muted small mb-0">ปี พ.ศ.</label>
              <select name="year" class="form-select form-select-sm" style="width:auto;"
                      onchange="this.form.submit()">
                @foreach ($years as $y)
                  <option value="{{ $y }}" {{ ($y == $year) ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
                @if (!in_array($year, $years))
                  <option value="{{ $year }}" selected>{{ $year }}</option>
                @endif
              </select>
            </form>
            <a href="{{ route('visit.appointment') }}" class="btn btn-outline-secondary btn-sm">กลับ</a>
          </div>
        </div>

        <div class="card-body">

          <div class="alert alert-info d-flex align-items-start gap-2 py-2">
            <i class="fa-solid fa-circle-info mt-1"></i>
            <div class="small">
              วันที่อยู่ในรายการนี้และ<b>ล็อกไว้</b> จะ<b>กดเลือกไม่ได้ในปฏิทินนัดหมาย</b>
              และระบบจะ<b>ไม่ส่ง LINE เตือน</b>ให้คนไข้มาในวันนั้น<br>
              เสาร์-อาทิตย์ถูกล็อกอยู่แล้วโดยอัตโนมัติ ไม่ต้องเพิ่มในรายการนี้
            </div>
          </div>

          @if ($clashTotal > 0)
            <div class="alert alert-danger d-flex align-items-start gap-2 py-2">
              <i class="fa-solid fa-triangle-exclamation mt-1"></i>
              <div class="small">
                <b>มีนัดค้างอยู่ในวันหยุด {{ $clashTotal }} ราย</b> —
                นัดพวกนี้ลงไว้ก่อนที่จะมีการล็อกวัน กรุณาเลื่อนนัดให้คนไข้
                (ดูแถวที่ขึ้นตัวเลขสีแดงในตารางข้างล่าง)
              </div>
            </div>
          @endif

          {{-- เพิ่มวันหยุดใหม่ --}}
          <form method="POST" action="{{ route('holiday.store') }}" class="row g-2 mb-4 p-3 bg-light rounded border">
            @csrf
            <div class="col-12"><b><i class="fa-solid fa-plus"></i> เพิ่มวันหยุด</b></div>
            <div class="col-md-2">
              <input type="text" name="holiday_date" required autocomplete="off"
                     class="form-control date @error('holiday_date') is-invalid @enderror"
                     placeholder="วว/ดด/พ.ศ." value="{{ old('holiday_date') }}">
              @error('holiday_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
              <input type="text" name="name" required maxlength="255"
                     class="form-control @error('name') is-invalid @enderror"
                     placeholder="ชื่อวันหยุด เช่น วันสงกรานต์" value="{{ old('name') }}">
              @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
              <select name="type" class="form-select">
                @foreach ($types as $key => $label)
                  <option value="{{ $key }}" {{ (old('type') === $key) ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3">
              <input type="text" name="note" maxlength="255" class="form-control"
                     placeholder="หมายเหตุ (ไม่บังคับ)" value="{{ old('note') }}">
            </div>
            <div class="col-md-1 d-grid">
              <button type="submit" class="btn btn-success">เพิ่ม</button>
            </div>
          </form>

          <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:46px;" class="text-center">#</th>
                  <th style="width:130px;">วันที่</th>
                  <th style="width:90px;">วัน</th>
                  <th>ชื่อวันหยุด</th>
                  <th style="width:170px;">ประเภท</th>
                  <th>หมายเหตุ</th>
                  <th style="width:110px;" class="text-center">สถานะ</th>
                  <th style="width:150px;" class="text-center">จัดการ</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($holidays as $i => $h)
                  @php
                    [$hy, $hm, $hd] = explode('-', $h->holiday_date);
                    $n = $clash[$h->holiday_date] ?? 0;
                  @endphp
                  <tr class="{{ $h->is_closed ? '' : 'table-light text-muted' }}">
                    <td class="text-center text-muted">{{ $i + 1 }}</td>
                    <td class="fw-semibold">
                      {{ (int) $hd }} {{ $thMonths[(int) $hm] }} {{ $hy }}
                    </td>
                    <td>{{ $h->thai_day }}</td>
                    <td>
                      {{ $h->name }}
                      @if ($n > 0)
                        <a href="{{ route('visit.appointment', ['start' => "$hd/$hm/$hy", 'end' => "$hd/$hm/$hy"]) }}"
                           class="badge bg-danger text-decoration-none ms-1"
                           title="มีนัดค้างอยู่ในวันนี้ กดเพื่อไปดูรายชื่อ">
                          <i class="fa-solid fa-triangle-exclamation"></i> มีนัด {{ $n }} ราย
                        </a>
                      @endif
                    </td>
                    <td><span class="badge {{ $typeBadge[$h->type] ?? 'bg-secondary' }}">{{ $h->type_label }}</span></td>
                    <td class="small text-muted">{{ $h->note }}</td>
                    <td class="text-center">
                      @if ($h->is_closed)
                        <span class="badge bg-danger"><i class="fa-solid fa-lock"></i> ล็อก</span>
                      @else
                        <span class="badge bg-light text-dark border"><i class="fa-solid fa-lock-open"></i> นัดได้</span>
                      @endif
                    </td>
                    <td class="text-center">
                      <button type="button" class="btn btn-outline-primary btn-sm"
                              data-bs-toggle="modal" data-bs-target="#edit-{{ $h->id }}" title="แก้ไข">
                        <i class="fa-solid fa-pen"></i>
                      </button>
                      <form method="POST" action="{{ route('holiday.toggle', $h->id) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm"
                                title="{{ $h->is_closed ? 'เปิดให้นัดวันนี้ได้' : 'ล็อกไม่ให้นัดวันนี้' }}">
                          <i class="fa-solid {{ $h->is_closed ? 'fa-lock-open' : 'fa-lock' }}"></i>
                        </button>
                      </form>
                      <form method="POST" action="{{ route('holiday.destroy', $h->id) }}" class="d-inline"
                            onsubmit="return confirm('ลบ {{ $h->name }} ออกจากรายการวันหยุด?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm" title="ลบ">
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                      ยังไม่มีวันหยุดของปี {{ $year }} — เพิ่มได้จากช่องด้านบน
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

        </div>
      </div>

    </div>
  </div>
</div>

{{-- modal แก้ไข แยกออกมานอกตาราง ไม่งั้น Bootstrap จะวางซ้อนผิดตำแหน่ง --}}
@foreach ($holidays as $h)
  <div class="modal fade" id="edit-{{ $h->id }}" tabindex="-1">
    <div class="modal-dialog">
      <form method="POST" action="{{ route('holiday.update', $h->id) }}" class="modal-content">
        @csrf @method('PUT')
        <div class="modal-header">
          <h6 class="modal-title fw-bold">แก้ไขวันหยุด</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label small">วันที่ (พ.ศ.)</label>
            @php [$ey, $em, $ed] = explode('-', $h->holiday_date); @endphp
            <input type="text" name="holiday_date" required autocomplete="off"
                   class="form-control date" value="{{ $ed }}/{{ $em }}/{{ $ey }}">
          </div>
          <div class="mb-2">
            <label class="form-label small">ชื่อวันหยุด</label>
            <input type="text" name="name" required maxlength="255" class="form-control" value="{{ $h->name }}">
          </div>
          <div class="mb-2">
            <label class="form-label small">ประเภท</label>
            <select name="type" class="form-select">
              @foreach ($types as $key => $label)
                <option value="{{ $key }}" {{ ($h->type === $key) ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label small">หมายเหตุ</label>
            <input type="text" name="note" maxlength="255" class="form-control" value="{{ $h->note }}">
          </div>
          <div class="form-check">
            <input type="hidden" name="is_closed" value="0">
            <input class="form-check-input" type="checkbox" name="is_closed" value="1"
                   id="closed-{{ $h->id }}" {{ ($h->is_closed) ? 'checked' : '' }}>
            <label class="form-check-label" for="closed-{{ $h->id }}">
              คลินิกปิดวันนี้ (ล็อกไม่ให้นัด)
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary">บันทึก</button>
        </div>
      </form>
    </div>
  </div>
@endforeach
@endsection

@push('scripts')
<script src="/js/bootstrap-datepicker.js"></script>
<script src="/js/bootstrap-datepicker-thai.js"></script>
<script src="/js/bootstrap-datepicker.th.js"></script>
<script>
    $(function () {
        // หน้านี้เป็นที่ "ตั้ง" วันหยุด จึงต้องเลือกวันหยุดได้ — ไม่ผูก holiday-lock เข้าไป
        $('.date').datepicker({ language: 'th-th', autoclose: true });
    });
</script>
@endpush
