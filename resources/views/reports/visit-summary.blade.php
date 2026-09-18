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
            <span class="caption">จำนวนการส่งเยี่ยมทั้งหมด</span>
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

            @php $u = $summary['urgency_breakdown']; @endphp
            <div style="margin-top:var(--space-5);">
                <p class="label">ความทันเวลาแยกตามความเร่งด่วน (ด่วน = บ้านแดง 5 วัน, ทั่วไป = บ้านเขียว/เหลือง 14/30 วัน)</p>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>เขต</th>
                                <th style="text-align:right;">ด่วน — ทันกำหนด</th>
                                <th style="text-align:right;">ด่วน — ไม่ทัน</th>
                                <th style="text-align:right;">ทั่วไป — ทันกำหนด</th>
                                <th style="text-align:right;">ทั่วไป — ไม่ทัน</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>ในเขต</td>
                                <td style="text-align:right;">{{ $u['urgent']['in_area']['on_time'] }}</td>
                                <td style="text-align:right;">{{ $u['urgent']['in_area']['late'] }}</td>
                                <td style="text-align:right;">{{ $u['general']['in_area']['on_time'] }}</td>
                                <td style="text-align:right;">{{ $u['general']['in_area']['late'] }}</td>
                            </tr>
                            <tr>
                                <td>นอกเขต</td>
                                <td style="text-align:right;">{{ $u['urgent']['out_area']['on_time'] }}</td>
                                <td style="text-align:right;">{{ $u['urgent']['out_area']['late'] }}</td>
                                <td style="text-align:right;">{{ $u['general']['out_area']['on_time'] }}</td>
                                <td style="text-align:right;">{{ $u['general']['out_area']['late'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ Distribution charts (case type / patient status) ============ --}}
    <div class="card">
        <div class="card-head">
            <div>
                <div class="h2">สรุปตามประเภทผู้ป่วยและสถานะ</div>
                <div class="sub">สัดส่วนจำนวนการส่งเยี่ยมในช่วงเวลาที่เลือก</div>
            </div>
        </div>
        <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-6);">
            <div>
                <span class="dist-group-title">ตามประเภทผู้ป่วย</span>
                <div class="dist-list">
                    @forelse ($summary['case_type_breakdown'] as $row)
                        <div class="dist-row">
                            <span class="dist-label">{{ $row['name'] }}</span>
                            <div class="dist-track"><div class="dist-fill" style="width:{{ $summary['total_referrals'] > 0 ? round($row['count'] / $summary['total_referrals'] * 100) : 0 }}%;"></div></div>
                            <span class="dist-count">{{ $row['count'] }}</span>
                        </div>
                    @empty
                        <div class="caption">ไม่มีข้อมูลในช่วงเวลานี้</div>
                    @endforelse
                </div>
            </div>
            <div>
                <span class="dist-group-title">ตามสถานะ</span>
                <div class="dist-list">
                    @forelse ($summary['patient_status_breakdown'] as $row)
                        <div class="dist-row">
                            <span class="dist-label">{{ $row['label'] }}</span>
                            <div class="dist-track"><div class="dist-fill" style="width:{{ $summary['total_referrals'] > 0 ? round($row['count'] / $summary['total_referrals'] * 100) : 0 }}%;"></div></div>
                            <span class="dist-count">{{ $row['count'] }}</span>
                        </div>
                    @empty
                        <div class="caption">ไม่มีข้อมูลในช่วงเวลานี้</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ============ Visit outcome / out-of-area response rate ============ --}}
    <div class="card">
        <div class="card-head">
            <div class="h2">สรุปผลการเยี่ยมบ้าน</div>
        </div>
        <div class="card-body">
            <div class="kpi-grid mini">
                <div class="kpi-tile">
                    <span class="caption">ในเขต</span>
                    <span class="kpi-value">{{ $summary['in_area_count'] }}</span>
                    <span class="hint">ราย</span>
                </div>
                <div class="kpi-tile">
                    <span class="caption">นอกเขต — มีการตอบกลับ</span>
                    <span class="kpi-value">{{ $summary['out_area_responded_count'] }}</span>
                    <span class="hint">ราย</span>
                </div>
                <div class="kpi-tile alert">
                    <span class="caption">นอกเขต — ไม่พบการตอบกลับในระบบ</span>
                    <span class="kpi-value risk">{{ $summary['out_area_no_response_count'] }}</span>
                    <span class="hint">ราย</span>
                </div>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-6);">
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
