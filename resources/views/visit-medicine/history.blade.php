@php
    $page = request('page', 1);
@endphp

<div class="mh-wrap">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <div class="fw-bold fs-5"><i class="fas fa-pills text-muted me-1"></i> ยาที่เคยสั่งให้คนไข้รายนี้</div>
            <div class="text-muted small">กด "ดึงทั้งชุด" เพื่อคัดลอกยาทุกตัวของครั้งนั้นมาใส่ใบสั่งยาครั้งนี้</div>
        </div>
        <span class="badge bg-secondary align-self-center">ย้อนหลัง {{ $past->count() }} ครั้ง</span>
    </div>

    @if($past->isEmpty())
        <div class="alert alert-light border text-center text-muted mb-0">
            <i class="fas fa-folder-open me-1"></i> คนไข้รายนี้ยังไม่เคยได้รับยาจากระบบ
        </div>
    @else
        @foreach($past as $p)
            @php
                // ถ้ายาทุกตัวของครั้งนั้นมีอยู่แล้ว การกด "ดึงทั้งชุด" จะไม่เพิ่มอะไร -> ปิดปุ่มไปเลย
                $newCount = $p->visitMedicines->filter(fn ($m) => !in_array((string) $m->medicine_id, $already, true))->count();
            @endphp
            <div class="card mb-2">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
                    <div class="fw-bold">
                        <i class="fas fa-calendar-day text-muted me-1"></i>
                        {{ \App\Utillity::db2thDate($p->date) ?: $p->date }}
                        <span class="badge bg-light text-muted border ms-1">{{ $p->visitMedicines->count() }} รายการ</span>
                    </div>
                    <form method="POST" action="{{ route('visit-medicine.copy', $visit->id) }}" class="m-0">
                        @csrf
                        <input type="hidden" name="from_visit" value="{{ $p->id }}">
                        <input type="hidden" name="page" value="{{ $page }}">
                        <button type="submit" class="btn btn-primary btn-sm" {{ $newCount === 0 ? 'disabled' : '' }}>
                            <i class="fas fa-copy"></i>
                            {{ $newCount ? 'ดึงทั้งชุด (' . $newCount . ')' : 'มีครบแล้ว' }}
                        </button>
                    </form>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ชื่อยา</th>
                                <th style="width:110px;">dose</th>
                                <th style="width:100px;">route</th>
                                <th style="width:80px;">จำนวน</th>
                                <th style="width:110px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($p->visitMedicines as $m)
                            @php $dup = in_array((string) $m->medicine_id, $already, true); @endphp
                            <tr class="{{ $dup ? 'text-muted' : '' }}">
                                <td>{{ $medicines[$m->medicine_id] ?? ('#' . $m->medicine_id) }}</td>
                                <td>{{ $m->dose }}</td>
                                <td>{{ $m->route }}
                                    @if($m->sigText() !== '')<div class="small" style="color:#0f766e;">{{ $m->sigText() }}</div>@endif
                                </td>
                                <td>{{ $m->amount }}</td>
                                <td class="text-end">
                                    @if($dup)
                                        <span class="badge bg-light text-muted border">มีแล้ว</span>
                                    @else
                                        <form method="POST" action="{{ route('visit-medicine.copy', $visit->id) }}" class="m-0">
                                            @csrf
                                            <input type="hidden" name="item" value="{{ $m->id }}">
                                            <input type="hidden" name="page" value="{{ $page }}">
                                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-plus"></i> ดึง
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        <div class="text-muted small mt-2">
            <i class="fas fa-circle-info"></i> ยาที่มีอยู่แล้วในใบสั่งยาครั้งนี้จะถูกข้ามให้อัตโนมัติ ไม่สั่งซ้ำ
        </div>
    @endif
</div>
