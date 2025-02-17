<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Exception;

use Exception;
use Kmi\Typo3NaturalLanguageQuery\Entity\Query;

final class PotentiallyUnsafeQuery extends Exception
{
    public static function fromQuery(string $query, string $forbiddenWord): self
    {
        return new self("The query `{$query}` is potentially unsafe, containing the forbidden word `{$forbiddenWord}`.");
    }
}

