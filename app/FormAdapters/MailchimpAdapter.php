<?php

namespace App\FormAdapters;

use App\FormAdapters\Contracts\FormAdapter;
use Illuminate\Support\Arr;

class MailchimpAdapter implements FormAdapter
{
    public function transform(string $formName, string $submissionId, array $fields, array $context = []): array
    {
        // Parameters are part of the interface; unused for Mailchimp payload shape.
        unset($formName, $submissionId);
        $opts   = (array) ($context['options'] ?? []);
        $status = (string) ($opts['status'] ?? env('MAILCHIMP_STATUS', 'subscribed'));

        // Resolve email strictly via field_map['EMAIL'] when provided
        $email = '';
        if (isset($opts['field_map']) && is_array($opts['field_map'])) {
            $emailSrc = $opts['field_map']['EMAIL'] ?? null;
            if (is_string($emailSrc) && $emailSrc !== '') {
                $emailVal = Arr::get($fields, $emailSrc);
                if ($emailVal !== null && $emailVal !== '') {
                    $email = strtolower(trim((string) $emailVal));
                }
            }
        }

        // Merge fields mapping (no guessing; requires explicit field_map)
        $mergeFields = [];
        $fieldMap = $opts['field_map'] ?? null;
        if (is_array($fieldMap) && !empty($fieldMap)) {
            foreach ($fieldMap as $tag => $source) {
                $tag = strtoupper((string) $tag);
                if ($tag === 'EMAIL') {
                    // EMAIL is handled separately for API payload address and URL computation
                    continue;
                }
                if ($tag === '') {
                    continue;
                }
                if (is_array($source)) {
                    // Address-type merge field
                    $addr = [];
                    foreach ($source as $k => $formKey) {
                        $val = is_string($formKey) ? Arr::get($fields, $formKey) : null;
                        if ($val !== null && $val !== '') {
                            $addr[$k] = (string) $val;
                        }
                    }
                    if (!empty($addr)) {
                        $mergeFields[$tag] = (object) $addr;
                    }
                } elseif (is_string($source) && $source !== '') {
                    $val = Arr::get($fields, $source);
                    if ($val !== null && $val !== '') {
                        $mergeFields[$tag] = (string) $val;
                    }
                }
            }
        }

        // Optional interest mapping via options: ['interests' => ['abc123' => true]]
        $interests = (array) ($opts['interests'] ?? []);

        $payload = [
            'email_address'   => $email,
            'status_if_new'   => $status,
            'status'          => $status,
            'merge_fields'    => (object) $mergeFields,
        ];

        if (!empty($interests)) {
            $payload['interests'] = (object) $interests;
        }

        return $payload;
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

        $method = strtoupper((string) ($opts['method'] ?? 'PUT'));

        // If URL not supplied in webhook, compute a member upsert endpoint when possible
        $url = null;
        // Derive data center from API key suffix (e.g., abcd-us21 => us21)
        $dataCenter = '';
        if ($apiKey !== '' && str_contains($apiKey, '-')) {
            $parts = explode('-', $apiKey);
            $dataCenter = strtolower(end($parts));
        }

        if (!empty($dataCenter) && !empty($listId)) {
            $email = '';
            // Resolve email using field_map['EMAIL'] strictly
            $map = (array) ($opts['field_map'] ?? []);
            if (!empty($map['EMAIL']) && is_string($map['EMAIL'])) {
                $emailVal = Arr::get((array) ($context['fields'] ?? []), $map['EMAIL']);
                if ($emailVal !== null && $emailVal !== '') {
                    $email = strtolower(trim((string) $emailVal));
                }
            }
            if ($email !== '') {
                $subscriberHash = md5($email);
                $url = sprintf('https://%s.api.mailchimp.com/3.0/lists/%s/members/%s', $dataCenter, $listId, $subscriberHash);
                $method = 'PUT'; // upsert
            }
        }

        $out = [
            'method'  => $method,
            'headers' => $headers,
        ];

        if ($url) {
            $out['url'] = $url;
        }

        // Optional follow-up: add tags if provided (array) or via env per form
        $tags = $opts['tags'] ?? [];
        if ((empty($tags) || !is_array($tags)) && !empty($context['form_name'])) {
            $formKey = strtoupper(preg_replace('~[^A-Za-z0-9]+~', '_', (string) $context['form_name']));
            $envKey = 'MAILCHIMP_' . $formKey . '_TAGS';
            $envVal = env($envKey, '');
            if (is_string($envVal) && $envVal !== '') {
                $tags = array_values(array_filter(array_map(static function ($s) {
                    return trim((string) $s);
                }, explode(',', $envVal)), static function ($s) {
                    return $s !== '';
                }));
            }
        }
        if (!empty($tags) && is_array($tags) && $dataCenter !== '' && $listId !== '') {
            $email = '';
            if (!empty($context['fields']) && is_array($context['fields'])) {
                $email = strtolower(trim((string) ($context['fields']['email'] ?? ($context['fields']['email_address'] ?? ''))));
            }
            if ($email !== '') {
                $subscriberHash = md5($email);
                $tagsUrl = sprintf('https://%s.api.mailchimp.com/3.0/lists/%s/members/%s/tags', $dataCenter, $listId, $subscriberHash);
                $tagsArray = [];
                foreach ((array) $tags as $t) {
                    $name = (string) $t;
                    if ($name !== '') {
                        $tagsArray[] = ['name' => $name, 'status' => 'active'];
                    }
                }
                if (!empty($tagsArray)) {
                    $out['followups'][] = [
                        'method' => 'POST',
                        'url' => $tagsUrl,
                        'headers' => $headers,
                        'json' => ['tags' => $tagsArray],
                    ];
                }
            }
        }

        return $out;
    }
}
