<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            บันทึกผลติดตาม — {{ $plan->referral->patient->name }}
            <span class="text-gray-400 text-base font-normal">ครั้งที่ {{ $plan->plan_number }}</span>
        </h2>
    </x-slot>

    @php
        $record = $plan->record;
        $analysis = $record->ai_analysis;
        $isConfirmed = $record->isConfirmed();
    @endphp

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($errors->any())
                <div class="p-4 rounded bg-red-50 text-red-700 text-sm">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (session('error'))
                <div class="p-4 rounded bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-3">ผลติดตามที่บันทึกไว้</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm mb-4">
                    <div>
                        <dt class="text-gray-500">วัน-เวลาที่ติดตาม</dt>
                        <dd class="font-medium text-gray-900">{{ $record->visited_at->format('d/m/Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">PPS Score</dt>
                        <dd class="font-medium text-gray-900">{{ $record->pps_score ?? '—' }}</dd>
                    </div>
                </dl>
                <dt class="text-gray-500 text-sm mb-1">อาการ/ปัญหาที่พบ</dt>
                <dd class="text-gray-900 whitespace-pre-line bg-gray-50 rounded-md p-4 text-sm">{{ $record->raw_notes }}</dd>
            </div>

            @if ($analysis && ! ($analysis['parse_error'] ?? false))
                <div class="border-2 border-dashed border-teal-300 bg-teal-50/40 rounded-lg p-5">
                    <span class="inline-block text-xs font-bold uppercase tracking-wide text-teal-800 bg-teal-100 rounded-full px-3 py-1 mb-4">
                        ผลวิเคราะห์จาก AI — รอพยาบาลยืนยัน
                    </span>
                    <div class="space-y-2 text-sm">
                        <div>
                            <span class="font-medium text-gray-700">พบสัญญาณเสี่ยง:</span>
                            @if ($analysis['risk_detected'] ?? false)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700 ml-1">พบ</span>
                                <span class="text-gray-700"> — {{ $analysis['risk_summary'] }}</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 ml-1">ไม่พบ</span>
                            @endif
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">คำแนะนำเบื้องต้น:</span>
                            <span class="text-gray-700">{{ $analysis['recommendation'] }}</span>
                        </div>
                    </div>
                </div>
            @elseif ($analysis['parse_error'] ?? false)
                <div class="p-4 rounded bg-amber-50 text-amber-800 text-sm">
                    AI ไม่สามารถแปลผลลัพธ์เป็นข้อมูลที่ใช้ได้ในครั้งนี้ กรุณาตัดสินใจด้วยตนเองด้านล่าง หรือลองขอวิเคราะห์ใหม่
                </div>
            @endif

            @if (! $isConfirmed)
                <form method="POST" action="{{ route('follow-up-plans.analyze', $plan) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-white border border-teal-300 text-teal-800 rounded-md text-sm font-semibold hover:bg-teal-50">
                        {{ $analysis ? '↻ ให้ AI วิเคราะห์ใหม่' : 'ให้ AI วิเคราะห์ผล' }}
                    </button>
                </form>
            @endif

            @if ($isConfirmed)
                <div class="bg-white border-2 border-teal-600 rounded-lg p-5">
                    <span class="inline-block text-xs font-bold uppercase tracking-wide text-teal-800 bg-teal-100 rounded-full px-3 py-1 mb-3">
                        ยืนยันแล้วโดย {{ $record->confirmer->name }} เมื่อ {{ $record->confirmed_at->format('d/m/Y H:i') }}
                    </span>
                    <p class="text-sm text-gray-700">
                        การตัดสินใจ:
                        <strong>
                            {{ match($record->nurse_decision) {
                                'repeat' => 'ติดตามซ้ำ',
                                'refer' => 'ส่งต่อแพทย์',
                                'close' => 'ปิดเคส',
                                default => $record->nurse_decision,
                            } }}
                        </strong>
                    </p>
                    @if ($record->decision_notes)
                        <p class="text-sm text-gray-600 mt-1">{{ $record->decision_notes }}</p>
                    @endif
                </div>
            @else
                <div class="border-2 border-teal-600 rounded-lg p-5 bg-white">
                    <span class="inline-block text-xs font-bold uppercase tracking-wide text-gray-700 bg-gray-100 rounded-full px-3 py-1 mb-4">
                        การตัดสินใจของพยาบาล — ต้องยืนยันเสมอ
                    </span>

                    <form method="POST" action="{{ route('follow-up-plans.decision', $plan) }}" class="space-y-4">
                        @csrf
                        @php $suggested = $analysis['suggested_decision'] ?? null; @endphp

                        <div class="flex flex-wrap gap-3">
                            @foreach (['repeat' => 'ติดตามซ้ำ', 'refer' => 'ส่งต่อแพทย์', 'close' => 'ปิดเคส'] as $value => $label)
                                <label class="border rounded-lg px-4 py-2 text-sm font-semibold cursor-pointer
                                    {{ old('nurse_decision', $suggested) === $value ? 'border-teal-600 bg-teal-50 text-teal-800' : 'border-gray-300 text-gray-700' }}">
                                    <input type="radio" name="nurse_decision" value="{{ $value }}" class="mr-1"
                                           @checked(old('nurse_decision', $suggested) === $value)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                หมายเหตุ <span class="text-gray-400 font-normal">(เช่น ส่งต่อถึงใคร/แผนกไหน)</span>
                            </label>
                            <textarea name="decision_notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300">{{ old('decision_notes', $analysis['recommendation'] ?? '') }}</textarea>
                        </div>

                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="risk_flag" value="1"
                                   @checked(old('risk_flag', $analysis['risk_detected'] ?? false))>
                            ยืนยันว่าพบสัญญาณเสี่ยงจริง
                        </label>

                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-teal-700 text-white rounded-md text-sm font-semibold hover:bg-teal-800">
                            ยืนยันการตัดสินใจ
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
