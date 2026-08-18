<?php

namespace FriendsOfRedaxo\ClearContent\Cleanup;

use rex_addon;
use rex_dir;
use rex_finder;
use rex_functional_exception;
use rex_i18n;
use rex_path;

final class SynchronizationFileCleaner
{
    public function hasTargets(): bool
    {
        return $this->getTargets() !== [];
    }

    public function count(): int
    {
        return array_sum(array_map($this->countFiles(...), $this->getTargets()));
    }

    /** @return list<string> */
    public function describe(): array
    {
        $details = [rex_i18n::msg('clear_content_preview_sync_unchanged')];
        foreach ($this->getTargets() as $path) {
            $details[] = rex_i18n::msg(
                'clear_content_preview_sync_path',
                $path,
                $this->countFiles($path),
            );
        }

        return $details;
    }

    public function delete(): int
    {
        $targets = $this->getTargets();
        $deleted = array_sum(array_map($this->countFiles(...), $targets));

        foreach ($targets as $path) {
            $this->assertSafeTarget($path);
            if (!rex_dir::delete($path)) {
                throw new rex_functional_exception(rex_i18n::msg('clear_content_error_sync_delete', $path));
            }
        }

        return $deleted;
    }

    /** @return list<string> */
    private function getTargets(): array
    {
        $targets = [];
        $developerPath = rtrim(rex_path::addonData('developer'), '/\\');
        if (is_dir($developerPath)) {
            $targets[] = $developerPath;
        }

        $theme = rex_addon::get('theme');
        if ($theme->isInstalled()) {
            $themeFolder = $theme->getProperty('theme_folder');
            if (is_string($themeFolder) && $themeFolder !== '') {
                $themePath = rtrim(rex_path::base($themeFolder . '/private/redaxo'), '/\\');
                if (is_dir($themePath)) {
                    $targets[] = $themePath;
                }
            }
        }

        return array_values(array_unique($targets));
    }

    private function countFiles(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        return iterator_count(
            rex_finder::factory($path)
                ->recursive()
                ->filesOnly()
                ->ignoreSystemStuff(false)
                ->ignoreUnreadableDirs(),
        );
    }

    private function assertSafeTarget(string $path): void
    {
        $basePath = rtrim((string) realpath(rex_path::base()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $resolved = realpath($path);
        if ($resolved === false) {
            return;
        }
        $resolved = rtrim($resolved, DIRECTORY_SEPARATOR);
        $safeName = in_array(basename($resolved), ['developer', 'redaxo'], true);
        if (!$safeName || !str_starts_with($resolved . DIRECTORY_SEPARATOR, $basePath)) {
            throw new rex_functional_exception(rex_i18n::msg('clear_content_error_sync_path', $path));
        }
    }
}
