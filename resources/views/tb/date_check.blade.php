@extends('layouts.app')

{{-- วันที่ผิดรูปแบบในบัตร TB 01 (2569-10-05) — อ่านอย่างเดียว ชี้ให้พยาบาลไปแก้ในบัตร (ดู TbDateCheckController) --}}

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0"><i class="fa fa-calendar-xmark text-danger me-1"></i> วันที่ผิดรูปแบบในบัตร TB 01</h4>
            <div class="text-muted small">
                ปี ค.ศ. · วันในอนาคตของช่องที่เป็นเหตุการณ์ที่เกิดแล้ว (เก็บเสมหะ, X-ray, DST, วันสั่งยา) — ระบบไม่แก้ให้เองเพราะเดาไม่ได้ว่าตั้งใจกรอกวันอะไร
            </div>
        </div>
        <a href="{{ route('tb.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left"></i> ทะเบียนวัณโรค</a>
    </div>

    @if(! $total)
        <div class="alert alert-success"><i class="fa fa-check me-1"></i> ไม่พบวันที่ผิดรูปแบบแล้ว</div>
    @else
        <div class="alert alert-warning py-2">
            พบ <b>{{ $total }}</b> ช่อง ใน <b>{{ $groups->count() }}</b> บัตร — กดชื่อกล่องเพื่อเปิดบัตรตรงกล่องนั้น แก้วันที่ (วว/ดด/ปปปป พ.ศ.) แล้วกดบันทึก รายการจะหายจากหน้านี้เอง
        </div>
        @foreach($groups as $regId => $items)
            @php $reg = $regs[$regId] ?? null; @endphp
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-bold">
                    @if($reg)
                        {{ optional($reg->patient)->code }} · {{ optional($reg->patient)->fullname ?? optional($reg->patient)->name }}
                        <span class="badge bg-light text-dark border ms-1">{{ $reg->tb_no ?: 'ยังไม่มีเลขทะเบียน' }}</span>
                    @else
                        บัตร #{{ $regId }} (ไม่พบบัตร)
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light"><tr><th>ช่อง</th><th>ค่าที่เก็บ</th><th>แสดงเป็น</th><th>ปัญหา</th></tr></thead>
                        <tbody>
                        @foreach($items as $it)
                            <tr>
                                <td>
                                    @if($reg)<a href="{{ route('tb.show', $reg->id) }}#{{ $it['anchor'] }}">{{ $it['label'] }}</a>@else{{ $it['label'] }}@endif
                                </td>
                                <td><code>{{ $it['value'] }}</code></td>
                                <td>{{ $it['shown'] }}</td>
                                <td class="text-danger">{{ $it['why'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
