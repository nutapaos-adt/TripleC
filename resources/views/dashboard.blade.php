<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-[#22201A] leading-tight">ภาพรวมทีมเยี่ยมบ้าน</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <div class="text-xs text-[#7C7863] mb-1">ผู้ป่วยทั้งหมดในความดูแล</div>
                    <div class="text-3xl font-extrabold text-[#22201A] tabular-nums">{{ $totalPatients }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <div class="text-xs text-[#7C7863] mb-1">วันนี้ต้องติดตาม</div>
                    <div class="text-3xl font-extrabold text-[#22201A] tabular-nums">{{ $dueTodayCount }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <div class="text-xs text-[#7C7863] mb-1">เกินกำหนดติดตาม</div>
                    <div class="text-3xl font-extrabold tabular-nums {{ $overdueCount > 0 ? 'text-[#B23B2C]' : 'text-[#22201A]' }}">{{ $overdueCount }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <div class="text-xs text-[#7C7863] mb-1">กลุ่มเสี่ยง (ยืนยันแล้ว)</div>
                    <div class="text-3xl font-extrabold tabular-nums {{ $riskCount > 0 ? 'text-[#B23B2C]' : 'text-[#22201A]' }}">{{ $riskCount }}</div>
                </div>
            </div>

            @if ($pendingReviewCount > 0)
                <div class="p-4 rounded bg-[#FBF0DC] text-[#C2891F] text-sm">
                    มีใบส่งต่อ {{ $pendingReviewCount }} รายการที่ยังไม่ได้ให้ AI สรุป/ยืนยันแผน —
                    <a href="{{ route('referrals.index') }}" class="underline font-medium">ไปดูรายการใบส่งต่อ</a>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-[#DEDAC6]">
                    <h3 class="text-sm font-semibold text-[#4A4739] uppercase tracking-wide">รายการที่ต้องติดตามวันนี้/เกินกำหนด</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[#DEDAC6] text-sm">
                        <thead class="bg-[#F1EEE0]">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-[#7C7863] uppercase text-xs">ผู้ป่วย</th>
                                <th class="px-4 py-3 text-left font-semibold text-[#7C7863] uppercase text-xs">ประเภทเคส</th>
                                <th class="px-4 py-3 text-left font-semibold text-[#7C7863] uppercase text-xs">เขต</th>
                                <th class="px-4 py-3 text-left font-semibold text-[#7C7863] uppercase text-xs">วิธีติดตาม</th>
                                <th class="px-4 py-3 text-left font-semibold text-[#7C7863] uppercase text-xs">กำหนด</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F1EEE0]">
                            @forelse ($upcomingPlans as $plan)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-[#22201A]">{{ $plan->referral->patient->name }}</div>
                                        <div class="text-[#7C7863] text-xs">HN {{ $plan->referral->patient->hn }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-[#4A4739]">{{ $plan->referral->caseType?->name ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                            {{ $plan->referral->zone === 'in_area' ? 'bg-[#E1EBEF] text-[#2C5166]' : 'bg-[#F1EEE0] text-[#4A4739]' }}">
                                            {{ $plan->referral->zone === 'in_area' ? 'ในเขต' : 'นอกเขต' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-[#4A4739]">{{ $plan->method === 'home_visit' ? 'เยี่ยมบ้าน' : 'โทรติดตาม' }}</td>
                                    <td class="px-4 py-3 tabular-nums">
                                        @if ($plan->isOverdue())
                                            <span class="text-[#B23B2C] font-medium">เกินกำหนด ({{ $plan->due_date->format('d/m/Y') }})</span>
                                        @else
                                            <span class="text-[#4A4739]">วันนี้</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('follow-up-plans.record.create', $plan) }}" class="text-[#2C5166] hover:underline font-medium">เริ่มติดตาม →</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-[#7C7863]">ไม่มีเคสที่ต้องติดตามวันนี้</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-[#DEDAC6]">
                    <h3 class="text-sm font-semibold text-[#4A4739] uppercase tracking-wide">สัญญาณเสี่ยงล่าสุดที่ยืนยันแล้ว</h3>
                </div>
                <div class="p-6 space-y-3">
                    @forelse ($recentRiskRecords as $record)
                        <div class="flex items-start gap-3 text-sm">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#F7E7E2] text-[#B23B2C] mt-0.5">เสี่ยง</span>
                            <div>
                                <span class="font-medium text-[#22201A]">{{ $record->plan->referral->patient->name }}</span>
                                <span class="text-[#4A4739]"> — {{ Str::limit($record->decision_notes ?: $record->raw_notes, 120) }}</span>
                                <div class="text-xs text-[#7C7863] mt-0.5">ยืนยันเมื่อ {{ $record->confirmed_at?->format('d/m/Y H:i') }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-[#7C7863] text-sm">ยังไม่มีสัญญาณเสี่ยงที่ยืนยันแล้ว</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
