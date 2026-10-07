<?php

namespace App\Http\Requests;

use App\Enums\QuestionType;
use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttemptRequest extends FormRequest
{
    public const MAX_TEXT_LENGTH = 5000;

    public function authorize(): bool
    {
        return $this->user()->can('view', $this->question());
    }

    protected function prepareForValidation(): void
    {
        $response = $this->input('response');

        if (! is_array($response)) {
            return;
        }

        if (is_string($response['text'] ?? null)) {
            $response['text'] = trim($response['text']);
        }

        if (is_string($response['key'] ?? null)) {
            $response['key'] = strtoupper(trim($response['key']));
        }

        $this->merge(['response' => $response]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $question = $this->question();
        $payload = $question->payload;

        return ['response' => ['required', 'array'], ...match ($question->type) {
            QuestionType::TrueFalse => [
                'response.value' => ['required', 'boolean'],
            ],
            QuestionType::MultipleChoice => [
                'response.key' => ['required', 'string', Rule::in(array_column($payload['options'] ?? [], 'key'))],
            ],
            QuestionType::Matching => [
                'response.pairs' => ['required', 'array', 'size:'.count($payload['left'] ?? [])],
                'response.pairs.*.left' => ['required', 'string', 'distinct', Rule::in(array_column($payload['left'] ?? [], 'id'))],
                'response.pairs.*.right' => ['required', 'string', 'distinct', Rule::in(array_column($payload['right'] ?? [], 'id'))],
            ],
            QuestionType::ShortAnswer => [
                'response.text' => ['required', 'string', 'min:2', 'max:'.self::MAX_TEXT_LENGTH],
            ],
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'response.value.required' => 'Choose True or False.',
            'response.key.required' => 'Choose an option.',
            'response.key.in' => 'Choose one of the listed options.',
            'response.pairs.required' => 'Match every item before checking your answer.',
            'response.pairs.size' => 'Match every item before checking your answer.',
            'response.pairs.*.right.distinct' => 'Each item in Column B can only be used once.',
            'response.pairs.*.left.distinct' => 'Each item in Column A can only be matched once.',
            'response.text.required' => 'Write your answer first.',
            'response.text.min' => 'Write a little more before checking your answer.',
        ];
    }

    public function question(): Question
    {
        return $this->route('question');
    }
}
