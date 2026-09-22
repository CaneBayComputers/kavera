<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * The list of content views allowed to resolve. Cached forever, rebuilt on
 * demand, so a cache clear costs one directory scan instead of a dead site.
 */
class ContentRegistry
{
    public const CACHE_KEY = 'content_list';

    /**
     * Scan resources/views/content and return the slugs (paths without .blade.php).
     *
     * @return list<string>
     */
    public function scan(): array
    {
        $contentPath = resource_path('views/content');
        if (! is_dir($contentPath)) {
            return [];
        }

        $slugs = [];
        foreach (File::allFiles($contentPath) as $file) {
            $relative = $file->getRelativePathname();
            if (! str_ends_with($relative, '.blade.php')) {
                continue;
            }
            $slugs[] = str_replace(DIRECTORY_SEPARATOR, '/', preg_replace('/\.blade\.php$/', '', $relative));
        }
        sort($slugs);

        return $slugs;
    }

    /**
     * The cached registry, rebuilt automatically if the cache was cleared.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $list = Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->scan());

        // Older installs stored a JSON string; accept it once and let the next refresh fix it.
        if (is_string($list)) {
            $list = json_decode($list, true) ?: [];
        }

        return is_array($list) ? array_values($list) : [];
    }

    /**
     * Rescan and overwrite the cache.
     *
     * @return list<string>
     */
    public function refresh(): array
    {
        $slugs = $this->scan();
        Cache::forever(self::CACHE_KEY, $slugs);

        return $slugs;
    }

    public function allows(string $slug): bool
    {
        return in_array($slug, $this->all(), true);
    }
}
