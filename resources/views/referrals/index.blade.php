<x-app-layout>
    <x-slot name="header">รายการใบส่งต่อ</x-slot>

    <div class="page-head" style="flex-direction:row;align-items:center;justify-content:space-between;">
        <div>
            <h1 class="h1">รายการใบส่งต่อ</h1>
            <p class="sub">เคสทั้งหมดที่ส่งข้อมูลเข้าระบบ</p>
        </div>
        <a href="{{ route('referrals.create') }}" class="btn btn-primary">+ ส่งข้อมูลเยี่ยมบ้าน</a>
    </div>

    @php
        $statusLabels = [
            null => 'ทั้งหมด',
            \App\Models\Referral::STATUS_PENDING_REVIEW => 'รอตรวจสอบ',
            \App\Models\Referral::STATUS_PLAN_CONFIRMED => 'ยืนยันแผนแล้ว',
            \App\Models\Referral::STATUS_IN_PROGRESS => 'กำลังติดตาม',
            \App\Models\Referral::STATUS_CLOSED => 'ปิดเคสแล้ว',
        ];
    @endphp
    <div style="display:flex;gap:var(--space-2);flex-wrap:wrap;">
        @foreach ($statusLabels as $value => $label)
            @php $count = $value === null ? $statusCounts['all'] : $statusCounts[$value]; @endphp
            <a href="{{ route('referrals.index', $value ? ['status' => $value] : []) }}"
               class="btn btn-sm {{ $status === $value ? 'btn-primary' : 'btn-secondary' }}">
                {{ $label }} ({{ $count }})
            </a>
        @endforeach
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ผู้ป่วย</th>
                        <th>แหล่งข้อมูล</th>
                        <th>ประเภทเคส</th>
                        <th>เขต</th>
                        <th>สถานะ</th>
                        <th>วันที่ส่งต่อข้อมูล</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($referrals as $referral)
                        <tr style="cursor:pointer;" onclick="window.location='{{ route('referrals.show', $referral) }}'">
                            <td>
                                <div class="patient-name">{{ $referral->patient->name }}</div>
                                <div class="patient-hn">HN {{ $referral->patient->hn }}</div>
                            </td>
                            <td>{{ $referral->source_detail ?: match($referral->source_type) {
                                'ward' => 'หอผู้ป่วย',
                                'opd' => 'OPD',
                                'internal_dept' => 'หน่วยงานภายใน รพ.',
                                'external_hospital' => 'โรงพยาบาลอื่น',
                                default => $referral->source_type,
                            } }}</td>
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
                            <td>{{ $statusLabels[$referral->status] ?? $referral->status }}</td>
                            <td class="due-date">{{ $referral->created_at->format('d/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center;padding:var(--space-8);color:var(--color-neutral-500);">ยังไม่มีใบส่งต่อในระบบ</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $referrals->links() }}</div>
</x-app-layout>
