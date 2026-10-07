<?php

namespace App\Http\Controllers;

use App\Actions\GradeQuestionAttempt;
use App\Http\Requests\StoreAttemptRequest;
use App\Http\Resources\QuestionAttemptResource;
use App\Models\Question;
use App\Services\AI\AIException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class QuestionAttemptController extends Controller
{
    /**
     * Grade the user's answer. Once answered, the correct answer and explanation
     * are returned too so the UI can show what was right.
     */
    public function store(StoreAttemptRequest $request, Question $question, GradeQuestionAttempt $grader): JsonResponse
    {
        try {
            $attempt = $grader->handle($question, $request->user(), $request->validated('response'));
        } catch (AIException $e) {
            Log::error('Answer grading failed.', [
                'question_id' => $question->id,
                'user_message' => $e->getMessage(),
                ...$e->context,
            ]);

            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json([
            'attempt' => (new QuestionAttemptResource($attempt))->resolve($request),
            'answer' => $question->answer,
            'explanation' => $question->explanation,
        ], 201);
    }
}
