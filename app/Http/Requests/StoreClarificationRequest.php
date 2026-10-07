<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClarificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('question'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->message)) {
            $this->merge(['message' => trim($this->message)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:2', 'max:1000'],
        ];
    }
}
