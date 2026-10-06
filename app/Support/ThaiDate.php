<?php

namespace App\Support;

use DateTimeInterface;

/**
 * แสดงวันที่เป็นรูปแบบไทย (วัน/เดือน/ปี พ.ศ.) ทั่วทั้งระบบ — ฐานข้อมูลและฟอร์มยังเก็บเป็น ค.ศ. (ISO) ตามเดิม
 * ใช้เฉพาะตอน "แสดงผล" เท่านั้น
 */
class ThaiDate
{
    public const MONTHS = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
        7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];

    /** 09/03/2499 */
    public static function date(?DateTimeInterface $date, string $empty = '—'): string
    {
        return $date ? $date->format('d/m/').((int) $date->format('Y') + 543) : $empty;
    }

    /** 09/03/2499 14:30 */
    public static function dateTime(?DateTimeInterface $date, string $empty = '—'): string
    {
        return $date ? self::date($date).' '.$date->format('H:i') : $empty;
    }

    /** ตุลาคม 2569 */
    public static function monthYear(?DateTimeInterface $date, string $empty = '—'): string
    {
        return $date ? self::MONTHS[(int) $date->format('n')].' '.((int) $date->format('Y') + 543) : $empty;
    }

    public static function monthName(int $month): string
    {
        return self::MONTHS[$month] ?? '';
    }

    /** ปี ค.ศ. → ปี พ.ศ. (ใช้กับตัวเลือกปีในตัวกรอง ซึ่งส่งค่าเป็น ค.ศ. กลับมาเหมือนเดิม) */
    public static function year(int $ceYear): int
    {
        return $ceYear + 543;
    }
}
