<?php

namespace FriendsOfRedaxo\ClearContent\Cleanup;

use rex;
use rex_article;
use rex_category;
use rex_clang;
use rex_functional_exception;
use rex_i18n;
use rex_sql;
use rex_sql_table;
use Throwable;

final class SliceCleaner
{
    /** @return array<int, string> article id => path and localized name */
    public function getAvailableArticles(): array
    {
        $clangId = rex_clang::getStartId();
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT id FROM ' . rex::getTable('article')
            . ' WHERE clang_id = :clang_id'
            . ' ORDER BY path, parent_id, startarticle DESC, catpriority, priority, name, id',
            ['clang_id' => $clangId],
        );

        $articles = [];
        foreach ($sql->getArray() as $row) {
            $articleId = (int) $row['id'];
            $article = rex_article::get($articleId, $clangId);
            if (!$article instanceof rex_article) {
                continue;
            }

            $parts = [];
            foreach (array_filter(explode('|', $article->getPath()), 'strlen') as $categoryId) {
                $category = rex_category::get((int) $categoryId, $clangId);
                if ($category instanceof rex_category) {
                    $parts[] = $category->getName();
                }
            }
            if (!$article->isStartArticle() || $parts === []) {
                $parts[] = $article->getName();
            }
            $articles[$articleId] = implode(' / ', $parts) . ' [' . $articleId . ']';
        }

        return $articles;
    }

    /** @return array<int, string> module id => localized name */
    public function getAvailableModules(): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT id, name FROM ' . rex::getTable('module') . ' ORDER BY name, id');
        $modules = [];
        foreach ($sql->getArray() as $module) {
            $modules[(int) $module['id']] = rex_i18n::translate((string) $module['name']);
        }

        return $modules;
    }

    /** @param list<int> $clangIds */
    public function countByLanguages(array $clangIds): int
    {
        $validated = $this->validateLanguages($clangIds);
        $sql = rex_sql::factory();

        return $this->countWhere(' WHERE clang_id IN (' . $sql->in($validated) . ')');
    }

    /**
     * @param list<int> $articleIds
     * @param list<int> $clangIds
     */
    public function countByArticlesAndLanguages(array $articleIds, array $clangIds): int
    {
        [$validatedArticleIds, $validatedClangIds] = $this->validateArticlesAndLanguages($articleIds, $clangIds);
        $sql = rex_sql::factory();
        return $this->countWhere(
            ' WHERE article_id IN (' . $sql->in($validatedArticleIds) . ')'
            . ' AND clang_id IN (' . $sql->in($validatedClangIds) . ')',
        );
    }

    /**
     * @param list<int> $articleIds
     * @param list<int> $clangIds
     * @return list<string>
     */
    public function describeArticlesAndLanguages(array $articleIds, array $clangIds): array
    {
        [$validatedArticleIds, $validatedClangIds] = $this->validateArticlesAndLanguages($articleIds, $clangIds);
        $languageNames = $this->getLanguageNames($validatedClangIds);

        return array_map(static function (int $articleId) use ($validatedClangIds, $languageNames): string {
            $article = rex_article::get($articleId, $validatedClangIds[0]);
            assert($article instanceof rex_article);

            return rex_i18n::msg(
                'clear_content_preview_article_language',
                $article->getName(),
                $articleId,
                $languageNames,
            );
        }, $validatedArticleIds);
    }

    /**
     * @param list<int> $clangIds
     * @return list<string>
     */
    public function describeLanguages(array $clangIds): array
    {
        return [rex_i18n::msg('clear_content_preview_languages', $this->getLanguageNames($this->validateLanguages($clangIds)))];
    }

    /** @param list<int> $moduleIds */
    public function countByModules(array $moduleIds): int
    {
        return $this->analyzeModules($moduleIds)->getCurrentCount();
    }

    /** @param list<int> $moduleIds */
    public function analyzeModules(array $moduleIds): ModuleCleanupAnalysis
    {
        $validated = $this->validateModules($moduleIds);
        $directSlices = [];
        foreach ($validated as $moduleId) {
            $directSlices[$moduleId] = $this->countModules([$moduleId]);
        }
        $nested = (new GridblockNestedModuleCleaner())->analyze($validated);

        return new ModuleCleanupAnalysis(
            $directSlices,
            $nested->currentByModule,
            $nested->historyByModule,
        );
    }

    /**
     * @param list<int> $moduleIds
     * @return list<string>
     */
    public function describeModules(array $moduleIds, ?ModuleCleanupAnalysis $analysis = null): array
    {
        $available = $this->getAvailableModules();
        $validated = $this->validateModules($moduleIds);
        $analysis ??= $this->analyzeModules($validated);
        $details = [];
        foreach ($validated as $moduleId) {
            $details[] = rex_i18n::msg(
                'clear_content_preview_module',
                $available[$moduleId],
                $moduleId,
                $analysis->directSlicesByModule[$moduleId] ?? 0,
                $analysis->nestedBlocksByModule[$moduleId] ?? 0,
                $analysis->nestedHistoryByModule[$moduleId] ?? 0,
            );
        }

        return $details;
    }

    /** @param list<int> $clangIds */
    public function deleteByLanguages(array $clangIds): int
    {
        $validated = $this->validateLanguages($clangIds);
        $sql = rex_sql::factory();

        return $this->deleteWhere(' WHERE clang_id IN (' . $sql->in($validated) . ')');
    }

    /**
     * @param list<int> $articleIds
     * @param list<int> $clangIds
     */
    public function deleteByArticlesAndLanguages(array $articleIds, array $clangIds): int
    {
        [$validatedArticleIds, $validatedClangIds] = $this->validateArticlesAndLanguages($articleIds, $clangIds);
        $sql = rex_sql::factory();
        return $this->deleteWhere(
            ' WHERE article_id IN (' . $sql->in($validatedArticleIds) . ')'
            . ' AND clang_id IN (' . $sql->in($validatedClangIds) . ')',
        );
    }

    /** @param list<int> $moduleIds */
    public function deleteByModules(array $moduleIds): int
    {
        $validated = $this->validateModules($moduleIds);
        $analysis = $this->analyzeModules($validated);
        $where = ' WHERE module_id IN (' . rex_sql::factory()->in($validated) . ')';
        $sliceTable = rex::getTable('article_slice');
        $historyTable = rex::getTable('article_slice_history');
        $sql = rex_sql::factory();
        $sql->beginTransaction();

        try {
            (new GridblockNestedModuleCleaner())->remove($validated);
            if (rex_sql_table::get($historyTable)->exists()) {
                $sql->setQuery('DELETE FROM ' . $historyTable . $where);
            }
            $sql->setQuery('DELETE FROM ' . $sliceTable . $where);
            $sql->commit();

            return $analysis->getCurrentCount();
        } catch (Throwable $exception) {
            $sql->rollBack();
            throw $exception;
        }
    }

    private function assertLanguageExists(int $clangId): void
    {
        if (!rex_clang::exists($clangId)) {
            throw new rex_functional_exception(rex_i18n::msg('clear_content_error_language'));
        }
    }

    /**
     * @param list<int> $articleIds
     * @param list<int> $clangIds
     * @return array{list<int>, list<int>}
     */
    private function validateArticlesAndLanguages(array $articleIds, array $clangIds): array
    {
        $validatedClangIds = $this->validateLanguages($clangIds);
        $validatedArticleIds = array_values(array_unique(array_filter(
            array_map('intval', $articleIds),
            static fn (int $articleId): bool => $articleId > 0,
        )));
        sort($validatedArticleIds);
        if ($validatedArticleIds === []) {
            throw new rex_functional_exception(rex_i18n::msg('clear_content_error_article_selection_missing'));
        }
        foreach ($validatedArticleIds as $articleId) {
            foreach ($validatedClangIds as $clangId) {
                if (!rex_article::get($articleId, $clangId) instanceof rex_article) {
                    throw new rex_functional_exception(rex_i18n::msg('clear_content_error_article_missing'));
                }
            }
        }

        return [$validatedArticleIds, $validatedClangIds];
    }

    /**
     * @param list<int> $clangIds
     * @return list<int>
     */
    private function validateLanguages(array $clangIds): array
    {
        if ($clangIds === []) {
            throw new rex_functional_exception(rex_i18n::msg('clear_content_error_languages_missing'));
        }
        foreach ($clangIds as $clangId) {
            $this->assertLanguageExists($clangId);
        }

        return array_values(array_unique($clangIds));
    }

    /** @param list<int> $clangIds */
    private function getLanguageNames(array $clangIds): string
    {
        return implode(', ', array_map(
            static fn (int $clangId): string => rex_clang::get($clangId)?->getName() ?? (string) $clangId,
            $clangIds,
        ));
    }

    /** @param array<string, scalar|null> $params */
    private function countWhere(string $where, array $params = []): int
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT COUNT(*) AS amount FROM ' . rex::getTable('article_slice') . $where, $params);

        return (int) $sql->getValue('amount');
    }

    /** @param list<int> $moduleIds */
    private function countModules(array $moduleIds): int
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT COUNT(*) AS amount FROM ' . rex::getTable('article_slice')
            . ' WHERE module_id IN (' . $sql->in($moduleIds) . ')',
        );

        return (int) $sql->getValue('amount');
    }

    /**
     * @param list<int> $moduleIds
     * @return list<int>
     */
    private function validateModules(array $moduleIds): array
    {
        if ($moduleIds === []) {
            throw new rex_functional_exception(rex_i18n::msg('clear_content_error_modules_missing'));
        }

        $available = $this->getAvailableModules();
        foreach ($moduleIds as $moduleId) {
            if (!isset($available[$moduleId])) {
                throw new rex_functional_exception(rex_i18n::msg('clear_content_error_module_invalid', $moduleId));
            }
        }

        return array_values(array_unique($moduleIds));
    }

    /** @param array<string, scalar|null> $params */
    private function deleteWhere(string $where, array $params = []): int
    {
        $sliceTable = rex::getTable('article_slice');
        $historyTable = rex::getTable('article_slice_history');
        $sql = rex_sql::factory();
        $sql->beginTransaction();

        try {
            $sql->setQuery('SELECT COUNT(*) AS amount FROM ' . $sliceTable . $where, $params);
            $deleted = (int) $sql->getValue('amount');

            if (rex_sql_table::get($historyTable)->exists()) {
                $sql->setQuery('DELETE FROM ' . $historyTable . $where, $params);
            }
            $sql->setQuery('DELETE FROM ' . $sliceTable . $where, $params);
            $sql->commit();

            return $deleted;
        } catch (Throwable $exception) {
            $sql->rollBack();
            throw $exception;
        }
    }
}
