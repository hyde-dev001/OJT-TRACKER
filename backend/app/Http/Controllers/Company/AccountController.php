<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $accountHolder = $request->user();
        $accountHolder->password = $request->validated('password');
        $accountHolder->save();

        return response()->json(['status' => 'password_updated']);
    }
}
