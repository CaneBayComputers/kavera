@extends('templates.blog')

@php
    $pageTitle = 'Blog';
    $pageDescription = 'Recent articles and updates.';
@endphp

@section('blog_content')
  <!-- Hero banner -->
  <section class="mb-4 p-4 p-md-5 rounded-3 text-white" style="background: linear-gradient(100deg, var(--brand-primary), var(--brand-accent)); overflow: hidden; position: relative;">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <h1 class="display-4 fw-bold mb-2" style="letter-spacing:.3px;">{{ $heading ?? 'Kavera Blog' }}</h1>
        <p class="lead mb-0 opacity-95">Build agent‑speed sites on Laravel. Tutorials, release notes, and deep‑dives on integrations like Eventbrite, Flickr, and Blogger — without the plugin drama.</p>
      </div>
      <div class="col-lg-5 text-center d-none d-lg-block" aria-hidden="true">
        <svg viewBox="0 0 21.359989 18.335836" role="img" aria-label="" style="max-width: 240px; height: auto; opacity:.9;" xmlns="http://www.w3.org/2000/svg">
          <g transform="translate(-25.287292,-13.474625)">
            <path d="M 49.78125,129.82943 V 60.529427 h 15.6 v 26.2 l 23.7,-26.2 h 18.5 l -29.3,31.9 30.9,37.400003 h -18.5 l -25.3,-29.3 v 29.3 z" transform="matrix(0.26458333,0,0,0.26458333,12.116003,-2.5404522)" fill="#ffffff"/>
          </g>
          <g transform="translate(-19.652699,-13.474625)">
            <path d="M 89.081057,60.529444 59.626283,92.466603 90.680667,129.83023 H 109.18067 L 78.280276,92.429835 107.58106,60.529444 Z" transform="matrix(0.26458333,0,0,0.26458333,12.116003,-2.5404522)" fill="#f0f0f0"/>
          </g>
          <g transform="translate(-25.287292,-13.474625)">
            <path d="m 46.647282,14.153658 -7.386113,7.761283 7.386113,8.860441 v -5.173327 l -3.073714,-3.687114 3.073714,-3.229777 z" fill="#eaeaea"/>
          </g>
        </svg>
      </div>
    </div>
  </section>

  <!-- Integration reference -->
  <section class="mb-4">
    <div class="p-3 p-md-4 border bg-white rounded-3 d-flex align-items-center justify-content-between flex-column flex-md-row">
      <div class="me-md-3 text-center text-md-start">
        <div class="fw-semibold text-uppercase small text-muted mb-1">Powered by</div>
        <div class="h5 mb-0">Google Blogger Integration</div>
      </div>
      <div class="mt-3 mt-md-0">
        <a href="/integrations/blogger" class="btn btn-brand">Learn how it works</a>
      </div>
    </div>
  </section>

  @if(empty($posts))
    <p class="text-muted">No posts yet.</p>
  @else
    <div class="row g-4">
      @foreach($posts as $post)
        <div class="col-md-6">
          <article class="card h-100 shadow-sm">
            @if(!empty($post['thumb']))
              <img src="{{ $post['thumb'] }}" class="card-img-top" alt="" loading="lazy">
            @endif
            <div class="card-body d-flex flex-column">
              <h3 class="h5 mb-1"><a href="{{ $post['path'] }}" class="text-decoration-none">{{ $post['title'] }}</a></h3>
              @php $dt = $post['published_at'] ?? null; @endphp
              @if($dt)
                <div class="text-muted small mb-2">{{ \Carbon\Carbon::parse($dt)->format('M j, Y') }}</div>
              @endif
              <p class="mb-3 flex-grow-1">{{ $post['summary'] }}</p>
              <div><a href="{{ $post['path'] }}" class="btn btn-sm btn-brand">Read more</a></div>
            </div>
          </article>
        </div>
      @endforeach
    </div>
  @endif
@endsection
