<?php

declare(strict_types=1);

namespace KonradMichalik\Typo3NaturalLanguageQuery\Service;

use Doctrine\DBAL\Exception\SyntaxErrorException;
use KonradMichalik\Typo3NaturalLanguageQuery\Configuration;
use KonradMichalik\Typo3NaturalLanguageQuery\Entity\Query;
use KonradMichalik\Typo3NaturalLanguageQuery\Exception\ForbiddenQuery;
use KonradMichalik\Typo3NaturalLanguageQuery\Exception\PotentiallyUnsafeQuery;
use KonradMichalik\Typo3NaturalLanguageQuery\Exception\SqlQueryIsNotValid;
use PHPSQLParser\PHPSQLParser;
use TYPO3\CMS\Core;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class DatabaseService
{
    protected array $configuration;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ExtensionConfiguration $extensionConfiguration
    ) {
        $this->configuration = $this->extensionConfiguration->get(Configuration::EXT_KEY);
    }

    /**
    * @throws \KonradMichalik\Typo3NaturalLanguageQuery\Exception\PotentiallyUnsafeQuery
    * @throws \Doctrine\DBAL\Exception
    * @throws \KonradMichalik\Typo3NaturalLanguageQuery\Exception\SqlQueryIsNotValid
    */
    public function runDatabaseQuery(Query &$query): void
    {
        $this->ensureQueryIsSafe($query->sqlQuery);
        try {
            $result = json_encode($this->connectionPool->getConnectionForTable($query->table)->executeQuery($query->sqlQuery)->fetchAllAssociative());
        } catch (SyntaxErrorException $e) {
            $message = $e->getMessage();
            $query->sqlError = $message;
            throw SqlQueryIsNotValid::fromQuery($query, $message);
        }
        $query->sqlResult = $result;
    }

    /**
    * @throws \Doctrine\DBAL\Exception
    */
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

    public function resolveResultSet(Query &$query): void
    {
        if ($query->resultSet !== null && $query->resultSet !== [] && $this->isArrayOnlyIntegerLike($query->resultSet)) {
            $uids = $query->resultSet;
        } else {
            $uids = $this->extractUids(json_decode($query->sqlResult, true));
        }
        if ($uids === []) {
            return;
        }

        $sql = sprintf('SELECT * FROM %s WHERE uid IN (%s)', $query->table, implode(',', $uids));
        $query->resultSet = $this->connectionPool->getConnectionForTable($query->table)->executeQuery($sql)->fetchAllAssociative();
    }

    /**
    * @throws \KonradMichalik\Typo3NaturalLanguageQuery\Exception\PotentiallyUnsafeQuery
    */
    private function ensureQueryIsSafe(string $query): void
    {
        $parser = new PHPSQLParser();
        $parsedSqlObject = $parser->parse($query);

        $forbiddenWords = ['INSERT', 'UPDATE', 'DELETE', 'ALTER', 'DROP', 'TRUNCATE', 'CREATE', 'REPLACE'];
        $matches = array_intersect(array_map('strtolower', array_keys($parsedSqlObject)), array_map('strtolower', $forbiddenWords));
        if (!empty($matches)) {
            throw PotentiallyUnsafeQuery::fromQuery($query, implode(', ', $matches));
        }

        if (in_array(strtolower($parsedSqlObject['FROM'][0]['table']), array_map('strtolower', explode(',', $this->configuration['database']['ignore_tables'])))) {
            throw ForbiddenQuery::ignoredTable($query, $parsedSqlObject['FROM']['table']);
        }
    }

    private function isArrayOnlyIntegerLike(array $array): bool
    {
        return array_filter($array, fn ($value) => !(is_int($value) || (is_string($value) && ctype_digit($value)))) === [];
    }

    private function extractUids(array $data): array
    {
        $uids = [];

        foreach ($data as $item) {
            if (is_array($item) && isset($item['uid'])) {
                $uids[] = $item['uid'];
            }
        }

        ksort($uids);
        return array_unique($uids);
    }
}
