<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PexelsSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:pexels-search
        {query* : Search terms}
        {--per_page=10}
        {--page=1}
        {--orientation=}
        {--no-console : Do not write per-image log lines}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Search Pexels, download JPEGs, and merge keyword tags into XMP dc:subject metadata';

    public function handle(): int
    {
        $apiKey = (string) config('services.pexels.api_key');
        $baseUrl = (string) config('services.pexels.base_url', 'https://api.pexels.com/v1');
        $timeout = (int) config('services.pexels.timeout', 8);

        if ($apiKey === '') {
            $this->error('PEXELS_API_KEY not configured');
            return self::FAILURE;
        }

        $query = trim(implode(' ', (array) $this->argument('query')));
        if ($query === '') {
            $this->error('Empty query');
            return self::FAILURE;
        }

        $params = [
            'query' => $query,
            'per_page' => (int) $this->option('per_page'),
            'page' => (int) $this->option('page'),
        ];
        $orientation = (string) $this->option('orientation');
        if ($orientation !== '') {
            $params['orientation'] = $orientation;
        }

        $writeConsole = ! (bool) $this->option('no-console');

        $slug = Str::slug($query, '-');
        if ($slug === '') {
            $slug = 'pexels-query';
        }
        $timestamp = date('Ymd_His');
        $baseDirectory = 'images/pexels';
        $runDirectory = $baseDirectory . '/' . $slug . '-' . $timestamp;

        $disk = Storage::disk('local'); // storage/app/private
        if (! $disk->exists($baseDirectory)) {
            $disk->makeDirectory($baseDirectory);
        }
        if (! $disk->exists($runDirectory)) {
            $disk->makeDirectory($runDirectory);
        }

        try {
            $url = rtrim($baseUrl, '/') . '/search';
            $resp = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => $apiKey,
                ])
                ->get($url, $params);

            if (! $resp->ok()) {
                throw new \RuntimeException('Pexels API error, status ' . $resp->status());
            }

            $decoded = $resp->json();
            if (! is_array($decoded)) {
                throw new \RuntimeException('Pexels response is not valid JSON.');
            }

            $photos = $decoded['photos'] ?? [];
            if (! is_array($photos) || $photos === []) {
                if ($writeConsole) {
                    $this->info('No Pexels results for query: ' . $query);
                }
                return self::SUCCESS;
            }

            $savedCount = 0;
            foreach ($photos as $photo) {
                if (! is_array($photo)) {
                    continue;
                }

                $this->downloadAndTagImage($photo, $disk, $runDirectory, $writeConsole);
                $savedCount++;
            }

            if ($writeConsole) {
                $this->info('Downloaded ' . $savedCount . ' image(s) into storage/app/private/' . $runDirectory);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Pexels search failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Download a single Pexels JPEG (src.original) and write its alt text into XMP dc:subject.
     *
     * @param array<string,mixed> $photo
     * @throws \RuntimeException
     */
    private function downloadAndTagImage(array $photo, $disk, string $runDirectory, bool $writeConsole): void
    {
        $id = (string) ($photo['id'] ?? '');

        $src = $photo['src'] ?? [];
        $imageUrl = null;
        if (is_array($src)) {
            $imageUrl = (string) ($src['original'] ?? $src['large2x'] ?? $src['large'] ?? '');
        }
        if ($imageUrl === null || $imageUrl === '') {
            throw new \RuntimeException('Missing image URL for Pexels photo ' . ($id !== '' ? $id : '[unknown id]'));
        }

        $altText = '';
        if (! empty($photo['alt']) && is_string($photo['alt'])) {
            $altText = trim((string) $photo['alt']);
        }

        $pathPart = (string) parse_url($imageUrl, PHP_URL_PATH);
        $ext = strtolower(pathinfo($pathPart, PATHINFO_EXTENSION) ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'jpe'], true)) {
            $ext = 'jpg';
        }

        $baseName = $id !== '' ? 'pexels-' . $id : 'pexels-' . md5($imageUrl);
        $relativePath = rtrim($runDirectory, '/') . '/' . $baseName . '.' . $ext;

        $resp = Http::timeout(20)->get($imageUrl);
        if (! $resp->ok()) {
            throw new \RuntimeException('Failed to download image for Pexels photo ' . $id . '. Status ' . $resp->status());
        }

        $disk->put($relativePath, $resp->body());
        $absolutePath = $disk->path($relativePath);

        if ($altText !== '') {
            $this->embedAltIntoJpegXmp($absolutePath, $altText);
        }

        if ($writeConsole) {
            $this->info('Saved image for Pexels photo ' . $id . ' to storage/app/private/' . $relativePath);
        }
    }

    /**
     * Embed a single alt text string into a JPEG's XMP dc:description field, preserving other metadata and pixels.
     *
     * @throws \RuntimeException
     */
    private function embedAltIntoJpegXmp(string $path, string $altText): void
    {
        if (! is_file($path)) {
            throw new \RuntimeException('JPEG file not found: ' . $path);
        }
        if (! is_writable($path)) {
            throw new \RuntimeException('JPEG file not writable: ' . $path);
        }

        $data = file_get_contents($path);
        if ($data === false) {
            throw new \RuntimeException('Failed to read JPEG file: ' . $path);
        }

        // Basic JPEG validation
        if (strlen($data) < 4 || $data[0] !== "\xFF" || $data[1] !== "\xD8") {
            throw new \RuntimeException('File does not appear to be a valid JPEG: ' . $path);
        }

        $tags = [$altText];

        $updated = $this->updateJpegXmpDcSubject($data, $tags);

        if (file_put_contents($path, $updated) === false) {
            throw new \RuntimeException('Failed to write updated JPEG metadata: ' . $path);
        }
    }

    /**
     * Update or insert an XMP packet with dc:description merged with the given tags.
     * Returns full JPEG binary with pixel data preserved.
     *
     * @param array<int,string> $tags
     * @throws \RuntimeException
     */
    private function updateJpegXmpDcSubject(string $jpegData, array $tags): string
    {
        $len = strlen($jpegData);
        if ($len < 4) {
            throw new \RuntimeException('JPEG data too short to be valid.');
        }

        $offset = 2; // Skip SOI
        $segments = [];
        $xmpIndex = null;
        $existingXmpXml = null;
        $tail = '';
        $xmpHeader = "http://ns.adobe.com/xap/1.0/\0";

        while ($offset + 4 <= $len) {
            if ($jpegData[$offset] !== "\xFF") {
                throw new \RuntimeException('Unexpected JPEG marker structure while scanning segments.');
            }

            $marker = ord($jpegData[$offset + 1]);
            $offset += 2;

            // Start of Scan (SOS) or End of Image (EOI): remainder is tail (pixel data + markers)
            if ($marker === 0xDA || $marker === 0xD9) {
                $tail = substr($jpegData, $offset - 2);
                break;
            }

            if ($offset + 2 > $len) {
                throw new \RuntimeException('Truncated JPEG segment length at offset ' . $offset . '.');
            }

            $length = (ord($jpegData[$offset]) << 8) | ord($jpegData[$offset + 1]);
            if ($length < 2 || $offset + $length > $len) {
                throw new \RuntimeException('Invalid JPEG segment length encountered.');
            }

            $segmentData = substr($jpegData, $offset + 2, $length - 2);
            $segments[] = [
                'marker' => $marker,
                'data' => $segmentData,
            ];

            if ($marker === 0xE1 && strncmp($segmentData, $xmpHeader, strlen($xmpHeader)) === 0) {
                $xmpIndex = count($segments) - 1;
                $existingXmpXml = substr($segmentData, strlen($xmpHeader));
            }

            $offset += $length;
        }

        // Merge or create XMP XML
        $newXmpXml = $this->mergeXmpDcDescriptionXml($existingXmpXml, $tags);
        $newXmpSegmentData = $xmpHeader . $newXmpXml;

        if ($xmpIndex === null) {
            // Insert new XMP segment near the beginning (after existing segments, before tail)
            array_splice($segments, 0, 0, [[
                'marker' => 0xE1,
                'data' => $newXmpSegmentData,
            ]]);
        } else {
            $segments[$xmpIndex]['data'] = $newXmpSegmentData;
        }

        // Rebuild JPEG: SOI + segments + tail
        $out = "\xFF\xD8";
        foreach ($segments as $seg) {
            $marker = (int) $seg['marker'];
            $data = (string) $seg['data'];
            $segLength = strlen($data) + 2;
            if ($segLength > 0xFFFF) {
                throw new \RuntimeException('XMP segment too large to fit into JPEG APP1.');
            }
            $out .= "\xFF" . chr($marker);
            $out .= chr(($segLength >> 8) & 0xFF) . chr($segLength & 0xFF);
            $out .= $data;
        }

        $out .= $tail;

        return $out;
    }

    /**
     * Merge tags into an XMP packet's dc:description.
     *
     * @param array<int,string> $tags
     */
    private function mergeXmpDcDescriptionXml(?string $existingXml, array $tags): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $description = null;

        if ($existingXml !== null && trim($existingXml) !== '') {
            if (@$dom->loadXML($existingXml)) {
                $xpath = new \DOMXPath($dom);
                $xpath->registerNamespace('x', 'adobe:ns:meta/');
                $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
                $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');

                $description = $xpath->query('//rdf:RDF/rdf:Description')->item(0);
            }
        }

        if (! $description instanceof \DOMElement) {
            [$dom, $description] = $this->createBasicXmpDom();
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        // We only care about the first tag (alt text) for description.
        $text = '';
        foreach ($tags as $tag) {
            if ((string) $tag !== '') {
                $text = (string) $tag;
                break;
            }
        }

        if ($text === '') {
            return $existingXml ?? ($dom->saveXML($dom->documentElement) ?: '');
        }

        $descNode = $xpath->query('.//dc:description', $description)->item(0);
        if (! $descNode instanceof \DOMElement) {
            $descNode = $dom->createElementNS('http://purl.org/dc/elements/1.1/', 'dc:description', $text);
            $description->appendChild($descNode);
        } elseif (trim((string) $descNode->nodeValue) === '') {
            // Only set if there is no existing description text.
            while ($descNode->firstChild) {
                $descNode->removeChild($descNode->firstChild);
            }
            $descNode->appendChild($dom->createTextNode($text));
        }

        return $dom->saveXML($dom->documentElement) ?: '';
    }

    /**
     * Create a minimal XMP DOM structure with rdf:Description.
     *
     * @return array{0:\DOMDocument,1:\DOMElement}
     */
    private function createBasicXmpDom(): array
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $xmpmeta = $dom->createElementNS('adobe:ns:meta/', 'x:xmpmeta');
        $dom->appendChild($xmpmeta);

        $rdf = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:RDF');
        $xmpmeta->appendChild($rdf);

        $description = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:Description');
        $rdf->appendChild($description);

        return [$dom, $description];
    }
}
