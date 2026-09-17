<x-app-layout>
    <x-slot name="header">ผลประเมินความพึงพอใจ — {{ $survey->referral->patient->name }}</x-slot>

    <div class="page-head">
        <h1 class="h1">ผลประเมินความพึงพอใจ</h1>
        <div class="sub">{{ $survey->referral->patient->name }} · HN {{ $survey->referral->patient->hn }}</div>
    </div>

    <div class="card">
        <div class="card-body" style="padding-top:var(--space-5);">
            @include('satisfaction-surveys._summary')

            <div class="btn-row">
                <a href="{{ route('satisfaction-surveys.index') }}" class="btn btn-secondary">กลับไปรายชื่อผู้ป่วย</a>
            </div>
        </div>
    </div>
</x-app-layout>
