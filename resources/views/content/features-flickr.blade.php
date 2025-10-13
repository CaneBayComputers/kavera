@extends('templates.main')
@section('content')
<section class="py-5"><div class="container">
  <h1 class="mb-3">Flickr Galleries</h1>
  <p class="text-muted">Curate albums in Flickr and display them as on‑site galleries. Cache the feed; no runtime API calls.</p>
  <h5>Idea</h5>
  <ul>
    <li>Configure Flickr keys and user/album IDs in <code>.env</code>.</li>
    <li>Run a sync command to populate Redis; render with a Blade partial.</li>
  </ul>
</div></section>
@endsection

