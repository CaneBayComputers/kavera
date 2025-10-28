<?php

namespace App\FormAdapters;

use App\FormAdapters\Contracts\FormAdapter;

class PassthroughAdapter implements FormAdapter
{
    public function transform(string $formName, string $submissionId, array $fields, array $context = []): array
    {
        // Send fields exactly as provided (no envelope), with optional merge of context keys.
        $includeContext = (bool) ($context['options']['include_context'] ?? false);

        if (! $includeContext) {
            return $fields;
        }

        return [
            'form_id' => $formName,
            'submission_id' => $submissionId,
            'fields' => $fields,
            'meta' => [
                'app' => (string) ($context['app'] ?? ''),
                'version' => (string) ($context['version'] ?? ''),
            ],
        ];
    }

    public function requestOptions(array $context = []): array
    {
        return [
            'method' => 'POST',
            'headers' => (array) ($context['options']['headers'] ?? []),
        ];
    }
}
