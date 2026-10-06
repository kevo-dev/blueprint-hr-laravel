<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->validated())) {
            throw ValidationException::withMessages(['email' => 'The provided credentials are incorrect.']);
        }

        $user = $request->user();
        $user->tokens()->where('name', 'blueprint-hr-web')->delete();
        $token = $user->createToken('blueprint-hr-web')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($request),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out successfully.']);
    }

    private function userPayload(Request $request): mixed
    {
        return $request->user()->load([
            'tenant',
            'employee.department',
            'employee.branch',
            'employee.designation',
            'employee.grade',
            'employee.employmentType',
        ]);
    }
}
