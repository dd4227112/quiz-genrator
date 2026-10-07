<?php

namespace App\Services\AI;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * An AI provider failure. getMessage() is always safe to show to end users;
 * technical detail for logs lives in $context and never includes credentials.
 */
class AIException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $userMessage, public readonly array $context = [])
    {
        parent::__construct($userMessage);
    }

    public static function notConfigured(string $detail): self
    {
        return new self('AI service is not configured correctly.', ['reason' => $detail]);
    }

    public static function connection(string $detail): self
    {
        return new self('Could not reach the AI service. Please try again.', ['reason' => $detail]);
    }

    public static function invalidResponse(string $detail): self
    {
        return new self('The AI service returned an unexpected response. Please try again.', ['reason' => $detail]);
    }

    public static function fromResponse(Response $response): self
    {
        $error = $response->json('error');
        $code = is_array($error) ? ($error['code'] ?? $error['type'] ?? null) : null;

        $message = match (true) {
            in_array($response->status(), [401, 403], true) => 'AI service is not configured correctly.',
            $response->status() === 404 => 'AI service is not configured correctly.',
            $response->status() === 429 && $code === 'insufficient_quota' => 'AI service quota has been exceeded. Please contact the administrator.',
            $response->status() === 429 => 'AI service is busy, try again shortly.',
            $response->status() === 400 && $code === 'context_length_exceeded' => 'The request was too large for the AI service. Try fewer questions.',
            $response->status() >= 500 => 'AI service is temporarily unavailable. Please try again later.',
            default => 'The AI service could not process the request. Please try again.',
        };

        return new self($message, [
            'status' => $response->status(),
            'error_code' => $code,
            'error_message' => is_array($error) ? self::redact($error['message'] ?? null) : null,
        ]);
    }

    /**
     * Provider error messages can echo (partially masked) credentials; strip them before logging.
     */
    public static function redact(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        $key = (string) config('ai.openai.api_key');
        if ($key !== '') {
            $message = str_replace($key, '[redacted]', $message);
        }

        return preg_replace('/\bsk-[A-Za-z0-9_\-*]{4,}/', '[redacted]', $message);
    }
}
