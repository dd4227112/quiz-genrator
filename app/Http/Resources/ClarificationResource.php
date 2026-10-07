<?php

namespace App\Http\Resources;

use App\Models\QuestionClarification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuestionClarification
 */
class ClarificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_message' => $this->user_message,
            'ai_response' => $this->ai_response,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
