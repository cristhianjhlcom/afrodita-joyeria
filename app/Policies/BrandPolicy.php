<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Brand $brand): bool
    {
        return $user->isAdmin();
    }

    public function toggleWhitelist(User $user): bool
    {
        return $user->isAdmin();
    }
}
