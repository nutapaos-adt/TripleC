<x-app-layout>
    <x-slot name="header">จัดการประเภทเคส &amp; เกณฑ์การเยี่ยม</x-slot>

    <div class="page-head" style="flex-direction:row;align-items:flex-start;justify-content:space-between;">
        <div>
            <h1 class="h1">ประเภทเคส</h1>
            <p class="sub">กำหนดประเภทเคสและกฎการติดตามเยี่ยมบ้านสำหรับแต่ละประเภท</p>
        </div>
        <a href="{{ route('admin.case-types.create') }}" class="btn btn-primary">+ เพิ่มประเภทเคส</a>
    </div>

    <div class="banner" style="background:var(--color-warning-tint);border-color:var(--color-warning);align-items:flex-start;">
        <div class="banner-text" style="color:var(--color-neutral-900);">
            <p><strong>จำนวนครั้งที่เยี่ยมจริง พิจารณาร่วมกับ "การจำแนกกลุ่มความรุนแรง" ของผู้ป่วยด้วย</strong> — ตารางด้านล่างเป็นกฎตาม<u>ประเภทเคส</u>เท่านั้น ระบบจะใช้ลำดับความสำคัญนี้ตอนคำนวณจริง:</p>
            <ol style="margin:8px 0 0;padding-left:20px;">
                <li>ประเภท <strong>Palliative Care</strong> → กำหนดรอบเยี่ยมตามระดับ PPS Score (ไม่ใช้กฎกลุ่มความรุนแรง)</li>
                <li>ประเภท <strong>หลังคลอด</strong> → เยี่ยม 3 ครั้งตามตารางด้านล่าง (ไม่ใช้กฎกลุ่มความรุนแรง)</li>
                <li>นอกเหนือจาก 2 ข้อบน หากผู้ป่วยอยู่ใน <strong>กลุ่ม 3 — บ้านสีแดง</strong> (ช่วยเหลือตนเองไม่ได้เลย) → เยี่ยมเดือนละ 1 ครั้งต่อเนื่อง โดยไม่คำนึงถึงประเภทเคส</li>
                <li>ประเภทเคสและกลุ่มอื่นๆ นอกเหนือจากข้างต้น → เยี่ยม 1 ครั้ง</li>
            </ol>
            <div style="margin-top:12px;padding-top:12px;border-top:1px dashed var(--color-neutral-300);">
                <strong>กำหนดวันเยี่ยมครั้งแรก พิจารณาจากการจำแนกกลุ่มความรุนแรง</strong> (คนละมิติกับจำนวนครั้งด้านบน — ใช้กำหนด "ภายในกี่วัน" ต้องเยี่ยมครั้งแรก):
                <ul style="margin:8px 0 0;padding-left:20px;">
                    <li><strong>กลุ่ม 1 — บ้านสีเขียว</strong> → เยี่ยมครั้งแรกภายใน 30 วัน</li>
                    <li><strong>กลุ่ม 2 — บ้านสีเหลือง</strong> → เยี่ยมครั้งแรกภายใน 14 วัน</li>
                    <li><strong>กลุ่ม 3 — บ้านสีแดง</strong> → เยี่ยมครั้งแรกภายใน 5 วัน</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ชื่อ</th>
                            <th>Slug</th>
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
                                </td>
                                <td><code>{{ $caseType->slug }}</code></td>
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
