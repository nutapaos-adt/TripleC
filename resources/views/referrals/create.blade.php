<x-app-layout>
    <x-slot name="header">ส่งข้อมูลเยี่ยมบ้าน — สร้างใบส่งต่อผู้ป่วย</x-slot>

    <div class="page-head">
        <h1 class="h1">ส่งข้อมูลเยี่ยมบ้าน</h1>
        <p class="sub">กรอกข้อมูลผู้ป่วยและสถานการณ์เบื้องต้น ระบบจะสร้างใบส่งต่อรอให้ทีมเยี่ยมบ้านตรวจสอบ/ยืนยันแผนต่อไป</p>
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
            <form method="POST" action="{{ route('referrals.store') }}" enctype="multipart/form-data">
                @csrf
                @php $referral = null; @endphp
                @include('referrals._form-fields')

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">บันทึกและส่งข้อมูล</button>
                    <a href="{{ route('referrals.index') }}" class="btn btn-secondary">ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>

    @include('referrals._form-scripts')
</x-app-layout>
