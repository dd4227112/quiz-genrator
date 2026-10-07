<?php

namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class QuestionAnswerController extends Controller
{
    public function __invoke(Question $question): JsonResponse
    {
        Gate::authorize('view', $question);

        return response()->json([
            'id' => $question->id,
            'answer' => $question->answer,
            'explanation' => $question->explanation,
        ]);
    }
}
