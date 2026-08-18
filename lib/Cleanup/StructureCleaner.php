<?php

namespace FriendsOfRedaxo\ClearContent\Cleanup;

use DateTimeImmutable;
use rex_article;
use rex_article_service;
use rex_category;
use rex_category_service;
use rex_clang;
use rex_functional_exception;
use rex_i18n;
use rex;
use rex_sql;
use rex_sql_table;

final class StructureCleaner
{
    public function countAll(): int
    {
        $protectedIds = array_values(array_unique([
            ...$this->getProtectedArticleIds(),
            ...$this->getProtectedCategoryIds(),
        ]));
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT COUNT(DISTINCT id) AS amount FROM ' . rex::getTable('article')
            . ' WHERE clang_id = :clang_id AND id NOT IN (' . $sql->in($protectedIds) . ')',
            ['clang_id' => rex_clang::getStartId()],
        );

        return (int) $sql->getValue('amount');
    }

    /** @param list<int> $categoryIds */
    public function countCategories(array $categoryIds): int
    {
        $count = 0;
        foreach ($this->getSelectedRootCategories($categoryIds, true) as $category) {
            $sql = rex_sql::factory();
            $sql->setQuery(
                'SELECT COUNT(DISTINCT id) AS amount FROM ' . rex::getTable('article')
                . ' WHERE clang_id = :clang_id AND (id = :category_id OR path LIKE :category_path)',
                [
                    'clang_id' => rex_clang::getStartId(),
                    'category_id' => $category->getId(),
                    'category_path' => '%|' . $category->getId() . '|%',
                ],
            );
            $count += (int) $sql->getValue('amount');
        }

        return $count;
    }

    /**
     * @param list<int> $categoryIds
     * @return list<string>
     */
    public function describeCategories(array $categoryIds): array
    {
        return array_map(
            static fn (rex_category $category): string => rex_i18n::msg(
                'clear_content_preview_category',
                html_entity_decode($category->getName(), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                $category->getId(),
            ),
            $this->getSelectedRootCategories($categoryIds, true),
        );
    }

    public function deleteAll(): int
    {
        $deleted = 0;
        $clangId = rex_clang::getStartId();
        $protectedCategoryIds = $this->getProtectedCategoryIds();

        foreach (rex_category::getRootCategories(false, $clangId) as $category) {
            $deleted += $this->cleanCategoryTree($category, $protectedCategoryIds);
        }

        $protectedIds = $this->getProtectedArticleIds();

        foreach (rex_article::getRootArticles(false, $clangId) as $article) {
            if (!in_array($article->getId(), $protectedIds, true)) {
                $this->deleteArticle($article->getId());
                ++$deleted;
            }
        }

        return $deleted;
    }

    /** @param list<int> $categoryIds */
    public function deleteCategories(array $categoryIds): int
    {
        $deleted = 0;
        foreach ($this->getSelectedRootCategories($categoryIds, true) as $category) {
            $deleted += $this->deleteCategoryTree($category);
        }

        return $deleted;
    }

    /** @param list<int> $categoryIds */
    public function countArticlesInCategories(array $categoryIds, string $olderThan): int
    {
        return count($this->findArticleIdsInCategories($categoryIds, $olderThan));
    }

    /**
     * @param list<int> $categoryIds
     * @return list<string>
     */
    public function describeArticlesInCategories(array $categoryIds, string $olderThan): array
    {
        $categories = $this->getSelectedRootCategories($categoryIds);
        $cutoff = $this->validateCutoffDate($olderThan);

        $details = array_map(
            static fn (rex_category $category): string => rex_i18n::msg(
                'clear_content_preview_mass_category',
                html_entity_decode($category->getName(), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                $category->getId(),
            ),
            $categories,
        );
        $details[] =
            $cutoff === null
                ? rex_i18n::msg('clear_content_preview_mass_all_dates')
                : rex_i18n::msg('clear_content_preview_mass_cutoff', $cutoff->format('d.m.Y'));
        $details[] = rex_i18n::msg('clear_content_preview_mass_protected');

        return $details;
    }

    /** @param list<int> $categoryIds */
    public function deleteArticlesInCategories(array $categoryIds, string $olderThan): int
    {
        $articleIds = $this->findArticleIdsInCategories($categoryIds, $olderThan);
        foreach ($articleIds as $id) {
            $this->deleteArticle($id);
        }

        return count($articleIds);
    }

    private function resolveCategory(int $categoryId): rex_category
    {
        if ($categoryId < 1) {
            throw new rex_functional_exception(rex_i18n::msg('clear_content_error_category_missing'));
        }

        $category = rex_category::get($categoryId, rex_clang::getStartId());
        if (!$category instanceof rex_category) {
            throw new rex_functional_exception(rex_i18n::msg('clear_content_error_category_invalid'));
        }

        return $category;
    }

    /**
     * @param list<int> $categoryIds
     * @return list<rex_category>
     */
    private function getSelectedRootCategories(array $categoryIds, bool $forCompleteDeletion = false): array
    {
        $categoryIds = array_values(array_unique(array_filter(
            array_map('intval', $categoryIds),
            static fn (int $categoryId): bool => $categoryId > 0,
        )));
        sort($categoryIds);
        if ($categoryIds === []) {
            throw new rex_functional_exception(rex_i18n::msg('clear_content_error_category_missing'));
        }

        $categories = [];
        foreach ($categoryIds as $categoryId) {
            $category = $this->resolveCategory($categoryId);
            if ($forCompleteDeletion) {
                $this->assertCategoryDeletable($category);
                $selectedAncestorIds = array_intersect($category->getPathAsArray(), $categoryIds);
                if ($selectedAncestorIds !== []) {
                    continue;
                }
            }
            $categories[] = $category;
        }

        return $categories;
    }

    /**
     * @param list<int> $categoryIds
     * @return list<int>
     */
    private function findArticleIdsInCategories(array $categoryIds, string $olderThan): array
    {
        $categories = $this->getSelectedRootCategories($categoryIds);
        $cutoff = $this->validateCutoffDate($olderThan);
        $sql = rex_sql::factory();
        $query = 'SELECT id FROM ' . rex::getTable('article')
            . ' WHERE parent_id IN (' . $sql->in(array_map(
                static fn (rex_category $category): int => $category->getId(),
                $categories,
            )) . ') AND startarticle = 0'
            . ' AND id NOT IN (' . $sql->in($this->getProtectedArticleIds()) . ')';
        $params = [];
        $query .= ' GROUP BY id';
        if ($cutoff !== null) {
            $query .= ' HAVING MAX(updatedate) < :updated_before';
            $params['updated_before'] = $cutoff->format('Y-m-d 00:00:00');
        }
        $query .= ' ORDER BY id';
        $sql->setQuery($query, $params);

        return array_values(array_map(
            static fn (array $article): int => (int) $article['id'],
            $sql->getArray(),
        ));
    }

    private function validateCutoffDate(string $olderThan): ?DateTimeImmutable
    {
        if ($olderThan === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $olderThan);
        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $olderThan) {
            throw new rex_functional_exception(rex_i18n::msg('clear_content_error_date_invalid'));
        }

        return $date;
    }

    private function deleteCategoryTree(rex_category $category): int
    {
        $deleted = 0;

        foreach ($category->getChildren(false) as $child) {
            $deleted += $this->deleteCategoryTree($child);
        }

        foreach ($category->getArticles(false) as $article) {
            if (!$article->isStartArticle()) {
                $this->deleteArticle($article->getId());
                ++$deleted;
            }
        }

        $categoryId = $category->getId();
        rex_category_service::deleteCategory($categoryId);
        $this->deleteArticleHistory($categoryId);

        return $deleted + 1;
    }

    /** @param list<int> $protectedCategoryIds */
    private function cleanCategoryTree(rex_category $category, array $protectedCategoryIds): int
    {
        if (!in_array($category->getId(), $protectedCategoryIds, true)) {
            return $this->deleteCategoryTree($category);
        }

        $deleted = 0;
        foreach ($category->getChildren(false) as $child) {
            $deleted += $this->cleanCategoryTree($child, $protectedCategoryIds);
        }

        foreach ($category->getArticles(false) as $article) {
            if (!$article->isStartArticle() && !in_array($article->getId(), $this->getProtectedArticleIds(), true)) {
                $this->deleteArticle($article->getId());
                ++$deleted;
            }
        }

        return $deleted;
    }

    private function deleteArticle(int $articleId): void
    {
        rex_article_service::deleteArticle($articleId);
        $this->deleteArticleHistory($articleId);
    }

    private function deleteArticleHistory(int $articleId): void
    {
        $historyTable = rex::getTable('article_slice_history');
        if (!rex_sql_table::get($historyTable)->exists()) {
            return;
        }

        $history = rex_sql::factory();
        $history->setQuery('DELETE FROM ' . $historyTable . ' WHERE article_id = :article_id', [
            'article_id' => $articleId,
        ]);
    }

    private function assertCategoryDeletable(rex_category $category): void
    {
        foreach ($this->getProtectedArticleIds() as $articleId) {
            $article = rex_article::get($articleId, rex_clang::getStartId());
            if (!$article instanceof rex_article) {
                continue;
            }

            if ($article->getCategoryId() === $category->getId()
                || str_contains($article->getPath(), '|' . $category->getId() . '|')) {
                throw new rex_functional_exception(rex_i18n::msg('clear_content_error_category_protected'));
            }
        }
    }

    /** @return list<int> */
    private function getProtectedArticleIds(): array
    {
        return array_values(array_unique([
            rex_article::getSiteStartArticleId(),
            rex_article::getNotfoundArticleId(),
        ]));
    }

    /** @return list<int> */
    private function getProtectedCategoryIds(): array
    {
        $categoryIds = [];
        foreach ($this->getProtectedArticleIds() as $articleId) {
            $article = rex_article::get($articleId, rex_clang::getStartId());
            if (!$article instanceof rex_article) {
                continue;
            }

            if ($article->getCategoryId() > 0) {
                $categoryIds[] = $article->getCategoryId();
            }
            foreach (array_filter(explode('|', $article->getPath()), 'strlen') as $pathId) {
                $categoryIds[] = (int) $pathId;
            }
        }

        return array_values(array_unique($categoryIds));
    }
}
