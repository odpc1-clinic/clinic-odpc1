{{-- ปุ่มเล็กข้างชื่อช่อง: เปิด popup ข้อความเดิมของช่องนี้ (ต้องมี $visit ใน scope) --}}
@isset($visit)
    @if($visit->id ?? null)
        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 ms-2 align-baseline js-fh-open"
                data-bs-toggle="modal" data-bs-target="#fieldHistoryModal"
                data-url="{{ route('visit.fieldHistory', ['id' => $visit->id, 'field' => $field]) }}"
                data-target="{{ $target }}"
                title="ดูข้อความเดิมที่เคยบันทึก แล้วดึงมาใช้ได้">
            <i class="fas fa-clock-rotate-left"></i> เดิม
        </button>
    @endif
@endisset
