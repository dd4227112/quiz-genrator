<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionClarification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionClarification>
 */
class QuestionClarificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'user_id' => User::factory(),
            'user_message' => 'Why is that the answer?',
            'ai_response' => 'Because of the reasons explained above.',
        ];
    }
}
