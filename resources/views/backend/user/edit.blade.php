@extends('backend.layouts.app')

@section('title', 'Edit User ' . $user->first_name)

@section('content')

    <div class="admin-hero p-4 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <h4 class="mb-0 fw-bold"><i class="fas fa-user-pen me-2"></i>แก้ไขผู้ใช้ {{ $user->name }}</h4>
        <a href="{{ route('users.index') }}" class="btn btn-light fw-bold shadow-sm"><i class="fa fa-arrow-left me-1"></i> กลับ</a>
    </div>

    <div class="card admin-card">
        <div class="card-body">
            <form method="POST" action="{{ route('users.update', $user->id) }}">
                @csrf
                @method('PUT')
                @include('backend.user._form')
                <!-- Submit Form Button -->
                <button type="submit" class="btn btn-primary mt-2">Save Changes</button>
            </form>
        </div>
    </div>
@endsection