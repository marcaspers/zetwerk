<?php

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;

class RecipePolicy
{
    public function update(User $user, Recipe $content): bool
    {
        return $user->id === $content->user_id;
    }

    public function delete(User $user, Recipe $content): bool
    {
        return $user->id === $content->user_id;
    }
}
