<?php

namespace App\Actions;

use App\Enums\QuestionSetStatus;
use App\Jobs\GenerateQuestionSetJob;
use App\Models\QuestionSet;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QueueQuestionSetGeneration
{
    /**
     * @param  array{topic_ids: list<int>, level_id: int, question_types: list<string>, count: int, additional_notes?: string|null}  $data
     */
    public function create(User $user, array $data): QuestionSet
    {
        $ids = array_map('intval', array_values($data['topic_ids']));
        $topics = Topic::whereKey($ids)->get()->sortBy(fn (Topic $topic) => array_search($topic->id, $ids, true))->values();

        $set = DB::transaction(function () use ($user, $data, $topics) {
            $set = $user->questionSets()->create([
                'topic' => $topics->pluck('name')->implode(' + '),
                'level_id' => $data['level_id'],
                'question_types' => array_values($data['question_types']),
                'requested_count' => $data['count'],
                'additional_notes' => filled($data['additional_notes'] ?? null) ? trim($data['additional_notes']) : null,
                'status' => QuestionSetStatus::Pending,
            ]);

            $set->topics()->attach($topics->mapWithKeys(fn (Topic $topic, int $index) => [$topic->id => ['position' => $index]])->all());

            return $set;
        });

        GenerateQuestionSetJob::dispatch($set);

        return $set;
    }

    public function retry(QuestionSet $set): void
    {
        DB::transaction(function () use ($set) {
            $set->questions()->delete();
            $set->update(['status' => QuestionSetStatus::Pending, 'error_message' => null]);
        });

        GenerateQuestionSetJob::dispatch($set);
    }
}
