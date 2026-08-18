<?php

namespace FriendsOfRedaxo\ClearContent\Cleanup;

final readonly class CleanupPreview
{
    /** @param list<string> $details */
    public function __construct(
        public string $title,
        public int $affectedRecords,
        public array $details = [],
        public bool $allowsEmptyExecution = false,
        public string $emptyMessageKey = 'clear_content_preview_empty_structure',
    ) {
    }
}
