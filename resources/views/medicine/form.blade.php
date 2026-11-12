<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label fw-semibold">ชื่อยา <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="fas fa-pills text-primary"></i></span>
            <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}" name="name" type="text" id="name"
                value="{{ $medicine->name ?? old('name') }}" placeholder="เช่น Paracetamol 500mg" required>
        </div>
        {!! $errors->first('name', '<div class="text-danger small mt-1">:message</div>') !!}
    </div>

    <div class="col-md-3">
        <label for="dose" class="form-label fw-semibold">Dose</label>
        <input class="form-control" name="dose" type="text" id="dose" value="{{ $medicine->dose ?? old('dose') }}" placeholder="เช่น 500mg">
    </div>

    <div class="col-md-3">
        <label for="route" class="form-label fw-semibold">Route</label>
        <input class="form-control" name="route" type="text" id="route" value="{{ $medicine->route ?? old('route') }}" placeholder="เช่น รับประทาน">
    </div>

    <div class="col-md-6">
        <label for="amount" class="form-label fw-semibold">วิธีใช้ / ขนาดที่สั่ง</label>
        <input class="form-control" name="amount" type="text" id="amount" value="{{ $medicine->amount ?? old('amount') }}" placeholder="เช่น 1 เม็ด วันละ 3 ครั้ง">
    </div>

    <div class="col-md-3">
        <label for="unit" class="form-label fw-semibold">หน่วยนับ</label>
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="fas fa-ruler text-secondary"></i></span>
            <input class="form-control" name="unit" type="text" id="unit" value="{{ $medicine->unit ?? old('unit') }}" placeholder="เม็ด / ขวด / ซอง">
        </div>
    </div>

    <div class="col-md-3">
        <label for="price" class="form-label fw-semibold">ราคาขาย/หน่วย (บาท)</label>
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="fas fa-baht-sign text-success"></i></span>
            <input class="form-control {{ $errors->has('price') ? 'is-invalid' : '' }}" name="price" type="number" step="0.01" min="0" id="price"
                value="{{ $medicine->price ?? '0' }}">
        </div>
        {!! $errors->first('price', '<div class="text-danger small mt-1">:message</div>') !!}

        {{-- ตัวช่วยตั้งราคา: โชว์ราคากลาง สคร.1 (ราคาเก็บคนไข้) และราคาอ้างอิง อย. (ราคาจัดซื้อ)
             ไม่เติมค่าให้อัตโนมัติ เพราะราคาเก็บคนไข้ต้องอิงประกาศ ไม่ใช่ราคาจัดซื้อ --}}
        @isset($medicine)
            @if($medicine->name)
                @php
                    $__stdPool  = \App\Models\StandardPrice::active()->whereIn('category', ['medicine', 'vaccine'])->get();
                    [$__std, ] = \App\Models\StandardPrice::findForMedicine($medicine->name, $__stdPool);

                    // คำค้นของ อย.: ตัดวงเล็บ/ขนาดออกให้เหลือชื่อตัวยา
                    $__base = trim(preg_replace('/\(.*?\)|\d+.*/u', '', $medicine->name));
                    $__fda  = mb_strlen($__base) >= 4
                        ? \App\Models\StandardPrice::where('category', 'fda_ref')
                            ->where('name', 'LIKE', '%' . $__base . '%')
                            ->orderBy('price')->limit(3)->get()
                        : collect();
                @endphp

                @if($__std || $__fda->count())
                    <div class="small mt-2 p-2 rounded" style="background:#f8fafc; border:1px solid #e2e8f0;">
                        @if($__std)
                            <div class="text-success">
                                <i class="fas fa-scale-balanced"></i>
                                ราคากลาง สคร.1: <b>{{ number_format((float) $__std->price, 2) }}</b> / {{ $__std->unit }}
                                <span class="text-muted">({{ $__std->name }})</span>
                            </div>
                        @endif
                        @foreach($__fda as $__f)
                            <div class="text-muted">
                                <i class="fas fa-book-medical"></i>
                                อ้างอิง อย. (จัดซื้อ): {{ number_format((float) $__f->price, 2) }} / {{ $__f->unit }}
                                <span style="font-size:.9em;">— {{ \Illuminate\Support\Str::limit($__f->name, 46) }}</span>
                            </div>
                        @endforeach
                        @if($__fda->count())
                            <div class="text-muted" style="font-size:.85em;">ราคา อย. คือราคาจัดซื้อ ไม่ใช่ราคาเก็บคนไข้</div>
                        @endif
                    </div>
                @endif
            @endif
        @endisset
    </div>

    <div class="col-md-4">
        <label for="reorder_point" class="form-label fw-semibold">จุดเตือนสต๊อกต่ำ</label>
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="fas fa-bell text-warning"></i></span>
            <input class="form-control" name="reorder_point" type="number" step="1" min="0" id="reorder_point"
                value="{{ $medicine->reorder_point ?? '0' }}">
        </div>
        <div class="form-text">เตือนเมื่อคงคลังรวมเหลือน้อยกว่าหรือเท่ากับค่านี้</div>

        {{-- เป็นวัคซีน — ติ๊กแล้วไปโผล่ในแท็บฉีดวัคซีน TMC, แผนนัดฉีดวัคซีน และหน้าตารางนัดวัคซีน (2569-09-30)
             เดิมไม่มีช่องนี้ วัคซีนตัวใหม่ที่เพิ่มในคลังยาจึงไม่ขึ้นที่ไหนเลย --}}
        <input type="hidden" name="is_vaccine" value="0">
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="is_vaccine" value="1" id="is_vaccine"
                   {{ ! empty($medicine->is_vaccine) ? 'checked' : '' }}>
            <label class="form-check-label fw-semibold" for="is_vaccine"><i class="fa-solid fa-syringe text-primary me-1"></i> เป็นวัคซีน</label>
        </div>
        @if(! empty($medicine->is_vaccine))
            <div class="form-text">
                ระยะนัดเข็มถัดไป: <b>{{ \App\Services\VaccineSchedule::describe($medicine->vaccine_intervals) }}</b> ·
                <a href="{{ route('vaccine-schedule.index') }}" target="_blank">แก้ที่ตารางนัดวัคซีน</a>
            </div>
        @endif
    </div>

    {{-- จำนวนคงคลัง: บริหารผ่านระบบ Lot ไม่กรอกมือตรงนี้ --}}
    <div class="col-md-8">
        <label class="form-label fw-semibold">จำนวนคงคลัง (Stock)</label>
        @if($formMode === 'edit')
            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border">
                <div>
                    <span class="fs-4 fw-bold {{ ($medicine->reorder_point > 0 && $medicine->totalLotStock() <= $medicine->reorder_point) ? 'text-danger' : 'text-dark' }}">
                        {{ $medicine->totalLotStock() }}
                    </span>
                    <span class="text-muted">{{ $medicine->unit }}</span>
                </div>
                <div class="text-muted small flex-grow-1">
                    <i class="fas fa-circle-info"></i> จำนวนคงคลังคำนวณจาก Lot อัตโนมัติ — เพิ่ม/ปรับสต๊อกได้ที่หน้าคลังยา
                </div>
                <a href="{{ route('medicine-lot.show', $medicine->id) }}" class="btn btn-success btn-sm">
                    <i class="fas fa-boxes-stacked"></i> จัดการ Lot / รับเข้า
                </a>
            </div>
        @else
            <div class="p-3 bg-light rounded border text-muted small">
                <i class="fas fa-circle-info"></i> ยาใหม่จะเริ่มที่คงคลัง <b>0</b> — หลังบันทึกแล้ว ไปเพิ่มสต๊อกเป็น Lot (พร้อมวันหมดอายุ/ราคาทุน) ได้ที่หน้าคลังยา
            </div>
        @endif
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button class="btn btn-primary px-4" type="submit">
        <i class="fas fa-{{ $formMode === 'edit' ? 'save' : 'plus' }} me-1"></i>
        {{ $formMode === 'edit' ? 'บันทึกการแก้ไข' : 'เพิ่มยา' }}
    </button>
    <a href="{{ url('/medicine') }}" class="btn btn-light border">ยกเลิก</a>
</div>
