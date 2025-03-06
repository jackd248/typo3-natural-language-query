<?php

declare(strict_types=1);

namespace KonradMichalik\Typo3NaturalLanguageQuery\Service;

use Doctrine\DBAL\Exception\InvalidColumnDeclaration;
use Doctrine\DBAL\Exception\InvalidColumnType;
use Doctrine\DBAL\Exception\InvalidColumnType\ColumnPrecisionRequired;
use KonradMichalik\Typo3NaturalLanguageQuery\Configuration;
use KonradMichalik\Typo3NaturalLanguageQuery\Entity\Query;
use KonradMichalik\Typo3NaturalLanguageQuery\Utility\HttpUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class SchemaService
{
    protected array $configuration;

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration
    ) {
        $this->configuration = $this->extensionConfiguration->get(Configuration::EXT_KEY);
    }

    /**
    * @throws \Doctrine\DBAL\Exception
    */
    public function describeTables(): array
    {
        $tables = [];
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName($this->configuration['database']['connection']);
        $schemaManager = $connection->getSchemaInformation();
        foreach ($schemaManager->introspectSchema()->getTables() as $table) {
            if ($this->matchesIgnoredTables($table->getName())) {
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
                'link' => $this->getRecordLink($query->table, $resultRow['uid']),
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
            if ($this->matchesIgnoredFields($field->getName())) {
                continue;
            }

            $platform = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName($this->configuration['database']['connection'])->getDatabasePlatform();
            $fieldOptions = ['name' => $field->getName(), 'length' => $field->getLength()];
            $type = '';
            try {
                $type = $field->getType()->getSQLDeclaration($fieldOptions, $platform);
            } catch (ColumnPrecisionRequired $exception) {
                // ToDo
            } catch (InvalidColumnDeclaration $exception) {
                // ToDo
            } catch (InvalidColumnType $exception) {
                // ToDo
            }
            $fields[] = [
                'name' => $field->getName(),
                'type' => $type,
            ];
        }
        return $fields;
    }

    /**
    * @throws \Doctrine\DBAL\Schema\SchemaException
    * @throws \Doctrine\DBAL\Exception
    */
    private function getFieldsByTable(string $tableName): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName($this->configuration['database']['connection']);
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

    private function matchesIgnoredTables(string $table): bool
    {
        foreach (explode(',', $this->configuration['database']['ignore_tables']) as $ignoredTable) {
            if (str_contains($ignoredTable, '*')) {
                $pattern = str_replace('*', '.*', $ignoredTable);
                if (preg_match('/^' . $pattern . '$/', $table)) {
                    return true;
                }
            } elseif ($table === $ignoredTable) {
                return true;
            }
        }
        return false;
    }

    private function matchesIgnoredFields(string $field): bool
    {
        foreach (explode(',', $this->configuration['database']['ignore_fields']) as $ignoredTable) {
            if ($field === $ignoredTable) {
                return true;
            }
        }
        return false;
    }

    private function getRecordLink(string $table, int $uid): string
    {
        return match ($table) {
            'pages' => HttpUtility::buildAbsoluteUrlFromRoute('web_layout', ['id' => $uid]),
            default => HttpUtility::buildAbsoluteUrlFromRoute('record_edit', ['edit' => [$table => [$uid => 'edit']]])
        };
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
