<?php

namespace Tests\Unit;

use App\Support\ThaiDate;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ThaiDateTest extends TestCase
{
    public function test_dates_are_shown_day_month_buddhist_year(): void
    {
        $date = Carbon::parse('1956-03-09 14:05');

        $this->assertSame('09/03/2499', ThaiDate::date($date));
        $this->assertSame('09/03/2499 14:05', ThaiDate::dateTime($date));
        $this->assertSame('มีนาคม 2499', ThaiDate::monthYear($date));
        $this->assertSame(2569, ThaiDate::year(2026));
    }

    public function test_missing_dates_show_a_dash(): void
    {
        $this->assertSame('—', ThaiDate::date(null));
        $this->assertSame('—', ThaiDate::dateTime(null));
        $this->assertSame('', ThaiDate::date(null, ''));
    }
}
