<x-app-layout>
    <x-slot name="header">วิเคราะห์แผนการพยาบาล</x-slot>

    @php
        $tabLink = fn (string $t, ?string $f = null) => route('care-plan.pending', array_filter(['tab' => $t === 'before' ? null : $t, 'filter' => $f]));
    @endphp

    <div class="page-head">
        <h1 class="h1">วิเคราะห์แผนการพยาบาล</h1>
        <p class="sub">เคสที่ยังรอพยาบาลประเมิน แยกเป็น วิเคราะห์ก่อนเยี่ยม (ตรวจแผนจากใบส่งต่อ) และ วิเคราะห์หลังเยี่ยม (ให้ AI ประเมินความเสี่ยงจากผลเยี่ยม แล้วพยาบาลยืนยันการตัดสินใจ)</p>
    </div>

    <div class="btn-row">
        <a href="{{ $tabLink('before') }}" class="btn {{ $tab === 'before' ? 'btn-primary' : 'btn-secondary' }}">วิเคราะห์ก่อนเยี่ยม ({{ $counts['before'] }})</a>
        <a href="{{ $tabLink('after') }}" class="btn {{ $tab === 'after' ? 'btn-primary' : 'btn-secondary' }}">วิเคราะห์หลังเยี่ยม ({{ $counts['after'] }})</a>
    </div>

    @if ($tab === 'before')
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ผู้ป่วย</th>
                            <th>แหล่งที่มา</th>
                            <th>ประเภทเคส</th>
                            <th>เขต</th>
                            <th>วันที่ส่งต่อข้อมูล</th>
                            <th>สถานะ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($referrals as $referral)
                            <tr>
                                <td>
                                    <div class="patient-name">{{ $referral->patient->name }}</div>
                                    <div class="patient-hn">HN {{ $referral->patient->hn }}</div>
                                </td>
                                <td>
                                    <span class="chip chip-method">{{ $referral->source_detail ?: match($referral->source_type) {
                                        'ward' => 'หอผู้ป่วย', 'opd' => 'OPD',
                                        'internal_dept' => 'หน่วยงานภายใน รพ.', 'external_hospital' => 'โรงพยาบาลอื่น',
                                        default => $referral->source_type,
                                    } }}</span>
                                </td>
                                <td>
                                    @if ($referral->caseType)
                                        <span class="chip chip-casetype">{{ $referral->caseType->name }}</span>
                                    @else
                                        <span class="caption">— ยังไม่ระบุ —</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="chip {{ $referral->zone === 'in_area' ? 'chip-inzone' : 'chip-outzone' }}">
                                        {{ $referral->zone === 'in_area' ? 'ในเขต' : 'นอกเขต' }}
                                    </span>
                                </td>
                                <td class="due-date">{{ \App\Support\ThaiDate::date($referral->created_at) }}</td>
                                <td><span class="chip chip-warning">รอตรวจสอบแผน</span></td>
                                <td>
                                    <a href="{{ route('referrals.care-plan', $referral) }}" class="btn btn-primary btn-sm">ตรวจสอบแผน</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align:center;padding:var(--space-8);color:var(--color-neutral-500);">ไม่มีเคสรอตรวจสอบแผนก่อนเยี่ยมในขณะนี้</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p class="caption">ทั้งหมด {{ $referrals->total() }} เคสรอตรวจสอบแผนก่อนเยี่ยม</p>
        <div>{{ $referrals->links() }}</div>
    @else
        <div class="btn-row" style="margin-top:var(--space-2);">
            <a href="{{ $tabLink('after') }}" class="btn btn-sm {{ $filter === null ? 'btn-primary' : 'btn-secondary' }}">ทั้งหมด ({{ $counts['after'] }})</a>
            <a href="{{ $tabLink('after', 'not_analyzed') }}" class="btn btn-sm {{ $filter === 'not_analyzed' ? 'btn-primary' : 'btn-secondary' }}">ยังไม่ได้ให้ AI วิเคราะห์ ({{ $counts['not_analyzed'] }})</a>
            <a href="{{ $tabLink('after', 'analyzed') }}" class="btn btn-sm {{ $filter === 'analyzed' ? 'btn-primary' : 'btn-secondary' }}">AI วิเคราะห์แล้ว รอยืนยัน ({{ $counts['analyzed'] }})</a>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ผู้ป่วย</th>
                            <th>ครั้งที่ / วิธี</th>
                            <th>วันที่เยี่ยม</th>
                            <th>รอมาแล้ว</th>
                            <th>เขต</th>
                            <th>สถานะการวิเคราะห์</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            @php
                                $plan = $record->plan;
                                $referral = $plan->referral;
                                $analysis = $record->ai_analysis;
                                $waitDays = (int) floor($record->visited_at->diffInDays(now()));
                            @endphp
                            <tr>
                                <td>
                                    <div class="patient-name">{{ $referral->patient->name }}</div>
                                    <div class="patient-hn">HN {{ $referral->patient->hn }}
                                        @if ($referral->caseType) <span class="chip chip-casetype" style="margin-left:4px;">{{ $referral->caseType->name }}</span> @endif
                                    </div>
                                </td>
                                <td>
                                    ครั้งที่ {{ $plan->plan_number }}
                                    <span class="chip chip-method" style="margin-left:4px;">{{ $record->method === 'phone_call' ? 'โทรติดตาม' : 'เยี่ยมบ้าน' }}</span>
                                </td>
                                <td class="due-date">{{ \App\Support\ThaiDate::dateTime($record->visited_at) }}</td>
                                <td>{{ $waitDays <= 0 ? 'วันนี้' : $waitDays.' วัน' }}</td>
                                <td>
                                    <span class="chip {{ $referral->zone === 'in_area' ? 'chip-inzone' : 'chip-outzone' }}">{{ $referral->zone === 'in_area' ? 'ในเขต' : 'นอกเขต' }}</span>
                                </td>
                                <td>
                                    @if ($analysis === null)
                                        <span class="chip chip-warning">ยังไม่ได้ให้ AI วิเคราะห์</span>
                                    @elseif ($analysis['parse_error'] ?? false)
                                        <span class="chip chip-warning">AI ตอบไม่สำเร็จ — ลองใหม่</span>
                                    @else
                                        <span class="chip chip-done">AI วิเคราะห์แล้ว รอยืนยัน</span>
                                        @if ($analysis['risk_detected'] ?? false)
                                            <span class="chip chip-risk" style="margin-left:4px;">AI พบสัญญาณเสี่ยง</span>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('follow-up-plans.review', $plan) }}" class="btn btn-primary btn-sm">
                                        {{ $analysis === null || ($analysis['parse_error'] ?? false) ? 'วิเคราะห์ความเสี่ยง' : 'ตรวจสอบและยืนยัน' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align:center;padding:var(--space-8);color:var(--color-neutral-500);">ไม่มีผลเยี่ยมที่รอประเมินในขณะนี้</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p class="caption">ทั้งหมด {{ $records->total() }} รายการ — เรียงตามเยี่ยมมานานที่สุดก่อน</p>
        <div>{{ $records->links() }}</div>
    @endif
</x-app-layout>
