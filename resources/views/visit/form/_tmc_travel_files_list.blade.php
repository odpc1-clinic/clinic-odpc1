{{-- รายการไฟล์แนบแผนการเดินทาง TMC — ใช้ทั้งตอนเปิดหน้า และตอบกลับหลังอัปโหลด/ลบ (fetch แล้วแทนที่ด้วย innerHTML)
     ⚠️ ห้ามใส่ <script> / <form> ในไฟล์นี้: สคริปต์ที่มากับ innerHTML ไม่รัน และไฟล์นี้อยู่ในฟอร์มหลักของหน้าตรวจ
     ปุ่มลบผูกแบบ delegate ไว้ใน tmc_history.blade.php --}}
@php
    $__tf = \App\Models\TmcTravelFile::where('patient_id', $visit->patient_id)
        ->orderByRaw('visit_id = ? DESC', [$visit->id])->orderByDesc('id')->get();
@endphp
@if($__tf->isEmpty())
    <div class="text-muted small">ยังไม่มีไฟล์แนบ</div>
@else
    <ul class="list-unstyled mb-0">
        @foreach($__tf as $__f)
            @php $__mine = (int) $__f->visit_id === (int) $visit->id; @endphp
            <li class="d-flex align-items-center gap-2 py-1 border-bottom">
                <i class="fa-regular {{ $__f->isImage() ? 'fa-file-image' : 'fa-file-pdf' }} text-secondary"></i>
                <a href="{{ route('tmc.travelFile.show', [$visit->id, $__f->id]) }}" target="_blank" rel="noopener" class="text-truncate" style="max-width: 60%;">
                    {{ $__f->title ?: $__f->original_name }}
                </a>
                <span class="small text-muted text-truncate">
                    @if($__f->title){{ $__f->original_name }} · @endif{{ $__f->sizeText() }}
                    · {{ optional($__f->created_at)->format('d/m/') }}{{ $__f->created_at ? $__f->created_at->year + 543 : '' }}
                </span>
                @if(! $__mine)
                    <span class="badge bg-light text-dark border" title="แนบไว้ตอนมาครั้งก่อน">ครั้งก่อน</span>
                @else
                    <button type="button" class="btn btn-outline-danger btn-sm ms-auto js-tf-delete" title="ลบไฟล์"
                            data-url="{{ route('tmc.travelFile.destroy', [$visit->id, $__f->id]) }}"
                            data-name="{{ $__f->title ?: $__f->original_name }}">
                        <i class="fa fa-trash"></i>
                    </button>
                @endif
            </li>
        @endforeach
    </ul>
@endif
