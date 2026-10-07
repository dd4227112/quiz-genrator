<?php

namespace App\Models;

use Database\Factories\QuestionAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['question_id', 'user_id', 'response', 'is_correct', 'score', 'feedback', 'graded_by'])]
class QuestionAttempt extends Model
{
    /** @use HasFactory<QuestionAttemptFactory> */
    use HasFactory;

    public const GRADED_AUTOMATICALLY = 'auto';

    public const GRADED_BY_AI = 'ai';

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'is_correct' => 'boolean',
            'score' => 'integer',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
