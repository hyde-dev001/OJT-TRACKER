<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_a_small_idempotent_canonical_demo(): void
    {
        $smokeUser = User::factory()->create(['email' => 'browser-registration-test@example.com']);
        Internship::factory()->create(['student_id' => $smokeUser->id]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $student = User::where('email', 'student@example.com')->firstOrFail();
        $internship = $student->currentInternship()->firstOrFail();

        $this->assertTrue(Hash::check('OjtTracker!2026', $student->password));
        $this->assertSame('2026-09-01', $internship->start_date->toDateString());
        $this->assertSame('2026-12-15', $internship->end_date->toDateString());
        $this->assertSame([1, 2, 3, 4, 5], $internship->work_days);
        $this->assertSame(480, $internship->expected_daily_minutes);
        $this->assertSame(3, $internship->workLogs()->count());
        $this->assertSame(3, $internship->tasks()->count());
        $this->assertSame(3, $internship->requirements()->count());
        $this->assertDatabaseMissing('users', ['email' => 'browser-registration-test@example.com']);
    }
}
