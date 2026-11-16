{{-- หัวการ์ดเคส (ชื่อ/ป้าย/วันที่) ใช้ร่วมกันทั้งการ์ดปกติและตารางพิมพ์ของเคสยาว: ต้องส่ง $row และ $n --}}
<div class="case-head">
    <div class="case-head-left">
        <span class="case-no">{{ $n }}</span>
        <span class="case-name">{{ $row['patient_name'] }}</span>
        @if ($row['patient_age'] !== null)
            <span class="case-age">อายุ {{ $row['patient_age'] }} ปี</span>
        @endif
    </div>
    <div class="case-badges">
        <span class="badge case-zone {{ $row['zone'] === 'in_area' ? 'in' : 'out' }}">{{ $row['zone'] === 'in_area' ? 'ในเขต' : 'นอกเขต' }}</span>
        <span class="badge badge-ward">{{ $row['ward_name'] }}</span>
        @if ($row['on_time'])
            <span class="badge badge-ontime">ทันเวลา</span>
        @else
            <span class="badge badge-late">ล่าช้า</span>
        @endif
    </div>
</div>
<div class="case-dates">
    กำหนดเยี่ยม {{ \App\Support\ThaiDate::date($row['due_date']) ?? '—' }}
    &middot; เยี่ยมจริง {{ \App\Support\ThaiDate::date($row['visited_at']) ?? '—' }}
</div>
