<?php

namespace App\FormAdapters;

use App\FormAdapters\Contracts\FormAdapter;

class ZapierAdapter implements FormAdapter
{
    public function transform(string $formName, string $submissionId, array $fields, array $context = []): array
    {
        // Send raw fields; optionally include minimal context
        $includeContext = (bool) (($context['options']['include_context'] ?? false));

        if (! $includeContext) {
            return $fields;
        }

        return [
            'form_id' => $formName,
            'submission_id' => $submissionId,
            'fields' => $fields,
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
