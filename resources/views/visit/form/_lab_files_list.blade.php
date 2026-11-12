{{-- รายการไฟล์ผลตรวจจากที่อื่นของคนไข้ (ทุกคลินิก + บัตร TB) — ใช้ทั้งตอนเปิดหน้า และตอบกลับหลังอัปโหลด/ลบ (innerHTML)
     ⚠️ ห้ามใส่ <script> / <form>: สคริปต์ที่มากับ innerHTML ไม่รัน และไฟล์นี้อยู่ในฟอร์มหลักของหน้าตรวจ
     ปุ่มลบผูกแบบ delegate ไว้ใน _lab_files.blade.php ฝั่งหน้าที่โหลดเต็ม --}}
@php
    $__all = \App\Models\TbLabFile::with('uploader')->where('patient_id', $visit->patient_id)
        ->orderByRaw('visit_id = ? DESC', [$visit->id])
        ->orderByRaw('test_date IS NULL, test_date DESC, id DESC')->get();
    // หน้าตรวจโชว์เฉพาะไฟล์ของ visit นี้ หรือที่อัปวันเดียวกับวันมาตรวจ (คลินิกขอ 2569-10-12)
    // ไฟล์วันอื่นพับไว้ข้างล่าง · บัตร TB 01 ยังโชว์ครบทุกไฟล์ (tb/_lab_files)
    $__vd = (string) $visit->date;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $__vd, $__m)) {
        $__vd = ((int) $__m[1] >= 2500 ? (int) $__m[1] - 543 : (int) $__m[1]) . '-' . $__m[2] . '-' . $__m[3];
    }
    [$__lf, $__old] = $__all->partition(function ($f) use ($visit, $__vd) {
        return (int) $f->visit_id === (int) $visit->id || optional($f->created_at)->format('Y-m-d') === $__vd;
    });
@endphp
@if($__lf->isEmpty())
    <div class="text-muted small">ยังไม่มีไฟล์ผลตรวจของวันนี้</div>
@else
    <div class="d-flex flex-wrap gap-2">
        @foreach($__lf as $__f)
            @include('visit.form._lab_file_card')
        @endforeach
    </div>
@endif
@if($__old->isNotEmpty())
    {{-- <details> เปิด/ปิดได้โดยไม่ต้องใช้สคริปต์ (ไฟล์นี้ถูกแทนที่ด้วย innerHTML) --}}
    <details class="mt-2">
        <summary class="small text-muted" style="cursor:pointer;">ไฟล์ผลตรวจวันอื่น {{ $__old->count() }} ไฟล์ (กดเพื่อดู)</summary>
        <div class="d-flex flex-wrap gap-2 mt-2">
        @foreach($__old as $__f)
            @include('visit.form._lab_file_card')
        @endforeach
        </div>
    </details>
@endif
