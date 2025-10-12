<?php

use Symfony\Component\Yaml\Yaml;

function array_to_text(array $input)
{
    $pretty_json = json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $lines = preg_split("/\r\n|\n|\r/", $pretty_json);

    $cleaned_lines = [];

    foreach ($lines as $line) {
        // Remove only the FIRST 4 spaces if present
        $line = preg_replace('/^ {4}/', '', $line, 1);

        // Remove braces, brackets, double quotes, and trailing commas
        $line = str_replace(['{', '}', '[', ']', '"'], '', $line);
        $line = preg_replace('/,\s*$/', '', $line);

        if ($line !== '') {
            $cleaned_lines[] = $line;
        }
    }

    return implode("\n", $cleaned_lines);
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
