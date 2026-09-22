<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BuildImageManifest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:images-manifest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Image ingest + optimization pipeline: WebP variants and an AI-friendly manifest.';

    public function handle(): int
    {
        return $this->handleBulkIngest();
    }

    /**
     * Bulk ingest pipeline: walk provider folders under storage/app/private/images,
     * generate WebP variants, and build manifest.
     */
    private function handleBulkIngest(): int
    {
        $baseDir = storage_path('app/private/images');
        $providers = ['pexels', 'pixabay', 'unsplash', 'user'];

        $state = $this->loadManifestState();
        $imagesByHash = [];
        foreach ($state['images'] as $index => $entry) {
            if (! empty($entry['hash'])) {
                $imagesByHash[$entry['hash']] = $index;
            }
        }

        $processed = 0;

        foreach ($providers as $provider) {
            $providerDir = $baseDir . DIRECTORY_SEPARATOR . $provider;
            if (! is_dir($providerDir)) {
                continue;
            }

            if ($provider === 'user') {
                $this->processUserFolder($providerDir, $provider, $state, $imagesByHash, $processed);
            } else {
                $this->processProviderFolder($providerDir, $provider, $state, $imagesByHash, $processed);
            }
        }

        $this->saveManifestState($state);

        $this->info('Ingest complete. Processed ' . $processed . ' image(s).');

        return self::SUCCESS;
    }

    private function processProviderFolder(string $providerDir, string $provider, array &$state, array &$imagesByHash, int &$processed): void
    {
        $dirIterator = new \RecursiveDirectoryIterator($providerDir, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dirIterator);

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile()) {
                continue;
            }

            $absolute = $fileInfo->getPathname();
            if (! $this->isSupportedImageExtension($absolute)) {
                continue;
            }

            // Derive original key terms from the first subfolder under the provider directory.
            $relativeDir = ltrim(substr($fileInfo->getPath(), strlen($providerDir)), DIRECTORY_SEPARATOR);
            $parts = $relativeDir === '' ? [] : explode(DIRECTORY_SEPARATOR, $relativeDir);
            $searchFolder = $parts[0] ?? '';
            $originalKeyTerms = $this->parseKeyTermsFromFolder($searchFolder);

            $this->processImageForManifest($provider, $absolute, $originalKeyTerms, $state, $imagesByHash, $processed);
        }
    }

    private function processUserFolder(string $userDir, string $provider, array &$state, array &$imagesByHash, int &$processed): void
    {
        $dirIterator = new \RecursiveDirectoryIterator($userDir, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                continue;
            }

            $absolute = $fileInfo->getPathname();
            $ext = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));

            if ($ext === 'zip') {
                $this->extractAndProcessZip($absolute, $provider, $state, $imagesByHash, $processed);
                // Delete original zip after extraction as requested.
                @unlink($absolute);
                continue;
            }

            if (! $this->isSupportedImageExtension($absolute)) {
                continue;
            }

            $this->processImageForManifest($provider, $absolute, [], $state, $imagesByHash, $processed);
        }
    }

    private function extractAndProcessZip(string $zipPath, string $provider, array &$state, array &$imagesByHash, int &$processed): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $this->warn('Failed to open zip: ' . $zipPath);
            return;
        }

        $tmpDir = storage_path('app/private/images/user/_zip_' . Str::uuid()->toString());
        if (! is_dir($tmpDir) && ! mkdir($tmpDir, 0777, true) && ! is_dir($tmpDir)) {
            $this->warn('Failed to create temp dir for zip extraction: ' . $tmpDir);
            $zip->close();
            return;
        }

        if (! $zip->extractTo($tmpDir)) {
            $this->warn('Failed to extract zip: ' . $zipPath);
            $zip->close();
            return;
        }
        $zip->close();

        $dirIterator = new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dirIterator);
        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile()) {
                continue;
            }
            $absolute = $fileInfo->getPathname();
            if (! $this->isSupportedImageExtension($absolute)) {
                continue;
            }
            $this->processImageForManifest($provider, $absolute, [], $state, $imagesByHash, $processed);
        }
    }

    private function isSupportedImageExtension(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === '') {
            return false;
        }

        $supported = [
            'jpg', 'jpeg', 'jpe', 'png', 'gif', 'bmp', 'tif', 'tiff', 'webp',
            'heic', 'heif', 'pdf', 'eps', 'ai', 'psd', 'avif', 'svg',
        ];

        return in_array($ext, $supported, true);
    }

    private function parseKeyTermsFromFolder(string $folder): array
    {
        if ($folder === '') {
            return [];
        }

        $normalized = str_replace(['_', ','], '-', $folder);
        $parts = array_filter(array_map('trim', explode('-', $normalized)));

        return array_values($parts);
    }

    private function processImageForManifest(string $provider, string $absolutePath, array $originalKeyTerms, array &$state, array &$imagesByHash, int &$processed): void
    {
        if (! is_file($absolutePath)) {
            return;
        }

        $hash = @sha1_file($absolutePath) ?: sha1($absolutePath);
        $existing = $imagesByHash[$hash] ?? null;
        $existingEntry = $existing !== null ? ($state['images'][$existing] ?? null) : null;

        $relPathForLog = $this->relativeOriginalPath($provider, $absolutePath);
        $this->logProgress('Image: [' . $provider . '] ' . $relPathForLog);

        $imageSize = @getimagesize($absolutePath);
        $width = 0;
        $height = 0;

        if ($imageSize !== false) {
            $width = (int) ($imageSize[0] ?? 0);
            $height = (int) ($imageSize[1] ?? 0);
        } elseif (class_exists(\Imagick::class)) {
            try {
                $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
                $multiFrameExts = [
                    'pdf', 'gif', 'webp', 'tiff', 'tif', 'ai', 'heic', 'heif', 'avif',
                    'jp2', 'j2k', 'jpf', 'jpx', 'ico', 'eps', 'psd',
                ];
                $readTarget = $absolutePath;
                if (in_array($ext, $multiFrameExts, true)) {
                    $readTarget .= '[0]';
                }

                $probe = new \Imagick();
                $probe->readImage($readTarget);
                if ($probe->getNumberImages() > 1) {
                    $probe = $probe->getImage();
                }
                $width = (int) $probe->getImageWidth();
                $height = (int) $probe->getImageHeight();
                $probe->clear();
                $probe->destroy();
            } catch (\Throwable $e) {
                $this->warn('Skipping unsupported image (cannot read size via Imagick): ' . $absolutePath);
                return;
            }
        } else {
            $this->warn('Skipping unsupported image (cannot read size): ' . $absolutePath);
            return;
        }

        if ($width <= 0 || $height <= 0) {
            $this->warn('Skipping image with invalid dimensions: ' . $absolutePath);
            return;
        }

        $aspect = $height > 0 ? $width / $height : 0.0;
        $orientation = $this->classifyOrientation($width, $height);

        // Optionally generate variants; use the content hash as the stable file name.
        // SVG is treated specially: copied as-is into images/svg with available_sizes = ['svg'].
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $imageLocationNameId = $ext === 'svg' ? $hash . '.svg' : $hash . '.webp';
        $sizes = [];

        if ($ext === 'svg') {
            // SVG: no raster optimization; just copy once into images/svg using the stable hash-based name.
            $sizes = ['svg'];
            $folder = $this->variantFolderForSize('svg'); // svg
            $destDir = storage_path('app/public/images/' . $folder);
            if (! is_dir($destDir) && ! mkdir($destDir, 0777, true) && ! is_dir($destDir)) {
                $this->warn('Failed to create SVG target directory: ' . $destDir);
            } else {
                $destPath = $destDir . DIRECTORY_SEPARATOR . $imageLocationNameId;
                if (! is_file($destPath)) {
                    if (! @copy($absolutePath, $destPath)) {
                        $this->warn('Failed to copy SVG to public images: ' . $absolutePath);
                    } else {
                        $this->logProgress('  SVG copied to images/svg as ' . $imageLocationNameId);
                    }
                } else {
                    $this->logProgress('  SVG already present in images/svg as ' . $imageLocationNameId);
                }
            }
        } else {
            // Rasterizable formats → WebP variants under images/{size}/.
            $allSizesPresent = false;

            // If the source has a non-default EXIF orientation, we must regenerate WebP assets
            // so they pick up the new orientation handling even when sizes already exist.
            $hasNonDefaultOrientation = $this->hasNonDefaultExifOrientation($absolutePath);

            if (is_array($existingEntry) && ! empty($existingEntry['id']) && ! empty($existingEntry['available_sizes']) && is_array($existingEntry['available_sizes'])) {
                $nameId = (string) $existingEntry['id'];
                $allSizesPresent = true;
                foreach ($existingEntry['available_sizes'] as $size) {
                    $folder = $this->variantFolderForSize($size);
                    $optPath = storage_path('app/public/images/' . $folder . '/' . $nameId);
                    if (! is_file($optPath)) {
                        $allSizesPresent = false;
                        break;
                    }
                }
                // Force regeneration when EXIF orientation indicates the image is rotated.
                if ($hasNonDefaultOrientation) {
                    $allSizesPresent = false;
                }
                if ($allSizesPresent) {
                    $imageLocationNameId = $nameId;
                    $sizes = $existingEntry['available_sizes'];
                }
            }

            if (! $allSizesPresent) {
                [$imageLocationNameId, $sizes] = $this->generateWebpVariants($absolutePath, $width, $height, $hash . '.webp');
                if (! empty($sizes)) {
                    $this->logProgress('  WebP sizes (generated): ' . implode(', ', $sizes));
                } else {
                    $this->logProgress('  WebP sizes: none generated');
                }
            } else {
                $this->logProgress('  WebP sizes (reused): ' . implode(', ', (array) $sizes));
            }
        }

        // Provider-specific metadata fields
        $originalQueryTerms = $provider === 'user' ? [] : array_values($originalKeyTerms);
        // Drop trailing timestamp-like segments (e.g., 20251119 151633)
        $originalQueryTerms = $this->stripTimestampTerms($originalQueryTerms);
        $providerKeywords = [];
        $providerDescription = null;

        if ($provider === 'pexels' || $provider === 'unsplash') {
            $providerKeywords = $originalQueryTerms;
        } elseif ($provider === 'pixabay') {
            $providerDescription = implode(' ', $originalQueryTerms);
        }

        // Relative original path under provider folder
        $relPath = $this->relativeOriginalPath($provider, $absolutePath);

        $entry = [
            'id' => $imageLocationNameId,
            'hash' => $hash,
            'provider' => $provider,
            'original_path' => $relPath,
            'orientation' => $orientation,
            'aspect_ratio' => $aspect,
            'original_query_terms' => $originalQueryTerms,
            'provider_keywords' => $providerKeywords,
            'provider_description' => $providerDescription,
            'available_sizes' => $sizes,
        ];

        if ($existing !== null) {
            $state['images'][$existing] = $entry;
        } else {
            $state['images'][] = $entry;
            $imagesByHash[$hash] = count($state['images']) - 1;
        }

        $processed++;
    }

    private function classifyOrientation(int $width, int $height): string
    {
        if ($width <= 0 || $height <= 0) {
            return 'unknown';
        }
        $aspect = $width / $height;

        if ($aspect >= 2.0 || $aspect <= 0.5) {
            return 'banner';
        }
        if (abs($aspect - 1.0) < 0.05) {
            return 'square';
        }
        if ($aspect > 1.0) {
            return 'landscape';
        }

        return 'portrait';
    }

    private function convertWithImagickLike(string $sourcePath, string $destPath, int $targetWidth, int $targetHeight, bool $scaleBox = false): void
    {
        if (! class_exists(\Imagick::class)) {
            return;
        }

        $sourcePath = (string) $sourcePath;
        $destPath = (string) $destPath;

        if (! is_file($sourcePath)) {
            return;
        }

        try {
            $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
            $multiFrameExts = [
                'pdf', 'gif', 'webp', 'tiff', 'tif', 'ai', 'heic', 'heif', 'avif',
                'jp2', 'j2k', 'jpf', 'jpx', 'ico', 'eps', 'psd',
            ];

            $readTarget = $sourcePath;
            if (in_array($ext, $multiFrameExts, true)) {
                $readTarget .= '[0]';
            }

            $image = new \Imagick();
            $image->setBackgroundColor('white');
            $image->readImage($readTarget);

            if ($image->getNumberImages() > 1) {
                $image = $image->getImage();
            }

            $image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);

            // Best-effort orientation fix based on embedded metadata.
            try {
                $orientation = $image->getImageOrientation();
                switch ($orientation) {
                    case \Imagick::ORIENTATION_BOTTOMRIGHT:
                        $image->rotateImage('white', 180);
                        break;
                    case \Imagick::ORIENTATION_RIGHTTOP:
                        $image->rotateImage('white', 90);
                        break;
                    case \Imagick::ORIENTATION_LEFTBOTTOM:
                        $image->rotateImage('white', 270);
                        break;
                    case \Imagick::ORIENTATION_TOPRIGHT:
                        $image->flopImage();
                        break;
                    case \Imagick::ORIENTATION_BOTTOMLEFT:
                        $image->flipImage();
                        break;
                    case \Imagick::ORIENTATION_RIGHTBOTTOM:
                        $image->flopImage();
                        $image->rotateImage('white', 90);
                        break;
                    case \Imagick::ORIENTATION_LEFTTOP:
                        $image->flopImage();
                        $image->rotateImage('white', 270);
                        break;
                    default:
                        break;
                }
                $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
            } catch (\Throwable $e) {
                // Orientation is best-effort only.
            }

            $width = $image->getImageWidth();
            $height = $image->getImageHeight();

            if ($width > 0 && $height > 0 && $targetWidth > 0 && $targetHeight > 0) {
                // $scaleBox currently behaves like a bounded resize; parameter retained for future behavior tweaks.
                if ($scaleBox) {
                    $scale = min($targetWidth / $width, $targetHeight / $height, 1.0);
                } else {
                    $scale = min($targetWidth / $width, $targetHeight / $height, 1.0);
                }
                $newWidth = (int) round($width * $scale);
                $newHeight = (int) round($height * $scale);

                if ($newWidth > 0 && $newHeight > 0) {
                    $image->resizeImage($newWidth, $newHeight, \Imagick::FILTER_LANCZOS, 1.0, true);
                }
            }

            $destExt = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
            if ($destExt === 'webp') {
                $image->setImageFormat('webp');
                $image->setOption('webp:method', '6');
            } elseif ($destExt === 'jpg' || $destExt === 'jpeg' || $destExt === 'jpe') {
                $image->setImageFormat('jpeg');
            }

            if ($destExt === 'jpg' || $destExt === 'jpeg' || $destExt === 'jpe' || $destExt === 'webp') {
                $image->setImageCompressionQuality(90);
            }

            $dir = dirname($destPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $image->writeImage($destPath);
            $image->clear();
            $image->destroy();
        } catch (\Throwable $e) {
            $this->warn(
                'Imagick conversion failed for source [' . $sourcePath . '] to dest [' . $destPath . ']: ' . $e->getMessage()
            );
        }
    }

    private function generateWebpVariants(string $absolutePath, int $width, int $height, string $nameId): array
    {
        $contents = @file_get_contents($absolutePath);
        $src = $contents !== false ? @imagecreatefromstring($contents) : false;

        $longSide = max($width, $height);
        $sizesToMake = [1920, 1280, 768, 480];
        $generatedSizes = [];

        // Best-effort EXIF orientation fix for JPEG-family inputs.
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if ($src && function_exists('exif_read_data') && in_array($ext, ['jpg', 'jpeg', 'jpe'], true)) {
            try {
                $exif = @exif_read_data($absolutePath);
                $orientation = isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;
                if (in_array($orientation, [3, 6, 8], true)) {
                    $angle = 0;
                    if ($orientation === 3) {
                        $angle = 180;
                    } elseif ($orientation === 6) {
                        $angle = -90;
                    } elseif ($orientation === 8) {
                        $angle = 90;
                    }
                    if ($angle !== 0) {
                        $rotated = @imagerotate($src, $angle, 0);
                        if ($rotated !== false) {
                            imagedestroy($src);
                            $src = $rotated;
                            // Swap width/height when we rotated by 90/270 degrees.
                            if (in_array($orientation, [6, 8], true)) {
                                $tmp = $width;
                                $width = $height;
                                $height = $tmp;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Orientation is best-effort only.
            }
        }

        // Very small originals: keep a single WebP at original size in a dedicated "small" folder.
        if ($longSide < 480) {
            if ($src) {
                $targetWidth = $width;
                $targetHeight = $height;

                $dst = imagecreatetruecolor($targetWidth, $targetHeight);
                if ($dst) {
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

                    $optDir = storage_path('app/public/images/small');
                    if (is_dir($optDir) || mkdir($optDir, 0777, true) || is_dir($optDir)) {
                        $destPath = $optDir . DIRECTORY_SEPARATOR . $nameId;
                        if (imagewebp($dst, $destPath, 80)) {
                            $generatedSizes[] = $longSide; // actual max dimension
                        }
                    }
                    imagedestroy($dst);
                }

                imagedestroy($src);
            } else {
                // Fallback: use ImageMagick convert to make a WebP at original size.
                $optDir = storage_path('app/public/images/small');
                if (is_dir($optDir) || mkdir($optDir, 0777, true) || is_dir($optDir)) {
                    $destPath = $optDir . DIRECTORY_SEPARATOR . $nameId;
                    $this->convertWithImagickLike($absolutePath, $destPath, $width, $height);
                    if (is_file($destPath)) {
                        $generatedSizes[] = $longSide;
                    }
                }
            }

            return [$nameId, $generatedSizes];
        }

        if ($src) {
            foreach ($sizesToMake as $target) {
                if ($longSide < $target) {
                    continue; // never upscale beyond original
                }

                if ($width >= $height) {
                    $targetWidth = $target;
                    $targetHeight = (int) round($height * ($targetWidth / $width));
                } else {
                    $targetHeight = $target;
                    $targetWidth = (int) round($width * ($targetHeight / $height));
                }

                $dst = imagecreatetruecolor($targetWidth, $targetHeight);
                if (! $dst) {
                    continue;
                }

                imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

                $optDir = storage_path('app/public/images/' . $target);
                if (! is_dir($optDir) && ! mkdir($optDir, 0777, true) && ! is_dir($optDir)) {
                    imagedestroy($dst);
                    continue;
                }

                $destPath = $optDir . DIRECTORY_SEPARATOR . $nameId;
                if (imagewebp($dst, $destPath, 80)) {
                    $generatedSizes[] = $target;
                }

                imagedestroy($dst);
            }

            imagedestroy($src);
        } else {
            // Fallback: use ImageMagick-like convert directly for each size.
            foreach ($sizesToMake as $target) {
                if ($longSide < $target) {
                    continue;
                }

                if ($width >= $height) {
                    $targetWidth = $target;
                    $targetHeight = (int) round($height * ($targetWidth / $width));
                } else {
                    $targetHeight = $target;
                    $targetWidth = (int) round($width * ($targetHeight / $height));
                }

                $optDir = storage_path('app/public/images/' . $target);
                if (! is_dir($optDir) && ! mkdir($optDir, 0777, true) && ! is_dir($optDir)) {
                    continue;
                }

                $destPath = $optDir . DIRECTORY_SEPARATOR . $nameId;
                $this->convertWithImagickLike($absolutePath, $destPath, $targetWidth, $targetHeight);
                if (is_file($destPath)) {
                    $generatedSizes[] = $target;
                }
            }
        }

        return [$nameId, $generatedSizes];
    }

    private function deleteDirectoryRecursive(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectoryRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * Map an available_size entry to its folder name under storage/app/public/images.
     *
     * @param mixed $size
     */
    private function variantFolderForSize($size): string
    {
        if ($size === 'svg') {
            return 'svg';
        }

        if (is_numeric($size)) {
            $sizeNum = (int) $size;
            if ($sizeNum < 480) {
                return 'small';
            }

            return (string) $sizeNum;
        }

        return (string) $size;
    }

    private function hasNonDefaultExifOrientation(string $absolutePath): bool
    {
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (! function_exists('exif_read_data') || ! in_array($ext, ['jpg', 'jpeg', 'jpe'], true)) {
            return false;
        }

        try {
            $exif = @exif_read_data($absolutePath);
        } catch (\Throwable $e) {
            return false;
        }

        if (! is_array($exif)) {
            return false;
        }

        $orientation = isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;

        // 1 is "normal"; 2-8 are rotated or mirrored variants.
        return $orientation >= 2 && $orientation <= 8;
    }

    private function stripTimestampTerms(array $terms): array
    {
        $count = count($terms);
        if ($count < 2) {
            return $terms;
        }

        $last = $terms[$count - 1];
        $secondLast = $terms[$count - 2];

        $isNumericLike = static function ($value): bool {
            $value = (string) $value;
            return ctype_digit($value) && strlen($value) >= 4 && strlen($value) <= 8;
        };

        if ($isNumericLike($last) && $isNumericLike($secondLast)) {
            return array_slice($terms, 0, $count - 2);
        }

        return $terms;
    }

    private function relativeOriginalPath(string $provider, string $absolutePath): string
    {
        $baseDir = storage_path('app/private/images/' . $provider . '/');
        if (str_starts_with($absolutePath, $baseDir)) {
            return ltrim(substr($absolutePath, strlen($baseDir)), DIRECTORY_SEPARATOR);
        }

        return basename($absolutePath);
    }

    private function loadManifestState(): array
    {
        $disk = Storage::disk('local');
        $path = 'images/manifest.json';

        $fresh = [
            'notes' => [
                'Adhere to any user image directory structure and file naming to infer intended page usage.',
                'To construct the image paths: images/{available_size}/{id}.',
                "Use Storage::url('images/...') for all image, asset, and file URLs; do not output /storage/... paths directly.",
                'The available_sizes array for each image must be strictly adhered to; only choose sizes that are explicitly listed there.',
                'Never invent or assume a size for an image URL (for example, do not use 1920 if 1920 is not present in available_sizes).',
                "When building an image URL, always derive the {available_size} segment from the image's available_sizes array and the {id} field; do not hard-code size values.",
            ],
            'images' => [],
        ];

        if (! $disk->exists($path)) {
            return $fresh;
        }

        $raw = $disk->get($path);
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $fresh;
        }

        if (! array_key_exists('notes', $decoded) || ! array_key_exists('images', $decoded) || ! is_array($decoded['images'])) {
            return $fresh;
        }

        // Older image entries (absolute original_path, final_keywords or image_location.sizes) → reset.
        $images = $decoded['images'] ?? [];
        $first = $images[0] ?? null;
        if (is_array($first)) {
            if (isset($first['final_keywords'])) {
                return $fresh;
            }
            if (isset($first['image_location']['sizes'])) {
                return $fresh;
            }
        }

        return $decoded;
    }

    private function saveManifestState(array $state): void
    {
        $disk = Storage::disk('local');
        if (! $disk->exists('images')) {
            $disk->makeDirectory('images');
        }

        $disk->put('images/manifest.json', json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function logProgress(string $message): void
    {
        // Some CLI wrappers set JSON_OUTPUT; avoid noisy logs in that mode.
        $jsonOutput = env('JSON_OUTPUT', false);
        if ($jsonOutput) {
            return;
        }

        $this->line($message);
    }
}
