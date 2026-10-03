<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    use RespondsWithApiEnvelope;

    public function store(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $login = $data['login'] ?? $data['email'];
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials = [
            $field => $login,
            'password' => $data['password'],
            'status' => 'active',
            'platform_user_id' => null,
        ];

        // Feature flow step 1: validate username/email credentials before a session is regenerated.
        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Feature flow step 2: regenerate browser sessions when Sanctum has attached one.
        if ($request->hasSession()) {
            $request->session()->regenerate();
            $client = app(TenantDatabaseManager::class)->current();
            if ($client) {
                $request->session()->put('auth_client_id', $client->id);
                $request->session()->put('auth_client_version', $client->session_version);
            } else {
                $request->session()->forget(['auth_client_id', 'auth_client_version']);
            }
        }

        $user = Auth::guard('web')->user()->load('roles.permissions', 'scopedCompanies');

        // Feature flow step 3: return the role and permission payload needed by the Nuxt shell.
        return $this->success('Authenticated successfully.', [
            'user' => new UserResource($user),
        ]);
    }

    public function destroy(): JsonResponse
    {
        Auth::guard('web')->logout();

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        return $this->success('Logged out successfully.');
    }
}
