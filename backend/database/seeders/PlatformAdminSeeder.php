<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Production administrators require explicitly configured credentials.
        $local = app()->environment(['local', 'testing']);
        $username = env('SEED_ADMIN_USERNAME', $local ? 'sys.admin' : null);
        $email = env('SEED_ADMIN_EMAIL', $local ? 'sys.admin@example.local' : null);
        $password = env('SEED_ADMIN_PASSWORD', $local ? 'sys.admin' : null);
        if (! $username || ! $email || ! $password) {
            return;
        }
        $user = User::firstOrCreate(['username' => $username], [
            'name' => env('SEED_ADMIN_NAME', 'System Admin'), 'email' => $email,
            'password' => $password, 'status' => 'active',
        ]);
        $user->roles()->syncWithoutDetaching([Role::where('slug', 'super_admin')->firstOrFail()->id => ['scope' => 'global']]);
    }
}
