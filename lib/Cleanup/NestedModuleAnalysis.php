<?php

namespace FriendsOfRedaxo\ClearContent\Cleanup;

final readonly class NestedModuleAnalysis
{
    /**
     * @param array<int, int> $currentByModule
     * @param array<int, int> $historyByModule
     */
    public function __construct(
        public array $currentByModule,
        public array $historyByModule,
    ) {
    }

    public function getCurrentCount(): int
    {
        return array_sum($this->currentByModule);
    }
}
