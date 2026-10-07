<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    |
    | Selects which App\Contracts\QuestionGenerator implementation is bound in
    | the container. Supported: "openai".
    |
    */

    'provider' => env('AI_PROVIDER', 'openai'),

    'timeout' => (int) env('AI_REQUEST_TIMEOUT', 120),

    'max_questions' => (int) env('AI_MAX_QUESTIONS_PER_REQUEST', 30),

    // Maximum questions requested from the model in a single API call.
    'batch_size' => 15,

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION') ?: null,
        'base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
        'model' => env('OPENAI_MODEL'),
        'max_completion_tokens' => (int) env('OPENAI_MAX_COMPLETION_TOKENS', 8000),
        'temperature' => env('OPENAI_TEMPERATURE') === null || env('OPENAI_TEMPERATURE') === ''
            ? null
            : (float) env('OPENAI_TEMPERATURE'),
    ],

];
