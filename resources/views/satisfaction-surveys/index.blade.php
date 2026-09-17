<x-app-layout>
    <x-slot name="header">ประเมินความพึงพอใจ</x-slot>

    <div class="page-head">
        <h1 class="h1">ประเมินความพึงพอใจ</h1>
        <div class="sub">ผู้ป่วยในเขตที่มีผลการติดตามอย่างน้อย 1 ครั้ง</div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="h2">รายชื่อผู้ป่วย</div>
        </div>
        <div class="card-body">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ผู้ป่วย</th>
                            <th>เยี่ยมล่าสุด</th>
                            <th>สถานะประเมิน</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php
                                $referral = $row['referral'];
                                $survey = $row['survey'];
                                $evaluated = $survey && $survey->isSubmitted();
                                $tokenUrl = route('satisfaction-surveys.token.show', $survey->token);
                            @endphp
                            <tr>
                                <td>
                                    <div class="patient-name">{{ $referral->patient->name }}</div>
                                    <div class="patient-hn">HN {{ $referral->patient->hn }}</div>
                                </td>
                                <td class="due-date">
                                    {{ $row['latest_visit'] ? $row['latest_visit']->format('d/m/Y') : '—' }}
                                </td>
                                <td>
                                    @if ($evaluated)
                                        <span class="chip chip-success">ประเมินแล้ว</span>
                                    @else
                                        <span class="chip chip-warning">ยังไม่ประเมิน</span>
                                    @endif
                                </td>
                                <td style="text-align:right;">
                                    @if ($evaluated)
                                        <a href="{{ route('satisfaction-surveys.show', $survey) }}" class="btn btn-secondary btn-sm">ดูผลประเมิน</a>
                                    @else
                                        <div class="btn-row" style="margin-top:0;justify-content:flex-end;">
                                            <a href="{{ route('satisfaction-surveys.create', ['referral_id' => $referral->id]) }}" class="btn btn-primary btn-sm">ประเมินให้ผู้ป่วย</a>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="var p=document.getElementById('qr-{{ $referral->id }}'); p.style.display = p.style.display === 'none' ? 'block' : 'none';">แสดง QR</button>
                                        </div>
                                        <div id="qr-{{ $referral->id }}" class="qr-panel" style="display:none;margin-top:var(--space-3);text-align:left;">
                                            <div style="display:flex;gap:var(--space-3);align-items:center;">
                                                <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="var(--color-primary-700)" stroke-width="1.5" style="flex-shrink:0;">
                                                    <rect x="3" y="3" width="7" height="7"/>
                                                    <rect x="14" y="3" width="7" height="7"/>
                                                    <rect x="3" y="14" width="7" height="7"/>
                                                    <path d="M14 14h3v3h-3zM20 14v3M17 20h3M14 20h.01"/>
                                                </svg>
                                                <div style="flex:1;min-width:0;">
                                                    <input type="text" class="input" readonly value="{{ $tokenUrl }}" onclick="this.select()" style="font-size:12px;">
                                                    <span class="caption">ลิงก์ให้ผู้ป่วย/ญาติสแกน QR หรือกรอกเองผ่านลิงก์นี้</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align:center;color:var(--color-neutral-500);padding:var(--space-8) 0;">
                                    ยังไม่มีผู้ป่วยที่เข้าเงื่อนไข (ในเขต และมีผลการติดตามอย่างน้อย 1 ครั้ง)
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
