@extends('templates.main')
@section('content')
<section class="py-5"><div class="container">
  <h1 class="mb-3">Pixabay Images</h1>
  <p class="text-muted">Search and embed high‑quality stock images to speed up page building. Great for placeholders or production art.</p>
  <div class="row g-4 align-items-start">
    <div class="col-lg-7">
      <h5>How it works (concept)</h5>
      <ol>
        <li>Configure your API key in <code>.env</code> (<code>PIXABAY_API_KEY</code>).</li>
        <li>Fetch JSON once via the built‑in command and save to storage.</li>
        <li>Render that cached JSON in a Blade view—no API calls at request time.</li>
      </ol>
      <p class="small">Example (run from project directory with Podium):</p>
      <pre class="small bg-light p-3"><code>podium art app:pixabay-search "abstract geometric memphis pattern" --per_page=9 &gt; storage/app/pixabay/services.json</code></pre>
      <p class="small">Then in your Blade page, load <code>storage/app/pixabay/services.json</code> and loop over <code>hits</code> for <code>webformatURL</code>/<code>largeImageURL</code>.</p>
      <p class="mb-0">See a live example on the <a href="/services">Services</a> page—those tiles are generated from a cached Pixabay search.</p>
    </div>
    <div class="col-lg-5">
      <div class="p-3 border bg-white small">
        <strong>Why Pixabay?</strong>
        <ul class="mb-0">
          <li>Generous free stock with clear usage terms</li>
          <li>Consistent dimensions + multiple size URLs</li>
          <li>Easy to cache and render statically</li>
        </ul>
      </div>
    </div>
  </div>
</div></section>
@endsection
