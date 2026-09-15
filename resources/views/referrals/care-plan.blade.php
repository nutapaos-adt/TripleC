<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-[#22201A] leading-tight">
            แผนการดูแล — {{ $referral->patient->name }}
            <span class="text-[#7C7863] text-base font-normal">(HN {{ $referral->patient->hn }})</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($errors->any())
                <div class="p-4 rounded bg-[#F7E7E2] text-[#B23B2C] text-sm">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @php
                $summary = $referral->confirmed_summary ?? $referral->ai_summary ?? [];
                $riskSignals = $summary['risk_signals'] ?? [];
                $suggestedCaseTypeId = $referral->case_type_id
                    ?? optional($caseTypes->firstWhere('slug', $summary['suggested_case_type_slug'] ?? null))->id;
                $isConfirmed = $referral->isConfirmed();
            @endphp

            @if (($referral->ai_summary['parse_error'] ?? false) && ! $isConfirmed)
                <div class="p-4 rounded bg-[#FBF0DC] text-[#C2891F] text-sm">
                    AI ไม่สามารถแปลผลลัพธ์เป็นข้อมูลที่ใช้ได้ในครั้งนี้ กรุณากรอกข้อมูลด้านล่างด้วยตนเอง หรือลองขอให้ AI สรุปใหม่อีกครั้ง
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <dt class="text-[#7C7863] text-sm mb-1">ข้อมูลต้นทาง (บันทึกดิบก่อน AI สรุป)</dt>
                <dd class="text-[#22201A] whitespace-pre-line bg-[#F1EEE0] rounded-md p-4 text-sm">{{ $referral->raw_notes }}</dd>
                <p class="text-xs text-[#7C7863] mt-2">
                    เทียบกับร่างจาก AI ด้านล่าง เพื่อตรวจว่า AI สรุปครบถ้วนและตรงกับข้อมูลที่ส่งเข้ามาจริงหรือไม่
                </p>
            </div>

            <form method="POST" action="{{ route('referrals.care-plan.confirm', $referral) }}" class="space-y-4">
                @csrf

                <div class="border-2 {{ $isConfirmed ? 'border-[#2C5166] bg-white' : 'border-dashed border-[#A8C4D2] bg-[#F4F8FA]' }} rounded-lg p-5">
                    <span class="inline-block text-xs font-bold uppercase tracking-wide text-[#2C5166] bg-[#E1EBEF] rounded-full px-3 py-1 mb-4">
                        @if ($isConfirmed)
                            ยืนยันแล้วโดย {{ $referral->confirmer->name }}
                        @else
                            ร่างจาก AI — ยังไม่ยืนยัน
                        @endif
                    </span>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-[#4A4739]">ประเภทผู้ป่วย</label>
                            <select name="case_type_id" class="mt-1 block w-full rounded-md bg-[#F1EEE0] border-[#C9C4AD] focus:border-[#2C5166] focus:ring-[#2C5166]">
                                <option value="">— เลือก —</option>
                                @foreach ($caseTypes as $caseType)
                                    <option value="{{ $caseType->id }}" @selected((string) old('case_type_id', $suggestedCaseTypeId) === (string) $caseType->id)>
                                        {{ $caseType->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-[#4A4739]">สรุปสภาพผู้ป่วย</label>
                            <input type="text" name="patient_type" value="{{ old('patient_type', $summary['patient_type'] ?? '') }}"
                                   class="mt-1 block w-full rounded-md bg-[#F1EEE0] border-[#C9C4AD] focus:border-[#2C5166] focus:ring-[#2C5166]">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-[#4A4739]">ปัญหาสำคัญ</label>
                            <textarea name="main_problem" rows="2" class="mt-1 block w-full rounded-md bg-[#F1EEE0] border-[#C9C4AD] focus:border-[#2C5166] focus:ring-[#2C5166]">{{ old('main_problem', $summary['main_problem'] ?? '') }}</textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-[#4A4739]">ความต้องการติดตาม</label>
                            <textarea name="follow_up_need" rows="2" class="mt-1 block w-full rounded-md bg-[#F1EEE0] border-[#C9C4AD] focus:border-[#2C5166] focus:ring-[#2C5166]">{{ old('follow_up_need', $summary['follow_up_need'] ?? '') }}</textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-[#4A4739]">
                                สัญญาณเสี่ยงที่พบ <span class="text-[#7C7863] font-normal">(บรรทัดละ 1 รายการ)</span>
                            </label>
                            <textarea name="risk_signals" rows="3" class="mt-1 block w-full rounded-md bg-[#F1EEE0] border-[#C9C4AD] focus:border-[#2C5166] focus:ring-[#2C5166]">{{ old('risk_signals', implode("\n", $riskSignals)) }}</textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-[#4A4739]">
                                PPS Score ปัจจุบัน <span class="text-[#7C7863] font-normal">(กรอกเฉพาะกรณี Palliative Care — ใช้กำหนดความถี่การเยี่ยมครั้งแรก)</span>
                            </label>
                            <input type="number" name="initial_pps_score" min="0" max="100"
                                   value="{{ old('initial_pps_score') }}" class="mt-1 block w-32 rounded-md bg-[#F1EEE0] border-[#C9C4AD] focus:border-[#2C5166] focus:ring-[#2C5166]">
                        </div>
                    </div>

                    <p class="text-xs text-[#7C7863] mt-4">
                        แก้ไขข้อความด้านบนได้ก่อนยืนยัน — พยาบาลต้องตรวจสอบและยืนยันทุกครั้งก่อนเริ่มแผนติดตาม
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-[#2C5166] text-white rounded-md text-sm font-semibold hover:bg-[#3D6B84]">
                        ยืนยันแผนติดตาม
                    </button>
                    <a href="{{ route('referrals.show', $referral) }}" class="text-sm text-[#7C7863] hover:text-[#4A4739]">กลับไปหน้าใบส่งต่อ</a>
                </div>
            </form>

            <form method="POST" action="{{ route('referrals.ai-summary', $referral) }}">
                @csrf
                <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-white border border-[#C9C4AD] text-[#22201A] rounded-md text-sm font-semibold hover:bg-[#F1EEE0]">↻ ขอให้ AI สรุปใหม่</button>
            </form>
        </div>
    </div>
</x-app-layout>
