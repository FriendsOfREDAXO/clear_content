<?php

use FriendsOfRedaxo\ClearContent\Backend\CleanupFormRenderer;
use FriendsOfRedaxo\ClearContent\Backend\CleanupPageController;
use FriendsOfRedaxo\ClearContent\Cleanup\CleanupRequest;
use FriendsOfRedaxo\ClearContent\Cleanup\SliceCleaner;
use FriendsOfRedaxo\ClearContent\Cleanup\SynchronizationFileCleaner;

/** @var rex_addon $this */

$request = CleanupRequest::fromPost();
$controller = new CleanupPageController($this);
$renderer = new CleanupFormRenderer($this);
$workflowResult = $controller->handle($request, [
    'slices_language',
    'slices_article_language',
    'slices_modules',
    'structure_all',
    'category',
    'articles_category',
    'synchronization_files',
]);

if ($workflowResult !== null) {
    echo $renderer->renderPreview($workflowResult, $request, $controller->getCsrfToken());
    return;
}

$availableLanguages = rex_clang::getAll(false);
$languageSelect = new rex_select();
$languageSelect->setName('clang_ids[]');
$languageSelect->setId('clear-content-languages');
$languageSelect->setMultiple();
$languageSelect->setSize(min(8, count($availableLanguages)));
$languageSelect->setAttribute('class', 'selectpicker show-menu-arrow form-control');
$languageSelect->setAttribute('data-actions-box', 'true');
$languageSelect->setAttribute('data-live-search', 'true');
$languageSelect->setAttribute('data-size', '8');
foreach ($availableLanguages as $clang) {
    $languageSelect->addOption($clang->getName() . ' [' . $clang->getId() . ']', $clang->getId());
}
$languageSelect->setSelected($request->clangIds);

$sliceCleaner = new SliceCleaner();
$articleSelect = new rex_select();
$articleSelect->setName('slice_article_ids[]');
$articleSelect->setId('clear-content-articles');
$articleSelect->setMultiple();
$availableArticles = $sliceCleaner->getAvailableArticles();
$articleSelect->setSize(min(8, count($availableArticles)));
$articleSelect->setAttribute('class', 'selectpicker show-menu-arrow form-control');
$articleSelect->setAttribute('data-actions-box', 'true');
$articleSelect->setAttribute('data-live-search', 'true');
$articleSelect->setAttribute('data-size', '10');
$articleSelect->setAttribute('title', $this->i18n('clear_content_articles_select'));
foreach ($availableArticles as $articleId => $articleName) {
    $articleSelect->addOption($articleName, $articleId);
}
$articleSelect->setSelected($request->sliceArticleIds);

$moduleSelect = new rex_select();
$moduleSelect->setName('module_ids[]');
$moduleSelect->setId('clear-content-modules');
$moduleSelect->setMultiple();
$availableModules = $sliceCleaner->getAvailableModules();
$synchronizationCleaner = new SynchronizationFileCleaner();
$moduleSelect->setSize(min(8, count($availableModules)));
$moduleSelect->setAttribute('class', 'selectpicker show-menu-arrow form-control');
$moduleSelect->setAttribute('data-actions-box', 'true');
$moduleSelect->setAttribute('data-live-search', 'true');
$moduleSelect->setAttribute('data-size', '8');
foreach ($availableModules as $moduleId => $moduleName) {
    $moduleSelect->addOption($moduleName . ' [' . $moduleId . ']', $moduleId);
}
$moduleSelect->setSelected($request->moduleIds);

$categorySelect = new rex_category_select(false, rex_clang::getStartId(), false, false);
$categorySelect->setName('category_ids[]');
$categorySelect->setId('clear-content-categories');
$categorySelect->setMultiple();
$categorySelect->setSize(8);
$categorySelect->setAttribute('class', 'selectpicker show-menu-arrow form-control');
$categorySelect->setAttribute('data-actions-box', 'true');
$categorySelect->setAttribute('data-live-search', 'true');
$categorySelect->setAttribute('data-size', '10');
$categorySelect->setAttribute('title', $this->i18n('clear_content_categories_select'));
$categorySelect->setSelected($request->categoryIds);

$section = '';
if (count($availableLanguages) > 1) {
    $section .= $renderer->field(
        $this->i18n('clear_content_languages_selection'),
        $languageSelect->get(),
    );
}
$section .= $renderer->field(
    $this->i18n('clear_content_slices_languages'),
    $renderer->actions($renderer->previewButton('slices_language', $this->i18n('clear_content_delete_slices_language'))),
);
$section .= $renderer->field(
    $this->i18n('clear_content_slices_article_language'),
    $articleSelect->get()
    . $renderer->actions($renderer->previewButton('slices_article_language', $this->i18n('clear_content_delete_slices_article_language'))),
);
if ($availableModules !== []) {
    $section .= $renderer->field(
        $this->i18n('clear_content_slices_modules'),
        $moduleSelect->get()
        . $renderer->actions($renderer->previewButton('slices_modules', $this->i18n('clear_content_delete_slices_modules'))),
    );
}
$actions = $renderer->section($this->i18n('clear_content_legend_slices'), $section);

$section = $renderer->field(
    $this->i18n('clear_content_categories'),
    $categorySelect->get(),
);
$section .= $renderer->field(
    $this->i18n('clear_content_mass_articles_date'),
    '<input class="form-control" type="date" name="older_than" value="' . rex_escape($request->olderThan, 'html_attr') . '">',
);
$section .= $renderer->field(
    $this->i18n('clear_content_actions'),
    $renderer->actions(
        $renderer->previewButton('category', $this->i18n('clear_content_delete_category'))
        . ' '
        . $renderer->previewButton('articles_category', $this->i18n('clear_content_delete_articles_category')),
    ),
);
$actions .= $renderer->section($this->i18n('clear_content_legend_structure'), $section);

$section = $renderer->field(
    $this->i18n('clear_content_actions'),
    $renderer->actions($renderer->previewButton('structure_all', $this->i18n('clear_content_delete_structure_all'))),
);
$actions .= $renderer->section($this->i18n('clear_content_structure_all'), $section);

if ($synchronizationCleaner->hasTargets()) {
    $section = $renderer->field(
        $this->i18n('clear_content_sync_files'),
        $renderer->actions($renderer->previewButton('synchronization_files', $this->i18n('clear_content_delete_sync_files'))),
    );
    $actions .= $renderer->section($this->i18n('clear_content_legend_sync_files'), $section);
}

$notices = $renderer->notice(
    $this->i18n('clear_content_languages_selection'),
    $this->i18n('clear_content_languages_selection_help'),
);
$notices .= $renderer->notice(
    $this->i18n('clear_content_slices_article_language'),
    $this->i18n('clear_content_slices_article_language_help'),
);
if ($availableModules !== []) {
    $notices .= $renderer->notice(
        $this->i18n('clear_content_slices_modules'),
        $this->i18n('clear_content_slices_modules_help'),
    );
}
$notices .= $renderer->notice(
    $this->i18n('clear_content_structure_all'),
    $this->i18n('clear_content_structure_info'),
    true,
);
$notices .= $renderer->notice(
    $this->i18n('clear_content_category'),
    $this->i18n('clear_content_category_help'),
    true,
);
$notices .= $renderer->notice(
    $this->i18n('clear_content_delete_articles_category'),
    $this->i18n('clear_content_mass_articles_info') . ' ' . $this->i18n('clear_content_mass_articles_date_help'),
);
if ($synchronizationCleaner->hasTargets()) {
    $notices .= $renderer->notice(
        $this->i18n('clear_content_legend_sync_files'),
        $this->i18n('clear_content_sync_files_info'),
        true,
    );
}
$content = $renderer->layout($actions, $notices);

echo $renderer->renderSelection(
    $content,
    $controller->getCsrfToken(),
);
