<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function update(User $user, Review $content): bool
    {
        return $user->id === $content->user_id;
    }

    public function delete(User $user, Review $content): bool
    {
        return $user->id === $content->user_id;
    }
}
