<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Entity;

final class Query
{
    public string $question;
    public ?string $table = null;
    public ?string $sqlQuery = null;
    public ?string $sqlResult = null;
    public ?string $answer = null;

    public function __construct(string $question, ?string $table = null)
    {
        $this->table = $table;
        $this->question = $question;
    }
}
