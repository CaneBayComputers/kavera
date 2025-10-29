<?php

namespace App\FormAdapters;

use App\FormAdapters\Contracts\FormAdapter;

class MailchimpTagsAdapter implements FormAdapter
{
    public function transform(string $formName, string $submissionId, array $fields, array $context = []): array
    {
        unset($formName, $submissionId, $fields);

        $opts = (array) ($context['options'] ?? []);
        $tags = $opts['tags'] ?? [];
        $status = (string) ($opts['tag_status'] ?? 'active'); // 'active' to add, 'inactive' to remove

        // Support tags as array of names or array of {name, status}
        $out = [];
        foreach ((array) $tags as $tag) {
            if (is_array($tag)) {
                $name = (string) ($tag['name'] ?? '');
                $tagStatus = (string) ($tag['status'] ?? $status);
                if ($name !== '') {
                    $out[] = ['name' => $name, 'status' => $tagStatus];
                }
            } else {
                $name = (string) $tag;
                if ($name !== '') {
                    $out[] = ['name' => $name, 'status' => $status];
                }
            }
        }

        return ['tags' => $out];
    }

    public function requestOptions(array $context = []): array
    {
        $opts = (array) ($context['options'] ?? []);

        $apiKey = (string) ($opts['api_key'] ?? env('MAILCHIMP_API_KEY', ''));
        $listId = (string) (
            $opts['audience_id']
                ?? $opts['list_id']
                ?? env('MAILCHIMP_AUDIENCE_ID')
                ?? env('MAILCHIMP_LIST_ID')
                ?? ''
        );

        $headers = [];
        if ($apiKey !== '') {
            $headers['Authorization'] = 'Basic ' . base64_encode('anystring:' . $apiKey);
        }

        // Compute data center from API key suffix
        $dataCenter = '';
        if ($apiKey !== '' && str_contains($apiKey, '-')) {
            $parts = explode('-', $apiKey);
            $dataCenter = strtolower(end($parts));
        }

        $url = null;
        // Build member tags endpoint when we have listId and email
        if ($dataCenter !== '' && $listId !== '') {
            $email = '';
            if (!empty($context['fields']) && is_array($context['fields'])) {
                $email = strtolower(trim((string) ($context['fields']['email'] ?? ($context['fields']['email_address'] ?? ''))));
            }
            if ($email !== '') {
                $subscriberHash = md5($email);
                $url = sprintf('https://%s.api.mailchimp.com/3.0/lists/%s/members/%s/tags', $dataCenter, $listId, $subscriberHash);
            }
        }

        return [
            'method'  => 'POST',
            'headers' => $headers,
            'url'     => $url,
        ];
    }
}
