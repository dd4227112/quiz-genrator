<?php

namespace App\Http\Controllers;

use App\Contracts\QuestionGenerator;
use App\Http\Requests\StoreClarificationRequest;
use App\Http\Resources\ClarificationResource;
use App\Models\Question;
use App\Services\AI\AIException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class QuestionClarificationController extends Controller
{
    public function index(Question $question): AnonymousResourceCollection
    {
        Gate::authorize('view', $question);

        return ClarificationResource::collection($question->clarifications()->get());
    }

    public function store(StoreClarificationRequest $request, Question $question, QuestionGenerator $generator): JsonResponse
    {
        $message = $request->validated('message');
        $history = $question->clarifications()->get();

        try {
            $response = $generator->clarify($question, $message, $history);
        } catch (AIException $e) {
            Log::error('Clarification request failed.', [
                'question_id' => $question->id,
                'user_message' => $e->getMessage(),
                ...$e->context,
            ]);

            return response()->json(['message' => $e->getMessage()], 503);
        }

        $clarification = $question->clarifications()->create([
            'user_id' => $request->user()->id,
            'user_message' => $message,
            'ai_response' => $response,
        ]);

        return (new ClarificationResource($clarification))->response()->setStatusCode(201);
    }
}
