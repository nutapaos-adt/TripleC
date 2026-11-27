{{--
    รายการงานที่ต้องทำบนหน้าภาพรวม (ลงพื้นที่เยี่ยมบ้าน / โทรติดตาม) — 1 เคสต่อ 1 แถว (นัดถัดไปของเคสนั้น) แถวย่อ 2 บรรทัด กดเพื่อขยายดูรายละเอียด
    ต้องส่ง: $plans (FollowUpPlan พร้อม referral.patient), $empty (ข้อความเมื่อว่าง), $groupBySubDistrict (bool),
            $kind ('visit' = ลงพื้นที่ / 'call' = โทรติดตาม — งานโทรให้ปุ่มโทรเป็นหลัก), $hidden (จำนวนที่ซ่อนอยู่ เพราะนัดเกิน 7 วัน), $allLink (ลิงก์ "ดูทั้งหมด")
    ป้ายความยินยอม/แพ้ยา เป็นการเตือนเท่านั้น ไม่ได้บล็อกการเริ่มติดตาม
--}}
@php
    $groupBySubDistrict = $groupBySubDistrict ?? false;
    $kind = $kind ?? 'visit';
    $hidden = $hidden ?? 0;
    $groups = ($groupBySubDistrict && $kind === 'visit')
        ? $plans->groupBy(fn ($p) => $p->referral->patient->sub_district ?: 'ไม่ระบุตำบล')
        : collect(['' => $plans]);
    $telHref = fn (?string $phone) => 'tel:'.preg_replace('/[^0-9+]/', '', (string) $phone);
@endphp

<ul class="wb-list">
    @if ($plans->isEmpty())
        <li class="wb-empty">{{ $empty }}</li>
    @endif

    @foreach ($groups as $groupName => $groupPlans)
        @if ($groupBySubDistrict && $kind === 'visit')
            <li class="wb-group">{{ $groupName === 'ไม่ระบุตำบล' ? $groupName : 'ต.'.$groupName }} <span class="caption">· {{ $groupPlans->count() }} ราย</span></li>
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
            <li>
                <details class="wb-row {{ $daysLeft < 0 ? 'late' : '' }}">
                    <summary class="wb-sum">
                        <div>
                            <div class="wb-l1">
                                <span class="patient-name">{{ $patient->name }}</span>
                                <span class="patient-hn">HN {{ $patient->hn }}</span>
                                @if ($referral->severityLabel())
                                    <span class="chip {{ $referral->severityChipClass() }}">{{ \Illuminate\Support\Str::before($referral->severityLabel(), ' (') }}</span>
                                @endif
                                <span class="wb-no">{{ $plan->plan_number === 1 ? 'ครั้งแรก' : 'ครั้งที่ '.$plan->plan_number }}</span>
                                @if ($kind === 'call')
                                    @if ($daysLeft < 0)
                                        <span class="chip chip-overdue">เกินกำหนด {{ abs($daysLeft) }} วัน</span>
                                    @elseif ($daysLeft === 0)
                                        <span class="chip chip-today">ครบวันนี้</span>
                                    @else
                                        <span class="chip chip-neutral">อีก {{ $daysLeft }} วัน</span>
                                    @endif
                                @endif
                            </div>
                            @if ($kind === 'visit')
                                <div class="wb-l2">
                                    @if ($patient->sub_district)
                                        <span>ต.{{ $patient->sub_district }}</span>
                                    @else
                                        <span class="chip chip-neutral">ไม่ระบุตำบล</span>
                                    @endif
                                    @if ($patient->phone)
                                        <a href="{{ $telHref($patient->phone) }}" class="wb-tel">{{ $patient->phone }}</a>
                                    @elseif ($referral->caregiver_phone)
                                        <a href="{{ $telHref($referral->caregiver_phone) }}" class="wb-tel">{{ $referral->caregiver_name ?: 'ผู้ดูแล' }} {{ $referral->caregiver_phone }}</a>
                                    @else
                                        <span class="caption">ไม่มีเบอร์โทร</span>
                                    @endif
                                    @if ($referral->drug_allergy_status === \App\Models\Referral::DRUG_ALLERGY_YES)
                                        <span class="chip chip-risk">{{ \Illuminate\Support\Str::limit($referral->drugAllergyText(), 40) }}</span>
                                    @endif
                                    @if (in_array($consent, [\App\Models\Referral::VISIT_CONSENT_PHONE_ONLY, \App\Models\Referral::VISIT_CONSENT_DECLINED], true))
                                        <span class="chip {{ $referral->visitConsentChipClass() }}">{{ $referral->visitConsentLabel() }}</span>
                                    @endif
                                </div>
                            @else
                                <div class="wb-calls">
                                    @if ($patient->phone)
                                        <a href="{{ $telHref($patient->phone) }}" class="wb-callbtn"><small>โทรผู้ป่วย</small>{{ $patient->phone }}</a>
                                    @else
                                        <span class="wb-callbtn none"><small>ผู้ป่วย</small>ไม่มีเบอร์</span>
                                    @endif
                                    @if ($referral->caregiver_phone)
                                        <a href="{{ $telHref($referral->caregiver_phone) }}" class="wb-callbtn"><small>โทรผู้ดูแล{{ $referral->caregiver_name ? ' · '.$referral->caregiver_name : '' }}{{ $referral->caregiver_relationship ? ' ('.$referral->caregiver_relationship.')' : '' }}</small>{{ $referral->caregiver_phone }}</a>
                                    @endif
                                    @if ($referral->drug_allergy_status === \App\Models\Referral::DRUG_ALLERGY_YES)
                                        <span class="chip chip-risk">{{ \Illuminate\Support\Str::limit($referral->drugAllergyText(), 40) }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="wb-right">
                            @if ($kind === 'visit')
                                @if ($daysLeft < 0)
                                    <span class="chip chip-overdue">เกินกำหนด {{ abs($daysLeft) }} วัน</span>
                                @elseif ($daysLeft === 0)
                                    <span class="chip chip-today">ครบวันนี้</span>
                                @else
                                    <span class="chip chip-neutral">อีก {{ $daysLeft }} วัน</span>
                                @endif
                            @endif
                            <a href="{{ route('follow-up-plans.record.create', $plan) }}" class="btn btn-secondary btn-sm">เริ่มติดตาม</a>
                            <span class="wb-chev" aria-hidden="true">&#9662;</span>
                        </div>
                    </summary>
                    <div class="wb-detail">
                        <dl class="wb-dl">
                            <div class="full">
                                <dt>ประเด็นที่หอฝากติดตาม</dt>
                                <dd>
                                    @if ($concerns->isNotEmpty())
                                        <ul>@foreach ($concerns as $concern)<li>{{ $concern }}</li>@endforeach</ul>
                                    @else
                                        <span class="caption">ไม่มีประเด็นที่ฝากไว้</span>
                                    @endif
                                </dd>
                            </div>
                            <div><dt>อุปกรณ์ที่ผู้ป่วยใช้</dt><dd>{{ $equipment->isNotEmpty() ? $equipment->implode(', ') : '—' }}</dd></div>
                            <div><dt>ประเภทเคส</dt><dd>{{ $referral->caseType?->name ?? '—' }}</dd></div>
                            <div class="full">
                                <dt>ที่อยู่</dt>
                                <dd>{{ $patient->address ?: '—' }}@if ($patient->sub_district) ต.{{ $patient->sub_district }}@endif @if ($patient->district) อ.{{ $patient->district }}@endif</dd>
                            </div>
                            <div><dt>เบอร์ผู้ป่วย</dt><dd>@if ($patient->phone)<a href="{{ $telHref($patient->phone) }}" class="wb-tel">{{ $patient->phone }}</a>@else<span class="caption">ไม่มีเบอร์ผู้ป่วย</span>@endif</dd></div>
                            <div>
                                <dt>เบอร์ผู้ดูแล</dt>
                                <dd>@if ($referral->caregiver_phone){{ $referral->caregiver_name }}{{ $referral->caregiver_relationship ? ' ('.$referral->caregiver_relationship.')' : '' }} <a href="{{ $telHref($referral->caregiver_phone) }}" class="wb-tel">{{ $referral->caregiver_phone }}</a>@else<span class="caption">ไม่มีข้อมูลผู้ดูแล</span>@endif</dd>
                            </div>
                            <div><dt>ความยินยอม</dt><dd>{{ $referral->visitConsentLabel() ?? 'ยังไม่ได้สอบถาม' }}</dd></div>
                            <div><dt>ประวัติแพ้ยา</dt><dd>{{ $referral->drugAllergyText() ?? 'ยังไม่ได้สอบถาม' }}</dd></div>
                            <div><dt>กำหนดนัด</dt><dd>{{ \App\Support\ThaiDate::date($plan->due_date) }}</dd></div>
                        </dl>
                        <a href="{{ route('referrals.show', $referral) }}" class="btn btn-secondary btn-sm">เปิดใบส่งต่อ</a>
                    </div>
                </details>
            </li>
        @endforeach
    @endforeach
</ul>

@if ($hidden > 0)
    <div class="wb-hidden">ซ่อนอีก {{ $hidden }} ราย (นัดเกิน 7 วัน) — <a href="{{ $allLink }}">ดูทั้งหมด</a></div>
@endif
