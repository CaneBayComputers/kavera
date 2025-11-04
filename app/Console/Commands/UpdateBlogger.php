<?php

namespace App\Console\Commands;

use App\Services\BloggerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class UpdateBlogger extends Command
{
    /** @var string */
    protected $signature = 'app:update-blogger {--max_results=} {--limit=}';

    /** @var string */
    protected $description = 'Fetch Google Blogger public posts with API key and cache in Redis.';

    public function handle(BloggerService $blogger): int
    {
        if (!$blogger->isEnabled()) {
            $this->error('Blogger API not configured. Set BLOGGER_API_KEY and BLOGGER_BLOG_ID in .env');
            return self::FAILURE;
        }

        $perPage = $this->option('max_results');
        $perPage = $perPage !== null ? (int) $perPage : (int) config('services.blogger.max_results', 50);
        $limit = $this->option('limit');
        $limit = $limit !== null ? (int) $limit : null;

        try {
            $posts = $blogger->fetchAllPosts($perPage, $limit);
            $cacheKey = (string) config('services.blogger.cache_key', 'blogger.posts');

            // Save indefinitely (no TTL) as with other integrations
            Cache::forever($cacheKey, $posts);

            $this->info('Blogger posts saved to Redis: ' . count($posts) . ' post(s).');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Blogger fetch exception: ' . $e->getMessage());
            $this->line(Str::limit($e->getTraceAsString(), 500));
            return self::FAILURE;
        }
    }
}
