@extends('backend.layouts.app')

@section('title', 'Users')

@section('content')
    <div class="admin-hero p-4 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h4 class="mb-1 fw-bold"><i class="fas fa-users-gear me-2"></i>ผู้ใช้งานระบบ (Users)</h4>
            <div class="opacity-75 small">{{ $result->total() }} {{ Str::plural('User', $result->count()) }}</div>
        </div>
        @can('add_users')
            <a href="{{ route('users.create') }}" class="btn btn-light fw-bold shadow-sm"><i class="fas fa-user-plus text-success me-1"></i> เพิ่มผู้ใช้</a>
        @endcan
    </div>

    <div class="result-set card admin-card">
      <div class="card-body p-0">
        <table class="table admin-table table-hover align-middle mb-0" id="data-table">
            <thead>
            <tr>
                <th>Id</th>
                <th>Name</th>
                <th>Email</th>
                <th>License Number</th> {{-- เพิ่มคอลัมน์ License Number --}}
                <th>Role</th>
                <th>Created At</th>
                @can('edit_users', 'delete_users')
                <th class="text-center">Actions</th>
                @endcan
            </tr>
            </thead>
            <tbody>
            @foreach($result as $item)
                <tr>
                    <td>{{ $item->id }}</td>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->email }}</td>
                    <td>{{ $item->license_number ?? '-' }}</td> {{-- เพิ่มข้อมูล License Number --}}
                    <td>{{ $item->roles->implode('name', ', ') }}</td>
                    <td>{{ $item->created_at->toFormattedDateString() }}</td>

                    @can('edit_users')
                    <td class="text-center">
                        @include('shared._actions', [
                            'entity' => 'users',
                            'id' => $item->id
                        ])
                    </td>
                    @endcan
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="d-flex justify-content-center py-3">
            {{ $result->links() }}
        </div>
      </div>
    </div>

@endsection