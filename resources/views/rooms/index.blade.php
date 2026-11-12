@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
        <h3 class="fw-bold mb-0"><i class="fa-solid fa-grip me-2"></i> ปุ่มลัดเข้าห้อง</h3>
        <span class="text-muted small">
            <i class="fa-solid fa-circle-info me-1"></i>
            กด <i class="fa-solid fa-download"></i> เพื่อโหลดไฟล์ shortcut ไปวางบนเดสก์ท็อปเครื่องลูกข่าย
        </span>
    </div>
    <p class="text-muted">เลือกห้องที่เครื่องนี้ใช้งาน · ตัวเลขคือจำนวนคิวที่รออยู่ตอนนี้</p>

    <div class="row g-3">
        @foreach($rooms as $key => $room)
        <div class="col-6 col-md-4 col-xl-3">
            <div class="room-tile h-100" style="--tile: {{ $room['color'] }};">
                <a href="{{ $room['url'] }}" class="room-tile-link">
                    <div class="room-tile-icon"><i class="fa-solid {{ $room['icon'] }}"></i></div>
                    <div class="room-tile-name">{{ $room['name'] }}</div>
                    @if($room['count'] !== null)
                        <div class="room-tile-count">
                            <span class="badge {{ $room['count'] > 0 ? 'bg-danger' : 'bg-secondary' }}">
                                {{ $room['count'] }} ราย
                            </span>
                        </div>
                    @else
                        <div class="room-tile-count"><span class="badge bg-light text-muted border">เปิดหน้าจอ</span></div>
                    @endif
                </a>
                <a href="{{ route('rooms.shortcut', $key) }}" class="room-tile-dl"
                   title="ดาวน์โหลด shortcut ของ{{ $room['name'] }} (ไฟล์ .url วางบนเดสก์ท็อปได้เลย)">
                    <i class="fa-solid fa-download"></i>
                </a>
            </div>
        </div>
        @endforeach
    </div>

    <div class="alert alert-light border mt-4 small">
        <strong><i class="fa-solid fa-screwdriver-wrench me-1"></i> วิธีติดตั้งที่เครื่องลูกข่าย</strong>
        <ol class="mb-0 mt-2">
            <li>เปิดหน้านี้ที่เครื่องลูกข่าย แล้วกดปุ่ม <i class="fa-solid fa-download"></i> ของห้องที่เครื่องนั้นใช้</li>
            <li>ลากไฟล์ <code>.url</code> ที่โหลดมาไปวางบนเดสก์ท็อป (หรือ Copy ไปที่ <code>C:\Users\Public\Desktop</code> ให้ทุก user เห็น)</li>
            <li>อยากให้เปิดขึ้นมาเองตอนเปิดเครื่อง ให้วางไฟล์เดียวกันไว้ใน <code>shell:startup</code></li>
        </ol>
        <div class="mt-2 text-muted">
            ที่อยู่ของ shortcut จะอิงตาม URL ที่เปิดหน้านี้อยู่ ({{ url('/') }}) — ถ้าเครื่องลูกข่ายเข้าผ่านชื่ออื่น ให้เปิดหน้านี้ด้วยชื่อนั้นแล้วค่อยโหลด
        </div>
    </div>
</div>

<style>
    .room-tile {
        position: relative;
        border: 2px solid var(--tile);
        border-radius: 14px;
        background: #fff;
        overflow: hidden;
        transition: transform .12s ease, box-shadow .12s ease;
    }
    .room-tile:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,.14); }
    .room-tile-link {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: .5rem; padding: 1.75rem 1rem 1.25rem; text-decoration: none; color: inherit; height: 100%;
    }
    .room-tile-icon {
        width: 64px; height: 64px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: var(--tile); color: #fff; font-size: 1.6rem;
    }
    .room-tile-name { font-weight: 700; font-size: 1.05rem; text-align: center; color: var(--tile); }
    .room-tile-count { margin-top: .15rem; }
    /* ปุ่มโหลด shortcut มุมขวาบน — เล็กแต่กดง่ายด้วยนิ้ว */
    .room-tile-dl {
        position: absolute; top: 8px; right: 8px;
        width: 34px; height: 34px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: #f1f3f5; color: #495057; text-decoration: none; font-size: .85rem;
    }
    .room-tile-dl:hover { background: var(--tile); color: #fff; }
</style>
@endsection
