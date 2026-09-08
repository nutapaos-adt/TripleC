<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-[#22201A] leading-tight">
            คู่มือติดตาม — {{ $plan->referral->patient->name }}
            <span class="text-[#7C7863] text-base font-normal">ครั้งที่ {{ $plan->plan_number }} · กำหนด {{ $plan->due_date->format('d/m/Y') }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('error'))
                <div class="p-4 rounded bg-[#F7E7E2] text-[#B23B2C] text-sm">{{ session('error') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center justify-between gap-4">
                <div>
                    <div class="font-semibold text-[#22201A]">{{ $plan->referral->patient->name }}</div>
                    <div class="text-sm text-[#7C7863]">HN {{ $plan->referral->patient->hn }} · {{ $plan->referral->caseType?->name }}</div>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#E1EBEF] text-[#2C5166]">
                    {{ $plan->method === 'home_visit' ? 'เยี่ยมบ้าน' : 'โทรติดตาม' }}
                </span>
            </div>

            @if ($plan->ai_guide && ! ($plan->ai_guide['parse_error'] ?? false))
                <div class="border-2 border-dashed border-[#A8C4D2] bg-[#F4F8FA] rounded-lg p-5">
                    <span class="inline-block text-xs font-bold uppercase tracking-wide text-[#2C5166] bg-[#E1EBEF] rounded-full px-3 py-1 mb-4">
                        ร่างจาก AI — ยังไม่ยืนยัน · หัวข้อที่แนะนำให้ประเมินครั้งนี้
                    </span>
                    <ul class="space-y-3">
                        @foreach ($plan->ai_guide['topics'] ?? [] as $topic)
                            <li class="flex items-start gap-3 bg-white rounded-md border border-[#DEDAC6] p-3">
                                <input type="checkbox" class="mt-1">
                                <div>
                                    <div class="font-medium text-[#22201A] text-sm">{{ $topic['title'] ?? '' }}</div>
                                    @if (! empty($topic['note']))
                                        <div class="text-xs text-[#7C7863] mt-0.5">{{ $topic['note'] }}</div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <p class="text-xs text-[#7C7863] mt-4">รายการนี้เป็นเพียงแนวทางช่วยจำ ไม่ได้บันทึกลงระบบ — ผลการประเมินจริงให้กรอกในหน้าบันทึกผลติดตาม</p>
                </div>
            @elseif ($plan->ai_guide['parse_error'] ?? false)
                <div class="p-4 rounded bg-[#FBF0DC] text-[#C2891F] text-sm">
                    AI ไม่สามารถแปลผลลัพธ์เป็นหัวข้อที่ใช้ได้ในครั้งนี้ ลองขอใหม่อีกครั้ง หรือข้ามไปบันทึกผลติดตามได้เลย
                </div>
            @else
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-sm text-[#7C7863]">
                    ยังไม่มีคู่มือติดตามสำหรับครั้งนี้
                </div>
            @endif

            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('follow-up-plans.guide.generate', $plan) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-white border border-[#C9C4AD] text-[#22201A] rounded-md text-sm font-semibold hover:bg-[#F1EEE0]">
                        {{ $plan->ai_guide ? '↻ ขอให้ AI แนะนำใหม่' : 'ให้ AI แนะนำหัวข้อประเมิน' }}
                    </button>
                </form>
                <a href="{{ route('follow-up-plans.record.create', $plan) }}" class="inline-flex items-center px-4 py-2 bg-[#2C5166] text-white rounded-md text-sm font-semibold hover:bg-[#3D6B84]">
                    เริ่มบันทึกผลติดตาม
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
