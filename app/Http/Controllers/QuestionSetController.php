<?php

namespace App\Http\Controllers;

use App\Actions\QueueQuestionSetGeneration;
use App\Enums\QuestionType;
use App\Http\Requests\StoreQuestionSetRequest;
use App\Http\Resources\QuestionSetResource;
use App\Models\Level;
use App\Models\QuestionSet;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class QuestionSetController extends Controller
{
    public function index(Request $request): Response
    {
        $sets = $request->user()->questionSets()
            ->with('level')
            ->withCount('questions')
            ->latest()
            ->latest('id')
            ->paginate(10)
            ->through(fn (QuestionSet $set) => (new QuestionSetResource($set))->resolve());

        return Inertia::render('QuestionSets/Index', ['questionSets' => $sets]);
    }

    public function create(): Response
    {
        return Inertia::render('QuestionSets/Create', [
            'levels' => Level::orderBy('sort_order')->get(['id', 'name']),
            'topics' => $this->topicOptions(),
            'maxTopics' => StoreQuestionSetRequest::MAX_TOPICS,
            'questionTypes' => QuestionType::options(),
            'maxCount' => (int) config('ai.max_questions'),
        ]);
    }

    /**
     * Active topics grouped for display, each with a popularity `rank` (0 = most used
     * across all sets) that the picker uses to break ties between equally good matches.
     *
     * @return list<array{id: int, name: string, category: string, rank: int}>
     */
    private function topicOptions(): array
    {
        $topics = Topic::active()->withCount('questionSets')->get(['id', 'name', 'category']);

        $rank = $topics
            ->sortBy([['question_sets_count', 'desc'], ['name', 'asc']])
            ->values()
            ->mapWithKeys(fn (Topic $topic, int $index) => [$topic->id => $index]);

        return $topics
            ->sortBy([['category', 'asc'], ['name', 'asc']])
            ->map(fn (Topic $topic) => [
                'id' => $topic->id,
                'name' => $topic->name,
                'category' => $topic->category,
                'rank' => $rank[$topic->id],
            ])
            ->values()
            ->all();
    }

    public function store(StoreQuestionSetRequest $request, QueueQuestionSetGeneration $action): RedirectResponse
    {
        $set = $action->create($request->user(), $request->validated());

        return redirect()->route('question-sets.show', $set)
            ->with('success', 'Generating your questions. This usually takes under a minute.');
    }

    public function show(QuestionSet $questionSet): Response
    {
        Gate::authorize('view', $questionSet);

        $questionSet->load(['level', 'questions.latestAttempt'])->loadCount('questions');

        return Inertia::render('QuestionSets/Show', [
            'questionSet' => (new QuestionSetResource($questionSet))->resolve(),
        ]);
    }

    public function status(QuestionSet $questionSet): JsonResponse
    {
        Gate::authorize('view', $questionSet);

        return response()->json([
            'status' => $questionSet->status->value,
            'error_message' => $questionSet->error_message,
            'questions_count' => $questionSet->questions()->count(),
        ]);
    }

    public function regenerate(QuestionSet $questionSet, QueueQuestionSetGeneration $action): RedirectResponse
    {
        Gate::authorize('regenerate', $questionSet);

        $action->retry($questionSet);

        return redirect()->route('question-sets.show', $questionSet)
            ->with('success', 'Trying again. Your questions are being generated.');
    }

    public function destroy(QuestionSet $questionSet): RedirectResponse
    {
        Gate::authorize('delete', $questionSet);

        $questionSet->delete();

        return redirect()->route('question-sets.index')->with('success', 'Question set deleted.');
    }
}
