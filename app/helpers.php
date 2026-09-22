<?php

function email_table(array $input, array $opts = []): string
{
    $defaults = [
        'table_width'   => '100%',
        'border_color'  => '#e5e7eb',
        'header_bg'     => '#f9fafb',
        'font_family'   => 'system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif',
        'label_width'   => '28%',
        'value_width'   => '72%',
    ];

    $opts = array_merge($defaults, $opts);

    $escape = static function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $normalizeKey = static function ($key) {
        $key = trim((string) $key);
        $key = str_replace(['_', '-'], ' ', $key);
        $key = preg_replace('/\s+/', ' ', $key);
        return ucwords($key);
    };

    $formatValue = static function ($value) use ($escape, $normalizeKey): string {
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        if ($value === null) {
            return '—';
        }
        if (is_scalar($value)) {
            return $escape((string) $value);
        }

        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                $value = $value->toArray();
            } else {
                $value = json_decode(json_encode($value), true);
            }
        }

        // Arrays/objects: render one level deep as Label: value lines
        if (is_array($value)) {
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);

            if (! $isAssoc) {
                $joined = array_map(static function ($v) use ($escape) {
                    return is_scalar($v) || $v === null ? $escape((string) $v) : $escape(json_encode($v));
                }, $value);

                return implode(', ', $joined);
            }

            $lines = [];
            foreach ($value as $k => $v) {
                if (is_bool($v)) {
                    $v = $v ? 'TRUE' : 'FALSE';
                } elseif ($v === null) {
                    $v = '—';
                } elseif (! is_scalar($v)) {
                    $v = json_encode($v, JSON_UNESCAPED_SLASHES);
                }

                $lines[] = '<strong>' . $escape($normalizeKey($k)) . ':</strong> ' . $escape((string) $v);
            }

            return implode('<br>', $lines);
        }

        // Fallback
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $json = $json === false ? '' : $json;
        return '<pre style="margin:0; white-space:pre-wrap; word-break:break-word;">' . $escape($json) . '</pre>';
    };

    $rows = '';

    foreach ($input as $key => $value) {
        $label = $normalizeKey($key);
        $display = $formatValue($value);

        $rows .= '<tr>' .
            '<th style="text-align:left;vertical-align:top;padding:10px;border:1px solid ' . $opts['border_color'] . ';background:' . $opts['header_bg'] . ';width:' . $opts['label_width'] . ';">' . $escape($label) . '</th>' .
            '<td style="vertical-align:top;padding:10px;border:1px solid ' . $opts['border_color'] . ';width:' . $opts['value_width'] . ';">' . $display . '</td>' .
            '</tr>';
    }

    $table = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;width:' . $opts['table_width'] . ';font-family:' . $opts['font_family'] . ';font-size:14px;line-height:1.5;color:#111827;">'
        . '<tbody>' . $rows . '</tbody>'
        . '</table>';

    return $table;
}

function is_dev()
{
    return app()->environment('local');
}

function is_prod()
{
    return ! is_dev();
}

function cdn($url = '')
{
    $original_url = $url;
    $path = ltrim($url, '/');

    $bucket = env('AWS_BUCKET');

    if (empty($bucket)) {
        return $original_url;
    }

    $region = env('AWS_DEFAULT_REGION', 'us-east-1');
    $use_path_style = env('AWS_USE_PATH_STYLE_ENDPOINT', false);

    if ($use_path_style) {
        $base_url = sprintf('https://s3.%s.amazonaws.com/%s', $region, $bucket);
    } else {
        $base_url = sprintf('https://%s.s3.%s.amazonaws.com', $bucket, $region);
    }

    $base_url = rtrim($base_url, '/');

    if ($path === '') {
        return $base_url;
    }

    return $base_url . '/' . $path;
}

function scripts($url = '', $cdn = true)
{
    $url = '/scripts/' . $url;

    return $cdn ? cdn($url) : $url;
}

function styles($url = '', $cdn = true)
{
    $url = '/styles/' . $url;

    return $cdn ? cdn($url) : $url;
}

function css($url = '', $cdn = true)
{
    $url = '/css/' . $url;

    return $cdn ? cdn($url) : $url;
}

function js($url = '', $cdn = true)
{
    $url = '/js/' . $url;

    return $cdn ? cdn($url) : $url;
}

function images($url = '', $cdn = true)
{
    $url = '/images/' . $url;

    return $cdn ? cdn($url) : $url;
}

function img($url = '', $cdn = true)
{
    $url = '/img/' . $url;

    return $cdn ? cdn($url) : $url;
}

function fonts($url = '', $cdn = true)
{
    $url = '/fonts/' . $url;

    return $cdn ? cdn($url) : $url;
}

function _c($str, $default = null)
{
    return config($str, $default);
}

function _l(...$params)
{
    if (function_exists('is_prod') && is_prod()) {
        return null; // do nothing in production
    }

    $out = '';

    $opts = [
        'level'      => 'debug',
        'channel'    => null,
        'with_trace' => false,
        'max_len'    => 20000,
        'return'     => false,
    ];

    // Allow trailing options array under key _opts
    if (! empty($params)) {
        $last = end($params);

        if (is_array($last) && array_key_exists('_opts', $last)) {
            $opts = array_merge($opts, $last['_opts']);

            array_pop($params);
        }
    }

    // Caller context
    $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
    $caller = $bt[1] ?? $bt[0] ?? [];
    $caller_file = $caller['file'] ?? 'unknown';
    $caller_line = $caller['line'] ?? 0;
    $caller_func = $caller['function'] ?? '';

    $header = sprintf(
        "[time:%s] [mem:%s] [caller:%s:%d%s]",
        date('Y-m-d H:i:s'),
        number_format(memory_get_usage(true) / 1048576, 2) . 'MB',
        $caller_file,
        $caller_line,
        $caller_func ? ' ' . $caller_func . '()' : ''
    );

    foreach ($params as $param) {
        $var_type = gettype($param);

        if ($var_type === 'object') {
            $var_type = get_class($param);
        }

        if ($param instanceof \Throwable) {
            $param = sprintf(
                "%s: %s\n%s",
                get_class($param),
                $param->getMessage(),
                $opts['with_trace'] ? $param->getTraceAsString() : ''
            );
        } elseif (is_object($param)) {
            if (method_exists($param, 'toArray')) {
                $param = $param->toArray();
            } else {
                $param = get_object_vars($param);
            }
        }

        if (is_array($param)) {
            $param = print_r($param, true);
        } elseif (is_callable($param)) {
            $param = '(function)';
        } elseif (is_bool($param)) {
            $param = $param ? 'TRUE' : 'FALSE';
        }

        if (is_string($param) && mb_strlen($param) > $opts['max_len']) {
            $truncated = mb_strlen($param) - $opts['max_len'];

            $param = mb_substr($param, 0, $opts['max_len']) . "\n… [truncated {$truncated} chars]";
        }

        $out .= "\n\n({$var_type}):\n{$param}\n\n---";
    }

    $out = $header . $out . "\n";

    if ($opts['return']) {
        return $out;
    }

    if ($opts['channel']) {
        logger()->channel($opts['channel'])->log($opts['level'], $out);
    } else {
        logger()->log($opts['level'], $out);
    }
}

if (!function_exists('eventbrite_enabled')) {
    function eventbrite_enabled(): bool
    {
        $token = config('services.eventbrite.private_token');
        $orgId = config('services.eventbrite.organization_id');
        return !empty($token) && !empty($orgId);
    }
}

if (!function_exists('eventbrite_fetch_events')) {
    /**
     * Read pre-fetched Eventbrite events from cache (populated by artisan command).
     * Always returns list ordered from soonest to latest by start time.
     *
     * @return array<int, array<string, mixed>>
     */
    function eventbrite_fetch_events(): array
    {
        $orgId = (string) config('services.eventbrite.organization_id');
        $privateToken = (string) config('services.eventbrite.private_token');
        if ($orgId === '' || $privateToken === '') {
            return [];
        }

        $cacheKey = (string) config('services.eventbrite.cache_key', 'eventbrite.events');
        $events = \Illuminate\Support\Facades\Cache::get($cacheKey, []);
        if (!is_array($events)) {
            return [];
        }

        // Ensure soonest → latest ordering by start time
        usort($events, static function ($a, $b) {
            $aTime = strtotime($a['start']['utc'] ?? $a['start']['local'] ?? '');
            $bTime = strtotime($b['start']['utc'] ?? $b['start']['local'] ?? '');
            return $aTime <=> $bTime;
        });

        return $events;
    }
}

if (!function_exists('eventbrite_image_url')) {
    /**
     * Extracts an image URL from an Eventbrite event object.
     */
    function eventbrite_image_url(array $event): ?string
    {
        // Eventbrite responds with 'logo' => ['url' => '...', 'original' => ['url' => '...']]
        $logo = $event['logo'] ?? null;
        if (is_array($logo)) {
            if (!empty($logo['original']['url'])) {
                return (string) $logo['original']['url'];
            }
            if (!empty($logo['url'])) {
                return (string) $logo['url'];
            }
        }
        return null;
    }
}

if (!function_exists('blogger_enabled')) {
    function blogger_enabled(): bool
    {
        $apiKey = config('services.blogger.api_key');
        $blogId = config('services.blogger.blog_id');
        return !empty($apiKey) && !empty($blogId);
    }
}

if (!function_exists('blogger_fetch_posts')) {
    /**
     * Read pre-fetched Blogger posts from cache (populated by artisan command).
     * Returns an array of normalized posts (see BloggerService::normalizeItem).
     *
     * @return array<int, array<string, mixed>>
     */
    function blogger_fetch_posts(): array
    {
        $cacheKey = (string) config('services.blogger.cache_key', 'blogger.posts');
        $posts = \Illuminate\Support\Facades\Cache::get($cacheKey, []);
        return is_array($posts) ? $posts : [];
    }
}

if (!function_exists('blogger_base')) {
    function blogger_base(): string
    {
        return trim((string) config('services.blogger.content_base', 'blog'), '/');
    }
}

if (!function_exists('blogger_label_segment')) {
    function blogger_label_segment(): string
    {
        return trim((string) config('services.blogger.label_segment', 'labels'), '/');
    }
}

if (!function_exists('blogger_index')) {
    /**
     * The whole Blogger index document written by app:blogger-import:
     * posts (id => preview), order (ids newest first), labels (slug => name/count/ids),
     * archives ('YYYY-MM' => ids, newest month first) and recent (top 10 previews).
     *
     * @return array{posts:array<string,array<string,mixed>>,order:list<string>,labels:array<string,array{name:string,count:int,ids:list<string>}>,archives:array<string,list<string>>,recent:list<array<string,mixed>>}
     */
    function blogger_index(): array
    {
        $index = \Illuminate\Support\Facades\Cache::get('blogger:index', []);
        $index = is_array($index) ? $index : [];
        return $index + ['posts' => [], 'order' => [], 'labels' => [], 'archives' => [], 'recent' => []];
    }
}

if (!function_exists('blogger_recent')) {
    /**
     * Returns list of recent post previews (id, title, path, published_at, summary), newest first.
     *
     * @return array<int, array<string,mixed>>
     */
    function blogger_recent(int $limit = 10): array
    {
        $index = blogger_index();
        if ($limit <= count($index['recent'])) {
            return array_slice($index['recent'], 0, $limit);
        }
        return blogger_previews_by_ids(array_slice($index['order'], 0, max(0, $limit)));
    }
}

if (!function_exists('blogger_previews_by_ids')) {
    /**
     * Resolve preview documents by Blogger post IDs, keeping the given order.
     *
     * @param array<int, string> $ids
     * @return array<int, array<string,mixed>>
     */
    function blogger_previews_by_ids(array $ids): array
    {
        $posts = blogger_index()['posts'];
        $out = [];
        foreach ($ids as $id) {
            if (isset($posts[(string) $id])) {
                $out[] = $posts[(string) $id];
            }
        }
        return $out;
    }
}

if (!function_exists('blogger_labels')) {
    /**
     * Returns an associative array of labels keyed by slug with name and count, sorted by name.
     *
     * @return array<string, array{name:string,count:int}>
     */
    function blogger_labels(): array
    {
        $out = [];
        foreach (blogger_index()['labels'] as $slug => $label) {
            $out[(string) $slug] = [
                'name' => (string) ($label['name'] ?? $slug),
                'count' => (int) ($label['count'] ?? 0),
            ];
        }
        uasort($out, static function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        return $out;
    }
}

if (!function_exists('blogger_label_ids')) {
    /**
     * Post IDs carrying a label slug, newest first.
     *
     * @return array<int, string>
     */
    function blogger_label_ids(string $slug): array
    {
        return array_values((array) (blogger_index()['labels'][$slug]['ids'] ?? []));
    }
}

if (!function_exists('blogger_label_name')) {
    function blogger_label_name(string $slug): ?string
    {
        $name = blogger_index()['labels'][$slug]['name'] ?? null;
        return $name !== null ? (string) $name : null;
    }
}

if (!function_exists('blogger_archives')) {
    /**
     * Returns array of archive buckets in 'YYYY-MM' format, newest first.
     *
     * @return array<int, string>
     */
    function blogger_archives(): array
    {
        return array_keys(blogger_index()['archives']);
    }
}

if (!function_exists('blogger_archive_ids')) {
    /**
     * Post IDs published in a 'YYYY-MM' month, newest first.
     *
     * @return array<int, string>
     */
    function blogger_archive_ids(string $ym): array
    {
        return array_values((array) (blogger_index()['archives'][$ym] ?? []));
    }
}

if (!function_exists('blogger_label_url')) {
    function blogger_label_url(string $slug): string
    {
        return '/' . blogger_base() . '/' . blogger_label_segment() . '/' . trim($slug, '/');
    }
}

if (!function_exists('blogger_archive_url')) {
    function blogger_archive_url(string $ym): string
    {
        [$y, $m] = explode('-', $ym) + [null, null];
        return '/' . blogger_base() . '/' . sprintf('%04d/%02d', (int) $y, (int) $m);
    }
}
