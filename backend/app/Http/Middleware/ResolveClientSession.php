<?php

namespace App\Http\Middleware;

use App\Models\PlatformClient;
use App\Services\Tenancy\TenantDatabaseManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ResolveClientSession
{
    public function __construct(private TenantDatabaseManager $databases) {}

    public function handle(Request $request, Closure $next)
    {
        // 1. Only login can choose a client; subsequent requests use the server-side session.
        $login = $request->is('api/login');
        $client = null;
        if ($login) {
            $request->validate(['client_code' => ['nullable', 'string', 'max:50']]);
            if ($request->filled('client_code')) {
                $client = PlatformClient::query()->where('client_code', $request->input('client_code'))->first();
                if (! $client || ($client->provisioning_status !== 'ready' || $client->maintenance_at)) {
                    throw ValidationException::withMessages(['login' => ['The provided credentials are incorrect.']]);
                }
                abort_unless($request->hasSession(), 422, 'Client login requires a browser session.');
            }
        } elseif ($request->hasSession() && $request->session()->has('auth_client_id')) {
            $client = PlatformClient::query()->find($request->session()->get('auth_client_id'));
            abort_unless($client, 401, 'The client session is no longer available.');
            abort_unless((int) $request->session()->get('auth_client_version') === $client->session_version, 401, 'Please sign in again.');
            abort_if($request->is('api/platform/*'), 403);
        }

        if (! $client && ! $login) {
            return $next($request);
        }
        // 2. Discard cached guards: user 1 in one database is not user 1 in another.
        Auth::forgetGuards();
        try {
            return $client
                ? $this->databases->run($client, fn () => $next($request))
                : $next($request);
        } finally {
            Auth::forgetGuards();
        }
    }
}
