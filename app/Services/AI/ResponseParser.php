<?php

namespace App\Services\AI;

class ResponseParser
{
    /**
     * Extract the list of question items from a model response.
     *
     * Returns null when nothing could be parsed. When $allowSalvage is true
     * (truncated output), complete question objects are recovered from a
     * partially written "questions" array.
     *
     * @return list<mixed>|null
     */
    public static function questions(string $content, bool $allowSalvage = false): ?array
    {
        $json = self::stripFences($content);
        $decoded = json_decode($json, true);

        if (is_array($decoded)) {
            if (isset($decoded['questions']) && is_array($decoded['questions'])) {
                return array_values($decoded['questions']);
            }

            // Tolerate a bare array of questions.
            if (array_is_list($decoded)) {
                return $decoded;
            }

            return null;
        }

        if (! $allowSalvage) {
            return null;
        }

        $salvaged = self::salvageObjects($json);

        return $salvaged === [] ? null : $salvaged;
    }

    public static function stripFences(string $content): string
    {
        $content = trim($content);

        if (preg_match('/^```[a-zA-Z0-9_-]*\s*\n?(.*?)\n?```\s*$/s', $content, $matches)) {
            return trim($matches[1]);
        }

        // An opening fence whose closing fence was cut off by truncation.
        return trim((string) preg_replace('/^```[a-zA-Z0-9_-]*\s*\n?/', '', $content));
    }

    /**
     * Walk the "questions" array and decode each fully-closed object,
     * ignoring the trailing partial one.
     *
     * @return list<array>
     */
    private static function salvageObjects(string $json): array
    {
        $keyPos = strpos($json, '"questions"');
        $start = strpos($json, '[', $keyPos === false ? 0 : $keyPos);

        if ($start === false) {
            return [];
        }

        $items = [];
        $depth = 0;
        $inString = false;
        $escaped = false;
        $objectStart = null;
        $length = strlen($json);

        for ($i = $start; $i < $length; $i++) {
            $char = $json[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{' || $char === '[') {
                $depth++;
                if ($char === '{' && $depth === 2) {
                    $objectStart = $i;
                }
            } elseif ($char === '}' || $char === ']') {
                if ($char === '}' && $depth === 2 && $objectStart !== null) {
                    $item = json_decode(substr($json, $objectStart, $i - $objectStart + 1), true);
                    if (is_array($item)) {
                        $items[] = $item;
                    }
                    $objectStart = null;
                }

                if (--$depth === 0) {
                    break;
                }
            }
        }

        return $items;
    }
}
