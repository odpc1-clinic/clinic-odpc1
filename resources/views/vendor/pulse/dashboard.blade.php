{{-- หน้าเฝ้าดูสุขภาพระบบ (Laravel Pulse, 2569-10-20) — เฉพาะ Admin · เก็บย้อนหลัง 7 วัน
     ตัดการ์ดคิวงาน/งานเบื้องหลังออก (ระบบนี้ทำงานแบบ sync ไม่มีคิว)
     คำสั่ง SQL ที่ช้าเก็บแบบมี ? แทนค่า — ไม่มีชื่อ/เลขบัตรคนไข้ติดมา --}}
<x-pulse>
    <livewire:pulse.servers cols="full" />

    <livewire:pulse.usage cols="4" rows="2" />

    <livewire:pulse.exceptions cols="8" />

    <livewire:pulse.slow-requests cols="8" />

    <livewire:pulse.slow-queries cols="full" />

    <livewire:pulse.slow-outgoing-requests cols="6" />

    <livewire:pulse.cache cols="6" />
</x-pulse>
