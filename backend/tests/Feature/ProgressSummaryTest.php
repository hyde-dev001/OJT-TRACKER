<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_sums_completed_minutes_only(): void
    {
        $internship = Internship::factory()->create(['required_minutes' => 30000]);

        WorkLog::factory()->completed()->create([
            'internship_id' => $internship->id,
            'work_date' => '2026-09-01',
            'rendered_minutes' => 600,
        ]);
        WorkLog::factory()->create([
            'internship_id' => $internship->id,
            'work_date' => '2026-09-02',
            'rendered_minutes' => 900,
            'status' => 'draft',
        ]);

        $summary = $internship->progressSummary();

        $this->assertSame(600, $summary['completed_minutes']);
        $this->assertSame(10.0, $summary['completed_hours']);
        $this->assertSame(29400, $summary['remaining_minutes']);
    }

    public function test_progress_percentage_is_capped_at_100(): void
    {
        $internship = Internship::factory()->create(['required_minutes' => 600]);
        WorkLog::factory()->completed()->create([
            'internship_id' => $internship->id,
            'rendered_minutes' => 900,
        ]);

        $summary = $internship->progressSummary();

        $this->assertSame(100, $summary['percentage']);
        $this->assertSame(0, $summary['remaining_minutes']);
    }
}
