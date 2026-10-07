<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Tests must never reach the real OpenAI API.
        Http::preventStrayRequests();
        Sleep::fake();

        config([
            'ai.provider' => 'openai',
            'ai.openai.api_key' => 'sk-test-key',
            'ai.openai.organization' => null,
            'ai.openai.base_url' => 'https://api.openai.test/v1',
            'ai.openai.model' => 'test-model',
            'ai.openai.max_completion_tokens' => 8000,
            'ai.openai.temperature' => null,
            'ai.max_questions' => 30,
            'ai.batch_size' => 15,
        ]);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const OPENAI_URL = 'https://api.openai.test/v1/chat/completions';

/**
 * A faked Chat Completions response with the real response shape.
 */
function openAiResponse(array|string $content, string $finishReason = 'stop', int $status = 200)
{
    return Http::response([
        'id' => 'chatcmpl-test',
        'object' => 'chat.completion',
        'created' => 1700000000,
        'model' => 'test-model',
        'choices' => [[
            'index' => 0,
            'message' => [
                'role' => 'assistant',
                'content' => is_array($content) ? json_encode($content) : $content,
                'refusal' => null,
            ],
            'finish_reason' => $finishReason,
        ]],
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 200, 'total_tokens' => 300],
    ], $status);
}

function openAiError(int $status, string $code = 'error', string $message = 'Error')
{
    return Http::response(['error' => [
        'message' => $message,
        'type' => 'invalid_request_error',
        'code' => $code,
    ]], $status);
}

/**
 * Build a valid question of the given type.
 */
function sampleQuestion(string $type, int $n = 1): array
{
    return match ($type) {
        'true_false' => [
            'type' => 'true_false',
            'prompt' => "True/false statement number {$n}.",
            'payload' => new stdClass,
            'answer' => ['value' => $n % 2 === 0],
            'explanation' => "Explanation for true/false {$n}.",
        ],
        'multiple_choice' => [
            'type' => 'multiple_choice',
            'prompt' => "Multiple choice question number {$n}?",
            'payload' => ['options' => [
                ['key' => 'A', 'text' => 'Alpha'],
                ['key' => 'B', 'text' => 'Bravo'],
                ['key' => 'C', 'text' => 'Charlie'],
                ['key' => 'D', 'text' => 'Delta'],
            ]],
            'answer' => ['key' => 'C'],
            'explanation' => "Explanation for multiple choice {$n}.",
        ],
        'matching' => [
            'type' => 'matching',
            'prompt' => "Match the items for set {$n}.",
            'payload' => [
                'left' => [
                    ['id' => '1', 'text' => 'One'],
                    ['id' => '2', 'text' => 'Two'],
                    ['id' => '3', 'text' => 'Three'],
                    ['id' => '4', 'text' => 'Four'],
                ],
                'right' => [
                    ['id' => 'a', 'text' => 'Uno'],
                    ['id' => 'b', 'text' => 'Dos'],
                    ['id' => 'c', 'text' => 'Tres'],
                    ['id' => 'd', 'text' => 'Cuatro'],
                ],
            ],
            'answer' => ['pairs' => [
                ['left' => '1', 'right' => 'a'],
                ['left' => '2', 'right' => 'b'],
                ['left' => '3', 'right' => 'c'],
                ['left' => '4', 'right' => 'd'],
            ]],
            'explanation' => "Explanation for matching {$n}.",
        ],
        'short_answer' => [
            'type' => 'short_answer',
            'prompt' => "Explain concept number {$n}.",
            'payload' => new stdClass,
            'answer' => ['text' => "Model answer {$n}.", 'key_points' => ['Point one', 'Point two']],
            'explanation' => "Explanation for short answer {$n}.",
        ],
    };
}

/**
 * @param  array<string, int>  $counts  type => count
 * @return array{questions: list<array>}
 */
function questionsPayload(array $counts, int $offset = 0): array
{
    $questions = [];
    foreach ($counts as $type => $count) {
        for ($i = 1; $i <= $count; $i++) {
            $questions[] = sampleQuestion($type, $offset + $i);
        }
    }

    return ['questions' => $questions];
}
