<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetCompanyInternPasswordRequest;
use App\Http\Requests\StoreCompanyInternRequest;
use App\Http\Requests\UpdateCompanyPlacementRequest;
use App\Models\Company;
use App\Models\Internship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InternController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $internships = $this->companyFor($request->user())
            ->internships()
            ->whereHas('student', fn ($query) => $query->where('role', 'intern'))
            ->with('student')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $internships->map(fn (Internship $internship): array => $this->internPayload($internship))
                ->values(),
        ]);
    }

    public function store(StoreCompanyInternRequest $request): JsonResponse
    {
        $data = $request->validated();
        $company = $this->companyFor($request->user());

        $internship = DB::transaction(function () use ($company, $data): Internship {
            $intern = new User();
            $suffix = $data['suffix'] ?? null;
            $intern->fill([
                'name' => trim($data['first_name'].' '.$data['last_name'].' '.($suffix ?? '')),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'suffix' => $suffix,
                'age' => (int) $data['age'],
                'email' => $data['email'],
                'password' => $data['password'],
                'email_verified_at' => now(),
            ]);
            $intern->role = 'intern';
            $intern->must_change_password = true;
            $intern->save();

            return $intern->studentInternships()->create([
                'company_id' => $company->id,
                'required_minutes' => null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'work_days' => $data['work_days'],
                'expected_daily_minutes' => (int) round(((float) $data['expected_hours_per_day']) * 60),
                'status' => 'active',
            ])->load('student');
        });

        return response()->json(['data' => $this->internPayload($internship)], 201);
    }

    public function updatePlacement(UpdateCompanyPlacementRequest $request, User $intern): JsonResponse
    {
        $data = $request->validated();
        $internship = $this->internshipFor($this->companyFor($request->user()), $intern);
        $internship->update([
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'work_days' => $data['work_days'],
            'expected_daily_minutes' => (int) round(((float) $data['expected_hours_per_day']) * 60),
        ]);

        return response()->json(['data' => $this->internPayload($internship->refresh()->load('student'))]);
    }

    public function resetPassword(ResetCompanyInternPasswordRequest $request, User $intern): JsonResponse
    {
        $internship = $this->internshipFor($this->companyFor($request->user()), $intern);
        $password = $request->validated('password');

        DB::transaction(function () use ($internship, $password): void {
            $intern = $internship->student()->firstOrFail();
            $intern->password = $password;
            $intern->must_change_password = true;
            $intern->save();

            DB::table('sessions')->where('user_id', $intern->id)->delete();
        });

        return response()->json([
            'data' => $this->internPayload($internship->load('student')),
        ]);
    }

    private function companyFor(User $accountHolder): Company
    {
        return $accountHolder->company()->firstOrFail();
    }

    private function internshipFor(Company $company, User $intern): Internship
    {
        return $company->internships()
            ->where('student_id', $intern->id)
            ->whereHas('student', fn ($query) => $query->where('role', 'intern'))
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function internPayload(Internship $internship): array
    {
        $intern = $internship->student;

        return [
            'id' => $intern->id,
            'name' => $intern->name,
            'first_name' => $intern->first_name,
            'last_name' => $intern->last_name,
            'suffix' => $intern->suffix,
            'email' => $intern->email,
            'setup_complete' => ! $intern->must_change_password && $internship->required_minutes !== null,
            'start_date' => $internship->start_date?->toDateString(),
            'end_date' => $internship->end_date?->toDateString(),
            'work_days' => $internship->work_days ?? [],
            'expected_hours_per_day' => $internship->expected_daily_minutes === null
                ? null
                : round($internship->expected_daily_minutes / 60, 2),
        ];
    }
}
