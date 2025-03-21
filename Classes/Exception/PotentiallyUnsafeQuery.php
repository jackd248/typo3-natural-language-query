<?php

declare(strict_types=1);

namespace KonradMichalik\Typo3NaturalLanguageQuery\Exception;

use Exception;

final class PotentiallyUnsafeQuery extends Exception
{
    public static function fromQuery(string $query, string $forbiddenWord): self
    {
        return new self("The query `{$query}` is potentially unsafe, containing the forbidden word `{$forbiddenWord}`.");
    }
}
