{{-- ตัวเลือก "ตรวจที่" / "รายการ" จากที่เคยพิมพ์ไว้ในไฟล์ผลตรวจทุกไฟล์ (คลินิกขอ 2569-10-08) — ยังพิมพ์ใหม่เองได้
     ใช้ร่วมช่องอัปโหลดกับช่องแก้ (partials/lab_file_edit_panel) ผ่าน list="lab-file-sources" / "lab-file-titles"
     include ครั้งเดียวต่อหน้า (หน้าตรวจกับบัตร TB 01 แยกหน้ากัน) · เรียงตามที่ใช้บ่อย --}}
@once
@php
    $__lfOpts = function ($col) {
        return \App\Models\TbLabFile::query()->whereNotNull($col)->where($col, '!=', '')
            ->select($col)->groupBy($col)->orderByRaw('COUNT(*) DESC')->limit(50)->pluck($col);
    };
@endphp
<datalist id="lab-file-sources">@foreach($__lfOpts('source') as $__o)<option value="{{ $__o }}">@endforeach</datalist>
<datalist id="lab-file-titles">@foreach($__lfOpts('title') as $__o)<option value="{{ $__o }}">@endforeach</datalist>
@endonce
