<?php

namespace App\Services;

/**
 * ตรวจค่าสัญญาณชีพที่พยาบาลกรอกเป็นช่องด้วยเกณฑ์ตายตัว (ไม่ใช่ AI) — ทำงานทันที ไม่ต้องรอโมเดล และไม่เดา
 *
 * ผลที่ได้เป็นเพียง "ข้อสังเกต" ให้พยาบาลดูและให้ AI ใช้ประกอบ ไม่ได้ตัดสินใจหรือเปลี่ยนสถานะเคสใดๆ
 * (human-in-the-loop: พยาบาลยืนยันการตัดสินใจเสมอ) เกณฑ์เป็นเกณฑ์กว้างสำหรับผู้ใหญ่เพื่อให้ตั้งข้อสังเกตเท่านั้น
 * ผู้ป่วย COPD/ผู้ที่ใช้ออกซิเจนอาจมีเป้า SpO2 ต่างจากทั่วไป พยาบาลจึงต้องพิจารณาตามบริบทเสมอ
 */
class VitalSignFlags
{
    /**
     * @param  array<string, mixed>|null  $vitals  คีย์: bp (เช่น "120/80"), pr, rr, temp, spo2
     * @return array<int, array{key: string, label: string, text: string, level: string}>  level = critical | warning
     */
    public static function flags(?array $vitals): array
    {
        if (! $vitals) {
            return [];
        }

        $flags = [];
        $add = function (string $key, string $label, string $text, string $level) use (&$flags) {
            $flags[] = ['key' => $key, 'label' => $label, 'text' => $text, 'level' => $level];
        };

        $spo2 = self::number($vitals['spo2'] ?? null);
        if ($spo2 !== null) {
            if ($spo2 < 90) {
                $add('spo2', 'SpO2', "SpO2 {$spo2}% ต่ำกว่า 90%", 'critical');
            } elseif ($spo2 < 92) {
                $add('spo2', 'SpO2', "SpO2 {$spo2}% ค่อนข้างต่ำ (90-91%)", 'warning');
            }
        }

        $rr = self::number($vitals['rr'] ?? null);
        if ($rr !== null) {
            if ($rr >= 30 || $rr < 8) {
                $add('rr', 'RR', "RR {$rr} ครั้ง/นาที ผิดปกติมาก", 'critical');
            } elseif ($rr > 24 || $rr < 10) {
                $add('rr', 'RR', "RR {$rr} ครั้ง/นาที ผิดปกติ", 'warning');
            }
        }

        $pr = self::number($vitals['pr'] ?? null);
        if ($pr !== null) {
            if ($pr >= 130 || $pr < 40) {
                $add('pr', 'PR', "PR {$pr} ครั้ง/นาที ผิดปกติมาก", 'critical');
            } elseif ($pr > 100 || $pr < 50) {
                $add('pr', 'PR', "PR {$pr} ครั้ง/นาที ผิดปกติ", 'warning');
            }
        }

        $temp = self::number($vitals['temp'] ?? null);
        if ($temp !== null) {
            if ($temp >= 39 || $temp < 35) {
                $add('temp', 'Temp', "อุณหภูมิ {$temp} °C ผิดปกติมาก", 'critical');
            } elseif ($temp >= 37.8 || $temp < 36) {
                $add('temp', 'Temp', "อุณหภูมิ {$temp} °C ผิดปกติ", 'warning');
            }
        }

        if (isset($vitals['bp']) && preg_match('/^\s*(\d{2,3})\s*[\/\\\\]\s*(\d{2,3})\s*$/', (string) $vitals['bp'], $m)) {
            $sys = (int) $m[1];
            $dia = (int) $m[2];
            if ($sys >= 180 || $dia >= 110 || $sys < 90) {
                $add('bp', 'BP', "BP {$sys}/{$dia} mmHg ผิดปกติมาก", 'critical');
            } elseif ($sys >= 160 || $dia >= 100 || $sys < 100) {
                $add('bp', 'BP', "BP {$sys}/{$dia} mmHg ผิดปกติ", 'warning');
            }
        }

        return $flags;
    }

    /**
     * ข้อความสรุปค่าที่วัดได้ + ข้อสังเกต สำหรับใส่ในพรอมต์ของ AI ("-" ถ้าไม่มีค่าที่กรอก)
     *
     * @param  array<string, mixed>|null  $vitals
     */
    public static function promptText(?array $vitals): string
    {
        if (! $vitals) {
            return '-';
        }

        $labels = ['bp' => 'BP', 'pr' => 'PR', 'rr' => 'RR', 'temp' => 'Temp', 'spo2' => 'SpO2'];
        $parts = [];
        foreach ($labels as $key => $label) {
            if (isset($vitals[$key]) && $vitals[$key] !== '') {
                $parts[] = $label.' '.$vitals[$key];
            }
        }

        if ($parts === []) {
            return '-';
        }

        $flagText = collect(self::flags($vitals))->pluck('text')->implode('; ');

        return implode(', ', $parts).($flagText !== '' ? " (ค่าที่ผิดปกติตามเกณฑ์: {$flagText})" : ' (ทุกค่าอยู่ในเกณฑ์ปกติ)');
    }

    private static function number(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $n = (float) $value;

        return floor($n) === $n ? (int) $n : $n;
    }
}
