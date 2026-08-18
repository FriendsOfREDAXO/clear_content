<?php

namespace FriendsOfRedaxo\ClearContent\Cleanup;

final readonly class CleanupWorkflowResult
{
    public function __construct(
        public CleanupPreview $preview,
        public string $token,
    ) {
    }
}
