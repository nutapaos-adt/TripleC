<x-app-layout>
    <x-slot name="header">วิเคราะห์แผนการพยาบาล — รอตรวจสอบ</x-slot>

    <div class="page-head">
        <h1 class="h1">รอตรวจสอบแผนการพยาบาล</h1>
        <p class="sub">ใบส่งต่อที่ยังไม่ได้ยืนยันแผนการดูแล</p>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ผู้ป่วย</th>
                        <th>แหล่งที่มา</th>
                        <th>ประเภทเคส</th>
                        <th>เขต</th>
                        <th>วันที่ส่งต่อข้อมูล</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($referrals as $referral)
                        <tr>
                            <td>
                                <div class="patient-name">{{ $referral->patient->name }}</div>
                                <div class="patient-hn">HN {{ $referral->patient->hn }}</div>
                            </td>
                            <td>
                                <span class="chip chip-method">{{ $referral->source_detail ?: match($referral->source_type) {
                                    'ward' => 'หอผู้ป่วย', 'opd' => 'OPD',
                                    'internal_dept' => 'หน่วยงานภายใน รพ.', 'external_hospital' => 'โรงพยาบาลอื่น',
                                    default => $referral->source_type,
                                } }}</span>
                            </td>
                            <td>
                                @if ($referral->caseType)
                                    <span class="chip chip-casetype">{{ $referral->caseType->name }}</span>
                                @else
                                    <span class="caption">— ยังไม่ระบุ —</span>
                                @endif
                            </td>
                            <td>
                                <span class="chip {{ $referral->zone === 'in_area' ? 'chip-inzone' : 'chip-outzone' }}">
                                    {{ $referral->zone === 'in_area' ? 'ในเขต' : 'นอกเขต' }}
                                </span>
                            </td>
                            <td class="due-date">{{ $referral->created_at->format('d/m/Y') }}</td>
                            <td>
                                <a href="{{ route('referrals.care-plan', $referral) }}" class="btn btn-primary btn-sm">ตรวจสอบแผน</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center;padding:var(--space-8);color:var(--color-neutral-500);">ไม่มีเคสรอตรวจสอบแผนแล้วในขณะนี้</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="caption">ทั้งหมด {{ $referrals->total() }} เคสรอตรวจสอบแผน</p>

    <div>{{ $referrals->links() }}</div>
</x-app-layout>
