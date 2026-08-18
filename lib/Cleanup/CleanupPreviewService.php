<?php

namespace FriendsOfRedaxo\ClearContent\Cleanup;

use rex_functional_exception;
use rex_i18n;

final class CleanupPreviewService
{
    public function create(CleanupRequest $request): CleanupPreview
    {
        return match ($request->action) {
            'slices_language' => new CleanupPreview(
                rex_i18n::msg('clear_content_delete_slices_language'),
                (new SliceCleaner())->countByLanguages($request->clangIds),
                (new SliceCleaner())->describeLanguages($request->clangIds),
            ),
            'slices_modules' => $this->createModulePreview($request),
            'slices_article_language' => new CleanupPreview(
                rex_i18n::msg('clear_content_delete_slices_article_language'),
                (new SliceCleaner())->countByArticlesAndLanguages($request->sliceArticleIds, $request->clangIds),
                (new SliceCleaner())->describeArticlesAndLanguages($request->sliceArticleIds, $request->clangIds),
            ),
            'structure_all' => new CleanupPreview(
                rex_i18n::msg('clear_content_delete_structure_all'),
                (new StructureCleaner())->countAll(),
                [rex_i18n::msg('clear_content_structure_info')],
            ),
            'category' => new CleanupPreview(
                rex_i18n::msg('clear_content_delete_category'),
                (new StructureCleaner())->countCategories($request->categoryIds),
                (new StructureCleaner())->describeCategories($request->categoryIds),
            ),
            'articles_category' => new CleanupPreview(
                rex_i18n::msg('clear_content_delete_articles_category'),
                (new StructureCleaner())->countArticlesInCategories($request->categoryIds, $request->olderThan),
                (new StructureCleaner())->describeArticlesInCategories($request->categoryIds, $request->olderThan),
            ),
            'synchronization_files' => new CleanupPreview(
                rex_i18n::msg('clear_content_delete_sync_files'),
                (new SynchronizationFileCleaner())->count(),
                (new SynchronizationFileCleaner())->describe(),
                true,
                'clear_content_preview_empty_sync',
            ),
            'media_categories' => new CleanupPreview(
                rex_i18n::msg('clear_content_delete_media_categories'),
                (new MediaCleaner())->countCategories($request->mediaCategoryIds),
                (new MediaCleaner())->describeCategories($request->mediaCategoryIds),
            ),
            'media_category' => new CleanupPreview(
                rex_i18n::msg('clear_content_delete_media_category'),
                (new MediaCleaner())->countByCategories($request->mediaCategoryIds),
                (new MediaCleaner())->describeCategories($request->mediaCategoryIds),
            ),
            'tables' => new CleanupPreview(
                rex_i18n::msg('clear_content_delete_tables'),
                (new TableCleaner())->count($request->tableNames),
                (new TableCleaner())->describe($request->tableNames),
            ),
            'tables_drop' => new CleanupPreview(
                rex_i18n::msg('clear_content_drop_tables'),
                (new TableCleaner())->countForCompleteRemoval($request->tableNames),
                (new TableCleaner())->describeCompleteRemoval($request->tableNames),
                true,
            ),
            default => throw new rex_functional_exception(rex_i18n::msg('clear_content_error_action')),
        };
    }

    private function createModulePreview(CleanupRequest $request): CleanupPreview
    {
        $cleaner = new SliceCleaner();
        $analysis = $cleaner->analyzeModules($request->moduleIds);

        return new CleanupPreview(
            rex_i18n::msg('clear_content_delete_slices_modules'),
            $analysis->getCurrentCount(),
            $cleaner->describeModules($request->moduleIds, $analysis),
        );
    }
}
