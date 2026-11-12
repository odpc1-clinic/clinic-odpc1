@php
    // ข้อความเดิมส่วนใหญ่ใช้ซ้ำได้ จึงมีทั้ง "ใช้แทน" (ทับของเดิม) และ "ต่อท้าย" (เก็บที่พิมพ์ไว้แล้ว)
    $current = $rows->firstWhere('id', $visit->id);
@endphp

<div class="fh-wrap">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <div class="fw-bold fs-5">{{ $label }}</div>
            @if($patient)
                <div class="text-muted small">
                    {{ $patient->name ?? '' }} {{ $patient->surname ?? '' }}
                    @if(!empty($patient->code)) · HN {{ $patient->code }} @endif
                </div>
            @endif
        </div>
        <span class="badge bg-secondary align-self-center">พบ {{ $rows->count() }} ครั้ง</span>
    </div>

    @if($rows->isEmpty())
        <div class="alert alert-light border text-center text-muted mb-0">
            <i class="fas fa-folder-open me-1"></i> ยังไม่เคยบันทึก{{ $label }}
        </div>
    @else
        @foreach($rows as $r)
            @php $isCurrent = (int) $r->id === (int) $visit->id; @endphp
            <div class="card mb-2 fh-item {{ $isCurrent ? 'fh-current' : '' }}">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
                    <div class="fw-bold">
                        <i class="fas fa-calendar-day text-muted me-1"></i>
                        {{ \App\Utillity::db2thDate($r->date) ?: $r->date }}
                        @if($isCurrent)<span class="badge bg-primary ms-1">ครั้งนี้</span>@endif
                    </div>
                    @unless($isCurrent)
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary js-fh-use"
                                    data-mode="replace" data-text="{{ $r->{$field} }}">
                                <i class="fas fa-arrow-right-to-bracket"></i> ใช้แทน
                            </button>
                            <button type="button" class="btn btn-outline-secondary js-fh-use"
                                    data-mode="append" data-text="{{ $r->{$field} }}">
                                <i class="fas fa-plus"></i> ต่อท้าย
                            </button>
                        </div>
                    @endunless
                </div>
                <div class="card-body py-2">
                    <div class="fh-text">{{ $r->{$field} }}</div>
                </div>
            </div>
        @endforeach
    @endif
</div>

<style>
    .fh-text    { white-space: pre-wrap; word-break: break-word; }
    .fh-current { border-color: #2563eb; box-shadow: inset 3px 0 0 #2563eb; }
</style>
