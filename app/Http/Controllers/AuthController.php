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
        if (! Auth::attempt($request->validated(), $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'The provided credentials are incorrect.']);
        }

        $request->session()->regenerate();

        return response()->json(['user' => $this->userPayload($request)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

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
