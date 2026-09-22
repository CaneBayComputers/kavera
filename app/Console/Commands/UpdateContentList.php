<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;

class UpdateContentList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-content-list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild the registry of content pages that are allowed to resolve (run after adding, removing or renaming a content view).';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $content_path = resource_path('views/content');

        $files = File::allFiles($content_path);

        foreach ($files as &$file) {
            $file = $file->getRelativePathname();

            $file = preg_replace('/\.blade\.php$/', '', $file);
        }

        $files = array_values($files);

        Cache::forever('content_list', $files);

        $this->info('Content list saved: ' . count($files) . ' page(s) registered in the "' . config('cache.default') . '" cache store.');
    }
}
