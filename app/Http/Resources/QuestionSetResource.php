<?php

namespace App\Http\Resources;

use App\Enums\QuestionType;
use App\Models\QuestionSet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuestionSet
 */
class QuestionSetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'topic' => $this->topic,
            'level' => $this->whenLoaded('level', fn () => [
                'id' => $this->level->id,
                'name' => $this->level->name,
            ]),
            'question_types' => array_map(fn (string $type) => [
                'value' => $type,
                'label' => QuestionType::tryFrom($type)?->label() ?? $type,
            ], $this->question_types ?? []),
            'requested_count' => $this->requested_count,
            'additional_notes' => $this->additional_notes,
            'status' => $this->status->value,
            'error_message' => $this->error_message,
            'questions_count' => $this->whenCounted('questions'),
            'questions' => $this->whenLoaded(
                'questions',
                fn () => QuestionResource::collection($this->questions)->resolve($request),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
