<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Service;

use Kmi\Typo3NaturalLanguageQuery\Entity\Query;
use Kmi\Typo3NaturalLanguageQuery\Exception\PotentiallyUnsafeQuery;
use TYPO3\CMS\Core;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class DatabaseService
{
    public function __construct(private readonly ConnectionPool $connectionPool)
    {
    }

    public function runDatabaseQuery(Query &$query): void
    {
        $this->ensureQueryIsSafe($query->sqlQuery);
        $result = json_encode($this->connectionPool->getConnectionForTable($query->table)->executeQuery($query->sqlQuery)->fetchAssociative());
        $query->sqlResult = $result;
    }

    public function getDatabasePlatformAndVersion(): ?string
    {
        try {
            $connection = $this->connectionPool->getConnectionByName(
                Core\Database\ConnectionPool::DEFAULT_CONNECTION_NAME,
            );
        } catch (\Exception) {
            return null;
        }

        return $connection->getServerVersion();
    }

    private function ensureQueryIsSafe(string $query): void
    {
        $query = strtolower($query);
        $forbiddenWords = ['insert ', 'update ', 'delete ', 'alter ', 'drop ', 'truncate ', 'create ', 'replace '];
        foreach ($forbiddenWords as $word) {
            if (str_contains($query, $word)) {
                throw PotentiallyUnsafeQuery::fromQuery($query, $word);
            }
        }
    }
}
