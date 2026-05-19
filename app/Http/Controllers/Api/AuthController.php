<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $token = $this->authService->login(
                email:    $request->validated('email'),
                password: $request->validated('password'),
            );

            return response()->json([
                'token' => $token->plainTextToken,
                'user'  => [
                    'id'    => $token->accessToken->tokenable->id,
                    'name'  => $token->accessToken->tokenable->name,
                    'email' => $token->accessToken->tokenable->email,
                ],
            ], Response::HTTP_OK);

        } catch (AuthenticationException $e) {
            throw ValidationException::withMessages([
                'email' => [$e->getMessage()],
            ]);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
