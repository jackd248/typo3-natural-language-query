<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Service;

use Kmi\Typo3NaturalLanguageQuery\Configuration;
use Kmi\Typo3NaturalLanguageQuery\Entity\Query;
use Kmi\Typo3NaturalLanguageQuery\Utility\GeneralHelper;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

final class DatabaseService
{
    public function runDatabaseQuery(Query &$query): void
    {
        $this->ensureQueryIsSafe($query->sqlQuery);

        if (substr($query->sqlQuery, -1) === "\"") {
            $query->sqlQuery = substr($query->sqlQuery, 0, -1);
        }
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($query->table);

        $result = json_encode($connection->executeQuery($query->sqlQuery)->fetchAssociative());
        $query->sqlResult = $result;
    }

    public function getDialect(): string
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName('Default');
        // ToDo
        return 'MariaDB';
    }

    private function ensureQueryIsSafe(string $query): void
    {
        $query = strtolower($query);
        $forbiddenWords = ['insert ', 'update ', 'delete ', 'alter ', 'drop ', 'truncate ', 'create ', 'replace '];
        foreach ($forbiddenWords as $word) {
            if (strpos($query, $word) !== false) {
                throw new \Exception('Query contains potential forbidden word: ' . $word);
            }
        }
    }

    private function getFieldsByTable(string $tableName): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName('Default');
        $schemaManager = $connection->getSchemaInformation();

        return $schemaManager->introspectSchema()->getTable($tableName)->getColumns();
    }

    public function describeFieldsOfTable(string $table): array
    {
        $fields = [];
        foreach ($this->getFieldsByTable($table) as $field) {
            $platform = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName('Default')->getDatabasePlatform();
            $fieldOptions = ['name' => $field->getName(), 'length' => $field->getLength()];
            $fields[] = [
                'name' => $field->getName(),
                'type' => $field->getType()->getSQLDeclaration($fieldOptions, $platform),
            ];
        }
        return $fields;
    }
}
