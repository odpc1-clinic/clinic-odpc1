{{-- ค่าบริการ/หัตถการ — แยกไฟล์ออกมา 2569-09-30 เพราะ TMC วางคนละที่กับคลินิกอื่น
     STI/TB: อยู่ในส่วนการรักษา ต่อจากค่า Lab (ที่เดิม)
     TMC:    อยู่ล่าง ต่อจากโซนฉีดวัคซีน ก่อนวันนัด (พยาบาลขอให้อยู่ด้วยกัน)
     ป้าย "แพ็กเกจ" เดิมใช้ bg-info-subtle ซึ่ง Bootstrap 5.1 ไม่มี (ขึ้นเป็นตัวหนังสือล่องหน) จึงใส่สีเอง --}}
{{-- ค่าบริการ/หัตถการ: แพทย์/พยาบาลติ๊กเลือก แล้วไหลเข้าบิลห้องยาอัตโนมัติ (ต่อจากการเพิ่มยา) --}}
@isset($serviceMaster)
<div class="card mb-3 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2" style="background:#6f42c1; color:#fff;">
        <span><i class="fa fa-hand-holding-medical"></i> ค่าบริการ / หัตถการ <span class="small opacity-75">(ติ๊กที่ทำจริง + แก้ราคาได้ → ส่งเข้าบิลอัตโนมัติ)</span></span>
        <span class="small"><i class="fas fa-hand-holding-heart"></i> ติ๊ก "ยกเว้น" รายตัว = ขอความอนุเคราะห์ (แพ็กเกจยกเว้นไม่ได้)</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:60px;" class="text-center">เลือก</th>
                    <th>รายการ</th>
                    <th class="text-end" style="width:130px;">ราคา/หน่วย</th>
                    <th class="text-center" style="width:100px;">จำนวน</th>
                    <th class="text-center" style="width:110px;">ยกเว้น</th>
                </tr>
            </thead>
            <tbody>
            @forelse($serviceMaster as $svc)
                @php
                    $svcChecked = isset($savedServiceQtys[$svc->id]);
                    // รายการที่ไม่ใช้กับคลินิกนี้ (เช่น แพ็กเกจ STD ในหน้า TMC) ซ่อนไว้
                    // ยกเว้นที่ติ๊กไว้แล้วใน visit นี้ ต้องยังแสดง ไม่งั้นกด Save แล้วรายการในบิลจะหายไป
                    if (! $svc->availableFor((int) ($visit->clinic ?? 1)) && ! $svcChecked) { continue; }
                    $svcWaived = !empty($savedServiceWaived[$svc->id]);
                @endphp
                <tr class="svc-row">
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input" style="transform:scale(1.4);" name="services[{{ $svc->id }}][use]" value="1" {{ $svcChecked ? 'checked' : '' }}>
                        <input type="hidden" name="services[{{ $svc->id }}][name]" value="{{ $svc->name }}">
                    </td>
                    <td>
                        {{ $svc->name }}
                        @if($svc->is_package)<span class="badge ms-1" style="background:#cff4fc; color:#055160;">แพ็กเกจ</span>@endif
                    </td>
                    <td class="text-end">
                        {{-- แก้ราคาได้ก่อนกดสั่ง (ค่าเริ่มต้น = ราคามาตรฐาน) --}}
                        <input type="number" class="form-control form-control-sm text-end svc-price-input" name="services[{{ $svc->id }}][price]" value="{{ $savedServicePrices[$svc->id] ?? $svc->price }}" min="0" step="0.01" style="max-width:120px; display:inline-block;">
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm text-center" name="services[{{ $svc->id }}][qty]" value="{{ $savedServiceQtys[$svc->id] ?? 1 }}" min="1" step="1">
                    </td>
                    <td class="text-center">
                        @if($svc->is_package)
                            <span class="text-muted small" title="แพ็กเกจเหมาจ่าย ยกเว้นไม่ได้">— คิดเสมอ —</span>
                        @else
                            <input type="hidden" name="services[{{ $svc->id }}][waive]" value="0">
                            <input type="checkbox" class="form-check-input svc-waive" style="transform:scale(1.4);" name="services[{{ $svc->id }}][waive]" value="1" title="ขอความอนุเคราะห์ (ไม่คิดรายการนี้)" {{ $svcWaived ? 'checked' : '' }}>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-2">ยังไม่มีรายการค่าบริการ</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<script>
    (function () {
        function syncRow(cb) {
            var row = cb.closest('tr');
            if (!row) return;
            var price = row.querySelector('.svc-price-input');
            if (price) {
                price.style.textDecoration = cb.checked ? 'line-through' : '';
                price.style.opacity = cb.checked ? '0.5' : '';
            }
        }
        document.querySelectorAll('.svc-waive').forEach(function (cb) {
            cb.addEventListener('change', function () { syncRow(cb); });
            syncRow(cb);
        });
    })();
</script>
@endisset
