<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Tenancy\ClientLoginDirectory;

class UserObserver
{
    public function saved(User $user): void
    {
        app(ClientLoginDirectory::class)->sync($user);
    }

    public function deleted(User $user): void
    {
        app(ClientLoginDirectory::class)->sync($user);
    }
}
