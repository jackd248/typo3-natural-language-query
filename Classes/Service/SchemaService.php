<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Service;

use TYPO3\CMS\Core;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class SchemaService
{
    public function describeTables(): array
    {
        $tables = [];
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(Core\Database\ConnectionPool::DEFAULT_CONNECTION_NAME);
        $schemaManager = $connection->getSchemaInformation();
        foreach ($schemaManager->introspectSchema()->getTables() as $table) {
            if (str_starts_with($table->getName(), 'sys_') || str_starts_with($table->getName(), 'cache_')) {
                continue;
            }
            $tables[] = [
                'name' => $table->getName(),
                'label' => isset($GLOBALS['TCA'][$table->getName()]['ctrl']['title']) ? $this->getLanguageService()->sL($GLOBALS['TCA'][$table->getName()]['ctrl']['title']) : $table->getName(),
            ];
        }
        return $tables;
    }

    public function describeTable(string $table): array
    {
        return [
            'name' => $table,
            'columns' => $this->describeFieldsOfTable($table),
        ];
    }

    private function describeFieldsOfTable(string $table): array
    {
        $fields = [];
        foreach ($this->getFieldsByTable($table) as $field) {
            $platform = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(Core\Database\ConnectionPool::DEFAULT_CONNECTION_NAME)->getDatabasePlatform();
            $fieldOptions = ['name' => $field->getName(), 'length' => $field->getLength()];
            $fields[] = [
                'name' => $field->getName(),
                'type' => $field->getType()->getSQLDeclaration($fieldOptions, $platform),
            ];
        }
        return $fields;
    }

    private function getFieldsByTable(string $tableName): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(Core\Database\ConnectionPool::DEFAULT_CONNECTION_NAME);
        $schemaManager = $connection->getSchemaInformation();

        return $schemaManager->introspectSchema()->getTable($tableName)->getColumns();
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
