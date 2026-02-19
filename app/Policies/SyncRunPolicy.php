<?php

namespace App\Policies;

use App\Models\SyncRun;
use App\Models\User;

class SyncRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, SyncRun $syncRun): bool
    {
        return $user->isAdmin();
    }

    public function trigger(User $user): bool
    {
        return $user->isAdmin();
    }
}
