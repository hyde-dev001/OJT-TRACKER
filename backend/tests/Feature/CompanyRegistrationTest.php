<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_register_and_is_signed_in_immediately(): void
    {
        $this->stateful()->postJson('/api/company/register', [
            'company_name' => 'Northwind Studio',
            'first_name' => 'Jamie',
            'last_name' => 'Santos',
            'email' => 'owner@northwind.example',
            'password' => 'StrongPassword1!',
            'password_confirmation' => 'StrongPassword1!',
        ])
            ->assertCreated()
            ->assertJsonPath('user.name', 'Jamie Santos')
            ->assertJsonPath('user.email', 'owner@northwind.example')
            ->assertJsonPath('user.role', 'company')
            ->assertJsonPath('user.company_name', 'Northwind Studio')
            ->assertJsonPath('user.must_change_password', false)
            ->assertJsonMissingPath('user.password');

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.company_name', 'Northwind Studio');

        $accountHolder = User::where('email', 'owner@northwind.example')->firstOrFail();

        $this->assertAuthenticatedAs($accountHolder);
        $this->assertDatabaseHas('companies', [
            'name' => 'Northwind Studio',
            'account_holder_id' => $accountHolder->id,
        ]);
        $this->assertSame($accountHolder->id, Company::firstOrFail()->accountHolder->id);
    }

    public function test_company_registration_rejects_an_email_used_by_a_student(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->stateful()->postJson('/api/company/register', [
            'company_name' => 'Northwind Studio',
            'first_name' => 'Jamie',
            'last_name' => 'Santos',
            'email' => 'existing@example.com',
            'password' => 'StrongPassword1!',
            'password_confirmation' => 'StrongPassword1!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('companies', 0);
    }
}
