<?php

return [

    // Throttling
    'ip_attempt_timeframe_seconds' => 15,

    'ip_max_attempts_per_timeframe' => 3,

    // Google ReCaptcha settings
    'recaptcha' => [

        // Required for production
        'site_key' => env('RECAPTCHA_SITE_KEY'),

        // Required for production
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),

        'url' => 'https://www.google.com/recaptcha/api/siteverify',

        // Tested, seems to be good default
        'threshold' => env('RECAPTCHA_THRESHOLD', 0.7),
    ],

    'forms' => [

        'contact' => [

            'subject' => env('APP_NAME', 'Laravel') . ' Contact Form',

            'rules' => [
                'email' => 'required_without:phone|email|max:100',
                'phone' => 'required_without:email|max:100',
                'message' => 'required|string|max:2000',
                'first_name' => 'required_without:company|string|min:2|max:100',
                'last_name' => 'nullable|string|min:2|max:100',
                'company' => 'required_without:first_name|string|min:2|max:100',
            ],

            // If unset will return to form page
            'success_page' => env('CONTACT_FORM_SUCCESS_PAGE'),

            // Mail settings (grouped)
            'mail' => [
                'to' => env('CONTACT_FORM_MAIL_TO'),
                'subject' => env('APP_NAME', 'Laravel') . ' Contact Form',
                // Has access to $formData array
                'view' => 'emails.contact',
                // Optional plain-text view (used to build multipart/alternative emails)
                'text_view' => 'emails.contact_text',
                // Values: view | text
                'type' => 'view',
            ],

            // Optional webhook(s) to receive form submissions
            // Backward compatible: if only CONTACT_FORM_WEBHOOK_URL is set, it will be used automatically.
            'webhooks' => [
                [
                    'url' => env('CONTACT_FORM_WEBHOOK_URL'),
                    'adapter' => App\FormAdapters\DefaultEnvelopeAdapter::class,
                    // 'method' => 'POST',
                    // 'headers' => ['Authorization' => 'Bearer ...'],
                    // 'options' => ['flatten' => true],
                ],

                // Zapier webhook integration (example mapping and static values)
                // [
                //     'adapter' => App\FormAdapters\ZapierAdapter::class,
                //     'options' => [
                //         // Endpoint URL for the Zap
                //         'url' => env('ZAPIER_CONTACT_WEBHOOK_URL', 'https://hooks.zapier.com/hooks/catch/XXXX/YYYY/'),
                //         // Map from contact.rules fields (source) to Zapier payload keys (dest)
                //         'field_map' => [
                //             'firstName' => 'first_name',
                //             'lastName'  => 'last_name',
                //             'company'   => 'company',
                //             'email'     => 'email',
                //             'phone'     => 'phone',
                //             'message'   => 'message',
                //         ],
                //         // Optional: constants to always include
                //         'static' => [
                //             'source' => 'website',
                //             'form'   => 'contact',
                //         ],
                //         // Optional: include minimal context
                //         // 'include_context' => true,
                //     ],
                // ],

                // Mailchimp webhook integration (upsert + optional tags)
                // [
                //     'adapter' => App\FormAdapters\MailchimpAdapter::class,
                //     'options' => [
                //         'api_key'     => env('MAILCHIMP_API_KEY'),
                //         'audience_id' => env('MAILCHIMP_AUDIENCE_ID'),
                //         'status'      => env('MAILCHIMP_STATUS', 'subscribed'),
                //         // Optional: add tags after upsert
                //         // 'tags'        => env('MAILCHIMP_CONTACT_TAGS'), // comma-separated
                //
                //         // Explicit field mapping (no guessing). Map from contact.rules fields.
                //         'field_map' => [
                //             'FNAME'   => 'first_name',
                //             'LNAME'   => 'last_name',
                //             'COMPANY' => 'company',
                //             'PHONE'   => 'phone',
                //             'ADDRESS' => [
                //                 'addr1'   => 'address1',
                //                 'addr2'   => 'address2',
                //                 'city'    => 'city',
                //                 'state'   => 'state',
                //                 'zip'     => 'zip',
                //                 'country' => 'country',
                //             ],
                //         ],
                //     ],
                // ],

                // Salesforce webhook integration (create Lead or any sObject)
                // [
                //     'adapter' => App\\FormAdapters\\SalesforceAdapter::class,
                //     'options' => [
                //         'base_url'    => env('SALESFORCE_BASE_URL'), // e.g. https://myinstance.my.salesforce.com
                //         'api_version' => env('SALESFORCE_API_VERSION', 'v59.0'),
                //         'object'      => env('SALESFORCE_OBJECT', 'Lead'),
                //         // 'access_token' => env('SALESFORCE_ACCESS_TOKEN'), // or pass headers via webhook
                //
                //         // Explicit field mapping (Salesforce field => form field key)
                //         'field_map' => [
                //             'FirstName'   => 'first_name',
                //             'LastName'    => 'last_name',
                //             'Company'     => 'company',
                //             'Email'       => 'email',
                //             'Phone'       => 'phone',
                //             'Description' => 'message',
                //         ],
                //
                //         // Provide default values for required fields if form is missing them
                //         'defaults' => [
                //             'Company' => env('SALESFORCE_DEFAULT_COMPANY', 'Unknown'),
                //         ],
                //     ],
                // ],
            ],
        ],
    ],
];
