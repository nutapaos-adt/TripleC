<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-[#22201A] leading-tight">แก้ไขสิทธิ์ผู้ใช้ — {{ $user->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                @if ($errors->any())
                    <div class="mb-4 p-4 rounded bg-[#F7E7E2] text-[#B23B2C] text-sm">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-sm font-medium text-[#4A4739]">อีเมล</label>
                        <p class="mt-1 text-[#22201A]">{{ $user->email }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[#4A4739]">สิทธิ์การใช้งาน</label>
                        <select name="role" class="mt-1 block w-full rounded-md bg-[#F1EEE0] border-[#C9C4AD] focus:border-[#2C5166] focus:ring-[#2C5166]">
                            @foreach (\App\Models\User::ROLES as $value => $label)
                                <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[#4A4739]">แผนก</label>
                        <input type="text" name="department" value="{{ old('department', $user->department) }}"
                               class="mt-1 block w-full rounded-md bg-[#F1EEE0] border-[#C9C4AD] focus:border-[#2C5166] focus:ring-[#2C5166]">
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-[#2C5166] text-white rounded-md text-sm font-semibold hover:bg-[#3D6B84]">
                            บันทึก
                        </button>
                        <a href="{{ route('admin.users.index') }}" class="text-sm text-[#7C7863] hover:text-[#4A4739]">ยกเลิก</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
