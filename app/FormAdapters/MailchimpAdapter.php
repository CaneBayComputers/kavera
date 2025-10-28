<?php

namespace App\FormAdapters;

use App\FormAdapters\Contracts\FormAdapter;

class MailchimpAdapter implements FormAdapter
{
    public function transform(string $formName, string $submissionId, array $fields, array $context = []): array
    {
        // Parameters are part of the interface; unused for Mailchimp payload shape.
        unset($formName, $submissionId);
        $opts   = (array) ($context['options'] ?? []);
        $status = (string) ($opts['status'] ?? env('MAILCHIMP_STATUS', 'subscribed'));

        $email = strtolower(trim((string) ($fields['email'] ?? ($fields['email_address'] ?? ''))));

        // Basic merge fields from common contact form keys
        $mergeFields = [];
        if (!empty($fields['name'])) {
            $mergeFields['FNAME'] = (string) $fields['name'];
        }
        if (!empty($fields['company'])) {
            $mergeFields['COMPANY'] = (string) $fields['company'];
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

        $apiKey     = (string) ($opts['api_key'] ?? env('MAILCHIMP_API_KEY', ''));
        $dataCenter = (string) ($opts['dc'] ?? $opts['data_center'] ?? env('MAILCHIMP_DC', ''));
        $listId = (string) ($opts['list_id'] ?? env('MAILCHIMP_LIST_ID', ''));

        $headers = [];
        if ($apiKey !== '') {
            $headers['Authorization'] = 'Basic ' . base64_encode('anystring:' . $apiKey);
        }

        $method = strtoupper((string) ($opts['method'] ?? 'PUT'));

        // If URL not supplied in webhook, compute a member upsert endpoint when possible
        $url = null;
        if (!empty($dataCenter) && !empty($listId)) {
            $email = '';
            if (!empty($context['fields']) && is_array($context['fields'])) {
                $email = strtolower(trim((string) ($context['fields']['email'] ?? ($context['fields']['email_address'] ?? ''))));
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

        return $out;
    }
}
