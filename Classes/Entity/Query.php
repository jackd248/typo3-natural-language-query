<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Entity;

final class Query
{
    public string $table;
    public string $question;
    public ?string $sqlQuery = null;
    public ?string $sqlResult = null;
    public ?string $answer = null;

    public function __construct(string $table, string $question)
    {
        $this->table = $table;
        $this->question = $question;
    }
}
