@extends('templates.main')

@php
    $pageTitle = 'Flickr Galleries Integration';
    $pageDescription = 'Synchronize Flickr albums and display as fast, cached galleries in your site.';
@endphp

@section('content')
<section class="py-5"><div class="container">
  <h1 class="mb-3">Flickr Galleries</h1>
  <p class="text-muted">Albums and photo sets are synchronized and cached in Redis for use in gallery components.</p>
</div></section>
@endsection

