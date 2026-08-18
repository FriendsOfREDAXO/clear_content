<?php

namespace FriendsOfRedaxo\ClearContent\Cleanup;

final readonly class CleanupRequest
{
    /**
     * @param list<string> $tableNames
     * @param list<int> $moduleIds
     * @param list<int> $sliceArticleIds
     * @param list<int> $clangIds
     * @param list<int> $categoryIds
     * @param list<int> $mediaCategoryIds
     */
    public function __construct(
        public string $action,
        public array $tableNames = [],
        public array $moduleIds = [],
        public array $sliceArticleIds = [],
        public string $olderThan = '',
        public array $clangIds = [],
        public array $categoryIds = [],
        public array $mediaCategoryIds = [],
    ) {
    }

    public static function fromPost(): self
    {
        $tableNames = array_values(array_unique(array_filter(
            \rex_post('table_names', 'array', []),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        )));
        sort($tableNames);
        $moduleIds = array_values(array_unique(array_filter(
            array_map('intval', \rex_post('module_ids', 'array', [])),
            static fn (int $value): bool => $value > 0,
        )));
        sort($moduleIds);
        $sliceArticleIds = array_values(array_unique(array_filter(
            array_map('intval', \rex_post('slice_article_ids', 'array', [])),
            static fn (int $value): bool => $value > 0,
        )));
        sort($sliceArticleIds);
        $availableClangIds = \rex_clang::getAllIds(false);
        $clangIds = array_values(array_unique(array_filter(
            array_map('intval', \rex_post('clang_ids', 'array', [])),
            static fn (int $value): bool => $value > 0,
        )));
        if ($clangIds === [] && count($availableClangIds) === 1) {
            $clangIds = $availableClangIds;
        }
        sort($clangIds);
        $categoryIds = array_values(array_unique(array_filter(
            array_map('intval', \rex_post('category_ids', 'array', [])),
            static fn (int $value): bool => $value > 0,
        )));
        sort($categoryIds);
        $mediaCategoryIds = array_values(array_unique(array_filter(
            array_map('intval', \rex_post('media_category_ids', 'array', [])),
            static fn (int $value): bool => $value > 0,
        )));
        sort($mediaCategoryIds);

        return new self(
            \rex_post('cleanup_action', 'string', ''),
            $tableNames,
            $moduleIds,
            $sliceArticleIds,
            \rex_post('older_than', 'string', ''),
            $clangIds,
            $categoryIds,
            $mediaCategoryIds,
        );
    }

    /** @return array{action: string, table_names: list<string>, module_ids: list<int>, slice_article_ids: list<int>, older_than: string, clang_ids: list<int>, category_ids: list<int>, media_category_ids: list<int>} */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'table_names' => $this->tableNames,
            'module_ids' => $this->moduleIds,
            'slice_article_ids' => $this->sliceArticleIds,
            'older_than' => $this->olderThan,
            'clang_ids' => $this->clangIds,
            'category_ids' => $this->categoryIds,
            'media_category_ids' => $this->mediaCategoryIds,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $tableNames = isset($data['table_names']) && is_array($data['table_names'])
            ? array_values(array_filter($data['table_names'], 'is_string'))
            : [];
        sort($tableNames);
        $moduleIds = isset($data['module_ids']) && is_array($data['module_ids'])
            ? array_values(array_unique(array_filter(
                array_map('intval', $data['module_ids']),
                static fn (int $value): bool => $value > 0,
            )))
            : [];
        sort($moduleIds);
        $sliceArticleIds = isset($data['slice_article_ids']) && is_array($data['slice_article_ids'])
            ? array_values(array_unique(array_filter(
                array_map('intval', $data['slice_article_ids']),
                static fn (int $value): bool => $value > 0,
            )))
            : [];
        sort($sliceArticleIds);
        $clangIds = isset($data['clang_ids']) && is_array($data['clang_ids'])
            ? array_values(array_unique(array_filter(
                array_map('intval', $data['clang_ids']),
                static fn (int $value): bool => $value > 0,
            )))
            : [];
        sort($clangIds);
        $categoryIds = isset($data['category_ids']) && is_array($data['category_ids'])
            ? array_values(array_unique(array_filter(
                array_map('intval', $data['category_ids']),
                static fn (int $value): bool => $value > 0,
            )))
            : [];
        sort($categoryIds);
        $mediaCategoryIds = isset($data['media_category_ids']) && is_array($data['media_category_ids'])
            ? array_values(array_unique(array_filter(
                array_map('intval', $data['media_category_ids']),
                static fn (int $value): bool => $value > 0,
            )))
            : [];
        sort($mediaCategoryIds);

        return new self(
            is_string($data['action'] ?? null) ? $data['action'] : '',
            $tableNames,
            $moduleIds,
            $sliceArticleIds,
            is_string($data['older_than'] ?? null) ? $data['older_than'] : '',
            $clangIds,
            $categoryIds,
            $mediaCategoryIds,
        );
    }
}
