<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'auth' => 'Unable to sign in. Please try again.',
            ]);
        }

        $request->session()->regenerate();

        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $nameAttributes = $this->nameAttributes($data);

        $user = DB::transaction(function () use ($data, $nameAttributes): User {
            $user = User::create(array_merge($nameAttributes, [
                'email' => $data['email'],
                'password' => $data['password'],
                'email_verified_at' => now(),
            ]));

            $user->studentInternships()->create([
                'required_minutes' => $data['required_hours'] * 60,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'work_days' => $data['work_days'],
                'expected_daily_minutes' => (int) round(((float) $data['expected_hours_per_day']) * 60),
                'status' => 'active',
            ]);

            return $user;
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $this->userPayload($user)], 201);
    }

    public function checkEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if (User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'An account already exists with this email.',
            ]);
        }

        return response()->json(['available' => true]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $nameAttributes = $this->nameAttributes($data);

        $internship = DB::transaction(function () use ($data, $nameAttributes, $user) {
            $attributes = $nameAttributes;

            if (filled($data['password'] ?? null)) {
                $attributes['password'] = $data['password'];
            }

            $user->update($attributes);
            $internship = $user->currentInternship()->firstOrFail();
            $internshipAttributes = [
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
            ];

            if (array_key_exists('work_days', $data)) {
                $internshipAttributes['work_days'] = $data['work_days'];
            }

            if (array_key_exists('expected_hours_per_day', $data)) {
                $internshipAttributes['expected_daily_minutes'] = (int) round(((float) $data['expected_hours_per_day']) * 60);
            }

            $internship->update($internshipAttributes);

            return $internship->refresh();
        });

        return response()->json([
            'user' => $this->userPayload($user->refresh()),
            'internship' => [
                'start_date' => $internship->start_date?->toDateString(),
                'end_date' => $internship->end_date?->toDateString(),
                'work_days' => $internship->work_days ?? [],
                'expected_hours_per_day' => $internship->expected_daily_minutes === null
                    ? null
                    : round($internship->expected_daily_minutes / 60, 2),
            ],
        ]);
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::forgetGuards();

        return response()->noContent();
    }

    /** @param array<string, mixed> $data
     * @return array{name: string, first_name: ?string, last_name: ?string, suffix: ?string}
     */
    private function nameAttributes(array $data): array
    {
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $suffix = trim((string) ($data['suffix'] ?? ''));

        if ($firstName === '' && $lastName === '' && filled($data['name'] ?? null)) {
            $parts = preg_split('/\s+/', trim((string) $data['name']), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if ($parts !== [] && $suffix === '' && in_array(end($parts), ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'], true)) {
                $suffix = array_pop($parts);
            }

            $firstName = array_shift($parts) ?? '';
            $lastName = implode(' ', $parts);
        }

        return [
            'name' => trim(implode(' ', array_filter([$firstName, $lastName, $suffix]))),
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'suffix' => $suffix !== '' ? $suffix : null,
        ];
    }

    /** @return array{id: int, name: string, first_name: ?string, last_name: ?string, suffix: ?string, email: string} */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'suffix' => $user->suffix,
            'email' => $user->email,
        ];
    }
}
