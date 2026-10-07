<?php

namespace App\Jobs;

use App\Actions\GenerateQuestionsForSet;
use App\Enums\QuestionSetStatus;
use App\Models\QuestionSet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateQuestionSetJob implements ShouldQueue
{
    use Queueable;

    /** The AI service already retries transient failures. */
    public int $tries = 1;

    /**
     * Up to 4 API calls (2 batches + follow-ups), each allowed AI_REQUEST_TIMEOUT
     * seconds with one retry. Keep DB_QUEUE_RETRY_AFTER above this value.
     */
    public int $timeout = 900;

    public function __construct(public QuestionSet $questionSet) {}

    public function handle(GenerateQuestionsForSet $action): void
    {
        if ($this->questionSet->status !== QuestionSetStatus::Pending) {
            return;
        }

        $action->handle($this->questionSet);
    }

    /**
     * Called when the job itself dies (e.g. worker timeout) so the UI stops polling.
     */
    public function failed(?Throwable $exception): void
    {
        app(GenerateQuestionsForSet::class)->fail(
            $this->questionSet->fresh() ?? $this->questionSet,
            'Question generation took too long or was interrupted. Please try again.',
            ['exception' => $exception ? $exception::class : null, 'reason' => $exception?->getMessage()],
        );
    }
}
