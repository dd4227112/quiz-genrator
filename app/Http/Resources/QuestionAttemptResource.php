<?php

namespace App\Http\Resources;

use App\Models\QuestionAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuestionAttempt
 */
class QuestionAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'response' => $this->response,
            'is_correct' => $this->is_correct,
            'score' => $this->score,
            'feedback' => $this->feedback,
            'graded_by' => $this->graded_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
