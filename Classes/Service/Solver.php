<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Service;

use Kmi\Typo3NaturalLanguageQuery\Connector\OpenAIConnector;
use Kmi\Typo3NaturalLanguageQuery\Entity\Query;
use Kmi\Typo3NaturalLanguageQuery\Type\QueryType;

final class Solver
{
    public function __construct(protected DatabaseService $databaseService, protected OpenAIConnector $openAIConnector)
    {
    }

    public function solve(string $question, ?string $table = null): Query
    {
        $query = new Query($question, $table);

        if ($table === null) {
            $this->openAIConnector->chat($query, QueryType::TABLE);
        }

        $this->openAIConnector->chat($query);
        $this->databaseService->runDatabaseQuery($query);

        $this->openAIConnector->chat($query, QueryType::ANSWER);
        return $query;
    }
}
