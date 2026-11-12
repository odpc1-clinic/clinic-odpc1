<div class="form-group @if ($errors->has('name')) has-error @endif">
    <label for="name">Name</label>
    <input type="text" name="name" id="name" class="form-control" placeholder="Name" value="{{ old('name', $user->name ?? '') }}">
    @if ($errors->has('name')) <p class="help-block">{{ $errors->first('name') }}</p> @endif
</div>

{{-- ชื่อภาษาอังกฤษ (ห้อง Lab ขอ 2569-10-16) — ใช้ในใบผล Lab ภาษาอังกฤษ · ว่าง = ใช้ชื่อไทย --}}
<div class="form-group @if ($errors->has('name_en')) has-error @endif">
    <label for="name_en">Name (English) — ชื่อภาษาอังกฤษ สำหรับใบผล Lab ภาษาอังกฤษ</label>
    <input type="text" name="name_en" id="name_en" class="form-control" placeholder="เช่น Pichaya Tantrakul, MT.11420" value="{{ old('name_en', $user->name_en ?? '') }}">
    @if ($errors->has('name_en')) <p class="help-block">{{ $errors->first('name_en') }}</p> @endif
</div>

<div class="form-group @if ($errors->has('email')) has-error @endif">
    <label for="email">Email</label>
    <input type="text" name="email" id="email" class="form-control" placeholder="Email" value="{{ old('email', $user->email ?? '') }}">
    @if ($errors->has('email')) <p class="help-block">{{ $errors->first('email') }}</p> @endif
</div>

<div class="form-group @if ($errors->has('license_number')) has-error @endif">
    <label for="license_number">License Number (ใบประกอบโรคศิลป์)</label>
    <input type="text" name="license_number" id="license_number" class="form-control" placeholder="เช่น ว.56349" value="{{ old('license_number', $user->license_number ?? '') }}">
    @if ($errors->has('license_number')) <p class="help-block">{{ $errors->first('license_number') }}</p> @endif
</div>

<div class="form-group @if ($errors->has('password')) has-error @endif">
    <label for="password">Password</label>
    <input type="password" name="password" id="password" class="form-control" placeholder="Password">
    @if ($errors->has('password')) <p class="help-block">{{ $errors->first('password') }}</p> @endif
</div>

<div class="form-group @if ($errors->has('roles')) has-error @endif">
    <label for="roles">Roles</label>
    @php $__selRoles = collect(old('roles', isset($user) ? $user->roles->pluck('id')->toArray() : []))->map(fn ($x) => (string) $x)->all(); @endphp
    <select name="roles[]" id="roles" class="form-control" multiple>
        @foreach($roles as $__rid => $__rname)
            <option value="{{ $__rid }}" {{ in_array((string) $__rid, $__selRoles, true) ? 'selected' : '' }}>{{ $__rname }}</option>
        @endforeach
    </select>
    @if ($errors->has('roles')) <p class="help-block">{{ $errors->first('roles') }}</p> @endif
</div>

@if(isset($user))
    @include('shared._permissions', ['closed' => 'true', 'model' => $user ])
@endif