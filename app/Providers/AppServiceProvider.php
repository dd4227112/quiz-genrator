<?php

namespace App\Providers;

use App\Contracts\QuestionGenerator;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Services\AI\OpenAIQuestionGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(QuestionGenerator::class, function ($app) {
            return match (config('ai.provider')) {
                'openai' => $app->make(OpenAIQuestionGenerator::class),
                default => throw new InvalidArgumentException('Unsupported AI_PROVIDER ['.config('ai.provider').'].'),
            };
        });
    }

    public function boot(): void
    {
        RateLimiter::for('generations', fn (Request $request) => Limit::perHour(10)
            ->by('generations:'.$request->user()?->id)
            ->response(fn (Request $request, array $headers) => response()->json([
                'message' => 'You have reached the limit of 10 question sets per hour. Please try again later.',
            ], 429, $headers)));

        // Only free-text answers cost an AI call; objective answers are graded locally.
        RateLimiter::for('answer-grading', function (Request $request) {
            $question = $request->route('question');
            $question = $question instanceof Question ? $question : Question::find($question);

            if ($question?->type !== QuestionType::ShortAnswer) {
                return Limit::none();
            }

            return Limit::perHour(60)
                ->by('answer-grading:'.$request->user()?->id)
                ->response(fn (Request $request, array $headers) => response()->json([
                    'message' => 'You have reached the limit of 60 AI-graded answers per hour. Please try again later.',
                ], 429, $headers));
        });

        RateLimiter::for('clarifications', fn (Request $request) => Limit::perHour(30)
            ->by('clarifications:'.$request->user()?->id)
            ->response(fn (Request $request, array $headers) => response()->json([
                'message' => 'You have reached the limit of 30 clarifications per hour. Please try again later.',
            ], 429, $headers)));
    }
}
