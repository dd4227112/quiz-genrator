<?php

namespace App\Contracts;

use App\Models\Question;
use App\Models\QuestionClarification;
use App\Models\QuestionSet;
use App\Services\AI\AIException;
use Illuminate\Support\Collection;

interface QuestionGenerator
{
    /**
     * Generate validated questions for the given set.
     *
     * Returns a list of questions, each shaped as
     * ['type' => string, 'prompt' => string, 'payload' => array, 'answer' => array, 'explanation' => string].
     * May return fewer questions than requested; never more.
     *
     * @return list<array{type: string, prompt: string, payload: array, answer: array, explanation: string}>
     *
     * @throws AIException when the provider fails before any usable output is produced.
     */
    public function generate(QuestionSet $set): array;

    /**
     * Answer a follow-up question about a single generated question.
     *
     * @param  Collection<int, QuestionClarification>  $history
     *
     * @throws AIException
     */
    public function clarify(Question $question, string $userMessage, Collection $history): string;

    /**
     * Grade a free-text answer against the question's model answer.
     *
     * @return array{is_correct: bool, score: int, feedback: string}
     *
     * @throws AIException
     */
    public function gradeAnswer(Question $question, string $userAnswer): array;
}
