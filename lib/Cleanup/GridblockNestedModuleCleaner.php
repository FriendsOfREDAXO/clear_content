<?php

namespace FriendsOfRedaxo\ClearContent\Cleanup;

use JsonException;
use rex;
use rex_sql;
use rex_sql_table;

/** Handles the documented JSON schema of the optional Gridblock add-on. */
final class GridblockNestedModuleCleaner
{
    private const MODULE_MARKER = 'GRID_MODULE_IDENTIFIER';

    /** @param list<int> $moduleIds */
    public function analyze(array $moduleIds): NestedModuleAnalysis
    {
        $containerModuleIds = $this->getContainerModuleIds();
        if ($containerModuleIds === []) {
            return new NestedModuleAnalysis([], []);
        }

        return new NestedModuleAnalysis(
            $this->scanTable(rex::getTable('article_slice'), $containerModuleIds, $moduleIds, false),
            rex_sql_table::get(rex::getTable('article_slice_history'))->exists()
                ? $this->scanTable(rex::getTable('article_slice_history'), $containerModuleIds, $moduleIds, false)
                : [],
        );
    }

    /** @param list<int> $moduleIds */
    public function remove(array $moduleIds): void
    {
        $containerModuleIds = $this->getContainerModuleIds();
        if ($containerModuleIds === []) {
            return;
        }

        $this->scanTable(rex::getTable('article_slice'), $containerModuleIds, $moduleIds, true);
        if (rex_sql_table::get(rex::getTable('article_slice_history'))->exists()) {
            $this->scanTable(rex::getTable('article_slice_history'), $containerModuleIds, $moduleIds, true);
        }
    }

    /** @return list<int> */
    private function getContainerModuleIds(): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT id FROM ' . rex::getTable('module')
            . ' WHERE input LIKE :marker OR output LIKE :marker',
            ['marker' => '%' . self::MODULE_MARKER . '%'],
        );

        return array_values(array_map(
            static fn (array $module): int => (int) $module['id'],
            $sql->getArray(),
        ));
    }

    /**
     * @param list<int> $containerModuleIds
     * @param list<int> $selectedModuleIds
     * @return array<int, int>
     * @throws JsonException
     */
    private function scanTable(
        string $tableName,
        array $containerModuleIds,
        array $selectedModuleIds,
        bool $mutate,
    ): array {
        $counts = array_fill_keys($selectedModuleIds, 0);
        $sql = rex_sql::factory();
        $columns = ['id', 'module_id'];
        for ($index = 1; $index <= 20; ++$index) {
            $columns[] = 'value' . $index;
        }
        $query = 'SELECT ' . implode(', ', array_map([$sql, 'escapeIdentifier'], $columns))
            . ' FROM ' . $sql->escapeIdentifier($tableName)
            . ' WHERE module_id IN (' . $sql->in($containerModuleIds) . ')';
        if ($selectedModuleIds !== []) {
            // Rows whose outer container module is selected are removed as a
            // whole and therefore must not also be rewritten or double-counted.
            $query .= ' AND module_id NOT IN (' . $sql->in($selectedModuleIds) . ')';
        }
        $sql->setQuery($query);

        foreach ($sql->getArray() as $row) {
            [$rowCounts, $changes] = $this->processRow($row, $selectedModuleIds);
            foreach ($rowCounts as $moduleId => $amount) {
                $counts[$moduleId] += $amount;
            }
            if ($mutate && $changes !== []) {
                $update = rex_sql::factory();
                $update->setTable($tableName);
                $update->setWhere(['id' => (int) $row['id']]);
                foreach ($changes as $column => $value) {
                    $update->setValue($column, $value);
                }
                $update->update();
            }
        }

        return $counts;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<int> $selectedModuleIds
     * @return array{array<int, int>, array<string, string>}
     * @throws JsonException
     */
    private function processRow(array $row, array $selectedModuleIds): array
    {
        $counts = array_fill_keys($selectedModuleIds, 0);
        $registry = $this->decodeArray((string) ($row['value19'] ?? ''));
        if ($registry === null) {
            return [$counts, []];
        }

        $removedUidsByColumn = [];
        foreach ($registry as $columnId => &$entries) {
            if (!is_array($entries)) {
                continue;
            }
            foreach ($entries as $uid => $definition) {
                $moduleId = is_array($definition)
                    ? (int) ($definition['id'] ?? 0)
                    : (int) $definition;
                if (!in_array($moduleId, $selectedModuleIds, true)) {
                    continue;
                }
                ++$counts[$moduleId];
                $removedUidsByColumn[(string) $columnId][] = (string) $uid;
                unset($entries[$uid]);
            }
        }
        unset($entries);

        if ($removedUidsByColumn === []) {
            return [$counts, []];
        }

        $changes = ['value19' => $this->encode($registry)];
        foreach ($removedUidsByColumn as $columnId => $uids) {
            $columnNumber = (int) $columnId;
            if ($columnNumber < 1 || $columnNumber > 16) {
                continue;
            }
            $valueColumn = 'value' . $columnNumber;
            $content = $this->decodeArray((string) ($row[$valueColumn] ?? ''));
            if ($content === null) {
                continue;
            }
            foreach ($uids as $uid) {
                unset($content[$uid]);
            }
            $changes[$valueColumn] = $this->encode($content);
        }

        $options = $this->decodeArray((string) ($row['value18'] ?? ''));
        if (is_array($options['columnslices'] ?? null)) {
            foreach ($removedUidsByColumn as $columnId => $uids) {
                if (!array_key_exists($columnId, $options['columnslices'])) {
                    continue;
                }
                $order = $options['columnslices'][$columnId];
                if (is_string($order)) {
                    $orderedUids = array_filter(explode(',', $order), static fn (string $uid): bool => $uid !== '');
                    $options['columnslices'][$columnId] = implode(',', array_values(array_diff($orderedUids, $uids)));
                } elseif (is_array($order)) {
                    $options['columnslices'][$columnId] = array_values(array_diff(
                        array_map('strval', $order),
                        $uids,
                    ));
                }
            }
            $changes['value18'] = $this->encode($options);
        }

        return [$counts, $changes];
    }

    /** @return array<mixed>|null */
    private function decodeArray(string $value): ?array
    {
        if ($value === '') {
            return null;
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (JsonException) {
            return null;
        }
    }

    /** @param array<mixed> $value
     *  @throws JsonException
     */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
