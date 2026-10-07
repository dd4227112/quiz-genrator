<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question_set_id' => QuestionSet::factory(),
            'type' => 'multiple_choice',
            'position' => 1,
            'prompt' => fake()->sentence().'?',
            'payload' => ['options' => [
                ['key' => 'A', 'text' => 'Option A'],
                ['key' => 'B', 'text' => 'Option B'],
                ['key' => 'C', 'text' => 'Option C'],
                ['key' => 'D', 'text' => 'Option D'],
            ]],
            'answer' => ['key' => 'B'],
            'explanation' => 'Because B is correct.',
        ];
    }

    public function trueFalse(bool $value = true): static
    {
        return $this->state([
            'type' => 'true_false',
            'payload' => [],
            'answer' => ['value' => $value],
        ]);
    }
}
