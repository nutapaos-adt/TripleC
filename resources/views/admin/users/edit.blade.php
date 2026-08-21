<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">แก้ไขสิทธิ์ผู้ใช้ — {{ $user->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                @if ($errors->any())
                    <div class="mb-4 p-4 rounded bg-red-50 text-red-700 text-sm">
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
                        <label class="block text-sm font-medium text-gray-700">อีเมล</label>
                        <p class="mt-1 text-gray-900">{{ $user->email }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">สิทธิ์การใช้งาน</label>
                        <select name="role" class="mt-1 block w-full rounded-md border-gray-300">
                            @foreach (\App\Models\User::ROLES as $value => $label)
                                <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">แผนก</label>
                        <input type="text" name="department" value="{{ old('department', $user->department) }}"
                               class="mt-1 block w-full rounded-md border-gray-300">
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-teal-700 text-white rounded-md text-sm font-semibold hover:bg-teal-800">
                            บันทึก
                        </button>
                        <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-500 hover:text-gray-700">ยกเลิก</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
