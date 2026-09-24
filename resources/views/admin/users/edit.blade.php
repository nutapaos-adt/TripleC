<x-app-layout>
    <x-slot name="header">แก้ไขสิทธิ์ผู้ใช้ — {{ $user->name }}</x-slot>

    <div class="page-head">
        <h1 class="h1">แก้ไขสิทธิ์ผู้ใช้ — {{ $user->name }}</h1>
    </div>

    <div class="card">
        <div class="card-body" style="padding-top:var(--space-5);">
            @if ($errors->any())
                <div class="banner" style="background:var(--color-risk-tint);border-color:var(--color-risk);margin-bottom:var(--space-5);">
                    <div class="banner-text">
                        <ul style="margin:0;padding-left:18px;color:var(--color-risk);">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PUT')

                <div class="field">
                    <label>อีเมล</label>
                    <div class="field-value">{{ $user->email }}</div>
                </div>

                <div class="field">
                    <label>สิทธิ์การใช้งาน</label>
                    <select name="role" class="input">
                        @foreach (\App\Models\User::ROLES as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label>แผนก</label>
                    <input type="text" name="department" value="{{ old('department', $user->department) }}" class="input">
                </div>

                <div class="field">
                    <label>วอร์ด</label>
                    <select name="ward_id" class="input">
                        <option value="" @selected(! old('ward_id', $user->ward_id))>— ไม่ระบุ —</option>
                        @foreach (\App\Models\Ward::orderBy('name')->get() as $ward)
                            <option value="{{ $ward->id }}" @selected((int) old('ward_id', $user->ward_id) === $ward->id)>{{ $ward->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
