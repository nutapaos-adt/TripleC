<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ใบนัดเยี่ยมบ้านวันนี้</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body { background: #fff; font-size: 12.5px; line-height: 1.4; color: #111; }
        .sheet { max-width: 794px; margin: 0 auto; padding: 12px 16px; }
        .head { border-bottom: 2px solid var(--color-primary-700, #2c5166); padding-bottom: 6px; margin-bottom: 10px; }
        .head .org { font-size: 12px; color: var(--color-primary-700, #2c5166); font-weight: 700; }
        .head h1 { margin: 2px 0; font-size: 18px; }
        table.sheet-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.sheet-table th, table.sheet-table td { border: 1px solid #222; padding: 4px 6px; vertical-align: top; font-size: 12px; overflow-wrap: anywhere; text-align: left; }
        table.sheet-table th { background: #f1eee0; font-weight: 700; }
        table.sheet-table td.blank { height: 56px; }
        table.sheet-table tr { break-inside: avoid; page-break-inside: avoid; }
        .group-row td { background: #e1ebef; font-weight: 700; }
        .foot { margin-top: 10px; padding-top: 6px; border-top: 1px solid #222; font-size: 11px; }
        @media print { .no-print { display: none !important; } .sheet { padding: 0; max-width: none; } }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="no-print btn-row" style="margin-bottom:var(--space-4);">
            <button type="button" class="btn btn-primary" onclick="window.print()">🖨 พิมพ์ใบนัดวันนี้</button>
            <a href="{{ route('dashboard') }}" class="btn btn-secondary">กลับไปหน้าภาพรวม</a>
        </div>

        <div class="head">
            <div class="org">โรงพยาบาลค่ายจิรประวัติ — ทีมเยี่ยมบ้าน</div>
            <h1>ใบนัดเยี่ยมบ้านวันที่ {{ \App\Support\ThaiDate::date($today) }}</h1>
            <div>เฉพาะรายที่เกินกำหนดและครบกำหนดวันนี้ ({{ $plans->count() }} ราย) · เรียงตามตำบลแล้วกำหนด · พิมพ์เมื่อ {{ \App\Support\ThaiDate::dateTime(now()) }}</div>
        </div>

        @if ($plans->isEmpty())
            <p>ไม่มีรายที่ต้องเยี่ยมบ้านเกินกำหนดหรือครบกำหนดวันนี้</p>
        @else
            <table class="sheet-table">
                <colgroup><col style="width:6%"><col style="width:20%"><col style="width:22%"><col style="width:16%"><col style="width:20%"><col style="width:16%"></colgroup>
                <thead>
                    <tr><th>ลำดับ</th><th>ชื่อ-สกุล / HN</th><th>ที่อยู่ / ตำบล</th><th>เบอร์โทร</th><th>ประเด็นที่ฝาก</th><th>จดผลสั้นๆ</th></tr>
                </thead>
                <tbody>
                    @php $lastSub = null; $n = 0; @endphp
                    @foreach ($plans as $plan)
                        @php
                            $referral = $plan->referral;
                            $patient = $referral->patient;
                            $sub = $patient->sub_district ?: 'ไม่ระบุตำบล';
                            $daysLeft = (int) round(today()->diffInDays($plan->due_date, false));
                            $concerns = collect($referral->confirmed_summary['risk_signals'] ?? [])->filter()->values();
                        @endphp
                        @if ($sub !== $lastSub)
                            <tr class="group-row"><td colspan="6">{{ $sub === 'ไม่ระบุตำบล' ? $sub : 'ต.'.$sub }}</td></tr>
                            @php $lastSub = $sub; @endphp
                        @endif
                        <tr>
                            <td>{{ ++$n }}</td>
                            <td>
                                <strong>{{ $patient->name }}</strong><br>HN {{ $patient->hn }}<br>
                                {{ $plan->plan_number === 1 ? 'เยี่ยมครั้งแรก' : 'ครั้งที่ '.$plan->plan_number }} · {{ $daysLeft < 0 ? 'เกินกำหนด '.abs($daysLeft).' วัน' : 'ครบวันนี้' }}
                            </td>
                            <td>{{ $patient->address }}@if ($patient->sub_district) ต.{{ $patient->sub_district }}@endif @if ($patient->district) อ.{{ $patient->district }}@endif</td>
                            <td>
                                @if ($patient->phone) ผู้ป่วย {{ $patient->phone }}<br>@endif
                                @if ($referral->caregiver_phone) {{ $referral->caregiver_name ?: 'ผู้ดูแล' }} {{ $referral->caregiver_phone }}@endif
                                @if (! $patient->phone && ! $referral->caregiver_phone) ไม่มีเบอร์โทร @endif
                            </td>
                            <td>
                                @foreach ($concerns->take(2) as $i => $c){{ $i + 1 }}. {{ $c }}<br>@endforeach
                                @if ($referral->drug_allergy_status === \App\Models\Referral::DRUG_ALLERGY_YES)<strong>{{ $referral->drugAllergyText() }}</strong>@endif
                            </td>
                            <td class="blank"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="foot">เอกสารนี้มีข้อมูลผู้ป่วย เก็บรักษาและทำลายตามระเบียบ</div>
    </div>
</body>
</html>
