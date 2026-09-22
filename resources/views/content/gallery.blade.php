@extends('templates.main')

@php
    $pageTitle = 'Gallery — ' . config('app.name');
    $pageDescription = 'Photo albums, kept in sync with Flickr.';
    $albums = flickr_albums();
    // Show one album when ?album=<id or title> is present, otherwise the album grid.
    $selected = request('album') ? flickr_album((string) request('album')) : null;
@endphp

@section('content')
<section class="py-5">
    <div class="container">

        @if(!flickr_enabled())
            <h1 class="mb-3">Gallery</h1>
            <p class="text-muted">Set <code>FLICKR_API_KEY</code> and <code>FLICKR_USER_ID</code> in <code>.env</code>, then run <code>php artisan app:flickr-sync</code>.</p>

        @elseif($selected)
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="/gallery">Gallery</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $selected['title'] }}</li>
                </ol>
            </nav>
            <h1 class="mb-2">{{ $selected['title'] }}</h1>
            @if($selected['description'] !== '')
                <p class="text-muted">{{ $selected['description'] }}</p>
            @endif

            <div class="row g-3">
                @foreach($selected['photos'] as $photo)
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="{{ $photo['large'] ?? $photo['medium'] }}" class="d-block ratio ratio-1x1 bg-light rounded overflow-hidden" target="_blank" rel="noopener" title="{{ $photo['title'] }}">
                            <img src="{{ $photo['small'] ?? $photo['thumb'] }}" alt="{{ $photo['description'] !== '' ? $photo['description'] : ($photo['title'] !== '' ? $photo['title'] : $selected['title'] . ' photo') }}" class="w-100 h-100" style="object-fit: cover;" loading="lazy">
                        </a>
                    </div>
                @endforeach
            </div>

        @elseif(empty($albums))
            <h1 class="mb-3">Gallery</h1>
            <p class="text-muted">No albums yet. Add a public album on Flickr and run <code>php artisan app:flickr-sync</code>.</p>

        @else
            <h1 class="mb-4">Gallery</h1>
            <div class="row g-4">
                @foreach($albums as $album)
                    <div class="col-sm-6 col-lg-4">
                        <a href="/gallery?album={{ $album['id'] }}" class="card h-100 text-decoration-none text-reset">
                            @if(!empty($album['cover']['medium']))
                                <div class="ratio ratio-4x3 bg-light">
                                    <img src="{{ $album['cover']['medium'] }}" alt="{{ $album['title'] }} album cover" class="card-img-top w-100 h-100" style="object-fit: cover;" loading="lazy">
                                </div>
                            @endif
                            <div class="card-body">
                                <h2 class="h5 card-title mb-1">{{ $album['title'] }}</h2>
                                <p class="card-text small text-muted mb-0">{{ $album['count'] }} {{ \Illuminate\Support\Str::plural('photo', $album['count']) }}</p>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif

    </div>
</section>
@endsection
