<?php

namespace App\Console\Commands;

use App\Services\ImageManifestBuilder;
use App\Services\RekognitionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

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
        {--max-labels=10 : Max Rekognition labels}
        {--min-confidence=70 : Min confidence (0-100)}
        {--celebrities : Enable Rekognition celebrity recognition (single-image mode)}
        {--format=both : Output format: yaml|json|both}
        {--force : Overwrite existing manifest files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate image manifest from storage/app/public/images or (temporarily) analyze a single image path with Rekognition.';

    public function handle(ImageManifestBuilder $builder, RekognitionService $rekognition): int
    {
        $imagePath = (string) ($this->argument('image') ?? '');

        // Temporary single-image Rekognition test path
        if ($imagePath !== '') {
            $withCelebrities = (bool) $this->option('celebrities');
            return $this->handleSingleImage($imagePath, $rekognition, $withCelebrities);
        }

        $pages = array_filter(array_map('trim', explode(',', (string) $this->option('pages'))));
        $useRekognition = (bool) $this->option('rekognition');
        $maxLabels = (int) $this->option('max-labels');
        $minConfidence = (int) $this->option('min-confidence');
        $format = strtolower((string) $this->option('format')) ?: 'both';
        $force = (bool) $this->option('force');

        if ($useRekognition && ! $rekognition->isAvailable()) {
            $this->warn('AWS Rekognition SDK/credentials not available; skipping labels.');
            $useRekognition = false;
        }

        $manifest = $builder->build([
            'pages' => $pages,
            'rekognition' => $useRekognition,
            'max_labels' => $maxLabels,
            'min_confidence' => $minConfidence,
        ]);

        $privateDisk = Storage::disk('local'); // root at storage/app/private
        $yamlPath = 'images-manifest.yaml';
        $jsonPath = 'images-manifest.json';

        $doYaml = $format === 'yaml' || $format === 'both';
        $doJson = $format === 'json' || $format === 'both';

        if ($doYaml) {
            if (! $force && $privateDisk->exists($yamlPath)) {
                $this->error('File exists: storage/app/private/' . $yamlPath . '. Use --force to overwrite.');
                return self::FAILURE;
            }
            $yaml = $builder->toYaml($manifest);
            $privateDisk->put($yamlPath, $yaml);
            $this->info('Wrote YAML: storage/app/private/' . $yamlPath);
        }

        if ($doJson) {
            if (! $force && $privateDisk->exists($jsonPath)) {
                $this->error('File exists: storage/app/private/' . $jsonPath . '. Use --force to overwrite.');
                return self::FAILURE;
            }
            $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $privateDisk->put($jsonPath, $json);
            $this->info('Wrote JSON: storage/app/private/' . $jsonPath);
        }

        $this->line('Done.');
        return self::SUCCESS;
    }

    /**
     * Temporary single-image Rekognition test: copy to /tmp, resize to max 1920px, send to Rekognition,
     * and write a minimal manifest.json under storage/app/private/images/.
     */
    private function handleSingleImage(string $imagePath, RekognitionService $rekognition, bool $withCelebrities): int
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
        $celebrities = [];

        if ($rekognition->isAvailable()) {
            $labels = $rekognition->detectLabels($tmpPath);
            $imageProperties = $rekognition->detectImageProperties($tmpPath);
            $faces = $rekognition->detectFaces($tmpPath);
            $textDetections = $rekognition->detectText($tmpPath);
            if ($withCelebrities) {
                $celebrities = $rekognition->recognizeCelebrities($tmpPath);
            }
        } else {
            $this->warn('AWS Rekognition SDK/credentials not available; analysis fields will be empty.');
        }

        $disk = Storage::disk('local'); // storage/app/private
        if (! $disk->exists('images')) {
            $disk->makeDirectory('images');
        }

        $manifestPath = 'images/manifest.json';
        $payload = [
            'generated_at' => date('c'),
            'source_image' => [
                'input' => $imagePath,
                'absolute' => $absolute,
            ],
            'temp_image' => [
                'path' => $tmpPath,
                'width' => $resizedWidth,
                'height' => $resizedHeight,
            ],
            'analysis' => [
                'labels' => $labels,
                'image_properties' => $imageProperties,
                'faces' => $faces,
                'text' => $textDetections,
                'celebrities' => $celebrities,
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
}
