<?php

use App\Models\Level;
use App\Models\Question;
use App\Models\QuestionClarification;
use App\Models\QuestionSet;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $set = QuestionSet::factory()->completed()->for($this->user)->create([
        'topic' => 'Laravel Developer',
        'level_id' => Level::factory()->create(['name' => 'Junior', 'slug' => 'junior']),
    ]);
    $this->question = Question::factory()->for($set)->create([
        'prompt' => 'Which facade sends HTTP requests?',
        'answer' => ['key' => 'B'],
        'explanation' => 'The Http facade wraps Guzzle.',
    ]);
});

it('stores and returns the clarification exchange', function () {
    Http::fake([OPENAI_URL => openAiResponse('Option B is correct because the Http facade wraps Guzzle.')]);

    $this->actingAs($this->user)
        ->postJson(route('questions.clarifications.store', $this->question), ['message' => '  Why is B correct?  '])
        ->assertCreated()
        ->assertJsonPath('data.user_message', 'Why is B correct?')
        ->assertJsonPath('data.ai_response', 'Option B is correct because the Http facade wraps Guzzle.')
        ->assertJsonStructure(['data' => ['id', 'user_message', 'ai_response', 'created_at']]);

    $stored = QuestionClarification::sole();
    expect($stored->question_id)->toBe($this->question->id)
        ->and($stored->user_id)->toBe($this->user->id)
        ->and($stored->user_message)->toBe('Why is B correct?');
});

it('sends question context and prior history without JSON mode', function () {
    QuestionClarification::factory()->for($this->question)->for($this->user)->create([
        'user_message' => 'First follow-up',
        'ai_response' => 'First reply',
    ]);
    Http::fake([OPENAI_URL => openAiResponse('Second reply')]);

    $this->actingAs($this->user)
        ->postJson(route('questions.clarifications.store', $this->question), ['message' => 'Second follow-up'])
        ->assertCreated();

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $messages = $body['messages'];
        $system = $messages[0]['content'];

        return ! array_key_exists('response_format', $body)
            && array_key_exists('max_completion_tokens', $body)
            && $messages[0]['role'] === 'system'
            && str_contains($system, 'Laravel Developer')
            && str_contains($system, 'Junior')
            && str_contains($system, 'Which facade sends HTTP requests?')
            && str_contains($system, 'Correct answer: B')
            && str_contains($system, 'The Http facade wraps Guzzle.')
            && str_contains($system, 'unrelated')
            && $messages[1] === ['role' => 'user', 'content' => 'First follow-up']
            && $messages[2] === ['role' => 'assistant', 'content' => 'First reply']
            && $messages[3] === ['role' => 'user', 'content' => 'Second follow-up']
            && count($messages) === 4;
    });
});

it('returns the clarification history', function () {
    QuestionClarification::factory()->count(2)->for($this->question)->for($this->user)->create();

    $this->actingAs($this->user)
        ->getJson(route('questions.clarifications.index', $this->question))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('validates the clarification message', function (mixed $message) {
    Http::fake();

    $this->actingAs($this->user)
        ->postJson(route('questions.clarifications.store', $this->question), ['message' => $message])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');

    Http::assertNothingSent();
    expect(QuestionClarification::count())->toBe(0);
})->with([
    'missing' => [null],
    'too short' => ['a'],
    'whitespace' => ['   '],
    'too long' => [str_repeat('a', 1001)],
]);

it('forbids clarifying another user\'s question', function () {
    Http::fake();

    $this->actingAs(User::factory()->create())
        ->postJson(route('questions.clarifications.store', $this->question), ['message' => 'Why?'])
        ->assertForbidden();
    $this->actingAs(User::factory()->create())
        ->getJson(route('questions.clarifications.index', $this->question))
        ->assertForbidden();

    Http::assertNothingSent();
});

it('returns a friendly error and stores nothing when the AI fails', function () {
    Http::fake([OPENAI_URL => openAiError(429, 'rate_limit_exceeded')]);

    $this->actingAs($this->user)
        ->postJson(route('questions.clarifications.store', $this->question), ['message' => 'Why is B correct?'])
        ->assertStatus(503)
        ->assertJson(['message' => 'AI service is busy, try again shortly.']);

    expect(QuestionClarification::count())->toBe(0);
});
