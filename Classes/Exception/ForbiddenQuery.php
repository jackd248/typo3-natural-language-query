<?php

declare(strict_types=1);

namespace KonradMichalik\Typo3NaturalLanguageQuery\Exception;

use Exception;

final class ForbiddenQuery extends Exception
{
    public static function ignoredTable(string $query, string $table): self
    {
        return new self("The query `{$query}` is tries to fetch from forbidden table `{$table}`.");
    }
}
