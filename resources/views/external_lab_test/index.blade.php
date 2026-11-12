@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h5 class="mb-0 fw-bold"><i class="fas fa-vials text-primary me-2"></i>รายการตรวจ Lab นอก</h5>
      <span class="text-muted small">
        ทั้งหมด {{ number_format($total) }} รายการ
        @if ($noPrice) · <span class="text-danger fw-bold">ยังไม่มีราคา {{ $noPrice }} รายการ</span> @endif
      </span>
    </div>
    <div class="card-body">

      @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
      @if (session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
      @if ($errors->any())
        <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
      @endif

      <div class="alert alert-info small d-flex gap-2">
        <i class="fas fa-circle-info mt-1"></i>
        <div>
          ราคาที่ใส่ตรงนี้คือ <b>ราคาตามใบเสนอราคา</b> ใช้เป็นตัวช่วยเตือนตอนสั่งตรวจ
          ส่วนราคาที่เก็บคนไข้จริง พยาบาลยังพิมพ์เองได้ทุกครั้งเหมือนเดิม ·
          <b>เว้นราคาว่างไว้ได้</b> ถ้ายังไม่รู้ราคา — จะไม่ขึ้นบรรทัดราคาตอนสั่ง
        </div>
      </div>

      {{-- เพิ่มรายการใหม่ --}}
      <details class="mb-3">
        <summary class="btn btn-success btn-sm">+ เพิ่มรายการตรวจใหม่</summary>
        <form method="POST" action="{{ route('external-lab-test.store') }}" class="row g-2 mt-2 p-3 border rounded bg-light">
          @csrf
          <div class="col-md-2">
            <label class="form-label small mb-1">ผู้ให้บริการ</label>
            <input name="vendor" class="form-control form-control-sm" value="N Health" required>
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">รหัส</label>
            <input name="code" class="form-control form-control-sm" placeholder="เช่น E011" required>
          </div>
          <div class="col-md-4">
            <label class="form-label small mb-1">ชื่อรายการตรวจ</label>
            <input name="name" class="form-control form-control-sm" required>
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">สิ่งส่งตรวจ</label>
            <input name="specimen" class="form-control form-control-sm" placeholder="ไม่ใส่ก็ได้">
          </div>
          <div class="col-md-1">
            <label class="form-label small mb-1">รอผล</label>
            <input name="turnaround" class="form-control form-control-sm" placeholder="1 day">
          </div>
          <div class="col-md-1">
            <label class="form-label small mb-1">ราคา</label>
            <input name="price" type="number" step="0.01" min="0" class="form-control form-control-sm text-end">
          </div>
          <div class="col-12"><button class="btn btn-success btn-sm">บันทึกรายการใหม่</button></div>
        </form>
      </details>

      <form method="GET" class="row g-2 mb-3">
        <div class="col-auto">
          <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm"
                 placeholder="ค้นหารหัสหรือชื่อ" style="width:260px">
        </div>
        <div class="col-auto"><button class="btn btn-secondary btn-sm">ค้นหา</button></div>
        @if ($q !== '')
          <div class="col-auto"><a href="{{ route('external-lab-test.index') }}" class="btn btn-outline-secondary btn-sm">ล้าง</a></div>
        @endif
      </form>

      <form method="POST" action="{{ route('external-lab-test.save') }}">
        @csrf
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:150px">รหัส / แล็บ</th>
                <th>ชื่อรายการตรวจ</th>
                <th style="width:200px">สิ่งส่งตรวจ</th>
                <th style="width:100px">รอผล</th>
                <th style="width:110px" class="text-end">ราคา</th>
                <th style="width:80px" class="text-center">เปิดใช้</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($rows as $r)
                <tr class="{{ is_null($r->price) ? 'table-warning' : '' }}">
                  <td class="fw-bold small">{{ $r->code }}
                    <input name="rows[{{ $r->id }}][vendor]" value="{{ $r->vendor }}" list="ext-vendors"
                           class="form-control form-control-sm mt-1 fw-normal {{ $r->vendor === 'ยังไม่ระบุแล็บ' ? 'border-danger' : '' }}"
                           title="แล็บที่ส่งตรวจ">
                  </td>
                  <td><input name="rows[{{ $r->id }}][name]" value="{{ $r->name }}" class="form-control form-control-sm"></td>
                  <td><input name="rows[{{ $r->id }}][specimen]" value="{{ $r->specimen }}" class="form-control form-control-sm"></td>
                  <td><input name="rows[{{ $r->id }}][turnaround]" value="{{ $r->turnaround }}" class="form-control form-control-sm"></td>
                  <td><input name="rows[{{ $r->id }}][price]" value="{{ $r->price }}" type="number" step="0.01" min="0"
                             class="form-control form-control-sm text-end" placeholder="ยังไม่รู้"></td>
                  <td class="text-center">
                    <input type="hidden" name="rows[{{ $r->id }}][active]" value="0">
                    <input type="checkbox" name="rows[{{ $r->id }}][active]" value="1"
                           class="form-check-input" {{ ($r->active) ? 'checked' : '' }}>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center">
          <div class="small text-muted">
            แถวสีเหลือง = ยังไม่ได้ใส่ราคา · เอาเครื่องหมายถูกออก = ซ่อนจากรายการตอนสั่งตรวจ (ของเก่าที่สั่งไว้แล้วไม่หาย)
          </div>
          <button class="btn btn-primary">บันทึกทั้งหมด</button>
        </div>
        <datalist id="ext-vendors">
          @foreach ($rows->pluck('vendor')->filter()->unique() as $__v)<option value="{{ $__v }}">@endforeach
        </datalist>
      </form>

      <div class="small text-muted mt-3">
        <b>หมายเหตุ:</b> รายการตั้งต้นมาจากไฟล์ seeder ของระบบ
        ถ้ามีการติดตั้งใหม่แล้วรัน seeder ซ้ำ ค่าที่แก้ในหน้านี้อาจถูกเขียนทับตามรหัสเดิม
        — แจ้งผู้ดูแลระบบให้อัปเดต seeder ด้วยเมื่อแก้ราคาสำคัญ
      </div>

    </div>
  </div>
</div>
@endsection
