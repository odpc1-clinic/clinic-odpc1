@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 780px;">


    <h3 class="fw-bold mb-1"><i class="fa-solid fa-share-nodes me-2"></i> ลิงก์แดชบอร์ดสำหรับส่งให้หัวหน้า / ฉายในห้องประชุม</h3>
    <p class="text-muted">เปิดก่อนประชุม ส่งลิงก์ทางไลน์ พอประชุมเสร็จลิงก์จะปิดเองตามเวลาที่ตั้งไว้</p>

    {{-- สถานะตอนนี้ --}}
    <div class="card shadow-sm mb-4 border-2 {{ $isLive ? 'border-success' : 'border-secondary' }}">
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-3">
                <span class="badge {{ $isLive ? 'bg-success' : 'bg-secondary' }}" style="font-size: 1rem; padding: 8px 14px;">
                    <i class="fa-solid {{ $isLive ? 'fa-lock-open' : 'fa-lock' }} me-1"></i>
                    {{ $isLive ? 'ลิงก์เปิดอยู่' : 'ลิงก์ปิดอยู่' }}
                </span>
                <div class="small text-muted">
                    @if($isLive && $expiresAt)
                        ปิดอัตโนมัติ {{ $expiresAt->format('H:i') }} น. วันที่ {{ $expiresAt->format('d/m/') }}{{ $expiresAt->year + 543 }}
                        ({{ $expiresAt->diffForHumans(null, true) }}ข้างหน้า)
                    @elseif($isLive)
                        ไม่กำหนดวันหมดอายุ — เปิดจนกว่าจะกดปิดเอง
                    @elseif($isExpired)
                        หมดอายุไปแล้วเมื่อ {{ $expiresAt->format('H:i') }} น. วันที่ {{ $expiresAt->format('d/m/') }}{{ $expiresAt->year + 543 }}
                    @else
                        คนที่ไม่ได้ล็อกอินเปิดดูไม่ได้
                    @endif
                </div>
            </div>

            @if($isLive)
                <label class="form-label fw-semibold small">ลิงก์สำหรับส่งทางไลน์ — กดปุ่มเพื่อคัดลอก</label>
                <div class="input-group mb-2">
                    <input type="text" class="form-control" id="shareUrl" value="{{ $liveUrl }}" readonly
                           style="font-size: .85rem; background:#f8f9fa;">
                    <button class="btn btn-primary" type="button" onclick="copyShareUrl()">
                        <i class="fa fa-copy"></i> คัดลอก
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ $liveUrl }}" target="_blank">
                        <i class="fa fa-up-right-from-square"></i> ลองเปิด
                    </a>
                </div>
                <div class="small text-muted">คนที่ได้ลิงก์นี้เปิดดูได้โดยไม่ต้องล็อกอิน — ส่งเฉพาะคนที่ต้องใช้จริง</div>
            @else
                <div class="alert alert-secondary mb-0 small">
                    ตอนนี้ลิงก์ปิดอยู่ ใครเปิดก็จะเด้งไปหน้าล็อกอิน — กดเปิดด้านล่างเมื่อจะใช้งาน
                </div>
            @endif
        </div>
    </div>

    {{-- เปิดใช้งาน --}}
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-success text-white fw-bold">
            <i class="fa-solid fa-lock-open me-1"></i> เปิดลิงก์ให้ใช้งาน
        </div>
        <div class="card-body">
            <form action="{{ route('report.share.update') }}" method="POST" class="row g-2 align-items-end">
                @csrf
                <input type="hidden" name="action" value="on">
                <div class="col-sm-7">
                    <label class="form-label fw-semibold">เปิดไว้นานแค่ไหน</label>
                    <select name="hours" class="form-select">
                        @foreach($durations as $h => $label)
                            <option value="{{ $h }}" {{ $h === '3' ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-5">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fa fa-play me-1"></i> {{ $isLive ? 'ต่อเวลา / เปลี่ยนระยะเวลา' : 'เปิดลิงก์' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ปิด / สร้างใหม่ --}}
    <div class="row g-3">
        <div class="col-md-6">
            <form action="{{ route('report.share.update') }}" method="POST">
                @csrf
                <input type="hidden" name="action" value="off">
                <button type="submit" class="btn btn-outline-danger w-100" {{ $isLive ? '' : 'disabled' }}>
                    <i class="fa fa-stop me-1"></i> ปิดลิงก์เดี๋ยวนี้
                </button>
            </form>
            <div class="small text-muted mt-1">ใช้เมื่อประชุมเสร็จก่อนเวลา</div>
        </div>
        <div class="col-md-6">
            <form action="{{ route('report.share.update') }}" method="POST"
                  onsubmit="return confirm('สร้างลิงก์ใหม่แล้วลิงก์เดิมที่เคยส่งไปจะใช้ไม่ได้อีกทั้งหมด ยืนยันหรือไม่?');">
                @csrf
                <input type="hidden" name="action" value="regenerate">
                <button type="submit" class="btn btn-outline-warning w-100">
                    <i class="fa fa-rotate me-1"></i> สร้างลิงก์ใหม่ (ลิงก์เก่าตายทันที)
                </button>
            </form>
            <div class="small text-muted mt-1">ใช้เมื่อลิงก์หลุดไปที่ไม่ควร</div>
        </div>
    </div>

    <div class="alert alert-light border mt-4 small mb-0">
        <strong><i class="fa-solid fa-circle-info me-1"></i> สิ่งที่หัวหน้าจะเห็น</strong> —
        แดชบอร์ดสถิติทั้งหน้า ตั้งช่วงวันที่เองได้ (เช่น ย้อนหลัง 1 ปี ดูตารางโรคหลักแยกชาย-หญิง)
        · เป็นตัวเลขรวมล้วน ไม่มีชื่อหรือ HN คนไข้
        · ลิงก์นี้เปิดได้<strong>เฉพาะหน้าแดชบอร์ด</strong> หน้าอื่นในระบบยังต้องล็อกอินตามปกติ
    </div>
</div>

<script>
function copyShareUrl() {
    var el = document.getElementById('shareUrl');
    el.select(); el.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(el.value).then(function () {
        alert('คัดลอกลิงก์แล้ว — วางในไลน์ได้เลย');
    }, function () {
        document.execCommand('copy');
        alert('คัดลอกลิงก์แล้ว');
    });
}
</script>
@endsection
