<x-app-layout>
    <x-slot name="header">
        บันทึกผลติดตาม — {{ $plan->referral->patient->name }}
    </x-slot>

    <div class="page-head">
        <h1 class="h1">บันทึกผลติดตาม — {{ $plan->referral->patient->name }}</h1>
        <p class="sub">
            HN {{ $plan->referral->patient->hn }} · ครั้งที่ {{ $plan->plan_number }} ·
            <span class="chip chip-method">{{ $plan->method === 'home_visit' ? 'เยี่ยมบ้าน' : 'โทรติดตาม' }}</span>
        </p>
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
                <h2 class="h2">บันทึกผลติดตามครั้งนี้</h2>
                <p class="sub">กรอกข้อมูลให้ครบก่อนส่งให้ AI วิเคราะห์ความเสี่ยงในขั้นตอนถัดไป</p>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('follow-up-plans.record.store', $plan) }}">
                @csrf

                <div class="field-grid">
                    <div class="field">
                        <label for="visited_at">วัน-เวลาที่ติดตาม</label>
                        <input type="datetime-local" id="visited_at" name="visited_at"
                               value="{{ old('visited_at', now()->format('Y-m-d\TH:i')) }}">
                    </div>

                    <div class="field">
                        <label for="pps_number">PPS Score</label>
                        <div class="pps-row">
                            <input type="range" id="pps_range" min="0" max="100" step="10"
                                   value="{{ old('pps_score', 50) }}">
                            <input type="number" id="pps_number" name="pps_score" min="0" max="100"
                                   value="{{ old('pps_score') }}" class="pps-number" placeholder="—">
                        </div>
                        <div class="pps-scale"><span>0</span><span>50</span><span>100</span></div>
                        <span class="hint">กรอกเฉพาะกรณี Palliative Care</span>
                    </div>

                    <div class="field full">
                        <label for="raw_notes">อาการ / ปัญหาที่พบ</label>
                        <textarea id="raw_notes" name="raw_notes" class="textarea-lg" required>{{ old('raw_notes') }}</textarea>
                    </div>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">บันทึกผลติดตาม</button>
                    <a href="{{ route('referrals.show', $plan->referral) }}" class="btn btn-secondary">กลับไปหน้าใบส่งต่อ</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var range = document.getElementById('pps_range');
            var number = document.getElementById('pps_number');
            if (!range || !number) {
                return;
            }

            range.addEventListener('input', function () {
                number.value = range.value;
            });
            number.addEventListener('input', function () {
                if (number.value !== '') {
                    range.value = number.value;
                }
            });
        })();
    </script>
</x-app-layout>
