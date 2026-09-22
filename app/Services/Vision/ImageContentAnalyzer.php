<?php

namespace App\Services\Vision;

use Throwable;

/**
 * Batches manifest images onto contact sheets, sends each sheet to the vision
 * provider, and maps the per-cell answers back to the manifest hashes.
 */
final class ImageContentAnalyzer
{
    public function __construct(
        private readonly VisionProvider $provider,
        private readonly ContactSheetBuilder $sheets,
        private readonly int $sheetSize = 16,
    ) {
    }

    /**
     * @param array<string, array{path:string, hint:string}> $images Keyed by manifest hash.
     * @param callable(string):void|null $log Progress and warning sink.
     *
     * @return array<string, array<string, mixed>> hash => content_analysis entry.
     */
    public function analyze(array $images, ?callable $log = null): array
    {
        $log ??= static function (): void {
        };

        $results = [];
        $batches = array_chunk($images, max(1, $this->sheetSize), true);
        $total = count($batches);

        foreach ($batches as $number => $batch) {
            $hashes = array_keys($batch);
            $paths = array_map(static fn(array $entry): string => $entry['path'], array_values($batch));
            $hints = [];
            foreach (array_values($batch) as $index => $entry) {
                $hints[$index + 1] = $entry['hint'];
            }

            $log(sprintf('  Sheet %d/%d: %d image(s) via %s (%s)', $number + 1, $total, count($paths), $this->provider->name(), $this->provider->model()));

            $sheetPath = null;
            try {
                $sheetPath = $this->sheets->build($paths);
                $cells = $this->provider->describeSheet($sheetPath, count($paths), $hints);
            } catch (Throwable $e) {
                $log('  WARNING: sheet skipped: ' . strtok($e->getMessage(), "\n"));
                continue;
            } finally {
                if ($sheetPath !== null && is_file($sheetPath)) {
                    @unlink($sheetPath);
                }
            }

            $analyzedAt = date('c');
            foreach ($hashes as $index => $hash) {
                $cell = $cells[$index + 1] ?? null;
                if ($cell === null) {
                    $log('  WARNING: no answer for cell ' . ($index + 1) . ' (' . $hash . ')');
                    continue;
                }
                $results[$hash] = $cell + [
                    'provider' => $this->provider->name(),
                    'model' => $this->provider->model(),
                    'analyzed_at' => $analyzedAt,
                ];
            }
        }

        return $results;
    }
}
