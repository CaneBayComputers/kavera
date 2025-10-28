<?php

namespace App\FormAdapters;

use App\FormAdapters\Contracts\FormAdapter;
use Illuminate\Support\Arr;

class DefaultEnvelopeAdapter implements FormAdapter
{
    public function transform(string $formName, string $submissionId, array $fields, array $context = []): array
    {
        $flatten = (bool) ($context['options']['flatten'] ?? true);

        return [
            'event' => 'form.submitted',
            'id' => 'evt_' . ($context['event_id'] ?? ''),
            'created_at' => (string) ($context['created_at'] ?? ''),
            'data' => [
                'form_id' => $formName,
                'submission_id' => 'sub_' . $submissionId,
                'fields' => $flatten ? Arr::dot($fields) : $fields,
            ],
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
