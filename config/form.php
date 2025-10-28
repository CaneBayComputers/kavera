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
                'name' => 'required_without:company|string|min:2|max:100',
                'company' => 'required_without:name|string|min:2|max:100',
            ],

            // If unset will return to form page
            'success_page' => env('CONTACT_FORM_SUCCESS_PAGE'),

            // Email address to send form data
            'mail_to' => env('CONTACT_FORM_MAIL_TO'),

            // Has access to $formData array
            'view' => 'emails.contact',
            // Optional plain-text view (used to build multipart/alternative emails)
            'text_view' => 'emails.contact_text',

            // Values: view | text
            'type' => 'view',

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

                // Zapier webook integration
                // [
                //     'url' => env('ZAPIER_CONTACT_WEBHOOK_URL', 'https://hooks.zapier.com/hooks/catch/XXXX/YYYY/'),
                //     'adapter' => App\FormAdapters\ZapierAdapter::class,
                // ],

                // Mailchimp webhook integration
                // [
                //     'adapter' => App\FormAdapters\MailchimpAdapter::class,
                //     'options' => [
                //         'api_key' => env('MAILCHIMP_API_KEY'),
                //         'dc'      => env('MAILCHIMP_DC'),
                //         'list_id' => env('MAILCHIMP_LIST_ID'),
                //         'status'  => env('MAILCHIMP_STATUS', 'subscribed'),
                //     ],
                // ],
            ],
        ],

        'signup' => [

            'subject' => env('APP_NAME', 'Laravel') . ' Newsletter Signup',

            'rules' => [
                'email' => 'required|email|max:100',
            ],

            // If unset will return to current page
            'success_page' => env('SIGNUP_FORM_SUCCESS_PAGE'),

            // Email address to send signup notifications
            'mail_to' => env('SIGNUP_FORM_MAIL_TO'),

            // Email templates (have access to $formData)
            'view' => 'emails.signup',
            'text_view' => 'emails.signup_text',

            // Values: view | text
            'type' => 'view',

            // Optional webhooks
            'webhooks' => [
                [
                    'url' => env('SIGNUP_FORM_WEBHOOK_URL'),
                    'adapter' => App\FormAdapters\DefaultEnvelopeAdapter::class,
                ],
            ],
        ],
    ],
];
