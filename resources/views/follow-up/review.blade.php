<x-app-layout>
    <x-slot name="header">
        บันทึกผลติดตาม — {{ $plan->referral->patient->name }}
    </x-slot>

    @php
        $record = $plan->record;
        $analysis = $record->ai_analysis;
        $isConfirmed = $record->isConfirmed();
        $decisionLabels = [
            'repeat' => 'ติดตามซ้ำ',
            'refer' => 'ส่งต่อแพทย์',
            'close' => 'ปิดเคส',
        ];
        $decisionDescriptions = [
            'repeat' => 'สร้างกำหนดการติดตามครั้งถัดไปโดยอัตโนมัติ ใช้เมื่อยังต้องเฝ้าดูอาการต่อเนื่อง',
            'refer' => 'ส่งต่อแพทย์/ทีมที่เกี่ยวข้องเพื่อประเมินเพิ่มเติม ระบบยังสร้างกำหนดการติดตามครั้งถัดไปให้',
            'close' => 'ยุติการติดตามต่อเนื่อง ระบบจะยกเลิกกำหนดการที่เหลือทั้งหมดและปิดเคส',
        ];
        $suggested = $analysis['suggested_decision'] ?? null;
        $selectedDecision = old('nurse_decision', $suggested);
    @endphp

    <div class="page-head">
        <h1 class="h1">บันทึกผลติดตาม — {{ $plan->referral->patient->name }}</h1>
        <p class="sub">ครั้งที่ {{ $plan->plan_number }}</p>
    </div>

    @if ($errors->any())
        <div class="card" style="border-color:var(--color-risk);">
            <div class="card-body" style="padding-top:var(--space-5);">
                <ul style="margin:0;padding-left:18px;color:var(--color-risk);font-size:13px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="h2">ผลติดตามที่บันทึกไว้</h2>
            </div>
        </div>
        <div class="card-body">
            <div class="field-grid">
                <div class="field">
                    <label>วัน-เวลาที่ติดตาม</label>
                    <div class="field-value">{{ $record->visited_at->format('d/m/Y H:i') }}</div>
                </div>
                <div class="field">
                    <label>PPS Score</label>
                    <div class="field-value">{{ $record->pps_score ?? '—' }}</div>
                </div>
                <div class="field full">
                    <label>อาการ/ปัญหาที่พบ</label>
                    <div class="field-value multiline">{{ $record->raw_notes }}</div>
                </div>
            </div>
        </div>
    </div>

    @if ($analysis && ! ($analysis['parse_error'] ?? false))
        <div class="ai-box">
            <span class="box-label"><span class="dot"></span>ร่างจาก AI — ยังไม่ยืนยัน · ผลวิเคราะห์ความเสี่ยง</span>

            <div class="field-grid">
                <div class="field full">
                    <label>พบสัญญาณเสี่ยง</label>
                    <div class="field-value">
                        @if ($analysis['risk_detected'] ?? false)
                            <span class="chip chip-risk">พบ</span> {{ $analysis['risk_summary'] ?? '' }}
                        @else
                            <span class="chip chip-success">ไม่พบ</span>
                        @endif
                    </div>
                </div>
                <div class="field full">
                    <label>คำแนะนำเบื้องต้น</label>
                    <div class="field-value multiline">{{ $analysis['recommendation'] ?? '—' }}</div>
                </div>
            </div>
        </div>
    @elseif ($analysis['parse_error'] ?? false)
        <div class="banner" style="background:var(--color-warning-tint);border-color:var(--color-warning);">
            <div class="banner-text">
                <p style="color:var(--color-warning);">AI ไม่สามารถแปลผลลัพธ์เป็นข้อมูลที่ใช้ได้ในครั้งนี้ กรุณาตัดสินใจด้วยตนเองด้านล่าง หรือลองขอวิเคราะห์ใหม่</p>
            </div>
        </div>
    @endif

    @if (! $isConfirmed)
        <form method="POST" action="{{ route('follow-up-plans.analyze', $plan) }}" class="btn-row">
            @csrf
            <button type="submit" class="btn btn-secondary">
                {{ $analysis ? '↻ ให้ AI วิเคราะห์ใหม่' : 'ให้ AI วิเคราะห์ผล' }}
            </button>
        </form>
    @endif

    @if ($isConfirmed)
        <div class="confirmed-box">
            <span class="box-label"><span class="dot"></span>การตัดสินใจของพยาบาล — ยืนยันแล้วโดย {{ $record->confirmer->name }} เมื่อ {{ $record->confirmed_at->format('d/m/Y H:i') }}</span>
            <p style="margin:0;font-size:14px;color:var(--color-neutral-900);">
                การตัดสินใจ:
                <strong>{{ $decisionLabels[$record->nurse_decision] ?? $record->nurse_decision }}</strong>
            </p>
            @if ($record->decision_notes)
                <p style="margin:6px 0 0;font-size:14px;color:var(--color-neutral-700);">{{ $record->decision_notes }}</p>
            @endif
        </div>
    @else
        <div class="nurse-decision">
            <span class="nurse-decision-label">การตัดสินใจของพยาบาล — ต้องยืนยันเสมอ</span>

            <form method="POST" action="{{ route('follow-up-plans.decision', $plan) }}" id="decisionForm">
                @csrf

                <div class="field-group">
                    <span class="label">เลือกการตัดสินใจ</span>
                    <div class="radio-cards">
                        @foreach ($decisionLabels as $value => $label)
                            <label class="radio-card @if($selectedDecision === $value) selected @endif" data-value="{{ $value }}">
                                <input type="radio" name="nurse_decision" value="{{ $value }}" @checked($selectedDecision === $value)>
                                <div class="rc-title">{{ $label }}</div>
                                <div class="rc-desc">{{ $decisionDescriptions[$value] }}</div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="field-group">
                    <div class="checkbox-row">
                        <input type="checkbox" id="risk_flag" name="risk_flag" value="1"
                               @checked(old('risk_flag', $analysis['risk_detected'] ?? false))>
                        <label for="risk_flag">ยืนยันว่าพบสัญญาณเสี่ยงจริง</label>
                    </div>
                </div>

                <div class="field-group">
                    <label for="decision_notes">
                        หมายเหตุ <span class="hint" style="display:inline;">(เช่น ส่งต่อถึงใคร/แผนกไหน)</span>
                    </label>
                    <textarea id="decision_notes" name="decision_notes" rows="2">{{ old('decision_notes', $analysis['recommendation'] ?? '') }}</textarea>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">ยืนยันการตัดสินใจ</button>
                </div>
            </form>
        </div>
    @endif

    <script>
        (function () {
            var cards = document.querySelectorAll('#decisionForm .radio-card');
            cards.forEach(function (card) {
                card.addEventListener('click', function () {
                    cards.forEach(function (c) { c.classList.remove('selected'); });
                    card.classList.add('selected');
                    card.querySelector('input[type="radio"]').checked = true;
                });
            });
        })();
    </script>
</x-app-layout>
