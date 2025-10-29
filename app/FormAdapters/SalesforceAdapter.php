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

        // If no explicit map provided, use a sensible default for common Lead fields
        if (empty($map)) {
            $map = [
                'FirstName'  => 'first_name',
                'LastName'   => 'last_name',
                'Company'    => 'company',
                'Email'      => 'email',
                'Phone'      => 'phone',
                'Description' => 'message',
            ];
        }

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
            // Backward-compat: if single 'name' field exists, use it for LastName
            if (isset($fields['name']) && $fields['name'] !== '') {
                $payload['LastName'] = (string) $fields['name'];
            } else {
                $payload['LastName'] = 'Unknown';
            }
        }
        if (!isset($payload['Company']) || $payload['Company'] === null || $payload['Company'] === '') {
            $payload['Company'] = env('SALESFORCE_DEFAULT_COMPANY', 'Unknown');
        }

        return $payload;
    }

    public function requestOptions(array $context = []): array
    {
        $opts = (array) ($context['options'] ?? []);

        // Prefer configured base URL + API version + object to compute the endpoint
        $baseUrl    = (string) ($opts['base_url'] ?? env('SALESFORCE_BASE_URL', ''));
        $apiVersion = (string) ($opts['api_version'] ?? env('SALESFORCE_API_VERSION', 'v59.0'));
        $object     = (string) ($opts['object'] ?? env('SALESFORCE_OBJECT', 'Lead'));
        $token      = (string) ($opts['access_token'] ?? env('SALESFORCE_ACCESS_TOKEN', ''));

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
