<x-app-layout>
    <x-slot name="header">รายชื่อผู้ป่วย — {{ $groupLabel }}</x-slot>

    <div class="page-head" style="flex-direction:row;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:var(--space-3);">
        <div>
            <h1 class="h1">{{ $groupLabel }}</h1>
            <p class="sub">{{ $monthLabel }} · {{ $referrals->count() }} ราย</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('dashboard', ['month' => $monthValue]) }}">← กลับหน้าภาพรวม</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ผู้ป่วย</th>
                            <th>ประเภทเคส</th>
                            <th>ส่งต่อเมื่อ</th>
                            <th>สถานะ</th>
                            <th>นัดถัดไป</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($referrals as $referral)
                            @php $next = $referral->followUpPlans->where('status', 'scheduled')->sortBy('plan_number')->first(); @endphp
                            <tr>
                                <td>
                                    <div class="patient-name">{{ $referral->patient->name }}</div>
                                    <div class="patient-hn">HN {{ $referral->patient->hn }}</div>
                                </td>
                                <td>
                                    {{ $referral->caseType?->name ?? '—' }}
                                    @if ($referral->severityLabel())
                                        <span class="chip {{ $referral->severityChipClass() }}" style="margin-left:4px;">{{ \Illuminate\Support\Str::before($referral->severityLabel(), ' (') }}</span>
                                    @endif
                                </td>
                                <td class="due-date">{{ \App\Support\ThaiDate::date($referral->created_at) }}</td>
                                <td><span class="chip {{ $referral->statusChipClass() }}">{{ $referral->statusLabel() }}</span></td>
                                <td class="due-date">
                                    @if ($next)
                                        ครั้งที่ {{ $next->plan_number }} · {{ \App\Support\ThaiDate::date($next->due_date) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td><a href="{{ route('referrals.show', $referral) }}" class="btn btn-secondary btn-sm">ดูรายละเอียด</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align:center;color:var(--color-neutral-500);padding:var(--space-8) 0;">ไม่มีผู้ป่วยในกลุ่มนี้ของเดือนนี้</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
