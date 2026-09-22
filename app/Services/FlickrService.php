<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Read-only Flickr REST client for public albums (photosets). Only an API key
 * is needed; nothing here requires OAuth.
 */
final class FlickrService
{
    private const PHOTO_EXTRAS = 'url_q,url_m,url_z,url_c,url_l,url_h,url_o,date_taken,date_upload,description,tags';

    private readonly string $apiKey;

    private readonly string $userId;

    public function __construct(
        ?string $apiKey = null,
        ?string $userId = null,
        private readonly string $baseUrl = 'https://www.flickr.com/services/rest/',
        private readonly int $timeout = 15,
    ) {
        // Defaults come from config so the container can resolve the service with no arguments.
        $this->apiKey = trim((string) ($apiKey ?? config('services.flickr.api_key', '')));
        $this->userId = trim((string) ($userId ?? config('services.flickr.user_id', '')));
    }

    public function isEnabled(): bool
    {
        return $this->apiKey !== '' && $this->userId !== '';
    }

    /**
     * Accepts an NSID (12345678@N01) or a Flickr username and returns the NSID.
     */
    public function resolveUserId(): string
    {
        if (str_contains($this->userId, '@N')) {
            return $this->userId;
        }

        $data = $this->call('flickr.people.findByUsername', ['username' => $this->userId]);
        $nsid = (string) ($data['user']['nsid'] ?? '');
        if ($nsid === '') {
            throw new RuntimeException('Flickr user not found: ' . $this->userId);
        }

        return $nsid;
    }

    /**
     * All albums for the user, without their photos.
     *
     * @return list<array<string, mixed>>
     */
    public function listAlbums(string $nsid): array
    {
        $albums = [];
        $page = 1;
        do {
            $data = $this->call('flickr.photosets.getList', [
                'user_id' => $nsid,
                'per_page' => 500,
                'page' => $page,
                'primary_photo_extras' => 'url_q,url_m,url_l',
            ]);
            $set = $data['photosets'] ?? [];
            foreach ((array) ($set['photoset'] ?? []) as $album) {
                $albums[] = $this->normalizeAlbum((array) $album, $nsid);
            }
            $pages = (int) ($set['pages'] ?? 1);
            $page++;
        } while ($page <= $pages);

        return $albums;
    }

    /**
     * Photos in one album, newest first as Flickr orders them.
     *
     * @return list<array<string, mixed>>
     */
    public function albumPhotos(string $nsid, string $albumId, int $maxPhotos = 500): array
    {
        $photos = [];
        $page = 1;
        do {
            $data = $this->call('flickr.photosets.getPhotos', [
                'user_id' => $nsid,
                'photoset_id' => $albumId,
                'extras' => self::PHOTO_EXTRAS,
                'per_page' => min(500, $maxPhotos),
                'page' => $page,
                'media' => 'photos',
            ]);
            $set = $data['photoset'] ?? [];
            $owner = (string) ($set['owner'] ?? $nsid);
            foreach ((array) ($set['photo'] ?? []) as $photo) {
                $photos[] = $this->normalizePhoto((array) $photo, $owner);
                if (count($photos) >= $maxPhotos) {
                    return $photos;
                }
            }
            $pages = (int) ($set['pages'] ?? 1);
            $page++;
        } while ($page <= $pages);

        return $photos;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function call(string $method, array $params): array
    {
        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->get($this->baseUrl, $params + [
                'method' => $method,
                'api_key' => $this->apiKey,
                'format' => 'json',
                'nojsoncallback' => 1,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Flickr ' . $method . ' failed: HTTP ' . $response->status());
        }

        $data = $response->json();
        if (! is_array($data) || ($data['stat'] ?? '') !== 'ok') {
            $message = is_array($data) ? (string) ($data['message'] ?? 'unknown error') : 'non-JSON response';
            throw new RuntimeException('Flickr ' . $method . ' failed: ' . $message);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $album
     * @return array<string, mixed>
     */
    private function normalizeAlbum(array $album, string $nsid): array
    {
        $extras = (array) ($album['primary_photo_extras'] ?? []);
        $id = (string) ($album['id'] ?? '');

        return [
            'id' => $id,
            'title' => (string) ($album['title']['_content'] ?? $album['title'] ?? ''),
            'description' => trim((string) ($album['description']['_content'] ?? $album['description'] ?? '')),
            'count' => (int) ($album['photos'] ?? $album['count_photos'] ?? 0),
            'updated_at' => isset($album['date_update']) ? gmdate('c', (int) $album['date_update']) : null,
            'created_at' => isset($album['date_create']) ? gmdate('c', (int) $album['date_create']) : null,
            'cover' => [
                'thumb' => $extras['url_q'] ?? null,
                'medium' => $extras['url_m'] ?? null,
                'large' => $extras['url_l'] ?? $extras['url_m'] ?? null,
            ],
            'page_url' => 'https://www.flickr.com/photos/' . $nsid . '/albums/' . $id,
            'photos' => [],
        ];
    }

    /**
     * @param array<string, mixed> $photo
     * @return array<string, mixed>
     */
    private function normalizePhoto(array $photo, string $owner): array
    {
        $id = (string) ($photo['id'] ?? '');
        $large = $photo['url_l'] ?? $photo['url_h'] ?? $photo['url_c'] ?? $photo['url_z'] ?? $photo['url_m'] ?? null;
        $largeKey = null;
        foreach (['l', 'h', 'c', 'z', 'm'] as $suffix) {
            if (! empty($photo['url_' . $suffix])) {
                $largeKey = $suffix;
                break;
            }
        }
        $tags = trim((string) ($photo['tags'] ?? ''));

        return [
            'id' => $id,
            'title' => (string) ($photo['title'] ?? ''),
            'description' => trim((string) ($photo['description']['_content'] ?? '')),
            'taken_at' => $photo['datetaken'] ?? null,
            'uploaded_at' => isset($photo['dateupload']) ? gmdate('c', (int) $photo['dateupload']) : null,
            'tags' => $tags === '' ? [] : explode(' ', $tags),
            'thumb' => $photo['url_q'] ?? null,      // 150px square
            'small' => $photo['url_m'] ?? null,      // 500px long side
            'medium' => $photo['url_z'] ?? $photo['url_c'] ?? $photo['url_m'] ?? null, // 640 or 800px
            'large' => $large,                        // up to 1600px
            'original' => $photo['url_o'] ?? null,
            'width' => $largeKey ? (int) ($photo['width_' . $largeKey] ?? 0) : 0,
            'height' => $largeKey ? (int) ($photo['height_' . $largeKey] ?? 0) : 0,
            'page_url' => 'https://www.flickr.com/photos/' . $owner . '/' . $id,
        ];
    }
}
