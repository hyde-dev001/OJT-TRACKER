<?php

namespace Database\Seeders;

use App\Models\Internship;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()
            ->where('email', 'like', 'browser-registration-%@example.com')
            ->get()
            ->each
            ->delete();

        $student = User::updateOrCreate(
            ['email' => 'student@example.com'],
            [
                'name' => 'Demo Student',
                'first_name' => 'Demo',
                'last_name' => 'Student',
                'suffix' => null,
                'password' => 'OjtTracker!2026',
                'email_verified_at' => now(),
            ],
        );

        $internships = $student->studentInternships()->oldest('id')->get();
        $internship = $internships->first();
        $internships->skip(1)->each->delete();
        $internship ??= $student->studentInternships()->make();
        $internship->fill([
            'required_minutes' => 30000,
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-15',
            'work_days' => [1, 2, 3, 4, 5],
            'expected_daily_minutes' => 480,
            'status' => 'active',
        ]);
        $internship->save();

        $this->cleanDemoRecords($internship);
        $this->seedWorkLogs($internship);
        $this->seedTasks($internship);
        $this->seedRequirements($internship);
    }

    private function cleanDemoRecords(Internship $internship): void
    {
        $internship->workLogs()
            ->whereNotIn('work_date', ['2026-09-01', '2026-09-02', '2026-09-03'])
            ->delete();
        $internship->tasks()
            ->whereNotIn('title', [
                'Plan weekly OJT report',
                'Review project handoff notes',
                'Prepare final OJT summary',
            ])
            ->delete();
        $internship->requirements()
            ->whereNotIn('title', [
                'Required internship agreement',
                'Completed ID orientation',
                'Optional portfolio checklist',
            ])
            ->delete();
    }

    private function seedWorkLogs(Internship $internship): void
    {
        $this->workLog($internship, '2026-09-01', 'completed');
        $this->workLog($internship, '2026-09-02', 'completed');
        $this->workLog($internship, '2026-09-03', 'completed');
    }

    /** @param array<string, mixed> $overrides */
    private function workLog(Internship $internship, string $date, string $status, array $overrides = []): void
    {
        $attributes = array_merge([
            'time_in' => '08:00',
            'time_out' => '17:00',
            'break_minutes' => 60,
            'rendered_minutes' => 480,
            'accomplishment_summary' => 'Completed assigned OJT activities.',
            'status' => $status,
        ], $overrides);
        $workLog = $internship->workLogs()->whereDate('work_date', $date)->first();

        if ($workLog) {
            $workLog->update($attributes);

            return;
        }

        $internship->workLogs()->create(array_merge(['work_date' => $date], $attributes));
    }

    private function seedTasks(Internship $internship): void
    {
        $this->task($internship, 'Plan weekly OJT report', 'to_do', '2026-09-25');
        $this->task($internship, 'Review project handoff notes', 'in_progress', '2026-09-26');
        $this->task($internship, 'Prepare final OJT summary', 'completed', '2026-12-10', [
            'completed_at' => '2026-09-07 10:00:00',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function task(Internship $internship, string $title, string $status, string $dueDate, array $overrides = []): void
    {
        Task::updateOrCreate(
            ['internship_id' => $internship->id, 'title' => $title],
            array_merge([
                'description' => 'Demo task for the personal OJT tracker.',
                'due_date' => $dueDate,
                'status' => $status,
                'completed_at' => null,
            ], $overrides),
        );
    }

    private function seedRequirements(Internship $internship): void
    {
        $this->requirement($internship, 'Required internship agreement', 'incomplete', '2026-09-25');
        $this->requirement($internship, 'Completed ID orientation', 'completed', '2026-09-05', [
            'student_notes' => 'Demo requirement completed.',
            'completed_at' => '2026-09-06 10:00:00',
        ]);
        $this->requirement($internship, 'Optional portfolio checklist', 'incomplete', '2026-10-15', [
            'is_required' => false,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function requirement(Internship $internship, string $title, string $status, string $dueDate, array $overrides = []): void
    {
        Requirement::updateOrCreate(
            ['internship_id' => $internship->id, 'title' => $title],
            array_merge([
                'description' => 'Demo requirement for the personal OJT tracker.',
                'due_date' => $dueDate,
                'is_required' => true,
                'status' => $status,
                'student_notes' => null,
                'completed_at' => null,
            ], $overrides),
        );
    }
}
