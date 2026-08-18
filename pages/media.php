<?php

use FriendsOfRedaxo\ClearContent\Backend\CleanupFormRenderer;
use FriendsOfRedaxo\ClearContent\Backend\CleanupPageController;
use FriendsOfRedaxo\ClearContent\Cleanup\CleanupRequest;

/** @var rex_addon $this */

$request = CleanupRequest::fromPost();
$controller = new CleanupPageController($this);
$renderer = new CleanupFormRenderer($this);
$workflowResult = $controller->handle($request, ['media_categories', 'media_category']);

if ($workflowResult !== null) {
    echo $renderer->renderPreview($workflowResult, $request, $controller->getCsrfToken());
    return;
}

$categorySelect = new rex_media_category_select(false);
$categorySelect->setName('media_category_ids[]');
$categorySelect->setId('clear-content-media-category');
$categorySelect->setMultiple();
$categorySelect->setSize(8);
$categorySelect->setAttribute('class', 'selectpicker show-menu-arrow form-control');
$categorySelect->setAttribute('data-actions-box', 'true');
$categorySelect->setAttribute('data-live-search', 'true');
$categorySelect->setAttribute('data-size', '10');
$categorySelect->setAttribute('title', $this->i18n('clear_content_media_category_select'));
$categorySelect->setSelected($request->mediaCategoryIds);
$hasMediaCategories = rex_media_category::getRootCategories() !== [];

if ($hasMediaCategories) {
    $section = $renderer->field(
        $this->i18n('clear_content_media_categories'),
        $categorySelect->get()
        . $renderer->actions(
            $renderer->previewButton('media_categories', $this->i18n('clear_content_delete_media_categories'), 'fa-folder-o')
            . ' '
            . $renderer->previewButton('media_category', $this->i18n('clear_content_delete_media_category'), 'fa-picture-o'),
        ),
    );
} else {
    $section = rex_view::info($this->i18n('clear_content_media_categories_empty'));
}
$actions = $renderer->section($this->i18n('clear_content_legend_media'), $section);
$notices = '';
if ($hasMediaCategories) {
    $notices = $renderer->notice(
        $this->i18n('clear_content_delete_media_categories'),
        $this->i18n('clear_content_media_categories_help'),
    );
    $notices .= $renderer->notice(
        $this->i18n('clear_content_delete_media_category'),
        $this->i18n('clear_content_media_category_help'),
        true,
    );
}
$content = $renderer->layout($actions, $notices);

echo $renderer->renderSelection(
    $content,
    $controller->getCsrfToken(),
);
