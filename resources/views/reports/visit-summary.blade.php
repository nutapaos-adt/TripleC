<x-app-layout>
    <x-slot name="header">สรุปข้อมูลสำคัญงานเยี่ยมบ้าน</x-slot>

    <div class="page-head">
        <h1 class="h1">สรุปข้อมูลสำคัญงานเยี่ยมบ้าน</h1>
        <span class="sub">ภาพรวมผลงานทีมเยี่ยมบ้าน — {{ $periodLabel }}</span>
    </div>

    {{-- ============ Period controls ============ --}}
    <div class="card">
        <div class="card-body" style="padding-top:var(--space-5);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--space-3);">
            <div class="btn-row" style="margin-top:0;">
                @foreach (['month' => 'รายเดือน', 'quarter' => 'รายไตรมาส', 'year' => 'รายปี'] as $type => $label)
                    @php
                        $defaultValueForType = match ($type) {
                            'quarter' => array_key_last($quarterOptions),
                            'year' => array_key_last($yearOptions),
                            default => array_key_last($monthOptions),
                        };
                    @endphp
                    <a href="{{ route('reports.visit-summary', ['period_type' => $type, 'period_value' => $defaultValueForType]) }}"
                       class="btn btn-sm {{ $periodType === $type ? 'btn-primary' : 'btn-secondary' }}">{{ $label }}</a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('reports.visit-summary') }}">
                <input type="hidden" name="period_type" value="{{ $periodType }}">
                <select name="period_value" onchange="this.form.submit()">
                    @php
                        $currentOptions = match ($periodType) {
                            'quarter' => $quarterOptions,
                            'year' => $yearOptions,
                            default => $monthOptions,
                        };
                    @endphp
                    @foreach ($currentOptions as $value => $label)
                        <option value="{{ $value }}" @selected($value === $periodValue)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    {{-- ============ KPI tiles ============ --}}
    <section class="kpi-grid" aria-label="สรุปตัวเลขการส่งเยี่ยมบ้าน">
        <div class="kpi-tile">
            <span class="caption">จำนวนการส่งต่อทั้งหมด</span>
            <span class="kpi-value">{{ $summary['total_referrals'] }}</span>
            <span class="caption">ราย</span>
        </div>
        <div class="kpi-tile">
            <span class="caption">ในเขต</span>
            <span class="kpi-value">{{ $summary['in_area_count'] }}</span>
            <span class="caption">ราย</span>
        </div>
        <div class="kpi-tile">
            <span class="caption">นอกเขต</span>
            <span class="kpi-value">{{ $summary['out_area_count'] }}</span>
            <span class="caption">ราย</span>
        </div>
        <div class="kpi-tile">
            <span class="caption">ยังไม่ได้รับการเยี่ยม/ยืนยันแผน</span>
            <span class="kpi-value {{ $summary['not_yet_visited_count'] > 0 ? 'risk' : '' }}">{{ $summary['not_yet_visited_count'] }}</span>
            <span class="caption">ราย — ในเขต {{ $summary['not_yet_visited_in_area'] }} &middot; นอกเขต {{ $summary['not_yet_visited_out_area'] }}</span>
        </div>
    </section>

    {{-- ============ Timeliness ============ --}}
    <div class="card">
        <div class="card-head">
            <div class="h2">ความทันเวลาของการเยี่ยมครั้งแรก</div>
            <div class="sub">นับจากวันที่ส่งต่อจนถึงวันที่เยี่ยม/โทรติดตามครั้งแรก (plan #1)</div>
        </div>
        <div class="card-body">
            <div class="kpi-split-row">
                <div class="kpi-split-item">
                    <div class="kpi-split-top">
                        <span class="kpi-split-label">ทันใน 5 วัน (ด่วน)</span>
                        <span class="kpi-split-value">{{ $summary['timeliness']['within_5'] }}</span>
                    </div>
                    <span class="kpi-split-sub">ราย</span>
                </div>
                <div class="kpi-split-item">
                    <div class="kpi-split-top">
                        <span class="kpi-split-label">ทันใน 14 วัน</span>
                        <span class="kpi-split-value">{{ $summary['timeliness']['within_14'] }}</span>
                    </div>
                    <span class="kpi-split-sub">ราย</span>
                </div>
                <div class="kpi-split-item">
                    <div class="kpi-split-top">
                        <span class="kpi-split-label">ทันใน 30 วัน</span>
                        <span class="kpi-split-value">{{ $summary['timeliness']['within_30'] }}</span>
                    </div>
                    <span class="kpi-split-sub">ราย</span>
                </div>
                <div class="kpi-split-item">
                    <div class="kpi-split-top">
                        <span class="kpi-split-label">เกิน 30 วัน</span>
                        <span class="kpi-split-value">{{ $summary['timeliness']['over_30'] }}</span>
                    </div>
                    <span class="kpi-split-sub">ราย</span>
                </div>
                <div class="kpi-split-item">
                    <div class="kpi-split-top">
                        <span class="kpi-split-label">ยังไม่ได้เยี่ยมครั้งแรก</span>
                        <span class="kpi-split-value">{{ $summary['timeliness']['not_visited'] }}</span>
                    </div>
                    <span class="kpi-split-sub">ราย</span>
                </div>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-6);">
        {{-- ============ Case type breakdown ============ --}}
        <div class="card">
            <div class="card-head">
                <div class="h2">แบ่งตามประเภทเคส</div>
            </div>
            <div class="card-body">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>ประเภทเคส</th><th style="text-align:right;">จำนวน</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($summary['case_type_breakdown'] as $row)
                                <tr>
                                    <td><span class="chip chip-casetype">{{ $row['name'] }}</span></td>
                                    <td style="text-align:right;">{{ $row['count'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" style="text-align:center;">ไม่มีข้อมูลในช่วงเวลานี้</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ============ Patient status breakdown ============ --}}
        <div class="card">
            <div class="card-head">
                <div class="h2">แบ่งตามสถานะผู้ป่วย</div>
            </div>
            <div class="card-body">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>สถานะ</th><th style="text-align:right;">จำนวน</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($summary['patient_status_breakdown'] as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td style="text-align:right;">{{ $row['count'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" style="text-align:center;">ไม่มีข้อมูลในช่วงเวลานี้</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ============ Ward breakdown ============ --}}
        <div class="card">
            <div class="card-head">
                <div class="h2">แบ่งตามหอผู้ป่วยต้นทาง</div>
            </div>
            <div class="card-body">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>หอผู้ป่วย</th><th style="text-align:right;">จำนวน</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($summary['ward_breakdown'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td style="text-align:right;">{{ $row['count'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" style="text-align:center;">ไม่มีข้อมูลในช่วงเวลานี้</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ============ Clinical tracer breakdown ============ --}}
        <div class="card">
            <div class="card-head">
                <div class="h2">เยี่ยมตาม Clinical Tracer</div>
                <div class="sub">นับตามจำนวนครั้งที่พบแท็กในใบส่งต่อ (1 รายอาจเข้าเงื่อนไขมากกว่า 1 แท็ก)</div>
            </div>
            <div class="card-body">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Tracer</th><th style="text-align:right;">จำนวน</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($summary['clinical_tracer_breakdown'] as $row)
                                <tr>
                                    <td>{{ $row['tag'] }}</td>
                                    <td style="text-align:right;">{{ $row['count'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" style="text-align:center;">ไม่มีข้อมูลในช่วงเวลานี้</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
