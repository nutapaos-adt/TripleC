<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-[#22201A] leading-tight">จัดการผู้ใช้งาน</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="p-4 rounded bg-[#E5F2E4] text-[#3E8E49] text-sm">{{ session('status') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-[#DEDAC6] text-sm">
                    <thead class="bg-[#F1EEE0]">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-[#7C7863] uppercase text-xs">ชื่อ</th>
                            <th class="px-4 py-3 text-left font-semibold text-[#7C7863] uppercase text-xs">อีเมล</th>
                            <th class="px-4 py-3 text-left font-semibold text-[#7C7863] uppercase text-xs">สิทธิ์การใช้งาน</th>
                            <th class="px-4 py-3 text-left font-semibold text-[#7C7863] uppercase text-xs">แผนก</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F1EEE0]">
                        @foreach ($users as $user)
                            <tr>
                                <td class="px-4 py-3 font-medium text-[#22201A]">{{ $user->name }}</td>
                                <td class="px-4 py-3 text-[#4A4739]">{{ $user->email }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#E1EBEF] text-[#2C5166]">
                                        {{ $user->roleLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-[#4A4739]">{{ $user->department ?: '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="text-[#2C5166] hover:underline font-medium">แก้ไขสิทธิ์</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
