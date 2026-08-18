<?php

namespace FriendsOfRedaxo\ClearContent\Cleanup;

use rex;
use rex_extension;
use rex_extension_point;
use rex_functional_exception;
use rex_i18n;
use rex_sql;
use rex_sql_table;
use rex_yform_manager_table;
use rex_yform_manager_table_api;
use Throwable;

final class TableCleaner
{
    /** @return array<string, string> table name => localized label */
    public function getAvailableTables(): array
    {
        $tables = [];
        foreach (rex_yform_manager_table::getAll() as $table) {
            $tableName = $table->getTableName();
            if ($this->isProtected($tableName) || !rex_sql_table::get($tableName)->exists()) {
                continue;
            }
            $tables[$tableName] = $table->getNameLocalized();
        }
        asort($tables, SORT_NATURAL | SORT_FLAG_CASE);

        return $tables;
    }

    /** @param list<string> $tableNames */
    public function count(array $tableNames): int
    {
        $total = 0;
        foreach ($this->validate($tableNames) as $tableName) {
            $sql = rex_sql::factory();
            $sql->setQuery('SELECT COUNT(*) AS amount FROM ' . $sql->escapeIdentifier($tableName));
            $total += (int) $sql->getValue('amount');
        }

        return $total;
    }

    /** @param list<string> $tableNames
     *  @return list<string>
     */
    public function describe(array $tableNames): array
    {
        $available = $this->getAvailableTables();
        $details = [];
        foreach ($this->validate($tableNames) as $tableName) {
            $sql = rex_sql::factory();
            $sql->setQuery('SELECT COUNT(*) AS amount FROM ' . $sql->escapeIdentifier($tableName));
            $details[] = rex_i18n::msg(
                'clear_content_preview_table',
                $available[$tableName],
                $tableName,
                (int) $sql->getValue('amount'),
            );
        }

        return $details;
    }

    /** @param list<string> $tableNames */
    public function delete(array $tableNames): int
    {
        $validated = $this->validate($tableNames);
        $deleted = $this->count($validated);
        $sql = rex_sql::factory();
        $sql->beginTransaction();

        try {
            foreach ($validated as $tableName) {
                $sql->setQuery('DELETE FROM ' . $sql->escapeIdentifier($tableName));
                if (rex_sql_table::get(rex::getTable('yform_history'))->exists()) {
                    $sql->setQuery(
                        'DELETE FROM ' . rex::getTable('yform_history') . ' WHERE table_name = :table_name',
                        ['table_name' => $tableName],
                    );
                }
            }
            $sql->commit();
        } catch (Throwable $exception) {
            $sql->rollBack();
            throw $exception;
        }

        foreach ($validated as $tableName) {
            rex_extension::registerPoint(new rex_extension_point('CLEAR_CONTENT_TABLE_CLEARED', $tableName));
        }

        return $deleted;
    }

    /** @param list<string> $tableNames */
    public function countForCompleteRemoval(array $tableNames): int
    {
        $plan = $this->buildRemovalPlan($tableNames);
        return $this->countPhysicalTables($plan['physical_tables']);
    }

    /** @param list<string> $tableNames
     *  @return list<string>
     */
    public function describeCompleteRemoval(array $tableNames): array
    {
        $plan = $this->buildRemovalPlan($tableNames);
        $details = [];
        foreach ($plan['physical_tables'] as $tableName) {
            $details[] = rex_i18n::msg(
                'clear_content_preview_drop_table',
                $tableName,
                $this->countPhysicalTables([$tableName]),
            );
        }
        foreach ($plan['incoming_fields'] as $field) {
            $details[] = rex_i18n::msg(
                'clear_content_preview_drop_relation',
                $field['source_table'],
                $field['field_name'],
                $field['target_table'],
            );
        }
        foreach ($plan['internal_foreign_keys'] as $foreignKey) {
            $details[] = rex_i18n::msg(
                'clear_content_preview_drop_foreign_key',
                $foreignKey['source_table'],
                $foreignKey['constraint_name'],
            );
        }
        $details[] = rex_i18n::msg('clear_content_preview_drop_definitions', count($plan['definitions']));
        $details[] = rex_i18n::msg('clear_content_preview_drop_history');

        return $details;
    }

    /** @param list<string> $tableNames */
    public function removeCompletely(array $tableNames): int
    {
        $plan = $this->buildRemovalPlan($tableNames);
        $deletedRows = $this->countPhysicalTables($plan['physical_tables']);

        // DDL statements cause implicit commits in MySQL. Every dependency is
        // therefore validated before the first schema change is executed.
        $sql = rex_sql::factory();
        foreach ($plan['internal_foreign_keys'] as $foreignKey) {
            $sql->setQuery(
                'ALTER TABLE ' . $sql->escapeIdentifier($foreignKey['source_table'])
                . ' DROP FOREIGN KEY ' . $sql->escapeIdentifier($foreignKey['constraint_name']),
            );
        }

        foreach ($plan['incoming_fields'] as $field) {
            $sourceTable = rex_sql_table::get($field['source_table']);
            if ($sourceTable->hasColumn($field['field_name'])) {
                $sourceTable->removeColumn($field['field_name'])->alter();
            }
            rex_yform_manager_table_api::removeTablefield($field['source_table'], $field['field_name']);
        }

        // Remove TableManager definitions before their physical tables. The
        // API only removes metadata; dropping the schema remains explicit.
        foreach ($plan['definitions'] as $tableName) {
            rex_yform_manager_table_api::removeTable($tableName);
        }

        foreach ($plan['physical_tables'] as $tableName) {
            rex_sql_table::get($tableName)->drop();
        }

        if (rex_sql_table::get(rex::getTable('yform_history'))->exists()) {
            $history = rex_sql::factory();
            foreach ($plan['physical_tables'] as $tableName) {
                $history->setQuery(
                    'DELETE FROM ' . rex::getTable('yform_history') . ' WHERE table_name = :table_name',
                    ['table_name' => $tableName],
                );
            }
        }

        rex_yform_manager_table::deleteCache();
        foreach ($plan['physical_tables'] as $tableName) {
            rex_extension::registerPoint(new rex_extension_point('CLEAR_CONTENT_TABLE_REMOVED', $tableName));
        }

        return $deletedRows;
    }

    /** @param list<string> $tableNames */
    private function countPhysicalTables(array $tableNames): int
    {
        $total = 0;
        foreach ($tableNames as $tableName) {
            if (!rex_sql_table::get($tableName)->exists()) {
                continue;
            }
            $sql = rex_sql::factory();
            $sql->setQuery('SELECT COUNT(*) AS amount FROM ' . $sql->escapeIdentifier($tableName));
            $total += (int) $sql->getValue('amount');
        }

        return $total;
    }

    /** @param list<string> $tableNames
     *  @return list<string>
     */
    private function validate(array $tableNames): array
    {
        if ($tableNames === []) {
            throw new rex_functional_exception(rex_i18n::msg('clear_content_error_tables_missing'));
        }

        $available = $this->getAvailableTables();
        foreach ($tableNames as $tableName) {
            if (!isset($available[$tableName])) {
                throw new rex_functional_exception(rex_i18n::msg('clear_content_error_table_invalid', $tableName));
            }
        }

        return array_values(array_unique($tableNames));
    }

    /**
     * @param list<string> $tableNames
     * @return array{
     *     physical_tables: list<string>,
     *     definitions: list<string>,
     *     incoming_fields: list<array{source_table: string, field_name: string, target_table: string}>,
     *     internal_foreign_keys: list<array{source_table: string, constraint_name: string}>
     * }
     */
    private function buildRemovalPlan(array $tableNames): array
    {
        $selected = $this->validate($tableNames);
        $allTables = rex_yform_manager_table::getAll();
        $physicalTables = $selected;
        $incomingFields = [];
        $relationUses = [];

        foreach ($allTables as $sourceTable) {
            foreach ($sourceTable->getRelations() as $field) {
                $targetTable = (string) $field->getElement('table');
                $relationTable = (string) $field->getElement('relation_table');
                $touchesSelection = in_array($sourceTable->getTableName(), $selected, true)
                    || in_array($targetTable, $selected, true)
                    || ($relationTable !== '' && in_array($relationTable, $selected, true));

                if ($relationTable !== '') {
                    $relationUses[$relationTable][] = $touchesSelection;
                    if ($touchesSelection) {
                        if ($this->isProtected($relationTable)) {
                            throw new rex_functional_exception(rex_i18n::msg(
                                'clear_content_error_protected_relation',
                                $relationTable,
                            ));
                        }
                        $physicalTables[] = $relationTable;
                    }
                }

                if ($touchesSelection && !in_array($sourceTable->getTableName(), $selected, true)) {
                    $incomingFields[] = [
                        'source_table' => $sourceTable->getTableName(),
                        'field_name' => $field->getName(),
                        'target_table' => $targetTable,
                    ];
                }
            }
        }

        foreach ($relationUses as $relationTable => $uses) {
            if (in_array($relationTable, $physicalTables, true) && in_array(false, $uses, true)) {
                throw new rex_functional_exception(rex_i18n::msg('clear_content_error_shared_relation', $relationTable));
            }
        }

        $physicalTables = array_values(array_unique(array_filter(
            $physicalTables,
            static fn (string $tableName): bool => rex_sql_table::get($tableName)->exists(),
        )));
        $incomingFields = array_values(array_filter(
            $incomingFields,
            static fn (array $field): bool => !in_array($field['source_table'], $physicalTables, true),
        ));
        $incomingFields = array_values(array_reduce(
            $incomingFields,
            static function (array $fields, array $field): array {
                $fields[$field['source_table'] . "\0" . $field['field_name']] = $field;
                return $fields;
            },
            [],
        ));
        $internalForeignKeys = $this->findInternalForeignKeysAndAssertNoExternalOnes($physicalTables);

        $definitions = array_values(array_filter(
            $physicalTables,
            static fn (string $tableName): bool => rex_yform_manager_table::get($tableName) instanceof rex_yform_manager_table,
        ));

        return [
            'physical_tables' => $physicalTables,
            'definitions' => $definitions,
            'incoming_fields' => $incomingFields,
            'internal_foreign_keys' => $internalForeignKeys,
        ];
    }

    /**
     * @param list<string> $tableNames
     * @return list<array{source_table: string, constraint_name: string}>
     */
    private function findInternalForeignKeysAndAssertNoExternalOnes(array $tableNames): array
    {
        if ($tableNames === []) {
            return [];
        }

        $internalForeignKeys = [];
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME'
            . ' FROM information_schema.KEY_COLUMN_USAGE'
            . ' WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND REFERENCED_TABLE_NAME IN (' . $sql->in($tableNames) . ')',
        );
        foreach ($sql->getArray() as $foreignKey) {
            $sourceTable = (string) $foreignKey['TABLE_NAME'];
            if (!in_array($sourceTable, $tableNames, true)) {
                throw new rex_functional_exception(rex_i18n::msg(
                    'clear_content_error_foreign_key',
                    $sourceTable,
                    (string) $foreignKey['COLUMN_NAME'],
                    (string) $foreignKey['CONSTRAINT_NAME'],
                ));
            }
            $internalForeignKeys[$sourceTable . "\0" . (string) $foreignKey['CONSTRAINT_NAME']] = [
                'source_table' => $sourceTable,
                'constraint_name' => (string) $foreignKey['CONSTRAINT_NAME'],
            ];
        }

        return array_values($internalForeignKeys);
    }

    private function isProtected(string $tableName): bool
    {
        if (str_starts_with($tableName, rex::getTablePrefix() . 'yform_')) {
            return true;
        }

        return in_array($tableName, [
            rex::getTable('article'),
            rex::getTable('article_slice'),
            rex::getTable('article_slice_history'),
            rex::getTable('clang'),
            rex::getTable('media'),
            rex::getTable('media_category'),
            rex::getTable('module'),
            rex::getTable('template'),
            rex::getTable('user'),
        ], true);
    }
}
