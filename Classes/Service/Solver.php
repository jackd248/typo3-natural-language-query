<?php

declare(strict_types=1);

namespace KonradMichalik\Typo3NaturalLanguageQuery\Service;

use KonradMichalik\Typo3NaturalLanguageQuery\Connector\OpenAIConnector;
use KonradMichalik\Typo3NaturalLanguageQuery\Entity\Query;
use KonradMichalik\Typo3NaturalLanguageQuery\Exception\SqlQueryIsNotValid;
use KonradMichalik\Typo3NaturalLanguageQuery\Type\QueryType;
use OpenAI\Exceptions\ErrorException;

final class Solver
{
    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly OpenAIConnector $openAIConnector
    ) {
    }

    public function solve(?Query $query = null, ?string $question = null, ?string $table = null): Query
    {
        if ($query === null && $question === null) {
            throw new \InvalidArgumentException('Either a query or a question must be provided', 1631710733);
        }

        if ($query === null) {
            $query = new Query($question, $table);
        }

        if ($table === null && $query->table === null) {
            $this->openAIConnector->chat($query, QueryType::TABLE);
        }

        $this->openAIConnector->chat($query);

        try {
            $this->databaseService->runDatabaseQuery($query);
        } catch (SqlQueryIsNotValid $sqlError) {
            $query->sqlQuery = null;
            $query->sqlError = $sqlError->getMessage();
            $this->solve($query);
        }

        try {
            $this->openAIConnector->chat($query, QueryType::ANSWER);
            $this->databaseService->resolveResultSet($query);
        } catch (ErrorException $errorException) {
            // If the prompt is too long, we can try to solve the query with less information
            if (str_contains($errorException->getErrorMessage(), 'Please reduce your prompt; or completion length.')) {
                $query->maximumPromptLengthExceeded = true;
                $query->sqlQuery = null;
                $query->sqlResult = null;
                $this->solve($query);
            }
        }

        return $query;
    }
}
