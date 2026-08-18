<?php

namespace FriendsOfRedaxo\ClearContent\Backend;

use FriendsOfRedaxo\ClearContent\Cleanup\CleanupRequest;
use FriendsOfRedaxo\ClearContent\Cleanup\CleanupWorkflow;
use FriendsOfRedaxo\ClearContent\Cleanup\CleanupWorkflowResult;
use rex;
use rex_addon;
use rex_csrf_token;
use rex_functional_exception;
use rex_i18n;
use rex_logger;
use rex_view;
use Throwable;

final class CleanupPageController
{
    private rex_csrf_token $csrfToken;

    public function __construct(private rex_addon $addon)
    {
        $this->csrfToken = rex_csrf_token::factory('clear_content_cleanup');
    }

    public function getCsrfToken(): rex_csrf_token
    {
        return $this->csrfToken;
    }

    /** @param list<string> $allowedActions */
    public function handle(CleanupRequest $request, array $allowedActions): ?CleanupWorkflowResult
    {
        if ($request->action === '') {
            return null;
        }

        try {
            if (!in_array($request->action, $allowedActions, true)) {
                throw new rex_functional_exception($this->addon->i18n('clear_content_error_action'));
            }
            $user = rex::requireUser();
            if (!$user->isAdmin()) {
                throw new rex_functional_exception($this->addon->i18n('clear_content_error_admin'));
            }
            if (!$this->csrfToken->isValid()) {
                throw new rex_functional_exception(rex_i18n::msg('csrf_token_invalid'));
            }

            $workflow = new CleanupWorkflow();
            $mode = \rex_post('cleanup_mode', 'string', '');
            if ($mode === 'preview') {
                return $workflow->preview($request, $user->getId());
            }
            if ($mode !== 'execute') {
                throw new rex_functional_exception($this->addon->i18n('clear_content_error_preview'));
            }

            $deleted = $workflow->execute(
                $request,
                $user->getId(),
                \rex_post('preview_token', 'string', ''),
                \rex_post('cleanup_confirmed', 'bool', false),
            );
            \rex_delete_cache();
            echo rex_view::success($this->addon->i18n('clear_content_success', $deleted));
        } catch (Throwable $exception) {
            rex_logger::logException($exception);
            echo rex_view::error(\rex_escape($exception->getMessage()));
        }

        return null;
    }
}
