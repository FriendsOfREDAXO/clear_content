<?php

namespace FriendsOfRedaxo\ClearContent\Cleanup;

use rex_functional_exception;
use rex_i18n;

final class CleanupWorkflow
{
    private const SESSION_KEY = 'clear_content_cleanup_preview';
    private const LIFETIME = 900;

    public function preview(CleanupRequest $request, int $userId): CleanupWorkflowResult
    {
        $preview = (new CleanupPreviewService())->create($request);
        $token = bin2hex(random_bytes(32));

        \rex_set_session(self::SESSION_KEY, [
            'token' => $token,
            'request' => $request->toArray(),
            'affected_records' => $preview->affectedRecords,
            'preview_signature' => $this->previewSignature($preview),
            'user_id' => $userId,
            'expires_at' => time() + self::LIFETIME,
        ]);

        return new CleanupWorkflowResult($preview, $token);
    }

    public function execute(CleanupRequest $request, int $userId, string $token, bool $confirmed): int
    {
        $stored = \rex_session(self::SESSION_KEY, 'array', []);
        $valid = isset(
            $stored['token'],
            $stored['request'],
            $stored['affected_records'],
            $stored['preview_signature'],
            $stored['user_id'],
            $stored['expires_at'],
        )
            && is_string($stored['token'])
            && is_array($stored['request'])
            && hash_equals($stored['token'], $token)
            && CleanupRequest::fromArray($stored['request'])->toArray() === $request->toArray()
            && (int) $stored['user_id'] === $userId
            && (int) $stored['expires_at'] >= time();

        if (!$valid) {
            throw new rex_functional_exception(rex_i18n::msg('clear_content_error_preview'));
        }
        if (!$confirmed) {
            throw new rex_functional_exception(rex_i18n::msg('clear_content_error_confirmation'));
        }

        $currentPreview = (new CleanupPreviewService())->create($request);
        if ($currentPreview->affectedRecords !== (int) $stored['affected_records']
            || !is_string($stored['preview_signature'])
            || !hash_equals($stored['preview_signature'], $this->previewSignature($currentPreview))
        ) {
            \rex_unset_session(self::SESSION_KEY);
            throw new rex_functional_exception(rex_i18n::msg('clear_content_error_preview_changed'));
        }

        // A preview token is single-use, including when execution fails later.
        \rex_unset_session(self::SESSION_KEY);

        return (new CleanupExecutor())->execute($request);
    }

    private function previewSignature(CleanupPreview $preview): string
    {
        return hash('sha256', serialize([
            $preview->title,
            $preview->affectedRecords,
            $preview->details,
            $preview->allowsEmptyExecution,
            $preview->emptyMessageKey,
        ]));
    }
}
