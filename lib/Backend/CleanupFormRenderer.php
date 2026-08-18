<?php

namespace FriendsOfRedaxo\ClearContent\Backend;

use FriendsOfRedaxo\ClearContent\Cleanup\CleanupRequest;
use FriendsOfRedaxo\ClearContent\Cleanup\CleanupWorkflowResult;
use rex_addon;
use rex_csrf_token;
use rex_fragment;
use rex_url;
use rex_view;

final class CleanupFormRenderer
{
    public function __construct(private rex_addon $addon)
    {
    }

    public function previewButton(string $action, string $label, string $icon = ''): string
    {
        $iconMarkup = $icon === ''
            ? ''
            : '<i class="rex-icon ' . \rex_escape($icon, 'html_attr') . '" aria-hidden="true"></i> ';

        return '<button class="btn btn-primary" type="submit" name="cleanup_action" value="' . \rex_escape($action, 'html_attr') . '">'
            . $iconMarkup . \rex_escape($label)
            . '</button>';
    }

    public function actions(string $buttons): string
    {
        return '<div class="btn-toolbar form-control-static" role="toolbar" style="margin-top: 8px">' . $buttons . '</div>';
    }

    public function field(string $label, string $fieldContent, string $note = ''): string
    {
        $fragment = new rex_fragment();
        $fragment->setVar('elements', [[
            'label' => '<label>' . \rex_escape($label) . '</label>',
            'field' => $fieldContent,
            'note' => $note === '' ? '' : \rex_escape($note),
        ]], false);

        return $fragment->parse('core/form/form.php');
    }

    public function section(string $title, string $body): string
    {
        $fragment = new rex_fragment();
        $fragment->setVar('title', \rex_escape($title), false);
        $fragment->setVar('body', $body, false);

        return $fragment->parse('core/page/section.php');
    }

    public function notice(string $title, string $text, bool $warning = false): string
    {
        $iconClass = $warning ? 'fa-exclamation-triangle text-warning' : 'fa-info-circle text-info';

        return '<div class="media">'
            . '<div class="media-left"><i class="rex-icon ' . $iconClass . '" aria-hidden="true"></i></div>'
            . '<div class="media-body"><h4 class="media-heading">' . \rex_escape($title) . '</h4>'
            . '<p class="text-muted">' . \rex_escape($text) . '</p></div>'
            . '</div>';
    }

    public function layout(string $actions, string $notices): string
    {
        if ($notices === '') {
            return '<div class="row"><div class="col-sm-12">' . $actions . '</div></div>';
        }

        return '<div class="row">'
            . '<div class="col-md-8">' . $actions . '</div>'
            . '<aside class="col-md-4">'
            . $this->section($this->addon->i18n('clear_content_notes'), $notices)
            . '</aside></div>';
    }

    public function renderPreview(
        CleanupWorkflowResult $result,
        CleanupRequest $request,
        rex_csrf_token $csrfToken,
    ): string {
        $details = '';
        foreach ($result->preview->details as $detail) {
            // Preview texts originate from rex_i18n::msg(), which already
            // returns HTML-escaped output.
            $details .= '<li class="list-group-item"><i class="rex-icon fa-check text-success" aria-hidden="true"></i> '
                . $detail . '</li>';
        }

        $body = '<div class="alert alert-info" role="status">'
            . '<i class="rex-icon fa-info-circle" aria-hidden="true"></i> '
            . '<strong>' . \rex_escape($this->addon->i18n('clear_content_preview_status')) . ':</strong> '
            . \rex_escape($this->addon->i18n('clear_content_preview_warning')) . '</div>';
        $body .= '<div class="table-responsive"><table class="table table-bordered table-striped">'
            . '<tbody><tr><th scope="row" class="rex-table-width-20">' . \rex_escape($this->addon->i18n('clear_content_preview_action_label')) . '</th><td><strong>' . $result->preview->title . '</strong></td></tr>'
            . '<tr><th scope="row">' . \rex_escape($this->addon->i18n('clear_content_preview_count')) . '</th>'
            . '<td><span class="badge" style="min-width: 42px; padding: 7px 11px; font-size: 18px">'
            . $result->preview->affectedRecords . '</span></td></tr>'
            . '</tbody></table></div>';
        if ($details !== '') {
            $body .= '<h4 style="margin-top: 20px">' . \rex_escape($this->addon->i18n('clear_content_preview_selection')) . '</h4>'
                . '<ul class="list-group">' . $details . '</ul>';
        }
        $buttons = '<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px">'
            . '<a class="btn btn-default btn-lg" href="'
            . \rex_escape(rex_url::currentBackendPage(), 'html_attr') . '">'
            . \rex_escape($this->addon->i18n('clear_content_preview_back')) . '</a>';
        if ($result->preview->affectedRecords === 0 && !$result->preview->allowsEmptyExecution) {
            $body .= rex_view::info($this->addon->i18n('clear_content_preview_empty'));
        } else {
            if ($result->preview->affectedRecords === 0) {
                $body .= rex_view::info($this->addon->i18n($result->preview->emptyMessageKey));
            }
            $buttons .= '<div style="display: flex; align-items: center; flex-wrap: wrap; gap: 15px">'
                . '<label class="checkbox-inline" style="margin: 0; padding-left: 30px; font-size: 16px; line-height: 1.4">'
                . '<input type="checkbox" name="cleanup_confirmed" value="1" required '
                . 'style="width: 20px; height: 20px; margin-top: 1px; margin-left: -30px"> '
                . \rex_escape($this->addon->i18n('clear_content_confirmation_checkbox'))
                . '</label> '
                . '<button class="btn btn-danger btn-lg" type="submit" data-confirm="'
                . \rex_escape($this->addon->i18n('clear_content_confirmation'), 'html_attr') . '">'
                . \rex_escape($this->addon->i18n('clear_content_execute')) . '</button></div>';
        }
        $buttons .= '</div>';

        $fragment = new rex_fragment();
        $fragment->setVar('title', $this->addon->i18n('clear_content_preview_title'));
        $fragment->setVar('body', $body, false);
        $fragment->setVar('buttons', $buttons, false);

        return '<form action="' . \rex_escape(rex_url::currentBackendPage(), 'html_attr') . '" method="post">'
            . $csrfToken->getHiddenField()
            . '<input type="hidden" name="cleanup_mode" value="execute">'
            . $this->hiddenRequestFields($request)
            . '<input type="hidden" name="preview_token" value="' . \rex_escape($result->token, 'html_attr') . '">'
            . $fragment->parse('core/page/section.php')
            . '</form>';
    }

    public function renderSelection(string $content, rex_csrf_token $csrfToken): string
    {
        return '<form action="' . \rex_escape(rex_url::currentBackendPage(), 'html_attr') . '" method="post">'
            . $csrfToken->getHiddenField()
            . '<input type="hidden" name="cleanup_mode" value="preview">'
            . $content
            . '</form>';
    }

    private function hiddenRequestFields(CleanupRequest $request): string
    {
        $fields = '<input type="hidden" name="cleanup_action" value="' . \rex_escape($request->action, 'html_attr') . '">'
            . '<input type="hidden" name="older_than" value="' . \rex_escape($request->olderThan, 'html_attr') . '">';
        foreach ($request->tableNames as $tableName) {
            $fields .= '<input type="hidden" name="table_names[]" value="' . \rex_escape($tableName, 'html_attr') . '">';
        }
        foreach ($request->moduleIds as $moduleId) {
            $fields .= '<input type="hidden" name="module_ids[]" value="' . $moduleId . '">';
        }
        foreach ($request->sliceArticleIds as $articleId) {
            $fields .= '<input type="hidden" name="slice_article_ids[]" value="' . $articleId . '">';
        }
        foreach ($request->clangIds as $clangId) {
            $fields .= '<input type="hidden" name="clang_ids[]" value="' . $clangId . '">';
        }
        foreach ($request->categoryIds as $categoryId) {
            $fields .= '<input type="hidden" name="category_ids[]" value="' . $categoryId . '">';
        }
        foreach ($request->mediaCategoryIds as $categoryId) {
            $fields .= '<input type="hidden" name="media_category_ids[]" value="' . $categoryId . '">';
        }

        return $fields;
    }
}
