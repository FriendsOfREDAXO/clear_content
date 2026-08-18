<?php

use FriendsOfRedaxo\ClearContent\Backend\CleanupFormRenderer;
use FriendsOfRedaxo\ClearContent\Backend\CleanupPageController;
use FriendsOfRedaxo\ClearContent\Cleanup\CleanupRequest;
use FriendsOfRedaxo\ClearContent\Cleanup\TableCleaner;

/** @var rex_addon $this */

$request = CleanupRequest::fromPost();
$controller = new CleanupPageController($this);
$renderer = new CleanupFormRenderer($this);
$workflowResult = $controller->handle($request, ['tables', 'tables_drop']);

if ($workflowResult !== null) {
    echo $renderer->renderPreview($workflowResult, $request, $controller->getCsrfToken());
    return;
}

$availableTables = (new TableCleaner())->getAvailableTables();
$tableSelect = new rex_select();
$tableSelect->setName('table_names[]');
$tableSelect->setId('clear-content-tables');
$tableSelect->setMultiple();
$tableSelect->setSize(min(8, count($availableTables)));
$tableSelect->setAttribute('class', 'selectpicker show-menu-arrow form-control');
$tableSelect->setAttribute('data-actions-box', 'true');
$tableSelect->setAttribute('data-live-search', 'true');
$tableSelect->setAttribute('data-size', '10');
foreach ($availableTables as $tableName => $label) {
    $tableSelect->addOption($label . ' [' . $tableName . ']', $tableName);
}
$tableSelect->setSelected($request->tableNames);

if ($availableTables === []) {
    $section = rex_view::info($this->i18n('clear_content_tables_empty'));
} else {
    $section = $renderer->field($this->i18n('clear_content_tables_selection'), $tableSelect->get());
    $section .= $renderer->field(
        $this->i18n('clear_content_tables_action'),
        $renderer->actions(
            $renderer->previewButton('tables', $this->i18n('clear_content_delete_tables'))
            . ' '
            . $renderer->previewButton('tables_drop', $this->i18n('clear_content_drop_tables')),
        ),
    );
}
$actions = $renderer->section($this->i18n('clear_content_legend_tables'), $section);
$notices = '';
if ($availableTables !== []) {
    $notices = $renderer->notice(
        $this->i18n('clear_content_delete_tables'),
        $this->i18n('clear_content_tables_info'),
    );
    $notices .= $renderer->notice(
        $this->i18n('clear_content_drop_tables'),
        $this->i18n('clear_content_tables_drop_info'),
        true,
    );
}
$content = $renderer->layout($actions, $notices);

echo $renderer->renderSelection(
    $content,
    $controller->getCsrfToken(),
);
