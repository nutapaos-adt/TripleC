<x-app-layout>
    <x-slot name="header">แก้ไขข้อมูล — {{ $referral->patient->name }} (HN {{ $referral->patient->hn }})</x-slot>

    <div class="page-head">
        <h1 class="h1">แก้ไขข้อมูลใบส่งต่อ</h1>
        <p class="sub">{{ $referral->patient->name }} (HN {{ $referral->patient->hn }}) — แก้ไขได้จนกว่าทีมเยี่ยมบ้านจะยืนยันแผนการดูแล</p>
    </div>

    @if ($errors->any())
        <div class="banner" style="background:var(--color-risk-tint);border-color:var(--color-risk);">
            <div class="banner-text">
                <p class="h3" style="color:var(--color-risk);">กรุณาตรวจสอบข้อมูลต่อไปนี้</p>
                <ul style="margin:4px 0 0;padding-left:18px;color:var(--color-risk);">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body" style="padding-top:var(--space-5);">
            <form method="POST" action="{{ route('referrals.update', $referral) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('referrals._form-fields')

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                    <a href="{{ route('referrals.show', $referral) }}" class="btn btn-secondary">ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>

    @include('referrals._form-scripts')
</x-app-layout>
