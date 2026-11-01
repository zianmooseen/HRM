<?php

namespace App\Services\Tenancy;

use App\Models\PlatformClient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;

class DiscoverLogin
{
    public function matches(Request $request): array
    {
        // Discovery precedes tenant selection and the normal tenant-scoped API limiter.
        $login = trim($request->input('login') ?: $request->input('email'));
        $ipKey = 'login-discovery:'.hash('sha256', (string) $request->ip());
        $key = $ipKey.':'.app(ClientLoginDirectory::class)->fingerprint($login);
        abort_if(RateLimiter::tooManyAttempts($key, 20) || RateLimiter::tooManyAttempts($ipKey, 60), 429, 'Too many sign-in attempts. Please try again shortly.');
        RateLimiter::hit($key, 60);
        RateLimiter::hit($ipKey, 60);
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $selected = $request->input('organization_id');
        $matches = [];
        if (! $selected || $selected === 'platform') {
            $user = User::on(config('tenancy.central_connection'))->where($field, $login)
                ->where('status', 'active')->whereNull('platform_user_id')->whereNull('deleted_at')->first();
            if ($user && Hash::check($request->input('password'), $user->password)
                && ($user->hasRole('super_admin') || SchemaLayout::legacyTestSchema())) {
                $matches[] = ['id' => 'platform', 'name' => 'Platform administration', 'client' => null];
            }
        }
        if ($selected !== 'platform') {
            $query = DB::connection(config('tenancy.central_connection'))->table('client_login_identifiers as identifiers')
                ->join('platform_clients as clients', 'clients.id', '=', 'identifiers.platform_client_id')
                ->join('companies as companies', 'companies.id', '=', 'clients.company_id')
                ->where('identifiers.identifier_hash', app(ClientLoginDirectory::class)->fingerprint($login))
                ->where('clients.provisioning_status', 'ready')->whereNull('clients.maintenance_at')
                ->whereNull('companies.deleted_at')
                ->when($selected, fn ($query) => $query->where('clients.uuid', $selected))
                ->select('identifiers.platform_client_id', 'identifiers.user_id')->distinct()->limit(51)->get();
            // Bound password-hash work for heavily reused identifiers. An explicit company selection stays cheap.
            if ($query->count() > 50) {
                throw ValidationException::withMessages(['login' => ['Use your work email to sign in.']]);
            }
            $manager = app(TenantDatabaseManager::class);
            $unavailable = false;
            foreach ($query as $candidate) {
                $client = PlatformClient::find($candidate->platform_client_id);
                if (! $client) {
                    continue;
                }
                try {
                    $valid = $manager->run($client, function () use ($candidate, $field, $login, $request) {
                        // Directory rows are hints only: recheck identity, account state and password in the tenant.
                        $user = User::whereKey($candidate->user_id)->where($field, $login)
                            ->where('status', 'active')->whereNull('platform_user_id')->whereNull('deleted_at')->first();

                        return $user && Hash::check($request->input('password'), $user->password);
                    });
                } catch (\Throwable $exception) {
                    report($exception);
                    $unavailable = true;
                    $valid = false;
                }
                if ($valid) {
                    $matches[] = ['id' => $client->uuid, 'name' => $client->company->name, 'client' => $client];
                }
            }
            // Do not silently pick a different company when a potentially matching tenant cannot be checked.
            abort_if($unavailable, 503, 'Sign-in is temporarily unavailable. Please try again later.');
        }

        return $matches;
    }
}
