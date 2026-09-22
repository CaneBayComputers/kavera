<?php

namespace App\Http\Controllers;

class BlogController extends Controller
{
    public function index()
    {
        return view('content.blog.index', [
            'posts' => blogger_recent(10),
            'heading' => 'Recent Posts',
        ]);
    }

    public function byLabel(string $label)
    {
        $label = strtolower($label);

        return view('content.blog.index', [
            'posts' => blogger_previews_by_ids(blogger_label_ids($label)),
            'heading' => 'Posts tagged: ' . (blogger_label_name($label) ?: $label),
        ]);
    }

    public function byArchive(int $year, int $month)
    {
        $ym = sprintf('%04d-%02d', $year, $month);

        return view('content.blog.index', [
            'posts' => blogger_previews_by_ids(blogger_archive_ids($ym)),
            'heading' => 'Archive: ' . $ym,
        ]);
    }
}
