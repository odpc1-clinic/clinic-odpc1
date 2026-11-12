@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-1"><i class="fas fa-scale-balanced me-2 text-primary"></i>ราคากลาง (อัตราค่าบริการสาธารณสุข)</h4>
            <div class="text-muted small">
                ตามประกาศ สคร.1 เชียงใหม่ · ใช้เป็นราคาอ้างอิง — ราคาที่เก็บจริงหน้างานปรับได้
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('standard-price.match') }}" class="btn btn-outline-primary">
                <i class="fas fa-link"></i> จับคู่ราคาเข้ายาในระบบ
            </a>
            <a href="{{ route('medicine.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-pills"></i> คลังยา
            </a>
        </div>
    </div>

    {{-- ตัวกรอง --}}
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <select name="category" class="form-select" onchange="this.form.submit()">
                <option value="">— ราคากลาง สคร.1 (ทุกหมวด) —</option>
                @foreach(\App\Models\StandardPrice::$categories as $k => $label)
                    <option value="{{ $k }}" {{ $category === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-5">
            <input type="text" name="search" value="{{ $keyword }}" class="form-control" placeholder="ค้นหาชื่อรายการ...">
        </div>
        <div class="col-md-3 d-grid">
            <button class="btn btn-primary"><i class="fas fa-search"></i> ค้นหา</button>
        </div>
    </form>

    @if($category === 'fda_ref')
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation me-1"></i>
            <strong>ราคาชุดนี้เป็น "ราคาจัดซื้อ" ของ อย. ไม่ใช่ราคาที่เก็บจากคนไข้</strong> —
            ใช้ดูประกอบตอนตั้งราคาเท่านั้น ราคาเก็บคนไข้ต้องอิงประกาศอัตราค่าบริการของ สคร.1
            (จากที่เทียบ ราคาเก็บคนไข้สูงกว่าราคาจัดซื้อประมาณ 2–4 เท่า)
        </div>
    @endif

    <form method="POST" action="{{ route('standard-price.update') }}">
        @csrf
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>หมวด</th>
                            <th>รายการ</th>
                            <th style="width:90px;">หน่วย</th>
                            <th style="width:130px;">ราคา (คนไทย)</th>
                            <th style="width:140px;">ราคา (ต่างชาติ)</th>
                            <th style="width:80px;" class="text-center">ใช้งาน</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($prices as $p)
                        <tr>
                            <td class="small text-muted text-nowrap">
                                {{ \App\Models\StandardPrice::$categories[$p->category] ?? $p->category }}
                            </td>
                            <td>
                                {{ $p->name }}
                                @if($p->effective_date)
                                    <div class="small text-muted">ประกาศ {{ $p->effective_date }}</div>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $p->unit }}</td>
                            <td>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end"
                                       name="rows[{{ $p->id }}][price]" value="{{ (float) $p->price }}">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end"
                                       name="rows[{{ $p->id }}][price_foreign]"
                                       value="{{ $p->price_foreign !== null ? (float) $p->price_foreign : '' }}"
                                       placeholder="—">
                            </td>
                            <td class="text-center">
                                <input type="checkbox" name="rows[{{ $p->id }}][active]" value="1" {{ $p->active ? 'checked' : '' }}>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">ไม่พบรายการ</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
            <div>{{ $prices->links() }}</div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> บันทึกราคาในหน้านี้
            </button>
        </div>
    </form>

    {{-- เพิ่มรายการใหม่ --}}
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white fw-bold"><i class="fas fa-plus me-1"></i> เพิ่มรายการราคากลาง</div>
        <div class="card-body">
            <form method="POST" action="{{ route('standard-price.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">หมวด</label>
                    <select name="category" class="form-select form-select-sm" required>
                        @foreach(\App\Models\StandardPrice::$categories as $k => $label)
                            <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">ชื่อรายการ</label>
                    <input type="text" name="name" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">หน่วยนับ</label>
                    <input type="text" name="unit" class="form-control form-control-sm" placeholder="เม็ด / Test / ครั้ง">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">ราคา (คนไทย)</label>
                    <input type="number" step="0.01" min="0" name="price" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label small text-muted mb-1">ต่างชาติ</label>
                    <input type="number" step="0.01" min="0" name="price_foreign" class="form-control form-control-sm">
                </div>
                <div class="col-md-1 d-grid">
                    <button class="btn btn-success btn-sm"><i class="fas fa-plus"></i> เพิ่ม</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
