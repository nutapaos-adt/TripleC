<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ภาพรวมทีมเยี่ยมบ้าน</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <div class="text-xs text-gray-500 mb-1">ผู้ป่วยทั้งหมดในความดูแล</div>
                    <div class="text-3xl font-bold text-gray-900">{{ $totalPatients }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <div class="text-xs text-gray-500 mb-1">วันนี้ต้องติดตาม</div>
                    <div class="text-3xl font-bold text-gray-900">{{ $dueTodayCount }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <div class="text-xs text-gray-500 mb-1">เกินกำหนดติดตาม</div>
                    <div class="text-3xl font-bold {{ $overdueCount > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $overdueCount }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <div class="text-xs text-gray-500 mb-1">กลุ่มเสี่ยง (ยืนยันแล้ว)</div>
                    <div class="text-3xl font-bold {{ $riskCount > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $riskCount }}</div>
                </div>
            </div>

            @if ($pendingReviewCount > 0)
                <div class="p-4 rounded bg-amber-50 text-amber-800 text-sm">
                    มีใบส่งต่อ {{ $pendingReviewCount }} รายการที่ยังไม่ได้ให้ AI สรุป/ยืนยันแผน —
                    <a href="{{ route('referrals.index') }}" class="underline font-medium">ไปดูรายการใบส่งต่อ</a>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">รายการที่ต้องติดตามวันนี้/เกินกำหนด</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-500 uppercase text-xs">ผู้ป่วย</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-500 uppercase text-xs">ประเภทเคส</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-500 uppercase text-xs">เขต</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-500 uppercase text-xs">วิธีติดตาม</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-500 uppercase text-xs">กำหนด</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($upcomingPlans as $plan)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $plan->referral->patient->name }}</div>
                                        <div class="text-gray-400 text-xs">HN {{ $plan->referral->patient->hn }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $plan->referral->caseType?->name ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                            {{ $plan->referral->zone === 'in_area' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $plan->referral->zone === 'in_area' ? 'ในเขต' : 'นอกเขต' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $plan->method === 'home_visit' ? 'เยี่ยมบ้าน' : 'โทรติดตาม' }}</td>
                                    <td class="px-4 py-3">
                                        @if ($plan->isOverdue())
                                            <span class="text-red-600 font-medium">เกินกำหนด ({{ $plan->due_date->format('d/m/Y') }})</span>
                                        @else
                                            <span class="text-gray-600">วันนี้</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('follow-up-plans.guide', $plan) }}" class="text-teal-700 hover:underline font-medium">เริ่มติดตาม →</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">ไม่มีเคสที่ต้องติดตามวันนี้</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">สัญญาณเสี่ยงล่าสุดที่ยืนยันแล้ว</h3>
                </div>
                <div class="p-6 space-y-3">
                    @forelse ($recentRiskRecords as $record)
                        <div class="flex items-start gap-3 text-sm">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700 mt-0.5">เสี่ยง</span>
                            <div>
                                <span class="font-medium text-gray-900">{{ $record->plan->referral->patient->name }}</span>
                                <span class="text-gray-600"> — {{ Str::limit($record->decision_notes ?: $record->raw_notes, 120) }}</span>
                                <div class="text-xs text-gray-400 mt-0.5">ยืนยันเมื่อ {{ $record->confirmed_at?->format('d/m/Y H:i') }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-gray-400 text-sm">ยังไม่มีสัญญาณเสี่ยงที่ยืนยันแล้ว</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
