<?php

namespace App\Console\Commands;

use App\Services\ContentRegistry;
use Illuminate\Console\Command;

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
    public function handle(ContentRegistry $registry): int
    {
        $slugs = $registry->refresh();

        $this->info('Content list saved: ' . count($slugs) . ' page(s) registered in the "' . config('cache.default') . '" cache store.');

        return self::SUCCESS;
    }
}
