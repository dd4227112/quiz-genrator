<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionAttempt>
 */
class QuestionAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'user_id' => User::factory(),
            'response' => ['key' => 'A'],
            'is_correct' => false,
            'score' => 0,
            'feedback' => null,
            'graded_by' => QuestionAttempt::GRADED_AUTOMATICALLY,
        ];
    }
}
