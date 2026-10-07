<?php

use App\Enums\QuestionSetStatus;
use App\Jobs\GenerateQuestionSetJob;
use App\Models\Level;
use App\Models\QuestionSet;
use App\Models\Topic;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function makeSet(array $attributes = []): QuestionSet
{
    return QuestionSet::factory()->create(array_merge([
        'level_id' => Level::factory()->create(['name' => 'Senior', 'slug' => 'senior'])->id,
        'topic' => 'Laravel Developer',
        'question_types' => ['true_false', 'multiple_choice', 'matching'],
        'requested_count' => 10,
    ], $attributes));
}

function runJob(QuestionSet $set): QuestionSet
{
    GenerateQuestionSetJob::dispatchSync($set);

    return $set->fresh();
}

it('saves the correct number of questions per type from a valid response', function () {
    Http::fake([OPENAI_URL => openAiResponse(questionsPayload([
        'true_false' => 4, 'multiple_choice' => 3, 'matching' => 3,
    ]))]);

    $set = runJob(makeSet());

    expect($set->status)->toBe(QuestionSetStatus::Completed)
        ->and($set->error_message)->toBeNull()
        ->and($set->questions()->count())->toBe(10);

    $counts = $set->questions()->reorder()->selectRaw('type, count(*) as c')->groupBy('type')->pluck('c', 'type')->all();
    expect($counts)->toEqual(['true_false' => 4, 'multiple_choice' => 3, 'matching' => 3])
        ->and($set->questions()->pluck('position')->all())->toBe(range(1, 10));

    Http::assertSentCount(1);
});

it('sends a correctly shaped chat completions request', function () {
    Http::fake([OPENAI_URL => openAiResponse(questionsPayload(['true_false' => 4, 'multiple_choice' => 3, 'matching' => 3]))]);

    runJob(makeSet(['additional_notes' => 'Focus on queues']));

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $userPrompt = $body['messages'][1]['content'];

        return $request->url() === OPENAI_URL
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer sk-test-key')
            && ! $request->hasHeader('OpenAI-Organization')
            && $body['model'] === 'test-model'
            && $body['max_completion_tokens'] === 8000
            && ! array_key_exists('max_tokens', $body)
            && ! array_key_exists('temperature', $body)
            && $body['response_format'] === ['type' => 'json_object']
            && $body['messages'][0]['role'] === 'system'
            && str_contains($body['messages'][0]['content'], 'JSON')
            && str_contains($userPrompt, 'Laravel Developer')
            && str_contains($userPrompt, 'Senior')
            && str_contains($userPrompt, 'true_false: exactly 4')
            && str_contains($userPrompt, 'multiple_choice: exactly 3')
            && str_contains($userPrompt, 'matching: exactly 3')
            && str_contains($userPrompt, 'Focus on queues');
    });
});

it('asks for balanced coverage when several topics are combined', function () {
    Http::fake([OPENAI_URL => openAiResponse(questionsPayload(['true_false' => 4, 'multiple_choice' => 3, 'matching' => 3]))]);
    $set = makeSet(['topic' => 'Laravel Developer + Database Administrator']);
    $set->topics()->attach([
        Topic::factory()->create(['name' => 'Laravel Developer'])->id => ['position' => 0],
        Topic::factory()->create(['name' => 'Database Administrator'])->id => ['position' => 1],
    ]);

    runJob($set);

    Http::assertSent(fn (Request $request) => str_contains($request->data()['messages'][1]['content'], 'Field (profession, course, or program): Laravel Developer + Database Administrator')
        && str_contains($request->data()['messages'][1]['content'], 'combines several fields'));
});

it('does not add multi-field guidance for a single topic', function () {
    Http::fake([OPENAI_URL => openAiResponse(questionsPayload(['true_false' => 4, 'multiple_choice' => 3, 'matching' => 3]))]);

    runJob(makeSet());

    Http::assertSent(fn (Request $request) => ! str_contains($request->data()['messages'][1]['content'], 'combines several fields'));
});

it('includes the organization header and temperature only when configured', function () {
    config(['ai.openai.organization' => 'org-123', 'ai.openai.temperature' => 0.4]);
    Http::fake([OPENAI_URL => openAiResponse(questionsPayload(['true_false' => 4, 'multiple_choice' => 3, 'matching' => 3]))]);

    runJob(makeSet());

    Http::assertSent(fn (Request $request) => $request->hasHeader('OpenAI-Organization', 'org-123')
        && $request->data()['temperature'] === 0.4);
});

it('strips accidental markdown fences', function () {
    $json = json_encode(questionsPayload(['true_false' => 4, 'multiple_choice' => 3, 'matching' => 3]));
    Http::fake([OPENAI_URL => openAiResponse("```json\n{$json}\n```")]);

    expect(runJob(makeSet())->questions()->count())->toBe(10);
});

it('marks the set failed with a friendly message when the response is not valid JSON', function () {
    Log::spy();
    Http::fake([OPENAI_URL => openAiResponse('Sorry, I cannot do that { not json')]);

    $set = runJob(makeSet());

    expect($set->status)->toBe(QuestionSetStatus::Failed)
        ->and($set->error_message)->toBe('The AI did not return any usable questions. Please try again.')
        ->and($set->questions()->count())->toBe(0);

    // Initial request plus exactly one follow-up.
    Http::assertSentCount(2);
    Log::shouldHaveReceived('error')->withArgs(fn ($message) => $message === 'Question generation failed.')->once();
});

it('marks the set failed with a friendly message on API errors', function (int $status, string $code, string $expected) {
    Http::fake([OPENAI_URL => openAiError($status, $code)]);

    $set = runJob(makeSet());

    expect($set->status)->toBe(QuestionSetStatus::Failed)
        ->and($set->error_message)->toBe($expected);
})->with([
    'invalid key' => [401, 'invalid_api_key', 'AI service is not configured correctly.'],
    'unknown model' => [404, 'model_not_found', 'AI service is not configured correctly.'],
    'rate limited' => [429, 'rate_limit_exceeded', 'AI service is busy, try again shortly.'],
    'quota exceeded' => [429, 'insufficient_quota', 'AI service quota has been exceeded. Please contact the administrator.'],
    'server error' => [500, 'server_error', 'AI service is temporarily unavailable. Please try again later.'],
    'bad request' => [400, 'invalid_value', 'The AI service could not process the request. Please try again.'],
]);

it('retries only on 429 and 5xx, not on client errors', function () {
    Http::fake([OPENAI_URL => openAiError(500, 'server_error')]);
    runJob(makeSet());
    Http::assertSentCount(2);
});

it('does not retry authentication errors', function () {
    Http::fake([OPENAI_URL => openAiError(401, 'invalid_api_key')]);
    runJob(makeSet());
    Http::assertSentCount(1);
});

it('fails without calling the API when the key or model is missing', function () {
    config(['ai.openai.api_key' => null]);
    Http::fake();

    $set = runJob(makeSet());

    expect($set->status)->toBe(QuestionSetStatus::Failed)
        ->and($set->error_message)->toBe('AI service is not configured correctly.');
    Http::assertNothingSent();
});

it('never logs the API key', function () {
    $logged = [];
    Log::listen(function ($event) use (&$logged) {
        $logged[] = $event->message.json_encode($event->context);
    });
    Http::fake([OPENAI_URL => openAiError(401, 'invalid_api_key', 'Incorrect API key provided: sk-test-key / sk-proj-****abcd.')]);

    runJob(makeSet());

    $output = implode("\n", $logged);
    expect($logged)->not->toBeEmpty()
        ->and($output)->not->toContain('sk-test-key')
        ->and($output)->not->toContain('sk-proj-')
        ->and($output)->toContain('[redacted]');
});

it('makes a follow-up request when the response is truncated', function () {
    // The model was cut off mid-way: 2 complete questions, then a partial one.
    $complete = questionsPayload(['true_false' => 2])['questions'];
    $truncated = '{"questions": ['.implode(',', array_map('json_encode', $complete)).', {"type": "multiple_choice", "prompt": "Cut of';

    Http::fake([OPENAI_URL => Http::sequence()
        ->push(json_decode(openAiResponse($truncated, 'length')->wait()->getBody(), true))
        ->push(json_decode(openAiResponse(questionsPayload(['true_false' => 2, 'multiple_choice' => 4], offset: 10))->wait()->getBody(), true)),
    ]);

    $set = runJob(makeSet(['question_types' => ['true_false', 'multiple_choice'], 'requested_count' => 8]));

    Http::assertSentCount(2);

    // The follow-up asks only for what is missing and avoids what we already have.
    $recorded = Http::recorded();
    $followUp = $recorded[1][0]->data()['messages'][1]['content'];
    expect($followUp)->toContain('true_false: exactly 2')
        ->toContain('multiple_choice: exactly 4')
        ->toContain('True/false statement number 1.');

    expect($set->status)->toBe(QuestionSetStatus::Completed)
        ->and($set->error_message)->toBeNull()
        ->and($set->questions()->where('type', 'true_false')->count())->toBe(4)
        ->and($set->questions()->where('type', 'multiple_choice')->count())->toBe(4);
});

it('saves partial results with a note when still short after the follow-up', function () {
    Http::fake([OPENAI_URL => Http::sequence()
        ->push(json_decode(openAiResponse(questionsPayload(['true_false' => 3]))->wait()->getBody(), true))
        ->push(json_decode(openAiResponse(['questions' => []])->wait()->getBody(), true)),
    ]);

    $set = runJob(makeSet(['question_types' => ['true_false'], 'requested_count' => 5]));

    Http::assertSentCount(2);
    expect($set->status)->toBe(QuestionSetStatus::Completed)
        ->and($set->questions()->count())->toBe(3)
        ->and($set->error_message)->toContain('Only 3 of 5');
});

it('discards invalid and duplicate questions and requests replacements', function () {
    $first = questionsPayload(['multiple_choice' => 3]);
    $first['questions'][1]['payload']['options'] = [];          // invalid
    $first['questions'][2]['prompt'] = $first['questions'][0]['prompt']; // duplicate

    Http::fake([OPENAI_URL => Http::sequence()
        ->push(json_decode(openAiResponse($first)->wait()->getBody(), true))
        ->push(json_decode(openAiResponse(questionsPayload(['multiple_choice' => 2], offset: 20))->wait()->getBody(), true)),
    ]);

    $set = runJob(makeSet(['question_types' => ['multiple_choice'], 'requested_count' => 3]));

    expect($set->questions()->count())->toBe(3)
        ->and($set->questions()->pluck('prompt')->unique()->count())->toBe(3);
    Http::assertSentCount(2);
});

it('ignores extra questions beyond the requested counts', function () {
    Http::fake([OPENAI_URL => openAiResponse(questionsPayload(['true_false' => 6, 'short_answer' => 2]))]);

    $set = runJob(makeSet(['question_types' => ['true_false'], 'requested_count' => 4]));

    expect($set->questions()->count())->toBe(4)
        ->and($set->questions()->where('type', '!=', 'true_false')->count())->toBe(0);
});

it('splits large requests into batches and passes earlier prompts to later batches', function () {
    Http::fake([OPENAI_URL => Http::sequence()
        ->push(json_decode(openAiResponse(questionsPayload(['true_false' => 8, 'short_answer' => 7]))->wait()->getBody(), true))
        ->push(json_decode(openAiResponse(questionsPayload(['true_false' => 7, 'short_answer' => 8], offset: 100))->wait()->getBody(), true)),
    ]);

    $set = runJob(makeSet(['question_types' => ['true_false', 'short_answer'], 'requested_count' => 30]));

    Http::assertSentCount(2);
    $recorded = Http::recorded();
    expect($recorded[0][0]->data()['messages'][1]['content'])->not->toContain('already generated')
        ->and($recorded[1][0]->data()['messages'][1]['content'])->toContain('already generated')
        ->toContain('True/false statement number 1.');

    expect($set->status)->toBe(QuestionSetStatus::Completed)
        ->and($set->questions()->count())->toBe(30)
        ->and($set->questions()->where('type', 'true_false')->count())->toBe(15)
        ->and($set->questions()->where('type', 'short_answer')->count())->toBe(15);
});

it('keeps first-batch results when a later batch fails', function () {
    Http::fake([OPENAI_URL => Http::sequence()
        ->push(json_decode(openAiResponse(questionsPayload(['multiple_choice' => 10]))->wait()->getBody(), true))
        ->pushStatus(500)->pushStatus(500)   // batch 2 + its retry
        ->pushStatus(500)->pushStatus(500),  // follow-up + its retry
    ]);

    $set = runJob(makeSet(['question_types' => ['multiple_choice'], 'requested_count' => 20]));

    expect($set->status)->toBe(QuestionSetStatus::Completed)
        ->and($set->questions()->count())->toBe(10)
        ->and($set->error_message)->toContain('Only 10 of 20');
});

it('stores type-specific JSON structures exactly', function () {
    Http::fake([OPENAI_URL => openAiResponse(questionsPayload([
        'true_false' => 1, 'multiple_choice' => 1, 'matching' => 1, 'short_answer' => 1,
    ]))]);

    $set = runJob(makeSet(['question_types' => ['true_false', 'multiple_choice', 'matching', 'short_answer'], 'requested_count' => 4]));
    $byType = $set->questions->keyBy(fn ($q) => $q->type->value);

    expect($byType['true_false']->answer)->toBe(['value' => false])
        ->and($byType['true_false']->payload)->toBe([])
        ->and($byType['multiple_choice']->answer)->toBe(['key' => 'C'])
        ->and($byType['multiple_choice']->payload['options'])->toHaveCount(4)
        ->and($byType['matching']->payload)->toHaveKeys(['left', 'right'])
        ->and($byType['matching']->answer['pairs'])->toHaveCount(4)
        ->and($byType['short_answer']->answer)->toBe(['text' => 'Model answer 1.', 'key_points' => ['Point one', 'Point two']]);
});

it('skips sets that are no longer pending', function () {
    Http::fake();
    $set = makeSet(['status' => QuestionSetStatus::Completed]);

    runJob($set);

    Http::assertNothingSent();
});

it('marks the set failed if the job itself fails', function () {
    $set = makeSet();

    (new GenerateQuestionSetJob($set))->failed(new RuntimeException('Timed out'));

    expect($set->fresh()->status)->toBe(QuestionSetStatus::Failed)
        ->and($set->fresh()->error_message)->toContain('took too long');
});
