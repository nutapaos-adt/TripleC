@props([
    'name',
    'value' => null,
    'id' => null,
    'yearsBack' => 3,
    'yearsForward' => 1,
    'time' => false,
    'required' => false,
])

{{--
    ช่องวันที่แบบ วัน / เดือน / ปี พ.ศ. — ไม่ใช้ <input type="date"> เพราะเบราว์เซอร์แสดงรูปแบบตามภาษา/ภูมิภาคของเครื่อง
    (บางเครื่อง mm/dd/yyyy และเป็น ค.ศ.) ทำให้ผู้ใช้แต่ละคนกรอกไม่เหมือนกัน
    ค่าที่ส่งกลับไปเซิร์ฟเวอร์เป็น ISO (ค.ศ. Y-m-d) ผ่าน input ซ่อนชื่อ $name เสมอ
--}}
@php
    $full = $value instanceof \DateTimeInterface ? $value->format('Y-m-d\TH:i') : (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) ? str_replace(' ', 'T', substr($value, 0, 16)) : '');
    $iso = $full !== '' ? substr($full, 0, 10) : '';
    [$selY, $selM, $selD] = $iso !== '' ? array_map('intval', explode('-', $iso)) : [null, null, null];
    $selH = $time && strlen($full) >= 16 ? (int) substr($full, 11, 2) : null;
    $selI = $time && strlen($full) >= 16 ? (int) substr($full, 14, 2) : null;
    $hiddenValue = $time ? ($selH !== null ? $full : '') : $iso;
    $thisYear = (int) now()->format('Y');
    $years = range($thisYear + (int) $yearsForward, $thisYear - (int) $yearsBack);
    if ($selY && ! in_array($selY, $years, true)) {
        $years[] = $selY;
        rsort($years);
    }
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
@endphp

<div class="thai-date" data-thai-date @if($time) data-with-time @endif>
    <input type="hidden" name="{{ $name }}" @if($id) id="{{ $id }}" @endif value="{{ $hiddenValue }}">
    <select data-part="d" aria-label="วัน" @required($required)>
        <option value="">วัน</option>
        @for ($d = 1; $d <= 31; $d++)
            <option value="{{ $d }}" @selected($selD === $d)>{{ $d }}</option>
        @endfor
    </select>
    <select data-part="m" aria-label="เดือน" @required($required)>
        <option value="">เดือน</option>
        @foreach ($months as $i => $label)
            <option value="{{ $i + 1 }}" @selected($selM === $i + 1)>{{ $label }}</option>
        @endforeach
    </select>
    <select data-part="y" aria-label="ปี พ.ศ." @required($required)>
        <option value="">ปี พ.ศ.</option>
        @foreach ($years as $y)
            <option value="{{ $y + 543 }}" @selected($selY === $y)>{{ $y + 543 }}</option>
        @endforeach
    </select>
    @if ($time)
        <select data-part="h" aria-label="ชั่วโมง" @required($required)>
            <option value="">ชม.</option>
            @for ($h = 0; $h < 24; $h++)
                <option value="{{ $h }}" @selected($selH === $h)>{{ sprintf('%02d', $h) }}</option>
            @endfor
        </select>
        <select data-part="i" aria-label="นาที" @required($required)>
            <option value="">นาที</option>
            @for ($i = 0; $i < 60; $i++)
                <option value="{{ $i }}" @selected($selI === $i)>{{ sprintf('%02d', $i) }}</option>
            @endfor
        </select>
    @endif
</div>

@once
    <style>
        .thai-date { display: flex; gap: 6px; }
        .thai-date select { flex: 1 1 0; min-width: 0; }
        .thai-date select[data-part="y"] { flex: 1.4 1 0; }
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
                var h = box.querySelector('[data-part="h"]');
                var mi = box.querySelector('[data-part="i"]');
                var withTime = !!h;

                function sync() {
                    var ce = y.value ? parseInt(y.value, 10) - 543 : null;
                    if (m.value) {
                        var max = daysIn(parseInt(m.value, 10), ce);
                        Array.prototype.forEach.call(d.options, function (o) {
                            if (o.value) o.disabled = parseInt(o.value, 10) > max;
                        });
                        if (d.value && parseInt(d.value, 10) > max) d.value = '';
                    }

                    var parts = withTime ? [d, m, y, h, mi] : [d, m, y];
                    var need = parts.length;
                    var filled = parts.filter(function (el) { return el.value !== ''; }).length;
                    var next = '';
                    if (filled === need) {
                        next = ce + '-' + ('0' + m.value).slice(-2) + '-' + ('0' + d.value).slice(-2);
                        if (withTime) next += 'T' + ('0' + h.value).slice(-2) + ':' + ('0' + mi.value).slice(-2);
                    }

                    // เลือกไม่ครบทุกช่อง → บล็อกการส่งฟอร์ม ไม่ให้ค่าหายเงียบๆ
                    y.setCustomValidity(filled > 0 && filled < need ? 'กรุณาเลือกให้ครบทุกช่อง หรือเว้นว่างทั้งหมด' : '');

                    if (hidden.value !== next) {
                        hidden.value = next;
                        hidden.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }

                (withTime ? [d, m, y, h, mi] : [d, m, y]).forEach(function (el) { el.addEventListener('change', sync); });
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
