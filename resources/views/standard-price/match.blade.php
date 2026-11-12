@extends('layouts.app')

@section('content')
@php
    $matched   = $rows->filter(fn ($r) => $r->match);
    $safe      = $matched->filter(fn ($r) => $r->doseSame);
    $review    = $matched->filter(fn ($r) => !$r->doseSame);
    $noMatch   = $rows->filter(fn ($r) => !$r->match);
    $noPrice   = $rows->filter(fn ($r) => !$r->hasPrice);
@endphp

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-1"><i class="fas fa-link me-2 text-primary"></i>จับคู่ราคากลาง → ราคายาในระบบ</h4>
            <div class="text-muted small">
                ราคาที่ลงแล้วจะขึ้นให้อัตโนมัติในหน้าคิดเงิน (พยาบาล/การเงินยังแก้รายครั้งได้)
            </div>
        </div>
        <a href="{{ route('standard-price.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> กลับหน้าราคากลาง
        </a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3">
            <div class="text-muted small">ยาในระบบ</div><div class="fs-4 fw-bold">{{ $rows->count() }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3">
            <div class="text-muted small">ยังไม่มีราคา</div><div class="fs-4 fw-bold text-danger">{{ $noPrice->count() }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3">
            <div class="text-muted small">จับคู่ได้ ขนาดตรงกัน</div><div class="fs-4 fw-bold text-success">{{ $safe->count() }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3">
            <div class="text-muted small">ต้องตรวจก่อน</div><div class="fs-4 fw-bold text-warning">{{ $review->count() }}</div></div></div></div>
    </div>

    <div class="alert alert-info">
        <i class="fas fa-circle-info me-1"></i>
        ระบบ<strong>ไม่ลงราคาให้เอง</strong> — ติ๊กเฉพาะรายการที่ตรวจแล้วว่าถูกต้อง
        แถวสีเหลืองคือชื่อตรงแต่<strong>ขนาดยาไม่ตรงกับประกาศ</strong> (เช่น ในระบบ 200 mg แต่ประกาศมีแค่ 400 mg) กรุณาตรวจก่อนติ๊ก<br>
        ยาที่<strong>ไม่มีในประกาศ</strong>ก็ติ๊กแล้วกรอกราคาเองได้ในช่องขวาสุด (มีราคาจัดซื้อของ อย. ไว้ให้ดูประกอบ)
    </div>

    <div class="d-flex gap-2 mb-3 flex-wrap">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="filter-all">ทั้งหมด ({{ $rows->count() }})</button>
        <button type="button" class="btn btn-outline-danger btn-sm" id="filter-noprice">เฉพาะที่ยังไม่มีราคา ({{ $noPrice->count() }})</button>
    </div>

    <form method="POST" action="{{ route('standard-price.applyMedicines') }}">
        @csrf
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:44px;" class="text-center">
                                <input type="checkbox" id="check-safe" title="ติ๊กเฉพาะรายการที่ขนาดตรงกัน">
                            </th>
                            <th>ยาในระบบ</th>
                            <th style="width:110px;" class="text-end">ราคาปัจจุบัน</th>
                            <th>รายการในประกาศ</th>
                            <th style="width:130px;">ราคากลางที่จะลง</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($rows as $r)
                        <tr class="{{ $r->match && !$r->doseSame ? 'table-warning' : '' }}" data-haspric="{{ $r->hasPrice ? 1 : 0 }}">
                            <td class="text-center">
                                {{-- ให้ติ๊กได้ทุกแถว รวมยาที่ไม่มีในประกาศ (กรอกราคาเองได้) --}}
                                <input type="checkbox" class="js-pick {{ $r->match && $r->doseSame ? 'js-safe' : '' }}"
                                       data-target="price-{{ $r->medicine->id }}">
                            </td>
                            <td>
                                {{ $r->medicine->name }}
                                @unless($r->hasPrice)
                                    <span class="badge bg-danger ms-1">ยังไม่มีราคา</span>
                                @endunless
                            </td>
                            <td class="text-end {{ $r->hasPrice ? '' : 'text-muted' }}">
                                {{ number_format((float) $r->medicine->price, 2) }}
                            </td>
                            <td class="small">
                                @if($r->match)
                                    {{ $r->match->name }}
                                    <span class="text-muted">· {{ $r->match->unit }}</span>
                                    @if($r->isAlias)
                                        <span class="badge bg-light text-muted border ms-1" title="จับคู่จากตารางชื่อพ้องที่ตั้งไว้">ชื่อพ้อง</span>
                                    @endif
                                    @unless($r->doseSame)
                                        <div class="text-danger">⚠ ขนาด/รูปแบบยาไม่ตรงกัน — ตรวจก่อนใช้</div>
                                    @endunless
                                @else
                                    <span class="text-muted">— ไม่มีในประกาศ สคร.1 —</span>
                                @endif

                                {{-- ราคา อย. เป็นราคาจัดซื้อ ใช้ประกอบการตั้งราคาเท่านั้น --}}
                                @foreach($r->fda as $f)
                                    <div class="text-muted" style="font-size:.8rem;">
                                        <i class="fas fa-book-medical"></i>
                                        อ้างอิง อย. {{ number_format((float) $f->price, 2) }} / {{ $f->unit }}
                                        — {{ \Illuminate\Support\Str::limit($f->name, 40) }}
                                    </div>
                                @endforeach
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0"
                                       class="form-control form-control-sm text-end"
                                       id="price-{{ $r->medicine->id }}"
                                       data-suggest="{{ $r->match ? (float) $r->match->price : '' }}"
                                       placeholder="{{ $r->match ? '' : 'กรอกราคาเอง' }}"
                                       disabled>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> ลงราคาตามที่ติ๊ก
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    // ติ๊กแถวไหน ช่องราคาแถวนั้นถึงจะถูกส่ง (ใส่ name ตอนติ๊ก) — กันลงราคาโดยไม่ตั้งใจ
    function sync(cb) {
        var box = document.getElementById(cb.dataset.target);
        if (!box) return;
        if (cb.checked) {
            box.disabled = false;
            if (box.value === '') { box.value = box.dataset.suggest; }
            box.name = 'apply[' + cb.dataset.target.replace('price-', '') + ']';
        } else {
            box.disabled = true;
            box.removeAttribute('name');
        }
    }

    document.addEventListener('change', function (e) {
        if (e.target.matches('.js-pick')) { sync(e.target); return; }

        if (e.target.matches('#check-safe')) {
            document.querySelectorAll('.js-safe').forEach(function (cb) {
                cb.checked = e.target.checked;
                sync(cb);
            });
        }
    });

    // กรองให้เหลือเฉพาะยาที่ยังไม่มีราคา จะได้ไล่กรอกทีเดียวจบ
    function filter(onlyNoPrice) {
        document.querySelectorAll('tbody tr[data-haspric]').forEach(function (tr) {
            tr.style.display = (onlyNoPrice && tr.dataset.haspric === '1') ? 'none' : '';
        });
    }
    document.addEventListener('click', function (e) {
        if (e.target.matches('#filter-noprice')) filter(true);
        if (e.target.matches('#filter-all'))     filter(false);
    });
})();
</script>
@endsection
