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
        {--pages= : Comma-separated page slugs to include}
        {--rekognition : Enable AWS Rekognition label detection}
        {--max-labels=10 : Max Rekognition labels}
        {--min-confidence=70 : Min confidence (0-100)}
        {--format=both : Output format: yaml|json|both}
        {--pixabay-query= : Optional Pixabay search query to import images}
        {--pixabay-per-page=12 : Pixabay results per page}
        {--pixabay-page=1 : Pixabay page number}
        {--dest=public/images/pixabay : Destination directory for Pixabay downloads (under storage/app)}
        {--force : Overwrite existing manifest files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan storage/app/public/images and generate image manifest (YAML/JSON) enriched with optional Rekognition keywords';

    public function handle(ImageManifestBuilder $builder, RekognitionService $rekognition): int
    {
        $pages = array_filter(array_map('trim', explode(',', (string) $this->option('pages'))));
        $useRekognition = (bool) $this->option('rekognition');
        $maxLabels = (int) $this->option('max-labels');
        $minConfidence = (int) $this->option('min-confidence');
        $format = strtolower((string) $this->option('format')) ?: 'both';
        $force = (bool) $this->option('force');
        $pixabayQuery = (string) $this->option('pixabay-query');
        $pixabayPerPage = (int) $this->option('pixabay-per-page');
        $pixabayPage = (int) $this->option('pixabay-page');
        $dest = (string) $this->option('dest');

        if ($useRekognition && !$rekognition->isAvailable()) {
            $this->warn('AWS Rekognition SDK/credentials not available; skipping labels.');
            $useRekognition = false;
        }

        // Optionally import images from Pixabay before building manifest
        if ($pixabayQuery !== '') {
            $this->importFromPixabay($pixabayQuery, $pixabayPerPage, $pixabayPage, $dest);
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
            if (!$force && $privateDisk->exists($yamlPath)) {
                $this->error('File exists: storage/app/private/' . $yamlPath . '. Use --force to overwrite.');
                return self::FAILURE;
            }
            $yaml = $builder->toYaml($manifest);
            $privateDisk->put($yamlPath, $yaml);
            $this->info('Wrote YAML: storage/app/private/' . $yamlPath);
        }

        if ($doJson) {
            if (!$force && $privateDisk->exists($jsonPath)) {
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

    private function importFromPixabay(string $query, int $perPage, int $page, string $destDir): void
    {
        // Resolve service lazily to avoid hard dependency in signature
        $service = app(\App\Services\PixabayService::class);
        if (!$service->isEnabled()) {
            $this->warn('PIXABAY_API_KEY not configured; skipping Pixabay import.');
            return;
        }

        $this->info("Searching Pixabay: '{$query}' (per_page={$perPage}, page={$page})");
        $hits = $service->search($query, [
            'per_page' => $perPage,
            'page' => $page,
            'safesearch' => 1,
            'image_type' => 'photo',
            'order' => 'popular',
        ]);
        if (empty($hits)) {
            $this->warn('No Pixabay results.');
            return;
        }

        if (!str_starts_with($destDir, 'public/')) {
            $this->warn("Destination '{$destDir}' is not under 'public/'. Using default 'public/images/pixabay'.");
            $destDir = 'public/images/pixabay';
        }
        if (!Storage::exists($destDir)) {
            Storage::makeDirectory($destDir);
        }

        $saved = 0;
        foreach ($hits as $hit) {
            $imageId = (string) ($hit['id'] ?? '');
            $large = (string) ($hit['largeImageURL'] ?? ($hit['webformatURL'] ?? ''));
            if ($imageId === '' || $large === '') {
                continue;
            }
            $ext = pathinfo(parse_url($large, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg';
            $name = 'pixabay-' . $imageId . '.' . strtolower($ext);
            $path = rtrim($destDir, '/') . '/' . $name;
            if (Storage::exists($path)) {
                continue;
            }

            try {
                $resp = \Illuminate\Support\Facades\Http::timeout(10)->get($large);
                if (!$resp->ok()) {
                    continue;
                }
                Storage::put($path, $resp->body());
                $saved++;
            } catch (\Throwable $e) {
                $this->warn('Failed to download Pixabay image: ' . $e->getMessage());
            }
        }

        $this->info("Downloaded {$saved} image(s) to storage/app/{$destDir}");
    }
}
