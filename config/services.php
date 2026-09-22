<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'eventbrite' => [
        // Use the Private token (Personal OAuth token). Fallback to legacy API key var if present.
        'private_token' => env('EVENTBRITE_PRIVATE_TOKEN', env('EVENTBRITE_API_KEY')),
        'organization_id' => env('EVENTBRITE_ORGANIZATION_ID'),
        // Comma-separated statuses, e.g., "live,started"; API supports "live", "started", "ended", etc.
        'status' => env('EVENTBRITE_STATUS', 'live,started'),
        // Eventbrite docs: "current_future" yields ongoing and upcoming
        'time_filter' => env('EVENTBRITE_TIME_FILTER', 'current_future'),
        'page_size' => env('EVENTBRITE_PAGE_SIZE', 10),
        'cache_key' => env('EVENTBRITE_CACHE_KEY', 'eventbrite.events'),
        // Expand related resources for richer display; include image/logo and venue.
        // Event times and URL are part of the base event fields.
        'expand' => env('EVENTBRITE_EXPAND', 'venue,logo,organizer'),
    ],

    'flickr' => [
        // Public read access only needs the API key; FLICKR_USER_ID is an NSID (12345678@N01) or username.
        'api_key' => env('FLICKR_API_KEY'),
        'user_id' => env('FLICKR_USER_ID'),
        'max_photos' => (int) env('FLICKR_MAX_PHOTOS', 500),
        // When true and the scheduler is running, app:flickr-sync runs every 15 minutes.
        'auto_sync' => filter_var(env('FLICKR_AUTO_SYNC', true), FILTER_VALIDATE_BOOL),
    ],

    'blogger' => [
        'api_key' => env('BLOGGER_API_KEY'),
        'blog_id' => env('BLOGGER_BLOG_ID'),
        'base_url' => env('BLOGGER_BASE_URL', 'https://www.googleapis.com/blogger/v3'),
        'timeout' => env('BLOGGER_TIMEOUT', 8),
        'cache_key' => env('BLOGGER_CACHE_KEY', 'blogger.posts'),
        'max_results' => env('BLOGGER_MAX_RESULTS', 50),
        // Base content path for future on-disk imports (e.g., "blog" → /content/blog/...)
        'content_base' => env('BLOGGER_CONTENT_BASE', 'blog'),
        // Subfolder in storage/app for raw dumps and import artifacts
        'storage_dir' => env('BLOGGER_STORAGE_DIR', 'blogger'),
        // Layout template and section used for imported post files
        'post_layout' => env('BLOGGER_POST_LAYOUT', 'templates.blog'),
        'post_section' => env('BLOGGER_POST_SECTION', 'blog_content'),
        // URL segment name for label/category listing under the content base
        'label_segment' => env('BLOGGER_LABEL_SEGMENT', 'labels'),
    ],

];
