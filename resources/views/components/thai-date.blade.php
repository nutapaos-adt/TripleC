@props([
    'name',
    'value' => null,
    'id' => null,
    'yearsBack' => 3,
    'yearsForward' => 1,
])

{{--
    ช่องวันที่แบบ วัน / เดือน / ปี พ.ศ. — ไม่ใช้ <input type="date"> เพราะเบราว์เซอร์แสดงรูปแบบตามภาษา/ภูมิภาคของเครื่อง
    (บางเครื่อง mm/dd/yyyy และเป็น ค.ศ.) ทำให้ผู้ใช้แต่ละคนกรอกไม่เหมือนกัน
    ค่าที่ส่งกลับไปเซิร์ฟเวอร์เป็น ISO (ค.ศ. Y-m-d) ผ่าน input ซ่อนชื่อ $name เสมอ
--}}
@php
    $iso = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) ? substr($value, 0, 10) : '');
    [$selY, $selM, $selD] = $iso !== '' ? array_map('intval', explode('-', $iso)) : [null, null, null];
    $thisYear = (int) now()->format('Y');
    $years = range($thisYear + (int) $yearsForward, $thisYear - (int) $yearsBack);
    if ($selY && ! in_array($selY, $years, true)) {
        $years[] = $selY;
        rsort($years);
    }
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
@endphp

<div class="thai-date" data-thai-date>
    <input type="hidden" name="{{ $name }}" @if($id) id="{{ $id }}" @endif value="{{ $iso }}">
    <select data-part="d" aria-label="วัน">
        <option value="">วัน</option>
        @for ($d = 1; $d <= 31; $d++)
            <option value="{{ $d }}" @selected($selD === $d)>{{ $d }}</option>
        @endfor
    </select>
    <select data-part="m" aria-label="เดือน">
        <option value="">เดือน</option>
        @foreach ($months as $i => $label)
            <option value="{{ $i + 1 }}" @selected($selM === $i + 1)>{{ $label }}</option>
        @endforeach
    </select>
    <select data-part="y" aria-label="ปี พ.ศ.">
        <option value="">ปี พ.ศ.</option>
        @foreach ($years as $y)
            <option value="{{ $y + 543 }}" @selected($selY === $y)>{{ $y + 543 }}</option>
        @endforeach
    </select>
</div>

@once
    <style>
        .thai-date { display: flex; gap: 6px; }
        .thai-date select { flex: 1 1 0; min-width: 0; }
        .thai-date select:nth-child(3) { flex: 1.4 1 0; }
    </style>
    <script>
        (function () {
            function daysIn(month, ceYear) {
                // ไม่ทราบปี → ให้ ก.พ. มี 29 วัน (ปีที่เลือกทีหลังจะตรวจอีกครั้ง)
                return new Date(ceYear || 2000, month, 0).getDate();
            }

            function init(box) {
                var hidden = box.querySelector('input[type="hidden"]');
                var d = box.querySelector('[data-part="d"]');
                var m = box.querySelector('[data-part="m"]');
                var y = box.querySelector('[data-part="y"]');

                function sync() {
                    var ce = y.value ? parseInt(y.value, 10) - 543 : null;
                    if (m.value) {
                        var max = daysIn(parseInt(m.value, 10), ce);
                        Array.prototype.forEach.call(d.options, function (o) {
                            if (o.value) o.disabled = parseInt(o.value, 10) > max;
                        });
                        if (d.value && parseInt(d.value, 10) > max) d.value = '';
                    }

                    var filled = [d.value, m.value, y.value].filter(Boolean).length;
                    var next = '';
                    if (filled === 3) {
                        next = ce + '-' + ('0' + m.value).slice(-2) + '-' + ('0' + d.value).slice(-2);
                    }

                    // เลือกไม่ครบ 3 ช่อง → บล็อกการส่งฟอร์ม ไม่ให้ค่าหายเงียบๆ
                    y.setCustomValidity(filled > 0 && filled < 3 ? 'กรุณาเลือกให้ครบ วัน เดือน และปี พ.ศ. หรือเว้นว่างทั้งหมด' : '');

                    if (hidden.value !== next) {
                        hidden.value = next;
                        hidden.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }

                [d, m, y].forEach(function (el) { el.addEventListener('change', sync); });
                sync();
            }

            function boot() {
                document.querySelectorAll('[data-thai-date]').forEach(init);
            }

            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
            else boot();
        })();
    </script>
@endonce
