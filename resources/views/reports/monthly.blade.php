<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>รายงานประจำเดือน {{ $monthLabel }} — Chira Continuity Care (Triple C)</title>
    @vite(['resources/css/app.css'])
    <style>
        /* page-specific print rule — landscape A4 for a wide tabular report (not a design-system-wide rule) */
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            /* พิมพ์ให้เหมือนหน้าจอ: คงสีพื้น/ป้าย และไม่ตัดการ์ดรายเคสคาบสองหน้า */
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .rpt-table tr, .kpi-tile { break-inside: avoid; page-break-inside: avoid; }
            /* การ์ดรายเคสที่ข้อความยาวเกินหนึ่งหน้า ห้ามตั้ง break-inside: avoid (Chrome จะดันทั้งก้อนไปหน้าใหม่แล้วเหลือหน้าว่าง)
               จึงปล่อยให้ตัดหน้าได้ และใช้ตาราง 3 เซลล์แทน grid เพราะตัดข้ามหน้าได้เสถียรกว่า; หัวการ์ดต้องไม่ถูกทิ้งไว้ท้ายหน้าตามลำพัง */
            .case-cols { display: table !important; table-layout: fixed; border-spacing: 10px 0; margin: 0 -10px; width: calc(100% + 20px); }
            .case-col { display: table-cell !important; vertical-align: top; width: 30%; }
            .case-col:last-child { width: 40%; }
            .case-head, .case-dates { break-after: avoid; page-break-after: avoid; }
            /* เคสที่ข้อความยาว (case-long, เกิน ~900 ตัวอักษร): ตอนพิมพ์ซ่อนการ์ดปกติ แล้วใช้ตารางจริง (.case-print-table)
               ที่ตัดข้ามหน้าได้ตามบรรทัดและซ้ำหัวตาราง (ชื่อผู้ป่วย + ชื่อคอลัมน์) ทุกหน้า — เลี่ยงอาการ Chrome ทิ้งหัวการ์ดไว้หน้าว่าง */
            .case-card.case-long { display: none !important; }
            .case-print { display: block !important; border: 1px solid var(--color-neutral-200); border-radius: 12px; margin-bottom: 16px; }
            .case-print .cp-top { position: relative; z-index: 2; background: #fff; border-radius: 12px 12px 0 0; padding-top: 12px; }
            .case-print .cp-top .case-head, .case-print .cp-top .case-dates { margin-left: 16px; margin-right: 16px; }
            .cp-titles { display: grid; grid-template-columns: 30% 30% 40%; height: 6mm; align-items: center; }
            .cp-titles span { padding: 0 10px; font-size: 11px; font-weight: 800; color: var(--color-primary-700); white-space: nowrap; }
            .case-print-table { display: table !important; width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 0; margin-top: -15mm; }
            .case-print-table thead { display: table-header-group; }
            .case-print-table .cpt-th { padding: 0; height: 15mm; text-align: left; font-weight: normal; vertical-align: top; background: #fff; }
            .case-print-table .cpt-name { height: 9mm; display: flex; align-items: center; gap: 8px; padding: 0 16px; }
            .case-print-table td { vertical-align: top; padding: 0 10px 12px; }
            .case-print-table .case-col { display: block !important; width: auto !important; }

            /* ส่วนที่ 5 ภาพ: ขึ้นหน้าใหม่ และให้ 6 ภาพ (3 คอลัมน์ x 2 แถว) พอดีหน้าเดียว */
            .report-photos { break-before: page; page-break-before: always; }
            .report-photos .photo-grid { grid-template-columns: repeat(3, 1fr) !important; gap: 8px !important; }
            .report-photos .photo-tile { break-inside: avoid; page-break-inside: avoid; gap: 2px !important; }
            .report-photos .photo-tile img { aspect-ratio: auto !important; height: 58mm; object-fit: contain !important; background: #fff; }

            /* ส่วนที่ 1 (ช่องสรุป + ตาราง + หมายเหตุ) ต้องจบในหน้าเดียว ไม่ให้ตารางขาดไปหน้าถัดไป: ย่อช่องไฟ/ขนาดตัวเลขเฉพาะตอนพิมพ์ */
            .report-wrap { padding: 0 !important; gap: 10px !important; max-width: none !important; }
            .report-wrap .page-head { margin: 0 !important; }
            .report-sec1 { break-inside: avoid; page-break-inside: avoid; }
            /* การ์ดส่วนอื่นก็ไม่ขาดกลางหน้า ยกเว้นทะเบียนเคส (ยาวหลายหน้า ให้ตัดตามการ์ดรายเคสแทน) */
            .report-wrap > .card:not(.report-long):not(.report-photos) { break-inside: avoid; page-break-inside: avoid; }
            .report-sec1 .card-head { padding: 8px 14px !important; }
            .report-sec1 .card-body { padding: 8px 14px !important; }
            .report-sec1 .kpi-grid { gap: 8px !important; }
            .report-sec1 .kpi-tile { padding: 6px 12px !important; min-height: 0 !important; gap: 0 !important; }
            .report-sec1 .kpi-tile .kpi-value { font-size: 26px !important; line-height: 30px !important; }
            .report-sec1 .kpi-tile.span-tall .kpi-value { font-size: 44px !important; line-height: 48px !important; }
            .report-sec1 .table-wrap { margin-top: 8px !important; }
            .report-sec1 .rpt-table th, .report-sec1 .rpt-table td { padding: 4px 8px !important; font-size: 12px !important; }
            .report-sec1 .rpt-table td.rpt-key { font-size: 14px !important; }
            .report-sec1 .rpt-table tr.rpt-total td.rpt-key { font-size: 15px !important; }
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

    <div class="report-wrap" style="max-width:1180px;margin:0 auto;padding:var(--space-6);display:flex;flex-direction:column;gap:var(--space-6);">

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
            <div class="card report-sec1">
                <div class="card-head">
                    <div class="h2">1. สรุปจำนวนการส่งเยี่ยมและความทันเวลา</div>
                </div>
                <div class="card-body">
                    @php $t = $report['visit_timeliness_table']; @endphp
                    <style>
                        /* ช่อง "ยังไม่ได้รับการเยี่ยม" สูง 2 แถวทางขวา (บนจอเล็กกลับเป็นกล่องปกติ) */
                        .kpi-tile.span-tall { grid-column: 4; grid-row: 1 / span 2; justify-content: center; }
                        @media screen and (max-width: 1024px) { .kpi-tile.span-tall { grid-column: auto; grid-row: auto; } }
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
                            <span class="caption">รอเยี่ยมทั้งหมด</span>
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

                    <style>
                        /* ตารางส่วนที่ 1: ชุดคอลัมน์หลัก (ประเภท / จำนวนการส่งเยี่ยม / จำนวนเยี่ยมจริง / รอเยี่ยมทั้งหมด) ให้เด่นกว่าคอลัมน์ย่อย */
                        .rpt-table td, .rpt-table th { text-align: right; }
                        .rpt-table td:first-child, .rpt-table th:first-child { text-align: left; }
                        .rpt-table thead th { vertical-align: middle; }
                        .rpt-table .rpt-group { text-align: center; border-bottom: 1px solid var(--color-neutral-300); color: var(--color-neutral-700); }
                        .rpt-table th.rpt-key { background: var(--color-primary-100); color: var(--color-primary-800); font-size: 13px; font-weight: 800; }
                        .rpt-table td.rpt-key { background: var(--color-primary-50); font-weight: 800; font-size: 16px; color: var(--color-neutral-900); }
                        .rpt-table th.rpt-sub, .rpt-table td.rpt-sub { font-weight: 700; border-left: 1px dashed var(--color-neutral-300); }
                        .rpt-table tr.rpt-total td { background: var(--color-neutral-100); font-weight: 700; }
                        .rpt-table tr.rpt-total td.rpt-key { background: var(--color-primary-100); font-size: 17px; }
                    </style>
                    <div class="table-wrap" style="margin-top:var(--space-5);">
                        <table class="rpt-table">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="rpt-key">ประเภท</th>
                                    <th rowspan="2" class="rpt-key">จำนวนการส่งเยี่ยม</th>
                                    <th rowspan="2" class="rpt-key">จำนวนเยี่ยมจริง</th>
                                    <th colspan="3" class="rpt-group">เยี่ยมด่วน</th>
                                    <th colspan="4" class="rpt-group">เยี่ยมทั่วไป</th>
                                    <th rowspan="2">Palliative</th>
                                    <th rowspan="2" class="rpt-key">รอเยี่ยมทั้งหมด</th>
                                </tr>
                                <tr>
                                    <th>ทันใน 5 วัน</th>
                                    <th>ไม่ทัน</th>
                                    <th class="rpt-sub">รวม</th>
                                    <th>ทันใน 14 วัน</th>
                                    <th>ทันใน 30 วัน</th>
                                    <th>เกิน 30 วัน</th>
                                    <th class="rpt-sub">รวม</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (['in_area' => 'ในเขต', 'out_area' => 'นอกเขต'] as $zoneKey => $zoneLabel)
                                    <tr>
                                        <td><b>{{ $zoneLabel }}</b></td>
                                        <td class="rpt-key">{{ $t[$zoneKey]['total_referred'] }}</td>
                                        <td class="rpt-key">{{ $t[$zoneKey]['visited_actual'] }}</td>
                                        <td>{{ $t[$zoneKey]['urgent']['on_time'] }}</td>
                                        <td>{{ $t[$zoneKey]['urgent']['late'] }}</td>
                                        <td class="rpt-sub">{{ $t[$zoneKey]['urgent']['total'] }}</td>
                                        <td>{{ $t[$zoneKey]['general']['within_14'] }}</td>
                                        <td>{{ $t[$zoneKey]['general']['within_30'] }}</td>
                                        <td>{{ $t[$zoneKey]['general']['over_30'] }}</td>
                                        <td class="rpt-sub">{{ $t[$zoneKey]['general']['total'] }}</td>
                                        <td>{{ $t[$zoneKey]['unclassified'] }}</td>
                                        <td class="rpt-key">{{ $t[$zoneKey]['waiting'] }}</td>
                                    </tr>
                                @endforeach
                                <tr class="rpt-total">
                                    <td>รวม</td>
                                        <td class="rpt-key">{{ $t['combined']['total_referred'] }}</td>
                                        <td class="rpt-key">{{ $t['combined']['visited_actual'] }}</td>
                                        <td>{{ $t['combined']['urgent']['on_time'] }}</td>
                                        <td>{{ $t['combined']['urgent']['late'] }}</td>
                                        <td class="rpt-sub">{{ $t['combined']['urgent']['total'] }}</td>
                                        <td>{{ $t['combined']['general']['within_14'] }}</td>
                                        <td>{{ $t['combined']['general']['within_30'] }}</td>
                                        <td>{{ $t['combined']['general']['over_30'] }}</td>
                                        <td class="rpt-sub">{{ $t['combined']['general']['total'] }}</td>
                                        <td>{{ $t['combined']['unclassified'] }}</td>
                                        <td class="rpt-key">{{ $t['combined']['waiting'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="caption" style="margin-top:var(--space-3);display:flex;flex-direction:column;gap:4px;">
                        <p><b>ตรวจยอด:</b> จำนวนการส่งเยี่ยม = จำนวนเยี่ยมจริง + รอเยี่ยมทั้งหมด ในทุกแถว</p>
                        <p>จำนวนเยี่ยมจริง = รวมเยี่ยมด่วน (ทันใน 5 วัน + ไม่ทัน) + รวมเยี่ยมทั่วไป (ทันใน 14 วัน + ทันใน 30 วัน + เกิน 30 วัน) + Palliative นับเฉพาะเคสที่มีผลเยี่ยม/โทรครั้งแรกบันทึกไว้แล้ว</p>
                        <p>เยี่ยมด่วน = บ้านแดง (ทันใน 5 วัน / ไม่ทัน), เยี่ยมทั่วไป = บ้านเหลืองและเขียว (ทันใน 14 วัน / 30 วัน / เกิน 30 วัน) ทุกช่วงนับวันจากวันที่ส่งต่อถึงวันที่เยี่ยมจริง</p>
                        <p>Palliative = เคสกลุ่ม 4 Palliative Care ที่เยี่ยมแล้ว ซึ่งไม่มีเกณฑ์จำนวนวันตายตัว (ใช้คะแนน PPS กำหนดรอบเยี่ยม) จึงจัดทัน/ไม่ทันไม่ได้ (รวมใบส่งต่อเดิมที่สร้างก่อนระบบบังคับเลือกกลุ่มความรุนแรง)</p>
                        <p>รอเยี่ยมทั้งหมด = ทุกเคสที่ยังไม่ได้รับการเยี่ยม/โทรครั้งแรก ไม่ว่ากลุ่มใด รวมทั้งที่ยังรอยืนยันแผน มีนัดภายในเดือนนี้ และมีนัดเดือนหน้า (เท่ากับช่อง "รอเยี่ยมทั้งหมด" ด้านบน)</p>
                        <p>นอกเขต หมายถึงผู้ป่วยที่ต้องการเยี่ยมบ้านและพักอาศัยนอกเขตพื้นที่รับผิดชอบ</p>
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
                    <div class="sub">สร้างโดย AI สแกนบันทึกผลการติดตามที่ไปเยี่ยมบ้านแล้วของเคสที่มีโรคประจำตัวเป็น DM หรือ COPD แสดงเฉพาะเคสที่พบภาวะแทรกซ้อน — เป็นข้อมูลประกอบเท่านั้น ไม่ใช่การตัดสินใจทางคลินิก</div>
                </div>
                <div class="card-body">
                    <p id="dmCopdProgress" class="caption no-print" style="margin-bottom:var(--space-2);"></p>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>ผู้ป่วย</th><th>กลุ่มโรค</th><th>สรุปจาก AI</th></tr>
                            </thead>
                            <tbody id="dmCopdBody">
                                <tr><td colspan="3" style="text-align:center;color:var(--color-neutral-500);">กำลังโหลดรายชื่อเคส…</td></tr>
                            </tbody>
                            @if ($dmCopdManual->isNotEmpty())
                                <tbody id="dmCopdManualBody">
                                    @foreach ($dmCopdManual as $entry)
                                        <tr>
                                            <td>{{ $entry->patient_label }}</td>
                                            <td><span class="chip {{ $entry->disease_tag === 'DM/COPD' ? 'chip-risk' : 'chip-warning' }}">{{ $entry->disease_tag }}</span></td>
                                            <td>
                                                <span class="chip chip-method">เพิ่มเอง</span>
                                                {{ $entry->summary }}
                                                @if ($canEditReport)
                                                    <form method="POST" action="{{ route('reports.monthly.dm-copd-manual.destroy', $entry) }}" class="no-print" style="display:inline;" onsubmit="return confirm('ลบรายการที่เพิ่มเองนี้?');">
                                                        @csrf @method('DELETE')
                                                        <input type="hidden" name="month" value="{{ $monthValue }}">
                                                        <button type="submit" class="btn btn-secondary btn-sm" style="margin-left:8px;">ลบ</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            @endif
                        </table>
                    </div>

                    @if ($canEditReport)
                        <details class="no-print" style="margin-top:var(--space-3);">
                            <summary style="cursor:pointer;font-weight:700;">+ เพิ่มรายการเอง (กรณี AI ตรวจไม่พบหรือไม่มีข้อมูลในระบบ)</summary>
                            <form method="POST" action="{{ route('reports.monthly.dm-copd-manual.store') }}" class="field-grid" style="margin-top:var(--space-3);">
                                @csrf
                                <input type="hidden" name="month" value="{{ $monthValue }}">
                                <div class="field">
                                    <label for="dmcopd-label">ชื่อผู้ป่วย (ตามรูปแบบในตาราง)</label>
                                    <input id="dmcopd-label" type="text" name="patient_label" maxlength="150" placeholder="เช่น นาย รักชาติ ย." required>
                                </div>
                                <div class="field">
                                    <label for="dmcopd-tag">กลุ่มโรค</label>
                                    <select id="dmcopd-tag" name="disease_tag" required>
                                        @foreach (\App\Models\DmCopdManualEntry::TAGS as $tag)
                                            <option value="{{ $tag }}">{{ $tag }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field" style="grid-column:1 / -1;">
                                    <label for="dmcopd-summary">ภาวะแทรกซ้อน/อุบัติการณ์ที่พบ</label>
                                    <textarea id="dmcopd-summary" name="summary" rows="2" maxlength="1000" required></textarea>
                                </div>
                                <div><button type="submit" class="btn btn-primary btn-sm">เพิ่มรายการ</button></div>
                            </form>
                        </details>

                        @if (count($dmCopdHidden))
                            <details class="no-print" style="margin-top:var(--space-3);">
                                <summary style="cursor:pointer;font-weight:700;">เคสที่ลบออกจากรายงานนี้ ({{ count($dmCopdHidden) }}) — กู้คืนได้</summary>
                                <ul style="margin:var(--space-2) 0 0;padding-left:var(--space-5);">
                                    @foreach ($dmCopdHidden as $hidden)
                                        <li style="margin-bottom:4px;">
                                            {{ $hidden['patient_name'] }} <span class="chip chip-warning">{{ $hidden['disease_tag'] }}</span>
                                            <form method="POST" action="{{ route('reports.monthly.dm-copd.restore', $hidden['referral_id']) }}" style="display:inline;">
                                                @csrf
                                                <input type="hidden" name="month" value="{{ $monthValue }}">
                                                <button type="submit" class="btn btn-secondary btn-sm" style="margin-left:8px;">กู้คืน</button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    @endif
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
                                            {{ $point['is_future'] ? '–' : ($point['in_area_percent'] !== null ? $point['in_area_percent'] : 'ไม่มีผลประเมินในระบบ') }}{{ $point['in_area_manual'] ? '*' : '' }}
                                        </td>
                                    @endforeach
                                    <td style="text-align:right;">{{ $trend['in_area_average_percent'] ?? '–' }}</td>
                                </tr>
                                <tr>
                                    <td>ความพึงพอใจของผู้ป่วยและญาติต่อการดูแลผู้ป่วยแบบประคับประคอง</td>
                                    <td style="text-align:right;">{{ $trend['target_percent'] }}</td>
                                    @foreach ($trend['months'] as $point)
                                        <td style="text-align:right;{{ $point['palliative_percent'] !== null && $point['palliative_percent'] < $trend['target_percent'] ? 'color:var(--color-risk);font-weight:600;' : '' }}">
                                            {{ $point['is_future'] ? '–' : ($point['palliative_percent'] !== null ? $point['palliative_percent'] : 'ไม่มีผลประเมินในระบบ') }}{{ $point['palliative_manual'] ? '*' : '' }}
                                        </td>
                                    @endforeach
                                    <td style="text-align:right;">{{ $trend['palliative_average_percent'] ?? '–' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    @if (count($trend['manual_entries']))
                        <p class="caption" style="margin-top:var(--space-2);">
                            * ค่าที่กรอกเอง (เดือนที่ไม่มีแบบประเมินในระบบ):
                            @foreach ($trend['manual_entries'] as $m)
                                {{ \App\Http\Controllers\Reports\MonthlyReportController::monthShortLabel(\Illuminate\Support\Carbon::createFromFormat('Y-m-d', $m['month'].'-01')) }}
                                {{ $m['audience'] === 'in_area' ? 'ในเขต' : 'ประคับประคอง' }} = {{ $m['percent'] }}
                                @if ($m['note']) ({{ $m['note'] }}) @endif
                                @if ($m['by']) โดย {{ $m['by'] }} @endif
                                @if ($canEditReport)
                                    <form method="POST" action="{{ route('reports.monthly.satisfaction-manual.destroy', $m['id']) }}" class="no-print" style="display:inline;" onsubmit="return confirm('ลบค่าที่กรอกเองนี้?');">
                                        @csrf @method('DELETE')
                                        <input type="hidden" name="return_month" value="{{ $monthValue }}">
                                        <button type="submit" class="btn btn-secondary btn-sm" style="margin:0 8px 0 4px;">ลบ</button>
                                    </form>
                                @endif
                                @if (! $loop->last) ; @endif
                            @endforeach
                        </p>
                    @endif

                    @if ($canEditReport)
                        <details class="no-print" style="margin-top:var(--space-3);">
                            <summary style="cursor:pointer;font-weight:700;">+ กรอกร้อยละเอง (กรณีเดือนนั้นไม่มีแบบประเมินในระบบ)</summary>
                            <form method="POST" action="{{ route('reports.monthly.satisfaction-manual.store') }}" class="field-grid" style="margin-top:var(--space-3);">
                                @csrf
                                <input type="hidden" name="return_month" value="{{ $monthValue }}">
                                <div class="field">
                                    <label for="sat-month">เดือน</label>
                                    <select id="sat-month" name="month" required>
                                        @foreach ($trend['months'] as $point)
                                            @continue($point['is_future'])
                                            <option value="{{ $point['month'] }}" @selected($point['month'] === $monthValue)>
                                                {{ \App\Http\Controllers\Reports\MonthlyReportController::monthShortLabel(\Illuminate\Support\Carbon::createFromFormat('Y-m-d', $point['month'].'-01')) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="sat-audience">แถว</label>
                                    <select id="sat-audience" name="audience" required>
                                        <option value="in_area">ผู้รับบริการในชุมชน (ในเขต)</option>
                                        <option value="palliative">ผู้ป่วยและญาติ (ประคับประคอง)</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="sat-percent">ร้อยละ (0–100)</label>
                                    <input id="sat-percent" type="number" name="percent" min="0" max="100" step="1" required>
                                </div>
                                <div class="field">
                                    <label for="sat-note">หมายเหตุ (ไม่บังคับ — เช่น ที่มาของตัวเลข)</label>
                                    <input id="sat-note" type="text" name="note" maxlength="255">
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary btn-sm">บันทึก</button>
                                    <span class="caption">ใช้เฉพาะเดือนที่ไม่มีแบบประเมินในระบบ — ถ้าเดือนนั้นมีแบบประเมินจริง ระบบจะใช้ค่าจริงแทน</span>
                                </div>
                            </form>
                        </details>
                    @endif
                </div>
            </div>

            {{-- ============ Section 5: photo gallery ============ --}}
            <div class="card report-photos">
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
            <div class="card report-long">
                <div class="card-head">
                    <div class="h2">6. ทะเบียนตอบกลับเยี่ยมบ้าน ประจำเดือน {{ $monthLabel }}</div>
                </div>
                <div class="card-body">
                    <style>
                        /* ทะเบียนตอบกลับ: 1 เคส = 1 กล่อง แบ่ง 3 คอลัมน์ในแถวเดียว (ข้อมูลผู้ป่วย | ประเด็นที่ต้องการติดตาม | ผลการเยี่ยม) */
                        .case-cols { display: grid; grid-template-columns: 1fr 1fr 1.3fr; gap: 10px; align-items: stretch; }
                        .case-col { border: 1px solid var(--color-neutral-200); border-radius: 8px; padding: 10px 12px; background: #fff; min-width: 0; }
                        .case-col .col-title { display: block; font-size: 11px; font-weight: 800; color: var(--color-primary-700); letter-spacing: .02em; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid var(--color-neutral-200); }
                        .case-col .kv { font-size: 12.5px; margin-bottom: 5px; overflow-wrap: anywhere; }
                        .case-col .kv .l { display: block; font-size: 10.5px; font-weight: 700; color: var(--color-neutral-500); }
                        .case-col .kv.allergy-yes .v { color: var(--color-risk); font-weight: 700; }
                        .case-col .txt { font-size: 12.5px; white-space: pre-line; overflow-wrap: anywhere; }
                        .case-print { display: none; }
                        .case-zone { font-weight: 700; }
                        .case-zone.in { background: var(--color-primary-100); color: var(--color-primary-800); }
                        .case-zone.out { background: var(--color-warning-tint); color: var(--color-warning); }
                        /* จอเล็กเท่านั้น — ตอนพิมพ์ต้องคง 3 คอลัมน์ (ความกว้างหน้ากระดาษอาจน้อยกว่า 900px) */
                        @media screen and (max-width: 900px) { .case-cols { grid-template-columns: 1fr; } }
                    </style>
                    @forelse ($report['case_log'] as $row)
                        @php
                            $caseLong = max(mb_strlen((string) $row['ward_concern']), mb_strlen((string) $row['visit_result']), mb_strlen((string) $row['diagnosis'])) > 900;
                        @endphp
                        <div class="case-card {{ $caseLong ? 'case-long' : '' }}">
                            @include('reports.partials.case-head', ['row' => $row, 'n' => $loop->iteration])
                            <div class="case-cols">
                                <div class="case-col">
                                    <span class="col-title">ข้อมูลผู้ป่วย</span>
                                    @include('reports.partials.case-patient', ['row' => $row])
                                </div>
                                <div class="case-col">
                                    <span class="col-title">ประเด็นที่ต้องการติดตาม</span>
                                    <div class="txt">{{ $row['ward_concern'] ?: '—' }}</div>
                                </div>
                                <div class="case-col">
                                    <span class="col-title">ผลการเยี่ยม</span>
                                    <div class="txt">{{ $row['visit_result'] ?: 'ยังไม่มีผลการเยี่ยม' }}</div>
                                </div>
                            </div>
                        </div>
                        @if ($caseLong)
                            {{-- เคสยาว: ตอนพิมพ์ใช้ตารางจริง (หัวตารางซ้ำเองทุกหน้า) — หัวบนสุด .cp-top เป็นหัวปกติและทึบ ทับหัวตารางของหน้าแรกไว้ ส่วนหน้าถัดไปจะเห็นหัวตารางที่มี "(ต่อ)" --}}
                            <div class="case-print">
                                <div class="cp-top">
                                    @include('reports.partials.case-head', ['row' => $row, 'n' => $loop->iteration])
                                    <div class="cp-titles"><span>ข้อมูลผู้ป่วย</span><span>ประเด็นที่ต้องการติดตาม</span><span>ผลการเยี่ยม</span></div>
                                </div>
                                <table class="case-print-table">
                                    <colgroup><col style="width:30%"><col style="width:30%"><col style="width:40%"></colgroup>
                                    <thead>
                                        <tr><th class="cpt-th" colspan="3">
                                            <div class="cpt-name"><span class="case-no">{{ $loop->iteration }}</span><span class="case-name">{{ $row['patient_name'] }}</span></div>
                                            <div class="cp-titles"><span>ข้อมูลผู้ป่วย (ต่อ)</span><span>ประเด็นที่ต้องการติดตาม (ต่อ)</span><span>ผลการเยี่ยม (ต่อ)</span></div>
                                        </th></tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><div class="case-col">@include('reports.partials.case-patient', ['row' => $row])</div></td>
                                            <td><div class="case-col"><div class="txt">{{ $row['ward_concern'] ?: '—' }}</div></div></td>
                                            <td><div class="case-col"><div class="txt">{{ $row['visit_result'] ?: 'ยังไม่มีผลการเยี่ยม' }}</div></div></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @empty
                        <div class="empty-note">ยังไม่มีเคสที่ได้รับการเยี่ยมในเดือนนี้</div>
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
            var hideUrl = @json($dmCopdHideUrl);
            var canEdit = @json($canEditReport);
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

            // ประมวลผลสำเร็จแล้วไม่พบภาวะแทรกซ้อน = ไม่ต้องแสดงในรายงาน (ที่ล้มเหลวยังแสดงไว้เพื่อให้รู้ว่าต้องลองใหม่)
            // ปุ่ม "ลบ" ท้ายแถวที่ AI สรุป: เอาเคสนี้ออกจากรายงานเดือนนี้ (ไม่ลบข้อมูลเคสจริง กู้คืนได้จากรายการใต้ตาราง)
            function addDeleteButton(tr, c, counted) {
                if (!canEdit) return;
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-secondary btn-sm no-print';
                btn.style.marginLeft = '8px';
                btn.textContent = 'ลบ';
                btn.addEventListener('click', function () {
                    if (!confirm('ลบรายการของ ' + c.patient_name + ' ออกจากรายงานเดือนนี้? (กู้คืนได้ภายหลัง)')) return;
                    btn.disabled = true;
                    fetch(hideUrl.replace('__ID__', c.referral_id), {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        credentials: 'same-origin'
                    })
                        .then(function (r) { if (!r.ok) throw new Error(r.status); })
                        .then(function () {
                            tr.remove();
                            if (counted) { found--; }
                            total--; done--;
                            updateProgress();
                            finishIfEmpty();
                        })
                        .catch(function () { btn.disabled = false; alert('ลบไม่สำเร็จ กรุณาลองใหม่'); });
                });
                tr.lastChild.appendChild(btn);
            }

            function isHidden(result) {
                return result.processed !== false && result.has_complication === false;
            }

            var progress = document.getElementById('dmCopdProgress');
            var total = 0, done = 0, found = 0;
            function updateProgress() {
                if (!progress) return;
                progress.textContent = done < total
                    ? 'AI กำลังตรวจบันทึกผลเยี่ยม ' + done + '/' + total + ' เคส (เคสแรกอาจใช้เวลา 1–2 นาทีต่อเคส — ผลที่ตรวจแล้วจะถูกจดจำไว้ ไม่ต้องรอหน้านี้) · พบภาวะแทรกซ้อน ' + found + ' เคส'
                    : 'ตรวจครบ ' + total + ' เคส · พบภาวะแทรกซ้อน ' + found + ' เคส';
            }
            function finishIfEmpty() {
                if (!body.children.length) { message('ไม่พบภาวะแทรกซ้อนจากบันทึกผลการเยี่ยมของเคส DM/COPD ในเดือนนี้'); }
            }

            fetch(casesUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
                .then(function (data) {
                    if (!data.cases.length) { message('ไม่พบเคสที่เข้าเงื่อนไขในกลุ่มผู้ป่วย DM/COPD ในเดือนนี้'); return; }

                    body.innerHTML = '';
                    total = data.cases.length;
                    var pending = [];
                    data.cases.forEach(function (c) {
                        if (c.result && isHidden(c.result)) { done++; return; }
                        var tr = document.createElement('tr');
                        tr.innerHTML = '<td>' + esc(c.patient_name) + '</td>'
                            + '<td><span class="chip ' + (c.disease_tag === 'DM/COPD' ? 'chip-risk' : 'chip-warning') + '">' + esc(c.disease_tag) + '</span></td>'
                            + '<td></td>';
                        var cell = tr.lastChild;
                        if (c.result) {
                            done++; found++;
                            fillSummary(cell, c.result);
                            addDeleteButton(tr, c, true);
                        } else {
                            tr.className = 'no-print';
                            cell.innerHTML = '<span class="caption">กำลังให้ AI ตรวจ…</span>';
                            pending.push({ c: c, cell: cell, tr: tr });
                        }
                        body.appendChild(tr);
                    });
                    updateProgress();
                    finishIfEmpty();

                    // ทำทีละเคสตามลำดับ ไม่ยิงพร้อมกัน (เครื่อง AI ที่แผนกประมวลผลได้ทีละคำขอ)
                    return pending.reduce(function (chain, item) {
                        return chain.then(function () {
                            return fetch(analyzeUrl.replace('__ID__', item.c.referral_id), {
                                method: 'POST',
                                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                                credentials: 'same-origin'
                            })
                                .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
                                .catch(function () {
                                    return { processed: false, summary: 'ไม่สามารถประมวลผลได้ — โหลดหน้านี้ใหม่เพื่อลองอีกครั้ง' };
                                })
                                .then(function (result) {
                                    done++;
                                    if (isHidden(result)) {
                                        item.tr.remove();
                                    } else {
                                        if (result.processed !== false) found++;
                                        item.tr.className = '';
                                        fillSummary(item.cell, result);
                                        addDeleteButton(item.tr, item.c, result.processed !== false);
                                    }
                                    updateProgress();
                                    finishIfEmpty();
                                });
                        });
                    }, Promise.resolve());
                })
                .catch(function () { message('โหลดรายชื่อเคสไม่สำเร็จ — กรุณาโหลดหน้านี้ใหม่'); });
        })();
    </script>
</body>
</html>
