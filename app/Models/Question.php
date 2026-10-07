<?php

namespace App\Models;

use App\Enums\QuestionType;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['question_set_id', 'type', 'position', 'prompt', 'payload', 'answer', 'explanation'])]
#[Hidden(['answer', 'explanation'])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'position' => 'integer',
            'payload' => 'array',
            'answer' => 'array',
        ];
    }

    public function questionSet(): BelongsTo
    {
        return $this->belongsTo(QuestionSet::class);
    }

    public function clarifications(): HasMany
    {
        return $this->hasMany(QuestionClarification::class)->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuestionAttempt::class);
    }

    public function latestAttempt(): HasOne
    {
        return $this->hasOne(QuestionAttempt::class)->latestOfMany();
    }
}
