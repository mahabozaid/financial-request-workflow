<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\NewAccessToken;

final class AuthService
{
    /**
     * @throws AuthenticationException
     */
    public function login(string $email, string $password): NewAccessToken
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            throw new AuthenticationException('The provided credentials are incorrect.');
        }

        /** @var User $user */
        $user = Auth::user();

        return $user->createToken('api');
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
