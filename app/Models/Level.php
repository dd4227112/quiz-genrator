<?php

namespace App\Models;

use Database\Factories\LevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'sort_order'])]
class Level extends Model
{
    /** @use HasFactory<LevelFactory> */
    use HasFactory;

    public function questionSets(): HasMany
    {
        return $this->hasMany(QuestionSet::class);
    }
}
