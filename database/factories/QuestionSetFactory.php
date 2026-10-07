<?php

namespace Database\Factories;

use App\Enums\QuestionSetStatus;
use App\Models\Level;
use App\Models\QuestionSet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionSet>
 */
class QuestionSetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'topic' => fake()->randomElement(['Laravel Developer', 'Registered Nurse', 'AWS Solutions Architect']),
            'level_id' => Level::factory(),
            'question_types' => ['true_false', 'multiple_choice', 'matching', 'short_answer'],
            'requested_count' => 4,
            'additional_notes' => null,
            'status' => QuestionSetStatus::Pending,
            'error_message' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(['status' => QuestionSetStatus::Completed]);
    }

    public function failed(string $message = 'Something went wrong.'): static
    {
        return $this->state(['status' => QuestionSetStatus::Failed, 'error_message' => $message]);
    }
}
