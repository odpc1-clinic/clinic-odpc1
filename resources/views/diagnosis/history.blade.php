@php
    $page = request('page', 1);
    $key  = fn ($d) => implode('|', [$d->disease, $d->term_syphilis, $d->other_disease]);
@endphp

<div class="dh-wrap">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <div class="fw-bold fs-5"><i class="fas fa-stethoscope text-muted me-1"></i> วินิจฉัยย้อนหลัง</div>
            <div class="text-muted small">ดึงมาแล้วยังแก้ไข/ลบได้ตามปกติ</div>
        </div>
        <span class="badge bg-secondary align-self-center">ย้อนหลัง {{ $past->count() }} ครั้ง</span>
    </div>

    @if($past->isEmpty())
        <div class="alert alert-light border text-center text-muted mb-0">
            <i class="fas fa-folder-open me-1"></i> คนไข้รายนี้ยังไม่เคยมีการวินิจฉัยในระบบ
        </div>
    @else
        @foreach($past as $p)
            @php
                $newCount = $p->diagnoses->filter(fn ($d) => !in_array($key($d), $already, true))->count();
            @endphp
            <div class="card mb-2">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
                    <div class="fw-bold">
                        <i class="fas fa-calendar-day text-muted me-1"></i>
                        {{ \App\Utillity::db2thDate($p->date) ?: $p->date }}
                        <span class="badge bg-light text-muted border ms-1">{{ $p->diagnoses->count() }} รายการ</span>
                    </div>
                    <form method="POST" action="{{ route('diagnosis.copy', $visit->id) }}" class="m-0">
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
                                <th>โรค</th>
                                <th style="width:180px;">สถานะโรค</th>
                                <th style="width:110px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($p->diagnoses as $d)
                            @php $dup = in_array($key($d), $already, true); @endphp
                            <tr class="{{ $dup ? 'text-muted' : '' }}">
                                <td>{{ $d->disease() }}</td>
                                <td>{{ $d->diseaseState() }}</td>
                                <td class="text-end">
                                    @if($dup)
                                        <span class="badge bg-light text-muted border">มีแล้ว</span>
                                    @else
                                        <form method="POST" action="{{ route('diagnosis.copy', $visit->id) }}" class="m-0">
                                            @csrf
                                            <input type="hidden" name="item" value="{{ $d->id }}">
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
            <i class="fas fa-circle-info"></i> รายการที่วินิจฉัยไว้แล้วในครั้งนี้จะถูกข้ามอัตโนมัติ
        </div>
    @endif
</div>
