@extends('layouts.app')

{{-- แคตตาล็อกแล็บรวม (2569-10-02) — ผู้ดูแล / หัวหน้า Lab
     ดูตามหมวด · ค้นหา · เปิด-ปิด · แก้ชื่อ/รหัส/หมวด/ราคาเก็บคนไข้ (เฉพาะแล็บนอก)/คลินิกที่เห็น/ลำดับ
     ฟอร์มบันทึกส่งเฉพาะแถวที่แก้ (สคริปต์ท้ายหน้าปิดแถวที่ไม่เปลี่ยน) — สองคนแก้คนละแถวพร้อมกันไม่ทับกัน --}}
@section('content')
<div class="container-fluid py-3">
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h5 class="mb-0 fw-bold"><i class="fas fa-flask text-primary me-2"></i>แคตตาล็อกแล็บรวม</h5>
      @if($ready)
        <div class="d-flex align-items-center gap-2">
          <span class="text-muted small">แสดง {{ number_format($total) }} รายการ</span>
          <form method="POST" action="{{ route('lab-catalog.sync') }}" class="d-inline">
            @csrf
            <button class="btn btn-outline-primary btn-sm" title="เพิ่มเฉพาะรายการที่ยังไม่มี เช่น แล็บนอกที่เพิ่งเพิ่ม — ของที่แก้ไว้ไม่ถูกทับ">
              <i class="fas fa-rotate me-1"></i> เติมรายการใหม่จากแหล่งเดิม
            </button>
          </form>
        </div>
      @endif
    </div>
    <div class="card-body">
      @if(! $ready)
        <div class="alert alert-warning mb-0">
          ยังไม่ได้เปิดใช้แคตตาล็อกแล็บ — ผู้ดูแลระบบต้องรัน migration
          <code>2026_10_02_300000_create_lab_catalog_items_table</code> แล้ว <code>php artisan lab-catalog:sync</code> ก่อน
          ระหว่างนี้ทุกหน้าสั่งแล็บใช้แบบเดิม
        </div>
      @else
        @if ($errors->any())
          <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        @endif

        <div class="alert alert-light border small mb-3">
          <i class="fas fa-circle-info me-1 text-primary"></i>
          แคตตาล็อกนี้คือ "เมนู" ของปุ่ม <b>สั่งแล็บ</b> — ของที่สั่งยังลงที่เดิมทั้งหมด
          (<span class="badge" style="background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7;">ในคลินิก</span> = ห้อง Lab ·
           <span class="badge" style="background:#eef2ff;color:#3730a3;border:1px solid #a5b4fc;">ส่งนอก</span> = Lab ส่งตรวจภายนอก ·
           <span class="badge" style="background:#fff7ed;color:#9a3412;border:1px solid #fdba74;">ส่งตรวจวัณโรค</span> = ช่องส่งตรวจในใบคัดกรอง TB)
          <br>ราคาแล็บในคลินิกมาจาก <a href="{{ route('lab-price.index') }}">ตั้งราคากลาง Lab</a> (บิลคิดจากที่นั่น) ·
          ราคาแล็บนอกในหน้านี้ = <b>ราคาเก็บคนไข้ตั้งต้น</b> (เว้นว่าง = พยาบาลกรอกเองเหมือนเดิม) ส่วนราคาใบเสนอราคาแก้ที่
          <a href="{{ route('external-lab-test.index') }}">รายการ Lab นอก</a>
        </div>

        <form method="GET" class="row g-2 mb-3 align-items-end">
          <div class="col-auto">
            <input type="search" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="ค้นหาชื่อหรือรหัส" style="width:220px">
          </div>
          <div class="col-auto">
            <select name="category" class="form-select form-select-sm">
              <option value="">ทุกหมวด</option>
              @foreach(\App\Models\LabCatalogItem::CATEGORIES as $k => $l)
                <option value="{{ $k }}" {{ ($filters['cat'] === $k) ? 'selected' : '' }}>{{ $l }} ({{ (int) optional($counts->get($k))->n }})</option>
              @endforeach
            </select>
          </div>
          <div class="col-auto">
            <select name="kind" class="form-select form-select-sm">
              <option value="">ทุกชนิด</option>
              @foreach(\App\Models\LabCatalogItem::KIND_LABELS as $k => $l)
                <option value="{{ $k }}" {{ ($filters['kind'] === $k) ? 'selected' : '' }}>{{ $l }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-auto">
            <select name="clinic" class="form-select form-select-sm">
              <option value="0">ทุกคลินิก</option>
              @foreach(\App\Models\LabCatalogItem::$clinicLabels as $k => $l)
                <option value="{{ $k }}" {{ ($filters['clinic'] === $k) ? 'selected' : '' }}>เห็นใน {{ $l }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-auto">
            <select name="show" class="form-select form-select-sm">
              <option value="all" {{ ($filters['show'] === 'all') ? 'selected' : '' }}>เปิด + ปิด</option>
              <option value="active" {{ ($filters['show'] === 'active') ? 'selected' : '' }}>เฉพาะที่เปิดใช้</option>
              <option value="inactive" {{ ($filters['show'] === 'inactive') ? 'selected' : '' }}>เฉพาะที่ปิด</option>
            </select>
          </div>
          <div class="col-auto"><button class="btn btn-secondary btn-sm">กรอง</button></div>
          <div class="col-auto"><a href="{{ route('lab-catalog.index') }}" class="btn btn-outline-secondary btn-sm">ล้าง</a></div>
        </form>

        <form method="POST" action="{{ route('lab-catalog.save', array_filter(['q' => $filters['q'], 'category' => $filters['cat'], 'kind' => $filters['kind'], 'clinic' => $filters['clinic'] ?: null, 'show' => $filters['show'] !== 'all' ? $filters['show'] : null])) }}" id="lcat-admin-form">
          @csrf
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light">
                <tr>
                  <th style="width:60px;">เปิดใช้</th>
                  <th style="min-width:240px;">ชื่อรายการ</th>
                  <th style="width:100px;">รหัส</th>
                  <th style="width:120px;">ชนิด</th>
                  <th style="width:200px;">หมวด</th>
                  <th style="width:200px;">ราคา</th>
                  <th style="width:150px;">คลินิกที่เห็น</th>
                  <th style="width:80px;">ลำดับ</th>
                </tr>
              </thead>
              <tbody>
              @forelse($grouped as $catKey => $items)
                <tr class="table-secondary"><td colspan="8" class="fw-bold small">{{ \App\Models\LabCatalogItem::CATEGORIES[$catKey] ?? $catKey }} · {{ $items->count() }} รายการ</td></tr>
                @foreach($items as $it)
                  @php
                    $pi = $it->priceInfo($labPrices, $it->externalTest);
                    $cl = array_map('intval', (array) ($it->clinics ?? [1, 2, 3]));
                    $p  = 'rows[' . $it->id . ']';
                  @endphp
                  <tr class="js-lcat-row {{ $it->active ? '' : 'text-muted' }}" style="{{ $it->active ? '' : 'background:#f8fafc;' }}">
                    <td class="text-center">
                      <input type="checkbox" class="form-check-input" name="{{ $p }}[active]" value="1" {{ ($it->active) ? 'checked' : '' }}>
                    </td>
                    <td><input type="text" class="form-control form-control-sm" name="{{ $p }}[name]" value="{{ $it->name }}" maxlength="255" required>
                      @if($it->kind === 'in_house')
                        <div class="small text-muted">วิธีตรวจ #{{ $it->lab_method }} ใน lab_subs</div>
                      @elseif($it->kind === 'external')
                        <div class="small text-muted">{{ optional($it->externalTest)->vendor }} · {{ optional($it->externalTest)->specimen }}</div>
                      @else
                        <div class="small text-muted">ช่องส่งตรวจวัณโรค: {{ $it->tb_key }}</div>
                      @endif
                    </td>
                    <td><input type="text" class="form-control form-control-sm" name="{{ $p }}[code]" value="{{ $it->code }}" maxlength="50"></td>
                    <td>
                      <span class="badge" style="{{ $it->kind === 'in_house' ? 'background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7;' : ($it->kind === 'external' ? 'background:#eef2ff;color:#3730a3;border:1px solid #a5b4fc;' : 'background:#fff7ed;color:#9a3412;border:1px solid #fdba74;') }}">{{ $it->kindLabel() }}</span>
                    </td>
                    <td>
                      <select class="form-select form-select-sm" name="{{ $p }}[category]">
                        @foreach(\App\Models\LabCatalogItem::CATEGORIES as $k => $l)
                          <option value="{{ $k }}" {{ ($it->category === $k) ? 'selected' : '' }}>{{ $l }}</option>
                        @endforeach
                      </select>
                    </td>
                    <td>
                      @if($it->kind === 'external')
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="{{ $p }}[default_price]"
                               value="{{ $it->default_price }}" placeholder="ว่าง = กรอกเอง">
                        <div class="small text-muted">ใบเสนอราคา {{ $pi['ref'] !== null ? number_format($pi['ref'], 0) . '.-' : '—' }}</div>
                      @else
                        <div class="small">{{ $pi['price'] !== null ? number_format($pi['price'], 0) . '.-' : '' }} <span class="text-muted">{{ $pi['note'] }}</span></div>
                      @endif
                      <input type="text" class="form-control form-control-sm mt-1" name="{{ $p }}[price_note]" value="{{ $it->price_note }}" maxlength="255" placeholder="หมายเหตุราคา (ไม่ใส่ก็ได้)">
                    </td>
                    <td>
                      @foreach(\App\Models\LabCatalogItem::$clinicLabels as $ck => $cn)
                        <label class="me-2 small text-nowrap"><input type="checkbox" class="form-check-input" name="{{ $p }}[clinics][]" value="{{ $ck }}" {{ (in_array($ck, $cl, true)) ? 'checked' : '' }}> {{ $cn }}</label>
                      @endforeach
                    </td>
                    <td><input type="number" class="form-control form-control-sm" name="{{ $p }}[sort]" value="{{ $it->sort }}"></td>
                  </tr>
                @endforeach
              @empty
                <tr><td colspan="8" class="text-center text-muted py-4">ไม่พบรายการ</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
          <div class="d-flex justify-content-end gap-2 align-items-center position-sticky bottom-0 bg-white py-2 border-top">
            <span class="small text-muted js-lcat-changed">ยังไม่มีการแก้ไข</span>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> บันทึกเฉพาะแถวที่แก้</button>
          </div>
        </form>
      @endif
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('lcat-admin-form');
    if (!form) return;
    function val(tr) {
        var out = [];
        tr.querySelectorAll('input, select').forEach(function (el) {
            if (!el.name) return;
            if (el.type === 'checkbox') { out.push(el.name + '=' + (el.checked ? el.value : '')); return; }
            out.push(el.name + '=' + el.value);
        });
        return out.join('&');
    }
    var rows = form.querySelectorAll('tr.js-lcat-row');
    rows.forEach(function (tr) { tr.__orig = val(tr); });
    function changed() { return Array.prototype.filter.call(rows, function (tr) { return val(tr) !== tr.__orig; }); }
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    function update() {
        var n = changed().length;
        form.querySelector('.js-lcat-changed').textContent = n ? ('แก้ไขแล้ว ' + n + ' แถว (ยังไม่บันทึก)') : 'ยังไม่มีการแก้ไข';
        rows.forEach(function (tr) { tr.style.outline = val(tr) !== tr.__orig ? '2px solid #f59e0b' : ''; });
    }
    // ส่งเฉพาะแถวที่แก้ — แถวอื่นปิดไว้ไม่ให้ถูกส่ง (คนอื่นแก้แถวอื่นพร้อมกันได้ ไม่ทับกัน)
    form.addEventListener('submit', function (e) {
        var c = changed();
        if (!c.length) { e.preventDefault(); alert('ยังไม่มีแถวที่แก้'); return; }
        rows.forEach(function (tr) {
            if (c.indexOf(tr) === -1) tr.querySelectorAll('input, select').forEach(function (el) { el.disabled = true; });
        });
    });
    window.addEventListener('beforeunload', function (e) { if (changed().length && !form.__sending) { e.preventDefault(); e.returnValue = ''; } });
    form.addEventListener('submit', function () { form.__sending = true; });
})();
</script>
@endpush
