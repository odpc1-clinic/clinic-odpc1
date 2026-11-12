{{-- แถบสรุปจำนวนคนไข้ที่ค้างอยู่ในแต่ละห้อง (แสดงบนหน้าห้องตรวจ)
     สร้างจาก Visit::$statusOption ตรง ๆ จะได้ไม่ต้องไล่แก้ทุกครั้งที่เพิ่มห้องใหม่
     ของเดิมพิมพ์ชื่อห้องไว้ตายตัวและผิดด้วย (status 2 = ห้องตรวจชาย 4 แต่เขียนว่า "ห้องตรวจเบอร์ 2")
     ไม่แสดง "กลับบ้าน" เพราะเป็นงานที่จบแล้ว ไม่ใช่คนที่ยังรออยู่ --}}
@php
    $__rooms = collect(\App\Models\Visit::$statusOption)->except([6]);
@endphp

<div class="table-responsive">
    <table class="table table-info">
        <thead>
            <tr>
                @foreach($__rooms as $__st => $__name)
                    <th>{{ $__name }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach($__rooms as $__st => $__name)
                    @php $__n = \App\Models\Visit::count($__st); @endphp
                    <td class="{{ $__n > 0 ? 'fw-bold' : 'text-muted' }}">{{ $__n }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>
    @include('sweetalert::alert')
</div>
