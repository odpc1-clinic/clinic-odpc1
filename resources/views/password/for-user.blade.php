@extends('layouts.app')
@section('content')
<div class="container" style="max-width:680px">
    <div class="card mt-4">
        <div class="card-header bg-danger text-white">
            <i class="fas fa-user-shield me-1"></i> ตั้งรหัสผ่านใหม่ให้ผู้ใช้
        </div>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
                </div>
            @endif

            <div class="alert alert-info">
                <div class="fs-5 fw-bold">{{ $user->name }}</div>
                <div class="text-muted">{{ $user->email }}</div>
            </div>

            {{-- จุดสำคัญของหน้านี้: แอดมินเปิดให้ แต่ไม่ควรเป็นคนพิมพ์
                 ข้อ 3 ของหนังสือห้ามบอกรหัสผ่านแก่ผู้อื่น --}}
            <div class="alert alert-warning">
                <b>ยื่นคีย์บอร์ดให้เจ้าตัวพิมพ์เองนะคะ</b><br>
                <span class="small">
                    ผู้ดูแลระบบไม่ควรรู้รหัสผ่านของคนอื่น (ข้อ 3 ของหนังสือ สธ 0418.17/719)
                    หน้านี้จึงมีแค่ช่องรหัสผ่าน แก้ชื่อหรือสิทธิ์ไม่ได้ กดโดนก็ไม่มีอะไรเสียหาย
                </span>
            </div>

            <form method="POST" action="{{ route('password.for-user.update', $user->id) }}" autocomplete="off">
                @csrf
                @include('password._fields')
                <div class="d-flex gap-2">
                    <a href="{{ route('password.status') }}" class="btn btn-outline-secondary btn-lg">ยกเลิก</a>
                    <button type="submit" class="btn btn-danger btn-lg flex-fill" id="pwSubmit" disabled>
                        บันทึกรหัสผ่านใหม่
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
