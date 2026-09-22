<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Pull a Website Manifestor brief into the places Kavera serves and reads from.
 *
 *   .website-manifest/manifest.json      -> storage/app/private/images/manifest.json
 *   .website-manifest/website-brief.md   -> storage/app/private/website-brief.md
 *   .website-manifest/optimized/<size>/  -> storage/app/public/images/<size>/   (URL /storage/images/<size>/<id>)
 *                                        -> s3://<AWS_BUCKET>/storage/images/<size>/<id> when AWS_BUCKET is set
 *
 * Production serves images from the site's S3 bucket through cdn(), never from
 * the server's disk, so the same key prefix is used on both sides. The bucket
 * must already exist with the standard CDN config; this never creates one.
 *
 * The .website-manifest folder is the client's input and is never modified.
 */
class ImportWebsiteManifest extends Command
{
    /** @var string */
    protected $signature = 'app:website-manifest-import
        {--from= : Folder holding .website-manifest (default: the project root)}
        {--s3 : Upload the images to the AWS_BUCKET S3 bucket (default when AWS_BUCKET is set)}
        {--no-s3 : Skip the S3 upload even if AWS_BUCKET is set}';

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

        $bucket = (string) config('filesystems.disks.s3.bucket');
        $useS3 = ! $this->option('no-s3') && ($this->option('s3') || $bucket !== '');
        if ($useS3 && $bucket === '') {
            $this->error('--s3 given but AWS_BUCKET is empty.');
            return self::FAILURE;
        }

        $copied = 0;
        $uploaded = 0;
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

                if ($useS3 && $this->uploadToS3($from, 'storage/images/' . $folder . '/' . $id)) {
                    $uploaded++;
                }
            }
        }

        $this->info(sprintf(
            'Imported %d image(s) from %d manifest entries; manifest and brief written under storage/app/private.',
            $copied,
            count($decoded['images'])
        ));
        if ($useS3) {
            $this->info(sprintf('Uploaded %d of %d image(s) to s3://%s/storage/images/ (served through cdn()).', $uploaded, $copied, $bucket));
            if ($uploaded < $copied) {
                $this->warn('Some uploads failed; check AWS credentials and that the bucket exists with the standard CDN config.');
            }
        } else {
            $this->line('AWS_BUCKET is empty, so images stay local (dev). Set AWS_BUCKET=<site>.cdn and re-run to publish them to S3.');
        }
        if (! $useS3 && ! is_link(public_path('storage'))) {
            $this->warn('public/storage is not linked; run "php artisan storage:link" so /storage/images/... resolves.');
        }

        return ($useS3 && $uploaded < $copied) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Put one optimized file in the CDN bucket with a long cache life. No ACL:
     * the bucket policy makes objects public and BucketOwnerEnforced rejects ACLs.
     */
    private function uploadToS3(string $file, string $key): bool
    {
        $contentType = str_ends_with($file, '.svg') ? 'image/svg+xml' : 'image/webp';
        $stream = fopen($file, 'rb');
        if ($stream === false) {
            return false;
        }
        try {
            return (bool) Storage::disk('s3')->put($key, $stream, [
                'ContentType' => $contentType,
                'CacheControl' => 'public, max-age=604800',
            ]);
        } catch (\Throwable $e) {
            $this->warn('S3 upload failed for ' . $key . ': ' . $e->getMessage());
            return false;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
