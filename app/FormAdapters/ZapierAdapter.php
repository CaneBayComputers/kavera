<?php

namespace App\FormAdapters;

use App\FormAdapters\Contracts\FormAdapter;
use Illuminate\Support\Arr;

class ZapierAdapter implements FormAdapter
{
    public function transform(string $formName, string $submissionId, array $fields, array $context = []): array
    {
        $opts = (array) ($context['options'] ?? []);

        // Optional: explicit field mapping for Zapier (dest => source key path)
        $mapped = [];
        $map = (array) ($opts['field_map'] ?? []);
        if (!empty($map)) {
            foreach ($map as $dest => $src) {
                if (!is_string($dest) || $dest === '' || !is_string($src) || $src === '') {
                    continue;
                }
                $val = Arr::get($fields, $src);
                if ($val !== null) {
                    $mapped[$dest] = $val;
                }
            }
        }

        // Base payload is either mapped or raw fields
        $payload = !empty($mapped) ? $mapped : $fields;

        // Optional: static key/values to merge
        if (!empty($opts['static']) && is_array($opts['static'])) {
            foreach ($opts['static'] as $k => $v) {
                if (is_string($k) && $k !== '') {
                    $payload[$k] = $v;
                }
            }
        }

        // Optional: include minimal context
        if (!empty($opts['include_context'])) {
            $payload['_context'] = [
                'form_id' => $formName,
                'submission_id' => $submissionId,
            ];
        }

        return $payload;
    }

    public function requestOptions(array $context = []): array
    {
        $opts = (array) ($context['options'] ?? []);
        $out = [
            'method' => 'POST',
            'headers' => (array) ($opts['headers'] ?? []),
        ];
        if (!empty($opts['url'])) {
            $out['url'] = (string) $opts['url'];
        }
        return $out;
    }
}
