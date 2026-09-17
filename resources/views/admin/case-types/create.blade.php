<x-app-layout>
    <x-slot name="header">เพิ่มประเภทเคส</x-slot>

    <div class="page-head">
        <h1 class="h1">เพิ่มประเภทเคส</h1>
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

            <form method="POST" action="{{ route('admin.case-types.store') }}">
                @csrf
                @php $caseType = null; @endphp
                @include('admin.case-types._form')

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                    <a href="{{ route('admin.case-types.index') }}" class="btn btn-secondary">ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
