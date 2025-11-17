<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UnsplashSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:unsplash-search
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
    protected $description = 'Search Unsplash, download JPEGs (urls.raw), and write combined description into XMP dc:description';

    public function handle(): int
    {
        $accessKey = (string) config('services.unsplash.access_key');
        $baseUrl = (string) config('services.unsplash.base_url', 'https://api.unsplash.com');
        $timeout = (int) config('services.unsplash.timeout', 8);

        if ($accessKey === '') {
            $this->error('UNSPLASH_ACCESS_KEY not configured');
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
            $slug = 'unsplash-query';
        }
        $timestamp = date('Ymd_His');
        $baseDirectory = 'images/unsplash';
        $runDirectory = $baseDirectory . '/' . $slug . '-' . $timestamp;

        $disk = Storage::disk('local'); // storage/app/private
        if (! $disk->exists($baseDirectory)) {
            $disk->makeDirectory($baseDirectory);
        }
        if (! $disk->exists($runDirectory)) {
            $disk->makeDirectory($runDirectory);
        }

        try {
            $url = rtrim($baseUrl, '/') . '/search/photos';
            $resp = Http::timeout($timeout)
                ->withHeaders([
                    'Accept-Version' => 'v1',
                    'Authorization' => 'Client-ID ' . $accessKey,
                ])
                ->get($url, $params);

            if (! $resp->ok()) {
                throw new \RuntimeException('Unsplash API error, status ' . $resp->status());
            }

            $decoded = $resp->json();
            if (! is_array($decoded)) {
                throw new \RuntimeException('Unsplash response is not valid JSON.');
            }

            $results = $decoded['results'] ?? [];
            if (! is_array($results) || $results === []) {
                if ($writeConsole) {
                    $this->info('No Unsplash results for query: ' . $query);
                }
                return self::SUCCESS;
            }

            $savedCount = 0;
            foreach ($results as $photo) {
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
            $this->error('Unsplash search failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Download a single Unsplash JPEG using urls.raw and write combined description into XMP dc:description.
     *
     * @param array<string,mixed> $photo
     * @throws \RuntimeException
     */
    private function downloadAndTagImage(array $photo, $disk, string $runDirectory, bool $writeConsole): void
    {
        $id = (string) ($photo['id'] ?? '');

        $urls = $photo['urls'] ?? [];
        $imageUrl = null;
        if (is_array($urls)) {
            // Prefer the raw URL as requested.
            $imageUrl = (string) ($urls['raw'] ?? $urls['full'] ?? $urls['regular'] ?? '');
        }
        if ($imageUrl === null || $imageUrl === '') {
            throw new \RuntimeException('Missing image URL (urls.raw/full/regular) for Unsplash photo ' . ($id !== '' ? $id : '[unknown id]'));
        }

        $description = trim((string) ($photo['description'] ?? ''));
        $alt = trim((string) ($photo['alt_description'] ?? ''));

        $combined = '';
        if ($description !== '' && $alt !== '') {
            $combined = $description . ' (' . $alt . ')';
        } elseif ($description !== '') {
            $combined = $description;
        } elseif ($alt !== '') {
            $combined = $alt;
        }

        $pathPart = (string) parse_url($imageUrl, PHP_URL_PATH);
        $ext = strtolower(pathinfo($pathPart, PATHINFO_EXTENSION) ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'jpe'], true)) {
            $ext = 'jpg';
        }

        $baseName = $id !== '' ? 'unsplash-' . $id : 'unsplash-' . md5($imageUrl);
        $relativePath = rtrim($runDirectory, '/') . '/' . $baseName . '.' . $ext;

        $resp = Http::timeout(20)->get($imageUrl);
        if (! $resp->ok()) {
            throw new \RuntimeException('Failed to download image for Unsplash photo ' . $id . '. Status ' . $resp->status());
        }

        $disk->put($relativePath, $resp->body());
        $absolutePath = $disk->path($relativePath);

        if ($combined !== '') {
            $this->embedDescriptionIntoJpegXmp($absolutePath, $combined);
        }

        if ($writeConsole) {
            $this->info('Saved image for Unsplash photo ' . $id . ' to storage/app/private/' . $relativePath);
        }
    }

    /**
     * Embed a combined description (description + alt_description) into a JPEG's XMP dc:description.
     *
     * @throws \RuntimeException
     */
    private function embedDescriptionIntoJpegXmp(string $path, string $text): void
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

        $updated = $this->updateJpegXmpDcDescription($data, $text);

        if (file_put_contents($path, $updated) === false) {
            throw new \RuntimeException('Failed to write updated JPEG metadata: ' . $path);
        }
    }

    /**
     * Update or insert an XMP packet with dc:description set to the given text (if blank).
     * Returns full JPEG binary with pixel data preserved.
     */
    private function updateJpegXmpDcDescription(string $jpegData, string $text): string
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
        $newXmpXml = $this->mergeXmpDcDescriptionXml($existingXmpXml, $text);
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
     * Merge a single description string into an XMP packet's dc:description element.
     */
    private function mergeXmpDcDescriptionXml(?string $existingXml, string $text): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $descriptionNode = null;

        if ($existingXml !== null && trim($existingXml) !== '') {
            if (@$dom->loadXML($existingXml)) {
                $xpath = new \DOMXPath($dom);
                $xpath->registerNamespace('x', 'adobe:ns:meta/');
                $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
                $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');

                $descriptionNode = $xpath->query('//rdf:RDF/rdf:Description')->item(0);
            }
        }

        if (! $descriptionNode instanceof \DOMElement) {
            [$dom, $descriptionNode] = $this->createBasicXmpDom();
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        $dcDescription = $xpath->query('.//dc:description', $descriptionNode)->item(0);
        if (! $dcDescription instanceof \DOMElement) {
            $dcDescription = $dom->createElementNS('http://purl.org/dc/elements/1.1/', 'dc:description', $text);
            $descriptionNode->appendChild($dcDescription);
        } elseif (trim((string) $dcDescription->nodeValue) === '') {
            while ($dcDescription->firstChild) {
                $dcDescription->removeChild($dcDescription->firstChild);
            }
            $dcDescription->appendChild($dom->createTextNode($text));
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

        $descriptionNode = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:Description');
        $rdf->appendChild($descriptionNode);

        return [$dom, $descriptionNode];
    }
}
