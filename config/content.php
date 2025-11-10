<?php

return [
    // Allowed path regex for content routes. Used by VerifyContentAccess and for
    // cleansing imported slugs. Must be a full-string match pattern.
    // Default allows letters, numbers, forward slashes, and dashes.
    'allowed_path_regex' => env('CONTENT_ALLOWED_PATH_REGEX', '/^[a-zA-Z0-9\/-]+$/'),
];

