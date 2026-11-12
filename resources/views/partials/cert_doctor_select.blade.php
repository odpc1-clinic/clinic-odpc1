{{--
    เลือกแพทย์ผู้ตรวจบนใบรับรองแพทย์ (คลินิกขอ 2569-10-06) — ไม่พิมพ์ออก (no-print)
    รายชื่อ = User::physicians() (นายแพทย์ / แพทย์หญิง) · เลือกแล้วเติมชื่อแพทย์ ชื่อใต้ลายเซ็น และเลขใบอนุญาต
    แพทย์ที่ระบบจดไว้ใน visit ไม่ใช่แพทย์ (เช่นพยาบาลที่มีสิทธิ์หน้าหมอ) = ล้างชื่อแล้วเตือนให้เลือก ไม่พิมพ์ชื่อผิดคนออกไป
--}}
@php
    $__phys = \App\Models\User::physicians();
    $__physIds = \App\Models\User::whereIn('id', $__phys->pluck('id'))->get(['id', 'name', 'license_number'])->keyBy('id');
    $__curId = (int) ($visit->doctor_by ?? 0);
    $__curIsPhys = $__phys->contains('id', $__curId);
    $__opts = $__phys->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'license' => (string) ($__physIds[$u->id]->license_number ?? '')])->values();
@endphp
{{-- body ของหน้าใบรับรองเป็น flex แนวนอน — แถบนี้ต้องเต็มแถวบนสุด ไม่งั้นไปเบียดใบรับรองจนเหลือคอลัมน์แคบ
     และหน้าใบรับรองไม่ได้ประกาศคลาส .no-print ไว้ จึงต้องซ่อนตอนพิมพ์เอง (บั๊ก 2569-10-29) --}}
<style>
    body { flex-wrap: wrap; }
    #cert-doc-bar { flex: 0 0 100%; box-sizing: border-box; }
    @media print { #cert-doc-bar { display: none !important; } }
</style>
<div class="no-print" id="cert-doc-bar" style="position:sticky; top:0; z-index:50; background:#fff3cd; border-bottom:1px solid #ffe08a; padding:8px 14px; font-family:'Sarabun',sans-serif; font-size:14pt; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
    <b>แพทย์ผู้ตรวจ:</b>
    <select id="cert-doc-select" style="font-size:14pt; padding:2px 6px;">
        <option value="">— เลือกแพทย์ —</option>
        @foreach($__opts as $o)
            <option value="{{ $o['id'] }}" {{ $__curIsPhys && $o['id'] === $__curId ? 'selected' : '' }}>{{ $o['name'] }}</option>
        @endforeach
    </select>
    @if(! $__curIsPhys)
        <span style="color:#b91c1c;">ยังไม่ได้ระบุแพทย์ผู้ตรวจใน visit นี้ — กรุณาเลือกก่อนพิมพ์</span>
    @endif
    <span style="color:#555; font-size:12pt;">เลขใบอนุญาตว่าง = พิมพ์ลงในใบเองได้ (คลิกที่ช่อง)</span>
</div>
<script>
(function () {
    var opts = @json($__opts);
    var sel = document.getElementById('cert-doc-select');
    function put(id, text) { var el = document.getElementById(id); if (el) el.textContent = text; }
    function apply(fill) {
        var o = opts.filter(function (x) { return String(x.id) === sel.value; })[0];
        if (!o) { if (fill) { put('cert_doctorName', ''); put('cert_signatureName', ''); } return; }
        put('cert_doctorName', o.name);
        put('cert_signatureName', o.name);
        put('cert_licenseNumber', o.license || '');
    }
    sel.addEventListener('change', function () { apply(true); });
    // เปิดหน้ามา: แพทย์ใน visit เป็นแพทย์จริง = เติมเลขใบอนุญาตให้ · ไม่ใช่ = ล้างชื่อ (กันพิมพ์ชื่อพยาบาลเป็นแพทย์)
    document.addEventListener('DOMContentLoaded', function () { apply({{ $__curIsPhys ? 'false' : 'true' }}); });
})();
</script>
