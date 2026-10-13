<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Internship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class CompanyInternManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_create_an_intern_with_its_own_placement_and_a_temporary_password(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $this->companyRequest($company)->postJson('/api/company/interns', [
            ...$this->internPayload([
                'suffix' => 'Jr.',
                'start_date' => '2026-11-01',
                'expected_hours_per_day' => 8,
            ]),
            'company_id' => $otherCompany->id,
            'role' => 'company',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Taylor Lee Jr.')
            ->assertJsonPath('data.suffix', 'Jr.')
            ->assertJsonPath('data.email', 'taylor.lee@example.com')
            ->assertJsonPath('data.setup_complete', false)
            ->assertJsonMissingPath('data.password');

        $intern = User::where('email', 'taylor.lee@example.com')->firstOrFail();

        $this->assertSame('intern', $intern->role);
        $this->assertSame('Jr.', $intern->suffix);
        $this->assertSame(20, $intern->age);
        $this->assertTrue($intern->must_change_password);
        $this->assertTrue(Hash::check('TemporaryPass1!', $intern->password));
        $internship = $intern->currentInternship()->firstOrFail();
        $this->assertSame($company->id, $internship->company_id);
        $this->assertNull($internship->required_minutes);
        $this->assertSame('2026-11-01', $internship->start_date->toDateString());
        $this->assertSame('2027-02-28', $internship->end_date->toDateString());
        $this->assertSame(480, $internship->expected_daily_minutes);
    }

    public function test_company_intern_list_is_scoped_to_its_own_interns(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $ownIntern = User::factory()->create(['role' => 'intern', 'must_change_password' => true]);
        $otherIntern = User::factory()->create(['role' => 'intern', 'must_change_password' => false]);
        Internship::factory()->create([
            'student_id' => $ownIntern->id,
            'company_id' => $company->id,
            'required_minutes' => null,
        ]);
        Internship::factory()->create([
            'student_id' => $otherIntern->id,
            'company_id' => $otherCompany->id,
        ]);

        $this->companyRequest($company)
            ->getJson('/api/company/interns')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownIntern->id)
            ->assertJsonPath('data.0.setup_complete', false)
            ->assertJsonMissing(['id' => $otherIntern->id]);
    }

    public function test_company_cannot_create_an_intern_with_an_existing_account_email(): void
    {
        $company = Company::factory()->create();
        User::factory()->create(['email' => 'taylor.lee@example.com']);

        $this->companyRequest($company)
            ->postJson('/api/company/interns', $this->internPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_company_can_update_only_placement_fields_for_its_intern(): void
    {
        $company = Company::factory()->create();
        $intern = User::factory()->create(['role' => 'intern']);
        $internship = Internship::factory()->create([
            'student_id' => $intern->id,
            'company_id' => $company->id,
            'required_minutes' => 30000,
        ]);

        $this->companyRequest($company)
            ->putJson("/api/company/interns/{$intern->id}/placement", [
                ...$this->placementPayload(),
                'required_hours' => 1000,
            ])
            ->assertOk()
            ->assertJsonPath('data.start_date', '2026-11-02')
            ->assertJsonPath('data.expected_hours_per_day', 7.5);

        $this->assertSame(30000, $internship->refresh()->required_minutes);
        $this->assertSame([1, 3, 5], $internship->work_days);
    }

    public function test_company_cannot_update_or_reset_another_company_intern(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $otherIntern = User::factory()->create(['role' => 'intern']);
        $otherInternship = Internship::factory()->create([
            'student_id' => $otherIntern->id,
            'company_id' => $otherCompany->id,
        ]);

        $this->companyRequest($otherCompany)
            ->putJson("/api/company/interns/{$otherIntern->id}/placement", $this->placementPayload())
            ->assertOk();
        $this->companyRequest($company)
            ->putJson("/api/company/interns/{$otherIntern->id}/placement", $this->placementPayload())
            ->assertNotFound();
        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$otherIntern->id}/password-reset", [
                'password' => 'NewTemporaryPass1!',
                'password_confirmation' => 'NewTemporaryPass1!',
            ])
            ->assertNotFound();

        $this->assertSame('2026-11-02', $otherInternship->refresh()->start_date->toDateString());
        $this->assertFalse($otherIntern->refresh()->must_change_password);
    }

    public function test_password_reset_hashes_the_new_temporary_password_and_revokes_sessions(): void
    {
        $company = Company::factory()->create();
        $intern = User::factory()->create([
            'role' => 'intern',
            'must_change_password' => false,
            'password' => Hash::make('OldPassword1!'),
        ]);
        Internship::factory()->create([
            'student_id' => $intern->id,
            'company_id' => $company->id,
        ]);
        DB::table('sessions')->insert([
            'id' => 'intern-session',
            'user_id' => $intern->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/password-reset", [
                'password' => 'NewTemporaryPass1!',
                'password_confirmation' => 'NewTemporaryPass1!',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $intern->id)
            ->assertJsonMissingPath('data.password');

        $this->assertTrue(Hash::check('NewTemporaryPass1!', $intern->refresh()->password));
        $this->assertTrue($intern->must_change_password);
        $this->assertDatabaseMissing('sessions', ['id' => 'intern-session']);
    }

    public function test_intern_creation_rolls_back_when_internship_creation_fails(): void
    {
        $company = Company::factory()->create();
        $email = 'atomic-intern@example.com';
        Internship::creating(static function (): void {
            throw new RuntimeException('Simulated internship failure.');
        });

        try {
            $this->withoutExceptionHandling()
                ->companyRequest($company)
                ->postJson('/api/company/interns', $this->internPayload(['email' => $email]));
            $this->fail('The simulated internship failure should be raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated internship failure.', $exception->getMessage());
        } finally {
            Internship::flushEventListeners();
        }

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_company_intern_creation_validates_password_and_placement(): void
    {
        $company = Company::factory()->create();

        foreach ([
            ['end_date' => '2026-10-31', 'field' => 'end_date'],
            ['work_days' => [8], 'field' => 'work_days.0'],
            ['expected_hours_per_day' => 24.5, 'field' => 'expected_hours_per_day'],
            ['suffix' => 'PhD', 'field' => 'suffix'],
            ['password' => 'weak', 'password_confirmation' => 'weak', 'field' => 'password'],
        ] as $index => $invalidInput) {
            $field = $invalidInput['field'];
            unset($invalidInput['field']);

            $this->companyRequest($company)
                ->postJson('/api/company/interns', $this->internPayload([
                    'email' => "invalid-intern-{$index}@example.com",
                    ...$invalidInput,
                ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }
    }

    public function test_company_intern_age_is_required_and_must_be_a_whole_number_from_1_to_120(): void
    {
        $company = Company::factory()->create();
        $missingAge = $this->internPayload(['email' => 'missing-age@example.com']);
        unset($missingAge['age']);

        $this->companyRequest($company)
            ->postJson('/api/company/interns', $missingAge)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('age');

        foreach ([0, 121, 18.5] as $index => $age) {
            $this->companyRequest($company)
                ->postJson('/api/company/interns', $this->internPayload([
                    'email' => "invalid-age-{$index}@example.com",
                    'age' => $age,
                ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('age');
        }
    }

    /** @return array<string, mixed> */
    private function internPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Taylor',
            'last_name' => 'Lee',
            'age' => 20,
            'email' => 'taylor.lee@example.com',
            'password' => 'TemporaryPass1!',
            'password_confirmation' => 'TemporaryPass1!',
            ...$this->placementPayload(),
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function placementPayload(): array
    {
        return [
            'start_date' => '2026-11-02',
            'end_date' => '2027-02-28',
            'work_days' => [1, 3, 5],
            'expected_hours_per_day' => 7.5,
        ];
    }

    private function companyRequest(Company $company): self
    {
        return $this->stateful()->actingAs($company->accountHolder);
    }
}
