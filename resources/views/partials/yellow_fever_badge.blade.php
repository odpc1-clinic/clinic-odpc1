{{-- ป้าย "ฉีดไข้เหลืองแล้ว · รับรองตลอดชีพ" (TMC ขอ 2569-10-07)
     ตั้งแต่ 11 ก.ค. 2016 (IHR/WHO) ใบรับรองไข้เหลือง 1 เข็มมีผลตลอดชีพ — คนไข้มาขอฉีดซ้ำบ่อย เจ้าหน้าที่ต้องเห็นทันที
     รับ $yfShots = VisitVaccine::yellowFeverShots(...) (เรียงเก่า -> ใหม่) · ว่าง = ไม่แสดงอะไร
     สีระบุเอง (Bootstrap 5.1 ไม่มีคลาส *-subtle) --}}
@if(isset($yfShots) && $yfShots->count())
    @php
        $__yfFirst = $yfShots->first();
        $__yfLast  = $yfShots->last();
    @endphp
    <div class="d-flex align-items-center flex-wrap gap-2 px-3 py-2 mb-2 rounded shadow-sm" role="status"
         style="background:#fef9c3; border:2px solid #eab308; color:#713f12;">
        <i class="fa-solid fa-shield-virus fa-lg"></i>
        <span class="fw-bold" style="font-size:1.05rem;">ฉีดไข้เหลืองแล้ว</span>
        <span>· วันที่ <b>{{ $__yfFirst->vaccinated_date_th ?? 'ไม่ทราบ' }}</b></span>
        <span>· Lot <b>{{ $__yfFirst->lot_number ?: '—' }}</b></span>
        @if((int) $__yfFirst->source === \App\Models\VisitVaccine::HISTORY)
            <span class="badge bg-secondary">ฉีดที่อื่น</span>
        @endif
        <span class="badge" style="background:#15803d;color:#fff;font-size:.85rem;">รับรองตลอดชีพ</span>
        @if($yfShots->count() > 1)
            <span class="small">(มีบันทึก {{ $yfShots->count() }} เข็ม · ล่าสุด {{ $__yfLast->vaccinated_date_th ?? '-' }})</span>
        @endif
    </div>
@endif
