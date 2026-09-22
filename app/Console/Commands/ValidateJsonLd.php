<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Brick\StructuredData\HTMLReader;
use Brick\StructuredData\Reader\JsonLdReader;
use ML\JsonLD\JsonLD;

class ValidateJsonLd extends Command
{
    protected $signature = 'schema:validate-jsonld {--file=}';
    protected $description = 'Validate JSON-LD files in resources/views/jsonld (or a specific file).';

    public function handle(): int
    {
        if (! class_exists(\ML\JsonLD\JsonLD::class) || ! class_exists(\Brick\StructuredData\HTMLReader::class)) {
            $this->error('JSON-LD validation needs the dev dependencies (ml/json-ld, brick/structured-data). Run "composer install" without --no-dev.');
            return self::FAILURE;
        }

        $directory = resource_path('views/jsonld');

        if (! is_dir($directory)) {
            $this->error("Directory not found: {$directory}");
            return 1;
        }

        // If user specified a file
        $file = $this->option('file');
        if ($file) {
            $path = "{$directory}/{$file}";
            if (! file_exists($path)) {
                $this->error("File not found: {$path}");
                return 1;
            }
            return $this->validateFile($path);
        }

        // Otherwise validate all .jsonld files
        $files = glob("{$directory}/*.jsonld");

        if (empty($files)) {
            $this->info("No .jsonld files found in {$directory}");
            return 0;
        }

        $errors = false;

        foreach ($files as $file) {
            $result = $this->validateFile($file);
            if ($result !== 0) {
                $errors = true;
            }
        }

        return $errors ? 1 : 0;
    }

    private function validateFile(string $file): int
    {
        $this->line("\nChecking {$file}...");

        $json = file_get_contents($file);

        // Must have <script> wrapper already
        preg_match_all(
            '/<script[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/is',
            $json,
            $matches
        );

        if (empty($matches[1])) {
            $this->error("  ❌ No <script type=\"application/ld+json\"> block found.");
            return 1;
        }

        foreach ($matches[1] as $json_text) {
            $json_text = trim($json_text);

            try {
                $data = json_decode($json_text, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $this->error("  ❌ Invalid JSON: {$e->getMessage()}");
                return 1;
            }

            // JSON-LD structural validation
            try {
                JsonLD::expand($data);
            } catch (\Throwable $e) {
                $this->error("  ❌ Invalid JSON-LD: {$e->getMessage()}");
                return 1;
            }

            // Brick semantic extraction (JSON-LD only)
            $html = '<script type="application/ld+json">' . $json_text . '</script>';
            $reader = new HTMLReader(new JsonLdReader());
            $items = $reader->read($html, 'http://localhost/');

            if (empty($items)) {
                $this->warn("  ⚠ Valid JSON-LD, but no schema.org items detected.");
            } else {
                $this->info("  ✅ Valid JSON-LD");
            }
        }

        return 0;
    }
}
