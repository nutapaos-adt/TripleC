<x-app-layout>
    <x-slot name="header">
        บันทึกการเยี่ยมบ้าน
    </x-slot>

    @php
        $statusChips = [
            null => 'ทั้งหมด',
            'overdue' => 'เกินกำหนด',
            'today' => 'วันนี้',
            'scheduled' => 'รอถึงกำหนด',
            'done' => 'เยี่ยมแล้ว',
        ];
        $countKeys = [
            null => 'all',
            'overdue' => 'overdue',
            'today' => 'today',
            'scheduled' => 'scheduled',
            'done' => 'done',
        ];
        $thaiMonths = [
            1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
            5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
            9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
        ];
    @endphp

    <div class="page-head">
        <h1 class="h1">บันทึกการเยี่ยมบ้าน</h1>
        <p class="sub">รายการแผนติดตาม (เยี่ยมบ้าน/โทรติดตาม) ของผู้ป่วยทุกรายที่อยู่ในความรับผิดชอบ ไม่จำกัดเฉพาะวันนี้</p>
    </div>

    <div class="btn-row">
        @foreach ($statusChips as $value => $label)
            <a href="{{ route('follow-up-plans.index', array_filter(['status' => $value, 'year' => $year, 'month' => $month])) }}"
               class="btn btn-sm {{ $status === $value ? 'btn-primary' : 'btn-secondary' }}">
                {{ $label }} ({{ $counts[$countKeys[$value]] }})
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('follow-up-plans.index') }}" class="btn-row" id="dateFilterForm">
        @if ($status)
            <input type="hidden" name="status" value="{{ $status }}">
        @endif
        <span class="label" style="align-self:center;">กรองตามกำหนด:</span>
        <select name="year" onchange="document.getElementById('dateFilterForm').submit()" style="width:auto;">
            <option value="">ทุกปี</option>
            @foreach ($availableYears as $y)
                <option value="{{ $y }}" @selected((string) $year === (string) $y)>{{ $y }}</option>
            @endforeach
        </select>
        <select name="month" onchange="document.getElementById('dateFilterForm').submit()" style="width:auto;">
            <option value="">ทุกเดือน</option>
            @foreach ($thaiMonths as $num => $name)
                <option value="{{ $num }}" @selected((string) $month === (string) $num)>{{ $name }}</option>
            @endforeach
        </select>
    </form>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ผู้ป่วย</th>
                        <th>ประเภทเคส</th>
                        <th>ครั้งที่</th>
                        <th>วิธีติดตาม</th>
                        <th>กำหนด</th>
                        <th>สถานะ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plans as $plan)
                        @php
                            $isDone = $plan->status === \App\Models\FollowUpPlan::STATUS_DONE;
                            $overdue = $plan->isOverdue();
                            $isToday = ! $isDone && ! $overdue
                                && $plan->status === \App\Models\FollowUpPlan::STATUS_SCHEDULED
                                && $plan->due_date->isToday();
                            $activeRule = $plan->referral->caseType?->activeVisitRule();
                            $total = ($activeRule && $activeRule->rule_type === \App\Models\VisitRule::TYPE_FIXED_COUNT)
                                ? $activeRule->fixed_visit_count
                                : '—';
                        @endphp
                        <tr @class(['row-overdue' => $overdue])>
                            <td>
                                <div class="patient-name">{{ $plan->referral->patient->name }}</div>
                                <div class="patient-hn">HN {{ $plan->referral->patient->hn }}</div>
                            </td>
                            <td>{{ $plan->referral->caseType?->name ?? '—' }}</td>
                            <td class="due-date">{{ $plan->plan_number }} / {{ $total }}</td>
                            <td>
                                <span class="chip chip-method">
                                    {{ $plan->method === \App\Models\FollowUpPlan::METHOD_HOME_VISIT ? 'เยี่ยมบ้าน' : 'โทรติดตาม' }}
                                </span>
                            </td>
                            <td class="due-date">{{ $plan->due_date->format('d/m/Y') }}</td>
                            <td>
                                @if ($isDone)
                                    <span class="chip chip-done">เยี่ยมแล้ว</span>
                                @elseif ($overdue)
                                    <span class="chip chip-overdue">เกินกำหนด</span>
                                @elseif ($isToday)
                                    <span class="chip chip-today">วันนี้</span>
                                @else
                                    <span class="chip chip-method">รอถึงกำหนด</span>
                                @endif
                            </td>
                            <td>
                                @if (! $isDone)
                                    <a href="{{ route('follow-up-plans.record.create', $plan) }}" class="btn btn-sm btn-secondary">บันทึกผล →</a>
                                @else
                                    <a href="{{ route('follow-up-plans.review', $plan) }}" class="btn btn-sm btn-secondary">ดูรายละเอียด →</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align:center;color:var(--color-neutral-500);padding:var(--space-8);">
                                ไม่มีรายการในหมวดนี้
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        {{ $plans->links() }}
    </div>
</x-app-layout>
