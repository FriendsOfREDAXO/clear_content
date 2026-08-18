<?php

namespace FriendsOfRedaxo\ClearContent\Cleanup;

use rex_functional_exception;
use rex_i18n;

final class CleanupExecutor
{
    public function execute(CleanupRequest $request): int
    {
        return match ($request->action) {
            'slices_language' => (new SliceCleaner())->deleteByLanguages($request->clangIds),
            'slices_modules' => (new SliceCleaner())->deleteByModules($request->moduleIds),
            'slices_article_language' => (new SliceCleaner())->deleteByArticlesAndLanguages($request->sliceArticleIds, $request->clangIds),
            'structure_all' => (new StructureCleaner())->deleteAll(),
            'category' => (new StructureCleaner())->deleteCategories($request->categoryIds),
            'articles_category' => (new StructureCleaner())->deleteArticlesInCategories($request->categoryIds, $request->olderThan),
            'synchronization_files' => (new SynchronizationFileCleaner())->delete(),
            'media_categories' => (new MediaCleaner())->deleteCategories($request->mediaCategoryIds),
            'media_category' => (new MediaCleaner())->deleteByCategories($request->mediaCategoryIds),
            'tables' => (new TableCleaner())->delete($request->tableNames),
            'tables_drop' => (new TableCleaner())->removeCompletely($request->tableNames),
            default => throw new rex_functional_exception(rex_i18n::msg('clear_content_error_action')),
        };
    }
}
