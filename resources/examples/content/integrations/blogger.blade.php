@extends('templates.main')

@php
    $pageTitle = 'Google Blogger Integration';
    $pageDescription = 'Publish in Blogger, import as Blade files, and render instantly with a lightweight index for recents, tags, and archives.';
@endphp

@section('content')
<section class="py-5"><div class="container">
  <h1 class="mb-3">Google Blogger</h1>
  <p class="lead">Write in Blogger, render in Laravel. Kavera pulls public posts with an API key, normalizes them, and <strong>imports each post as a Blade file</strong> so your site stays fast and predictable.</p>

  <div class="row g-4 align-items-start">
    <div class="col-lg-7">
      <h5 class="mb-2">How it works</h5>
      <ol>
        <li>Enable Blogger API v3 and create an API key in Google Cloud.</li>
        <li>Set <code>BLOGGER_API_KEY</code> and <code>BLOGGER_BLOG_ID</code> in <code>.env</code>.</li>
        <li>Import posts as Blade files (overwrite always, never delete):<br><code>script -q -c "podium art app:blogger-import --per_page=50" /dev/null</code></li>
        <li>Refresh content registry:<br><code>script -q -c "podium art app:update-content-list" /dev/null</code></li>
      </ol>

      @php $base = trim((string) config('services.blogger.content_base', 'blog'), '/'); @endphp
      <section class="mt-4 p-4 p-md-5 rounded-3 text-white text-center" style="background: linear-gradient(100deg, var(--brand-primary), var(--brand-accent));">
        <h2 class="h3 fw-bold mb-2">See It Live</h2>
        <p class="mb-3">Imported posts render as fast, static Blade pages with tags and monthly archives.</p>
        <a href="/{{ $base }}" class="btn btn-light btn-lg">View Blog</a>
      </section>

      <p class="small text-muted">Slugs are cleansed using the same regex as content routing (see <code>config/content.php</code>). Non‑matching characters → space, spaces collapse to one dash, then lower‑cased.</p>

      <h6 class="mt-4">Config keys</h6>
      <ul class="small mb-3">
        <li><code>services.blogger.content_base</code> → base folder under <code>resources/views/content</code> (default: <code>blog</code>)</li>
        <li><code>services.blogger.post_layout</code> → Blade layout for imported posts (default: <code>templates.blog</code>)</li>
        <li><code>services.blogger.post_section</code> → section name to yield content into (default: <code>blog_content</code>)</li>
        <li><code>content.allowed_path_regex</code> → routing/slug allow‑list regex</li>
      </ul>

      <p class="mb-0">Want a sidebar with tags and archive? Use the sample blog layout: set <code>BLOGGER_POST_LAYOUT=templates.blog</code> and <code>BLOGGER_POST_SECTION=blog_content</code>, then build your tag/month lists in that template. We’ll keep posts as files, and later we can add lightweight Redis indices for recents/labels/archives.</p>
    </div>
    <div class="col-lg-5">
      <div class="p-3 border bg-white small">
        <strong>Why this is fast</strong>
        <ul class="mb-0">
          <li>No runtime API calls — posts render as static Blade files.</li>
          <li>Simple file routing fits Kavera’s flat‑file architecture.</li>
          <li>Clean slugs; easy to link and share.</li>
        </ul>
      </div>
      <div class="p-3 border bg-white small mt-3">
        <strong>Example: Recent posts widget</strong>
        <pre class="small bg-light p-2"><code>@php($recent = blogger_recent(5))
@if($recent)
  &lt;ul class="list-unstyled mb-0"&gt;
  @foreach($recent as $post)
    &lt;li class="mb-1"&gt;&lt;a href="{{ $post['path'] }}"&gt;{{ $post['title'] }}&lt;/a&gt;&lt;/li&gt;
  @endforeach
  &lt;/ul&gt;
@endif</code></pre>
        <p class="mb-0">Use helpers for labels and archives in layouts: <code>blogger_labels()</code>, <code>blogger_archives()</code>, <code>blogger_label_url($slug)</code>, <code>blogger_archive_url($ym)</code>.</p>
      </div>
    </div>
  </div>

</div></section>
@endsection
