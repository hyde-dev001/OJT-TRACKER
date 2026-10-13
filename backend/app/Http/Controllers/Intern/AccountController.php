<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeInternPasswordRequest;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function changePassword(ChangeInternPasswordRequest $request): JsonResponse
    {
        $intern = $request->user();
        $intern->password = $request->validated('password');
        $intern->must_change_password = false;
        $intern->save();

        return response()->json([
            'user' => [
                'id' => $intern->id,
                'role' => $intern->role,
                'must_change_password' => $intern->must_change_password,
            ],
        ]);
    }
}
