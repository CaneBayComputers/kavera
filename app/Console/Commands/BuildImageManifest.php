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
        {--pages= : Comma-separated page slugs to include}
        {--rekognition : Enable AWS Rekognition label detection}
        {--max-labels=25 : Max Rekognition labels}
        {--min-confidence=70 : Min confidence (0-100)}
        {--format=both : Output format: yaml|json|both}
        {--force : Overwrite existing manifest files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Image ingest + optimization pipeline with AWS Rekognition analysis.';

    public function handle(RekognitionService $rekognition): int
    {
        $imagePath = (string) ($this->argument('image') ?? '');

        // Temporary single-image Rekognition test path (debug helper)
        if ($imagePath !== '') {
            return $this->handleSingleImage($imagePath, $rekognition);
        }
        $force = (bool) $this->option('force');

        return $this->handleBulkIngest($rekognition, $force);
    }

    /**
     * Temporary single-image Rekognition test: copy to /tmp, resize to max 1920px, send to Rekognition,
     * and write a minimal manifest.json under storage/app/private/images/.
     */
    private function handleSingleImage(string $imagePath, RekognitionService $rekognition): int
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

        if ($rekognition->isAvailable()) {
            $labels = $rekognition->detectLabels($tmpPath);
            $imageProperties = $rekognition->detectImageProperties($tmpPath);
            $faces = $rekognition->detectFaces($tmpPath);
            $textDetections = $rekognition->detectText($tmpPath);
        } else {
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
        $colors = [];

        if (! empty($imageProperties['full']['quality'])) {
            $quality = $imageProperties['full']['quality'];
            $brightness = $quality['brightness'] ?? null;
            $sharpness = $quality['sharpness'] ?? null;
            $contrast = $quality['contrast'] ?? null;
        }
        if (! empty($imageProperties['full']['dominant_colors']) && is_array($imageProperties['full']['dominant_colors'])) {
            foreach ($imageProperties['full']['dominant_colors'] as $color) {
                $hex = (string) ($color['hex'] ?? '');
                if ($hex !== '') {
                    $colors[] = $hex;
                }
            }
        }

        $facesCount = is_array($faces) ? count($faces) : 0;

        $textSegments = [];
        if (! empty($textDetections)) {
            usort($textDetections, static function ($a, $b) {
                return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
            });
            $seenText = [];
            foreach ($textDetections as $det) {
                $text = trim((string) ($det['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $key = mb_strtolower($text);
                if (isset($seenText[$key])) {
                    continue;
                }
                $seenText[$key] = true;
                $textSegments[] = $text;
            }
        }

        $disk = Storage::disk('local'); // storage/app/private
        if (! $disk->exists('images')) {
            $disk->makeDirectory('images');
        }

        $manifestPath = 'images/manifest.json';
        $payload = [
            'auto_identified_properties_from_aws_rekognition' => [
                'objects' => $objects,
                'brightness' => $brightness,
                'sharpness' => $sharpness,
                'contrast' => $contrast,
                'dominant_colors' => $colors,
                'faces' => $facesCount,
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
    private function handleBulkIngest(RekognitionService $rekognition, bool $force): int
    {
        $baseDir = storage_path('app/private/images');
        $providers = ['pexels', 'pixabay', 'unsplash', 'user'];

        $state = $this->loadManifestState();
        $imagesById = [];
        foreach ($state['images'] as $index => $entry) {
            if (! empty($entry['id'])) {
                $imagesById[$entry['id']] = $index;
            }
        }

        $processed = 0;

        foreach ($providers as $provider) {
            $providerDir = $baseDir . DIRECTORY_SEPARATOR . $provider;
            if (! is_dir($providerDir)) {
                continue;
            }

            if ($provider === 'user') {
                $this->processUserFolder($providerDir, $provider, $rekognition, $force, $state, $imagesById, $processed);
            } else {
                $this->processProviderFolder($providerDir, $provider, $rekognition, $force, $state, $imagesById, $processed);
            }
        }

        $this->saveManifestState($state);

        $this->info('Ingest complete. Processed ' . $processed . ' image(s).');

        return self::SUCCESS;
    }

    private function processProviderFolder(string $providerDir, string $provider, RekognitionService $rekognition, bool $force, array &$state, array &$imagesById, int &$processed): void
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

            $this->processImageForManifest($provider, $absolute, $originalKeyTerms, $rekognition, $force, $state, $imagesById, $processed);
        }
    }

    private function processUserFolder(string $userDir, string $provider, RekognitionService $rekognition, bool $force, array &$state, array &$imagesById, int &$processed): void
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
                $this->extractAndProcessZip($absolute, $provider, $rekognition, $force, $state, $imagesById, $processed);
                // Delete original zip after extraction as requested.
                @unlink($absolute);
                continue;
            }

            if (! $this->isSupportedImageExtension($absolute)) {
                continue;
            }

            $this->processImageForManifest($provider, $absolute, [], $rekognition, $force, $state, $imagesById, $processed);
        }
    }

    private function extractAndProcessZip(string $zipPath, string $provider, RekognitionService $rekognition, bool $force, array &$state, array &$imagesById, int &$processed): void
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
            $this->processImageForManifest($provider, $absolute, [], $rekognition, $force, $state, $imagesById, $processed);
        }

        // Clean up extracted files
        $this->deleteDirectoryRecursive($tmpDir);
    }

    private function isSupportedImageExtension(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'svg' || $ext === '') {
            return false;
        }

        $supported = [
            'jpg', 'jpeg', 'jpe', 'png', 'gif', 'bmp', 'tif', 'tiff', 'webp',
            'heic', 'heif', 'pdf', 'eps', 'ai', 'psd'
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

    private function processImageForManifest(string $provider, string $absolutePath, array $originalKeyTerms, RekognitionService $rekognition, bool $force, array &$state, array &$imagesById, int &$processed): void
    {
        if (! is_file($absolutePath)) {
            return;
        }

        $id = @sha1_file($absolutePath) ?: sha1($absolutePath);
        $existing = $imagesById[$id] ?? null;

        // Idempotency: if entry and optimized files exist and not forced, skip.
        if (! $force && $existing !== null) {
            $entry = $state['images'][$existing] ?? null;
            if (is_array($entry) && ! empty($entry['image_location']['name_id']) && ! empty($entry['image_location']['sizes']) && is_array($entry['image_location']['sizes'])) {
                $nameId = (string) $entry['image_location']['name_id'];
                $allSizesPresent = true;
                foreach ($entry['image_location']['sizes'] as $size) {
                    $optPath = storage_path('app/public/images/optimized/' . $size . '/' . $nameId);
                    if (! is_file($optPath)) {
                        $allSizesPresent = false;
                        break;
                    }
                }
                if ($allSizesPresent) {
                    return;
                }
            }
        }

        $imageSize = @getimagesize($absolutePath);
        if ($imageSize === false) {
            $this->warn('Skipping unsupported image (cannot read size): ' . $absolutePath);
            return;
        }
        $width = (int) ($imageSize[0] ?? 0);
        $height = (int) ($imageSize[1] ?? 0);
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

        $autoProps = $this->analyzeWithRekognition($tmpPath, $rekognition);

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
        $finalKeywords = array_values($keywords);

        // TODO: read existing EXIF/IPTC/XMP keywords and merge; for now we rely on provider terms + Rekog.

        // Generate WebP variants
        [$imageLocationNameId, $sizes] = $this->generateWebpVariants($absolutePath, $width, $height);

        $entry = [
            'id' => $id,
            'provider' => $provider,
            'original_path' => $absolutePath,
            'orientation' => $orientation,
            'aspect_ratio' => $aspect,
            'original_key_terms' => array_values($originalKeyTerms),
            'final_keywords' => $finalKeywords,
            'auto_identified_properties_from_aws_rekognition' => $autoProps,
            'image_location' => [
                'name_id' => $imageLocationNameId,
                'sizes' => $sizes,
            ],
        ];

        if ($existing !== null) {
            $state['images'][$existing] = $entry;
        } else {
            $state['images'][] = $entry;
            $imagesById[$id] = count($state['images']) - 1;
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
        if ($contents === false) {
            return null;
        }

        $src = @imagecreatefromstring($contents);
        if (! $src) {
            return null;
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

    private function analyzeWithRekognition(string $tmpPath, RekognitionService $rekognition): array
    {
        $labels = [];
        $imageProperties = [];
        $faces = [];
        $textDetections = [];

        if ($rekognition->isAvailable()) {
            $labels = $rekognition->detectLabels($tmpPath);
            $imageProperties = $rekognition->detectImageProperties($tmpPath);
            $faces = $rekognition->detectFaces($tmpPath);
            $textDetections = $rekognition->detectText($tmpPath);
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
        $colors = [];

        if (! empty($imageProperties['full']['quality'])) {
            $quality = $imageProperties['full']['quality'];
            $brightness = $quality['brightness'] ?? null;
            $sharpness = $quality['sharpness'] ?? null;
            $contrast = $quality['contrast'] ?? null;
        }
        if (! empty($imageProperties['full']['dominant_colors']) && is_array($imageProperties['full']['dominant_colors'])) {
            foreach ($imageProperties['full']['dominant_colors'] as $color) {
                $hex = (string) ($color['hex'] ?? '');
                if ($hex !== '') {
                    $colors[] = $hex;
                }
            }
        }

        $facesCount = is_array($faces) ? count($faces) : 0;

        $textSegments = [];
        if (! empty($textDetections)) {
            usort($textDetections, static function ($a, $b) {
                return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
            });
            $seenText = [];
            foreach ($textDetections as $det) {
                $text = trim((string) ($det['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $key = mb_strtolower($text);
                if (isset($seenText[$key])) {
                    continue;
                }
                $seenText[$key] = true;
                $textSegments[] = $text;
            }
        }

        return [
            'objects' => $objects,
            'brightness' => $brightness,
            'sharpness' => $sharpness,
            'contrast' => $contrast,
            'dominant_colors' => $colors,
            'faces' => $facesCount,
            'text_segments' => $textSegments,
        ];
    }

    private function generateWebpVariants(string $absolutePath, int $width, int $height): array
    {
        $contents = @file_get_contents($absolutePath);
        if ($contents === false) {
            return ['', []];
        }

        $src = @imagecreatefromstring($contents);
        if (! $src) {
            return ['', []];
        }

        $longSide = max($width, $height);
        $sizesToMake = [1920, 1280, 768, 480];
        $nameId = Str::uuid()->toString() . '.webp';
        $generatedSizes = [];

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

            $optDir = storage_path('app/public/images/optimized/' . $target);
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

    private function loadManifestState(): array
    {
        $disk = Storage::disk('local');
        $path = 'images/manifest.json';

        if (! $disk->exists($path)) {
            return [
                'notes' => [
                    'Rekog objects, dominant_colors, and text appear in highest confidence order (index 0..N).',
                    "Use and utilize all pictures found in 'user' folder.",
                ],
                'images' => [],
            ];
        }

        $raw = $disk->get($path);
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [
                'notes' => [
                    'Rekog objects, dominant_colors, and text appear in highest confidence order (index 0..N).',
                    "Use and utilize all pictures found in 'user' folder.",
                ],
                'images' => [],
            ];
        }

        // If legacy format (single analysis object), reset to new structure.
        if (array_key_exists('auto_identified_properties_from_aws_rekognition', $decoded)) {
            return [
                'notes' => [
                    'Rekog objects, dominant_colors, and text appear in highest confidence order (index 0..N).',
                    "Use and utilize all pictures found in 'user' folder.",
                ],
                'images' => [],
            ];
        }

        if (! array_key_exists('notes', $decoded) || ! array_key_exists('images', $decoded) || ! is_array($decoded['images'])) {
            $decoded = [
                'notes' => [
                    'Rekog objects, dominant_colors, and text appear in highest confidence order (index 0..N).',
                    "Use and utilize all pictures found in 'user' folder.",
                ],
                'images' => [],
            ];
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
}
