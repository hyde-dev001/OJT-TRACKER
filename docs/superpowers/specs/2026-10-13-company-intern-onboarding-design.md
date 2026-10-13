# Company Registration and Intern Account Setup

- **Status:** Approved for implementation
- **Date:** 2026-10-13
- **Scope:** First slice of the company-managed OJT workflow

## Purpose

Add a company account path while preserving student self-registration as an independent personal tracker. A company account holder can create intern accounts and provide their initial login credentials in person. Company-managed interns enter the OJT hours required by their school; the company account holder sets the placement dates and work schedule.

This slice establishes account ownership and onboarding. Live attendance, company-assigned tasks and requirements, proof uploads, and review actions are later slices.

## Agreed product rules

- The app has two independent paths in the same application:
  - A self-registered student continues to use the current personal tracker.
  - A company-created intern uses a company-managed OJT account.
- A self-registered account is not automatically linked to a company. If the same person needs both account types, each account uses a different email address.
- A company has one account holder. No separate HR role, additional staff seats, or administrator approval flow is introduced.
- Company registration is self-service and activates immediately. The company name and account holder identity are self-reported.
- A company account holder may create multiple intern accounts and can access records for that company only.
- Company-created intern accounts use a temporary password selected by the company account holder. The intern must replace it at first sign-in.
- The company account holder can reset a password for an intern in their company by setting a new temporary password; the intern must replace it at next sign-in.
- The intern supplies the required OJT hours from school during initial setup and can update that value later.
- The company account holder sets and may update the internship start date, target end date, recurring work days, and expected hours per day.
- Each side can update only the OJT values assigned to it above.
- Company-managed attendance can be recorded before the intern enters a required-hours target, but a progress percentage cannot be shown until the target exists.
- Company-managed OJT records do not certify official school or company attendance or completion.

## Users and account ownership

### Company account holder

The account holder registers a company account using:

- company name;
- account holder name;
- unique email address;
- password and confirmation.

Company account passwords follow the existing registration password policy.

The new company account is active after successful registration. No external company verification is required in this project slice.

The company account holder creates each intern account with the intern's first and last name, optional suffix, age, unique email, and a temporary password. Suffix choices match student registration (`Jr.`, `Sr.`, `II`, `III`, `IV`, or `V`). Age is required as a whole number from 1 through 120. The account holder gives the password to the intern in person. The app stores only the password hash and never returns the password after account creation.

The account holder also sets the company's placement data for that intern:

- OJT start date;
- target end date later than the start date;
- one or more ISO work days (Monday = 1 through Sunday = 7);
- expected hours per work day, from 0.5 through 24.

The existing project's date, weekday, and expected-hours validation rules apply.
The temporary password follows the existing password policy: at least 12
characters, mixed case, a number, and a symbol. The account holder's role and
company ownership are assigned by the backend, not accepted as user-selected
registration fields.

If an intern forgets their password, the company account holder can set a new
temporary password for that intern and provide it in person. The intern must
replace it at the next sign-in. The reset revokes the intern's active sessions;
the account holder cannot view the current or newly set password after saving
it. Password reset uses the same password policy as intern account creation.

### Company-managed intern

At the first successful sign-in with the temporary password, the intern must choose a permanent password before using the account. The permanent password follows the existing registration password policy.

After changing the password, the intern enters the required OJT hours from school. The intern can update this target later. Until the target is set, the account remains in an initial-setup state: the company can see that setup is incomplete, and any later attendance records may be stored, but progress percentage is unavailable.

Required hours must be a whole number from 1 through 10,000, matching the
current student registration limits. The backend stores the value as integer
minutes.

The company-managed intern account is associated with one company-managed internship. It can view its own hours and assigned work items in later slices, but cannot create or edit company-owned attendance, tasks, or requirements.

### Self-registered student

The existing student registration and personal tracker remain available and continue their current behavior. Self-registered students are not converted or linked to a company-managed account. A company that needs a separate managed account for the same person must use another email address because account emails are globally unique.

## Initial workflows

### Company registration

1. The user selects company registration from the public registration choices.
2. The user enters company and account-holder details.
3. The backend validates and creates the company and its account holder atomically.
4. The account holder is signed in and routed to the company workspace.

### Company creates an intern account

1. The company account holder enters the intern's name, email, and a temporary password.
2. The account holder enters the placement dates and company work schedule.
3. The backend validates the unique email and schedule, then creates the intern account and company-owned internship in one transaction.
4. The intern receives the temporary password in person.
5. On first sign-in, the intern must replace the temporary password and enter required OJT hours.
6. The company account holder can see whether the intern has completed the initial setup.

If the intern's email already belongs to a self-registered account, company account creation fails with a field-level email error. The company must use a different email; the existing personal account remains separate.

### Company resets an intern password

1. The company account holder selects an intern belonging to their company and sets a new temporary password.
2. The backend verifies company ownership, validates the password, stores its hash, requires a password change at next sign-in, and revokes the intern's active sessions.
3. The account holder gives the temporary password to the intern in person; the password is not returned or shown again after saving.

## Authorization and data protection

- Backend authorization is authoritative; frontend route guards only improve navigation.
- Company requests derive company ownership from the authenticated account. A submitted `company_id` or intern identifier must never allow access to another company's records.
- Company account holders can create and view only their own interns and company-owned internship data.
- Company account holders can reset passwords only for interns belonging to their own company.
- A company-managed intern can view and update only their own required-hours target and profile password.
- A company-managed intern cannot access the personal-tracker's write actions for work logs, tasks, or requirements.
- Personal student records keep their existing ownership and behavior.
- Registration and account-creation validation errors are field-level and do not expose internal exception details.
- Temporary passwords are never logged or returned after submission. The intern is forced to replace the password on first sign-in.

## Scope boundaries

This slice includes:

- company self-registration and company account ownership;
- company-created intern accounts;
- first-sign-in password replacement;
- company-initiated intern password reset with forced change at next sign-in;
- student-entered required OJT hours;
- company-owned placement dates and schedule;
- server-side ownership checks;
- preserving the current independent student registration flow.

Later slices add:

1. live company time-in/time-out, one session per intern per work day, company-entered break corrections, and student read-only hours;
2. company-created tasks and requirements, private image/PDF submissions, resubmission after change requests, and company approval.

This slice does not add additional company staff, an HR role, an administrator or approval role, company verification, account linking, email invitations or email-based password recovery, attendance recording, task assignment, proof upload, or review endpoints.

## Acceptance criteria

1. Existing student registration still creates a personal tracker and preserves its current student workflow.
2. A user can register a company account and is routed to the company workspace after registration.
3. A company account holder can create multiple intern accounts with unique emails and company-owned placement data.
4. Company intern creation requires an age from 1 through 120 and supports the same optional suffix values as student registration.
5. The company name is visible in the company account holder's profile menu and the intern's OJT account setup.
6. A company account holder cannot view or modify another company's interns or records, including by changing request identifiers.
7. An intern account cannot be created with an email already used by a personal or company-managed account.
8. An intern signing in with a temporary password must replace it before using the company-managed student workspace.
9. After replacing the temporary password, the intern can enter and later update required OJT hours.
10. The company account holder can set and update placement dates, recurring work days, and expected daily hours; the intern cannot change those values.
11. An intern who has not entered required OJT hours is clearly marked as needing initial setup, and no progress percentage is shown until the target exists.
12. A self-registered student account remains independent and is never silently linked to a company.
13. A company account holder can reset only their own intern's password; the reset invalidates active sessions and requires the intern to choose a new password at next sign-in.

## Verification plan

Backend feature coverage should verify company registration, company-created intern setup, unique email handling, forced first-login password replacement, company-scoped password resets, active-session invalidation, field ownership, and cross-company authorization. Existing student registration tests should continue to pass. Frontend coverage should verify the registration choice, role-based routing, first-login setup, password reset, and recoverable validation states. Browser verification should cover company registration through intern first sign-in and a company-initiated reset while checking that the personal student flow remains usable.

## Known risks

- Company identity is self-reported and unverified. A later product decision would be needed to add email verification or an authorized verifier.
- An account holder knows the temporary password until the intern changes it. The forced first-login password change reduces the time this remains true.
- The company account holder knows a reset password until the intern changes it; the forced next-sign-in change and session revocation limit that access.
- Separate account histories for one person require separate email addresses and cannot be merged in this design.
