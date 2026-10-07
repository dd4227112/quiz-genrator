<?php

namespace App\Http\Requests;

use App\Enums\QuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestionSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public const MAX_TOPICS = 3;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'topic_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_TOPICS],
            'topic_ids.*' => [
                'required', 'integer', 'distinct',
                Rule::exists('topics', 'id')->where('is_active', true),
            ],
            'level_id' => ['required', 'integer', 'exists:levels,id'],
            'question_types' => ['required', 'array', 'min:1'],
            'question_types.*' => ['required', 'string', 'distinct', Rule::in(QuestionType::values())],
            'count' => ['required', 'integer', 'min:1', 'max:'.config('ai.max_questions')],
            'additional_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'topic_ids.required' => 'Choose at least one profession, course, or program.',
            'topic_ids.min' => 'Choose at least one profession, course, or program.',
            'topic_ids.max' => 'Choose up to '.self::MAX_TOPICS.' professions, courses, or programs.',
            'topic_ids.*.exists' => 'Choose a profession, course, or program from the list.',
            'topic_ids.*.distinct' => 'Each profession, course, or program can only be chosen once.',
            'question_types.required' => 'Select at least one question type.',
            'question_types.min' => 'Select at least one question type.',
            'question_types.*.in' => 'One of the selected question types is not supported.',
            'level_id.required' => 'Choose a level.',
            'level_id.exists' => 'Choose a valid level.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'count' => 'number of questions',
            'topic_ids' => 'professions, courses, or programs',
            'level_id' => 'level',
            'question_types' => 'question types',
        ];
    }
}
