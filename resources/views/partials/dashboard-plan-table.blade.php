@php $showPlanNumber = $showPlanNumber ?? false; @endphp

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>ผู้ป่วย</th>
                @if ($showPlanNumber)<th>ครั้งที่</th>@endif
                <th>วิธีติดตาม</th>
                <th>กำหนดนัด</th>
                <th>สถานะ</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($plans as $plan)
                @php $daysLeft = (int) round(today()->diffInDays($plan->due_date, false)); @endphp
                <tr @class(['row-overdue' => $plan->isOverdue()])>
                    <td>
                        <div class="patient-name">{{ $plan->referral->patient->name }}</div>
                        <div class="patient-hn">HN {{ $plan->referral->patient->hn }}
                            @if ($plan->referral->severity_group === \App\Models\Referral::SEVERITY_RED)
                                <span class="chip chip-severity-red" style="margin-left:4px;">บ้านสีแดง</span>
                            @endif
                        </div>
                    </td>
                    @if ($showPlanNumber)<td>ครั้งที่ {{ $plan->plan_number }}</td>@endif
                    <td><span class="chip chip-method">{{ $plan->method === 'home_visit' ? 'เยี่ยมบ้าน' : 'โทรติดตาม' }}</span></td>
                    <td class="due-date">{{ \App\Support\ThaiDate::date($plan->due_date) }}</td>
                    <td>
                        @if ($daysLeft < 0)
                            <span class="chip chip-overdue">เกินกำหนด {{ abs($daysLeft) }} วัน</span>
                        @elseif ($daysLeft === 0)
                            <span class="chip chip-today">ครบกำหนดวันนี้</span>
                        @else
                            <span class="chip chip-neutral">อีก {{ $daysLeft }} วัน</span>
                        @endif
                    </td>
                    <td><a href="{{ route('follow-up-plans.record.create', $plan) }}" class="btn btn-primary btn-sm">เริ่มติดตาม</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showPlanNumber ? 6 : 5 }}" style="text-align:center;color:var(--color-neutral-500);padding:var(--space-8) 0;">{{ $empty }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
