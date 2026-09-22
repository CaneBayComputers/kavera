@extends('templates.main')

@php
    $pageTitle = 'Flickr Galleries Integration';
    $pageDescription = 'Photos pushed to a Flickr album from your phone show up on the site after the next sync.';
    $albums = flickr_albums();
    $selected = request('album') ? flickr_album((string) request('album')) : null;
@endphp

@section('content')
<section class="py-5"><div class="container">
  <h1 class="mb-3">Flickr Galleries</h1>
  <p class="text-muted">
    Set <code>FLICKR_API_KEY</code> and <code>FLICKR_USER_ID</code>, run <code>php artisan app:flickr-sync</code>
    (or let the scheduler run it every 15 minutes), and every public album on the account becomes a gallery.
    Templates read the cache through <code>flickr_albums()</code> and <code>flickr_album($idOrTitle)</code>; nothing calls Flickr at request time.
  </p>

  @if(!flickr_enabled())
    <div class="alert alert-secondary">Flickr is not configured on this install.</div>

  @elseif($selected)
    <p><a href="/integrations/flickr">&larr; All albums</a></p>
    <h2 class="h4">{{ $selected['title'] }}</h2>
    <div class="row g-3">
      @foreach($selected['photos'] as $photo)
        <div class="col-6 col-md-3">
          <a href="{{ $photo['large'] ?? $photo['medium'] }}" target="_blank" rel="noopener" class="d-block ratio ratio-1x1 rounded overflow-hidden bg-light">
            <img src="{{ $photo['small'] ?? $photo['thumb'] }}" alt="{{ $photo['title'] !== '' ? $photo['title'] : $selected['title'] . ' photo' }}" class="w-100 h-100" style="object-fit:cover;" loading="lazy">
          </a>
        </div>
      @endforeach
    </div>

  @elseif(empty($albums))
    <div class="alert alert-secondary">No albums cached yet. Run <code>php artisan app:flickr-sync</code>.</div>

  @else
    <div class="row g-4">
      @foreach($albums as $album)
        <div class="col-sm-6 col-lg-4">
          <a href="/integrations/flickr?album={{ $album['id'] }}" class="card h-100 text-decoration-none text-reset shadow-sm">
            @if(!empty($album['cover']['medium']))
              <div class="ratio ratio-4x3 bg-light"><img src="{{ $album['cover']['medium'] }}" alt="{{ $album['title'] }} album cover" class="w-100 h-100" style="object-fit:cover;" loading="lazy"></div>
            @endif
            <div class="card-body">
              <h2 class="h5 mb-1">{{ $album['title'] }}</h2>
              <p class="small text-muted mb-0">{{ $album['count'] }} photo(s)</p>
            </div>
          </a>
        </div>
      @endforeach
    </div>
  @endif
</div></section>
@endsection
