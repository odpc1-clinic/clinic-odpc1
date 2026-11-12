@extends('backend.layouts.app')

@section('title', 'Roles & Permissions')

@section('content')

    <!-- Modal -->
    <div class="modal fade" id="roleModal" tabindex="-1" role="dialog" aria-labelledby="roleModalLabel">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('roles.store') }}">
            @csrf

            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="roleModalLabel">Role</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- name Form Input -->
                    <div class="form-group @if ($errors->has('name')) has-error @endif">
                        <label for="name">Name</label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="Role Name" value="{{ old('name') }}">
                        @if ($errors->has('name')) <p class="help-block">{{ $errors->first('name') }}</p> @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>

                    <!-- Submit Form Button -->
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
                </form>
            </div>
        </div>
    </div>

    <div class="admin-hero p-4 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h4 class="mb-1 fw-bold"><i class="fas fa-user-shield me-2"></i>บทบาท & สิทธิ์ (Roles &amp; Permissions)</h4>
            <div class="opacity-75 small">กำหนดสิทธิ์การเข้าถึงของแต่ละบทบาท</div>
        </div>
        @can('add_roles')
            <a href="#" class="btn btn-light fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#roleModal"><i class="fas fa-plus text-success me-1"></i> เพิ่มบทบาท</a>
        @endcan
    </div>

    @forelse ($roles as $role)
        <div class="card admin-card mb-3">
            <div class="card-body">
                <form method="POST" action="{{ route('roles.update', $role->id) }}" class="m-b">
                @csrf
                @method('PUT')

                @if($role->name === 'Admin')
                    @include('shared._permissions', [
                                  'title' => $role->name .' Permissions',
                                  'options' => ['disabled'] ])
                @else
                    @include('shared._permissions', [
                                  'title' => $role->name .' Permissions',
                                  'model' => $role ])
                    @can('edit_roles')
                        <button type="submit" class="btn btn-primary mt-2">Save</button>
                    @endcan
                @endif

                </form>
            </div>
        </div>

    @empty
        <p>No Roles defined, please run <code>php artisan db:seed</code> to seed some dummy data.</p>
    @endforelse






@endsection
