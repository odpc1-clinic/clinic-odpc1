{{-- ผู้สัมผัส ↔ ผู้ป่วยต้นเหตุ (index case) — แท็บข้อมูลบัตร (คลินิกขอ 2569-10-22)
     บัตรผู้สัมผัส (LTBI / ป่วยเอง): ลิงก์กลับไปบัตรของ index
     บัตรวัณโรค / ดื้อยา: รายชื่อผู้สัมผัสทั้งหมด รวมจากตารางร่วมบ้าน + ใบคัดกรองผู้สัมผัส + บัตรที่ผูก index นี้ (App\Services\TbContacts)
     ตัวแปร: $reg --}}
@php
    $__TC = \App\Services\TbContacts::class;
    $__idx = $__TC::ready() && $reg->index_registration_id ? \App\Models\TbRegistration::with('patient')->find($reg->index_registration_id) : null;
    $__isIndex = in_array((int) $reg->registry_type, [\App\Models\TbRegistration::TYPE_TB, \App\Models\TbRegistration::TYPE_MDR], true);
    $__rows = $__isIndex ? $__TC::forIndex($reg) : [];
    $__imp = \App\Models\TbScreening::$impressionOption;
@endphp
@if($__idx || $__isIndex)
<div class="card border-0 shadow-sm mb-3" id="tb-contacts">
    <div class="card-body">
        @if($__idx)
            <div class="alert alert-light border d-flex flex-wrap align-items-center gap-2 py-2 {{ $__isIndex ? 'mb-3' : 'mb-0' }}">
                <i class="fa fa-people-arrows text-warning"></i>
                <span>ผู้สัมผัสของผู้ป่วย</span>
                <a href="{{ route('tb.show', $__idx->id) }}" class="fw-bold">{{ $__TC::label($__idx) }}</a>
                <span class="badge {{ $__idx->isActive() ? 'bg-success' : 'bg-secondary' }}">{{ $__idx->isActive() ? 'กำลังรักษา' : $__idx->outcomeName() }}</span>
            </div>
        @endif

        @if($__isIndex)
            <h6 class="fw-bold mb-2"><i class="fa fa-people-group me-1"></i> ผู้สัมผัส ({{ count($__rows) }} ราย)
                <span class="small text-muted fw-normal">รวมจากตารางสมาชิกร่วมบ้าน · ใบคัดกรองผู้สัมผัส · บัตร LTBI ที่ผูกผู้ป่วยรายนี้</span>
            </h6>
            @if(! $__rows)
                <div class="small text-muted">
                    ยังไม่มีผู้สัมผัสที่ผูกไว้ — ผูกได้ 3 ทาง: ① ตารางสมาชิกร่วมบ้านในใบคัดกรองของผู้ป่วยรายนี้ (ใส่ HN)
                    ② ใบคัดกรองของผู้สัมผัส ช่อง "ผูกบัตร TB ของผู้ป่วย" ③ บัตร LTBI ช่อง "ผู้ป่วยต้นเหตุ"
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:2.5rem">#</th>
                                <th>ชื่อ-สกุล / HN</th>
                                <th style="width:8rem">ความสัมพันธ์</th>
                                <th style="width:10rem">ที่มา</th>
                                <th>คัดกรองล่าสุด</th>
                                <th>บัตร</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($__rows as $__i => $__c)
                            <tr>
                                <td class="text-center text-muted">{{ $__i + 1 }}</td>
                                <td>
                                    @if($__c['patient_id'])
                                        <a href="{{ route('patient.edit', $__c['patient_id']) }}" target="_blank" class="fw-semibold">{{ $__c['name'] }}</a>
                                    @else
                                        <span class="fw-semibold">{{ $__c['name'] }}</span>
                                    @endif
                                    <div class="small text-muted">{{ $__c['hn'] ? 'HN ' . $__c['hn'] : 'ยังไม่มีเวชระเบียน / ยังไม่มาตรวจ' }}</div>
                                </td>
                                <td class="small">{{ $__c['relation'] ?: '—' }}</td>
                                <td class="small">
                                    @foreach(array_keys($__c['sources']) as $__s)<span class="badge bg-light text-dark border me-1">{{ $__s }}</span>@endforeach
                                </td>
                                <td class="small">
                                    @if($__c['screening'])
                                        @php $__sc = $__c['screening']; @endphp
                                        @if($__sc->visit)
                                            <a href="{{ route('visit.edit', ['visit' => $__sc->visit_id]) }}" target="_blank">{{ \App\Utillity::db2thDate($__sc->visit->date) }}</a>
                                        @endif
                                        · {{ $__imp[$__sc->impression] ?? 'ยังไม่สรุปผล' }}
                                    @else
                                        <span class="text-muted">ยังไม่มาคัดกรอง</span>
                                    @endif
                                </td>
                                <td class="small">
                                    @forelse($__c['cards'] as $__card)
                                        <div>
                                            <a href="{{ route('tb.show', $__card->id) }}">{{ $__card->typeName() }}</a>
                                            <span class="badge {{ $__card->isActive() ? 'bg-success' : 'bg-secondary' }}">{{ $__card->isActive() ? 'กำลังรักษา' : $__card->outcomeName() }}</span>
                                        </div>
                                    @empty
                                        <span class="text-muted">ไม่มีบัตร</span>
                                    @endforelse
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</div>
@endif
