<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">จัดการประเภทเคส &amp; เกณฑ์การเยี่ยม</h2>
            <a href="{{ route('admin.case-types.create') }}" class="inline-flex items-center px-4 py-2 bg-teal-700 text-white rounded-md text-sm font-semibold hover:bg-teal-800">
                + เพิ่มประเภทเคส
            </a>
        </div>
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
                            <th class="px-4 py-3 text-left font-semibold text-gray-500 uppercase text-xs">เกณฑ์</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-500 uppercase text-xs">สถานะ</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($caseTypes as $caseType)
                            @php $rule = $caseType->visitRules->firstWhere('is_active', true); @endphp
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ $caseType->name }}</div>
                                    <div class="text-gray-400 text-xs">{{ $caseType->slug }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    @if (! $rule)
                                        <span class="text-amber-600">ยังไม่ตั้งเกณฑ์</span>
                                    @elseif ($rule->rule_type === 'fixed_count')
                                        {{ $rule->fixed_visit_count }} ครั้ง ห่างกันครั้งละ {{ $rule->fixed_interval_days }} วัน
                                    @else
                                        อิงคะแนน ({{ count($rule->score_rules ?? []) }} ช่วง)
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                        {{ $caseType->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $caseType->is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.case-types.edit', $caseType) }}" class="text-teal-700 hover:underline font-medium">แก้ไข</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
