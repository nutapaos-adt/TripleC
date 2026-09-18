<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>รายงานประจำเดือน {{ $monthLabel }} — Chira Continuity Care (Triple C)</title>
    @vite(['resources/css/app.css'])
    <style>
        /* page-specific print rule — landscape A4 for a wide tabular report (not a design-system-wide rule) */
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--space-3);padding:var(--space-4) var(--space-6);background:var(--color-primary-800);">
        <a href="{{ route('reports.visit-summary') }}" class="btn btn-secondary btn-sm">&larr; กลับไปสรุปข้อมูลสำคัญ</a>

        <form method="GET" action="{{ route('reports.monthly') }}" style="display:flex;align-items:center;gap:var(--space-3);">
            <select name="month" onchange="this.form.submit()">
                @foreach ($monthOptions as $value => $label)
                    <option value="{{ $value }}" @selected($value === $monthValue)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">ดูรายงาน</button>
        </form>

        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">พิมพ์รายงานนี้</button>
    </div>

    <div style="max-width:1180px;margin:0 auto;padding:var(--space-6);display:flex;flex-direction:column;gap:var(--space-6);">

        <div class="page-head">
            <h1 class="h1">แบบฟอร์มสรุปผลงานเยี่ยมบ้านประจำเดือน {{ $monthLabel }}</h1>
            <span class="sub">Chira Continuity Care (Triple C) &middot; พิมพ์เมื่อ {{ now()->translatedFormat('d/m/Y') }}</span>
        </div>

        @if ($report['total_referrals'] === 0)
            <div class="card">
                <div class="card-body" style="text-align:center;padding:var(--space-12) var(--space-5);">
                    <p class="h2">ไม่มีข้อมูลสำหรับเดือนนี้</p>
                    <p class="caption">ยังไม่มีใบส่งต่อที่สร้างในเดือน {{ $monthLabel }} ในระบบ ลองเลือกเดือนอื่นจากช่องด้านบน</p>
                </div>
            </div>
        @else

            {{-- ============ Section 1: KPI + timeliness ============ --}}
            <div class="card">
                <div class="card-head">
                    <div class="h2">1. สรุปจำนวนการส่งเยี่ยมและความทันเวลา</div>
                </div>
                <div class="card-body">
                    <section class="kpi-grid" aria-label="สรุปตัวเลขการส่งเยี่ยมบ้านประจำเดือน">
                        <div class="kpi-tile">
                            <span class="caption">จำนวนการส่งต่อทั้งหมด</span>
                            <span class="kpi-value">{{ $report['total_referrals'] }}</span>
                            <span class="caption">ราย</span>
                        </div>
                        <div class="kpi-tile">
                            <span class="caption">ในเขต</span>
                            <span class="kpi-value">{{ $report['in_area_count'] }}</span>
                            <span class="caption">ราย</span>
                        </div>
                        <div class="kpi-tile">
                            <span class="caption">นอกเขต</span>
                            <span class="kpi-value">{{ $report['out_area_count'] }}</span>
                            <span class="caption">ราย</span>
                        </div>
                        <div class="kpi-tile">
                            <span class="caption">ยังไม่ได้รับการเยี่ยม/ยืนยันแผน</span>
                            <span class="kpi-value {{ $report['not_yet_visited_count'] > 0 ? 'risk' : '' }}">{{ $report['not_yet_visited_count'] }}</span>
                            <span class="caption">ราย — ในเขต {{ $report['not_yet_visited_in_area'] }} &middot; นอกเขต {{ $report['not_yet_visited_out_area'] }}</span>
                        </div>
                    </section>

                    <div style="margin-top:var(--space-5);">
                        <div class="kpi-split-row">
                            <div class="kpi-split-item">
                                <div class="kpi-split-top">
                                    <span class="kpi-split-label">ทันใน 5 วัน (ด่วน)</span>
                                    <span class="kpi-split-value">{{ $report['timeliness']['within_5'] }}</span>
                                </div>
                                <span class="kpi-split-sub">ราย</span>
                            </div>
                            <div class="kpi-split-item">
                                <div class="kpi-split-top">
                                    <span class="kpi-split-label">ทันใน 14 วัน</span>
                                    <span class="kpi-split-value">{{ $report['timeliness']['within_14'] }}</span>
                                </div>
                                <span class="kpi-split-sub">ราย</span>
                            </div>
                            <div class="kpi-split-item">
                                <div class="kpi-split-top">
                                    <span class="kpi-split-label">ทันใน 30 วัน</span>
                                    <span class="kpi-split-value">{{ $report['timeliness']['within_30'] }}</span>
                                </div>
                                <span class="kpi-split-sub">ราย</span>
                            </div>
                            <div class="kpi-split-item">
                                <div class="kpi-split-top">
                                    <span class="kpi-split-label">เกิน 30 วัน</span>
                                    <span class="kpi-split-value">{{ $report['timeliness']['over_30'] }}</span>
                                </div>
                                <span class="kpi-split-sub">ราย</span>
                            </div>
                            <div class="kpi-split-item">
                                <div class="kpi-split-top">
                                    <span class="kpi-split-label">ยังไม่ได้เยี่ยมครั้งแรก</span>
                                    <span class="kpi-split-value">{{ $report['timeliness']['not_visited'] }}</span>
                                </div>
                                <span class="kpi-split-sub">ราย</span>
                            </div>
                        </div>
                    </div>

                    @php $u = $report['urgency_breakdown']; @endphp
                    <div style="margin-top:var(--space-5);">
                        <p class="label">ความทันเวลาแยกตามความเร่งด่วน (ด่วน = บ้านแดง เกณฑ์ 5 วัน, ทั่วไป = บ้านเขียว/เหลือง เกณฑ์ 14/30 วัน — ไม่รวม Palliative ซึ่งใช้ PPS Score กำหนดรอบเยี่ยมเอง)</p>
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

            {{-- ============ Section 2: breakdowns ============ --}}
            <div class="card">
                <div class="card-head">
                    <div class="h2">2. สรุปผลการเยี่ยมบ้านและรับส่งต่อ</div>
                </div>
                <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-6);">
                    <div>
                        <p class="label">แบ่งตามประเภทเคส</p>
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>ประเภทเคส</th><th style="text-align:right;">จำนวน</th></tr></thead>
                                <tbody>
                                    @forelse ($report['case_type_breakdown'] as $row)
                                        <tr>
                                            <td><span class="chip chip-casetype">{{ $row['name'] }}</span></td>
                                            <td style="text-align:right;">{{ $row['count'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" style="text-align:center;">ไม่มีข้อมูล</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div>
                        <p class="label">แบ่งตามสถานะผู้ป่วย</p>
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>สถานะ</th><th style="text-align:right;">จำนวน</th></tr></thead>
                                <tbody>
                                    @forelse ($report['patient_status_breakdown'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td style="text-align:right;">{{ $row['count'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" style="text-align:center;">ไม่มีข้อมูล</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div>
                        <p class="label">แบ่งตามหอผู้ป่วยต้นทาง</p>
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>หอผู้ป่วย</th><th style="text-align:right;">จำนวน</th></tr></thead>
                                <tbody>
                                    @forelse ($report['ward_breakdown'] as $row)
                                        <tr>
                                            <td>{{ $row['name'] }}</td>
                                            <td style="text-align:right;">{{ $row['count'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" style="text-align:center;">ไม่มีข้อมูล</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div>
                        <p class="label">เยี่ยมตาม Clinical Tracer</p>
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>Tracer</th><th style="text-align:right;">จำนวน</th></tr></thead>
                                <tbody>
                                    @forelse ($report['clinical_tracer_breakdown'] as $row)
                                        <tr>
                                            <td>{{ $row['tag'] }}</td>
                                            <td style="text-align:right;">{{ $row['count'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" style="text-align:center;">ไม่มีข้อมูล</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ Section 3: DM/COPD complications (AI-assisted) ============ --}}
            <div class="card">
                <div class="card-head">
                    <div class="h2">3. ภาวะแทรกซ้อน/อุบัติการณ์ โรค DM และ COPD ประจำเดือน {{ $monthLabel }}</div>
                    <div class="sub">สร้างโดย AI สแกนบันทึกผลการเยี่ยมของเคสที่มีโรคประจำตัวเป็น DM หรือ COPD เท่านั้น — เป็นข้อมูลประกอบเท่านั้น ไม่ใช่การตัดสินใจทางคลินิก</div>
                </div>
                <div class="card-body">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>ผู้ป่วย</th><th>กลุ่มโรค</th><th>สรุปจาก AI</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($dmCopdComplications as $row)
                                    <tr>
                                        <td>{{ $row['patient_name'] }}</td>
                                        <td>
                                            <span class="chip {{ $row['disease_tag'] === 'DM/COPD' ? 'chip-risk' : 'chip-warning' }}">{{ $row['disease_tag'] }}</span>
                                        </td>
                                        <td>{{ $row['summary'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" style="text-align:center;">ไม่พบเคสที่เข้าเงื่อนไขในกลุ่มผู้ป่วย DM/COPD ในเดือนนี้</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ============ Section 4: satisfaction trend ============ --}}
            <div class="card">
                <div class="card-head">
                    <div class="h2">4. ความพึงพอใจในงานเยี่ยมบ้าน (12 เดือนล่าสุด)</div>
                </div>
                <div class="card-body">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>หัวข้อ</th>
                                    @foreach ($report['satisfaction_trend'] as $point)
                                        <th style="text-align:right;">{{ \App\Http\Controllers\Reports\MonthlyReportController::monthShortLabel(\Illuminate\Support\Carbon::createFromFormat('Y-m-d', $point['month'].'-01')) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>ความพึงพอใจของผู้รับบริการในชุมชน — ในเขต (คะแนนเต็ม 5)</td>
                                    @foreach ($report['satisfaction_trend'] as $point)
                                        <td style="text-align:right;">{{ $point['in_area_average'] !== null ? number_format($point['in_area_average'], 2) : 'ไม่มี case' }}</td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td>ความพึงพอใจของผู้ป่วยและญาติ — ประคับประคอง (คะแนนเต็ม 5)</td>
                                    @foreach ($report['satisfaction_trend'] as $point)
                                        <td style="text-align:right;">{{ $point['palliative_average'] !== null ? number_format($point['palliative_average'], 2) : 'ไม่มี case' }}</td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ============ Section 5: case log ============ --}}
            <div class="card">
                <div class="card-head">
                    <div class="h2">5. ทะเบียนตอบกลับเยี่ยมบ้าน ประจำเดือน {{ $monthLabel }}</div>
                </div>
                <div class="card-body">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ผู้ป่วย</th>
                                    <th>หอผู้ป่วย</th>
                                    <th>สถานะ</th>
                                    <th>กำหนดเยี่ยม</th>
                                    <th>เยี่ยมจริง</th>
                                    <th>วินิจฉัย/โรคประจำตัว</th>
                                    <th>ผลการเยี่ยม</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($report['case_log'] as $row)
                                    <tr>
                                        <td>
                                            <span class="patient-name">{{ $row['patient_name'] }}</span>
                                            @if ($row['patient_age'] !== null)
                                                <span class="caption">อายุ {{ $row['patient_age'] }} ปี</span>
                                            @endif
                                        </td>
                                        <td>{{ $row['ward_name'] }}</td>
                                        <td>
                                            @if ($row['on_time'] === null)
                                                <span class="chip chip-warning">ยังไม่ได้เยี่ยม</span>
                                            @elseif ($row['on_time'])
                                                <span class="chip chip-success">ทันเวลา</span>
                                            @else
                                                <span class="chip chip-risk">ล่าช้า</span>
                                            @endif
                                        </td>
                                        <td class="due-date">{{ $row['due_date']?->format('d/m/Y') ?? '—' }}</td>
                                        <td class="due-date">{{ $row['visited_at']?->format('d/m/Y') ?? '—' }}</td>
                                        <td>
                                            {{ $row['diagnosis'] ?? '—' }}
                                            @if ($row['underlying_disease'])
                                                <br><span class="caption">โรคประจำตัว: {{ $row['underlying_disease'] }}</span>
                                            @endif
                                        </td>
                                        <td style="white-space:pre-line;max-width:320px;">{{ $row['visit_result'] ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" style="text-align:center;">ยังไม่มีทะเบียนตอบกลับเยี่ยมบ้านในเดือนนี้</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        @endif

    </div>

</body>
</html>
