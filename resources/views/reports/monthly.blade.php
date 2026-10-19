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
            <span class="sub">Chira Continuity Care (Triple C) &middot; พิมพ์เมื่อ {{ \App\Support\ThaiDate::date(now()) }}</span>
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
                    @php $t = $report['visit_timeliness_table']; @endphp
                    <style>
                        /* ช่อง "ยังไม่ได้รับการเยี่ยม" สูง 2 แถวทางขวา (บนจอเล็กกลับเป็นกล่องปกติ) */
                        .kpi-tile.span-tall { grid-column: 4; grid-row: 1 / span 2; justify-content: center; }
                        @media (max-width: 1024px) { .kpi-tile.span-tall { grid-column: auto; grid-row: auto; } }
                    </style>
                    <section class="kpi-grid" aria-label="สรุปตัวเลขการส่งเยี่ยมบ้านประจำเดือน">
                        <div class="kpi-tile">
                            <span class="caption">จำนวนการส่งเยี่ยมทั้งหมด</span>
                            <span class="kpi-value">{{ $report['total_referrals'] }}</span>
                            <span class="hint">ราย</span>
                        </div>
                        <div class="kpi-tile">
                            <span class="caption">ในเขต</span>
                            <span class="kpi-value">{{ $report['in_area_count'] }}</span>
                            <span class="hint">ราย</span>
                        </div>
                        <div class="kpi-tile">
                            <span class="caption">นอกเขต</span>
                            <span class="kpi-value">{{ $report['out_area_count'] }}</span>
                            <span class="hint">ราย</span>
                        </div>
                        <div class="kpi-tile alert span-tall">
                            <span class="caption">ยังไม่ได้รับการเยี่ยม/ยืนยันแผน</span>
                            <span class="kpi-value risk" style="font-size:56px;line-height:64px;">{{ $report['not_yet_visited_count'] }}</span>
                            <span class="hint">ราย — ในเขต {{ $report['not_yet_visited_in_area'] }} &middot; นอกเขต {{ $report['not_yet_visited_out_area'] }}</span>
                        </div>
                        <div class="kpi-tile">
                            <span class="caption">ทันใน 5 วัน (ด่วน)</span>
                            <span class="kpi-value">{{ $t['combined']['urgent']['on_time'] }}</span>
                            <span class="hint">ราย</span>
                        </div>
                        <div class="kpi-tile">
                            <span class="caption">ทันใน 14 วัน</span>
                            <span class="kpi-value">{{ $t['combined']['general']['within_14'] }}</span>
                            <span class="hint">ราย</span>
                        </div>
                        <div class="kpi-tile">
                            <span class="caption">ทันใน 30 วัน</span>
                            <span class="kpi-value">{{ $t['combined']['general']['within_30'] }}</span>
                            <span class="hint">ราย</span>
                        </div>
                    </section>

                    <div class="table-wrap" style="margin-top:var(--space-5);">
                        <table>
                            <thead>
                                <tr>
                                    <th>ประเภท</th>
                                    <th style="text-align:right;">จำนวนการส่งเยี่ยม</th>
                                    <th style="text-align:right;">จำนวนเยี่ยมจริง</th>
                                    <th style="text-align:right;">ทันใน 5 วัน (ด่วน)</th>
                                    <th style="text-align:right;">ไม่ทัน (ด่วน)</th>
                                    <th style="text-align:right;">รวม (ด่วน)</th>
                                    <th style="text-align:right;">ทันใน 14 วัน (ทั่วไป)</th>
                                    <th style="text-align:right;">ทันใน 30 วัน (ทั่วไป)</th>
                                    <th style="text-align:right;">เกิน 30 วัน (ทั่วไป)</th>
                                    <th style="text-align:right;">รวม (ทั่วไป)</th>
                                    <th style="text-align:right;">รอเยี่ยมทั้งหมด</th>
                                    <th style="text-align:right;">ไม่ระบุกลุ่ม / Palliative</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (['in_area' => 'ในเขต', 'out_area' => 'นอกเขต'] as $zoneKey => $zoneLabel)
                                    <tr>
                                        <td>{{ $zoneLabel }}</td>
                                        <td style="text-align:right;">{{ $t[$zoneKey]['total_referred'] }}</td>
                                        <td style="text-align:right;">{{ $t[$zoneKey]['visited_actual'] }}</td>
                                        <td style="text-align:right;">{{ $t[$zoneKey]['urgent']['on_time'] }}</td>
                                        <td style="text-align:right;">{{ $t[$zoneKey]['urgent']['late'] }}</td>
                                        <td style="text-align:right;">{{ $t[$zoneKey]['urgent']['total'] }}</td>
                                        <td style="text-align:right;">{{ $t[$zoneKey]['general']['within_14'] }}</td>
                                        <td style="text-align:right;">{{ $t[$zoneKey]['general']['within_30'] }}</td>
                                        <td style="text-align:right;">{{ $t[$zoneKey]['general']['over_30'] }}</td>
                                        <td style="text-align:right;">{{ $t[$zoneKey]['general']['total'] }}</td>
                                        <td style="text-align:right;">{{ $t[$zoneKey]['waiting'] }}</td>
                                        <td style="text-align:right;">{{ $t[$zoneKey]['unclassified'] }}</td>
                                    </tr>
                                @endforeach
                                <tr style="font-weight:700;background:var(--color-neutral-100);">
                                    <td>รวม</td>
                                    <td style="text-align:right;">{{ $t['combined']['total_referred'] }}</td>
                                    <td style="text-align:right;">{{ $t['combined']['visited_actual'] }}</td>
                                    <td style="text-align:right;">{{ $t['combined']['urgent']['on_time'] }}</td>
                                    <td style="text-align:right;">{{ $t['combined']['urgent']['late'] }}</td>
                                    <td style="text-align:right;">{{ $t['combined']['urgent']['total'] }}</td>
                                    <td style="text-align:right;">{{ $t['combined']['general']['within_14'] }}</td>
                                    <td style="text-align:right;">{{ $t['combined']['general']['within_30'] }}</td>
                                    <td style="text-align:right;">{{ $t['combined']['general']['over_30'] }}</td>
                                    <td style="text-align:right;">{{ $t['combined']['general']['total'] }}</td>
                                    <td style="text-align:right;">{{ $t['combined']['waiting'] }}</td>
                                    <td style="text-align:right;">{{ $t['combined']['unclassified'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="caption" style="margin-top:var(--space-3);display:flex;flex-direction:column;gap:4px;">
                        <p><b>ตรวจยอด:</b> จำนวนการส่งเยี่ยม = จำนวนเยี่ยมจริง + รอเยี่ยมทั้งหมด + ไม่ระบุกลุ่ม/Palliative ในทุกแถว</p>
                        <p>ด่วน = บ้านแดง, ทั่วไป = บ้านเหลืองและเขียว นับวันจากวันที่ส่งต่อถึงวันที่เยี่ยมจริง</p>
                        <p>นอกเขต หมายถึงผู้ป่วยที่ต้องการเยี่ยมบ้านและพักอาศัยนอกเขตพื้นที่รับผิดชอบ</p>
                        <p>จำนวนเยี่ยมจริง หมายถึงเคสที่มีผลการเยี่ยม/โทรติดตามครั้งแรกบันทึกไว้แล้วเท่านั้น (เฉพาะเคสที่ระบุกลุ่มบ้านสี) และเท่ากับผลรวม "รวม (ด่วน)" + "รวม (ทั่วไป)"</p>
                        <p>ทันใน 5 วัน (ด่วน) เยี่ยมภายใน 5 วันนับจากส่งต่อ, ไม่ทัน (ด่วน) เกิน 5 วัน — ทันใน 14 วัน / ทันใน 30 วัน / เกิน 30 วัน (ทั่วไป) นับจากวันส่งต่อเช่นกัน</p>
                        <p>รอเยี่ยมทั้งหมด หมายถึงเคสที่ระบุกลุ่มบ้านสีแล้วแต่ยังไม่ได้รับการเยี่ยม/โทรครั้งแรก — รวมทั้งที่ยังรอยืนยันแผน มีนัดภายในเดือนนี้ และมีนัดเดือนหน้า</p>
                        <p>ไม่ระบุกลุ่ม / Palliative หมายถึงเคสกลุ่ม 4 Palliative Care ซึ่งไม่มีเกณฑ์จำนวนวันตายตัว (ใช้คะแนน PPS กำหนดรอบเยี่ยม) รวมถึงใบส่งต่อเดิมที่สร้างก่อนที่ระบบจะบังคับเลือกกลุ่มความรุนแรง (ใบใหม่ต้องเลือกกลุ่มทุกใบ) จึงจัดว่าทัน/ไม่ทันไม่ได้ และแยกไว้ในช่องนี้ช่องเดียว ทั้งที่เยี่ยมแล้วและยังไม่ได้เยี่ยม</p>
                        <p>ยังไม่ได้รับการเยี่ยม/ยืนยันแผน (KPI ด้านบน) หมายถึงทุกเคสที่ยังไม่มีผลเยี่ยมครั้งแรก ไม่ว่ากลุ่มใด (= รอเยี่ยมทั้งหมด + ไม่ระบุกลุ่ม/Palliative ที่ยังไม่ได้เยี่ยม)</p>
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
                            <tbody id="dmCopdBody">
                                <tr><td colspan="3" style="text-align:center;color:var(--color-neutral-500);">กำลังโหลดรายชื่อเคส…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ============ Section 4: satisfaction trend ============ --}}
            @php $trend = $report['satisfaction_trend']; @endphp
            <div class="card">
                <div class="card-head">
                    <div class="h2">4. ความพึงพอใจในงานเยี่ยมบ้าน ปีงบประมาณ {{ $trend['fiscal_year_be'] }}</div>
                </div>
                <div class="card-body">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>หัวข้อการประเมิน</th>
                                    <th style="text-align:right;">เป้าหมาย (ร้อยละ)</th>
                                    @foreach ($trend['months'] as $point)
                                        <th style="text-align:right;">{{ \App\Http\Controllers\Reports\MonthlyReportController::monthShortLabel(\Illuminate\Support\Carbon::createFromFormat('Y-m-d', $point['month'].'-01')) }}</th>
                                    @endforeach
                                    <th style="text-align:right;">ค่าเฉลี่ย</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>ความพึงพอใจของผู้รับบริการในชุมชน (ในเขต)</td>
                                    <td style="text-align:right;">{{ $trend['target_percent'] }}</td>
                                    @foreach ($trend['months'] as $point)
                                        <td style="text-align:right;{{ $point['in_area_percent'] !== null && $point['in_area_percent'] < $trend['target_percent'] ? 'color:var(--color-risk);font-weight:600;' : '' }}">
                                            {{ $point['is_future'] ? '–' : ($point['in_area_percent'] !== null ? $point['in_area_percent'] : 'ไม่มี case') }}
                                        </td>
                                    @endforeach
                                    <td style="text-align:right;">{{ $trend['in_area_average_percent'] ?? '–' }}</td>
                                </tr>
                                <tr>
                                    <td>ความพึงพอใจของผู้ป่วยและญาติต่อการดูแลผู้ป่วยแบบประคับประคอง</td>
                                    <td style="text-align:right;">{{ $trend['target_percent'] }}</td>
                                    @foreach ($trend['months'] as $point)
                                        <td style="text-align:right;{{ $point['palliative_percent'] !== null && $point['palliative_percent'] < $trend['target_percent'] ? 'color:var(--color-risk);font-weight:600;' : '' }}">
                                            {{ $point['is_future'] ? '–' : ($point['palliative_percent'] !== null ? $point['palliative_percent'] : 'ไม่มี case') }}
                                        </td>
                                    @endforeach
                                    <td style="text-align:right;">{{ $trend['palliative_average_percent'] ?? '–' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ============ Section 5: photo gallery ============ --}}
            <div class="card">
                <div class="card-head">
                    <div class="h2">5. ภาพการปฏิบัติงาน</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('reports.monthly.photos.store') }}" enctype="multipart/form-data" class="field-grid no-print" style="margin-bottom:var(--space-4);">
                        @csrf
                        <input type="hidden" name="month" value="{{ $monthValue }}">
                        <div class="field">
                            <label>เพิ่มภาพ</label>
                            <input type="file" name="photo" accept="image/*" required>
                        </div>
                        <div class="field">
                            <label>คำอธิบายภาพ</label>
                            <input type="text" name="caption" placeholder="เช่น เยี่ยมบ้านผู้ป่วย Palliative Care">
                        </div>
                        <div class="field" style="align-self:end;">
                            <button type="submit" class="btn btn-secondary">อัปโหลด</button>
                        </div>
                    </form>

                    @if ($photos->isEmpty())
                        <div class="empty-note">ยังไม่มีภาพการปฏิบัติงานสำหรับเดือนนี้</div>
                    @else
                        <div class="photo-grid">
                            @foreach ($photos as $photo)
                                <div class="photo-tile">
                                    <img src="{{ route('reports.monthly.photos.show', $photo) }}" alt="{{ $photo->caption ?? 'ภาพการปฏิบัติงาน' }}">
                                    <span class="photo-cap">{{ $photo->caption ?: 'ภาพที่ '.$loop->iteration }}</span>
                                    <form method="POST" action="{{ route('reports.monthly.photos.destroy', $photo) }}" class="no-print" onsubmit="return confirm('ลบภาพนี้?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-secondary btn-sm">ลบ</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- ============ Section 6: case log ============ --}}
            <div class="card">
                <div class="card-head">
                    <div class="h2">6. ทะเบียนตอบกลับเยี่ยมบ้าน ประจำเดือน {{ $monthLabel }}</div>
                </div>
                <div class="card-body">
                    @forelse ($report['case_log'] as $row)
                        <div class="case-card">
                            <div class="case-head">
                                <div class="case-head-left">
                                    <span class="case-no">{{ $loop->iteration }}</span>
                                    <span class="case-name">{{ $row['patient_name'] }}</span>
                                    @if ($row['patient_age'] !== null)
                                        <span class="case-age">อายุ {{ $row['patient_age'] }} ปี</span>
                                    @endif
                                </div>
                                <div class="case-badges">
                                    <span class="badge badge-ward">{{ $row['ward_name'] }}</span>
                                    @if ($row['on_time'] === null)
                                        <span class="badge badge-ward">ยังไม่ได้เยี่ยม</span>
                                    @elseif ($row['on_time'])
                                        <span class="badge badge-ontime">ทันเวลา</span>
                                    @else
                                        <span class="badge badge-late">ล่าช้า</span>
                                    @endif
                                </div>
                            </div>
                            <div class="case-dates">
                                กำหนดเยี่ยม {{ \App\Support\ThaiDate::date($row['due_date']) ?? '—' }}
                                &middot; เยี่ยมจริง {{ \App\Support\ThaiDate::date($row['visited_at']) ?? '—' }}
                            </div>
                            <div class="case-dx">
                                <span class="l">วินิจฉัย/โรคประจำตัว:</span> {{ $row['diagnosis'] ?? '—' }}
                                @if ($row['underlying_disease']) &middot; {{ $row['underlying_disease'] }} @endif
                            </div>
                            @if ($row['ward_concern'])
                                <div class="case-block">
                                    <span class="l">ประเด็นที่หอผู้ป่วยต้องการติดตาม</span>
                                    <ul><li style="white-space:pre-line;">{{ $row['ward_concern'] }}</li></ul>
                                </div>
                            @endif
                            <div class="case-block">
                                <span class="l">ผลการเยี่ยม</span>
                                <ul><li style="white-space:pre-line;">{{ $row['visit_result'] ?? 'ยังไม่มีผลการเยี่ยม' }}</li></ul>
                            </div>
                        </div>
                    @empty
                        <div class="empty-note">ยังไม่มีทะเบียนตอบกลับเยี่ยมบ้านในเดือนนี้</div>
                    @endforelse
                </div>
            </div>

            <div class="sign-block">
                <div class="memo">
                    <div>ที่ .......................................</div>
                    <div class="line" style="margin-top:20px;"></div>
                </div>
                <div class="approve">
                    <div class="line"></div>
                    <div>ตรวจถูกต้อง</div>
                </div>
            </div>

        @endif

    </div>

    <script>
        // ส่วนที่ 3: แสดงรายชื่อเคสทันที แล้วให้ AI สรุปทีละเคสแบบเบื้องหลัง (เคสที่เคยสรุปไว้แล้วขึ้นทันที) ไม่ให้หน้ารายงานโหลดช้า
        (function () {
            var body = document.getElementById('dmCopdBody');
            if (!body) return;
            var casesUrl = @json($dmCopdCasesUrl);
            var analyzeUrl = @json($dmCopdAnalyzeUrl);
            var csrf = document.querySelector('meta[name="csrf-token"]');
            csrf = csrf ? csrf.getAttribute('content') : '';

            function esc(text) {
                return String(text == null ? '' : text).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }
            function message(text) {
                body.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--color-neutral-500);">' + esc(text) + '</td></tr>';
            }
            function fillSummary(cell, result) {
                cell.innerHTML = (result.processed === false ? '<span class="chip chip-warning">AI ประมวลผลไม่สำเร็จ</span> ' : '') + esc(result.summary);
            }

            fetch(casesUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
                .then(function (data) {
                    if (!data.cases.length) { message('ไม่พบเคสที่เข้าเงื่อนไขในกลุ่มผู้ป่วย DM/COPD ในเดือนนี้'); return; }

                    body.innerHTML = '';
                    var pending = [];
                    data.cases.forEach(function (c) {
                        var tr = document.createElement('tr');
                        tr.innerHTML = '<td>' + esc(c.patient_name) + '</td>'
                            + '<td><span class="chip ' + (c.disease_tag === 'DM/COPD' ? 'chip-risk' : 'chip-warning') + '">' + esc(c.disease_tag) + '</span></td>'
                            + '<td></td>';
                        var cell = tr.lastChild;
                        if (c.result) {
                            fillSummary(cell, c.result);
                        } else {
                            cell.innerHTML = '<span class="caption">กำลังให้ AI สรุป… (ครั้งแรกอาจใช้เวลา 1–2 นาทีต่อเคส ไม่ต้องรอหน้านี้ — ผลจะถูกจดจำไว้)</span>';
                            pending.push({ c: c, cell: cell });
                        }
                        body.appendChild(tr);
                    });

                    // ทำทีละเคสตามลำดับ ไม่ยิงพร้อมกัน (เครื่อง AI ที่แผนกประมวลผลได้ทีละคำขอ)
                    return pending.reduce(function (chain, item) {
                        return chain.then(function () {
                            return fetch(analyzeUrl.replace('__ID__', item.c.referral_id), {
                                method: 'POST',
                                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                                credentials: 'same-origin'
                            })
                                .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
                                .then(function (result) { fillSummary(item.cell, result); })
                                .catch(function () {
                                    fillSummary(item.cell, { processed: false, summary: 'ไม่สามารถประมวลผลได้ — โหลดหน้านี้ใหม่เพื่อลองอีกครั้ง' });
                                });
                        });
                    }, Promise.resolve());
                })
                .catch(function () { message('โหลดรายชื่อเคสไม่สำเร็จ — กรุณาโหลดหน้านี้ใหม่'); });
        })();
    </script>
</body>
</html>
