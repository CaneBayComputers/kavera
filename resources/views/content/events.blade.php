@extends('templates.main')

@section('content')

<div class="container my-5">
    <div class="row mb-4">
        <div class="col-md-10 mx-auto text-center">
            <h1 class="display-5">Upcoming Events</h1>
            <p class="text-muted">Powered by Eventbrite</p>
        </div>
    </div>

    @php
        $configured = eventbrite_enabled();
        $events = $configured ? eventbrite_fetch_events() : [];
    @endphp

    @if(!$configured)
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="alert alert-warning">
                    <strong>Eventbrite not configured.</strong>
                    <div class="mt-2">
                        Add <code>EVENTBRITE_TOKEN</code> and <code>EVENTBRITE_ORGANIZATION_ID</code> to your <code>.env</code> file, then refresh content and caches.
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4">
        @forelse ($events as $event)
            @php
                $img = eventbrite_image_url($event);
                $startLocal = \Carbon\Carbon::parse($event['start']['local'] ?? $event['start']['utc'] ?? null);
                $endLocal = \Carbon\Carbon::parse($event['end']['local'] ?? $event['end']['utc'] ?? null);
                $venue = $event['venue']['name'] ?? null;
            @endphp

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    @if($img)
                        <img src="{{ $img }}" class="card-img-top" alt="{{ $event['name']['text'] ?? 'Event' }}">
                    @endif
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">{{ $event['name']['text'] ?? 'Untitled Event' }}</h5>
                        <p class="card-text text-muted small mb-2">
                            @if($startLocal)
                                <span>{{ $startLocal->format('M j, Y g:ia') }}</span>
                            @endif
                            @if($endLocal)
                                <span class="mx-1">–</span>
                                <span>{{ $endLocal->format('g:ia') }}</span>
                            @endif
                            @if($venue)
                                <br><span>{{ $venue }}</span>
                            @endif
                        </p>
                        <p class="card-text flex-grow-1">{{ $event['summary'] ?? \Illuminate\Support\Str::limit($event['description']['text'] ?? '', 160) }}</p>
                        @if(!empty($event['url']))
                            <a href="{{ $event['url'] }}" class="btn btn-primary mt-auto" target="_blank" rel="noopener">View on Eventbrite</a>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-md-8 mx-auto">
                <div class="alert alert-info">
                    No events found.
                </div>
            </div>
        @endforelse
    </div>
</div>

@endsection

