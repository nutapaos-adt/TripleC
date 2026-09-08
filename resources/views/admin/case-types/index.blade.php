<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-[#22201A] leading-tight">จัดการประเภทเคส &amp; เกณฑ์การเยี่ยม</h2>
            <a href="{{ route('admin.case-types.create') }}" class="inline-flex items-center px-4 py-2 bg-[#2C5166] text-white rounded-md text-sm font-semibold hover:bg-[#3D6B84]">
                + เพิ่มประเภทเคส
            </a>
        </div>
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
                            <th class="px-4 py-3 text-left font-semibold text-[#7C7863] uppercase text-xs">เกณฑ์</th>
                            <th class="px-4 py-3 text-left font-semibold text-[#7C7863] uppercase text-xs">สถานะ</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F1EEE0]">
                        @foreach ($caseTypes as $caseType)
                            @php $rule = $caseType->visitRules->firstWhere('is_active', true); @endphp
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-[#22201A]">{{ $caseType->name }}</div>
                                    <div class="text-[#7C7863] text-xs">{{ $caseType->slug }}</div>
                                </td>
                                <td class="px-4 py-3 text-[#4A4739]">
                                    @if (! $rule)
                                        <span class="text-[#C2891F]">ยังไม่ตั้งเกณฑ์</span>
                                    @elseif ($rule->rule_type === 'fixed_count')
                                        {{ $rule->fixed_visit_count }} ครั้ง ห่างกันครั้งละ {{ $rule->fixed_interval_days }} วัน
                                    @else
                                        อิงคะแนน ({{ count($rule->score_rules ?? []) }} ช่วง)
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                        {{ $caseType->is_active ? 'bg-[#E5F2E4] text-[#3E8E49]' : 'bg-[#F1EEE0] text-[#7C7863]' }}">
                                        {{ $caseType->is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.case-types.edit', $caseType) }}" class="text-[#2C5166] hover:underline font-medium">แก้ไข</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
