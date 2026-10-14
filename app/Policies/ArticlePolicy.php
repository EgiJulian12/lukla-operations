<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Article;
use App\Models\User;

/**
 * Data induk artikel (matriks RBAC, Tim Sample).
 */
class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageArticles()
            || $role->canManageSamples()
            || $role->canConfigureSystem()
            || $role->canManageProduction()
            || $role->canManageRateCards()
            || $role->canManageMaterials()
            || $role->isClientFacing()
        );
    }

    public function view(User $user, Article $article): bool
    {
        return $user->client_id === null
            || (int) $user->client_id === (int) $article->client_id;
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Article $article): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Article $article): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageArticles());
    }
}
