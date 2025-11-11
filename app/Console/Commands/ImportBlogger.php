<?php

namespace App\Console\Commands;

use App\Services\BloggerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;

class ImportBlogger extends Command
{
    /** @var string */
    protected $signature = 'app:blogger-import {--per_page=} {--limit=} {--base=}';

    /** @var string */
    protected $description = 'Import public Blogger posts as Blade files under the configured content base. Always overwrites existing files.';

    public function handle(BloggerService $blogger): int
    {
        if (!$blogger->isEnabled()) {
            $this->error('Blogger API not configured. Set BLOGGER_API_KEY and BLOGGER_BLOG_ID in .env');
            return self::FAILURE;
        }

        $perPage = $this->option('per_page');
        $perPage = $perPage !== null ? (int) $perPage : (int) config('services.blogger.max_results', 50);
        $limit = $this->option('limit');
        $limit = $limit !== null ? (int) $limit : null;
        $base = (string) ($this->option('base') ?: config('services.blogger.content_base', 'blog'));
        $base = trim($base, '/');

        $items = $blogger->fetchAllPosts($perPage, $limit);

        // Reset indices so counts and lists reflect the current import set
        $this->resetIndices();

        $targetDir = base_path('resources/views/content/' . $base);
        File::ensureDirectoryExists($targetDir);
        // Ensure the base has a .gitignore so generated posts are not committed
        $gitignore = $targetDir . '/.gitignore';
        if (!File::exists($gitignore)) {
            File::put($gitignore, "# Generated blog posts\n*.blade.php\n");
        }

        $count = 0;
        foreach ($items as $post) {
            $slug = $this->deriveSlug($post);
            if ($slug === '') {
                // fallback to id
                $slug = strtolower($post['id'] ?? 'post');
            }

            $filename = $targetDir . '/' . $slug . '.blade.php';
            $contents = $this->renderBlade($post);
            File::put($filename, $contents);
            $this->line('Wrote: content/' . $base . '/' . $slug . '.blade.php');
            $count++;

            // Index to Redis for fast lists
            $id = (string) ($post['id'] ?? '');
            $publishedAt = (string) ($post['published_at'] ?? '');
            $ts = strtotime($publishedAt) ?: time();
            $path = '/' . $base . '/' . $slug;
            $preview = [
                'id' => $id,
                'title' => (string) ($post['title'] ?? ''),
                'url' => (string) ($post['url'] ?? ''),
                'published_at' => $publishedAt,
                'summary' => (string) ($post['summary'] ?? ''),
                'slug' => $slug,
                'path' => $path,
                'thumb' => $this->firstImageUrl((string) ($post['content_html'] ?? '')),
            ];
            Redis::set('blogger:post:' . $id, json_encode($preview, JSON_UNESCAPED_SLASHES));
            Redis::zadd('blogger:posts:by_published', $ts, $id);
            $ym = gmdate('Y-m', $ts);
            Redis::zadd('blogger:archive:' . $ym, $ts, $id);
            Redis::zadd('blogger:archives', (int) gmdate('Ym', $ts), $ym);

            $labels = $post['labels'] ?? [];
            if (is_array($labels)) {
                foreach ($labels as $label) {
                    $slugLabel = $this->cleanseSlug((string) $label);
                    if ($slugLabel === '') {
                        continue;
                    }
                    Redis::sadd('blogger:label:' . $slugLabel . ':ids', $id);
                    Redis::hincrby('blogger:labels', $slugLabel, 1);
                    Redis::hset('blogger:labels_display', $slugLabel, (string) $label);
                }
            }
        }

        // Maintain a pre-computed recent list (top 10)
        $ids = Redis::zrevrange('blogger:posts:by_published', 0, 9) ?: [];
        $recent = [];
        foreach ($ids as $pid) {
            $json = Redis::get('blogger:post:' . $pid);
            if ($json) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $recent[] = $decoded;
                }
            }
        }
        Redis::set('blogger:recent', json_encode($recent, JSON_UNESCAPED_SLASHES));

        $this->info("Imported {$count} post(s) under content/{$base}");

        return self::SUCCESS;
    }

    /**
     * Clear label counts/sets and archive/order indices before rebuilding.
     */
    private function resetIndices(): void
    {
        // Clear label counts and display names
        Redis::del('blogger:labels');
        Redis::del('blogger:labels_display');

        // Clear per-label id sets
        $labelSets = Redis::keys('blogger:label:*:ids') ?: [];
        if (!empty($labelSets)) {
            Redis::del($labelSets);
        }

        // Clear archive buckets and month list
        $archiveBuckets = Redis::keys('blogger:archive:*') ?: [];
        if (!empty($archiveBuckets)) {
            Redis::del($archiveBuckets);
        }
        Redis::del('blogger:archives');

        // Clear ordering and recent cache
        Redis::del('blogger:posts:by_published');
        Redis::del('blogger:recent');
    }

    private function deriveSlug(array $post): string
    {
        $url = (string) ($post['url'] ?? '');
        $candidate = '';
        if ($url !== '') {
            $path = parse_url($url, PHP_URL_PATH) ?: '';
            $segments = array_values(array_filter(explode('/', (string) $path)));
            $candidate = end($segments) ?: '';
            // Strip common Blogger ".html" suffix
            $candidate = preg_replace('/\.(html?|HTML?)$/', '', (string) $candidate) ?? '';
        }
        if ($candidate === '') {
            $candidate = (string) ($post['title'] ?? '');
        }
        return $this->cleanseSlug($candidate);
    }

    private function cleanseSlug(string $input): string
    {
        $regex = (string) config('content.allowed_path_regex', '/^[a-zA-Z0-9\/-]+$/');
        // Extract inner character class from ^[...]$ if present
        $charClass = 'a-zA-Z0-9\/\-';
        $trimmed = trim($regex, '/');
        if (preg_match('/^\^\[([^\]]+)\]\+\$$/', $trimmed, $m)) {
            $charClass = $m[1];
        }
        // For a single slug segment, do not allow slashes; remove them from class
        $charClass = str_replace('/', '', $charClass);
        $slug = strtolower($input);
        // Replace any disallowed characters with a space
        $slug = preg_replace('/[^' . $charClass . ']+/u', ' ', $slug) ?? '';
        // Collapse whitespace to single dash
        $slug = preg_replace('/\s+/', '-', trim($slug)) ?? '';
        // Trim extraneous dashes
        return trim($slug, '-');
    }

    private function renderBlade(array $post): string
    {
        $title = (string) ($post['title'] ?? '');
        $desc = (string) ($post['summary'] ?? '');
        if (mb_strlen($desc) > 155) {
            $desc = rtrim(mb_substr($desc, 0, 154)) . '…';
        }
        $html = (string) ($post['content_html'] ?? '');

        $out = [];
        $layout = (string) config('services.blogger.post_layout', 'templates.main');
        $section = (string) config('services.blogger.post_section', 'content');
        $out[] = "@extends('{$layout}')";
        $out[] = '';
        $out[] = '@php';
        $out[] = '    $pageTitle = ' . var_export($title, true) . ';';
        $out[] = '    $pageDescription = ' . var_export($desc, true) . ';';
        $out[] = '@endphp';
        $out[] = '';
        $out[] = "@section('{$section}')";
        $out[] = '<section class="py-5"><div class="container">';
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $out[] = '  <h1 class="mb-3">' . $safeTitle . '</h1>';
        $out[] = '  <div class="blog-post-content">';
        // Insert raw HTML body as-is
        $out[] = $html;
        $out[] = '  </div>';
        $out[] = '</div></section>';
        $out[] = '@endsection';

        return implode("\n", $out) . "\n";
    }

    private function firstImageUrl(string $html): ?string
    {
        if ($html === '') {
            return null;
        }
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
            return $m[1] ?? null;
        }
        return null;
    }
}
