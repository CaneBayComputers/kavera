<?php

namespace App\Console\Commands;

use App\Services\FlickrService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncFlickr extends Command
{
    /** @var string */
    protected $signature = 'app:flickr-sync
        {--album= : Only sync the album with this Flickr album id or exact title}
        {--max-photos=500 : Maximum photos to pull per album}';

    /** @var string */
    protected $description = 'Pull public Flickr albums and their photos into the cache for gallery pages.';

    public function handle(FlickrService $flickr): int
    {
        if (! $flickr->isEnabled()) {
            $this->error('Flickr not configured. Set FLICKR_API_KEY and FLICKR_USER_ID in .env');
            return self::FAILURE;
        }

        $only = trim((string) $this->option('album'));
        $maxPhotos = max(1, (int) $this->option('max-photos'));

        try {
            $nsid = $flickr->resolveUserId();
            $albums = $flickr->listAlbums($nsid);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($only !== '') {
            $albums = array_values(array_filter($albums, static fn(array $a): bool => $a['id'] === $only || strcasecmp($a['title'], $only) === 0));
            if (empty($albums)) {
                $this->error('No album matches "' . $only . '".');
                return self::FAILURE;
            }
        }

        $existing = (array) (Cache::get('flickr:albums', [])['albums'] ?? []);
        $synced = [];
        $failed = 0;

        foreach ($albums as $album) {
            try {
                $album['photos'] = $flickr->albumPhotos($nsid, $album['id'], $maxPhotos);
                $this->line('Album: ' . $album['title'] . ' (' . count($album['photos']) . ' photo(s))');
            } catch (Throwable $e) {
                $failed++;
                $this->warn('Album failed, keeping previous copy if any: ' . $album['title'] . ' — ' . $e->getMessage());
                if (isset($existing[$album['id']])) {
                    $album = $existing[$album['id']];
                }
            }
            $synced[$album['id']] = $album;
        }

        // A partial sync (--album) keeps every other album as it was.
        if ($only !== '') {
            $synced = $synced + $existing;
        }

        Cache::forever('flickr:albums', [
            'synced_at' => gmdate('c'),
            'user_id' => $nsid,
            'albums' => $synced,
        ]);

        $this->info('Flickr sync complete: ' . count($synced) . ' album(s) cached' . ($failed ? ', ' . $failed . ' failed' : '') . '.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
