<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageManifestBuilder
{
    public function __construct(
        private readonly RekognitionService $rekognition,
    ) {
    }

    /**
     * Build manifest for images under storage/app/public/images.
     *
     * @param array{rekognition?:bool,max_labels?:int,min_confidence?:int,pages?:array<string>,extensions?:array<string>} $options
     * @return array<string,mixed>
     */
    public function build(array $options = []): array
    {
        $useRekognition = (bool) ($options['rekognition'] ?? false);
        $pagesFilter = array_map('strval', (array) ($options['pages'] ?? []));
        $extensions = array_map('strtolower', (array) ($options['extensions'] ?? ['jpg','jpeg','png','gif','webp']));
        $maxLabels = isset($options['max_labels']) ? (int) $options['max_labels'] : null;
        $minConfidence = isset($options['min_confidence']) ? (int) $options['min_confidence'] : null;

        $public = Storage::disk('public');
        $files = $public->files('images');

        $items = [];
        foreach ($files as $relPath) {
            $ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
            if ($ext === '' || !in_array($ext, $extensions, true)) {
                continue;
            }

            $filename = basename($relPath);
            $parsed = $this->parseFilename($filename);
            if (!empty($pagesFilter) && $parsed['page'] !== null && !in_array($parsed['page'], $pagesFilter, true)) {
                continue;
            }

            $absPath = $public->path($relPath);
            [$width, $height] = $this->imageSize($absPath);
            $aspect = $width > 0 && $height > 0 ? round($width / $height, 4) : null;

            $role = $parsed['role'] ?? null;
            if ($role === null) {
                $role = $this->inferRoleByAspect($aspect);
            }

            $alt = $this->defaultAltFromParsed($parsed);

            $labels = [];
            if ($useRekognition) {
                $labels = $this->rekognition->detectLabels($absPath, $maxLabels, $minConfidence);
            }

            $keywords = array_values(array_unique(array_filter(array_map(static function ($l) {
                return is_array($l) ? (string) ($l['name'] ?? '') : '';
            }, $labels))));

            $items[] = [
                'file' => $filename,
                'path' => 'public/' . ltrim($relPath, '/'),
                'url' => $public->url($relPath),
                'page' => $parsed['page'],
                'section' => $parsed['section'],
                'role' => $role,
                'order' => $parsed['order'],
                'width' => $width,
                'height' => $height,
                'aspect_ratio' => $aspect,
                'alt' => $alt,
                'caption' => null,
                'hints' => [
                    'placement' => $this->placementForRole($role),
                    'keywords' => $keywords,
                ],
                'source' => [
                    'type' => 'local',
                ],
            ];
        }

        // Group by page
        $byPage = [];
        foreach ($items as $i) {
            $page = (string) ($i['page'] ?? 'default');
            $byPage[$page] = $byPage[$page] ?? [ 'page' => $page, 'images' => [] ];
            $byPage[$page]['images'][] = $i;
        }

        // Sort each page images by section then order then file
        foreach ($byPage as &$group) {
            usort($group['images'], static function ($a, $b) {
                return [$a['section'] ?? '', (int) ($a['order'] ?? 0), $a['file']]
                    <=> [$b['section'] ?? '', (int) ($b['order'] ?? 0), $b['file']];
            });
        }
        unset($group);

        return [
            'generated_at' => date('c'),
            'notes' => 'Generated from storage/app/public/images. Rekognition keywords optional.',
            'pages' => array_values($byPage),
        ];
    }

    /**
     * Very simple YAML dumper for arrays/scalars used here.
     */
    public function toYaml(mixed $data, int $indent = 0): string
    {
        $pad = str_repeat('  ', $indent);
        if (is_array($data)) {
            $isAssoc = array_keys($data) !== range(0, count($data) - 1);
            $out = '';
            if ($isAssoc) {
                foreach ($data as $k => $v) {
                    $out .= $pad . $this->yamlKey($k) . ': ' . $this->yamlInlineOrBlock($v, $indent) . "\n";
                }
                return $out;
            }
            foreach ($data as $v) {
                if (is_array($v)) {
                    $out .= $pad . "- " . $this->yamlInlineOrBlock($v, $indent, true) . "\n";
                } else {
                    $out .= $pad . "- " . $this->yamlScalar($v) . "\n";
                }
            }
            return $out;
        }
        return $pad . $this->yamlScalar($data) . "\n";
    }

    private function yamlInlineOrBlock(mixed $value, int $indent, bool $listItem = false): string
    {
        if (is_array($value)) {
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);
            if (!$isAssoc) {
                // simple list inline
                return '[' . implode(', ', array_map([$this, 'yamlScalar'], $value)) . ']';
            }
            $nl = "\n";
            $pad = str_repeat('  ', $indent + ($listItem ? 1 : 1));
            $s = $nl;
            foreach ($value as $k => $v) {
                $s .= $pad . $this->yamlKey($k) . ': ' . $this->yamlInlineOrBlock($v, $indent + 1) . $nl;
            }
            return rtrim($s, "\n");
        }
        return $this->yamlScalar($value);
    }

    private function yamlKey(string $k): string
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $k) ? $k : '"' . str_replace('"', '\\"', $k) . '"';
    }

    private function yamlScalar(mixed $v): string
    {
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if ($v === null) {
            return 'null';
        }
        if (is_numeric($v)) {
            return (string) $v;
        }
        $s = (string) $v;
        if ($s === '' || preg_match('/[:#\-\n\r\t]/', $s)) {
            return '"' . str_replace('"', '\\"', $s) . '"';
        }
        return $s;
    }

    /**
     * Parse filename pattern: <page>-<section>-<role>-<order>.<ext>
     * Returns [page?:string, section?:string, role?:string, order?:int]
     * Missing parts become null.
     *
     * @return array{page:?string,section:?string,role:?string,order:?int}
     */
    private function parseFilename(string $filename): array
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $parts = explode('-', $name);
        $parts = array_values(array_filter($parts, static fn($p) => $p !== ''));

        $page = $parts[0] ?? null;
        $section = $parts[1] ?? null;
        $role = $parts[2] ?? null;
        $order = isset($parts[3]) && ctype_digit($parts[3]) ? (int) $parts[3] : null;

        return [
            'page' => $page ? Str::slug($page, '-') : null,
            'section' => $section ? Str::slug($section, '-') : null,
            'role' => $role ? Str::slug($role, '-') : null,
            'order' => $order,
        ];
    }

    private function imageSize(string $absolutePath): array
    {
        if (!is_file($absolutePath)) {
            return [0, 0];
        }
        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return [0, 0];
        }
        return [(int) ($info[0] ?? 0), (int) ($info[1] ?? 0)];
    }

    private function inferRoleByAspect(?float $aspect): ?string
    {
        if ($aspect === null) {
            return null;
        }
        if ($aspect >= 2.0) {
            return 'banner'; // hero/banner very wide
        }
        if ($aspect >= 0.9 && $aspect <= 1.1) {
            return 'headshot'; // square-ish
        }
        return 'feature'; // fallback rectangular
    }

    private function placementForRole(?string $role): ?string
    {
        return match ($role) {
            'banner', 'hero' => 'hero',
            'feature', 'card' => 'features',
            'headshot', 'logo' => 'team_or_logos',
            default => 'gallery',
        };
    }

    private function defaultAltFromParsed(array $parsed): string
    {
        $tokens = [];
        foreach (['page', 'section', 'role'] as $k) {
            if (!empty($parsed[$k])) {
                $tokens[] = (string) $parsed[$k];
            }
        }
        $base = implode(' ', $tokens);
        return $base !== '' ? ucwords(str_replace('-', ' ', $base)) : '';
    }
}
