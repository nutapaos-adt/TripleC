<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-[#22201A] leading-tight">
            บันทึกผลติดตาม — {{ $plan->referral->patient->name }}
            <span class="text-[#7C7863] text-base font-normal">ครั้งที่ {{ $plan->plan_number }} · {{ $plan->method === 'home_visit' ? 'เยี่ยมบ้าน' : 'โทรติดตาม' }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($errors->any())
                <div class="p-4 rounded bg-[#F7E7E2] text-[#B23B2C] text-sm">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('follow-up-plans.record.store', $plan) }}" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[#4A4739]">วัน-เวลาที่ติดตาม</label>
                            <input type="datetime-local" name="visited_at"
                                   value="{{ old('visited_at', now()->format('Y-m-d\TH:i')) }}"
                                   class="mt-1 block w-full rounded-md bg-[#F1EEE0] border-[#C9C4AD] focus:border-[#2C5166] focus:ring-[#2C5166]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#4A4739]">
                                PPS Score <span class="text-[#7C7863] font-normal">(กรอกเฉพาะกรณี Palliative Care)</span>
                            </label>
                            <input type="number" name="pps_score" min="0" max="100" value="{{ old('pps_score') }}"
                                   class="mt-1 block w-32 rounded-md bg-[#F1EEE0] border-[#C9C4AD] focus:border-[#2C5166] focus:ring-[#2C5166]">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[#4A4739]">อาการ / ปัญหาที่พบ</label>
                        <textarea name="raw_notes" rows="6" required
                                  class="mt-1 block w-full rounded-md bg-[#F1EEE0] border-[#C9C4AD] focus:border-[#2C5166] focus:ring-[#2C5166]">{{ old('raw_notes') }}</textarea>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-[#2C5166] text-white rounded-md text-sm font-semibold hover:bg-[#3D6B84]">
                            บันทึกผลติดตาม
                        </button>
                        <a href="{{ route('follow-up-plans.guide', $plan) }}" class="text-sm text-[#7C7863] hover:text-[#4A4739]">กลับไปดูคู่มือติดตาม</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
