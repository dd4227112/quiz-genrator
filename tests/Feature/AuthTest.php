<?php

use App\Models\Question;
use App\Models\QuestionSet;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests to login from protected pages', function (string $method, Closure $url) {
    $this->{$method}($url())->assertRedirect(route('login'));
})->with([
    'home' => ['get', fn () => '/'],
    'history' => ['get', fn () => route('question-sets.index')],
    'create' => ['get', fn () => route('question-sets.create')],
    'store' => ['post', fn () => route('question-sets.store')],
    'show' => ['get', fn () => route('question-sets.show', QuestionSet::factory()->create())],
    'status' => ['get', fn () => route('question-sets.status', QuestionSet::factory()->create())],
    'regenerate' => ['post', fn () => route('question-sets.regenerate', QuestionSet::factory()->create())],
    'destroy' => ['delete', fn () => route('question-sets.destroy', QuestionSet::factory()->create())],
    'answer' => ['get', fn () => route('questions.answer', Question::factory()->create())],
    'clarifications' => ['get', fn () => route('questions.clarifications.index', Question::factory()->create())],
    'attempt' => ['post', fn () => route('questions.attempts.store', Question::factory()->create())],
    'clarify' => ['post', fn () => route('questions.clarifications.store', Question::factory()->create())],
]);

it('redirects authenticated users from home to the create page', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('question-sets.create'));
});

it('renders the login and register pages', function () {
    $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    $this->get(route('register'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
});

it('registers a new user and lands on the create page', function () {
    $this->post(route('register'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertRedirect('/question-sets/create');

    $this->assertAuthenticated();
    expect(User::where('email', 'ada@example.com')->exists())->toBeTrue();
});

it('validates registration', function () {
    $this->post(route('register'), ['name' => '', 'email' => 'not-an-email', 'password' => 'x', 'password_confirmation' => 'y'])
        ->assertSessionHasErrors(['name', 'email', 'password']);

    $this->assertGuest();
});

it('logs in and out', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'secret-password'])
        ->assertRedirect('/question-sets/create');
    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'))->assertRedirect('/');
    $this->assertGuest();
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('shares the authenticated user and flash messages with Inertia', function () {
    $user = User::factory()->create(['name' => 'Grace']);

    $this->actingAs($user)
        ->withSession(['success' => 'Saved!'])
        ->get(route('question-sets.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.name', 'Grace')
            ->missing('auth.user.password')
            ->where('flash.success', 'Saved!'));
});
