<?php

namespace App\Models;

use App\Enums\QuestionSetStatus;
use Database\Factories\QuestionSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'topic', 'level_id', 'question_types', 'requested_count',
    'additional_notes', 'status', 'error_message',
])]
class QuestionSet extends Model
{
    /** @use HasFactory<QuestionSetFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'question_types' => 'array',
            'requested_count' => 'integer',
            'status' => QuestionSetStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }

    /**
     * The catalog topics this set was generated for, in the order the user chose them.
     * `topic` keeps their display string (and the free text of sets created before the catalog).
     */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class)->withPivot('position')->orderByPivot('position');
    }
}
