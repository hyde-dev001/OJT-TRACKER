<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use App\Services\OjtProgressAssistant;
use App\Services\OjtProgressSummary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OjtProgressSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aggregates_only_the_internships_tasks_and_required_requirements(): void
    {
        $generatedAt = CarbonImmutable::parse('2026-09-24 00:30:00', 'Asia/Manila');
        $student = User::factory()->create(['name' => 'John Paragas']);
        $other = User::factory()->create();
        $own = Internship::factory()->create([
            'student_id' => $student->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-15',
            'work_days' => [1, 2, 3, 4, 5],
            'expected_daily_minutes' => 480,
        ]);
        $otherInternship = Internship::factory()->create(['student_id' => $other->id]);

        Task::factory()->create([
            'internship_id' => $own->id,
            'status' => 'to_do',
            'due_date' => '2026-09-23',
        ]);
        Task::factory()->create([
            'internship_id' => $own->id,
            'status' => 'to_do',
            'due_date' => '2026-09-24',
        ]);
        Task::factory()->create([
            'internship_id' => $own->id,
            'status' => 'in_progress',
            'due_date' => null,
        ]);
        Task::factory()->completed()->create([
            'internship_id' => $own->id,
            'due_date' => '2026-09-23',
        ]);
        Task::factory()->create([
            'internship_id' => $otherInternship->id,
            'due_date' => '2026-09-23',
        ]);

        Requirement::factory()->create([
            'internship_id' => $own->id,
            'is_required' => true,
            'status' => 'incomplete',
            'due_date' => '2026-09-23',
        ]);
        Requirement::factory()->completed()->create([
            'internship_id' => $own->id,
            'is_required' => true,
        ]);
        Requirement::factory()->create([
            'internship_id' => $own->id,
            'is_required' => false,
            'due_date' => '2026-09-23',
        ]);
        Requirement::factory()->create([
            'internship_id' => $otherInternship->id,
            'is_required' => true,
            'due_date' => '2026-09-23',
        ]);

        $summary = app(OjtProgressSummary::class)->build($own, $generatedAt);

        $this->assertSame($student->name, $summary['student_name']);
        $this->assertSame('2026-09-24 00:30:00', $summary['generated_at']->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Manila', $summary['generated_at']->getTimezone()->getName());
        $this->assertSame(4, $summary['task_counts']['total']);
        $this->assertSame(2, $summary['task_counts']['to_do']);
        $this->assertSame(1, $summary['task_counts']['in_progress']);
        $this->assertSame(1, $summary['task_counts']['completed']);
        $this->assertSame(1, $summary['task_counts']['overdue']);
        $this->assertSame(2, $summary['requirement_counts']['total']);
        $this->assertSame(1, $summary['requirement_counts']['completed']);
        $this->assertSame(1, $summary['requirement_counts']['incomplete']);
        $this->assertSame(1, $summary['requirement_counts']['overdue']);
        $this->assertSame(
            app(OjtProgressAssistant::class)->build($own, $generatedAt->startOfDay()),
            $summary['overview'],
        );
    }

    public function test_it_returns_zero_counts_for_empty_data_and_reuses_future_pace_status(): void
    {
        $student = User::factory()->create();
        $internship = Internship::factory()->create([
            'student_id' => $student->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-12-15',
            'work_days' => [1, 2, 3, 4, 5],
            'expected_daily_minutes' => 480,
        ]);
        $generatedAt = CarbonImmutable::parse('2026-09-24 09:00:00', 'Asia/Manila');

        $summary = app(OjtProgressSummary::class)->build($internship, $generatedAt);

        $this->assertSame([
            'total' => 0,
            'to_do' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'overdue' => 0,
        ], $summary['task_counts']);
        $this->assertSame([
            'total' => 0,
            'completed' => 0,
            'incomplete' => 0,
            'overdue' => 0,
        ], $summary['requirement_counts']);
        $this->assertSame('not_started', $summary['overview']['pace']['status']);
        $this->assertSame(0, $summary['overview']['progress']['rendered_minutes']);
    }
}
