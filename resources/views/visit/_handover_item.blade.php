{{-- เรื่องส่งเวรที่ยังค้าง 1 รายการ — หน้าตาเดียวกับที่ JS สร้างตอนเพิ่มใหม่ (visit/_handover_js: itemHtml) --}}
<div class="ho-item" data-ho-id="{{ $n->id }}">
    <div class="flex-grow-1">
        <div style="white-space:pre-wrap;">{{ $n->body }}</div>
        <div class="small ho-meta">{{ $n->author->name ?? '-' }} · {{ $th($n->created_at) }}</div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-success js-ho-resolve" data-url="{{ route('patient.notes.resolve', $n->id) }}" title="เรื่องนี้จบแล้ว">
        <i class="fa fa-check"></i> ปิดเรื่อง
    </button>
</div>
