<?php

namespace App\Console\Commands;

use App\Services\RekognitionService;
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
    protected $signature = 'app:images-manifest
        {image? : Optional image path (relative to project base) to analyze}
        {--rekognition : Enable AWS Rekognition analysis}
        {--rekog : Alias for --rekognition}
        {--rekog-faces : Include Rekognition face analysis}
        {--rekog-text : Include Rekognition text detection}
        {--rekog-max-labels=25 : Max Rekognition labels}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Image ingest + optimization pipeline with AWS Rekognition analysis.';

    public function handle(RekognitionService $rekognition): int
    {
        $imagePath = (string) ($this->argument('image') ?? '');

        $useRekognition = (bool) $this->option('rekognition') || (bool) $this->option('rekog');
        $includeFaces = (bool) $this->option('rekog-faces');
        $includeText = (bool) $this->option('rekog-text');
        $rekogMaxLabels = $this->option('rekog-max-labels');
        $rekogMaxLabels = $rekogMaxLabels !== null ? (int) $rekogMaxLabels : null;

        // Temporary single-image Rekognition test path (debug helper)
        if ($imagePath !== '') {
            return $this->handleSingleImage($imagePath, $rekognition, $useRekognition, $includeFaces, $includeText, $rekogMaxLabels);
        }

        return $this->handleBulkIngest($rekognition, $useRekognition, $includeFaces, $includeText, $rekogMaxLabels);
    }

    /**
     * Temporary single-image Rekognition test: copy to /tmp, resize to max 1920px, send to Rekognition,
     * and write a minimal manifest.json under storage/app/private/images/.
     */
    private function handleSingleImage(string $imagePath, RekognitionService $rekognition, bool $useRekognition, bool $includeFaces, bool $includeText, ?int $rekogMaxLabels): int
    {
        $absolute = $this->resolveImagePath($imagePath);
        if (! is_file($absolute)) {
            $this->error('Image not found: ' . $absolute);
            return self::FAILURE;
        }

        $info = @getimagesize($absolute);
        if ($info === false || ($info[2] ?? null) !== IMAGETYPE_JPEG) {
            $this->error('Image must be a JPEG: ' . $absolute);
            return self::FAILURE;
        }

        $tmpDir = sys_get_temp_dir();
        $baseName = basename($absolute);
        $tmpPath = rtrim($tmpDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $baseName;

        if (! copy($absolute, $tmpPath)) {
            $this->error('Failed to copy image to temp path: ' . $tmpPath);
            return self::FAILURE;
        }

        [$width, $height] = [$info[0] ?? 0, $info[1] ?? 0];
        $maxDim = max($width, $height);

        $resizedWidth = $width;
        $resizedHeight = $height;

        if ($maxDim > 1920 && $width > 0 && $height > 0) {
            $scale = 1920 / $maxDim;
            $resizedWidth = (int) round($width * $scale);
            $resizedHeight = (int) round($height * $scale);

            $srcImg = imagecreatefromjpeg($tmpPath);
            if (! $srcImg) {
                $this->error('Failed to create image resource from temp JPEG.');
                return self::FAILURE;
            }

            $dstImg = imagecreatetruecolor($resizedWidth, $resizedHeight);
            if (! $dstImg) {
                imagedestroy($srcImg);
                $this->error('Failed to create destination image resource.');
                return self::FAILURE;
            }

            imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $resizedWidth, $resizedHeight, $width, $height);

            if (! imagejpeg($dstImg, $tmpPath, 90)) {
                imagedestroy($srcImg);
                imagedestroy($dstImg);
                $this->error('Failed to write resized JPEG to temp path.');
                return self::FAILURE;
            }

            imagedestroy($srcImg);
            imagedestroy($dstImg);
        }

        $labels = [];
        $imageProperties = [];
        $faces = [];
        $textDetections = [];

        if ($useRekognition && $rekognition->isAvailable()) {
            $labels = $rekognition->detectLabels($tmpPath, $rekogMaxLabels);
            $imageProperties = $rekognition->detectImageProperties($tmpPath);
            if ($includeFaces) {
                $faces = $rekognition->detectFaces($tmpPath);
            }
            if ($includeText) {
                $textDetections = $rekognition->detectText($tmpPath);
            }
        } elseif ($useRekognition) {
            $this->warn('AWS Rekognition SDK/credentials not available; analysis fields will be empty.');
        }

        // Build condensed manifest payload structure
        $objects = [];
        if (! empty($labels)) {
            usort($labels, static function ($a, $b) {
                return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
            });
            foreach ($labels as $label) {
                $name = (string) ($label['name'] ?? '');
                if ($name !== '') {
                    $objects[] = $name;
                }
            }
        }

        $brightness = null;
        $sharpness = null;
        $contrast = null;
        $foregroundColorsOut = [];
        $backgroundColorsOut = [];

        if (! empty($imageProperties['full']['quality'])) {
            $quality = $imageProperties['full']['quality'];
            $brightness = $quality['brightness'] ?? null;
            $sharpness = $quality['sharpness'] ?? null;
            $contrast = $quality['contrast'] ?? null;
        }
        // Separate foreground/background dominant colors; fall back to full if needed.
        if (! empty($imageProperties['foreground']['dominant_colors']) && is_array($imageProperties['foreground']['dominant_colors'])) {
            foreach ($imageProperties['foreground']['dominant_colors'] as $color) {
                $hex = (string) ($color['hex'] ?? '');
                if ($hex === '') {
                    continue;
                }
                $desc = $this->describeColor($hex);
                $normalizedHex = ltrim($hex, '#');
                if (strlen($normalizedHex) === 3) {
                    $normalizedHex = $normalizedHex[0] . $normalizedHex[0]
                        . $normalizedHex[1] . $normalizedHex[1]
                        . $normalizedHex[2] . $normalizedHex[2];
                }
                $foregroundColorsOut[] = trim($desc . ' (#' . strtolower($normalizedHex) . ')');
            }
        }
        if (! empty($imageProperties['background']['dominant_colors']) && is_array($imageProperties['background']['dominant_colors'])) {
            foreach ($imageProperties['background']['dominant_colors'] as $color) {
                $hex = (string) ($color['hex'] ?? '');
                if ($hex === '') {
                    continue;
                }
                $desc = $this->describeColor($hex);
                $normalizedHex = ltrim($hex, '#');
                if (strlen($normalizedHex) === 3) {
                    $normalizedHex = $normalizedHex[0] . $normalizedHex[0]
                        . $normalizedHex[1] . $normalizedHex[1]
                        . $normalizedHex[2] . $normalizedHex[2];
                }
                $backgroundColorsOut[] = trim($desc . ' (#' . strtolower($normalizedHex) . ')');
            }
        }
        if (empty($foregroundColorsOut) && empty($backgroundColorsOut) && ! empty($imageProperties['full']['dominant_colors']) && is_array($imageProperties['full']['dominant_colors'])) {
            // If no foreground/background info, treat full-image colors as foreground.
            foreach ($imageProperties['full']['dominant_colors'] as $color) {
                $hex = (string) ($color['hex'] ?? '');
                if ($hex === '') {
                    continue;
                }
                $desc = $this->describeColor($hex);
                $normalizedHex = ltrim($hex, '#');
                if (strlen($normalizedHex) === 3) {
                    $normalizedHex = $normalizedHex[0] . $normalizedHex[0]
                        . $normalizedHex[1] . $normalizedHex[1]
                        . $normalizedHex[2] . $normalizedHex[2];
                }
                $foregroundColorsOut[] = trim($desc . ' (#' . strtolower($normalizedHex) . ')');
            }
        }

        $facesCount = null;
        if ($includeFaces) {
            $facesCount = is_array($faces) ? count($faces) : 0;
        }

        $textSegments = null;
        if ($includeText && ! empty($textDetections)) {
            usort($textDetections, static function ($a, $b) {
                return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
            });
            $seenText = [];
            $segments = [];
            foreach ($textDetections as $det) {
                $confidence = (float) ($det['confidence'] ?? 0);
                if ($confidence < 95.0) {
                    continue; // require high confidence for text
                }

                $text = trim((string) ($det['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $key = mb_strtolower($text);
                if (isset($seenText[$key])) {
                    continue;
                }
                $seenText[$key] = true;
                $segments[] = $text;
            }
            $textSegments = $segments;
        }

        $disk = Storage::disk('local'); // storage/app/private
        if (! $disk->exists('images')) {
            $disk->makeDirectory('images');
        }

        $manifestPath = 'images/manifest.json';
        $payload = [
            'auto_identified_properties_from_aws_rekognition' => [
                'objects' => $objects,
                'brightness' => $this->toPercent($brightness),
                'sharpness' => $this->toPercent($sharpness),
                'contrast' => $this->toPercent($contrast),
                'foreground_colors' => $foregroundColorsOut,
                'background_colors' => $backgroundColorsOut,
                'number_of_human_faces' => $facesCount,
                'text_segments' => $textSegments,
            ],
        ];

        $disk->put($manifestPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info('Wrote temporary manifest to storage/app/private/' . $manifestPath);

        return self::SUCCESS;
    }

    private function resolveImagePath(string $imagePath): string
    {
        if (str_starts_with($imagePath, DIRECTORY_SEPARATOR)) {
            return $imagePath;
        }

        return base_path($imagePath);
    }

    /**
     * Bulk ingest pipeline: walk provider folders under storage/app/private/images,
     * analyze with Rekognition, update XMP, generate WebP variants, and build manifest.
     */
    private function handleBulkIngest(RekognitionService $rekognition, bool $useRekognition, bool $includeFaces, bool $includeText, ?int $rekogMaxLabels): int
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
                $this->processUserFolder($providerDir, $provider, $rekognition, $useRekognition, $includeFaces, $includeText, $rekogMaxLabels, $state, $imagesByHash, $processed);
            } else {
                $this->processProviderFolder($providerDir, $provider, $rekognition, $useRekognition, $includeFaces, $includeText, $rekogMaxLabels, $state, $imagesByHash, $processed);
            }
        }

        $this->saveManifestState($state);

        $this->info('Ingest complete. Processed ' . $processed . ' image(s).');

        return self::SUCCESS;
    }

    private function processProviderFolder(string $providerDir, string $provider, RekognitionService $rekognition, bool $useRekognition, bool $includeFaces, bool $includeText, ?int $rekogMaxLabels, array &$state, array &$imagesByHash, int &$processed): void
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

            $this->processImageForManifest($provider, $absolute, $originalKeyTerms, $rekognition, $useRekognition, $includeFaces, $includeText, $rekogMaxLabels, $state, $imagesByHash, $processed);
        }
    }

    private function processUserFolder(string $userDir, string $provider, RekognitionService $rekognition, bool $useRekognition, bool $includeFaces, bool $includeText, ?int $rekogMaxLabels, array &$state, array &$imagesByHash, int &$processed): void
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
                $this->extractAndProcessZip($absolute, $provider, $rekognition, $useRekognition, $includeFaces, $includeText, $rekogMaxLabels, $state, $imagesByHash, $processed);
                // Delete original zip after extraction as requested.
                @unlink($absolute);
                continue;
            }

            if (! $this->isSupportedImageExtension($absolute)) {
                continue;
            }

            $this->processImageForManifest($provider, $absolute, [], $rekognition, $useRekognition, $includeFaces, $includeText, $rekogMaxLabels, $state, $imagesByHash, $processed);
        }
    }

    private function extractAndProcessZip(string $zipPath, string $provider, RekognitionService $rekognition, bool $useRekognition, bool $includeFaces, bool $includeText, ?int $rekogMaxLabels, array &$state, array &$imagesByHash, int &$processed): void
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
            $this->processImageForManifest($provider, $absolute, [], $rekognition, $useRekognition, $includeFaces, $includeText, $rekogMaxLabels, $state, $imagesByHash, $processed);
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

    private function processImageForManifest(string $provider, string $absolutePath, array $originalKeyTerms, RekognitionService $rekognition, bool $useRekognition, bool $includeFaces, bool $includeText, ?int $rekogMaxLabels, array &$state, array &$imagesByHash, int &$processed): void
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

        $tmpPath = $this->createTempJpegForRekognition($absolutePath, 1280);
        if ($tmpPath === null) {
            $this->warn('Failed to create temp JPEG for Rekognition: ' . $absolutePath);
            return;
        }

        if ($useRekognition) {
            $mode = 'labels';
            if ($includeFaces) {
                $mode .= '+faces';
            }
            if ($includeText) {
                $mode .= '+text';
            }
            $this->logProgress('  Rekog: ' . $mode);
        } else {
            $this->logProgress('  Rekog: disabled');
        }

        $autoProps = $this->analyzeWithRekognition($tmpPath, $rekognition, $useRekognition, $includeFaces, $includeText, $rekogMaxLabels);

        // Unified keywords for manifest (provider terms + Rekog objects).
        $keywords = [];
        foreach ($originalKeyTerms as $term) {
            $t = trim((string) $term);
            if ($t !== '') {
                $keywords[mb_strtolower($t)] = $t;
            }
        }
        if (! empty($autoProps['objects']) && is_array($autoProps['objects'])) {
            foreach ($autoProps['objects'] as $obj) {
                $t = trim((string) $obj);
                if ($t === '') {
                    continue;
                }
                $keywords[mb_strtolower($t)] = $t;
            }
        }
        // TODO: read existing EXIF/IPTC/XMP keywords and merge; for now we rely on provider terms + Rekog.

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
            'auto_identified_properties_from_aws_rekognition' => $autoProps,
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

    private function createTempJpegForRekognition(string $absolutePath, int $maxLongSide): ?string
    {
        $contents = @file_get_contents($absolutePath);
        $src = $contents !== false ? @imagecreatefromstring($contents) : false;

        if (! $src) {
            // Fallback: use ImageMagick convert directly to make a JPEG resized to maxLongSide.
            $tmpPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . Str::uuid()->toString() . '.jpg';
            $this->convertWithImagickLike($absolutePath, $tmpPath, $maxLongSide, $maxLongSide, true);
            return is_file($tmpPath) ? $tmpPath : null;
        }

        // Best-effort EXIF orientation for JPEGs; ignore errors and non-JPEG formats.
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (function_exists('exif_read_data') && in_array($ext, ['jpg', 'jpeg', 'jpe'], true)) {
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
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Orientation is best-effort only.
            }
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $maxDim = max($width, $height);

        $targetWidth = $width;
        $targetHeight = $height;
        if ($maxDim > $maxLongSide && $width > 0 && $height > 0) {
            $scale = $maxLongSide / $maxDim;
            $targetWidth = (int) round($width * $scale);
            $targetHeight = (int) round($height * $scale);
        }

        $dst = imagecreatetruecolor($targetWidth, $targetHeight);
        if (! $dst) {
            imagedestroy($src);
            return null;
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $tmpPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . Str::uuid()->toString() . '.jpg';
        if (! imagejpeg($dst, $tmpPath, 90)) {
            imagedestroy($src);
            imagedestroy($dst);
            return null;
        }

        imagedestroy($src);
        imagedestroy($dst);

        return $tmpPath;
    }

    private function analyzeWithRekognition(string $tmpPath, RekognitionService $rekognition, bool $useRekognition, bool $includeFaces, bool $includeText, ?int $rekogMaxLabels): array
    {
        $labels = [];
        $imageProperties = [];
        $faces = [];
        $textDetections = [];

        if ($useRekognition && $rekognition->isAvailable()) {
            $labels = $rekognition->detectLabels($tmpPath, $rekogMaxLabels);
            $imageProperties = $rekognition->detectImageProperties($tmpPath);
            if ($includeFaces) {
                $faces = $rekognition->detectFaces($tmpPath);
            }
            if ($includeText) {
                $textDetections = $rekognition->detectText($tmpPath);
            }
        }

        $objects = [];
        if (! empty($labels)) {
            usort($labels, static function ($a, $b) {
                return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
            });
            foreach ($labels as $label) {
                $name = (string) ($label['name'] ?? '');
                if ($name !== '') {
                    $objects[] = $name;
                }
            }
        }

        $brightness = null;
        $sharpness = null;
        $contrast = null;
        $foregroundColorsOut = [];
        $backgroundColorsOut = [];

        if (! empty($imageProperties['full']['quality'])) {
            $quality = $imageProperties['full']['quality'];
            $brightness = $quality['brightness'] ?? null;
            $sharpness = $quality['sharpness'] ?? null;
            $contrast = $quality['contrast'] ?? null;
        }
        // Separate foreground/background dominant colors; fall back to full if needed.
        if (! empty($imageProperties['foreground']['dominant_colors']) && is_array($imageProperties['foreground']['dominant_colors'])) {
            foreach ($imageProperties['foreground']['dominant_colors'] as $color) {
                $hex = (string) ($color['hex'] ?? '');
                if ($hex === '') {
                    continue;
                }
                $desc = $this->describeColor($hex);
                $normalizedHex = ltrim($hex, '#');
                if (strlen($normalizedHex) === 3) {
                    $normalizedHex = $normalizedHex[0] . $normalizedHex[0]
                        . $normalizedHex[1] . $normalizedHex[1]
                        . $normalizedHex[2] . $normalizedHex[2];
                }
                $foregroundColorsOut[] = trim($desc . ' (#' . strtolower($normalizedHex) . ')');
            }
        }
        if (! empty($imageProperties['background']['dominant_colors']) && is_array($imageProperties['background']['dominant_colors'])) {
            foreach ($imageProperties['background']['dominant_colors'] as $color) {
                $hex = (string) ($color['hex'] ?? '');
                if ($hex === '') {
                    continue;
                }
                $desc = $this->describeColor($hex);
                $normalizedHex = ltrim($hex, '#');
                if (strlen($normalizedHex) === 3) {
                    $normalizedHex = $normalizedHex[0] . $normalizedHex[0]
                        . $normalizedHex[1] . $normalizedHex[1]
                        . $normalizedHex[2] . $normalizedHex[2];
                }
                $backgroundColorsOut[] = trim($desc . ' (#' . strtolower($normalizedHex) . ')');
            }
        }
        if (empty($foregroundColorsOut) && empty($backgroundColorsOut) && ! empty($imageProperties['full']['dominant_colors']) && is_array($imageProperties['full']['dominant_colors'])) {
            // If no foreground/background info, treat full-image colors as foreground.
            foreach ($imageProperties['full']['dominant_colors'] as $color) {
                $hex = (string) ($color['hex'] ?? '');
                if ($hex === '') {
                    continue;
                }
                $desc = $this->describeColor($hex);
                $normalizedHex = ltrim($hex, '#');
                if (strlen($normalizedHex) === 3) {
                    $normalizedHex = $normalizedHex[0] . $normalizedHex[0]
                        . $normalizedHex[1] . $normalizedHex[1]
                        . $normalizedHex[2] . $normalizedHex[2];
                }
                $foregroundColorsOut[] = trim($desc . ' (#' . strtolower($normalizedHex) . ')');
            }
        }

        $facesCount = null;
        if ($includeFaces) {
            $facesCount = is_array($faces) ? count($faces) : 0;
        }

        $textSegments = null;
        if ($includeText && ! empty($textDetections)) {
            usort($textDetections, static function ($a, $b) {
                return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
            });
            $seenText = [];
            $segments = [];
            foreach ($textDetections as $det) {
                $confidence = (float) ($det['confidence'] ?? 0);
                if ($confidence < 95.0) {
                    continue; // require high confidence for text
                }

                $text = trim((string) ($det['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $key = mb_strtolower($text);
                if (isset($seenText[$key])) {
                    continue;
                }
                $seenText[$key] = true;
                $segments[] = $text;
            }
            $textSegments = $segments;
        }

        return [
            'objects' => $objects,
            'brightness' => $this->toPercent($brightness),
            'sharpness' => $this->toPercent($sharpness),
            'contrast' => $this->toPercent($contrast),
            'foreground_colors' => $foregroundColorsOut,
            'background_colors' => $backgroundColorsOut,
            'number_of_human_faces' => $facesCount,
            'text_segments' => $textSegments,
        ];
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

    private function toPercent($value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $v = (int) round((float) $value);
        if ($v < 0) {
            $v = 0;
        }
        if ($v > 100) {
            $v = 100;
        }

        return $v . '%';
    }

    private function describeColor(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return $hex;
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r_f = $r / 255;
        $g_f = $g / 255;
        $b_f = $b / 255;

        $max = max($r_f, $g_f, $b_f);
        $min = min($r_f, $g_f, $b_f);

        $l = ($max + $min) / 2;

        if ($max === $min) {
            $h = 0;
            $s = 0;
        } else {
            $d = $max - $min;

            $s = $l > 0.5
                ? $d / (2 - $max - $min)
                : $d / ($max + $min);

            if ($max === $r_f) {
                $h = ($g_f - $b_f) / $d + ($g_f < $b_f ? 6 : 0);
            } elseif ($max === $g_f) {
                $h = ($b_f - $r_f) / $d + 2;
            } else {
                $h = ($r_f - $g_f) / $d + 4;
            }

            $h /= 6;
        }

        $hue_deg = $h * 360;

        // Grayscale handling: very low saturation → shades of gray/white/black.
        if ($s < 0.05) {
            if ($l <= 0.08) {
                return 'black';
            }
            if ($l <= 0.18) {
                return 'near black';
            }
            if ($l <= 0.32) {
                return 'dark gray';
            }
            if ($l <= 0.65) {
                return 'gray';
            }
            if ($l <= 0.85) {
                return 'light gray';
            }
            if ($l <= 0.95) {
                return 'near white';
            }
            return 'white';
        }

        // Hue name including magenta and brown.
        if ($hue_deg < 15 || $hue_deg >= 345) {
            $hue_name = 'red';
        } elseif ($hue_deg < 45) {
            $hue_name = 'orange';
        } elseif ($hue_deg < 75) {
            $hue_name = 'yellow';
        } elseif ($hue_deg < 150) {
            $hue_name = 'green';
        } elseif ($hue_deg < 210) {
            $hue_name = 'cyan';
        } elseif ($hue_deg < 270) {
            $hue_name = 'blue';
        } elseif ($hue_deg < 300) {
            $hue_name = 'purple';
        } elseif ($hue_deg < 345) {
            $hue_name = 'magenta';
        } else {
            $hue_name = 'red';
        }

        // Override to brown when in the warm range with medium lightness.
        if ($hue_deg >= 15 && $hue_deg <= 45 && $l >= 0.2 && $l <= 0.6) {
            $hue_name = 'brown';
        }

        // Lightness descriptor.
        if ($l < 0.15) {
            $lightness = 'very dark';
        } elseif ($l < 0.35) {
            $lightness = 'dark';
        } elseif ($l < 0.65) {
            $lightness = '';
        } elseif ($l < 0.85) {
            $lightness = 'light';
        } else {
            $lightness = 'very light';
        }

        // Saturation descriptor.
        if ($s < 0.15) {
            $saturation = 'muted';
        } elseif ($s < 0.35) {
            $saturation = 'muted';
        } elseif ($s < 0.70) {
            $saturation = '';
        } else {
            $saturation = 'vibrant';
        }

        $parts = array_filter([$lightness, $saturation, $hue_name]);

        return implode(' ', $parts);
    }

    private function generateWebpVariants(string $absolutePath, int $width, int $height, string $nameId): array
    {
        $contents = @file_get_contents($absolutePath);
        $src = $contents !== false ? @imagecreatefromstring($contents) : false;

        $longSide = max($width, $height);
        $sizesToMake = [1920, 1280, 768, 480];
        $generatedSizes = [];

        // Best-effort EXIF orientation fix for JPEG-family inputs; mirrors Rekog temp behavior.
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
                'Rekognition objects, colors, and text appear in highest significance order (index 0..N).',
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

        // Legacy single-analysis format
        if (array_key_exists('auto_identified_properties_from_aws_rekognition', $decoded)) {
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
        // Podium's --json-output sets JSON_OUTPUT; avoid noisy logs in that mode.
        $jsonOutput = env('JSON_OUTPUT', false);
        if ($jsonOutput) {
            return;
        }

        $this->line($message);
    }
}
