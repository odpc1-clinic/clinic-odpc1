@extends('layouts.app')
@section('content')
<div class="container" style="max-width:640px">
    <div class="card mt-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-key me-1"></i> เปลี่ยนรหัสผ่านของฉัน
        </div>
        <div class="card-body">
            @if (session('success'))
                {{-- เปลี่ยนสำเร็จ — บอกให้ชัดว่าครั้งหน้าต้องใช้รหัสใหม่ แล้วไปทำงานต่อได้เลย --}}
                <div class="alert alert-success border border-success border-2">
                    <div class="fw-bold fs-5"><i class="fas fa-circle-check me-1"></i> เปลี่ยนรหัสผ่านสำเร็จแล้ว</div>
                    <div>ครั้งหน้าเข้าระบบด้วย<b>รหัสผ่านใหม่</b>ที่เพิ่งตั้ง</div>
                    <a href="{{ url('/') }}" class="btn btn-success mt-2"><i class="fas fa-house me-1"></i> ไปทำงานต่อ</a>
                </div>
            @endif
            @if ($errors->any())
                {{-- ไม่ผ่าน = รหัสยังเป็นรหัสเดิม — เคยมีคนไม่เห็นข้อความเล็ก ๆ แล้วไปเข้าด้วยรหัสใหม่จนเข้าไม่ได้ (2569-11-02) --}}
                <div class="alert alert-danger border border-danger border-3" id="pwError">
                    <div class="fw-bold fs-5"><i class="fas fa-circle-xmark me-1"></i> ยังไม่ได้เปลี่ยนรหัสผ่าน</div>
                    <div class="mb-1"><b>รหัสผ่านของท่านยังเป็นรหัสเดิม</b> — แก้ตามนี้แล้วกดบันทึกอีกครั้ง:</div>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                    </ul>
                </div>
                <script>document.addEventListener('DOMContentLoaded', function () { var e = document.getElementById('pwError'); if (e) e.scrollIntoView({ block: 'start' }); });</script>
            @endif

            @if (auth()->user()->must_change_password && ! $errors->any())
                {{-- ถูกบังคับเปลี่ยน (ForcePasswordChange) — บอกให้ชัดว่าทำไม และใช้ระบบส่วนอื่นได้หลังตั้งเสร็จ (2569-11-02) --}}
                <div class="alert alert-danger">
                    <div class="fw-bold mb-1"><i class="fas fa-shield-halved me-1"></i> กรุณาตั้งรหัสผ่านใหม่ก่อนใช้งานระบบ</div>
                    <div class="small">
                        บัญชีนี้ยังไม่ได้เปลี่ยนรหัสผ่านตามนโยบายความปลอดภัย ตั้งรหัสใหม่เสร็จแล้วจึงใช้งานหน้าอื่นได้ตามปกติ
                    </div>
                </div>
            @endif

            <div class="alert alert-warning small">
                <div class="fw-bold mb-1">ต้นเรื่อง: หนังสือกระทรวงสาธารณสุข ที่ สธ 0418.17/719 ลงวันที่ 27 สิงหาคม 2569</div>
                สำนักงานคณะกรรมการการรักษาความมั่นคงปลอดภัยไซเบอร์แห่งชาติ (สกมช.) แจ้งเตือนความเสี่ยงชื่อผู้ใช้และรหัสผ่านของระบบในสังกัดกระทรวงสาธารณสุข
                ทุกบัญชีจึงต้องเปลี่ยนเป็นรหัสผ่านที่คาดเดายาก และ<b>ห้ามบอกรหัสผ่านแก่ผู้อื่น</b> (รวมถึงผู้ดูแลระบบ)
            </div>

            <div class="border rounded p-3 mb-3" style="background:#f8fafc;">
                <div class="fw-bold mb-2">รหัสผ่านใหม่ต้องมีครบทุกข้อ</div>
                <ol class="mb-2 ps-3" style="line-height:1.8;">
                    <li>ยาว<b>อย่างน้อย 12 ตัวอักษร</b></li>
                    <li><b>ตัวพิมพ์ใหญ่</b> ภาษาอังกฤษ อย่างน้อย 1 ตัว (A–Z)</li>
                    <li><b>ตัวพิมพ์เล็ก</b> ภาษาอังกฤษ อย่างน้อย 1 ตัว (a–z)</li>
                    <li><b>ตัวเลข</b> อย่างน้อย 1 ตัว (0–9)</li>
                    <li><b>อักขระพิเศษ</b> อย่างน้อย 1 ตัว เช่น <code>! @ # $ % &amp; * ?</code></li>
                </ol>
                <div class="fw-bold mb-1 text-danger">ห้าม</div>
                <ul class="mb-0 ps-3 small" style="line-height:1.7;">
                    <li>ซ้ำกับรหัสผ่านเดิม</li>
                    <li>มีชื่อหรืออีเมลของตัวเองอยู่ในรหัสผ่าน</li>
                    <li>มีคำที่เดาง่าย เช่น <code>password</code> <code>admin</code> <code>123456</code> <code>qwerty</code> <code>clinic</code> <code>hospital</code> <code>111111</code></li>
                    <li>ใช้รหัสเดียวกับระบบอื่น หรือจดไว้ให้คนอื่นเห็น</li>
                </ul>
            </div>

            <form method="POST" action="{{ route('password.mine.update') }}" autocomplete="off">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-bold">รหัสผ่านเดิม</label>
                    <input type="password" name="current_password" class="form-control form-control-lg" autocomplete="current-password" required>
                    <div class="form-text">จำรหัสเดิมไม่ได้? แจ้งผู้ดูแลระบบให้เปิดหน้าตั้งรหัสใหม่ให้ค่ะ</div>
                </div>
                @include('password._fields')
                <button type="submit" class="btn btn-primary btn-lg w-100" id="pwSubmit" disabled>
                    บันทึกรหัสผ่านใหม่
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
