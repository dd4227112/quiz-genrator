<?php

use App\Models\Question;
use App\Models\QuestionSet;
use App\Models\User;

it('returns the answer and explanation to the owner', function () {
    $owner = User::factory()->create();
    $question = Question::factory()
        ->for(QuestionSet::factory()->completed()->for($owner))
        ->create(['answer' => ['key' => 'B'], 'explanation' => 'Because B.']);

    $this->actingAs($owner)
        ->getJson(route('questions.answer', $question))
        ->assertOk()
        ->assertExactJson([
            'id' => $question->id,
            'answer' => ['key' => 'B'],
            'explanation' => 'Because B.',
        ]);
});

it('forbids other users from fetching the answer', function () {
    $question = Question::factory()->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('questions.answer', $question))
        ->assertForbidden();
});

it('requires authentication to fetch the answer', function () {
    $question = Question::factory()->create();

    $this->getJson(route('questions.answer', $question))->assertUnauthorized();
});
