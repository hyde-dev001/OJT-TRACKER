<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompanyAccountPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_account_holder_can_change_their_own_password(): void
    {
        $companyAccount = User::factory()->create([
            'role' => 'company',
            'password' => 'CurrentPassword1!',
        ]);

        $this->stateful()->actingAs($companyAccount)
            ->putJson('/api/company/password', [
                'current_password' => 'CurrentPassword1!',
                'password' => 'NewCompanyPassword2!',
                'password_confirmation' => 'NewCompanyPassword2!',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('NewCompanyPassword2!', $companyAccount->refresh()->password));
    }

    public function test_company_password_change_rejects_an_incorrect_current_password(): void
    {
        $companyAccount = User::factory()->create([
            'role' => 'company',
            'password' => 'CurrentPassword1!',
        ]);

        $this->stateful()->actingAs($companyAccount)
            ->putJson('/api/company/password', [
                'current_password' => 'WrongPassword1!',
                'password' => 'NewCompanyPassword2!',
                'password_confirmation' => 'NewCompanyPassword2!',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('CurrentPassword1!', $companyAccount->refresh()->password));
    }

    public function test_non_company_accounts_cannot_change_a_company_password(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->stateful()->actingAs($student)
            ->putJson('/api/company/password', [
                'current_password' => 'password',
                'password' => 'NewCompanyPassword2!',
                'password_confirmation' => 'NewCompanyPassword2!',
            ])
            ->assertForbidden();
    }
}
