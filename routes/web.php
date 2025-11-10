<?php

use App\Http\Controllers\Form;
use App\Http\Controllers\PageController;
use App\Http\Controllers\BlogController;
use App\Http\Middleware\VerifyContentAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('content.index');
});

Route::post('forms/{form}', [Form::class, 'process']);

// Blogger routes (label and archive), configurable base and label segment
Route::group([], function () {
    $base = trim((string) config('services.blogger.content_base', 'blog'), '/');
    $labelSeg = trim((string) config('services.blogger.label_segment', 'labels'), '/');
    if ($base !== '') {
        Route::get("/{$base}", [BlogController::class, 'index'])->name('blog.index');
        Route::get("/{$base}/{$labelSeg}/{label}", [BlogController::class, 'byLabel'])->name('blog.label');
        Route::get("/{$base}/{year}/{month}", [BlogController::class, 'byArchive'])
            ->where(['year' => '\\d{4}', 'month' => '\\d{2}'])
            ->name('blog.archive');
    }
});

Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '.*')
    ->middleware(VerifyContentAccess::class);
