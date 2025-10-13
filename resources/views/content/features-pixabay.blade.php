@extends('templates.main')
@section('content')
<section class="py-5"><div class="container">
  <h1 class="mb-3">Pixabay Images</h1>
  <p class="text-muted">Search and embed high‑quality stock images to speed up page building. Great for placeholders or production art.</p>
  <h5>Idea</h5>
  <ul>
    <li>Set Pixabay API key in <code>.env</code>.</li>
    <li>Use a small helper/command to fetch and cache image metadata; render responsive images in Blade.</li>
  </ul>
</div></section>
@endsection

