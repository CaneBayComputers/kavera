<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class UpdateEventbrite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-eventbrite {--status=} {--time_filter=} {--page_size=} {--expand=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Eventbrite events using API key, cache sorted list in Redis.';

    public function handle(): int
    {
        $privateToken = (string) config('services.eventbrite.private_token');
        $orgId = (string) config('services.eventbrite.organization_id');

        if ($privateToken === '' || $orgId === '') {
            $this->error('Eventbrite private token or organization ID not configured.');
            return self::FAILURE;
        }

        $status = (string) ($this->option('status') ?: config('services.eventbrite.status', 'live,started'));
        $timeFilter = (string) ($this->option('time_filter') ?: config('services.eventbrite.time_filter', 'current_future'));
        $pageSize = (int) ($this->option('page_size') ?: config('services.eventbrite.page_size', 10));
        $expand = (string) ($this->option('expand') ?: config('services.eventbrite.expand', 'venue,logo,organizer'));

        // Acquire a useable token from API key. For Eventbrite v3, a Personal OAuth token behaves as the bearer.
        // Here we treat the configured API key as the operational bearer token.
        $token = $this->resolveToken($privateToken);
        if ($token === '') {
            $this->error('Unable to resolve Eventbrite token.');
            return self::FAILURE;
        }

        // Build query params. Order by start ascending for soonest → latest.
        $params = [
            'status' => $status,
            'time_filter' => $timeFilter,
            'page_size' => $pageSize,
            'order_by' => 'start_asc',
            'expand' => $expand,
        ];

        try {
            $resp = Http::withToken($token)
                ->timeout(6)
                ->get("https://www.eventbriteapi.com/v3/organizations/{$orgId}/events/", $params);

            if (!$resp->ok()) {
                $this->error('Eventbrite API error: HTTP ' . $resp->status());
                $this->line(Str::limit($resp->body(), 500));
                return self::FAILURE;
            }

            $json = $resp->json();
            $events = $json['events'] ?? [];
            if (!is_array($events)) {
                $events = [];
            }

            // Ensure start ascending sort regardless of API behavior.
            usort($events, static function ($a, $b) {
                $aTime = strtotime($a['start']['utc'] ?? $a['start']['local'] ?? '');
                $bTime = strtotime($b['start']['utc'] ?? $b['start']['local'] ?? '');
                return $aTime <=> $bTime;
            });

            $cacheKey = (string) config('services.eventbrite.cache_key', 'eventbrite.events');

            // Save indefinitely (no TTL)
            Cache::forever($cacheKey, $events);

            $this->info('Eventbrite events saved to Redis: ' . count($events) . ' event(s).');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Eventbrite fetch exception: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function resolveToken(string $privateToken): string
    {
        // For Eventbrite v3, the Private token (Personal OAuth token) is the bearer.
        return $privateToken;
    }
}
