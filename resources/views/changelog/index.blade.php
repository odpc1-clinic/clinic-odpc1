@extends('layouts.app')

@section('content')
{{-- ตัวใหญ่อ่านง่าย (พยาบาลอาวุโส) เหมือนหน้ารายงานอื่น --}}
<style>
    #cl-page { font-size: 1.08rem; }
    #cl-page .cl-day { border-left: 4px solid #0d6efd; }
    #cl-page .cl-item + .cl-item { border-top: 1px dashed #dee2e6; }
    #cl-page .cl-tag { min-width: 4.6rem; text-align: center; font-size: .92rem; }
    #cl-page .cl-note { font-size: .95rem; }
    @media print {
        .no-print { display: none !important; }
        #cl-page .cl-day { break-inside: avoid; }
    }
</style>

<div class="container py-3" id="cl-page">

    <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
        <h4 class="mb-0"><i class="fas fa-bullhorn me-2"></i>มีอะไรใหม่</h4>
        <span class="text-muted">บันทึกการเปลี่ยนแปลงของระบบ เรียงจากใหม่ไปเก่า</span>
        <button type="button" class="btn btn-outline-secondary btn-sm ms-auto no-print" onclick="window.print()">
            <i class="fa fa-print me-1"></i> พิมพ์ / บันทึกไว้ประชุม
        </button>
    </div>

    <div class="d-flex gap-3 flex-wrap mb-3 text-muted cl-note">
        <span><span class="badge bg-success cl-tag">ใหม่</span> ของที่เพิ่งเพิ่มเข้ามา</span>
        <span><span class="badge bg-danger cl-tag">แก้บั๊ก</span> ของที่เคยทำงานผิด</span>
        <span><span class="badge bg-primary cl-tag">ปรับ</span> ของเดิมที่เปลี่ยนที่/เปลี่ยนวิธีใช้</span>
    </div>

    @forelse($entries as $day)
        @php
            // วันที่เก็บเป็น พ.ศ. Y-m-d อยู่แล้ว แสดงเป็น ว/ด/ป ให้อ่านง่าย
            $__p = explode('-', $day['date'] ?? '');
            $__show = count($__p) === 3 ? ((int) $__p[2]) . '/' . ((int) $__p[1]) . '/' . $__p[0] : ($day['date'] ?? '');
        @endphp
        <div class="card mb-3 cl-day" data-date="{{ $day['date'] ?? '' }}">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <i class="fas fa-calendar-day text-primary"></i>
                <b>{{ $__show }}</b>
                @if($loop->first)
                    <span class="badge bg-warning text-dark">ล่าสุด</span>
                @endif
                @if(!empty($day['approx']))
                    <span class="badge bg-secondary" title="ไล่ย้อนหลังจากไฟล์ระบบ วันที่อาจคลาดไปบ้าง">วันที่โดยประมาณ</span>
                @endif
            </div>
            <div class="card-body py-0">
                @foreach($day['items'] ?? [] as $it)
                    @php
                        $__tag = $it['tag'] ?? 'change';
                        $__cls = ['new' => 'bg-success', 'fix' => 'bg-danger', 'change' => 'bg-primary'][$__tag] ?? 'bg-secondary';
                        $__lbl = ['new' => 'ใหม่',    'fix' => 'แก้บั๊ก', 'change' => 'ปรับ'][$__tag]    ?? $__tag;
                    @endphp
                    <div class="cl-item d-flex gap-3 py-3">
                        <span class="badge {{ $__cls }} cl-tag align-self-start">{{ $__lbl }}</span>
                        <div>
                            @if(!empty($it['area']))
                                <div class="text-muted cl-note"><i class="fas fa-location-dot me-1"></i>{{ $it['area'] }}</div>
                            @endif
                            <div>{{ $it['text'] ?? '' }}</div>
                            @if(!empty($it['note']))
                                <div class="alert alert-light border mt-2 mb-0 py-2 px-3 cl-note">
                                    <i class="fas fa-circle-info me-1 text-primary"></i>{{ $it['note'] }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="alert alert-light border text-center text-muted">ยังไม่มีบันทึกการเปลี่ยนแปลง</div>
    @endforelse

</div>

<script>
// เข้าหน้านี้ = อ่านแล้ว -> เก็บวันที่ล่าสุดไว้ในเครื่อง จุดแดงบนเมนูจะหายไป
// ใช้ localStorage แทนการเก็บใน DB เพราะเป็นเรื่องของ "เครื่องนี้อ่านหรือยัง" ไม่ใช่ข้อมูลคนไข้
try { localStorage.setItem('changelog_seen', @json($latestDate)); } catch (e) {}
</script>
@endsection
