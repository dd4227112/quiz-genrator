<?php

use App\Services\AI\QuestionDistribution;

it('splits counts as evenly as possible, remainder to earlier types', function (int $count, array $types, array $expected) {
    expect(QuestionDistribution::distribute($count, $types))->toBe($expected);
})->with([
    '10 across 3' => [10, ['true_false', 'multiple_choice', 'matching'], ['true_false' => 4, 'multiple_choice' => 3, 'matching' => 3]],
    '12 across 4' => [12, ['true_false', 'multiple_choice', 'matching', 'short_answer'], ['true_false' => 3, 'multiple_choice' => 3, 'matching' => 3, 'short_answer' => 3]],
    '7 across 2' => [7, ['short_answer', 'matching'], ['short_answer' => 4, 'matching' => 3]],
    '5 across 1' => [5, ['multiple_choice'], ['multiple_choice' => 5]],
    'fewer than types' => [2, ['true_false', 'multiple_choice', 'matching'], ['true_false' => 1, 'multiple_choice' => 1]],
    'duplicates ignored' => [4, ['matching', 'matching', 'true_false'], ['matching' => 2, 'true_false' => 2]],
    'zero' => [0, ['true_false'], []],
    'no types' => [5, [], []],
]);

it('always sums to the requested count', function () {
    foreach (range(1, 30) as $count) {
        $result = QuestionDistribution::distribute($count, ['true_false', 'multiple_choice', 'matching']);
        expect(array_sum($result))->toBe($count);
    }
});

it('keeps small requests in a single batch', function () {
    $distribution = ['true_false' => 5, 'matching' => 5];

    expect(QuestionDistribution::batches($distribution, 15))->toBe([$distribution]);
});

it('splits large requests into batches no bigger than the batch size while keeping per-type totals', function () {
    $distribution = QuestionDistribution::distribute(30, ['true_false', 'multiple_choice', 'matching', 'short_answer']);
    $batches = QuestionDistribution::batches($distribution, 15);

    expect($batches)->toHaveCount(2);

    $totals = [];
    foreach ($batches as $batch) {
        expect(array_sum($batch))->toBeLessThanOrEqual(15);
        foreach ($batch as $type => $count) {
            $totals[$type] = ($totals[$type] ?? 0) + $count;
        }
    }

    ksort($totals);
    ksort($distribution);
    expect($totals)->toBe($distribution);
});

it('handles an uneven split into three batches', function () {
    $batches = QuestionDistribution::batches(['multiple_choice' => 31], 15);

    expect($batches)->toHaveCount(3)
        ->and(array_sum(array_map('array_sum', $batches)))->toBe(31)
        ->and(max(array_map('array_sum', $batches)))->toBeLessThanOrEqual(15);
});
