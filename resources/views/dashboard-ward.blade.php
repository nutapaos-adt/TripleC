<x-app-layout>
    <x-slot name="header">ภาพรวมหอผู้ป่วย — {{ auth()->user()->ward?->name ?? 'ยังไม่ได้กำหนดหอผู้ป่วย' }}</x-slot>

    <div class="page-head">
        <h1 class="h1">ภาพรวมหอผู้ป่วยประจำเดือนนี้</h1>
        <div class="sub">ข้อมูลใบส่งต่อของหอ {{ auth()->user()->ward?->name ?? '—' }} เดือน {{ now()->translatedFormat('F Y') }}</div>
    </div>

    @if (! auth()->user()->ward_id)
        <div class="banner" style="background:var(--color-warning-tint);border-color:var(--color-warning);">
            <div class="banner-text"><p style="color:var(--color-warning);">บัญชีของคุณยังไม่ได้กำหนดหอผู้ป่วย จึงยังไม่มีข้อมูลให้แสดง — กรุณาติดต่อแอดมินเพื่อกำหนดหอผู้ป่วยให้บัญชีนี้</p></div>
        </div>
    @endif

    <div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);">
        <div class="kpi-tile">
            <div class="caption">ส่งข้อมูลแล้ว (เดือนนี้)</div>
            <div class="kpi-value">{{ $totalReferralsCount }}</div>
        </div>
        <div class="kpi-tile">
            <div class="caption">รอยืนยัน</div>
            <div class="kpi-value">{{ $pendingReviewCount }}</div>
        </div>
        <div class="kpi-tile">
            <div class="caption">ได้รับการเยี่ยมแล้ว</div>
            <div class="kpi-value">{{ $visitedCount }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="h2">สัดส่วนประเภทเคส (เดือนนี้)</div>
        </div>
        <div class="card-body">
            @forelse ($caseTypeBreakdown as $row)
                <div style="display:flex;align-items:center;gap:var(--space-4);margin-bottom:var(--space-3);">
                    <div style="width:180px;flex-shrink:0;font-size:13px;color:var(--color-neutral-700);">{{ $row['name'] }}</div>
                    <div style="flex:1;background:var(--color-neutral-100);border-radius:var(--radius-pill);height:10px;overflow:hidden;">
                        <div style="width:{{ $row['percentage'] }}%;background:var(--color-primary-700);height:100%;border-radius:var(--radius-pill);"></div>
                    </div>
                    <div style="width:90px;flex-shrink:0;text-align:right;font-size:13px;font-weight:600;color:var(--color-neutral-900);">{{ $row['count'] }} ({{ $row['percentage'] }}%)</div>
                </div>
            @empty
                <div class="caption">ยังไม่มีข้อมูลใบส่งต่อในเดือนนี้</div>
            @endforelse
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="h2">รายการรอยืนยัน</div>
            <div class="sub">{{ $pendingReferrals->count() }} รายการ</div>
        </div>
        <div class="card-body">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ผู้ป่วย</th>
                            <th>ประเภทเคส</th>
                            <th>วันที่ส่งข้อมูล</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pendingReferrals as $referral)
                            <tr>
                                <td>
                                    <div class="patient-name">{{ $referral->patient->name }}</div>
                                    <div class="patient-hn">HN {{ $referral->patient->hn }}</div>
                                </td>
                                <td>
                                    <span class="chip chip-casetype">{{ $referral->caseType?->name ?? '— รอ AI ประเมิน —' }}</span>
                                </td>
                                <td class="due-date">{{ $referral->created_at->format('d/m/Y') }}</td>
                                <td style="text-align:right;">
                                    <a href="{{ route('referrals.show', $referral) }}" class="btn btn-secondary btn-sm">แก้ไขข้อมูล</a>
                                    <a href="{{ route('referrals.show', $referral) }}" class="btn btn-secondary btn-sm">ดูรายละเอียด</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align:center;color:var(--color-neutral-500);padding:var(--space-8) 0;">ไม่มีรายการรอยืนยัน</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
