<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ListEventbriteOrganizations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:eventbrite-organizations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List Eventbrite organizations for the authenticated user';

    public function handle(): int
    {
        $privateToken = (string) config('services.eventbrite.private_token');
        if ($privateToken === '') {
            $this->error('Eventbrite private token not configured. Set EVENTBRITE_PRIVATE_TOKEN in .env');
            return self::FAILURE;
        }

        $token = $this->resolveToken($privateToken);
        try {
            $resp = Http::withToken($token)
                ->timeout(6)
                ->get('https://www.eventbriteapi.com/v3/users/me/organizations/');

            if (!$resp->ok()) {
                $this->error('Eventbrite API error: HTTP ' . $resp->status());
                $this->line(Str::limit($resp->body(), 500));
                return self::FAILURE;
            }

            $json = $resp->json();
            $orgs = $json['organizations'] ?? [];
            if (!is_array($orgs) || count($orgs) === 0) {
                $this->info('No organizations found.');
                return self::SUCCESS;
            }

            $rows = [];
            foreach ($orgs as $org) {
                $rows[] = [
                    'id' => (string) ($org['id'] ?? ''),
                    'name' => (string) ($org['name'] ?? ''),
                    'resource_uri' => (string) ($org['resource_uri'] ?? ''),
                ];
            }

            $this->table(['ID', 'Name', 'Resource URI'], $rows);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Eventbrite request failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function resolveToken(string $privateToken): string
    {
        // Private token (Personal OAuth token) is the bearer.
        return $privateToken;
    }
}
