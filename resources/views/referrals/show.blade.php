<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-[#22201A] leading-tight">
            ใบส่งต่อ — {{ $referral->patient->name }}
            <span class="text-[#7C7863] text-base font-normal">(HN {{ $referral->patient->hn }})</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-4 rounded bg-[#E5F2E4] text-[#3E8E49] text-sm">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="p-4 rounded bg-[#F7E7E2] text-[#B23B2C] text-sm">{{ session('error') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-wrap gap-2 mb-4">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                        {{ $referral->zone === 'in_area' ? 'bg-[#E1EBEF] text-[#2C5166]' : 'bg-[#F1EEE0] text-[#4A4739]' }}">
                        {{ $referral->zone === 'in_area' ? 'ในเขต' : 'นอกเขต' }}
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#E1EBEF] text-[#2C5166]">
                        {{ $referral->caseType?->name ?? 'รอ AI ประเมินประเภทเคส' }}
                    </span>
                </div>

                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-[#7C7863]">แหล่งข้อมูล</dt>
                        <dd class="font-medium text-[#22201A]">
                            {{ match($referral->source_type) {
                                'ward' => 'หอผู้ป่วย',
                                'opd' => 'OPD',
                                'internal_dept' => 'หน่วยงานภายใน รพ.',
                                'external_hospital' => 'โรงพยาบาลอื่น',
                                default => $referral->source_type,
                            } }}
                            @if ($referral->source_detail) — {{ $referral->source_detail }} @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[#7C7863]">ส่งข้อมูลโดย</dt>
                        <dd class="font-medium text-[#22201A]">{{ $referral->creator->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[#7C7863]">ที่อยู่</dt>
                        <dd class="font-medium text-[#22201A]">
                            {{ $referral->patient->address }}
                            @if ($referral->patient->sub_district) ต.{{ $referral->patient->sub_district }} @endif
                            @if ($referral->patient->district) อ.{{ $referral->patient->district }} @endif
                            @if ($referral->patient->province) จ.{{ $referral->patient->province }} @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[#7C7863]">เบอร์โทร</dt>
                        <dd class="font-medium text-[#22201A]">{{ $referral->patient->phone ?: '—' }}</dd>
                    </div>
                </dl>

                <div class="mt-6">
                    <dt class="text-[#7C7863] text-sm mb-1">ข้อความสรุปอาการ / สถานการณ์</dt>
                    <dd class="text-[#22201A] whitespace-pre-line bg-[#F1EEE0] rounded-md p-4 text-sm">{{ $referral->raw_notes }}</dd>
                </div>

                @if ($referral->attachments->isNotEmpty())
                    <div class="mt-6">
                        <dt class="text-[#7C7863] text-sm mb-2">เอกสารแนบ</dt>
                        <ul class="space-y-2">
                            @foreach ($referral->attachments as $attachment)
                                <li>
                                    <a href="{{ route('referrals.attachments.download', [$referral, $attachment]) }}"
                                       class="inline-flex items-center gap-2 text-sm text-[#2C5166] hover:underline">
                                        📄 {{ $attachment->original_name }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="bg-[#F4F8FA] border border-dashed border-[#A8C4D2] rounded-lg p-4 text-sm text-[#2C5166] flex items-center justify-between gap-4">
                @if ($referral->isConfirmed())
                    <span>ยืนยันแผนติดตามแล้วโดย {{ $referral->confirmer->name }} เมื่อ {{ $referral->confirmed_at->format('d/m/Y H:i') }}</span>
                    <a href="{{ route('referrals.care-plan', $referral) }}" class="shrink-0 inline-flex items-center px-3 py-1.5 bg-white border border-[#C9C4AD] text-[#22201A] rounded-md text-sm font-semibold hover:bg-[#F1EEE0]">
                        ดูแผนการดูแล
                    </a>
                @elseif ($referral->ai_summary)
                    <span>AI สรุปข้อมูลแล้ว รอพยาบาลตรวจสอบและยืนยัน</span>
                    <a href="{{ route('referrals.care-plan', $referral) }}" class="shrink-0 inline-flex items-center px-3 py-1.5 bg-[#2C5166] text-white rounded-md text-sm font-semibold hover:bg-[#3D6B84]">
                        วิเคราะห์แผนการพยาบาล
                    </a>
                @else
                    <span>ขั้นตอนถัดไป: ให้ AI ช่วยสรุปข้อมูลและแนะนำแผนติดตาม</span>
                    <form method="POST" action="{{ route('referrals.ai-summary', $referral) }}">
                        @csrf
                        <button type="submit" class="shrink-0 inline-flex items-center px-3 py-1.5 bg-[#2C5166] text-white rounded-md text-sm font-semibold hover:bg-[#3D6B84]">
                            ให้ AI ช่วยสรุปข้อมูล
                        </button>
                    </form>
                @endif
            </div>

            @if ($referral->followUpPlans->isNotEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-[#4A4739] uppercase tracking-wide mb-3">กำหนดการติดตาม</h3>
                    <ul class="divide-y divide-[#F1EEE0]">
                        @foreach ($referral->followUpPlans->sortBy('plan_number') as $plan)
                            <li class="py-3 flex items-center justify-between text-sm">
                                <div>
                                    <span class="font-medium text-[#22201A]">ครั้งที่ {{ $plan->plan_number }}</span>
                                    <span class="text-[#7C7863]">— {{ $plan->method === 'home_visit' ? 'เยี่ยมบ้าน' : 'โทรติดตาม' }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-[#7C7863] tabular-nums">กำหนด {{ $plan->due_date->format('d/m/Y') }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                        {{ match(true) {
                                            $plan->status === 'done' => 'bg-[#E5F2E4] text-[#3E8E49]',
                                            $plan->isOverdue() => 'bg-[#F7E7E2] text-[#B23B2C]',
                                            default => 'bg-[#FBF0DC] text-[#C2891F]',
                                        } }}">
                                        {{ match(true) {
                                            $plan->status === 'done' => 'เยี่ยมแล้ว',
                                            $plan->isOverdue() => 'เกินกำหนด',
                                            default => 'รอถึงกำหนด',
                                        } }}
                                    </span>
                                    @if ($plan->status !== 'done')
                                        <a href="{{ route('follow-up-plans.record.create', $plan) }}" class="text-[#2C5166] hover:underline font-medium">
                                            เริ่มติดตาม →
                                        </a>
                                    @elseif ($plan->record && ! $plan->record->isConfirmed())
                                        <a href="{{ route('follow-up-plans.review', $plan) }}" class="text-[#C2891F] hover:underline font-medium">
                                            รอวิเคราะห์/ยืนยัน →
                                        </a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
