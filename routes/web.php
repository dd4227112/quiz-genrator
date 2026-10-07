<?php

use App\Http\Controllers\QuestionAnswerController;
use App\Http\Controllers\QuestionAttemptController;
use App\Http\Controllers\QuestionClarificationController;
use App\Http\Controllers\QuestionSetController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('question-sets.create')
    : redirect()->route('login')
)->name('home');

Route::middleware('auth')->group(function () {
    Route::controller(QuestionSetController::class)->group(function () {
        Route::get('/question-sets', 'index')->name('question-sets.index');
        Route::get('/question-sets/create', 'create')->name('question-sets.create');
        Route::post('/question-sets', 'store')->middleware('throttle:generations')->name('question-sets.store');
        Route::get('/question-sets/{questionSet}', 'show')->name('question-sets.show');
        Route::get('/question-sets/{questionSet}/status', 'status')->name('question-sets.status');
        Route::post('/question-sets/{questionSet}/regenerate', 'regenerate')->middleware('throttle:generations')->name('question-sets.regenerate');
        Route::delete('/question-sets/{questionSet}', 'destroy')->name('question-sets.destroy');
    });

    Route::get('/questions/{question}/answer', QuestionAnswerController::class)->name('questions.answer');
    Route::post('/questions/{question}/attempts', [QuestionAttemptController::class, 'store'])
        ->middleware('throttle:answer-grading')
        ->name('questions.attempts.store');
    Route::get('/questions/{question}/clarifications', [QuestionClarificationController::class, 'index'])->name('questions.clarifications.index');
    Route::post('/questions/{question}/clarifications', [QuestionClarificationController::class, 'store'])
        ->middleware('throttle:clarifications')
        ->name('questions.clarifications.store');
});
