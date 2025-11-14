<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PixabaySearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:pixabay-search
        {query* : Search terms}
        {--image_type=photo}
        {--orientation=}
        {--order=popular}
        {--safesearch=1}
        {--per_page=10}
        {--page=1}
        {--category=}
        {--no-console : Do not write JSON to stdout}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Search Pixabay and output raw JSON result to stdout';

    public function handle(): int
    {
        $key = (string) config('services.pixabay.key');
        $baseUrl = (string) config('services.pixabay.base_url', 'https://pixabay.com/api/');
        $timeout = (int) config('services.pixabay.timeout', 6);

        if ($key === '') {
            $this->output->writeln(json_encode(['error' => 'PIXABAY_API_KEY not configured'], JSON_UNESCAPED_SLASHES));
            return self::FAILURE;
        }

        $q = trim(implode(' ', (array) $this->argument('query')));
        if ($q === '') {
            $this->output->writeln(json_encode(['error' => 'Empty query'], JSON_UNESCAPED_SLASHES));
            return self::FAILURE;
        }

        $params = [
            'key' => $key,
            'q' => $q,
            'image_type' => (string) $this->option('image_type'),
            'orientation' => (string) ($this->option('orientation') ?: ''),
            'order' => (string) $this->option('order'),
            'safesearch' => (string) $this->option('safesearch'),
            'per_page' => (int) $this->option('per_page'),
            'page' => (int) $this->option('page'),
            'category' => (string) ($this->option('category') ?: ''),
        ];

        // Remove empty optional params
        $params = array_filter($params, static function ($v) {
            return !($v === '' || $v === null);
        });

        $writeConsole = ! (bool) $this->option('no-console');

        // Build unique output filename under storage/app/private/pixabay
        $slug = Str::slug($q, '-');
        if ($slug === '') {
            $slug = 'pixabay-query';
        }
        $timestamp = date('Ymd_His');
        $directory = 'pixabay';
        $filename = $slug . '-' . $timestamp . '.json';

        $disk = Storage::disk('local'); // maps to storage/app/private
        if (! $disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        $outputFile = $directory . '/' . $filename;

        try {
            $resp = Http::timeout($timeout)->get($baseUrl, $params);
            if (!$resp->ok()) {
                $payload = [
                    'error' => 'Pixabay API error',
                    'status' => $resp->status(),
                    'body' => Str::limit($resp->body(), 2000),
                ];
                $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
                if ($writeConsole) {
                    $this->output->writeln($json);
                }
                $disk->put($outputFile, $json);
                return self::FAILURE;
            }

            $raw = $resp->body();

            // Pretty-print JSON before saving/echoing when possible.
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } else {
                $pretty = $raw;
            }

            // Save JSON to storage/app/private/<output>
            $disk->put($outputFile, $pretty);

            if ($writeConsole) {
                $this->output->writeln($pretty);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $payload = [
                'error' => 'Pixabay request exception',
                'message' => $e->getMessage(),
            ];
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
            if ($writeConsole) {
                $this->output->writeln($json);
            }
            $disk->put($outputFile, $json);
            return self::FAILURE;
        }
    }
}
