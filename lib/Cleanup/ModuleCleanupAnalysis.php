<?php

namespace FriendsOfRedaxo\ClearContent\Cleanup;

final readonly class ModuleCleanupAnalysis
{
    /**
     * @param array<int, int> $directSlicesByModule
     * @param array<int, int> $nestedBlocksByModule
     * @param array<int, int> $nestedHistoryByModule
     */
    public function __construct(
        public array $directSlicesByModule,
        public array $nestedBlocksByModule,
        public array $nestedHistoryByModule,
    ) {
    }

    public function getCurrentCount(): int
    {
        return array_sum($this->directSlicesByModule) + array_sum($this->nestedBlocksByModule);
    }
}
