<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">จัดการผู้ใช้งาน</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="p-4 rounded bg-green-50 text-green-700 text-sm">{{ session('status') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-500 uppercase text-xs">ชื่อ</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-500 uppercase text-xs">อีเมล</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-500 uppercase text-xs">สิทธิ์การใช้งาน</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-500 uppercase text-xs">แผนก</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($users as $user)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $user->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-teal-50 text-teal-700">
                                        {{ $user->roleLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $user->department ?: '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="text-teal-700 hover:underline font-medium">แก้ไขสิทธิ์</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
