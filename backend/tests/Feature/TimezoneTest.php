<?php

namespace Tests\Feature;

use Tests\TestCase;

class TimezoneTest extends TestCase
{
    public function test_application_uses_the_philippine_timezone(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
        $this->assertSame('Asia/Manila', now()->getTimezone()->getName());
    }
}
