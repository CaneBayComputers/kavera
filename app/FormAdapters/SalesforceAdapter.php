<?php

namespace App\FormAdapters;

use App\FormAdapters\Contracts\FormAdapter;

class SalesforceAdapter implements FormAdapter
{
    public function transform(string $formName, string $submissionId, array $fields, array $context = []): array
    {
        // Parameters required by interface; unused in payload shape.
        unset($formName, $submissionId);
        // Build payload using an optional field map from options.
        $opts = (array) ($context['options'] ?? []);

        $map = (array) ($opts['field_map'] ?? []);
        $defaults = (array) ($opts['defaults'] ?? []);

        // Helper to read from raw fields by key (no deep dot resolution to keep it simple here)
        $get = static function (string $key, $fallback = null) use ($fields) {
            return array_key_exists($key, $fields) ? $fields[$key] : $fallback;
        };

        // No hard-coded defaults. Expect explicit mapping via options.field_map.

        $payload = [];
        foreach ($map as $sfField => $sourceKey) {
            $payload[$sfField] = $get((string) $sourceKey);
        }

        // Apply defaults when values are missing
        if (!empty($defaults)) {
            foreach ($defaults as $sfField => $value) {
                if (!isset($payload[$sfField]) || $payload[$sfField] === null || $payload[$sfField] === '') {
                    $payload[$sfField] = $value;
                }
            }
        }

        // Ensure required Lead fields exist with safe fallbacks
        if (!isset($payload['LastName']) || $payload['LastName'] === null || $payload['LastName'] === '') {
            $payload['LastName'] = 'Unknown';
        }
        if (!isset($payload['Company']) || $payload['Company'] === null || $payload['Company'] === '') {
            $payload['Company'] = config('services.salesforce.default_company', 'Unknown');
        }

        return $payload;
    }

    public function requestOptions(array $context = []): array
    {
        $opts = (array) ($context['options'] ?? []);

        // Prefer configured base URL + API version + object to compute the endpoint
        $baseUrl    = (string) ($opts['base_url'] ?? config('services.salesforce.base_url', ''));
        $apiVersion = (string) ($opts['api_version'] ?? config('services.salesforce.api_version', 'v59.0'));
        $object     = (string) ($opts['object'] ?? config('services.salesforce.object', 'Lead'));
        $token      = (string) ($opts['access_token'] ?? config('services.salesforce.access_token', ''));

        $headers = [];
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $out = [
            'method'  => 'POST',
            'headers' => $headers,
        ];

        // If a URL can be composed, do so; otherwise rely on webhook.url in config
        if ($baseUrl !== '' && $apiVersion !== '' && $object !== '') {
            $out['url'] = rtrim($baseUrl, '/') . '/services/data/' . $apiVersion . '/sobjects/' . $object;
        }

        return $out;
    }
}
