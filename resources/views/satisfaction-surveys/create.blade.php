<x-app-layout>
    <x-slot name="header">ประเมินความพึงพอใจ — {{ $referral->patient->name }}</x-slot>

    <div class="page-head">
        <h1 class="h1">แบบประเมินความพึงพอใจผู้รับบริการในชุมชน</h1>
        <div class="sub">{{ $referral->patient->name }} · HN {{ $referral->patient->hn }} (เจ้าหน้าที่กรอกแทนผู้ป่วย/ญาติ)</div>
    </div>

    <div class="card">
        <div class="card-body" style="padding-top:var(--space-5);">
            @if ($errors->any())
                <div class="banner" style="background:var(--color-risk-tint);border-color:var(--color-risk);margin-bottom:var(--space-5);">
                    <div class="banner-text">
                        <ul style="margin:0;padding-left:18px;color:var(--color-risk);">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('satisfaction-surveys.store') }}">
                @csrf
                <input type="hidden" name="satisfaction_survey_id" value="{{ $survey->id }}">

                @include('satisfaction-surveys._form-fields')

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">บันทึกแบบประเมิน</button>
                    <a href="{{ route('satisfaction-surveys.index') }}" class="btn btn-secondary">ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
