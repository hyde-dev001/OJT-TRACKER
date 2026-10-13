<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Internship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InternAccountSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_intern_login_reports_that_the_temporary_password_must_change(): void
    {
        $intern = User::factory()->create([
            'role' => 'intern',
            'must_change_password' => true,
            'password' => 'TemporaryPass1!',
        ]);

        $this->stateful()->postJson('/api/login', [
            'email' => $intern->email,
            'password' => 'TemporaryPass1!',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'intern')
            ->assertJsonPath('user.must_change_password', true)
            ->assertJsonMissingPath('user.password');
    }

    public function test_internship_access_is_blocked_until_the_intern_changes_the_password(): void
    {
        $company = Company::factory()->create();
        $intern = User::factory()->create([
            'role' => 'intern',
            'must_change_password' => true,
        ]);
        Internship::factory()->create([
            'student_id' => $intern->id,
            'company_id' => $company->id,
            'required_minutes' => null,
        ]);

        $this->internRequest($intern)
            ->getJson('/api/intern/internship')
            ->assertForbidden();
        $this->internRequest($intern)
            ->putJson('/api/intern/internship', ['required_hours' => 500])
            ->assertForbidden();
    }

    public function test_intern_can_change_the_temporary_password_and_clear_the_setup_flag(): void
    {
        $intern = User::factory()->create([
            'role' => 'intern',
            'must_change_password' => true,
            'password' => 'TemporaryPass1!',
        ]);

        $this->internRequest($intern)
            ->putJson('/api/intern/password', [
                'current_password' => 'TemporaryPass1!',
                'password' => 'PermanentPassword2!',
                'password_confirmation' => 'PermanentPassword2!',
            ])
            ->assertOk()
            ->assertJsonPath('user.role', 'intern')
            ->assertJsonPath('user.must_change_password', false)
            ->assertJsonMissingPath('user.password');

        $this->assertTrue(Hash::check('PermanentPassword2!', $intern->refresh()->password));
        $this->assertFalse($intern->must_change_password);
    }

    public function test_intern_password_change_rejects_an_incorrect_current_password(): void
    {
        $intern = User::factory()->create([
            'role' => 'intern',
            'must_change_password' => true,
            'password' => 'TemporaryPass1!',
        ]);

        $this->internRequest($intern)
            ->putJson('/api/intern/password', [
                'current_password' => 'WrongTemporaryPass1!',
                'password' => 'PermanentPassword2!',
                'password_confirmation' => 'PermanentPassword2!',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue($intern->refresh()->must_change_password);
        $this->assertTrue(Hash::check('TemporaryPass1!', $intern->password));
    }

    public function test_intern_must_replace_the_temporary_password_with_a_different_password(): void
    {
        $intern = User::factory()->create([
            'role' => 'intern',
            'must_change_password' => true,
            'password' => 'TemporaryPass1!',
        ]);

        $this->internRequest($intern)
            ->putJson('/api/intern/password', [
                'current_password' => 'TemporaryPass1!',
                'password' => 'TemporaryPass1!',
                'password_confirmation' => 'TemporaryPass1!',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_intern_can_view_an_unset_target_without_a_progress_percentage(): void
    {
        [$intern] = $this->internshipContext(requiredMinutes: null);

        $this->internRequest($intern)
            ->getJson('/api/intern/internship')
            ->assertOk()
            ->assertJsonPath('data.required_minutes', null)
            ->assertJsonPath('data.required_hours', null)
            ->assertJsonPath('data.progress.required_minutes', null)
            ->assertJsonPath('data.progress.required_hours', null)
            ->assertJsonPath('data.progress.remaining_minutes', null)
            ->assertJsonPath('data.progress.remaining_hours', null)
            ->assertJsonPath('data.progress.percentage', null);
    }

    public function test_intern_can_set_and_update_only_required_hours(): void
    {
        [$intern, $internship] = $this->internshipContext(requiredMinutes: null);

        $this->internRequest($intern)
            ->putJson('/api/intern/internship', [
                'required_hours' => 500,
                'start_date' => '2030-01-01',
                'end_date' => '2030-12-31',
                'work_days' => [7],
                'expected_hours_per_day' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.required_hours', 500)
            ->assertJsonPath('data.required_minutes', 30000)
            ->assertJsonPath('data.start_date', '2026-11-02')
            ->assertJsonPath('data.work_days', [1, 3, 5])
            ->assertJsonPath('data.expected_hours_per_day', 7.5);

        $this->internRequest($intern)
            ->putJson('/api/intern/internship', ['required_hours' => 600])
            ->assertOk()
            ->assertJsonPath('data.required_minutes', 36000);

        $this->assertSame('2026-11-02', $internship->refresh()->start_date->toDateString());
        $this->assertSame([1, 3, 5], $internship->work_days);
        $this->assertSame(450, $internship->expected_daily_minutes);
    }

    public function test_intern_required_hours_must_be_a_whole_number_in_the_allowed_range(): void
    {
        [$intern] = $this->internshipContext(requiredMinutes: null);

        foreach ([0, 10001, 500.5] as $hours) {
            $this->internRequest($intern)
                ->putJson('/api/intern/internship', ['required_hours' => $hours])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('required_hours');
        }
    }

    /** @return array{0: User, 1: Internship} */
    private function internshipContext(?int $requiredMinutes = null): array
    {
        $company = Company::factory()->create();
        $intern = User::factory()->create([
            'role' => 'intern',
            'must_change_password' => false,
        ]);
        $internship = Internship::factory()->create([
            'student_id' => $intern->id,
            'company_id' => $company->id,
            'required_minutes' => $requiredMinutes,
            'start_date' => '2026-11-02',
            'end_date' => '2027-02-28',
            'work_days' => [1, 3, 5],
            'expected_daily_minutes' => 450,
        ]);

        return [$intern, $internship];
    }

    private function internRequest(User $intern): self
    {
        return $this->stateful()->actingAs($intern);
    }
}
