<?php

namespace App\Actions;

use App\Contracts\QuestionGenerator;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\User;
use App\Services\AI\AIException;

class GradeQuestionAttempt
{
    public function __construct(private readonly QuestionGenerator $ai) {}

    /**
     * Grade a validated response and store it as the user's latest attempt.
     *
     * @param  array<string, mixed>  $response
     *
     * @throws AIException when AI grading of a free-text answer fails
     */
    public function handle(Question $question, User $user, array $response): QuestionAttempt
    {
        [$normalized, $result, $gradedBy] = match ($question->type) {
            QuestionType::TrueFalse => $this->trueFalse($question, $response),
            QuestionType::MultipleChoice => $this->multipleChoice($question, $response),
            QuestionType::Matching => $this->matching($question, $response),
            QuestionType::ShortAnswer => $this->shortAnswer($question, $response),
        };

        return $question->attempts()->create([
            'user_id' => $user->id,
            'response' => $normalized,
            'is_correct' => $result['is_correct'],
            'score' => $result['score'],
            'feedback' => $result['feedback'],
            'graded_by' => $gradedBy,
        ]);
    }

    private function trueFalse(Question $question, array $response): array
    {
        $value = filter_var($response['value'], FILTER_VALIDATE_BOOLEAN);

        return [['value' => $value], $this->binary($value === ($question->answer['value'] ?? null)), QuestionAttempt::GRADED_AUTOMATICALLY];
    }

    private function multipleChoice(Question $question, array $response): array
    {
        $key = strtoupper((string) $response['key']);

        return [['key' => $key], $this->binary($key === ($question->answer['key'] ?? null)), QuestionAttempt::GRADED_AUTOMATICALLY];
    }

    private function matching(Question $question, array $response): array
    {
        $correct = collect($question->answer['pairs'] ?? [])->pluck('right', 'left')
            ->mapWithKeys(fn ($right, $left) => [(string) $left => (string) $right]);

        $pairs = collect($response['pairs'])
            ->map(fn (array $pair) => ['left' => (string) $pair['left'], 'right' => (string) $pair['right']])
            ->sortBy('left', SORT_NATURAL)
            ->values();

        $total = $correct->count();
        $right = $pairs->filter(fn (array $pair) => $correct->get($pair['left']) === $pair['right'])->count();

        return [
            ['pairs' => $pairs->all()],
            [
                'is_correct' => $total > 0 && $right === $total,
                'score' => $total > 0 ? (int) round($right / $total * 100) : 0,
                'feedback' => "You matched {$right} of {$total} pairs correctly.",
            ],
            QuestionAttempt::GRADED_AUTOMATICALLY,
        ];
    }

    private function shortAnswer(Question $question, array $response): array
    {
        $text = trim((string) $response['text']);

        return [['text' => $text], $this->ai->gradeAnswer($question, $text), QuestionAttempt::GRADED_BY_AI];
    }

    /**
     * @return array{is_correct: bool, score: int, feedback: null}
     */
    private function binary(bool $correct): array
    {
        return ['is_correct' => $correct, 'score' => $correct ? 100 : 0, 'feedback' => null];
    }
}
