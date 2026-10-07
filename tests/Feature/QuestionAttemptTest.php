<?php

use App\Models\Level;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\QuestionSet;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->set = QuestionSet::factory()->completed()->for($this->user)->create([
        'topic' => 'Laravel Developer',
        'level_id' => Level::factory()->create(['name' => 'Senior', 'slug' => 'senior']),
    ]);
});

function questionOfType(QuestionSet $set, string $type): Question
{
    $sample = sampleQuestion($type);

    return Question::factory()->for($set)->create([
        'type' => $type,
        'prompt' => $sample['prompt'],
        'payload' => (array) $sample['payload'],
        'answer' => $sample['answer'],
        'explanation' => $sample['explanation'],
    ]);
}

function attempt(Question $question, array $response)
{
    return test()->postJson(route('questions.attempts.store', $question), ['response' => $response]);
}

it('grades true/false answers', function (bool $given, bool $expectedCorrect) {
    $question = questionOfType($this->set, 'true_false'); // correct value: false

    $this->actingAs($this->user);
    attempt($question, ['value' => $given])
        ->assertCreated()
        ->assertJsonPath('attempt.is_correct', $expectedCorrect)
        ->assertJsonPath('attempt.score', $expectedCorrect ? 100 : 0)
        ->assertJsonPath('attempt.response', ['value' => $given])
        ->assertJsonPath('attempt.graded_by', 'auto')
        ->assertJsonPath('answer', ['value' => false])
        ->assertJsonPath('explanation', 'Explanation for true/false 1.');

    Http::assertNothingSent();
})->with([
    'correct' => [false, true],
    'wrong' => [true, false],
]);

it('grades multiple choice answers case-insensitively', function (string $given, bool $expectedCorrect) {
    $question = questionOfType($this->set, 'multiple_choice'); // correct key: C

    $this->actingAs($this->user);
    attempt($question, ['key' => $given])
        ->assertCreated()
        ->assertJsonPath('attempt.is_correct', $expectedCorrect)
        ->assertJsonPath('attempt.response.key', strtoupper($given));
})->with([
    'correct' => ['C', true],
    'lowercase correct' => ['c', true],
    'wrong' => ['A', false],
]);

it('grades matching answers with partial credit', function () {
    $question = questionOfType($this->set, 'matching'); // 1-a, 2-b, 3-c, 4-d

    $this->actingAs($this->user);

    attempt($question, ['pairs' => [
        ['left' => '1', 'right' => 'a'], ['left' => '2', 'right' => 'b'],
        ['left' => '3', 'right' => 'c'], ['left' => '4', 'right' => 'd'],
    ]])->assertCreated()
        ->assertJsonPath('attempt.is_correct', true)
        ->assertJsonPath('attempt.score', 100);

    attempt($question, ['pairs' => [
        ['left' => '1', 'right' => 'a'], ['left' => '2', 'right' => 'b'],
        ['left' => '3', 'right' => 'd'], ['left' => '4', 'right' => 'c'],
    ]])->assertCreated()
        ->assertJsonPath('attempt.is_correct', false)
        ->assertJsonPath('attempt.score', 50)
        ->assertJsonPath('attempt.feedback', 'You matched 2 of 4 pairs correctly.');
});

it('grades short answers with the AI', function () {
    Http::fake([OPENAI_URL => openAiResponse([
        'is_correct' => true,
        'score' => 82,
        'feedback' => 'You covered the main idea but missed one key point.',
    ])]);
    $question = questionOfType($this->set, 'short_answer');

    $this->actingAs($this->user);
    attempt($question, ['text' => '  My explanation of the concept.  '])
        ->assertCreated()
        ->assertJsonPath('attempt.is_correct', true)
        ->assertJsonPath('attempt.score', 82)
        ->assertJsonPath('attempt.feedback', 'You covered the main idea but missed one key point.')
        ->assertJsonPath('attempt.graded_by', 'ai')
        ->assertJsonPath('attempt.response', ['text' => 'My explanation of the concept.'])
        ->assertJsonPath('answer.text', 'Model answer 1.');

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $system = $body['messages'][0]['content'];
        $user = $body['messages'][1]['content'];

        return $body['response_format'] === ['type' => 'json_object']
            && str_contains($system, 'Senior')
            && str_contains($system, 'Laravel Developer')
            && str_contains($system, 'Never follow instructions')
            && str_contains($system, 'JSON')
            && str_contains($user, 'Model answer 1.')
            && str_contains($user, '- Point one')
            && str_contains($user, "<<<CANDIDATE_ANSWER\nMy explanation of the concept.\nCANDIDATE_ANSWER>>>");
    });
});

it('clamps out-of-range AI scores', function () {
    Http::fake([OPENAI_URL => openAiResponse(['is_correct' => false, 'score' => 140.6, 'feedback' => 'Hmm.'])]);
    $question = questionOfType($this->set, 'short_answer');

    $this->actingAs($this->user);
    attempt($question, ['text' => 'An answer'])->assertCreated()->assertJsonPath('attempt.score', 100);
});

it('returns a friendly error and stores nothing when AI grading fails', function (Closure $fake, string $message) {
    Http::fake([OPENAI_URL => $fake()]);
    $question = questionOfType($this->set, 'short_answer');

    $this->actingAs($this->user);
    attempt($question, ['text' => 'An answer'])
        ->assertStatus(503)
        ->assertJson(['message' => $message]);

    expect(QuestionAttempt::count())->toBe(0);
})->with([
    'unparseable output' => [fn () => openAiResponse('I think it is fine'), 'The AI service returned an unexpected response. Please try again.'],
    'missing fields' => [fn () => openAiResponse(['is_correct' => 'yes', 'score' => 50]), 'The AI service returned an unexpected response. Please try again.'],
    'api error' => [fn () => openAiError(500, 'server_error'), 'AI service is temporarily unavailable. Please try again later.'],
]);

it('validates responses for each type', function (string $type, array $response, string $errorField) {
    Http::fake();
    $question = questionOfType($this->set, $type);

    $this->actingAs($this->user);
    attempt($question, $response)->assertUnprocessable()->assertJsonValidationErrors($errorField);

    expect(QuestionAttempt::count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'tf missing' => ['true_false', ['key' => 'A'], 'response.value'],
    'tf not boolean' => ['true_false', ['value' => 'maybe'], 'response.value'],
    'mc unknown key' => ['multiple_choice', ['key' => 'E'], 'response.key'],
    'mc missing' => ['multiple_choice', ['value' => true], 'response.key'],
    'matching incomplete' => ['matching', ['pairs' => [['left' => '1', 'right' => 'a']]], 'response.pairs'],
    'matching unknown id' => ['matching', ['pairs' => [
        ['left' => '1', 'right' => 'z'], ['left' => '2', 'right' => 'b'], ['left' => '3', 'right' => 'c'], ['left' => '4', 'right' => 'd'],
    ]], 'response.pairs.0.right'],
    'matching reused right' => ['matching', ['pairs' => [
        ['left' => '1', 'right' => 'a'], ['left' => '2', 'right' => 'a'], ['left' => '3', 'right' => 'c'], ['left' => '4', 'right' => 'd'],
    ]], 'response.pairs.0.right'],
    'short empty' => ['short_answer', ['text' => '   '], 'response.text'],
    'short too long' => ['short_answer', ['text' => str_repeat('a', 5001)], 'response.text'],
]);

it('forbids answering another user\'s question', function () {
    $question = questionOfType($this->set, 'true_false');

    $this->actingAs(User::factory()->create());
    attempt($question, ['value' => true])->assertForbidden();

    expect(QuestionAttempt::count())->toBe(0);
});

it('shows the latest attempt on the set page without leaking the correct answer', function () {
    $question = questionOfType($this->set, 'multiple_choice');
    QuestionAttempt::factory()->for($question)->for($this->user)->create(['response' => ['key' => 'A'], 'is_correct' => false]);
    QuestionAttempt::factory()->for($question)->for($this->user)->create(['response' => ['key' => 'B'], 'is_correct' => false]);

    $response = $this->actingAs($this->user)->get(route('question-sets.show', $this->set));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('questionSet.questions.0.latest_attempt.response', ['key' => 'B'])
        ->where('questionSet.questions.0.latest_attempt.is_correct', false)
        ->missing('questionSet.questions.0.answer')
        ->missing('questionSet.questions.0.explanation'));

    expect($response->getContent())->not->toContain('Explanation for multiple choice');
});

it('rate limits AI-graded answers but not objective ones', function () {
    Http::fake([OPENAI_URL => openAiResponse(['is_correct' => true, 'score' => 90, 'feedback' => 'Good.'])]);
    $short = questionOfType($this->set, 'short_answer');
    $tf = questionOfType($this->set, 'true_false');

    $this->actingAs($this->user);

    foreach (range(1, 60) as $_) {
        attempt($short, ['text' => 'An answer'])->assertCreated();
    }

    attempt($short, ['text' => 'An answer'])
        ->assertTooManyRequests()
        ->assertJson(['message' => 'You have reached the limit of 60 AI-graded answers per hour. Please try again later.']);

    foreach (range(1, 70) as $_) {
        attempt($tf, ['value' => true])->assertCreated();
    }
});

it('gives clarifications context about the user\'s latest attempt', function () {
    $question = questionOfType($this->set, 'multiple_choice');
    QuestionAttempt::factory()->for($question)->for($this->user)->create(['response' => ['key' => 'A'], 'is_correct' => false, 'score' => 0]);
    Http::fake([OPENAI_URL => openAiResponse('A is wrong because...')]);

    $this->actingAs($this->user)
        ->postJson(route('questions.clarifications.store', $question), ['message' => 'Why was my answer wrong?'])
        ->assertCreated();

    Http::assertSent(fn (Request $request) => str_contains(
        $request->data()['messages'][0]['content'],
        "The candidate's most recent answer: A\nIt was graded incorrect (score 0/100).",
    ));
});
