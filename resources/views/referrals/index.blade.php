<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-[#22201A] leading-tight">รายการใบส่งต่อ</h2>
            <a href="{{ route('referrals.create') }}" class="inline-flex items-center px-4 py-2 bg-[#2C5166] text-white rounded-md text-sm font-semibold hover:bg-[#3D6B84]">
                + ส่งข้อมูลเยี่ยมบ้าน
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="mb-4 p-4 rounded bg-[#E5F2E4] text-[#3E8E49] text-sm">{{ session('status') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-[#DEDAC6] text-sm">
                    <thead class="bg-[#F1EEE0]">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-[#7C7863] uppercase text-xs">ผู้ป่วย</th>
                            <th class="px-4 py-3 text-left font-semibold text-[#7C7863] uppercase text-xs">แหล่งข้อมูล</th>
                            <th class="px-4 py-3 text-left font-semibold text-[#7C7863] uppercase text-xs">ประเภทเคส</th>
                            <th class="px-4 py-3 text-left font-semibold text-[#7C7863] uppercase text-xs">เขต</th>
                            <th class="px-4 py-3 text-left font-semibold text-[#7C7863] uppercase text-xs">สถานะ</th>
                            <th class="px-4 py-3 text-left font-semibold text-[#7C7863] uppercase text-xs">วันที่ส่งต่อข้อมูล</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F1EEE0]">
                        @forelse ($referrals as $referral)
                            <tr class="hover:bg-[#F4F8FA] cursor-pointer" onclick="window.location='{{ route('referrals.show', $referral) }}'">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-[#22201A]">{{ $referral->patient->name }}</div>
                                    <div class="text-[#7C7863] text-xs">HN {{ $referral->patient->hn }}</div>
                                </td>
                                <td class="px-4 py-3 text-[#4A4739]">{{ $referral->source_detail ?: match($referral->source_type) {
                                    'ward' => 'หอผู้ป่วย',
                                    'opd' => 'OPD',
                                    'internal_dept' => 'หน่วยงานภายใน รพ.',
                                    'external_hospital' => 'โรงพยาบาลอื่น',
                                    default => $referral->source_type,
                                } }}</td>
                                <td class="px-4 py-3 text-[#4A4739]">{{ $referral->caseType?->name ?? '— รอ AI ประเมิน —' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                        {{ $referral->zone === 'in_area' ? 'bg-[#E1EBEF] text-[#2C5166]' : 'bg-[#F1EEE0] text-[#4A4739]' }}">
                                        {{ $referral->zone === 'in_area' ? 'ในเขต' : 'นอกเขต' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-[#4A4739]">
                                    {{ match($referral->status) {
                                        'pending_review' => 'รอตรวจสอบ',
                                        'plan_confirmed' => 'ยืนยันแผนแล้ว',
                                        'in_progress' => 'กำลังติดตาม',
                                        'closed' => 'ปิดเคสแล้ว',
                                        default => $referral->status,
                                    } }}
                                </td>
                                <td class="px-4 py-3 text-[#7C7863] tabular-nums">{{ $referral->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-[#7C7863]">ยังไม่มีใบส่งต่อในระบบ</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $referrals->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
