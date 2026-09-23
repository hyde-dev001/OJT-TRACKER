<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_user_route_rejects_guests(): void
    {
        $this->stateful()->getJson('/api/user')->assertUnauthorized();
    }

    public function test_login_returns_the_authenticated_student_without_a_role(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->stateful()->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonMissingPath('user.role')
            ->assertJsonMissingPath('user.password');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'student@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $response = $this->stateful()->postJson('/api/login', [
            'email' => 'student@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('auth');
        $this->assertStringNotContainsString('These credentials do not match our records.', $response->getContent());
        $this->assertStringNotContainsString('student@example.com', $response->getContent());
    }

    public function test_student_can_register_with_an_internship(): void
    {
        $this->stateful()->postJson('/api/register', [
            'name' => 'New Student',
            'suffix' => 'Jr.',
            'email' => 'new.student@example.com',
            'password' => 'StrongPassword1!',
            'password_confirmation' => 'StrongPassword1!',
            'required_hours' => 500,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'work_days' => [1, 2, 3, 4, 5],
            'expected_hours_per_day' => 8,
        ])
            ->assertCreated()
            ->assertJsonPath('user.name', 'New Student Jr.')
            ->assertJsonPath('user.email', 'new.student@example.com')
            ->assertJsonMissingPath('user.role')
            ->assertJsonMissingPath('user.password');

        $student = User::where('email', 'new.student@example.com')->firstOrFail();

        $this->assertSame('New Student Jr.', $student->name);

        $this->assertAuthenticatedAs($student);
        $this->assertDatabaseHas('internships', [
            'student_id' => $student->id,
            'required_minutes' => 30000,
            'status' => 'active',
        ]);
        $this->assertSame('2026-09-01', $student->currentInternship()->firstOrFail()->start_date->toDateString());
        $this->assertSame('2026-09-30', $student->currentInternship()->firstOrFail()->end_date->toDateString());
        $this->assertSame([1, 2, 3, 4, 5], $student->currentInternship()->firstOrFail()->work_days);
        $this->assertSame(480, $student->currentInternship()->firstOrFail()->expected_daily_minutes);
        $this->assertSame(1, Internship::where('student_id', $student->id)->count());
    }

    public function test_student_registration_stores_first_last_and_suffix_name_parts(): void
    {
        $this->stateful()->postJson('/api/register', [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'suffix' => 'III',
            'email' => 'maria.santos@example.com',
            'password' => 'StrongPassword1!',
            'password_confirmation' => 'StrongPassword1!',
            'required_hours' => 500,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'work_days' => [1, 2, 3, 4, 5],
            'expected_hours_per_day' => 8,
        ])
            ->assertCreated()
            ->assertJsonPath('user.first_name', 'Maria')
            ->assertJsonPath('user.last_name', 'Santos')
            ->assertJsonPath('user.suffix', 'III')
            ->assertJsonPath('user.name', 'Maria Santos III');

        $this->assertDatabaseHas('users', [
            'email' => 'maria.santos@example.com',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'suffix' => 'III',
            'name' => 'Maria Santos III',
        ]);
    }

    public function test_registration_normalizes_duplicate_work_days_and_converts_expected_hours(): void
    {
        $this->stateful()->postJson('/api/register', [
            'name' => 'Schedule Student',
            'email' => 'schedule.student@example.com',
            'password' => 'StrongPassword1!',
            'password_confirmation' => 'StrongPassword1!',
            'required_hours' => 500,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'work_days' => [1, 1, 5],
            'expected_hours_per_day' => 7.5,
        ])->assertCreated();

        $internship = User::where('email', 'schedule.student@example.com')->firstOrFail()
            ->currentInternship()->firstOrFail();

        $this->assertSame([1, 5], $internship->work_days);
        $this->assertSame(450, $internship->expected_daily_minutes);
    }

    public function test_registration_rejects_missing_or_invalid_schedule_values(): void
    {
        foreach ([
            ['work_days' => [], 'expected_hours_per_day' => 8, 'field' => 'work_days'],
            ['work_days' => [0], 'expected_hours_per_day' => 8, 'field' => 'work_days.0'],
            ['work_days' => [8], 'expected_hours_per_day' => 8, 'field' => 'work_days.0'],
            ['work_days' => [1], 'expected_hours_per_day' => 0, 'field' => 'expected_hours_per_day'],
            ['work_days' => [1], 'expected_hours_per_day' => 24.5, 'field' => 'expected_hours_per_day'],
        ] as $index => $schedule) {
            $this->stateful()->postJson('/api/register', [
                'name' => 'Invalid Schedule Student',
                'email' => "invalid-schedule-{$index}@example.com",
                'password' => 'StrongPassword1!',
                'password_confirmation' => 'StrongPassword1!',
                'required_hours' => 500,
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-30',
                'work_days' => $schedule['work_days'],
                'expected_hours_per_day' => $schedule['expected_hours_per_day'],
            ])->assertUnprocessable()->assertJsonValidationErrors($schedule['field']);
        }
    }

    public function test_registration_requires_a_target_end_date(): void
    {
        $this->stateful()->postJson('/api/register', [
            'name' => 'Missing End Date Student',
            'email' => 'missing-end-date@example.com',
            'password' => 'StrongPassword1!',
            'password_confirmation' => 'StrongPassword1!',
            'required_hours' => 500,
            'start_date' => '2026-09-01',
            'work_days' => [1],
            'expected_hours_per_day' => 8,
        ])->assertUnprocessable()->assertJsonValidationErrors('end_date');
    }

    public function test_registration_rejects_a_target_end_date_that_is_not_after_the_start_date(): void
    {
        foreach ([
            ['start_date' => '2026-09-01', 'end_date' => '2026-09-01'],
            ['start_date' => '2026-09-02', 'end_date' => '2026-09-01'],
        ] as $index => $dates) {
            $response = $this->stateful()->postJson('/api/register', [
                'name' => 'Date Validation Student',
                'email' => "date-validation-{$index}@example.com",
                'password' => 'StrongPassword1!',
                'password_confirmation' => 'StrongPassword1!',
            'required_hours' => 500,
            ...$dates,
            'work_days' => [1, 2, 3, 4, 5],
            'expected_hours_per_day' => 8,
        ]);

            $response
                ->assertUnprocessable()
                ->assertJsonValidationErrors('end_date');
            $this->assertStringContainsString(
                'Target end date must be after your OJT start date.',
                $response->getContent()
            );
        }
    }

    public function test_registration_rejects_an_unknown_suffix(): void
    {
        $this->stateful()->postJson('/api/register', [
            'name' => 'Suffix Student',
            'suffix' => 'PhD',
            'email' => 'suffix-validation@example.com',
            'password' => 'StrongPassword1!',
            'password_confirmation' => 'StrongPassword1!',
            'required_hours' => 500,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'work_days' => [1, 2, 3, 4, 5],
            'expected_hours_per_day' => 8,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('suffix');
    }

    public function test_registration_requires_positive_required_hours(): void
    {
        $this->stateful()->postJson('/api/register', [
            'name' => 'Invalid Hours Student',
            'email' => 'invalid-hours@example.com',
            'password' => 'StrongPassword1!',
            'password_confirmation' => 'StrongPassword1!',
            'required_hours' => 0,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'work_days' => [1, 2, 3, 4, 5],
            'expected_hours_per_day' => 8,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('required_hours');
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->stateful()->postJson('/api/register', [
            'name' => 'Duplicate Student',
            'email' => 'existing@example.com',
            'password' => 'StrongPassword1!',
            'password_confirmation' => 'StrongPassword1!',
            'required_hours' => 500,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'work_days' => [1, 2, 3, 4, 5],
            'expected_hours_per_day' => 8,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_email_availability_check_rejects_existing_account(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->getJson('/api/register/check-email?email=existing@example.com')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_email_availability_check_accepts_an_available_email(): void
    {
        $this->getJson('/api/register/check-email?email=available@example.com')
            ->assertOk()
            ->assertJson(['available' => true]);
    }

    public function test_registration_rolls_back_user_when_internship_creation_fails(): void
    {
        $email = 'atomic-registration@example.com';
        Internship::creating(static function (): void {
            throw new RuntimeException('Simulated internship failure.');
        });

        try {
            $this->withoutExceptionHandling()->stateful()->postJson('/api/register', [
                'name' => 'Atomic Student',
                'email' => $email,
                'password' => 'StrongPassword1!',
                'password_confirmation' => 'StrongPassword1!',
                'required_hours' => 500,
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-30',
                'work_days' => [1, 2, 3, 4, 5],
                'expected_hours_per_day' => 8,
            ]);
            $this->fail('The simulated internship failure should be raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated internship failure.', $exception->getMessage());
        } finally {
            Internship::flushEventListeners();
        }

        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertDatabaseMissing('internships', ['student_id' => User::where('email', $email)->value('id')]);
    }

    public function test_registration_requires_a_twelve_character_mixed_case_number_symbol_password(): void
    {
        $passwords = [
            'lowercaseonly!',
            'UPPERCASEONLY!',
            'StrongPassword',
            'StrongPassword!',
            'Aa!short',
        ];

        foreach ($passwords as $index => $password) {
            $this->stateful()->postJson('/api/register', [
                'name' => 'Weak Password Student',
                'email' => "weak-password-{$index}@example.com",
                'password' => $password,
                'password_confirmation' => $password,
                'required_hours' => 500,
                'start_date' => '2026-09-22',
                'end_date' => '2026-09-30',
                'work_days' => [1, 2, 3, 4, 5],
                'expected_hours_per_day' => 8,
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('password');
        }
    }

    public function test_authenticated_student_can_update_profile(): void
    {
        $student = User::factory()->create([
            'name' => 'Original Student',
            'first_name' => 'Original',
            'last_name' => 'Student',
            'suffix' => null,
            'password' => Hash::make('secret-password'),
        ]);
        $internship = Internship::factory()->create([
            'student_id' => $student->id,
            'start_date' => '2026-09-22',
            'end_date' => '2026-12-22',
        ]);

        $this->stateful()->actingAs($student)
            ->putJson('/api/profile', [
                'first_name' => 'Updated',
                'last_name' => 'Student',
                'suffix' => 'Jr.',
                'start_date' => '2026-10-01',
                'end_date' => '2027-01-01',
                'work_days' => [1, 3, 5],
                'expected_hours_per_day' => 7.5,
                'current_password' => 'secret-password',
                'password' => 'NewStrongPassword1!',
                'password_confirmation' => 'NewStrongPassword1!',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Updated Student Jr.')
            ->assertJsonPath('user.first_name', 'Updated')
            ->assertJsonPath('user.last_name', 'Student')
            ->assertJsonPath('user.suffix', 'Jr.')
            ->assertJsonPath('user.email', $student->email)
            ->assertJsonPath('internship.start_date', '2026-10-01')
            ->assertJsonPath('internship.end_date', '2027-01-01')
            ->assertJsonPath('internship.work_days', [1, 3, 5])
            ->assertJsonPath('internship.expected_hours_per_day', 7.5);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => 'Updated Student Jr.',
            'first_name' => 'Updated',
            'last_name' => 'Student',
            'suffix' => 'Jr.',
        ]);
        $this->assertSame('2026-10-01', $internship->refresh()->start_date->toDateString());
        $this->assertSame('2027-01-01', $internship->refresh()->end_date->toDateString());
        $this->assertSame([1, 3, 5], $internship->refresh()->work_days);
        $this->assertSame(450, $internship->refresh()->expected_daily_minutes);
        $this->assertTrue(Hash::check('NewStrongPassword1!', $student->refresh()->password));
    }

    public function test_profile_validates_schedule_settings(): void
    {
        $student = User::factory()->create();
        Internship::factory()->create(['student_id' => $student->id]);

        $this->stateful()->actingAs($student)
            ->putJson('/api/profile', [
                'name' => 'Updated Student',
                'start_date' => '2026-10-01',
                'end_date' => '2027-01-01',
                'work_days' => [],
                'expected_hours_per_day' => 24.5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['work_days', 'expected_hours_per_day']);
    }

    public function test_profile_password_change_requires_the_current_password(): void
    {
        $student = User::factory()->create([
            'password' => Hash::make('secret-password'),
        ]);
        Internship::factory()->create(['student_id' => $student->id]);

        foreach ([null, 'wrong-password'] as $currentPassword) {
            $payload = [
                'name' => 'Updated Student',
                'start_date' => '2026-10-01',
                'end_date' => '2027-01-01',
                'password' => 'NewStrongPassword1!',
                'password_confirmation' => 'NewStrongPassword1!',
            ];
            if ($currentPassword !== null) {
                $payload['current_password'] = $currentPassword;
            }

            $this->stateful()->actingAs($student)
                ->putJson('/api/profile', $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('current_password');
        }

        $this->assertTrue(Hash::check('secret-password', $student->refresh()->password));
    }

    public function test_profile_password_must_use_the_registration_password_policy(): void
    {
        $student = User::factory()->create();
        Internship::factory()->create(['student_id' => $student->id]);

        $this->stateful()->actingAs($student)
            ->putJson('/api/profile', [
                'name' => 'Updated Student',
                'start_date' => '2026-10-01',
                'end_date' => '2027-01-01',
                'current_password' => 'password',
                'password' => 'weakpassword!',
                'password_confirmation' => 'weakpassword!',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_profile_requires_target_end_date_after_start_date(): void
    {
        $student = User::factory()->create();
        Internship::factory()->create(['student_id' => $student->id]);

        $this->stateful()->actingAs($student)
            ->putJson('/api/profile', [
                'name' => 'Updated Student',
                'start_date' => '2026-10-02',
                'end_date' => '2026-10-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->stateful()->actingAs($user)->postJson('/api/logout')->assertNoContent();

        $this->stateful()->getJson('/api/user')->assertUnauthorized();
    }
}
