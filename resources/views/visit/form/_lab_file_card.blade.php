{{-- การ์ดไฟล์ผลตรวจ 1 ใบ ใน _lab_files_list (ใช้ทั้งกลุ่มวันนี้และกลุ่มวันอื่น) · ตัวแปร: $visit, $__f · ห้ามมี <script>/<form> --}}
            @php
                $__url  = route('visit.labFile.show', [$visit->id, $__f->id]);
                $__mine = (int) $__f->visit_id === (int) $visit->id;
            @endphp
            <div class="card shadow-sm" style="width: 12rem;">
                <a href="{{ $__url }}" target="_blank" rel="noopener" title="เปิดดู" class="text-decoration-none d-flex align-items-center justify-content-center"
                   style="height: 8rem; background:#f8fafc; border-bottom:1px solid #e5e7eb; overflow:hidden;">
                    @if($__f->isImage())
                        <img src="{{ $__url }}?thumb=1" alt="" loading="lazy" style="max-width:100%; max-height:100%; object-fit:contain;">
                    @else
                        <i class="fa fa-file-pdf" style="font-size:3rem; color:#dc2626;"></i>
                    @endif
                </a>
                <div class="card-body p-2 small">
                    <div class="fw-semibold text-truncate" title="{{ $__f->title }}">{{ $__f->title ?: '(ไม่ระบุรายการ)' }}</div>
                    <div>{{ $__f->test_date ? \App\Support\ThaiDate::show($__f->test_date) : 'ไม่ระบุวันที่ตรวจ' }}</div>
                    @if($__f->source)<div class="text-truncate" title="{{ $__f->source }}">{{ $__f->source }}</div>@endif
                    @if($__f->note)<div class="text-muted text-truncate" title="{{ $__f->note }}">{{ $__f->note }}</div>@endif
                    <div class="text-muted text-truncate" title="{{ $__f->original_name }}">{{ $__f->original_name }} · {{ $__f->sizeText() }}</div>
                    <div class="text-muted">โดย {{ optional($__f->uploader)->name ?: '—' }}</div>
                    <div class="d-flex flex-wrap gap-1 mt-1 align-items-center">
                        <a href="{{ $__url }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm flex-fill"><i class="fa fa-eye"></i> ดู</a>
                        @include('partials.lab_file_edit_panel', ['f' => $__f, 'url' => route('visit.labFile.update', [$visit->id, $__f->id])])
                        @if($__mine)
                            <button type="button" class="btn btn-outline-danger btn-sm js-vlf-delete" title="ลบไฟล์"
                                    data-url="{{ route('visit.labFile.destroy', [$visit->id, $__f->id]) }}"
                                    data-name="{{ $__f->title ?: $__f->original_name }}">
                                <i class="fa fa-trash"></i>
                            </button>
                        @else
                            {{-- ลบได้เฉพาะไฟล์ที่อัปใน visit นี้ — ที่อื่นให้ไปลบที่ต้นทาง --}}
                            <span class="badge bg-light text-dark border" title="อัปไว้ที่{{ $__f->tb_registration_id ? 'บัตร TB' : 'การมาครั้งก่อน' }}">{{ $__f->tb_registration_id ? 'บัตร TB' : 'ครั้งก่อน' }}</span>
                        @endif
                    </div>
                </div>
            </div>
