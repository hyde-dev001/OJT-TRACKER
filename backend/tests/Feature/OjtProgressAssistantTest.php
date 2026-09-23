<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkLog;
use App\Services\OjtProgressAssistant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OjtProgressAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_progress_and_required_pace_from_completed_minutes(): void
    {
        $internship = $this->internship([
            'required_minutes' => 1_000,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'work_days' => [1, 2, 3, 4, 5],
            'expected_daily_minutes' => 480,
        ]);

        WorkLog::factory()->completed()->create([
            'internship_id' => $internship->id,
            'rendered_minutes' => 601,
            'work_date' => '2026-09-22',
        ]);
        WorkLog::factory()->create([
            'internship_id' => $internship->id,
            'rendered_minutes' => 900,
            'status' => 'draft',
            'work_date' => '2026-09-23',
        ]);

        $overview = $this->build($internship);

        $this->assertSame(601, $overview['progress']['rendered_minutes']);
        $this->assertSame(399, $overview['progress']['remaining_minutes']);
        $this->assertSame(60, $overview['progress']['percentage']);
        $this->assertSame(6, $overview['pace']['remaining_scheduled_days']);
        $this->assertSame(67, $overview['pace']['required_daily_minutes']);
        $this->assertSame('on_track', $overview['pace']['status']);
    }

    public function test_it_caps_progress_and_sets_zero_pace_when_hours_are_complete(): void
    {
        $internship = $this->internship([
            'required_minutes' => 600,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'work_days' => [1, 2, 3, 4, 5],
            'expected_daily_minutes' => 480,
        ]);

        WorkLog::factory()->completed()->create([
            'internship_id' => $internship->id,
            'rendered_minutes' => 900,
            'work_date' => '2026-09-22',
        ]);

        $overview = $this->build($internship);

        $this->assertSame(0, $overview['progress']['remaining_minutes']);
        $this->assertSame(100, $overview['progress']['percentage']);
        $this->assertSame(0, $overview['pace']['required_daily_minutes']);
        $this->assertSame('complete', $overview['pace']['status']);
    }

    public function test_it_counts_the_inclusive_configured_schedule(): void
    {
        $internship = $this->internship([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'work_days' => [1, 3, 5],
            'required_minutes' => 3_000,
            'expected_daily_minutes' => 480,
        ]);

        $overview = $this->build($internship, '2026-09-23');

        $this->assertSame(4, $overview['pace']['remaining_scheduled_days']);
    }

    public function test_it_distinguishes_future_start_deadline_and_at_risk_states(): void
    {
        $future = $this->internship([
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'required_minutes' => 4_800,
            'work_days' => [1, 2, 3, 4, 5],
            'expected_daily_minutes' => 480,
        ]);
        $expired = $this->internship([
            'start_date' => '2026-08-01',
            'end_date' => '2026-09-22',
            'required_minutes' => 4_800,
            'work_days' => [1, 2, 3, 4, 5],
            'expected_daily_minutes' => 480,
        ]);
        $atRisk = $this->internship([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-24',
            'required_minutes' => 4_800,
            'work_days' => [1, 2, 3, 4, 5],
            'expected_daily_minutes' => 60,
        ]);

        $this->assertSame('not_started', $this->build($future)['pace']['status']);
        $this->assertSame('deadline_passed', $this->build($expired)['pace']['status']);
        $this->assertSame('at_risk', $this->build($atRisk)['pace']['status']);
    }

    public function test_incomplete_schedule_data_makes_pace_unavailable(): void
    {
        $missing = $this->internship([
            'work_days' => null,
            'expected_daily_minutes' => null,
        ]);
        $invalid = $this->internship([
            'work_days' => [0, 8],
            'expected_daily_minutes' => 0,
        ]);
        $invalidPeriod = $this->internship([
            'start_date' => '2026-09-30',
            'end_date' => '2026-09-01',
        ]);

        $this->assertSame('pace_unavailable', $this->build($missing)['pace']['status']);
        $this->assertSame('pace_unavailable', $this->build($invalid)['pace']['status']);
        $this->assertSame('pace_unavailable', $this->build($invalidPeriod)['pace']['status']);
    }

    public function test_valid_schedule_with_no_remaining_days_stays_at_risk(): void
    {
        $internship = $this->internship([
            'start_date' => '2026-09-23',
            'end_date' => '2026-09-23',
            'work_days' => [1],
            'expected_daily_minutes' => 480,
        ]);

        $overview = $this->build($internship);

        $this->assertSame(0, $overview['pace']['remaining_scheduled_days']);
        $this->assertNull($overview['pace']['required_daily_minutes']);
        $this->assertSame('at_risk', $overview['pace']['status']);
    }

    public function test_deadline_passed_adds_a_high_priority_attention_item(): void
    {
        $internship = $this->internship([
            'start_date' => '2026-08-01',
            'end_date' => '2026-09-22',
            'required_minutes' => 4_800,
            'work_days' => [1, 2, 3, 4, 5],
            'expected_daily_minutes' => 480,
        ]);

        $overview = $this->build($internship);

        $this->assertSame('deadline_passed', $overview['pace']['status']);
        $this->assertSame(1, $overview['attention']['items'][0]['priority']);
        $this->assertSame('work_hours', $overview['attention']['items'][0]['type']);
        $this->assertSame('/student/work-hours', $overview['attention']['items'][0]['href']);
    }

    public function test_attention_is_prioritized_capped_and_deterministic(): void
    {
        $internship = $this->internship([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'required_minutes' => 10_000,
            'work_days' => [1, 2, 3, 4, 5],
            'expected_daily_minutes' => 60,
        ]);

        Requirement::factory()->create(['internship_id' => $internship->id, 'title' => 'Required overdue', 'is_required' => true, 'status' => 'incomplete', 'due_date' => '2026-09-20']);
        Task::factory()->create(['internship_id' => $internship->id, 'title' => 'Task overdue', 'status' => 'to_do', 'due_date' => '2026-09-20']);
        Requirement::factory()->create(['internship_id' => $internship->id, 'title' => 'Required today', 'is_required' => true, 'status' => 'incomplete', 'due_date' => '2026-09-23']);
        Task::factory()->create(['internship_id' => $internship->id, 'title' => 'Task today', 'status' => 'to_do', 'due_date' => '2026-09-23']);
        Requirement::factory()->create(['internship_id' => $internship->id, 'title' => 'Required soon', 'is_required' => true, 'status' => 'incomplete', 'due_date' => '2026-09-24']);
        Task::factory()->create(['internship_id' => $internship->id, 'title' => 'Task soon', 'status' => 'to_do', 'due_date' => '2026-09-24']);

        $overview = $this->build($internship);

        $this->assertSame([2, 3, 4, 5, 6], array_column($overview['attention']['items'], 'priority'));
        $this->assertSame(2, $overview['attention']['additional_count']);
        $this->assertSame('Required overdue', $overview['attention']['items'][0]['title']);
        $this->assertSame('Task overdue', $overview['attention']['items'][1]['title']);
    }

    public function test_required_no_due_date_is_attention_but_optional_no_due_date_is_not(): void
    {
        $internship = $this->internship([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'required_minutes' => 3_000,
            'work_days' => [1, 2, 3, 4, 5],
            'expected_daily_minutes' => 1_000,
        ]);

        Requirement::factory()->create([
            'internship_id' => $internship->id,
            'title' => 'Required no date',
            'is_required' => true,
            'status' => 'incomplete',
            'due_date' => null,
        ]);
        Requirement::factory()->create([
            'internship_id' => $internship->id,
            'title' => 'Optional no date',
            'is_required' => false,
            'status' => 'incomplete',
            'due_date' => null,
        ]);

        $items = $this->build($internship)['attention']['items'];

        $this->assertCount(1, $items);
        $this->assertSame('Required no date', $items[0]['title']);
        $this->assertSame(9, $items[0]['priority']);
    }

    public function test_readiness_requires_hours_and_required_requirements_only(): void
    {
        $internship = $this->internship([
            'required_minutes' => 480,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'work_days' => [1, 2, 3, 4, 5],
            'expected_daily_minutes' => 480,
        ]);
        $required = Requirement::factory()->create([
            'internship_id' => $internship->id,
            'is_required' => true,
            'status' => 'incomplete',
        ]);
        Requirement::factory()->create([
            'internship_id' => $internship->id,
            'is_required' => false,
            'status' => 'incomplete',
        ]);
        Task::factory()->create([
            'internship_id' => $internship->id,
            'status' => 'to_do',
        ]);

        $notReady = $this->build($internship);
        $this->assertFalse($notReady['completion']['ready']);
        $this->assertSame([$required->id], array_column($notReady['completion']['incomplete_required_requirements'], 'id'));

        WorkLog::factory()->completed()->create([
            'internship_id' => $internship->id,
            'rendered_minutes' => 480,
            'work_date' => '2026-09-22',
        ]);
        $required->update(['status' => 'completed']);

        $ready = $this->build($internship);
        $this->assertTrue($ready['completion']['ready']);
        $this->assertSame([], $ready['completion']['blockers']);
    }

    public function test_hours_alone_can_make_readiness_true_when_no_required_requirements_exist(): void
    {
        $internship = $this->internship([
            'required_minutes' => 480,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'work_days' => [1, 2, 3, 4, 5],
            'expected_daily_minutes' => 480,
        ]);

        WorkLog::factory()->completed()->create([
            'internship_id' => $internship->id,
            'rendered_minutes' => 480,
            'work_date' => '2026-09-22',
        ]);

        $this->assertTrue($this->build($internship)['completion']['ready']);
    }

    private function internship(array $attributes = []): Internship
    {
        return Internship::factory()->create(array_merge([
            'student_id' => User::factory(),
            'required_minutes' => 3_000,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'work_days' => [1, 2, 3, 4, 5],
            'expected_daily_minutes' => 480,
        ], $attributes));
    }

    private function build(Internship $internship, string $date = '2026-09-23'): array
    {
        return app(OjtProgressAssistant::class)->build(
            $internship->refresh(),
            CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Manila'),
        );
    }
}
