<?php

namespace App\Actions;

use App\Contracts\QuestionGenerator;
use App\Enums\QuestionSetStatus;
use App\Models\QuestionSet;
use App\Services\AI\AIException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateQuestionsForSet
{
    public function __construct(private readonly QuestionGenerator $generator) {}

    public function handle(QuestionSet $set): void
    {
        try {
            $questions = $this->generator->generate($set);
        } catch (AIException $e) {
            $this->fail($set, $e->getMessage(), $e->context);

            return;
        } catch (Throwable $e) {
            $this->fail($set, 'Something went wrong while generating questions. Please try again.', [
                'exception' => $e::class,
                'reason' => $e->getMessage(),
            ]);

            return;
        }

        if ($questions === []) {
            $this->fail($set, 'The AI did not return any usable questions. Please try again.', [
                'reason' => 'zero valid questions after follow-up',
            ]);

            return;
        }

        $saved = count($questions);
        $note = $saved < $set->requested_count
            ? "Only {$saved} of {$set->requested_count} questions could be generated. You can generate a new set to get more."
            : null;

        DB::transaction(function () use ($set, $questions, $note) {
            $set->questions()->delete();

            $now = now();
            $set->questions()->insert(array_map(fn (array $q, int $index) => [
                'question_set_id' => $set->id,
                'type' => $q['type'],
                'position' => $index + 1,
                'prompt' => $q['prompt'],
                'payload' => json_encode((object) $q['payload']),
                'answer' => json_encode($q['answer']),
                'explanation' => $q['explanation'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $questions, array_keys($questions)));

            $set->update([
                'status' => QuestionSetStatus::Completed,
                'error_message' => $note,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function fail(QuestionSet $set, string $userMessage, array $context = []): void
    {
        Log::error('Question generation failed.', [
            'question_set_id' => $set->id,
            'user_message' => $userMessage,
            ...$context,
        ]);

        $set->update([
            'status' => QuestionSetStatus::Failed,
            'error_message' => $userMessage,
        ]);
    }
}
