<?php

namespace App\FormAdapters\Contracts;

interface FormAdapter
{
    /**
     * Transform raw form fields into a webhook payload.
     *
     * @param string $formName
     * @param string $submissionId
     * @param array<string, mixed> $fields  Raw user-submitted fields
     * @param array<string, mixed> $context Extra context (app/meta/options)
     * @return array<string, mixed>
     */
    public function transform(string $formName, string $submissionId, array $fields, array $context = []): array;

    /**
     * Optional per-adapter request options (e.g., headers, method overrides).
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed> e.g., ['headers' => [...], 'method' => 'POST']
     */
    public function requestOptions(array $context = []): array;
}
