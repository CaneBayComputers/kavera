<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Pull a Website Manifestor brief into the places Kavera serves and reads from.
 *
 *   .website-manifest/manifest.json      -> storage/app/private/images/manifest.json
 *   .website-manifest/website-brief.md   -> storage/app/private/website-brief.md
 *   .website-manifest/optimized/<size>/  -> storage/app/public/images/<size>/   (URL /storage/images/<size>/<id>)
 *
 * The .website-manifest folder is the client's input and is never modified.
 */
class ImportWebsiteManifest extends Command
{
    /** @var string */
    protected $signature = 'app:website-manifest-import
        {--from= : Folder holding .website-manifest (default: the project root)}';

    /** @var string */
    protected $description = 'Copy a Website Manifestor brief (.website-manifest/) into storage/ so templates and agents can use it.';

    public function handle(): int
    {
        $root = rtrim((string) ($this->option('from') ?: base_path()), '/');
        $source = $root . '/.website-manifest';

        if (! is_dir($source) || ! is_file($source . '/manifest.json')) {
            $this->error('No .website-manifest/manifest.json under ' . $root . '. Run Website Manifestor on this folder first.');
            return self::FAILURE;
        }

        $decoded = json_decode((string) file_get_contents($source . '/manifest.json'), true);
        if (! is_array($decoded) || ! isset($decoded['images']) || ! is_array($decoded['images'])) {
            $this->error('manifest.json is not a Website Manifestor manifest.');
            return self::FAILURE;
        }

        $privateDir = storage_path('app/private');
        $publicImages = storage_path('app/public/images');
        File::ensureDirectoryExists($privateDir . '/images');
        File::ensureDirectoryExists($publicImages);

        // Manifest, with the Kavera-specific URL rule appended for agents.
        $decoded['notes'] = array_values(array_unique(array_merge((array) ($decoded['notes'] ?? []), [
            'On this Kavera site the public URL of an optimized image is /storage/images/{available_size}/{id} ("small" for sizes under 480, "svg" for SVGs).',
        ])));
        file_put_contents($privateDir . '/images/manifest.json', json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        foreach (['website-brief.md', 'AGENTS.md'] as $doc) {
            if (is_file($source . '/' . $doc)) {
                copy($source . '/' . $doc, $privateDir . '/' . ($doc === 'AGENTS.md' ? 'website-manifest-AGENTS.md' : $doc));
            }
        }

        $copied = 0;
        foreach ($decoded['images'] as $image) {
            $id = (string) ($image['id'] ?? '');
            foreach ((array) ($image['available_sizes'] ?? []) as $size) {
                $folder = $size === 'svg' ? 'svg' : ((is_numeric($size) && (int) $size < 480) ? 'small' : (string) $size);
                $from = $source . '/optimized/' . $folder . '/' . $id;
                if ($id === '' || ! is_file($from)) {
                    continue;
                }
                File::ensureDirectoryExists($publicImages . '/' . $folder);
                copy($from, $publicImages . '/' . $folder . '/' . $id);
                $copied++;
            }
        }

        $this->info(sprintf(
            'Imported %d image(s) from %d manifest entries; manifest and brief written under storage/app/private.',
            $copied,
            count($decoded['images'])
        ));
        if (! is_link(public_path('storage'))) {
            $this->warn('public/storage is not linked; run "php artisan storage:link" so /storage/images/... resolves.');
        }

        return self::SUCCESS;
    }
}
