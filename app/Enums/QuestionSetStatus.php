<?php

namespace App\Enums;

enum QuestionSetStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
