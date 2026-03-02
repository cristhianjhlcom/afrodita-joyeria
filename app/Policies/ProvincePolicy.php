<?php

namespace App\Policies;

use App\Models\Province;
use App\Models\User;

class ProvincePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Province $province): bool
    {
        return $user->isAdmin();
    }
}
