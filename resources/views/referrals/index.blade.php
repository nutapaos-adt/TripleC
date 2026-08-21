<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">รายการใบส่งต่อ</h2>
            <a href="{{ route('referrals.create') }}" class="inline-flex items-center px-4 py-2 bg-teal-700 text-white rounded-md text-sm font-semibold hover:bg-teal-800">
                + รับเคสใหม่
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="mb-4 p-4 rounded bg-green-50 text-green-700 text-sm">{{ session('status') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-500 uppercase text-xs">ผู้ป่วย</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-500 uppercase text-xs">แหล่งข้อมูล</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-500 uppercase text-xs">ประเภทเคส</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-500 uppercase text-xs">เขต</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-500 uppercase text-xs">สถานะ</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-500 uppercase text-xs">วันที่รับเคส</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($referrals as $referral)
                            <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('referrals.show', $referral) }}'">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ $referral->patient->name }}</div>
                                    <div class="text-gray-400 text-xs">HN {{ $referral->patient->hn }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $referral->source_detail ?: match($referral->source_type) {
                                    'ward' => 'หอผู้ป่วย',
                                    'opd' => 'OPD',
                                    'internal_dept' => 'หน่วยงานภายใน รพ.',
                                    'external_hospital' => 'โรงพยาบาลอื่น',
                                    default => $referral->source_type,
                                } }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $referral->caseType?->name ?? '— รอ AI ประเมิน —' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                        {{ $referral->zone === 'in_area' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $referral->zone === 'in_area' ? 'ในเขต' : 'นอกเขต' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ match($referral->status) {
                                        'pending_review' => 'รอตรวจสอบ',
                                        'plan_confirmed' => 'ยืนยันแผนแล้ว',
                                        'in_progress' => 'กำลังติดตาม',
                                        'closed' => 'ปิดเคสแล้ว',
                                        default => $referral->status,
                                    } }}
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $referral->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400">ยังไม่มีใบส่งต่อในระบบ</td>
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
