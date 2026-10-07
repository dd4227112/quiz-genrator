<?php

namespace App\Http\Resources;

use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public question shape. Deliberately excludes `answer` and `explanation`,
 * which are only served by the reveal and attempt endpoints.
 *
 * @mixin Question
 */
class QuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'position' => $this->position,
            'prompt' => $this->prompt,
            'payload' => (object) ($this->payload ?? []),
            'latest_attempt' => $this->whenLoaded(
                'latestAttempt',
                fn () => $this->latestAttempt ? (new QuestionAttemptResource($this->latestAttempt))->resolve($request) : null,
            ),
        ];
    }
}
