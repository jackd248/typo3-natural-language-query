<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Service;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class SchemaService
{
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

    private function getFieldsByTable(string $tableName): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName('Default');
        $schemaManager = $connection->getSchemaInformation();

        return $schemaManager->introspectSchema()->getTable($tableName)->getColumns();
    }
}
