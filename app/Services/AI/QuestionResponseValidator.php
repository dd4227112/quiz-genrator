<?php

namespace App\Services\AI;

use App\Enums\QuestionType;

/**
 * Validates and normalizes a single AI-generated question against the
 * type-specific JSON structures. Invalid items yield null.
 */
class QuestionResponseValidator
{
    private const MC_KEYS = ['A', 'B', 'C', 'D'];

    private const MATCHING_MIN_PAIRS = 4;

    private const MATCHING_MAX_PAIRS = 6;

    /** Reason the most recent validate() call rejected its input. */
    public ?string $lastError = null;

    /**
     * @return array{type: string, prompt: string, payload: array, answer: array, explanation: string}|null
     */
    public function validate(mixed $item): ?array
    {
        $this->lastError = null;

        if (! is_array($item)) {
            return $this->reject('item is not an object');
        }

        $type = QuestionType::tryFrom((string) ($item['type'] ?? ''));
        $prompt = $this->nonEmptyString($item['prompt'] ?? null);
        $explanation = $this->nonEmptyString($item['explanation'] ?? null);
        $payload = $item['payload'] ?? null;
        $answer = $item['answer'] ?? null;

        if ($type === null) {
            return $this->reject('unknown type');
        }
        if ($prompt === null) {
            return $this->reject('missing prompt');
        }
        if ($explanation === null) {
            return $this->reject('missing explanation');
        }
        if (! is_array($payload) || ! is_array($answer)) {
            return $this->reject('payload and answer must be objects');
        }

        $normalized = match ($type) {
            QuestionType::TrueFalse => $this->trueFalse($answer),
            QuestionType::MultipleChoice => $this->multipleChoice($payload, $answer),
            QuestionType::Matching => $this->matching($payload, $answer),
            QuestionType::ShortAnswer => $this->shortAnswer($answer),
        };

        if ($normalized === null) {
            return null;
        }

        return [
            'type' => $type->value,
            'prompt' => $prompt,
            'payload' => $normalized['payload'],
            'answer' => $normalized['answer'],
            'explanation' => $explanation,
        ];
    }

    private function trueFalse(array $answer): ?array
    {
        if (! array_key_exists('value', $answer) || ! is_bool($answer['value'])) {
            return $this->reject('true_false answer.value must be boolean');
        }

        return ['payload' => [], 'answer' => ['value' => $answer['value']]];
    }

    private function multipleChoice(array $payload, array $answer): ?array
    {
        $options = $payload['options'] ?? null;

        if (! is_array($options) || ! array_is_list($options) || count($options) !== count(self::MC_KEYS)) {
            return $this->reject('multiple_choice needs exactly 4 options');
        }

        $normalized = [];
        foreach ($options as $option) {
            $key = is_array($option) ? ($option['key'] ?? null) : null;
            $text = is_array($option) ? $this->nonEmptyString($option['text'] ?? null) : null;

            if (! is_string($key) || $text === null) {
                return $this->reject('multiple_choice option needs key and text');
            }
            $normalized[strtoupper(trim($key))] = $text;
        }

        $keys = array_keys($normalized);
        sort($keys);
        if ($keys !== self::MC_KEYS) {
            return $this->reject('multiple_choice option keys must be A-D');
        }

        $answerKey = is_string($answer['key'] ?? null) ? strtoupper(trim($answer['key'])) : null;
        if (! in_array($answerKey, self::MC_KEYS, true)) {
            return $this->reject('multiple_choice answer.key must reference an option');
        }

        return [
            'payload' => ['options' => array_map(
                fn (string $key) => ['key' => $key, 'text' => $normalized[$key]],
                self::MC_KEYS,
            )],
            'answer' => ['key' => $answerKey],
        ];
    }

    private function matching(array $payload, array $answer): ?array
    {
        $left = $this->matchingColumn($payload['left'] ?? null);
        $right = $this->matchingColumn($payload['right'] ?? null);
        $pairs = $answer['pairs'] ?? null;

        if ($left === null || $right === null) {
            return $this->reject('matching columns need unique ids and text');
        }

        $count = count($left);
        if ($count < self::MATCHING_MIN_PAIRS || $count > self::MATCHING_MAX_PAIRS || count($right) !== $count) {
            return $this->reject('matching needs 4-6 items in each column');
        }

        if (! is_array($pairs) || ! array_is_list($pairs) || count($pairs) !== $count) {
            return $this->reject('matching needs one pair per left item');
        }

        $map = [];
        $usedRight = [];
        foreach ($pairs as $pair) {
            $l = is_array($pair) ? $this->id($pair['left'] ?? null) : null;
            $r = is_array($pair) ? $this->id($pair['right'] ?? null) : null;

            if ($l === null || $r === null || ! isset($left[$l]) || ! isset($right[$r])) {
                return $this->reject('matching pair references an unknown id');
            }
            if (isset($map[$l]) || isset($usedRight[$r])) {
                return $this->reject('matching ids must be used exactly once');
            }

            $map[$l] = $r;
            $usedRight[$r] = true;
        }

        return $this->shuffleMatching($left, $right, $map);
    }

    private function shortAnswer(array $answer): ?array
    {
        $text = $this->nonEmptyString($answer['text'] ?? null);
        $points = $answer['key_points'] ?? null;

        if ($text === null) {
            return $this->reject('short_answer answer.text is required');
        }
        if (! is_array($points) || ! array_is_list($points) || $points === []) {
            return $this->reject('short_answer answer.key_points must be a non-empty array');
        }

        $normalizedPoints = [];
        foreach ($points as $point) {
            $point = $this->nonEmptyString($point);
            if ($point === null) {
                return $this->reject('short_answer key_points must be non-empty strings');
            }
            $normalizedPoints[] = $point;
        }

        return ['payload' => [], 'answer' => ['text' => $text, 'key_points' => $normalizedPoints]];
    }

    /**
     * Shuffle the right column and relabel its ids (a, b, c, ...) in display order,
     * so neither position nor id reveals which items belong together.
     *
     * @param  array<string, string>  $left
     * @param  array<string, string>  $right
     * @param  array<string, string>  $map  left id => right id
     */
    private function shuffleMatching(array $left, array $right, array $map): array
    {
        $leftIds = array_keys($left);
        $alignedRight = array_map(fn (string $l) => $map[$l], $leftIds);
        $order = $alignedRight;

        // Prefer an order where no right item sits beside its partner.
        for ($attempt = 0; $attempt < 20; $attempt++) {
            shuffle($order);
            $fixedPoints = array_filter(array_keys($order), fn (int $i) => $order[$i] === $alignedRight[$i]);
            if ($fixedPoints === []) {
                break;
            }
        }

        $newRightIds = [];
        $rightColumn = [];
        foreach ($order as $index => $oldId) {
            $newId = chr(ord('a') + $index);
            $newRightIds[$oldId] = $newId;
            $rightColumn[] = ['id' => $newId, 'text' => $right[$oldId]];
        }

        $leftColumn = [];
        $pairs = [];
        foreach ($leftIds as $index => $oldId) {
            $newId = (string) ($index + 1);
            $leftColumn[] = ['id' => $newId, 'text' => $left[$oldId]];
            $pairs[] = ['left' => $newId, 'right' => $newRightIds[$map[$oldId]]];
        }

        return [
            'payload' => ['left' => $leftColumn, 'right' => $rightColumn],
            'answer' => ['pairs' => $pairs],
        ];
    }

    /**
     * @return array<string, string>|null id => text, in original order
     */
    private function matchingColumn(mixed $column): ?array
    {
        if (! is_array($column) || ! array_is_list($column)) {
            return null;
        }

        $items = [];
        foreach ($column as $item) {
            $id = is_array($item) ? $this->id($item['id'] ?? null) : null;
            $text = is_array($item) ? $this->nonEmptyString($item['text'] ?? null) : null;

            if ($id === null || $text === null || isset($items[$id])) {
                return null;
            }
            $items[$id] = $text;
        }

        return $items;
    }

    private function id(mixed $value): ?string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function reject(string $reason): null
    {
        $this->lastError = $reason;

        return null;
    }
}
