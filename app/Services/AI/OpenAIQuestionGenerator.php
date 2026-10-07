<?php

namespace App\Services\AI;

use App\Contracts\QuestionGenerator;
use App\Models\Question;
use App\Models\QuestionClarification;
use App\Models\QuestionSet;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class OpenAIQuestionGenerator implements QuestionGenerator
{
    public function __construct(
        private readonly QuestionResponseValidator $validator,
        private readonly QuestionPromptBuilder $prompts,
    ) {}

    public function generate(QuestionSet $set): array
    {
        $set->loadMissing('level');

        $wanted = QuestionDistribution::distribute($set->requested_count, $set->question_types);
        $batchSize = (int) config('ai.batch_size', 15);

        $collected = [];
        $seenPrompts = [];

        // First pass: batches of up to $batchSize questions each.
        foreach (QuestionDistribution::batches($wanted, $batchSize) as $batch) {
            try {
                $this->runBatch($set, $batch, $collected, $seenPrompts);
            } catch (AIException $e) {
                // Nothing usable yet means the provider itself is failing: give up.
                if ($collected === []) {
                    throw $e;
                }
                $this->logWarning('Question batch failed; continuing with partial results.', $set, $e);
            }
        }

        // One follow-up round for whatever is still missing (truncation, invalid items, duplicates).
        $missing = $this->missingCounts($wanted, $collected);

        if ($missing !== []) {
            Log::info('Requesting follow-up questions to fill shortfall.', [
                'question_set_id' => $set->id,
                'missing' => $missing,
            ]);

            foreach (QuestionDistribution::batches($missing, $batchSize) as $batch) {
                try {
                    $this->runBatch($set, $batch, $collected, $seenPrompts);
                } catch (AIException $e) {
                    if ($collected === []) {
                        throw $e;
                    }
                    $this->logWarning('Follow-up batch failed; keeping partial results.', $set, $e);
                }
            }
        }

        return $this->orderByType($collected, array_keys($wanted));
    }

    public function clarify(Question $question, string $userMessage, Collection $history): string
    {
        $question->loadMissing(['questionSet.level', 'latestAttempt']);

        $messages = [
            ['role' => 'system', 'content' => $this->prompts->clarificationSystemPrompt($question)],
        ];

        foreach ($history as $exchange) {
            /** @var QuestionClarification $exchange */
            $messages[] = ['role' => 'user', 'content' => $exchange->user_message];
            $messages[] = ['role' => 'assistant', 'content' => $exchange->ai_response];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $result = $this->chat($messages, jsonMode: false);
        $content = trim($result['content']);

        if ($content === '') {
            throw AIException::invalidResponse('Empty clarification response (finish_reason: '.$result['finish_reason'].')');
        }

        return $content;
    }

    public function gradeAnswer(Question $question, string $userAnswer): array
    {
        $question->loadMissing('questionSet.level');

        $result = $this->chat([
            ['role' => 'system', 'content' => $this->prompts->gradingSystemPrompt($question)],
            ['role' => 'user', 'content' => $this->prompts->gradingUserPrompt($question, $userAnswer)],
        ], jsonMode: true);

        $decoded = json_decode(ResponseParser::stripFences($result['content']), true);

        $isCorrect = is_array($decoded) ? ($decoded['is_correct'] ?? null) : null;
        $score = is_array($decoded) ? ($decoded['score'] ?? null) : null;
        $feedback = is_array($decoded) && is_string($decoded['feedback'] ?? null) ? trim($decoded['feedback']) : '';

        if (! is_bool($isCorrect) || ! is_numeric($score) || $feedback === '') {
            throw AIException::invalidResponse('Unparseable grading response (finish_reason: '.$result['finish_reason'].')');
        }

        return [
            'is_correct' => $isCorrect,
            'score' => (int) max(0, min(100, round((float) $score))),
            'feedback' => $feedback,
        ];
    }

    /**
     * Request one batch and append the valid, non-duplicate questions to $collected,
     * never exceeding the per-type counts requested for this batch.
     *
     * @param  array<string, int>  $batch
     * @param  list<array>  $collected
     * @param  array<string, true>  $seenPrompts
     */
    private function runBatch(QuestionSet $set, array $batch, array &$collected, array &$seenPrompts): void
    {
        $avoid = array_column($collected, 'prompt');

        $result = $this->chat([
            ['role' => 'system', 'content' => $this->prompts->generationSystemPrompt($set)],
            ['role' => 'user', 'content' => $this->prompts->generationUserPrompt($set, $batch, $avoid)],
        ], jsonMode: true);

        $truncated = $result['finish_reason'] === 'length';
        if ($truncated) {
            Log::warning('AI response was truncated (finish_reason=length).', [
                'question_set_id' => $set->id,
                'batch' => $batch,
            ]);
        }

        $items = ResponseParser::questions($result['content'], allowSalvage: $truncated);

        if ($items === null) {
            Log::warning('AI response could not be parsed as JSON.', [
                'question_set_id' => $set->id,
                'finish_reason' => $result['finish_reason'],
                'content_preview' => Str::limit($result['content'], 500),
            ]);

            return;
        }

        $remaining = $batch;
        $rejected = 0;

        foreach ($items as $item) {
            $question = $this->validator->validate($item);

            if ($question === null) {
                $rejected++;

                continue;
            }

            $type = $question['type'];
            $fingerprint = $this->fingerprint($question['prompt']);

            if (($remaining[$type] ?? 0) <= 0 || isset($seenPrompts[$fingerprint])) {
                continue;
            }

            $collected[] = $question;
            $seenPrompts[$fingerprint] = true;
            $remaining[$type]--;
        }

        if ($rejected > 0) {
            Log::warning('Discarded invalid AI questions.', [
                'question_set_id' => $set->id,
                'rejected' => $rejected,
                'last_reason' => $this->validator->lastError,
            ]);
        }
    }

    /**
     * Perform a Chat Completions request.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{content: string, finish_reason: string|null}
     */
    private function chat(array $messages, bool $jsonMode): array
    {
        $config = config('ai.openai');

        if (blank($config['api_key'])) {
            throw AIException::notConfigured('OPENAI_API_KEY is not set');
        }
        if (blank($config['model'])) {
            throw AIException::notConfigured('OPENAI_MODEL is not set');
        }

        $body = [
            'model' => $config['model'],
            'messages' => $messages,
            'max_completion_tokens' => $config['max_completion_tokens'],
        ];

        if ($config['temperature'] !== null) {
            $body['temperature'] = $config['temperature'];
        }

        if ($jsonMode) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $headers = filled($config['organization'])
            ? ['OpenAI-Organization' => $config['organization']]
            : [];

        try {
            $response = Http::withToken($config['api_key'])
                ->withHeaders($headers)
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('ai.timeout'))
                ->retry(2, 1000, fn (Throwable $e) => $this->shouldRetry($e), throw: false)
                ->post($config['base_url'].'/chat/completions', $body);
        } catch (ConnectionException $e) {
            throw AIException::connection($e->getMessage());
        }

        if ($response->failed()) {
            throw AIException::fromResponse($response);
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content)) {
            $refusal = $response->json('choices.0.message.refusal');

            throw AIException::invalidResponse($refusal ? 'Model refused: '.Str::limit($refusal, 200) : 'Missing choices[0].message.content');
        }

        return [
            'content' => $content,
            'finish_reason' => $response->json('choices.0.finish_reason'),
        ];
    }

    private function shouldRetry(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        if ($e instanceof RequestException) {
            $status = $e->response->status();

            // An exhausted quota won't recover within a retry window.
            if ($status === 429 && $e->response->json('error.code') === 'insufficient_quota') {
                return false;
            }

            return $status === 429 || $status >= 500;
        }

        return false;
    }

    /**
     * @param  array<string, int>  $wanted
     * @param  list<array>  $collected
     * @return array<string, int>
     */
    private function missingCounts(array $wanted, array $collected): array
    {
        $have = array_count_values(array_column($collected, 'type'));
        $missing = [];

        foreach ($wanted as $type => $count) {
            $gap = $count - ($have[$type] ?? 0);
            if ($gap > 0) {
                $missing[$type] = $gap;
            }
        }

        return $missing;
    }

    /**
     * Group questions by the order the user selected their types, keeping
     * generation order within each type.
     *
     * @param  list<array>  $questions
     * @param  list<string>  $typeOrder
     * @return list<array>
     */
    private function orderByType(array $questions, array $typeOrder): array
    {
        $rank = array_flip($typeOrder);

        return collect($questions)
            ->values()
            ->sortBy(fn (array $q, int $index) => [$rank[$q['type']] ?? PHP_INT_MAX, $index])
            ->values()
            ->all();
    }

    private function fingerprint(string $prompt): string
    {
        return Str::of($prompt)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }

    private function logWarning(string $message, QuestionSet $set, AIException $e): void
    {
        Log::warning($message, [
            'question_set_id' => $set->id,
            'error' => $e->getMessage(),
            ...$e->context,
        ]);
    }
}
