<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PixabayService
{
    public function isEnabled(): bool
    {
        return (string) config('services.pixabay.key') !== '';
    }

    /**
     * Search Pixabay for images.
     *
     * @param string $query
     * @param array{image_type?:string,orientation?:string,order?:string,safesearch?:int,per_page?:int,page?:int,category?:string} $options
     * @return array<int, array<string,mixed>> Raw hits array (empty if disabled or error)
     */
    public function search(string $query, array $options = []): array
    {
        $key = (string) config('services.pixabay.key');
        $baseUrl = (string) config('services.pixabay.base_url', 'https://pixabay.com/api/');
        $timeout = (int) config('services.pixabay.timeout', 6);

        if ($key === '' || trim($query) === '') {
            return [];
        }

        $params = array_filter([
            'key' => $key,
            'q' => trim($query),
            'image_type' => (string) ($options['image_type'] ?? 'photo'),
            'orientation' => (string) ($options['orientation'] ?? ''),
            'order' => (string) ($options['order'] ?? 'popular'),
            'safesearch' => (int) ($options['safesearch'] ?? 1),
            'per_page' => (int) ($options['per_page'] ?? 20),
            'page' => (int) ($options['page'] ?? 1),
            'category' => (string) ($options['category'] ?? ''),
        ], static fn($v) => !($v === '' || $v === null));

        try {
            $resp = Http::timeout($timeout)->get($baseUrl, $params);
            if (!$resp->ok()) {
                return [];
            }
            $json = $resp->json() ?: [];
            $hits = $json['hits'] ?? [];
            return is_array($hits) ? $hits : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
