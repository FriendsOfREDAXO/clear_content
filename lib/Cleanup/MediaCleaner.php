<?php

namespace FriendsOfRedaxo\ClearContent\Cleanup;

use rex;
use rex_extension;
use rex_extension_point;
use rex_file;
use rex_functional_exception;
use rex_i18n;
use rex_media_category;
use rex_media_cache;
use rex_path;
use rex_sql;
use Throwable;

final class MediaCleaner
{
    /** @param list<int> $categoryIds */
    public function countCategories(array $categoryIds): int
    {
        return count($this->validateCategories($categoryIds));
    }

    /** @param list<int> $categoryIds */
    public function countByCategories(array $categoryIds): int
    {
        $categoryIds = $this->validateCategories($categoryIds);

        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT COUNT(*) AS amount FROM ' . rex::getTable('media')
            . ' WHERE category_id IN (' . $sql->in($categoryIds) . ')',
        );

        return (int) $sql->getValue('amount');
    }

    /**
     * @param list<int> $categoryIds
     * @return list<string>
     */
    public function describeCategories(array $categoryIds): array
    {
        return array_map(
            static fn (rex_media_category $category): string => rex_i18n::msg(
                'clear_content_preview_media_category',
                $category->getName(),
                $category->getId(),
            ),
            $this->getCategories($this->validateCategories($categoryIds)),
        );
    }

    /** @param list<int> $categoryIds */
    public function deleteByCategories(array $categoryIds): int
    {
        $categoryIds = $this->validateCategories($categoryIds);

        return $this->deleteFilenames($this->getFilenames($categoryIds));
    }

    /** @param list<string> $filenames */
    private function deleteFilenames(array $filenames): int
    {
        $mediaTable = rex::getTable('media');
        $sql = rex_sql::factory();

        foreach ($filenames as $filename) {
            $path = rex_path::media($filename);
            if (is_file($path) && !rex_file::delete($path)) {
                throw new rex_functional_exception(rex_i18n::msg('clear_content_error_media_file', $filename));
            }
        }

        $sql->beginTransaction();
        try {
            if ($filenames !== []) {
                $sql->setQuery('DELETE FROM ' . $mediaTable . ' WHERE filename IN (' . $sql->in($filenames) . ')');
            }
            $sql->commit();
        } catch (Throwable $exception) {
            $sql->rollBack();
            throw $exception;
        }

        foreach ($filenames as $filename) {
            rex_media_cache::delete($filename);
            rex_extension::registerPoint(new rex_extension_point('MEDIA_DELETED', '', ['filename' => $filename]));
        }

        return count($filenames);
    }

    /**
     * @param null|list<int> $categoryIds
     * @return list<string>
     */
    private function getFilenames(?array $categoryIds = null): array
    {
        $sql = rex_sql::factory();
        $where = null === $categoryIds ? '' : ' WHERE category_id IN (' . $sql->in($categoryIds) . ')';
        $sql->setQuery(
            'SELECT filename FROM ' . rex::getTable('media') . $where . ' ORDER BY filename',
        );

        return array_map(
            static fn (array $row): string => (string) $row['filename'],
            $sql->getArray(),
        );
    }

    private function getCategory(int $categoryId): rex_media_category
    {
        $category = rex_media_category::get($categoryId);
        if (!$category instanceof rex_media_category) {
            throw new rex_functional_exception(rex_i18n::msg('clear_content_error_media_category'));
        }

        return $category;
    }

    /**
     * @param list<int> $categoryIds
     * @return list<int>
     */
    private function validateCategories(array $categoryIds): array
    {
        $categoryIds = array_values(array_unique(array_filter(
            array_map('intval', $categoryIds),
            static fn (int $categoryId): bool => $categoryId > 0,
        )));
        sort($categoryIds);
        if ($categoryIds === []) {
            throw new rex_functional_exception(rex_i18n::msg('clear_content_error_media_category'));
        }
        $this->getCategories($categoryIds);

        return $categoryIds;
    }

    /**
     * @param list<int> $categoryIds
     * @return list<rex_media_category>
     */
    private function getCategories(array $categoryIds): array
    {
        return array_map(fn (int $categoryId): rex_media_category => $this->getCategory($categoryId), $categoryIds);
    }

    /** @param list<int> $categoryIds */
    public function deleteCategories(array $categoryIds): int
    {
        $categoryIds = $this->validateCategories($categoryIds);
        $categoryTable = rex::getTable('media_category');
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT id, parent_id FROM ' . $categoryTable);
        $parentIds = [];
        foreach ($sql->getArray() as $row) {
            $parentIds[(int) $row['id']] = (int) $row['parent_id'];
        }
        $selected = array_fill_keys($categoryIds, true);
        $replacementParents = [];
        foreach ($categoryIds as $categoryId) {
            $replacementParents[$categoryId] = $this->findRemainingParent($categoryId, $parentIds, $selected);
        }

        $sql->beginTransaction();
        try {
            foreach ($replacementParents as $categoryId => $parentId) {
                $sql->setQuery(
                    'UPDATE ' . rex::getTable('media') . ' SET category_id = ? WHERE category_id = ?',
                    [$parentId, $categoryId],
                );
            }

            $sql->setQuery('DELETE FROM ' . $categoryTable . ' WHERE id IN (' . $sql->in($categoryIds) . ')');

            foreach ($parentIds as $categoryId => $parentId) {
                if (isset($selected[$categoryId])) {
                    continue;
                }
                $newParentId = $this->findRemainingParent($categoryId, $parentIds, $selected);
                $path = $this->buildCategoryPath($newParentId, $parentIds, $selected);
                $sql->setQuery(
                    'UPDATE ' . $categoryTable . ' SET parent_id = ?, path = ? WHERE id = ?',
                    [$newParentId, $path, $categoryId],
                );
            }
            $sql->commit();
        } catch (Throwable $exception) {
            $sql->rollBack();
            throw $exception;
        }

        foreach ($categoryIds as $categoryId) {
            rex_media_cache::deleteCategory($categoryId);
            rex_extension::registerPoint(new rex_extension_point('MEDIA_CATEGORY_DELETED', ['id' => $categoryId]));
        }
        rex_media_cache::deleteLists();

        return count($categoryIds);
    }

    /**
     * @param array<int, int> $parentIds
     * @param array<int, true> $selected
     */
    private function findRemainingParent(int $categoryId, array $parentIds, array $selected): int
    {
        $parentId = $parentIds[$categoryId] ?? 0;
        $visited = [];
        while ($parentId > 0 && isset($selected[$parentId]) && !isset($visited[$parentId])) {
            $visited[$parentId] = true;
            $parentId = $parentIds[$parentId] ?? 0;
        }

        return $parentId;
    }

    /**
     * @param array<int, int> $parentIds
     * @param array<int, true> $selected
     */
    private function buildCategoryPath(int $parentId, array $parentIds, array $selected): string
    {
        $pathIds = [];
        $visited = [];
        while ($parentId > 0 && !isset($visited[$parentId])) {
            $visited[$parentId] = true;
            if (!isset($selected[$parentId])) {
                $pathIds[] = $parentId;
            }
            $parentId = $parentIds[$parentId] ?? 0;
        }

        return '|' . ($pathIds === [] ? '' : implode('|', array_reverse($pathIds)) . '|');
    }
}
