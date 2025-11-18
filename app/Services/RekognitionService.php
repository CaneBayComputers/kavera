<?php

namespace App\Services;

class RekognitionService
{
    public function isAvailable(): bool
    {
        return class_exists(\Aws\Rekognition\RekognitionClient::class)
            && (string) env('AWS_ACCESS_KEY_ID') !== ''
            && (string) env('AWS_SECRET_ACCESS_KEY') !== '';
    }

    /**
     * Detect labels using AWS Rekognition for an image file path.
     * Returns array of ['name' => string, 'confidence' => float].
     * If SDK not available or credentials missing, returns empty array.
     *
     * @param string $absolutePath Local filesystem path to image
     * @param int|null $maxLabels
     * @param int|null $minConfidence
     * @return array<int, array{name:string,confidence:float}>
     */
    public function detectLabels(string $absolutePath, ?int $maxLabels = null, ?int $minConfidence = null): array
    {
        $client = $this->makeClient();
        if ($client === null || ! is_file($absolutePath)) {
            return [];
        }

        $max = $maxLabels ?? (int) config('services.rekognition.max_labels', 10);
        $min = $minConfidence ?? (int) config('services.rekognition.min_confidence', 70);

        try {
            $bytes = file_get_contents($absolutePath);
            if ($bytes === false) {
                return [];
            }

            $result = $client->detectLabels([
                'Image' => ['Bytes' => $bytes],
                'MaxLabels' => $max,
                'MinConfidence' => $min,
            ]);

            $labels = $result['Labels'] ?? [];
            $out = [];
            foreach ($labels as $label) {
                $name = (string) ($label['Name'] ?? '');
                $conf = (float) ($label['Confidence'] ?? 0.0);
                if ($name !== '') {
                    $out[] = ['name' => $name, 'confidence' => $conf];
                }
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Detect image properties such as dominant colors and quality metrics (brightness, sharpness, contrast).
     * Returns a breakdown for the full image, foreground, and background when supported by the API.
     *
     * @return array<string,mixed>
     */
    public function detectImageProperties(string $absolutePath): array
    {
        $client = $this->makeClient();
        if ($client === null || ! is_file($absolutePath)) {
            return [];
        }

        try {
            $bytes = file_get_contents($absolutePath);
            if ($bytes === false) {
                return [];
            }

            // Some regions/APIs may not support IMAGE_PROPERTIES; if so, this will throw and we return [].
            $result = $client->detectLabels([
                'Image' => ['Bytes' => $bytes],
                'Features' => ['GENERAL_LABELS', 'IMAGE_PROPERTIES'],
            ]);

            $props = $result['ImageProperties'] ?? [];

            $normalizeQuality = static function (array $q): array {
                return [
                    'brightness' => $q['Brightness'] ?? null,
                    'sharpness' => $q['Sharpness'] ?? null,
                    'contrast' => $q['Contrast'] ?? null,
                ];
            };

            $normalizeColors = static function (array $colors): array {
                $out = [];
                foreach ($colors as $color) {
                    $out[] = [
                        'red' => $color['Red'] ?? null,
                        'green' => $color['Green'] ?? null,
                        'blue' => $color['Blue'] ?? null,
                        'hex' => $color['HexCode'] ?? null,
                        'saturation' => $color['Saturation'] ?? null,
                        'brightness' => $color['Brightness'] ?? null,
                        'pixel_percent' => $color['PixelPercentage'] ?? null,
                    ];
                }

                return $out;
            };

            $fullQuality = isset($props['Quality']) && is_array($props['Quality'])
                ? $normalizeQuality($props['Quality'])
                : $normalizeQuality([]);
            $fullColors = isset($props['DominantColors']) && is_array($props['DominantColors'])
                ? $normalizeColors($props['DominantColors'])
                : [];

            $foreground = $props['Foreground'] ?? [];
            $foregroundQuality = isset($foreground['Quality']) && is_array($foreground['Quality'])
                ? $normalizeQuality($foreground['Quality'])
                : $normalizeQuality([]);
            $foregroundColors = isset($foreground['DominantColors']) && is_array($foreground['DominantColors'])
                ? $normalizeColors($foreground['DominantColors'])
                : [];

            $background = $props['Background'] ?? [];
            $backgroundQuality = isset($background['Quality']) && is_array($background['Quality'])
                ? $normalizeQuality($background['Quality'])
                : $normalizeQuality([]);
            $backgroundColors = isset($background['DominantColors']) && is_array($background['DominantColors'])
                ? $normalizeColors($background['DominantColors'])
                : [];

            return [
                'full' => [
                    'quality' => $fullQuality,
                    'dominant_colors' => $fullColors,
                ],
                'foreground' => [
                    'quality' => $foregroundQuality,
                    'dominant_colors' => $foregroundColors,
                ],
                'background' => [
                    'quality' => $backgroundQuality,
                    'dominant_colors' => $backgroundColors,
                ],
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Detect faces and return a simplified set of attributes.
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectFaces(string $absolutePath): array
    {
        $client = $this->makeClient();
        if ($client === null || ! is_file($absolutePath)) {
            return [];
        }

        try {
            $bytes = file_get_contents($absolutePath);
            if ($bytes === false) {
                return [];
            }

            $result = $client->detectFaces([
                'Image' => ['Bytes' => $bytes],
                'Attributes' => ['ALL'],
            ]);

            $faces = $result['FaceDetails'] ?? [];
            $out = [];
            foreach ($faces as $face) {
                $out[] = [
                    'bounding_box' => $face['BoundingBox'] ?? null,
                    'confidence' => $face['Confidence'] ?? null,
                    'emotions' => $face['Emotions'] ?? [],
                    'age_range' => $face['AgeRange'] ?? null,
                    'gender' => $face['Gender'] ?? null,
                    'smile' => $face['Smile'] ?? null,
                    'eyeglasses' => $face['Eyeglasses'] ?? null,
                    'sunglasses' => $face['Sunglasses'] ?? null,
                    'beard' => $face['Beard'] ?? null,
                    'mustache' => $face['Mustache'] ?? null,
                    'eyes_open' => $face['EyesOpen'] ?? null,
                    'mouth_open' => $face['MouthOpen'] ?? null,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Detect text in the image and return detected strings with confidence.
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectText(string $absolutePath): array
    {
        $client = $this->makeClient();
        if ($client === null || ! is_file($absolutePath)) {
            return [];
        }

        try {
            $bytes = file_get_contents($absolutePath);
            if ($bytes === false) {
                return [];
            }

            $result = $client->detectText([
                'Image' => ['Bytes' => $bytes],
            ]);

            $detections = $result['TextDetections'] ?? [];
            $out = [];
            foreach ($detections as $det) {
                $out[] = [
                    'type' => $det['Type'] ?? null,
                    'text' => $det['DetectedText'] ?? null,
                    'confidence' => $det['Confidence'] ?? null,
                    'geometry' => $det['Geometry'] ?? null,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function makeClient(): ?\Aws\Rekognition\RekognitionClient
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $region = (string) config('services.rekognition.region', 'us-east-1');
        $version = (string) config('services.rekognition.version', 'latest');

        return new \Aws\Rekognition\RekognitionClient([
            'region' => $region,
            'version' => $version,
        ]);
    }
}
