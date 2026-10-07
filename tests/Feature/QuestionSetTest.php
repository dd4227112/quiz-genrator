<?php

use App\Enums\QuestionSetStatus;
use App\Jobs\GenerateQuestionSetJob;
use App\Models\Level;
use App\Models\Question;
use App\Models\QuestionSet;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\LevelSeeder;
use Database\Seeders\TopicSeeder;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([LevelSeeder::class, TopicSeeder::class]);
    $this->user = User::factory()->create();
    $this->level = Level::where('slug', 'senior')->first();
});

function validSetInput(array $overrides = []): array
{
    return array_merge([
        'topic_ids' => [Topic::where('name', 'Laravel Developer')->value('id')],
        'level_id' => Level::where('slug', 'senior')->value('id'),
        'question_types' => ['true_false', 'multiple_choice'],
        'count' => 10,
        'additional_notes' => 'Focus on REST APIs',
    ], $overrides);
}

it('renders the create form with levels, types, and max count', function () {
    $this->actingAs($this->user)
        ->get(route('question-sets.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('QuestionSets/Create')
            ->has('levels', 6)
            ->where('levels.0.name', 'Beginner')
            ->where('levels.5.name', 'Lead/Expert')
            ->has('questionTypes', 4)
            ->where('questionTypes.0', ['value' => 'true_false', 'label' => 'True/False'])
            ->where('maxCount', 30)
            ->where('maxTopics', 3)
            ->has('topics', count(array_merge(...array_values(TopicSeeder::CATALOG))))
            ->has('topics.0', fn (Assert $topic) => $topic->hasAll(['id', 'name', 'category', 'rank'])));
});

it('ranks topics by how often they are used so ties favour common choices', function () {
    $registered = Topic::where('name', 'Registered Nurse')->first();
    $enrolled = Topic::where('name', 'Enrolled Nurse')->first();
    QuestionSet::factory()->count(2)->create()->each(fn (QuestionSet $set) => $set->topics()->attach($registered));
    QuestionSet::factory()->create()->topics()->attach($enrolled);

    $this->actingAs($this->user)
        ->get(route('question-sets.create'))
        ->assertInertia(function (Assert $page) {
            $topics = collect($page->toArray()['props']['topics']);
            $rank = $topics->pluck('rank', 'name');

            expect($rank['Registered Nurse'])->toBe(0)
                ->and($rank['Enrolled Nurse'])->toBe(1)
                ->and($topics->pluck('rank')->sort()->values()->all())->toBe(range(0, $topics->count() - 1))
                // Display order stays grouped by category, then name.
                ->and($topics->map(fn ($t) => $t['category'].'|'.$t['name'])->all())
                ->toBe($topics->map(fn ($t) => $t['category'].'|'.$t['name'])->sort()->values()->all());
        });
});

it('only offers active topics', function () {
    Topic::where('name', 'Laravel Developer')->update(['is_active' => false]);

    $this->actingAs($this->user)
        ->get(route('question-sets.create'))
        ->assertInertia(fn (Assert $page) => $page->where(
            'topics',
            fn ($topics) => collect($topics)->doesntContain('name', 'Laravel Developer'),
        ));
});

it('combines several chosen topics in the order picked', function () {
    Queue::fake();
    $ids = Topic::whereIn('name', ['Database Administrator', 'Laravel Developer'])->pluck('id', 'name');

    $this->actingAs($this->user)
        ->post(route('question-sets.store'), validSetInput([
            'topic_ids' => [$ids['Laravel Developer'], $ids['Database Administrator']],
        ]))
        ->assertRedirect();

    $set = QuestionSet::sole();
    expect($set->topic)->toBe('Laravel Developer + Database Administrator')
        ->and($set->topics->pluck('name')->all())->toBe(['Laravel Developer', 'Database Administrator']);
});

it('creates a pending set, dispatches the job, and redirects to the show page', function () {
    Queue::fake();

    $response = $this->actingAs($this->user)->post(route('question-sets.store'), validSetInput());

    $set = QuestionSet::sole();
    $response->assertRedirect(route('question-sets.show', $set));

    expect($set->user_id)->toBe($this->user->id)
        ->and($set->status)->toBe(QuestionSetStatus::Pending)
        ->and($set->topic)->toBe('Laravel Developer')
        ->and($set->question_types)->toBe(['true_false', 'multiple_choice'])
        ->and($set->requested_count)->toBe(10)
        ->and($set->additional_notes)->toBe('Focus on REST APIs');

    Queue::assertPushed(GenerateQuestionSetJob::class, fn ($job) => $job->questionSet->is($set));
});

it('validates input and does not dispatch the job', function (array|Closure $overrides, string $field) {
    Queue::fake();
    $overrides = $overrides instanceof Closure ? $overrides() : $overrides;

    $this->actingAs($this->user)
        ->post(route('question-sets.store'), validSetInput($overrides))
        ->assertSessionHasErrors($field);

    expect(QuestionSet::count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'no topics' => [['topic_ids' => []], 'topic_ids'],
    'free text instead of a topic' => [['topic_ids' => ['asdf qwerty']], 'topic_ids.0'],
    'unknown topic' => [['topic_ids' => [999999]], 'topic_ids.0'],
    'duplicate topics' => [fn () => ['topic_ids' => array_fill(0, 2, Topic::value('id'))], 'topic_ids.0'],
    'too many topics' => [fn () => ['topic_ids' => Topic::limit(4)->pluck('id')->all()], 'topic_ids'],
    'inactive topic' => [fn () => ['topic_ids' => [Topic::factory()->inactive()->create()->id]], 'topic_ids.0'],
    'missing level' => [['level_id' => null], 'level_id'],
    'unknown level' => [['level_id' => 999], 'level_id'],
    'no types' => [['question_types' => []], 'question_types'],
    'invalid type' => [['question_types' => ['essay']], 'question_types.0'],
    'count zero' => [['count' => 0], 'count'],
    'count above max' => [['count' => 31], 'count'],
    'count not integer' => [['count' => 'ten'], 'count'],
    'notes too long' => [['additional_notes' => str_repeat('a', 501)], 'additional_notes'],
]);

it('respects the configured maximum question count', function () {
    Queue::fake();
    config(['ai.max_questions' => 5]);

    $this->actingAs($this->user)
        ->post(route('question-sets.store'), validSetInput(['count' => 6]))
        ->assertSessionHasErrors('count');
});

it('shows a set without exposing answers or explanations', function () {
    $set = QuestionSet::factory()->completed()->for($this->user)->create(['level_id' => $this->level->id]);
    Question::factory()->for($set)->create([
        'position' => 1,
        'answer' => ['key' => 'B'],
        'explanation' => 'SECRET-EXPLANATION-TEXT',
    ]);
    Question::factory()->trueFalse()->for($set)->create(['position' => 2, 'explanation' => 'ANOTHER-SECRET']);

    $response = $this->actingAs($this->user)->get(route('question-sets.show', $set));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('QuestionSets/Show')
        ->where('questionSet.id', $set->id)
        ->where('questionSet.status', 'completed')
        ->where('questionSet.level.name', 'Senior')
        ->has('questionSet.questions', 2)
        ->has('questionSet.questions.0', fn (Assert $q) => $q
            ->hasAll(['id', 'type', 'type_label', 'position', 'prompt', 'payload'])
            ->missing('answer')
            ->missing('explanation')
            ->etc())
        ->has('questionSet.questions.1', fn (Assert $q) => $q->missing('answer')->missing('explanation')->etc()));

    $html = $response->getContent();
    expect($html)->not->toContain('SECRET-EXPLANATION-TEXT')
        ->not->toContain('ANOTHER-SECRET')
        ->not->toContain('&quot;answer&quot;')
        ->not->toContain('"answer"');
});

it('forbids viewing another user\'s set', function () {
    $set = QuestionSet::factory()->create();

    $this->actingAs($this->user)->get(route('question-sets.show', $set))->assertForbidden();
    $this->actingAs($this->user)->getJson(route('question-sets.status', $set))->assertForbidden();
    $this->actingAs($this->user)->delete(route('question-sets.destroy', $set))->assertForbidden();
});

it('returns status JSON for polling', function () {
    $set = QuestionSet::factory()->failed('AI service is busy, try again shortly.')->for($this->user)->create();

    $this->actingAs($this->user)
        ->getJson(route('question-sets.status', $set))
        ->assertOk()
        ->assertExactJson([
            'status' => 'failed',
            'error_message' => 'AI service is busy, try again shortly.',
            'questions_count' => 0,
        ]);
});

it('lists only the user\'s own sets, newest first, paginated', function () {
    QuestionSet::factory()->for($this->user)->create(['topic' => 'Older', 'created_at' => now()->subDay()]);
    QuestionSet::factory()->for($this->user)->create(['topic' => 'Newer']);
    QuestionSet::factory()->create(['topic' => 'Someone else']);

    $this->actingAs($this->user)
        ->get(route('question-sets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('QuestionSets/Index')
            ->has('questionSets.data', 2)
            ->where('questionSets.data.0.topic', 'Newer')
            ->where('questionSets.data.1.topic', 'Older')
            ->has('questionSets.links'));
});

it('regenerates a failed set by clearing questions and re-dispatching', function () {
    Queue::fake();
    $set = QuestionSet::factory()->failed()->for($this->user)->create();
    Question::factory()->for($set)->create();

    $this->actingAs($this->user)
        ->post(route('question-sets.regenerate', $set))
        ->assertRedirect(route('question-sets.show', $set));

    $set->refresh();
    expect($set->status)->toBe(QuestionSetStatus::Pending)
        ->and($set->error_message)->toBeNull()
        ->and($set->questions()->count())->toBe(0);
    Queue::assertPushed(GenerateQuestionSetJob::class);
});

it('does not regenerate a set that has not failed', function () {
    Queue::fake();
    $set = QuestionSet::factory()->completed()->for($this->user)->create();

    $this->actingAs($this->user)->post(route('question-sets.regenerate', $set))->assertForbidden();
    Queue::assertNothingPushed();
});

it('deletes a set and its questions', function () {
    $set = QuestionSet::factory()->for($this->user)->create();
    Question::factory()->for($set)->create();

    $this->actingAs($this->user)
        ->delete(route('question-sets.destroy', $set))
        ->assertRedirect(route('question-sets.index'));

    expect(QuestionSet::count())->toBe(0)->and(Question::count())->toBe(0);
});
