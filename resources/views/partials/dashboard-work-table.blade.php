{{--
    ตารางงานที่ต้องทำบนหน้าภาพรวม (ลงพื้นที่เยี่ยมบ้าน / โทรติดตาม) — 1 เคสต่อ 1 แถว (นัดถัดไปของเคสนั้น)
    ต้องส่ง: $plans (FollowUpPlan พร้อม referral.patient), $empty (ข้อความเมื่อว่าง), $groupBySubDistrict (bool)
    ป้ายความยินยอม/แพ้ยา เป็นการเตือนเท่านั้น ไม่ได้บล็อกการเริ่มติดตาม
--}}
@php
    $groupBySubDistrict = $groupBySubDistrict ?? false;
    $groups = $groupBySubDistrict
        ? $plans->groupBy(fn ($p) => $p->referral->patient->sub_district ?: 'ไม่ระบุตำบล')
        : collect(['' => $plans]);
@endphp

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>ผู้ป่วย</th>
                <th>พื้นที่ / ติดต่อ</th>
                <th>ประเด็นที่ต้องดู</th>
                <th>กำหนด</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($groups as $groupName => $groupPlans)
                @if ($groupBySubDistrict)
                    <tr>
                        <td colspan="5" style="background:var(--color-primary-50);font-weight:700;color:var(--color-primary-800);padding:var(--space-2) var(--space-4);">
                            {{ $groupName === 'ไม่ระบุตำบล' ? $groupName : 'ต.'.$groupName }} <span class="caption">· {{ $groupPlans->count() }} ราย</span>
                        </td>
                    </tr>
                @endif
                @foreach ($groupPlans as $plan)
                    @php
                        $referral = $plan->referral;
                        $patient = $referral->patient;
                        $daysLeft = (int) round(today()->diffInDays($plan->due_date, false));
                        $concerns = collect($referral->confirmed_summary['risk_signals'] ?? [])->filter()->values();
                        $consent = $referral->visit_consent;
                        $equipment = collect($referral->equipment ?? [])->filter();
                    @endphp
                    <tr @class(['row-overdue' => $daysLeft < 0])>
                        <td>
                            <a href="{{ route('referrals.show', $referral) }}" class="patient-name" style="text-decoration:none;color:inherit;">{{ $patient->name }}</a>
                            <div class="patient-hn">HN {{ $patient->hn }}</div>
                            <div style="margin-top:4px;display:flex;gap:4px;flex-wrap:wrap;">
                                @if ($referral->severityLabel())
                                    <span class="chip {{ $referral->severityChipClass() }}">{{ \Illuminate\Support\Str::before($referral->severityLabel(), ' (') }}</span>
                                @endif
                                <span class="chip chip-method">{{ $plan->plan_number === 1 ? 'เยี่ยมครั้งแรก' : 'ครั้งที่ '.$plan->plan_number }}</span>
                            </div>
                            @if ($referral->caseType)
                                <div class="caption" style="margin-top:2px;">{{ $referral->caseType->name }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($patient->sub_district || $patient->district)
                                <strong>{{ $patient->sub_district ? 'ต.'.$patient->sub_district : '' }}{{ $patient->district ? ' อ.'.$patient->district : '' }}</strong>
                            @endif
                            @if ($patient->address)
                                <div class="caption">{{ \Illuminate\Support\Str::limit($patient->address, 60) }}</div>
                            @endif
                            <div style="margin-top:4px;font-size:13px;">
                                @if ($patient->phone)
                                    <div>ผู้ป่วย <a href="tel:{{ preg_replace('/[^0-9+]/', '', $patient->phone) }}" style="color:var(--color-primary-700);font-weight:600;">{{ $patient->phone }}</a></div>
                                @endif
                                @if ($referral->caregiver_phone)
                                    <div>{{ $referral->caregiver_name ?: 'ผู้ดูแล' }} <a href="tel:{{ preg_replace('/[^0-9+]/', '', $referral->caregiver_phone) }}" style="color:var(--color-primary-700);font-weight:600;">{{ $referral->caregiver_phone }}</a></div>
                                @endif
                                @if (! $patient->phone && ! $referral->caregiver_phone)
                                    <span class="caption">ไม่มีเบอร์โทร</span>
                                @endif
                            </div>
                        </td>
                        <td style="max-width:300px;">
                            @if ($concerns->isNotEmpty())
                                <div style="font-size:13px;">{{ \Illuminate\Support\Str::limit($concerns->first(), 90) }}@if ($concerns->count() > 1) <span class="caption">(+{{ $concerns->count() - 1 }} ประเด็น)</span>@endif</div>
                            @else
                                <div class="caption">ไม่มีประเด็นที่ฝากไว้</div>
                            @endif
                            <div style="margin-top:4px;display:flex;gap:4px;flex-wrap:wrap;">
                                @if ($referral->drug_allergy_status === \App\Models\Referral::DRUG_ALLERGY_YES)
                                    <span class="chip chip-risk">{{ \Illuminate\Support\Str::limit($referral->drugAllergyText(), 40) }}</span>
                                @endif
                                @if (in_array($consent, [\App\Models\Referral::VISIT_CONSENT_PHONE_ONLY, \App\Models\Referral::VISIT_CONSENT_DECLINED], true))
                                    <span class="chip {{ $referral->visitConsentChipClass() }}">{{ $referral->visitConsentLabel() }}</span>
                                @endif
                                @if ($equipment->isNotEmpty())
                                    <span class="chip chip-neutral">{{ $equipment->implode(', ') }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="due-date">
                            {{ \App\Support\ThaiDate::date($plan->due_date) }}
                            <div style="margin-top:4px;">
                                @if ($daysLeft < 0)
                                    <span class="chip chip-overdue">เกินกำหนด {{ abs($daysLeft) }} วัน</span>
                                @elseif ($daysLeft === 0)
                                    <span class="chip chip-today">ครบกำหนดวันนี้</span>
                                @else
                                    <span class="chip chip-neutral">อีก {{ $daysLeft }} วัน</span>
                                @endif
                            </div>
                        </td>
                        <td><a href="{{ route('follow-up-plans.record.create', $plan) }}" class="btn btn-primary btn-sm">เริ่มติดตาม</a></td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;color:var(--color-neutral-500);padding:var(--space-8) 0;">{{ $empty }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
