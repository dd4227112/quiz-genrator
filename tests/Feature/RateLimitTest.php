<?php

use App\Models\Level;
use App\Models\Question;
use App\Models\QuestionSet;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('limits generations to 10 per hour per user', function () {
    Queue::fake();
    $user = User::factory()->create();
    $level = Level::factory()->create();
    $input = ['topic_ids' => [Topic::factory()->create()->id], 'level_id' => $level->id, 'question_types' => ['true_false'], 'count' => 3];

    foreach (range(1, 10) as $_) {
        $this->actingAs($user)->post(route('question-sets.store'), $input)->assertRedirect();
    }

    $this->actingAs($user)
        ->post(route('question-sets.store'), $input)
        ->assertTooManyRequests()
        ->assertJson(['message' => 'You have reached the limit of 10 question sets per hour. Please try again later.']);

    expect(QuestionSet::count())->toBe(10);

    // Limits are per user.
    $this->actingAs(User::factory()->create())->post(route('question-sets.store'), $input)->assertRedirect();
});

it('limits clarifications to 30 per hour per user', function () {
    Http::fake([OPENAI_URL => openAiResponse('An answer.')]);
    $user = User::factory()->create();
    $question = Question::factory()->for(QuestionSet::factory()->completed()->for($user))->create();

    foreach (range(1, 30) as $_) {
        $this->actingAs($user)
            ->postJson(route('questions.clarifications.store', $question), ['message' => 'Explain more'])
            ->assertCreated();
    }

    $this->actingAs($user)
        ->postJson(route('questions.clarifications.store', $question), ['message' => 'Explain more'])
        ->assertTooManyRequests()
        ->assertJson(['message' => 'You have reached the limit of 30 clarifications per hour. Please try again later.']);
});
