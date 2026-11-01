<?php

namespace App\Services\Tenancy;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClientLoginDirectory
{
    public function fingerprint(string $identifier): string
    {
        return hash_hmac('sha256', trim($identifier), config('app.key'));
    }

    public function sync(User $user): void
    {
        $manager = app(TenantDatabaseManager::class);
        $client = $manager->current();
        if (! $client || $user->getConnection()->getName() !== $manager->connectionName($client)) {
            return;
        }
        $connection = DB::connection(config('tenancy.central_connection'));
        $connection->transaction(function () use ($connection, $client, $user) {
            $connection->table('client_login_identifiers')->where('platform_client_id', $client->id)->where('user_id', $user->id)->delete();
            if (! $user->exists || $user->status !== 'active' || $user->platform_user_id !== null || $user->deleted_at) {
                return;
            }
            $hashes = collect([$user->username, $user->email])->filter()->map(fn ($value) => $this->fingerprint($value))->unique();
            foreach ($hashes as $hash) {
                $connection->table('client_login_identifiers')->insert([
                    'platform_client_id' => $client->id, 'user_id' => $user->id, 'identifier_hash' => $hash,
                ]);
            }
        });
    }
}
