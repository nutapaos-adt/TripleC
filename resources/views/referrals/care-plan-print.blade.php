<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แผนการดูแล — {{ $referral->patient->name }}</title>
    @vite(['resources/css/app.css'])
    @php
        // ส่วนของแบบฟอร์มบันทึกการเยี่ยมปรับตามเคส (เหมือนหน้าบันทึกผลติดตามในระบบ)
        $isPalliative = $referral->caseType?->slug === 'palliative-care';
        $isRed = $referral->severity_group === \App\Models\Referral::SEVERITY_RED;
        $isTkaUka = $referral->isTkaUkaCase();
        $summary = $referral->confirmed_summary ?? [];
        $plans = $referral->followUpPlans->sortBy('plan_number')->values();
        $box = fn (string $label, bool $on = false) => ($on ? '☑' : '☐').' '.$label;
        $sourceText = $referral->source_detail ?: match ($referral->source_type) {
            'ward' => 'หอผู้ป่วย', 'opd' => 'OPD',
            'internal_dept' => 'หน่วยงานภายใน รพ.', 'external_hospital' => 'โรงพยาบาลอื่น',
            default => $referral->source_type,
        };
    @endphp
    <style>
        @page { size: A4; margin: 10mm 12mm; }
        * { box-sizing: border-box; }
        body { background: #fff; font-size: 12px; line-height: 1.35; color: #111; }
        .sheet { max-width: 794px; margin: 0 auto; padding: 12px 16px; }
        .page { page-break-after: always; break-after: page; }
        .page:last-child { page-break-after: auto; break-after: auto; }
        .head { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; border-bottom: 2px solid var(--color-primary-700, #2c5166); padding-bottom: 6px; margin-bottom: 8px; }
        .head .org { font-size: 12px; color: var(--color-primary-700, #2c5166); font-weight: 700; }
        .head h1 { margin: 0; font-size: 19px; font-weight: 700; }
        .head .meta { font-size: 11px; color: #555; text-align: right; }
        .chips { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 8px; }
        .info { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #999; border-bottom: 0; }
        .info .it { display: flex; gap: 6px; padding: 3px 7px; border-bottom: 1px solid #999; }
        .info .full { grid-column: 1 / -1; }
        .info .lb { flex: 0 0 118px; font-weight: 700; color: #333; }
        .info .vl { flex: 1; min-width: 0; overflow-wrap: anywhere; }
        .plan { margin-top: 10px; border: 1.5px solid var(--color-primary-700, #2c5166); border-radius: 6px; padding: 8px 10px; }
        .plan h2 { margin: 0 0 4px; font-size: 13px; }
        .plan ul { margin: 2px 0 6px; padding-left: 18px; }
        .plan li { margin-bottom: 1px; }
        .plan .sub { font-weight: 700; margin-top: 4px; }
        .sig { margin-top: 14px; display: flex; gap: 16px; }
        .sig > div { flex: 1; border-top: 1px solid #777; padding-top: 3px; font-size: 11px; }

        /* ===== แบบฟอร์มบันทึกการเยี่ยม ===== */
        .frm-head { display: flex; justify-content: space-between; gap: 12px; align-items: stretch; margin-bottom: 6px; }
        .frm-head .t { flex: 1; }
        .frm-head .t .org { font-size: 12px; font-weight: 700; color: var(--color-primary-700, #2c5166); }
        .frm-head .t h2 { margin: 0; font-size: 18px; line-height: 1.2; }
        .frm-head .pt { flex: 0 0 210px; border: 1px dashed #888; padding: 6px 8px; font-size: 11px; display: flex; flex-direction: column; justify-content: center; }
        .frm-head .pt b { font-size: 13px; }
        .f { border: 1px solid #777; border-bottom: 0; }
        .f .sec { background: #e8eef2; font-weight: 700; padding: 3px 7px; border-bottom: 1px solid #777; font-size: 12.5px; }
        .f .sec small { font-weight: 400; color: #555; margin-left: 6px; }
        .f .r { display: grid; grid-template-columns: repeat(12, 1fr); border-bottom: 1px solid #777; }
        .f .c { padding: 3px 7px; border-left: 1px solid #777; min-height: 38px; }
        .f .c:first-child { border-left: 0; }
        .f .c .l { font-weight: 700; display: block; }
        .f .c .o { display: block; margin-top: 1px; }
        .f .s2 { grid-column: span 2; } .f .s3 { grid-column: span 3; } .f .s4 { grid-column: span 4; }
        .f .s5 { grid-column: span 5; } .f .s6 { grid-column: span 6; } .f .s7 { grid-column: span 7; }
        .f .s8 { grid-column: span 8; } .f .s12 { grid-column: span 12; }
        .f .tall { min-height: 56px; }
        .f .rule { grid-column: span 12; height: 24px; min-height: 0; }
        .u { color: #555; font-size: 11px; }
        .foot { margin-top: 6px; font-size: 10px; color: #777; text-align: center; }

        @media print {
            .no-print { display: none !important; }
            .sheet { padding: 0; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="no-print btn-row" style="margin-bottom:var(--space-4);">
            <button type="button" class="btn btn-primary" onclick="window.print()">🖨 พิมพ์ (แผนการดูแล + แบบบันทึกการเยี่ยม)</button>
            <a href="{{ route('referrals.show', $referral) }}" class="btn btn-secondary">กลับไปหน้าใบส่งต่อ</a>
        </div>

        {{-- ================= หน้า 1: สรุปแผนการดูแล ================= --}}
        <section class="page">
            <div class="head">
                <div>
                    <div class="org">โรงพยาบาลค่ายจิรประวัติ</div>
                    <h1>รายงานสรุปแผนการดูแลผู้ป่วย</h1>
                </div>
                <div class="meta">พิมพ์เมื่อ {{ now()->format('d/m/Y H:i') }}</div>
            </div>

            <div class="chips">
                @if ($referral->severityLabel())
                    <span class="chip {{ $referral->severityChipClass() }}">{{ $referral->severityLabel() }}</span>
                @endif
                <span class="chip {{ $referral->zone === 'in_area' ? 'chip-inzone' : 'chip-outzone' }}">{{ $referral->zone === 'in_area' ? 'ในเขต' : 'นอกเขต' }}</span>
            </div>

            <div class="info">
                <div class="it"><span class="lb">ชื่อ-สกุล</span><span class="vl"><b>{{ $referral->patient->name }}</b></span></div>
                <div class="it"><span class="lb">HN</span><span class="vl"><b>{{ $referral->patient->hn }}</b></span></div>
                <div class="it"><span class="lb">เลขบัตรประชาชน</span><span class="vl">{{ $referral->patient->national_id ?? '—' }}</span></div>
                <div class="it"><span class="lb">สิทธิการรักษา</span><span class="vl">{{ $referral->coverage_type ?? '—' }}</span></div>
                <div class="it"><span class="lb">สถานะผู้ป่วย</span><span class="vl">{{ $referral->patientStatusLabel() }}@if($referral->military_unit) ({{ $referral->military_unit }})@endif</span></div>
                <div class="it"><span class="lb">ผู้ดูแลหลัก</span><span class="vl">{{ $referral->caregiver_name ?? '—' }}@if($referral->caregiver_phone) — {{ $referral->caregiver_phone }}@endif</span></div>
                <div class="it full"><span class="lb">ที่อยู่</span><span class="vl">
                    {{ $referral->patient->address }}
                    @if ($referral->patient->sub_district) ต.{{ $referral->patient->sub_district }} @endif
                    @if ($referral->patient->district) อ.{{ $referral->patient->district }} @endif
                    @if ($referral->patient->province) จ.{{ $referral->patient->province }} @endif
                </span></div>
                <div class="it"><span class="lb">แหล่งที่มา</span><span class="vl">{{ $sourceText }}</span></div>
                <div class="it"><span class="lb">ประเภทเคส</span><span class="vl">{{ $referral->caseType?->name ?? '—' }}</span></div>
                <div class="it"><span class="lb">แพทย์เจ้าของไข้</span><span class="vl">{{ $referral->attending_physician ?? '—' }}</span></div>
                @if ($referral->ward && ! $referral->ward->has_admission)
                    <div class="it"><span class="lb">วันที่พบผู้ป่วย / นัด OPD</span><span class="vl">{{ $referral->encounter_date?->format('d/m/Y') ?? '—' }} / {{ $referral->opd_followup_date?->format('d/m/Y') ?? '—' }}</span></div>
                @else
                    <div class="it"><span class="lb">Admit / จำหน่าย / นัด OPD</span><span class="vl">{{ $referral->admit_date?->format('d/m/Y') ?? '—' }} / {{ $referral->discharge_date?->format('d/m/Y') ?? '—' }} / {{ $referral->opd_followup_date?->format('d/m/Y') ?? '—' }}</span></div>
                @endif
                <div class="it full"><span class="lb">การวินิจฉัย</span><span class="vl">{{ $referral->diagnosis ?? '—' }}</span></div>
                <div class="it"><span class="lb">โรคประจำตัว</span><span class="vl">{{ $referral->underlying_disease ?? '—' }}</span></div>
                <div class="it"><span class="lb">ประวัติการผ่าตัด</span><span class="vl">{{ $referral->surgery_history ?? '—' }}</span></div>
                <div class="it {{ $isPalliative ? '' : 'full' }}"><span class="lb">อุปกรณ์ของผู้ป่วย</span><span class="vl">{{ !empty($referral->equipment) ? implode(', ', $referral->equipment) : '—' }}</span></div>
                @if ($isPalliative)
                    <div class="it"><span class="lb">PPS Score เริ่มต้น</span><span class="vl">{{ $referral->initial_pps_score ?? '—' }}</span></div>
                @endif
                @if (!empty($referral->clinical_tracers))
                    <div class="it full"><span class="lb">Clinical tracer</span><span class="vl">{{ implode(', ', $referral->clinical_tracers) }}</span></div>
                @endif
            </div>

            <div class="plan">
                <h2>แผนการพยาบาล</h2>
                <div class="sub">ประเด็นที่ต้องติดตาม</div>
                @if (!empty($summary['risk_signals']))
                    <ul>@foreach ($summary['risk_signals'] as $signal)<li>{{ $signal }}</li>@endforeach</ul>
                @else
                    <div>—</div>
                @endif

                <div class="sub">กำหนดการติดตาม</div>
                @php
                    $firstPlan = $plans->first();
                    $secondPlan = $plans->get(1);
                    $intervalDays = $firstPlan && $secondPlan ? (int) round($firstPlan->due_date->diffInDays($secondPlan->due_date)) : null;
                    $firstVisitOffsetDays = $firstPlan && $referral->confirmed_at ? (int) round($referral->confirmed_at->diffInDays($firstPlan->due_date)) : null;
                @endphp
                <div>
                    @forelse ($plans as $plan)
                        ครั้งที่ {{ $plan->plan_number }} — {{ $plan->method === 'home_visit' ? 'เยี่ยมบ้าน' : 'โทรติดตาม' }} — กำหนด {{ $plan->due_date->format('d/m/Y') }}<br>
                    @empty
                        ยังไม่มีกำหนดการ
                    @endforelse
                </div>
                @if ($firstPlan)
                    <div class="u" style="margin-top:3px;">
                        {{ $firstPlan->method === 'home_visit' ? 'เยี่ยมบ้าน' : 'โทรติดตาม' }}
                        @if ($intervalDays) ทุก {{ $intervalDays }} วัน @endif
                        @if ($isPalliative && $referral->initial_pps_score) (ตาม PPS Score {{ $referral->initial_pps_score }}) @endif
                        @if ($firstVisitOffsetDays !== null) — เริ่มครั้งแรกภายใน {{ $firstVisitOffsetDays }} วันหลังยืนยันแผน @endif
                    </div>
                @endif
            </div>

            <div class="sig">
                <div>ยืนยันแผนโดย: {{ $referral->confirmer?->name ?? '—' }}@if($referral->confirmed_at) เมื่อ {{ $referral->confirmed_at->format('d/m/Y H:i') }}@endif</div>
            </div>
        </section>

        {{-- ================= หน้า 2–3: แบบบันทึกผลการเยี่ยมบ้าน/ติดตามทางโทรศัพท์ ================= --}}
        <section class="page">
            <div class="frm-head">
                <div class="t">
                    <div class="org">โรงพยาบาลค่ายจิรประวัติ</div>
                    <h2>แบบบันทึกผลการเยี่ยมบ้าน / ติดตามทางโทรศัพท์</h2>
                </div>
                <div class="pt">
                    <b>{{ $referral->patient->name }}</b>
                    <span>HN {{ $referral->patient->hn }}</span>
                    <span>{{ $referral->patient->dob ? 'เกิด '.$referral->patient->dob->format('d/m/Y').' ('.$referral->patient->dob->age.' ปี)' : '' }}</span>
                </div>
            </div>

            <div class="f">
                <div class="sec">1. ข้อมูลการติดตาม</div>
                <div class="r">
                    <div class="c s3"><span class="l">ติดตามครั้งที่</span>......... จาก {{ $plans->count() ?: '.........' }}</div>
                    <div class="c s3"><span class="l">วันที่เยี่ยม/โทร</span></div>
                    <div class="c s3"><span class="l">เวลา</span></div>
                    <div class="c s3"><span class="l">กำหนดนัดตามแผน</span></div>
                </div>
                <div class="r">
                    <div class="c s6"><span class="l">วิธีการติดตามครั้งนี้</span><span class="o">☐ ลงพื้นที่เยี่ยม &nbsp;&nbsp; ☐ โทรติดตาม</span></div>
                    <div class="c s6"><span class="l">ผู้เยี่ยม/ผู้ติดตาม</span></div>
                </div>
                <div class="r">
                    <div class="c s12"><span class="l">การจำแนกผู้ป่วย</span><span class="o">
                        {{ $box('บ้านสีเขียว', $referral->severity_group === 'green') }} &nbsp;&nbsp;
                        {{ $box('บ้านสีเหลือง', $referral->severity_group === 'yellow') }} &nbsp;&nbsp;
                        {{ $box('บ้านสีแดง', $referral->severity_group === 'red') }} &nbsp;&nbsp;
                        {{ $box('Palliative Care', $referral->severity_group === 'palliative') }}
                    </span></div>
                </div>

                <div class="sec">2. การประเมินทางกายภาพ <small>(เฉพาะกรณีลงพื้นที่เยี่ยม — โทรติดตามข้ามส่วนนี้)</small></div>
                <div class="r"><div class="c s12 tall"><span class="l">ลักษณะทั่วไป (General Appearance)</span></div></div>
                <div class="r">
                    <div class="c s3"><span class="l">BP</span><span class="u">mmHg</span></div>
                    <div class="c s2"><span class="l">PR</span><span class="u">ครั้ง/นาที</span></div>
                    <div class="c s2"><span class="l">RR</span><span class="u">ครั้ง/นาที</span></div>
                    <div class="c s2"><span class="l">Temp</span><span class="u">°C</span></div>
                    <div class="c s3"><span class="l">SpO2</span><span class="u">%</span></div>
                </div>
                <div class="r">
                    <div class="c s4"><span class="l">น้ำหนัก</span><span class="u">กก.</span></div>
                    <div class="c s4"><span class="l">ส่วนสูง</span><span class="u">ซม.</span></div>
                    <div class="c s4"><span class="l">BMI</span></div>
                </div>

                @if ($isPalliative || $isRed)
                    <div class="sec">3. การประเมินเฉพาะกลุ่ม</div>
                    @if ($isPalliative)
                        <div class="r"><div class="c s12"><span class="l">PPS Score (Palliative Care)</span>คะแนน ........... <span class="u">(0–100: 100 = ปกติดี, 0 = เสียชีวิต)</span></div></div>
                    @endif
                    @if ($isRed)
                        <div class="r"><div class="c s12" style="background:#f3f6f9;min-height:0;"><b>ADL (กลุ่ม 3 บ้านสีแดง)</b> <span class="u">&nbsp; ทำได้เอง = 2 &nbsp; ช่วยเหลือบางส่วน = 1 &nbsp; ทำเองไม่ได้ = 0</span></div></div>
                        <div class="r">
                            <div class="c s6"><span class="l">การรับประทานอาหาร</span><span class="o">☐ 2 &nbsp; ☐ 1 &nbsp; ☐ 0</span></div>
                            <div class="c s6"><span class="l">การเคลื่อนไหว/ย้ายตัว</span><span class="o">☐ 2 &nbsp; ☐ 1 &nbsp; ☐ 0</span></div>
                        </div>
                        <div class="r">
                            <div class="c s6"><span class="l">การขับถ่าย/ปัสสาวะ</span><span class="o">☐ 2 &nbsp; ☐ 1 &nbsp; ☐ 0</span></div>
                            <div class="c s6"><span class="l">การอาบน้ำ/แต่งตัว</span><span class="o">☐ 2 &nbsp; ☐ 1 &nbsp; ☐ 0</span></div>
                        </div>
                        <div class="r"><div class="c s12" style="min-height:0;"><b>คะแนน ADL รวม</b> ......... / 8</div></div>
                    @endif
                @endif

                @if ($isTkaUka)
                    <div class="sec">{{ ($isPalliative || $isRed) ? '4' : '3' }}. การประเมินหลังผ่าตัดเปลี่ยนข้อเข่า (TKA/UKA)</div>
                    <div class="r">
                        <div class="c s6"><span class="l">แผลผ่าตัด (ลงพื้นที่เท่านั้น)</span><span class="o">☐ แห้งดี &nbsp; ☐ บวม &nbsp; ☐ แดง</span></div>
                        <div class="c s6"><span class="l">ทำแผลโดย</span><span class="o">☐ สถานพยาบาล &nbsp; ☐ ทำแผลเอง &nbsp; ☐ ปิดแผลไว้ ..... วัน</span></div>
                    </div>
                    <div class="r">
                        <div class="c s3"><span class="l">Pain score</span><span class="u">....... /10</span></div>
                        <div class="c s3"><span class="l">ADL</span><span class="u">....... คะแนน</span></div>
                        <div class="c s6"><span class="l">การเดินด้วย Walker</span><span class="o">☐ By walk &nbsp; ☐ ไม่ใช้ Walker &nbsp; ☐ ไม่ยอมเดิน เหตุผล ..........</span></div>
                    </div>
                    <div class="r">
                        <div class="c s6"><span class="l">การงอข้อเข่า 0–90 องศา (ลงพื้นที่เท่านั้น)</span><span class="o">☐ &gt;90° &nbsp; ☐ =90° &nbsp; ☐ &lt;90°</span></div>
                        <div class="c s6"><span class="l">ประวัติหกล้มหลังผ่าตัด</span><span class="o">☐ ไม่เคย &nbsp; ☐ หกล้ม ..... ครั้ง</span></div>
                    </div>
                    <div class="r">
                        <div class="c s6"><span class="l">สภาพสิ่งแวดล้อม/บ้าน</span><span class="o">☐ ไม่เสี่ยงอุบัติเหตุ &nbsp; ☐ เสี่ยง ระบุ ..........</span></div>
                        <div class="c s6"><span class="l">การบริหารร่างกาย</span><span class="o">☐ สม่ำเสมอ &nbsp; ☐ ไม่สม่ำเสมอ &nbsp; ☐ ไม่บริหาร</span></div>
                    </div>
                @endif

                <div class="sec">{{ 3 + (($isPalliative || $isRed) ? 1 : 0) + ($isTkaUka ? 1 : 0) }}. บันทึกผลการติดตาม <small>(อาการ/ปัญหาที่พบ สิ่งที่ทำ)</small></div>
                <div class="r">
                    @for ($i = 0; $i < 7; $i++)<div class="c rule" style="border-left:0;border-bottom:1px solid #bbb;"></div>@endfor
                </div>

                <div class="sec">{{ 4 + (($isPalliative || $isRed) ? 1 : 0) + ($isTkaUka ? 1 : 0) }}. การประเมินความเสี่ยงและการตัดสินใจของพยาบาล</div>
                <div class="r">
                    <div class="c s5"><span class="l">สัญญาณเสี่ยง</span><span class="o">☐ ไม่พบ &nbsp; ☐ พบ</span></div>
                    <div class="c s7"><span class="l">การตัดสินใจ</span><span class="o">☐ ติดตามซ้ำ &nbsp; ☐ ส่งต่อ &nbsp; ☐ ปิดเคส</span></div>
                </div>
                <div class="r">
                    <div class="c s8"><span class="l">หมายเหตุการตัดสินใจ</span></div>
                    <div class="c s4"><span class="l">ภาพประกอบการเยี่ยม</span><span class="o">☐ ถ่ายภาพแล้ว</span></div>
                </div>
                <div class="r">
                    <div class="c s6 tall"><span class="l">ลงชื่อพยาบาลผู้เยี่ยม/ผู้บันทึก</span></div>
                    <div class="c s3 tall"><span class="l">วันที่</span></div>
                    <div class="c s3 tall"><span class="l">เวลา</span></div>
                </div>
            </div>
            <div class="foot">แบบบันทึกผลการเยี่ยมบ้าน/ติดตามทางโทรศัพท์ โรงพยาบาลค่ายจิรประวัติ — {{ $referral->patient->name }} (HN {{ $referral->patient->hn }})</div>
        </section>
    </div>
</body>
</html>
