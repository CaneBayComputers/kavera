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
        if (!$this->isAvailable() || !is_file($absolutePath)) {
            return [];
        }

        $region = (string) config('services.rekognition.region', 'us-east-1');
        $version = (string) config('services.rekognition.version', 'latest');
        $max = $maxLabels ?? (int) config('services.rekognition.max_labels', 10);
        $min = $minConfidence ?? (int) config('services.rekognition.min_confidence', 70);

        try {
            /** @var \Aws\Rekognition\RekognitionClient $client */
            $client = new \Aws\Rekognition\RekognitionClient([
                'region' => $region,
                'version' => $version,
            ]);

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
}
