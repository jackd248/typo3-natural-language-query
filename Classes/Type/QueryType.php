<?php

declare(strict_types=1);

namespace KonradMichalik\Typo3NaturalLanguageQuery\Type;

enum QueryType: string
{
    case TABLE = 'table';
    case QUERY = 'sqlQuery';
    case ANSWER = 'answer';
}
