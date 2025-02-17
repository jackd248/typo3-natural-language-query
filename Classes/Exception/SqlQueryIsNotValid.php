<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Exception;

use Exception;
use Kmi\Typo3NaturalLanguageQuery\Entity\Query;

final class SqlQueryIsNotValid extends Exception
{
    public static function fromQuery(Query $query, string $message): self
    {
        return new self("The query `{$query->sqlQuery}` has the following sql error: `{$message}`.");
    }
}
