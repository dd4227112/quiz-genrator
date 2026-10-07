<?php

namespace App\Enums;

enum QuestionType: string
{
    case TrueFalse = 'true_false';
    case MultipleChoice = 'multiple_choice';
    case Matching = 'matching';
    case ShortAnswer = 'short_answer';

    public function label(): string
    {
        return match ($this) {
            self::TrueFalse => 'True/False',
            self::MultipleChoice => 'Multiple Choice',
            self::Matching => 'Matching',
            self::ShortAnswer => 'Short Answer',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
