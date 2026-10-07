<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    public function view(User $user, Question $question): bool
    {
        return $question->questionSet()->where('user_id', $user->id)->exists();
    }
}
