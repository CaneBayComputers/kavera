@extends('templates.main')

@php
    $pageTitle = 'Google Blogger Integration';
    $pageDescription = 'Keep writing in Blogger and display cached posts on your site with simple, fast rendering.';
@endphp

@section('content')
<section class="py-5"><div class="container">
  <h1 class="mb-3">Google Blogger</h1>
  <p class="text-muted">Keep writing in Blogger and pull entries into a flat‑file page. Your team edits where they already work.</p>
  <h5>Idea</h5>
  <ul>
    <li>Set Blogger API key/blog ID in <code>.env</code>.</li>
    <li>Sync and cache posts; render an excerpts list with deep links.</li>
  </ul>
</div></section>
@endsection
