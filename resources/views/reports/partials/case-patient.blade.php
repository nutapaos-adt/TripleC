{{-- ข้อมูลผู้ป่วยในการ์ดเคส (ไม่รวมชื่อคอลัมน์): ต้องส่ง $row --}}
<div class="kv"><span class="l">การวินิจฉัย</span><span class="v">{{ $row['diagnosis'] ?: '—' }}</span></div>
<div class="kv"><span class="l">โรคประจำตัว</span><span class="v">{{ $row['underlying_disease'] ?: '—' }}</span></div>
@if ($row['surgery_history'])
    <div class="kv"><span class="l">การผ่าตัด</span><span class="v">{{ $row['surgery_history'] }}@if ($row['surgery_date']) &middot; วันที่ผ่าตัด {{ \App\Support\ThaiDate::date($row['surgery_date']) }}@endif</span></div>
@endif
@if (! empty($row['equipment']))
    <div class="kv"><span class="l">อุปกรณ์ของผู้ป่วย</span><span class="v">{{ implode(', ', $row['equipment']) }}</span></div>
@endif
<div class="kv {{ $row['drug_allergy_is_yes'] ? 'allergy-yes' : '' }}"><span class="l">ประวัติแพ้ยา</span><span class="v">{{ $row['drug_allergy'] ?? 'ยังไม่ได้สอบถาม' }}</span></div>
