<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BloggerService
{
    /**
     * Returns true if API key and blog ID are configured.
     */
    public function isEnabled(): bool
    {
        return $this->getApiKey() !== '' && $this->getBlogId() !== '';
    }

    /**
     * Fetch a single page of posts from Blogger API.
     * Returns an array: [ 'items' => array<array>, 'nextPageToken' => ?string ]
     *
     * @param int|null $maxResults Optional max results per page (1-500 per API docs)
     * @param string|null $pageToken Optional pagination token
     * @return array{items: array<int, array<string,mixed>>, nextPageToken?: string}
     */
    public function fetchPosts(?int $maxResults = null, ?string $pageToken = null): array
    {
        $apiKey = $this->getApiKey();
        $blogId = $this->getBlogId();
        $base = rtrim((string) config('services.blogger.base_url', 'https://www.googleapis.com/blogger/v3'), '/');
        $timeout = (int) config('services.blogger.timeout', 8);

        if ($apiKey === '' || $blogId === '') {
            return ['items' => []];
        }

        $params = [
            'key' => $apiKey,
        ];

        $max = $maxResults ?? (int) config('services.blogger.max_results', 50);
        if ($max > 0) {
            $params['maxResults'] = $max;
        }
        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }

        $url = sprintf('%s/blogs/%s/posts', $base, urlencode($blogId));

        $resp = Http::timeout($timeout)->get($url, $params);

        if (!$resp->ok()) {
            return ['items' => []];
        }

        $json = $resp->json() ?: [];
        $items = $json['items'] ?? [];
        if (!is_array($items)) {
            $items = [];
        }

        // Normalize items for downstream usage
        $normalized = array_map([$this, 'normalizeItem'], $items);

        $out = ['items' => $normalized];
        if (!empty($json['nextPageToken']) && is_string($json['nextPageToken'])) {
            $out['nextPageToken'] = $json['nextPageToken'];
        }

        return $out;
    }

    /**
     * Fetch all posts by paging until no nextPageToken, or until $hardLimit items are collected if provided.
     *
     * @param int|null $perPage Max results per page
     * @param int|null $hardLimit Optional hard limit of total items to collect
     * @return array<int, array<string,mixed>>
     */
    public function fetchAllPosts(?int $perPage = null, ?int $hardLimit = null): array
    {
        $all = [];
        $pageToken = null;

        do {
            $page = $this->fetchPosts($perPage, $pageToken);
            $items = $page['items'] ?? [];
            foreach ($items as $item) {
                $all[] = $item;
                if ($hardLimit !== null && count($all) >= $hardLimit) {
                    return $all;
                }
            }
            $pageToken = $page['nextPageToken'] ?? null;
        } while ($pageToken);

        return $all;
    }

    /**
     * Normalize a Blogger post record into a consistent structure.
     *
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    public function normalizeItem(array $item): array
    {
        $author = $item['author'] ?? [];
        $labels = $item['labels'] ?? [];

        $content = (string) ($item['content'] ?? '');

        return [
            'id' => (string) ($item['id'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'url' => (string) ($item['url'] ?? ''),
            'published_at' => (string) ($item['published'] ?? ''),
            'updated_at' => (string) ($item['updated'] ?? ''),
            'author_name' => (string) ($author['displayName'] ?? ''),
            'author_url' => (string) ($author['url'] ?? ''),
            'labels' => is_array($labels) ? array_values($labels) : [],
            // HTML content from Blogger; downstream can render as-is or strip as needed
            'content_html' => $content,
            // Derive a simple plaintext summary (first 200 chars) by stripping tags
            'summary' => $this->summarize($content, 200),
        ];
    }

    private function summarize(string $html, int $len): string
    {
        $text = trim(html_entity_decode(strip_tags($html)));
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) <= $len) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $len - 1)) . '…';
    }

    private function getApiKey(): string
    {
        return (string) config('services.blogger.api_key');
    }

    private function getBlogId(): string
    {
        return (string) config('services.blogger.blog_id');
    }
}
