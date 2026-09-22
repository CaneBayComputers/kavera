<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyContentAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->path(), '/');

        // Allow home page to pass through
        if ($path === '') {
            return $next($request);
        }

        // Regex: allow only characters defined in configuration
        $regex = (string) config('content.allowed_path_regex', '/^[a-zA-Z0-9\/-]+$/');
        if (! preg_match($regex, $path)) {
            abort(404);
        }

        // Allow dynamic blog routes to pass through (handled by BlogController)
        $base = trim((string) config('services.blogger.content_base', 'blog'), '/');
        $labelSeg = trim((string) config('services.blogger.label_segment', 'labels'), '/');
        if (
            $base !== '' && (
            $path === $base ||
            preg_match('#^' . preg_quote($base, '#') . '/' . preg_quote($labelSeg, '#') . '/[^/]+$#', $path) ||
            preg_match('#^' . preg_quote($base, '#') . '/\d{4}/\d{2}$#', $path)
            )
        ) {
            return $next($request);
        }

        $content_list = Cache::get('content_list', []);

        // Older registries were stored as a JSON string; accept both shapes.
        if (is_string($content_list)) {
            $content_list = json_decode($content_list, true) ?: [];
        }

        if (is_array($content_list) && in_array($path, $content_list, true)) {
            return $next($request);
        }

        abort(404);
    }
}
