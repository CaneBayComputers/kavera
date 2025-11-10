<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class BlogController extends Controller
{
    public function index()
    {
        $posts = $this->recent(10);
        return view('content.blog.index', [
            'posts' => $posts,
            'heading' => 'Recent Posts',
        ]);
    }

    public function byLabel(string $label)
    {
        $label = strtolower($label);
        $key = 'blogger:label:' . $label . ':ids';
        $ids = Redis::smembers($key) ?: [];
        $posts = $this->previewsByIds($ids);
        // Sort newest first using published timestamp in preview
        usort($posts, function ($a, $b) {
            return strtotime($b['published_at'] ?? '') <=> strtotime($a['published_at'] ?? '');
        });
        return view('content.blog.index', [
            'posts' => $posts,
            'heading' => 'Posts tagged: ' . ($this->labelName($label) ?: $label),
        ]);
    }

    public function byArchive(int $year, int $month)
    {
        $ym = sprintf('%04d-%02d', $year, $month);
        $ids = Redis::zrevrange('blogger:archive:' . $ym, 0, -1) ?: [];
        $posts = $this->previewsByIds($ids);
        return view('content.blog.index', [
            'posts' => $posts,
            'heading' => 'Archive: ' . $ym,
        ]);
    }

    private function recent(int $n): array
    {
        $json = Redis::get('blogger:recent');
        if (!$json) { return []; }
        $arr = json_decode($json, true);
        return is_array($arr) ? $arr : [];
    }

    private function previewsByIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $json = Redis::get('blogger:post:' . $id);
            if ($json) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) { $out[] = $decoded; }
            }
        }
        return $out;
    }

    private function labelName(string $slug): ?string
    {
        return Redis::hget('blogger:labels_display', $slug) ?: null;
    }
}

