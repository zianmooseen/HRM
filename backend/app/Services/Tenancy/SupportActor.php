<?php

namespace App\Services\Tenancy;

use App\Models\PlatformClient;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportActor
{
    public function forAdministrator(PlatformClient $client, User $actor): User
    {
        return Cache::lock('support-actor:'.$client->uuid.':'.$actor->id, 30)->block(5, function () use ($client, $actor) {
            return DB::transaction(function () use ($client, $actor) {
                $support = User::query()->where('platform_user_id', $actor->id)->first();
                if (! $support) {
                    $support = new User;
                    $support->forceFill([
                        'platform_user_id' => $actor->id,
                        'name' => $actor->name.' (platform support)',
                        'username' => 'platform-support-'.Str::uuid(),
                        'email' => Str::uuid().'@support.invalid',
                        'password' => Str::random(64), 'status' => 'support',
                    ])->save();
                    $role = Role::query()->where('slug', 'super_admin')->whereNull('company_id')->firstOrFail();
                    $support->roles()->attach($role->id, ['company_id' => $client->company_id, 'scope' => 'company']);
                }

                return $support;
            });
        });
    }
}
