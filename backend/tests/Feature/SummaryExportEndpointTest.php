<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\Requirement;
use App\Models\User;
use App\Models\WorkLog;
use App\Services\OjtProgressSummary;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SummaryExportEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_guest_cannot_download_the_student_summary(): void
    {
        $this->getJson('/api/student/overview/export')->assertUnauthorized();
    }

    public function test_student_can_download_a_pdf_with_a_manila_date_filename(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        $ownInternship = Internship::factory()->create(['student_id' => $student->id]);
        $otherInternship = Internship::factory()->create(['student_id' => $other->id]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 09:00:00', 'Asia/Manila'));
        $internshipQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$internshipQueries): void {
            if (str_contains($query->sql, 'internships') && str_contains($query->sql, 'student_id')) {
                $internshipQueries[] = $query;
            }
        });

        $response = $this->stateful()->actingAs($student)->get(
            '/api/student/overview/export?student_id='.$other->id.'&internship_id='.$otherInternship->id,
        );

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Access-Control-Expose-Headers', 'Content-Disposition');
        $this->assertStringContainsString(
            'OJT-Progress-Summary-2026-09-24.pdf',
            $response->headers->get('Content-Disposition'),
        );
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertCount(1, $internshipQueries);
        $this->assertContains($student->id, $internshipQueries[0]->bindings);
        $this->assertNotContains($otherInternship->id, $internshipQueries[0]->bindings);
        $this->assertNotSame($ownInternship->id, $otherInternship->id);
    }

    public function test_export_returns_not_found_without_an_internship(): void
    {
        $student = User::factory()->create();

        $this->stateful()->actingAs($student)
            ->get('/api/student/overview/export')
            ->assertNotFound();
    }

    public function test_pdf_view_escapes_and_bounds_content_and_shows_empty_counts(): void
    {
        $student = User::factory()->create(['name' => 'Jordan <Student> Example']);
        $internship = Internship::factory()->create([
            'student_id' => $student->id,
            'required_minutes' => 30_000,
            'start_date' => '2026-10-01',
            'end_date' => '2026-12-15',
            'work_days' => [1, 2, 3, 4, 5],
            'expected_daily_minutes' => 480,
        ]);
        $titles = [
            '<script>alert(1)</script>',
            'First very long blocker title that should wrap without being cut off',
            'Second very long blocker title that should wrap without being cut off',
            'Third very long blocker title that should wrap without being cut off',
            'Fourth very long blocker title that should wrap without being cut off',
            'Overflow blocker title that should be represented by the remaining count',
        ];
        foreach ($titles as $title) {
            Requirement::factory()->create([
                'internship_id' => $internship->id,
                'title' => $title,
                'is_required' => true,
                'status' => 'incomplete',
                'due_date' => null,
            ]);
        }
        $generatedAt = CarbonImmutable::parse('2026-09-24 09:00:00', 'Asia/Manila');
        $summary = app(OjtProgressSummary::class)->build($internship, $generatedAt);

        $html = view('exports.ojt-summary', $summary)->render();

        $this->assertStringContainsString('Jordan &lt;Student&gt; Example', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('First very long blocker title that should wrap without being cut off', $html);
        $this->assertStringNotContainsString($titles[5], $html);
        $this->assertStringContainsString('and 1 more', $html);
        $this->assertStringContainsString('Rendered: 0m', $html);
        $this->assertStringContainsString('Required: 500h', $html);
        $this->assertStringContainsString('0%', $html);
        $this->assertStringContainsString('Not started', $html);
        $this->assertStringContainsString('No tasks yet', $html);
        $this->assertStringContainsString(
            'Generated from the student’s tracked OJT data for personal progress monitoring. This is not official verification of attendance or internship completion by a school or company.',
            $html,
        );
    }

    public function test_pdf_view_has_empty_required_requirement_state(): void
    {
        $student = User::factory()->create();
        $internship = Internship::factory()->create(['student_id' => $student->id]);
        $generatedAt = CarbonImmutable::parse('2026-09-24 09:00:00', 'Asia/Manila');
        $summary = app(OjtProgressSummary::class)->build($internship, $generatedAt);

        $html = view('exports.ojt-summary', $summary)->render();

        $this->assertStringContainsString('No tasks yet', $html);
        $this->assertStringContainsString('No required requirements yet', $html);
    }

    public function test_ready_summary_still_shows_zero_remaining_hours_and_incomplete_count(): void
    {
        $student = User::factory()->create();
        $internship = Internship::factory()->create([
            'student_id' => $student->id,
            'required_minutes' => 30_000,
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-15',
            'work_days' => [1, 2, 3, 4, 5],
            'expected_daily_minutes' => 480,
        ]);
        WorkLog::factory()->completed()->create([
            'internship_id' => $internship->id,
            'work_date' => '2026-09-23',
            'rendered_minutes' => 30_000,
        ]);
        $generatedAt = CarbonImmutable::parse('2026-09-24 09:00:00', 'Asia/Manila');
        $summary = app(OjtProgressSummary::class)->build($internship, $generatedAt);

        $html = view('exports.ojt-summary', $summary)->render();

        $this->assertTrue($summary['overview']['completion']['ready']);
        $this->assertStringContainsString('Completion Readiness: Ready', $html);
        $this->assertStringContainsString('Remaining hours: 0m', $html);
        $this->assertStringContainsString('Incomplete required requirements: 0', $html);
    }
}
