<x-app-layout>
    <x-slot name="header">จัดการประเภทเคส &amp; เกณฑ์การเยี่ยม</x-slot>

    <div class="page-head">
        <h1 class="h1">จัดการประเภทเคส &amp; เกณฑ์การเยี่ยม</h1>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="h2">รายการประเภทเคส</div>
            <a href="{{ route('admin.case-types.create') }}" class="btn btn-primary btn-sm">+ เพิ่มประเภทเคส</a>
        </div>
        <div class="card-body">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ชื่อ</th>
                            <th>คำอธิบาย</th>
                            <th>สถานะ</th>
                            <th>สรุปกฎการติดตาม</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($caseTypes as $caseType)
                            @php $rule = $caseType->visitRules->firstWhere('is_active', true); @endphp
                            <tr>
                                <td>
                                    <div class="patient-name">{{ $caseType->name }}</div>
                                    <div class="caption">{{ $caseType->slug }}</div>
                                </td>
                                <td style="max-width:260px;">{{ $caseType->description }}</td>
                                <td>
                                    <span class="chip {{ $caseType->is_active ? 'chip-success' : 'chip-method' }}">
                                        {{ $caseType->is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน' }}
                                    </span>
                                </td>
                                <td>
                                    @if (! $rule)
                                        {{-- ไม่มีเกณฑ์ตายตัวโดยตั้งใจ — ใช้กติกาสำรองตามกลุ่มความรุนแรงใน VisitPlanService --}}
                                        1 ครั้ง (กลุ่ม 3 บ้านสีแดง: เดือนละ 1 ครั้งต่อเนื่อง)
                                    @elseif ($rule->rule_type === 'fixed_count')
                                        ตายตัว: {{ $rule->fixed_visit_count }} ครั้ง ห่างกัน {{ $rule->fixed_interval_days }} วัน
                                    @else
                                        ตามคะแนน PPS ({{ count($rule->score_rules ?? []) }} ช่วงคะแนน)
                                    @endif
                                </td>
                                <td style="text-align:right;">
                                    <a href="{{ route('admin.case-types.edit', $caseType) }}" class="btn btn-secondary btn-sm">แก้ไข</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
