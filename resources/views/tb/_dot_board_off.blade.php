{{-- รายชื่อคนไข้ที่พัก DOT ไว้ — ยังอยู่ในทะเบียน นัด F/U ตาม road map ได้ตามปกติ
     แยกไฟล์ออกมาเพราะคอลัมน์คนละชุดกับตารางรายวัน --}}
@if($rows->isEmpty())
    <div class="text-muted">ยังไม่มีคนไข้ที่พัก DOT ไว้</div>
@else
<div class="table-responsive">
    <table class="table table-sm table-bordered align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th style="width:3rem">ลำดับ</th>
                <th style="width:18%">ชื่อ-สกุล</th>
                <th style="width:10%">เลขทะเบียน</th>
                <th style="width:12%">เหตุผล DOT เดิม</th>
                <th style="width:12%">สูตรยาที่ใช้</th>
                <th style="width:10%">ปิดเมื่อ</th>
                <th style="width:12%">หลักฐานวันที่เลือก</th>
                <th>เหตุผลที่ปิด</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $i => $reg)
            <tr>
                <td class="text-center text-muted">{{ $i + 1 }}</td>
                <td>
                    <a href="{{ route('tb.show', ['id' => $reg->id, 'from' => 'dot', 'view' => 'off']) }}" class="fw-bold text-decoration-none">
                        {{ optional($reg->patient)->name }} {{ optional($reg->patient)->surname }}
                    </a>
                    <div class="small text-muted">HN {{ optional($reg->patient)->code }}</div>
                </td>
                <td>{{ $reg->tb_no ?: '—' }}</td>
                <td>{{ $reg->dot_reason ?: '—' }}</td>
                <td class="small">{{ $reg->currentRegimenText() ?: '—' }}</td>
                <td>{{ $reg->dot_off_date ? \App\Utillity::db2thDate($reg->dot_off_date) : '—' }}</td>

                {{-- คนพัก DOT ก็ยังส่งคลิปเข้ามาได้ ต้องเห็นด้วย ไม่งั้นหลักฐานหายเงียบ --}}
                <td>
                    @forelse($subs[$reg->id] ?? collect() as $sub)
                        <div>
                            <a href="{{ route('tb.dotMedia', $sub->id) }}" target="_blank" class="small">
                                <i class="fa {{ $sub->kind === 'image' ? 'fa-image' : 'fa-video' }} me-1"></i>
                                {{ $sub->received_at ? $sub->received_at->format('H:i') : 'ไม่ทราบเวลา' }}
                            </a>
                        </div>
                    @empty
                        <span class="small text-muted">—</span>
                    @endforelse
                </td>

                <td>
                    <div class="small text-muted">{{ $reg->dot_off_reason ?: 'ไม่ได้ระบุเหตุผล' }}</div>
                    {{-- ดึงกลับมา DOT จากหน้านี้ได้ (คลินิกขอ 2569-10-22 — เดิมทำได้ที่หน้าตรวจเท่านั้น)
                         ไฟล์นี้อยู่ในฟอร์มของหน้า DOT — ใช้ปุ่มธรรมดา ให้ JS ของหน้าแม่สร้างฟอร์มต่อกับ body (ห้ามซ้อน <form>) --}}
                    <button type="button" class="btn btn-sm btn-outline-success mt-1 js-dot-resume"
                            data-url="{{ route('tb.dotStatus', $reg->id) }}"
                            data-name="{{ optional($reg->patient)->name }} {{ optional($reg->patient)->surname }}">
                        <i class="fa fa-rotate-left me-1"></i> ดึงกลับมา DOT
                    </button>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
