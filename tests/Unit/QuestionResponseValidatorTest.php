<?php

use App\Services\AI\QuestionResponseValidator;

beforeEach(function () {
    $this->validator = new QuestionResponseValidator;
});

function validQuestion(string $type): array
{
    return json_decode(json_encode(sampleQuestion($type)), true);
}

it('accepts a valid question of each type', function (string $type) {
    $result = $this->validator->validate(validQuestion($type));

    expect($result)->not->toBeNull()
        ->and($result['type'])->toBe($type)
        ->and($result)->toHaveKeys(['type', 'prompt', 'payload', 'answer', 'explanation']);
})->with(['true_false', 'multiple_choice', 'matching', 'short_answer']);

it('rejects items that are not objects or miss common fields', function (mixed $item) {
    expect($this->validator->validate($item))->toBeNull();
})->with([
    'string' => ['not a question'],
    'unknown type' => [['type' => 'essay', 'prompt' => 'x', 'payload' => [], 'answer' => [], 'explanation' => 'y']],
    'missing prompt' => [array_merge(validQuestion('true_false'), ['prompt' => ''])],
    'missing explanation' => [array_diff_key(validQuestion('true_false'), ['explanation' => true])],
    'answer not object' => [array_merge(validQuestion('true_false'), ['answer' => true])],
]);

it('rejects malformed true/false questions', function (array $answer) {
    $question = array_merge(validQuestion('true_false'), ['answer' => $answer]);

    expect($this->validator->validate($question))->toBeNull();
})->with([
    'string value' => [['value' => 'true']],
    'int value' => [['value' => 1]],
    'missing value' => [['correct' => true]],
]);

it('rejects malformed multiple choice questions', function (callable $mutate) {
    $question = validQuestion('multiple_choice');
    $mutate($question);

    expect($this->validator->validate($question))->toBeNull();
})->with([
    'three options' => [fn (&$q) => array_pop($q['payload']['options'])],
    'five options' => [fn (&$q) => $q['payload']['options'][] = ['key' => 'E', 'text' => 'Echo']],
    'wrong keys' => [fn (&$q) => $q['payload']['options'][3]['key'] = 'E'],
    'duplicate keys' => [fn (&$q) => $q['payload']['options'][3]['key'] = 'A'],
    'empty option text' => [fn (&$q) => $q['payload']['options'][0]['text'] = ' '],
    'answer key missing from options' => [fn (&$q) => $q['answer']['key'] = 'E'],
    'no answer key' => [fn (&$q) => $q['answer'] = ['value' => 'A']],
    'no options' => [fn (&$q) => $q['payload'] = []],
]);

it('rejects malformed matching questions', function (callable $mutate) {
    $question = validQuestion('matching');
    $mutate($question);

    expect($this->validator->validate($question))->toBeNull();
})->with([
    'too few pairs' => [function (&$q) {
        array_pop($q['payload']['left']);
        array_pop($q['payload']['right']);
        array_pop($q['answer']['pairs']);
        array_pop($q['payload']['left']);
        array_pop($q['payload']['right']);
        array_pop($q['answer']['pairs']);
    }],
    'too many pairs' => [function (&$q) {
        foreach (['5' => 'e', '6' => 'f', '7' => 'g'] as $l => $r) {
            $q['payload']['left'][] = ['id' => (string) $l, 'text' => "L{$l}"];
            $q['payload']['right'][] = ['id' => $r, 'text' => "R{$r}"];
            $q['answer']['pairs'][] = ['left' => (string) $l, 'right' => $r];
        }
    }],
    'uneven columns' => [fn (&$q) => array_pop($q['payload']['right'])],
    'unknown right id' => [fn (&$q) => $q['answer']['pairs'][0]['right'] = 'z'],
    'unknown left id' => [fn (&$q) => $q['answer']['pairs'][0]['left'] = '9'],
    'right id reused' => [fn (&$q) => $q['answer']['pairs'][1]['right'] = 'a'],
    'left id reused' => [fn (&$q) => $q['answer']['pairs'][1]['left'] = '1'],
    'missing pair' => [fn (&$q) => array_pop($q['answer']['pairs'])],
    'duplicate column ids' => [fn (&$q) => $q['payload']['left'][1]['id'] = '1'],
]);

it('rejects malformed short answer questions', function (array $answer) {
    $question = array_merge(validQuestion('short_answer'), ['answer' => $answer]);

    expect($this->validator->validate($question))->toBeNull();
})->with([
    'empty text' => [['text' => '', 'key_points' => ['a']]],
    'missing key points' => [['text' => 'Answer']],
    'key points not array' => [['text' => 'Answer', 'key_points' => 'a, b']],
    'empty key points' => [['text' => 'Answer', 'key_points' => []]],
    'blank key point' => [['text' => 'Answer', 'key_points' => ['ok', '']]],
]);

it('shuffles matching so the right column order and ids do not reveal the pairs', function () {
    $result = $this->validator->validate(validQuestion('matching'));

    $left = collect($result['payload']['left'])->pluck('text', 'id');
    $right = collect($result['payload']['right'])->pluck('text', 'id');

    // Right ids are relabelled a, b, c, d in display order.
    expect(array_column($result['payload']['right'], 'id'))->toBe(['a', 'b', 'c', 'd'])
        ->and(array_column($result['payload']['left'], 'id'))->toBe(['1', '2', '3', '4']);

    // No item sits directly beside its partner.
    foreach ($result['answer']['pairs'] as $index => $pair) {
        expect($pair['right'])->not->toBe(chr(ord('a') + $index));
    }

    // The original pairing is preserved through the relabelling.
    $expected = ['One' => 'Uno', 'Two' => 'Dos', 'Three' => 'Tres', 'Four' => 'Cuatro'];
    foreach ($result['answer']['pairs'] as $pair) {
        expect($right[$pair['right']])->toBe($expected[$left[$pair['left']]]);
    }
});

it('normalizes multiple choice keys and option order', function () {
    $question = validQuestion('multiple_choice');
    $question['payload']['options'] = array_reverse($question['payload']['options']);
    $question['answer']['key'] = 'c';

    $result = $this->validator->validate($question);

    expect(array_column($result['payload']['options'], 'key'))->toBe(['A', 'B', 'C', 'D'])
        ->and($result['answer'])->toBe(['key' => 'C']);
});

it('normalizes true/false and short answer payloads to empty objects', function () {
    $tf = validQuestion('true_false');
    $tf['payload'] = ['unexpected' => 'data'];

    expect($this->validator->validate($tf)['payload'])->toBe([]);
});
