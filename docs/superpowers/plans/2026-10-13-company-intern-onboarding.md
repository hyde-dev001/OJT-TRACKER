# Company Registration and Intern Account Setup Implementation Plan

> **For agentic workers:** After user approval, execute this plan sequentially with `superpowers:executing-plans`. Do not commit changes unless the user explicitly approves a commit.

**Goal:** Add the approved company registration and company-managed intern onboarding flow while preserving the existing personal student tracker.

**Architecture:** Keep Laravel as the authority for account roles, validation, company ownership, password state, and internship data. Reuse Sanctum session authentication, existing internship endpoints and resources where their rules fit, the centralized Axios client, and the existing Vue registration and UI patterns. Add only the company, intern, and role-aware pieces needed for Slice 1.

**Tech Stack:** Laravel 12, PHP 8.2+, Sanctum, Eloquent, MySQL/MariaDB, Vue 3, JavaScript, Vue Router, Pinia, Axios, Tailwind CSS, PHPUnit, Vitest, and Vue Test Utils.

**Spec:** `docs/superpowers/specs/2026-10-13-company-intern-onboarding-design.md` (approved by the user on 2026-10-13)

## Global Constraints

- Implement Slice 1 only: company registration, company-created intern accounts, initial and reset temporary passwords, intern-owned required OJT hours, and company-owned placement dates and schedule.
- Keep the existing `/register` personal student flow and its behavior; new users default to the `student` account type.
- Use only three account types: `student`, `company`, and `intern`; each company has one account holder.
- Company account holders create intern accounts manually and give temporary passwords in person; interns change temporary passwords at their next sign-in.
- Never log or return a temporary password after the request that creates or resets it.
- Do not add invitation links, email delivery or password recovery, administrator approval, company verification, more company roles, attendance, task assignment, proof upload, or review workflows.
- Keep email addresses globally unique. Derive company ownership from the signed-in account and never trust a submitted `company_id` or intern ID for authorization.
- Keep personal student and company-managed intern accounts independent; the same person uses a different email address for each account.
- Reuse the existing password policy: at least 12 characters, uppercase and lowercase letters, a number, and a symbol.
- Required OJT hours are a whole number from 1 through 10,000 and are stored as integer minutes; a missing target has no progress percentage.
- Placement dates require an end date after the start date. Work days are unique ISO weekdays 1–7; expected daily hours are 0.5–24 and are stored as integer minutes.
- Preserve current date behavior: do not impose a fixed work week or reject a start date solely because it is in the past or future.
- The backend remains authoritative. The intern cannot update company-owned dates or schedule, and company account holders cannot update intern-owned required hours.
- Preserve existing records and behavior. Add migrations; do not rewrite or remove existing student data.
- Do not add a dependency, commit, or implement any later slice.

## Review Focus

- **Duplicate or conflicting email:** one email cannot create both a personal and company-managed account; pin with company-registration and intern-creation tests.
- **Forged company or intern identifiers:** a company account holder must not read, update, or reset an intern from another company; pin with direct-ID authorization tests.
- **Temporary password handling:** password values must never appear in API responses, and reset must remove the intern's database sessions; pin with creation and reset tests.
- **Forced setup bypass:** an intern with `must_change_password` cannot update OJT hours or access student routes before changing the temporary password; pin with middleware/API tests.
- **Owner-only OJT fields and missing targets:** interns can update required hours only, company account holders can update placement data only, and a null target has no percentage; pin with API and resource tests.

---

## File Structure

### Backend

- Add `backend/app/Models/Company.php` for the account-holder and company-internship relationships.
- Add one migration for the `companies` table, the reintroduced `users.role`, the intern password-change flag, `internships.company_id`, and nullable `internships.required_minutes`.
- Update `User` and `Internship` relationships and factories; preserve `student` as the default role.
- Add role and forced-password-change middleware, then group existing student routes and new company/intern routes by role.
- Add company registration and company intern-management actions using Laravel Form Requests and a DB transaction for each multi-record creation.
- Reuse `Student\InternshipController`, `UpdateInternshipRequest`, `InternshipPolicy`, and `InternshipResource` for an intern's own required-hours target where their current behavior already matches the spec.

### Frontend

- Keep `RegisterView.vue` as personal student registration and link to a separate `CompanyRegisterView.vue`.
- Use existing `services/api.js`, `services/ojt.js`, `PasswordRequirements.vue`, and `DatePickerField.vue` rather than adding clients or dependencies.
- Add one `CompanyInternsView.vue` for the account holder's intern list and create/update/reset actions.
- Add one `InternAccountView.vue` for forced password replacement, required-hours setup and updates, read-only placement details, and later password changes.
- Make routing and shared navigation account-type aware while leaving student links and student pages unchanged for student users.

### Documentation

- Align `PROJECT_CONTEXT.md`, `DESIGN.md`, `AGENTS.md`, and `README.md` with the approved two-path product and the Slice 1 boundary.

---

### Task 1: Add company ownership and optional required-hours schema

**Files:**

- Create: `backend/database/migrations/2026_10_13_000001_add_company_intern_onboarding.php`
- Create: `backend/app/Models/Company.php`
- Create: `backend/database/factories/CompanyFactory.php`
- Modify: `backend/app/Models/User.php`
- Modify: `backend/app/Models/Internship.php`
- Modify: `backend/database/factories/UserFactory.php`
- Modify: `backend/database/factories/InternshipFactory.php`
- Modify: `backend/app/Http/Resources/InternshipResource.php`
- Test: `backend/tests/Feature/AuthTest.php`
- Test: `backend/tests/Feature/InternshipTest.php`

**Interfaces:**

- `Company` belongs to one account-holder `User` and has many company-owned `Internship` records.
- `CompanyFactory` creates one `company` account-holder user so company tests can build isolated ownership fixtures.
- `Internship.company_id` is nullable for existing personal student records; company-created internship rows set it from the authenticated company's relationship.
- `Internship.required_minutes` is nullable until a company-managed intern enters school-required hours.
- `Internship::progressSummary()` returns `percentage: null` and null required/remaining values when no required-hours target exists. Existing student payloads remain unchanged because their target is required.

- [ ] **Step 1: Add failing schema and missing-target tests.** Assert that a new user defaults to `student`, and an internship with `required_minutes = null` reports null required/remaining values and a null progress percentage rather than zero.

```php
public function test_missing_required_hours_has_no_progress_percentage(): void
{
    $internship = Internship::factory()->create(['required_minutes' => null]);

    $this->assertNull($internship->progressSummary()['percentage']);
}

public function test_new_users_default_to_personal_student_role(): void
{
    $this->assertSame('student', User::factory()->create()->role);
}
```

- [ ] **Step 2: Run the narrow backend tests and confirm they fail for the missing role/schema behavior.**

Run from `backend`: `php artisan test --filter=InternshipTest` and `php artisan test --filter=AuthTest`

- [ ] **Step 3: Add the schema and relationships.** Reintroduce `users.role` with default `student`; add `users.must_change_password` defaulting to false; create `companies` with a unique `account_holder_id`; add nullable `internships.company_id`; make `required_minutes` nullable. Add foreign keys and indexes. Do not alter or delete existing student rows. Update the models and factories, and make resource/progress output explicitly null when the target is missing.

```php
$requiredMinutes = $this->required_minutes;
if ($requiredMinutes === null) {
    return [
        'completed_minutes' => $completedMinutes,
        'completed_hours' => round($completedMinutes / 60, 2),
        'required_minutes' => null,
        'required_hours' => null,
        'remaining_minutes' => null,
        'remaining_hours' => null,
        'percentage' => null,
    ];
}
```

Place this early return in the existing `progressSummary()` after `$completedMinutes` is calculated and before `$requiredMinutes` is cast to int. Update its return annotation and make `InternshipResource.required_hours` null when `required_minutes` is null. Keep the existing calculation for the non-null branch; do not create a second progress formula.

- [ ] **Step 4: Run `php artisan test --filter=InternshipTest` and `php artisan test --filter=AuthTest` from `backend`; confirm the new assertions pass and the existing student registration assertions still pass.**

---

### Task 2: Add company registration and enforce account roles

**Files:**

- Create: `backend/app/Http/Requests/RegisterCompanyRequest.php`
- Create: `backend/app/Http/Middleware/EnsureAccountRole.php`
- Modify: `backend/app/Http/Controllers/AuthController.php`
- Modify: `backend/app/Models/User.php`
- Modify: `backend/bootstrap/app.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/tests/Feature/AuthTest.php`
- Modify: `backend/tests/Feature/AuthorizationTest.php`
- Test: `backend/tests/Feature/CompanyRegistrationTest.php`

**Interfaces:**

- Throttled `POST /api/company/register` accepts `company_name`, account-holder name fields, `email`, `password`, and `password_confirmation`; it creates the company and `company` user in one transaction and signs the account holder in.
- `GET /api/user` and login/register responses include `user.role` and `user.must_change_password`, but never password data.
- `account-role:student`, `account-role:company`, and `account-role:intern` are server-side route guards. Existing `/api/student/*` and `/api/profile` routes require `student`.
- Existing `POST /api/register` remains the personal student path and does not accept a client-selected role.

- [ ] **Step 1: Add failing tests for immediate company registration, global email uniqueness, and student role preservation.** Include a client-supplied `role: company` in a personal student registration test and assert the resulting account remains `student`.

```php
$this->stateful()->postJson('/api/company/register', [
    'company_name' => 'Northwind Studio',
    'first_name' => 'Jamie',
    'last_name' => 'Santos',
    'email' => 'owner@northwind.example',
    'password' => 'StrongPassword1!',
    'password_confirmation' => 'StrongPassword1!',
])->assertCreated()
    ->assertJsonPath('user.role', 'company')
    ->assertJsonMissingPath('user.password');
```

- [ ] **Step 2: Run `php artisan test --filter=CompanyRegistrationTest` from `backend`; confirm the new registration test fails because the route does not exist.**

- [ ] **Step 3: Add `RegisterCompanyRequest`, the throttled transactional registration action, role data in the auth payload, and the role middleware alias. Assign roles explicitly in trusted backend code; do not accept them from request data. Place company routes under `account-role:company`, intern routes under `account-role:intern`, and the existing student route group under `account-role:student`.

```php
use App\Http\Controllers\Company\InternController;

Route::middleware(['auth:sanctum', 'account-role:company'])
    ->prefix('company')->group(function (): void {
        Route::get('/interns', [InternController::class, 'index']);
    });
```

Keep the company name self-reported, activate the account immediately, and do not add verification, email, or administrator workflows.

- [ ] **Step 4: Run `php artisan test --filter=CompanyRegistrationTest`, `php artisan test --filter=AuthTest`, and `php artisan test --filter=AuthorizationTest` from `backend`; confirm company registration works and personal student routes reject company/intern accounts.**

---

### Task 3: Add company intern creation, placement updates, and password reset

**Files:**

- Create: `backend/app/Http/Requests/StoreCompanyInternRequest.php`
- Create: `backend/app/Http/Requests/UpdateCompanyPlacementRequest.php`
- Create: `backend/app/Http/Requests/ResetCompanyInternPasswordRequest.php`
- Create: `backend/app/Http/Controllers/Company/InternController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/CompanyInternManagementTest.php`

**Interfaces:**

- `GET /api/company/interns` returns only the authenticated account holder's interns, company placement data, and a derived setup-complete state (`must_change_password` is false and `required_minutes` is set).
- `POST /api/company/interns` accepts intern name, unique email, temporary password, start/end dates, work days, and expected hours per day. It creates an `intern` user and company-owned internship atomically, with no required-hours target yet.
- `PUT /api/company/interns/{intern}/placement` updates only start/end dates, work days, and expected daily hours for an intern in the authenticated company.
- `POST /api/company/interns/{intern}/password-reset` accepts a new temporary password, sets `must_change_password = true`, and deletes that intern's rows from the existing `sessions` table. The project session configuration defaults to that database-backed table.
- Never log or return a submitted password; derive `company_id` from the signed-in account for all writes.

- [ ] **Step 1: Add failing feature tests for intern creation, transaction rollback, required field validation, duplicate email rejection, placement ownership, password hashing, reset-session deletion, and cross-company IDs.** Include a forged `company_id` in one create request and assert the saved internship still belongs to the authenticated company.

```php
$company = Company::factory()->create();
$accountHolder = $company->accountHolder;
$otherCompany = Company::factory()->create();

$response = $this->stateful()->actingAs($accountHolder)->postJson('/api/company/interns', [
    'first_name' => 'Taylor',
    'last_name' => 'Lee',
    'email' => 'taylor.lee@example.com',
    'password' => 'TemporaryPass1!',
    'password_confirmation' => 'TemporaryPass1!',
    'start_date' => '2026-11-01',
    'end_date' => '2027-02-28',
    'work_days' => [1, 2, 3, 4, 5],
    'expected_hours_per_day' => 8,
    'company_id' => $otherCompany->id,
]);

$response->assertCreated()->assertJsonMissingPath('data.password');

$intern = User::where('email', 'taylor.lee@example.com')->firstOrFail();
$this->assertDatabaseHas('internships', [
    'student_id' => $intern->id,
    'company_id' => $company->id,
    'required_minutes' => null,
]);
```

- [ ] **Step 2: Run `php artisan test --filter=CompanyInternManagementTest` from `backend`; confirm these tests fail before the controller/routes exist.**

- [ ] **Step 3: Implement the company-scoped controller and Form Requests.** Use the account-holder relationship to find the company; create the intern user and internship in one DB transaction; set role and password-change state server-side; convert expected hours to integer minutes; normalize unique ISO weekdays; reject end dates not after start dates. For placement updates, query through the authenticated company's internships so a different company's intern returns 404. On reset, update the password and flag, then delete matching `sessions.user_id` rows.

```php
DB::table('sessions')->where('user_id', $intern->id)->delete();
```

- [ ] **Step 4: Run `php artisan test --filter=CompanyInternManagementTest` from `backend`; confirm an inserted session row is removed after reset and the response contains no password.**

---

### Task 4: Add intern password and required-hours setup endpoints

**Files:**

- Create: `backend/app/Http/Requests/ChangeInternPasswordRequest.php`
- Create: `backend/app/Http/Middleware/EnsureInternPasswordChanged.php`
- Create: `backend/app/Http/Controllers/Intern/AccountController.php`
- Modify: `backend/bootstrap/app.php`
- Modify: `backend/routes/api.php`
- Reuse: `backend/app/Http/Controllers/Student/InternshipController.php`
- Reuse: `backend/app/Http/Requests/UpdateInternshipRequest.php`
- Reuse: `backend/app/Policies/InternshipPolicy.php`
- Reuse: `backend/app/Http/Resources/InternshipResource.php`
- Test: `backend/tests/Feature/InternAccountSetupTest.php`

**Interfaces:**

- `PUT /api/intern/password` requires `current_password`, `password`, and `password_confirmation`; it updates only the authenticated intern's password and clears `must_change_password`.
- `GET /api/intern/internship` returns the intern's own company-managed internship, including the company-owned placement values as read-only response fields.
- `PUT /api/intern/internship` reuses the existing required-hours-only request/controller action; it cannot accept or update placement fields.
- `intern.password-changed` blocks internship GET/PUT routes while `must_change_password` is true. The password change route remains available.
- Required hours can be set on first setup and changed later; the backend stores `required_hours * 60`.

- [ ] **Step 1: Add failing tests for forced password change, blocked hours updates before that change, valid and invalid required-hours values, and an intern's inability to change placement dates or schedule.** Also assert a null target serializes with null required/remaining values and a null percentage.

```php
$this->stateful()->actingAs($intern)
    ->putJson('/api/intern/internship', ['required_hours' => 500])
    ->assertForbidden();
```

- [ ] **Step 2: Run `php artisan test --filter=InternAccountSetupTest` from `backend`; confirm the new route/state assertions fail.**

- [ ] **Step 3: Add the intern password action and middleware.** Apply the middleware only to intern internship routes, keep the password endpoint available while a change is required, and reuse the current internship update implementation so only required hours can be changed.

```php
use App\Http\Controllers\Intern\AccountController;
use App\Http\Controllers\Student\InternshipController;

Route::middleware(['auth:sanctum', 'account-role:intern'])->prefix('intern')->group(function (): void {
    Route::put('/password', [AccountController::class, 'changePassword']);
    Route::middleware('intern.password-changed')->group(function (): void {
        Route::get('/internship', [InternshipController::class, 'show']);
        Route::put('/internship', [InternshipController::class, 'update']);
    });
});
```

- [ ] **Step 4: Run `php artisan test --filter=InternAccountSetupTest`, `php artisan test --filter=InternshipTest`, and `php artisan test --filter=AuthorizationTest` from `backend`; confirm the intern can update only their target and students retain their existing endpoint behavior.**

---

### Task 5: Add company registration and role-aware frontend routing

**Files:**

- Create: `frontend/src/views/CompanyRegisterView.vue`
- Create: `frontend/src/views/CompanyRegisterView.test.js`
- Modify: `frontend/src/views/RegisterView.vue`
- Modify: `frontend/src/views/RegisterView.test.js`
- Modify: `frontend/src/views/LoginView.vue`
- Modify: `frontend/src/views/LoginView.test.js`
- Modify: `frontend/src/stores/auth.js`
- Modify: `frontend/src/stores/auth.test.js`
- Modify: `frontend/src/router/index.js`
- Modify: `frontend/src/router/index.test.js`
- Modify: `frontend/src/App.vue`
- Modify: `frontend/src/App.test.js`
- Modify: `frontend/src/components/StudentAccountMenu.vue`
- Modify: `frontend/src/components/StudentAccountMenu.test.js`
- Modify: `frontend/src/components/MobileNavigationDrawer.vue`
- Create: `frontend/src/components/MobileNavigationDrawer.test.js`

**Interfaces:**

- Public routes remain `/register` for personal student signup and add `/register/company` for company signup.
- `authStore.registerCompany(details)` posts to `/company/register`, stores the returned user, and marks authentication bootstrapped.
- Successful login routes `student` users to `/student/overview`, `company` users to `/company/interns`, and `intern` users to `/intern/account`.
- Existing internal student deep links remain usable only by `student` users.
- Route metadata and shared navigation reflect `user.role`; route guards are navigation help only, with the Laravel role middleware remaining authoritative.

- [ ] **Step 1: Add failing frontend tests for the company-registration link, company registration submission, role-based login destinations, role-protected routes, and student regression.** Keep the existing student registration request assertion unchanged.

```js
expect(routerPush).toHaveBeenCalledWith('/company/interns')
```

- [ ] **Step 2: Run `npm.cmd run test -- --run src/views/RegisterView.test.js src/views/CompanyRegisterView.test.js src/views/LoginView.test.js src/router/index.test.js src/stores/auth.test.js` from `frontend`; confirm the new role-flow expectations fail.**

- [ ] **Step 3: Add the separate company registration form using existing password feedback and the centralized API/auth store. Add route roles and role-aware default redirects. Update the shared desktop/mobile navigation so each account type sees only its Slice 1 destinations; retain the existing student routes and labels for student users.**

- [ ] **Step 4: Run the same targeted Vitest command plus `npm.cmd run test -- --run src/App.test.js src/components/StudentAccountMenu.test.js src/components/MobileNavigationDrawer.test.js` from `frontend`; confirm new routing works and existing student navigation remains intact.**

---

### Task 6: Add the company intern-management page

**Files:**

- Create: `frontend/src/views/company/CompanyInternsView.vue`
- Create: `frontend/src/views/company/CompanyInternsView.test.js`
- Modify: `frontend/src/router/index.js`
- Modify: `frontend/src/services/ojt.js`
- Modify: `frontend/src/services/ojt.test.js`
- Reuse: `frontend/src/components/DatePickerField.vue`
- Reuse: `frontend/src/components/PasswordRequirements.vue`
- Reuse: `frontend/src/components/PageHeader.vue`

**Interfaces:**

- `listCompanyInterns()` calls `GET /company/interns`.
- `createCompanyIntern(payload)` calls `POST /company/interns`.
- `updateCompanyPlacement(internId, payload)` calls `PUT /company/interns/{internId}/placement`.
- `resetCompanyInternPassword(internId, payload)` calls `POST /company/interns/{internId}/password-reset`.
- `/company/interns` shows the account holder's own intern list, setup state, placement data, and controls to create, update placement, and reset a password.

- [ ] **Step 1: Add failing service and view tests.** Verify the service uses the centralized Axios client and the view displays loading, empty, recoverable error, field validation, and success states for create/update/reset.

```js
const payload = {
  first_name: 'Taylor',
  last_name: 'Lee',
  email: 'taylor.lee@example.com',
  password: 'TemporaryPass1!',
  password_confirmation: 'TemporaryPass1!',
  start_date: '2026-11-01',
  end_date: '2027-02-28',
  work_days: [1, 2, 3, 4, 5],
  expected_hours_per_day: 8,
}

expect(api.post).toHaveBeenCalledWith('/company/interns', payload)
expect(wrapper.text()).toContain('Needs initial setup')
```

- [ ] **Step 2: Run `npm.cmd run test -- --run src/services/ojt.test.js src/views/company/CompanyInternsView.test.js` from `frontend`; confirm the new tests fail before the page/service functions exist.**

- [ ] **Step 3: Add the small OJT service calls and the company list/form page. Clear password fields after a successful save; show no password after creation or reset. Keep the UI focused on the intern account, setup state, dates, work days, expected hours, and reset action.**

- [ ] **Step 4: Run the targeted command again from `frontend`; confirm the service and manager workflow tests pass.**

---

### Task 7: Add the company-managed intern account page

**Files:**

- Create: `frontend/src/views/intern/InternAccountView.vue`
- Create: `frontend/src/views/intern/InternAccountView.test.js`
- Modify: `frontend/src/router/index.js`
- Modify: `frontend/src/services/ojt.js`
- Modify: `frontend/src/services/ojt.test.js`
- Modify: `frontend/src/stores/auth.js`
- Modify: `frontend/src/stores/auth.test.js`
- Reuse: `frontend/src/components/PasswordRequirements.vue`

**Interfaces:**

- `authStore.changeInternPassword(payload)` calls `PUT /intern/password` and updates the auth-store user's `must_change_password` value from the response.
- `getInternInternship()` calls `GET /intern/internship`; `updateInternRequiredHours(requiredHours)` calls `PUT /intern/internship` with only `required_hours`.
- `/intern/account` first requires a password change when `must_change_password` is true. After saving it, the page loads the internship and presents the required-hours form.
- After initial setup, the same page allows the intern to update required hours and change their profile password. Company-set dates, work days, and expected hours are displayed read-only. Do not add attendance, task, or requirement pages in this slice.

- [ ] **Step 1: Add failing tests for the ordered password-then-hours flow, target updates, read-only placement values, and validation feedback.** Verify the internship request is not made while the account still requires a password change.

```js
expect(api.put.mock.invocationCallOrder[0]).toBeLessThan(api.get.mock.invocationCallOrder[0])
expect(api.put).toHaveBeenCalledWith('/intern/internship', { required_hours: 500 })
```

- [ ] **Step 2: Run `npm.cmd run test -- --run src/views/intern/InternAccountView.test.js src/services/ojt.test.js src/stores/auth.test.js` from `frontend`; confirm the new tests fail before the view/service methods exist.**

- [ ] **Step 3: Implement the account page with sequential first-login panels, read-only placement details, and editable required-hours/password forms after setup. Use field-level errors, a disabled busy state, and success/recoverable error feedback.**

- [ ] **Step 4: Run the targeted Vitest command again from `frontend`; confirm interns can complete setup and later update their own required hours/password.**

---

### Task 8: Align documentation and run complete verification

**Files:**

- Modify: `PROJECT_CONTEXT.md`
- Modify: `DESIGN.md`
- Modify: `AGENTS.md`
- Modify: `README.md`
- Reference: `docs/superpowers/specs/2026-10-13-company-intern-onboarding-design.md`

- [ ] **Step 1: Update product documentation to describe independent personal student and company-managed onboarding paths.** State that company account holders are self-registered, one per company, manually create interns, set placement schedule, and can reset intern passwords. State that the intern changes temporary passwords and owns required hours, and that company-managed records do not certify official attendance or completion. Keep attendance, tasks, proof, and review outside this Slice 1 implementation.

- [ ] **Step 2: Update the AI guardrails and human README so they no longer claim the product is student-only or that roles are permanently removed.** Preserve the existing student workflow, current visual principles, verification commands, and no-commit policy.

- [ ] **Step 3: Run the complete Laravel and frontend checks using the repository's configured scripts.**

Run from `backend`: `php artisan test`

Run from `frontend`: `npm.cmd run test -- --run` and `npm.cmd run build`

Run from the repository root: `git diff --check`

- [ ] **Step 4: Walk the browser flow with the local app: register a company, create an intern, sign in with the temporary password, change it, set required hours, update the company placement, reset the intern password, confirm the old session is rejected, and sign in with the reset password. Then sign in through the existing personal student registration/login flow and confirm it still opens the student overview.**

- [ ] **Step 5: Review the final diff against the approved spec and confirm no invitations, extra roles, verification, attendance, task, proof, or review code was added. Do not commit.**

---

## Spec Coverage Self-Review

- Acceptance criteria 1 and 10 (preserve the independent student tracker): Tasks 2 and 5; regression tests in `AuthTest`, `RegisterView.test.js`, `LoginView.test.js`, and router tests.
- Acceptance criteria 2 (company registration and workspace): Tasks 2 and 5–6.
- Acceptance criteria 3 (multiple intern accounts and company placement data): Tasks 3 and 6.
- Acceptance criteria 4 (company isolation): Tasks 2–3, including direct-ID tests for list/update/reset.
- Acceptance criterion 5 (global unique email): Tasks 2–3.
- Acceptance criterion 6 (forced initial password change): Tasks 3–5 and 7; the backend middleware is authoritative.
- Acceptance criterion 7 (intern enters/updates required hours): Tasks 1 and 4, with UI in Task 7.
- Acceptance criterion 8 (company-owned placement values): Tasks 3 and 6; intern API and UI remain read-only for those fields.
- Acceptance criterion 9 (initial setup and no percentage without a target): Tasks 1, 3, 4, and 6–7.
- Acceptance criterion 11 (company-scoped reset, session invalidation, forced next password change): Tasks 3–4 and 6–7.
- No company verification, admin role, invitation, email password flow, or later feature slice is included.

## Execution Boundary

This file is an implementation plan only. No application code, migrations, frontend pages, tests, or product documentation are to be changed until the user approves this plan. No commit is authorized.
