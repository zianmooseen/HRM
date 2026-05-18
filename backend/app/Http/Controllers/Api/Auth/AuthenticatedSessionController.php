<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    use RespondsWithApiEnvelope;

    public function store(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        // Feature flow step 1: validate credentials before a session is regenerated.
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Feature flow step 2: regenerate the session to prevent fixation.
        $request->session()->regenerate();

        $user = $request->user()->load('roles.permissions', 'scopedCompanies');

        // Feature flow step 3: return the role and permission payload needed by the Nuxt shell.
        return $this->success('Authenticated successfully.', [
            'user' => new UserResource($user),
        ]);
    }

    public function destroy(): JsonResponse
    {
        Auth::guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return $this->success('Logged out successfully.');
    }
}
