<?php

namespace App\Services\AI;

class QuestionDistribution
{
    /**
     * Spread $count as evenly as possible across $types, giving the remainder
     * to the earliest types (e.g. 10 across 3 types => 4/3/3).
     *
     * @param  list<string>  $types
     * @return array<string, int> type => count (types with zero are omitted)
     */
    public static function distribute(int $count, array $types): array
    {
        $types = array_values(array_unique($types));

        if ($count <= 0 || $types === []) {
            return [];
        }

        $base = intdiv($count, count($types));
        $remainder = $count % count($types);
        $result = [];

        foreach ($types as $index => $type) {
            $amount = $base + ($index < $remainder ? 1 : 0);

            if ($amount > 0) {
                $result[$type] = $amount;
            }
        }

        return $result;
    }

    /**
     * Split a per-type distribution into batches of at most $batchSize questions,
     * keeping the per-type totals intact.
     *
     * @param  array<string, int>  $distribution
     * @return list<array<string, int>>
     */
    public static function batches(array $distribution, int $batchSize): array
    {
        $batchSize = max(1, $batchSize);
        $total = array_sum($distribution);

        if ($total <= $batchSize) {
            return $total > 0 ? [$distribution] : [];
        }

        $batchCount = (int) ceil($total / $batchSize);
        $batches = array_fill(0, $batchCount, []);
        $sizes = array_fill(0, $batchCount, 0);

        // Deal questions round-robin so each batch gets a similar mix of types.
        $cursor = 0;
        foreach ($distribution as $type => $amount) {
            for ($i = 0; $i < $amount; $i++) {
                while ($sizes[$cursor] >= $batchSize) {
                    $cursor = ($cursor + 1) % $batchCount;
                }
                $batches[$cursor][$type] = ($batches[$cursor][$type] ?? 0) + 1;
                $sizes[$cursor]++;
                $cursor = ($cursor + 1) % $batchCount;
            }
        }

        return array_values(array_filter($batches));
    }
}
