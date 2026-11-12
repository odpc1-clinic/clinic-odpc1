{{--
    ยาวัณโรคที่สั่งในบัตร TB 01 — แสดงในส่วนสั่งยาของหน้าตรวจ TB ให้เห็นยาที่สั่งครบทุกรายการ (คลินิกขอ 2569-10-01)
    ยาวัณโรคสั่งในตารางขนาดยาของบัตร ไม่ได้อยู่ในตารางสั่งยาของ visit จึงดูอย่างเดียว แก้ที่บัตร
    ใช้แถวสั่งยาครั้งล่าสุดของบัตรที่ยังรักษาอยู่ · ยาอื่นที่หมอเพิ่มในสูตร (Para ฯลฯ) ก็ขึ้นด้วยถ้ามีขนาดในแถวนั้น
--}}
@php
    $__tbr = \App\Models\TbRegistration::forVisit($visit);
    $__tbd = $__tbr ? \App\Models\TbMonthlyDose::where('tb_registration_id', $__tbr->id)
        ->whereNotNull('doses')->orderByDesc('sort')->orderByDesc('id')->first() : null;
    $__tbNames = \App\Models\TbDoseRule::whereNotNull('drug_label')->pluck('drug_label', 'drug_key')
        ->merge(\App\Models\TbDrug::whereNotNull('code')->get()->mapWithKeys(fn ($d) => [(preg_match('/\(([^)]+)\)/', $d->name, $m) ? $m[1] : $d->name) => $d->name]));
    $__fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
@endphp
@if($__tbr)
    <div class="card mt-3 border-0 shadow-sm" style="border-left:5px solid #b45309 !important;">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
                <div class="fw-bold" style="color:#b45309;">
                    <i class="fa-solid fa-lungs me-1"></i> ยาวัณโรค (สั่งในบัตร TB 01)
                    @if($__tbd)<span class="badge bg-light text-dark border ms-1">{{ $__tbd->labelForHumans() }}</span>@endif
                    @if($__tbd && $__tbd->dispensed_date)<span class="small text-muted ms-1">สั่ง {{ \App\Utillity::db2thDate($__tbd->dispensed_date) }}</span>@endif
                </div>
                <a href="{{ route('tb.show', ['id' => $__tbr->id, 'from' => 'visit', 'visit' => $visit->id, 'page' => request('page', 1)]) }}#tb-order"
                   target="_blank" class="btn btn-sm btn-outline-secondary">แก้ที่บัตร TB 01</a>
            </div>
            @if(! $__tbd)
                <div class="small text-muted">ยังไม่มีการสั่งยาในตารางขนาดยาของบัตร</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light"><tr><th>ชื่อยา</th><th style="width:11rem;">ขนาด</th><th>วิธีใช้</th><th style="width:7rem;">จ่าย</th></tr></thead>
                        <tbody>
                        @foreach((array) $__tbd->doses as $__k => $__amt)
                            @continue(trim((string) $__amt) === '')
                            @php
                                $__dt = $__tbr->doseTablets($__k, (string) $__amt);
                                $__sig = $__tbr->sigFor($__k);
                            @endphp
                            <tr>
                                <td>{{ $__tbNames[$__k] ?? $__k }}</td>
                                <td>
                                    @if($__dt['kind'] === 'tab')
                                        {{ $__dt['strength'] ? $__fmt($__dt['mg']) . ' มก. = ' . $__fmt($__dt['strength']) . ' × ' : '' }}<b>{{ $__fmt($__dt['tabs']) }} เม็ด</b>
                                    @elseif(in_array($__dt['kind'], ['mg', 'inj'], true))
                                        {{ $__fmt($__dt['mg']) }} มก.{{ $__dt['kind'] === 'inj' ? ' (ฉีด)' : '' }}
                                    @else
                                        {{ $__amt }}
                                    @endif
                                </td>
                                <td class="small">{{ \App\Models\TbRegistration::sigText($__sig) ?: '—' }}</td>
                                <td class="small">
                                    @if(! empty($__sig['dispense'])) {{ $__sig['dispense'] }} {{ $__sig['unit'] ?? '' }}
                                    @else {{ $__tbd->dispensed_days ?: ($__tbd->label === '2wk' ? 14 : 28) }} วัน @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endif
