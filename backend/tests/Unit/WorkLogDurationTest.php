<?php

namespace Tests\Unit;

use App\Models\WorkLog;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WorkLogDurationTest extends TestCase
{
    public function test_calculates_net_minutes_from_times_and_break(): void
    {
        $this->assertSame(480, WorkLog::calculateRenderedMinutes('08:00', '17:00', 60));
    }

    public function test_rejects_a_non_positive_work_session(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WorkLog::calculateRenderedMinutes('17:00', '08:00', 0);
    }

    public function test_rejects_a_break_that_consumes_the_session(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WorkLog::calculateRenderedMinutes('08:00', '09:00', 60);
    }
}
