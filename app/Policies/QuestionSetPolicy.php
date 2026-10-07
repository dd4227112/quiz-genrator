<?php

namespace App\Policies;

use App\Enums\QuestionSetStatus;
use App\Models\QuestionSet;
use App\Models\User;

class QuestionSetPolicy
{
    public function view(User $user, QuestionSet $set): bool
    {
        return $set->user_id === $user->id;
    }

    public function regenerate(User $user, QuestionSet $set): bool
    {
        return $this->view($user, $set) && $set->status === QuestionSetStatus::Failed;
    }

    public function delete(User $user, QuestionSet $set): bool
    {
        return $this->view($user, $set);
    }
}
