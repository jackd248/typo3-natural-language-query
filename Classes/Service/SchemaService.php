<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Service;

use Kmi\Typo3NaturalLanguageQuery\Entity\Query;
use Kmi\Typo3NaturalLanguageQuery\Utility\HttpUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class SchemaService
{
    /**
    * @throws \Doctrine\DBAL\Exception
    */
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

    /**
    * @throws \Doctrine\DBAL\Exception
    */
    public function describeTable(string $table): array
    {
        return [
            'name' => $table,
            'columns' => $this->describeFieldsOfTable($table),
        ];
    }

    public function prepareResultSet(Query $query): array
    {
        $result = [];
        foreach ($query->resultSet as $resultRow) {
            $result[] = [
                'uid' => $resultRow['uid'],
                'label' => $this->getRecordTitle($query->table, $resultRow),
                'link' => HttpUtility::buildAbsoluteUrlFromRoute('record_edit', ['edit' => [$query->table => [$resultRow['uid'] => 'edit']]]),
            ];
        }
        return $result;
    }

    /**
    * @throws \Doctrine\DBAL\Exception
    */
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

    /**
    * I need to adopt this from BackendUtility because it throws errors in CLI context
    *
    * @param $table
    * @param $row
    * @return string
    */
    private function getRecordTitle($table, $row): string
    {
        $params = [];
        if (!empty($GLOBALS['TCA'][$table]['ctrl']['label_userFunc'])) {
            try {
                $params['table'] = $table;
                $params['row'] = $row;
                $params['title'] = '';
                $params['options'] = $GLOBALS['TCA'][$table]['ctrl']['label_userFunc_options'] ?? [];

                $null = null;
                GeneralUtility::callUserFunction($GLOBALS['TCA'][$table]['ctrl']['label_userFunc'], $params, $null);
                $recordTitle = (string)($params['title'] ?? '');
                return $recordTitle;
            } catch (\Exception $e) {
            }
        }
        // No userFunc: Build label
        $ctrlLabel = $GLOBALS['TCA'][$table]['ctrl']['label'] ?? '';
        $ctrlLabelValue = $row[$ctrlLabel] ?? '';
        $recordTitle = BackendUtility::getProcessedValue(
            $table,
            $ctrlLabel,
            (string)$ctrlLabelValue,
            0,
            false,
            false,
            $row['uid'] ?? null,
            false,
            0,
            $row
        ) ?? '';

        if (!empty($GLOBALS['TCA'][$table]['ctrl']['label_alt'])
            && (!empty($GLOBALS['TCA'][$table]['ctrl']['label_alt_force']) || $recordTitle === '')
        ) {
            $altFields = GeneralUtility::trimExplode(',', $GLOBALS['TCA'][$table]['ctrl']['label_alt'], true);
            $tA = [];
            if (!empty($recordTitle)) {
                $tA[] = $recordTitle;
            }
            foreach ($altFields as $fN) {
                // Format string value - leave array value (e.g. for select fields) as is
                if (!is_array($row[$fN] ?? false)) {
                    $recordTitle = trim(strip_tags((string)($row[$fN] ?? '')));
                }
                if ($recordTitle !== '') {
                    $recordTitle = BackendUtility::getProcessedValue($table, $fN, $recordTitle, 0, false, false, $row['uid'] ?? 0, true, 0, $row);
                    if (!($GLOBALS['TCA'][$table]['ctrl']['label_alt_force'] ?? false)) {
                        break;
                    }
                    $tA[] = $recordTitle;
                }
            }
            if ($GLOBALS['TCA'][$table]['ctrl']['label_alt_force'] ?? false) {
                $recordTitle = implode(', ', $tA);
            }
        }

        return $recordTitle;
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
