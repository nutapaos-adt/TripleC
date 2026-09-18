<x-app-layout>
    <x-slot name="header">ติดตามผลการเยี่ยม</x-slot>

    @php
        $chip = function (string $key, string $label) use ($counts, $outcome) {
            $active = $outcome === $key || ($key === 'all' && ! $outcome);
            $query = array_filter(array_merge(request()->query(), ['outcome' => $key === 'all' ? null : $key, 'page' => null]));

            return [
                'href' => route('ward.visit-results', $query),
                'label' => $label.' ('.($counts[$key] ?? 0).')',
                'active' => $active,
            ];
        };
        $chips = [
            $chip('all', 'ทั้งหมด'),
            $chip('risk', 'พบความเสี่ยง'),
            $chip('normal', 'ปกติ ไม่พบความเสี่ยง'),
            $chip('closed', 'ปิดเคสแล้ว'),
        ];
    @endphp

    <div class="page-head">
        <h1 class="h1">ติดตามผลการเยี่ยม{{ auth()->user()->isWardStaff() ? ' — หอ '.(auth()->user()->ward?->name ?? '—') : '' }}</h1>
        <div class="sub">เคสที่หอผู้ป่วยส่งเยี่ยมบ้าน และทีมเยี่ยมบ้านลงพื้นที่/ติดตามแล้วอย่างน้อย 1 ครั้ง — แสดงทุกเคสรวมทั้งเคสที่ปกติดีไม่พบความเสี่ยง ไม่ใช่แค่เคสที่มีสัญญาณผิดปกติ</div>
    </div>

    <div class="btn-row">
        @foreach ($chips as $c)
            <a href="{{ $c['href'] }}" class="btn btn-sm {{ $c['active'] ? 'btn-primary' : 'btn-secondary' }}">{{ $c['label'] }}</a>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body" style="padding-top:var(--space-5);">
            <form method="GET" action="{{ route('ward.visit-results') }}" class="grid-row cols-3" style="align-items:end;">
                <input type="hidden" name="outcome" value="{{ $outcome }}">
                <div class="field" style="margin-bottom:0;">
                    <label for="q">ค้นหาผู้ป่วย (ชื่อ หรือ HN)</label>
                    <input type="text" id="q" name="q" value="{{ $q }}" class="input" placeholder="ชื่อผู้ป่วย หรือ HN">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="month">เดือนที่เยี่ยมล่าสุด</label>
                    <select id="month" name="month" class="input">
                        <option value="">ทุกเดือน</option>
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected((string) $month === (string) $m)>{{ \Illuminate\Support\Carbon::create()->month($m)->translatedFormat('F') }}</option>
                        @endfor
                    </select>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="year">ปีที่เยี่ยมล่าสุด</label>
                    <select id="year" name="year" class="input">
                        <option value="">ทุกปี</option>
                        @for ($y = now()->year; $y >= now()->year - 5; $y--)
                            <option value="{{ $y }}" @selected((string) $year === (string) $y)>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="field" style="margin-bottom:0;grid-column:1 / -1;">
                    <button type="submit" class="btn btn-primary btn-sm">ค้นหา</button>
                    <a href="{{ route('ward.visit-results') }}" class="btn btn-secondary btn-sm">ล้างตัวกรอง</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="h2">รายการเคส</div>
            <div class="sub">{{ $rows->total() }} รายการ</div>
        </div>
        <div class="card-body">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ผู้ป่วย</th>
                            <th>ประเภทเคส</th>
                            <th>วันที่เยี่ยมล่าสุด</th>
                            <th>สถานะ/ผล</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php
                                $referral = $row['referral'];
                                $latestPlan = $row['latest_plan'];
                            @endphp
                            <tr>
                                <td>
                                    <div class="patient-name">{{ $referral->patient->name }}</div>
                                    <div class="patient-hn">HN {{ $referral->patient->hn }}</div>
                                </td>
                                <td>
                                    <span class="chip chip-casetype">{{ $referral->caseType?->name ?? '—' }}</span>
                                </td>
                                <td class="due-date">{{ $row['latest_visited_at']?->format('d/m/Y') ?? '—' }}</td>
                                <td>
                                    @if ($row['is_risk'])
                                        <span class="chip chip-risk">พบความเสี่ยง</span>
                                    @elseif ($row['is_closed'])
                                        <span class="chip chip-closed">ปิดเคสแล้ว</span>
                                    @else
                                        <span class="chip chip-success">ปกติ ไม่พบความเสี่ยง</span>
                                    @endif
                                </td>
                                <td style="text-align:right;">
                                    @if ($row['is_risk'] && $latestPlan)
                                        <a href="{{ route('follow-up-plans.review', $latestPlan) }}" class="btn btn-secondary btn-sm">ดูรายละเอียด</a>
                                    @else
                                        <a href="{{ route('referrals.show', $referral) }}" class="btn btn-secondary btn-sm">ดูรายละเอียด</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align:center;color:var(--color-neutral-500);padding:var(--space-8) 0;">ไม่พบรายการที่ตรงกับเงื่อนไข</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:var(--space-4);">
                {{ $rows->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
