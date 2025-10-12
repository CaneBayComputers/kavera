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

            // Optional webhook endpoint to receive form submissions
            'webhook_url' => env('CONTACT_FORM_WEBHOOK_URL'),
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

            // Optional webhook
            'webhook_url' => env('SIGNUP_FORM_WEBHOOK_URL'),
        ],
    ],
];
