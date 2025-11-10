@extends('templates.main')

@php
    $recent = blogger_recent(5);
@endphp

@section('content')
<section class="py-5">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="mb-3 p-4 border bg-white" style="border-left:4px solid var(--brand-accent);">
          <div class="d-flex align-items-center mb-1">
            <i class="bi bi-chat-square-text me-2 text-primary"></i>
            <strong class="text-uppercase small text-muted">Kavera Blog</strong>
          </div>
          <p class="mb-0 small">News and notes on building agent‑speed sites with Laravel, plus integration tips for Eventbrite, Flickr, Blogger, and more.</p>
        </div>
        @yield('blog_content')
      </div>
      <div class="col-lg-4">
        <div class="p-3 mb-3" style="background: linear-gradient(105deg, var(--brand-primary), var(--brand-accent)); color:#fff; border-radius:.5rem;">
          <div class="d-flex align-items-center">
            <i class="bi bi-rss me-2"></i>
            <strong class="mb-0">Recent</strong>
          </div>
          @php $recent = blogger_recent(5); @endphp
          @if(empty($recent))
            <p class="small mb-0 mt-2">No recent posts.</p>
          @else
            <ul class="list-unstyled small mb-0 mt-2">
              @foreach($recent as $p)
                <li class="mb-2 d-flex align-items-start">
                  @if(!empty($p['thumb']))
                    <img src="{{ $p['thumb'] }}" alt="" class="me-2" style="width:48px;height:48px;object-fit:cover;border-radius:.25rem;" loading="lazy">
                  @endif
                  <div>
                    <a href="{{ $p['path'] }}" class="link-light text-decoration-none">{{ $p['title'] }}</a>
                    @if(!empty($p['published_at']))
                      <div class="opacity-75">{{ \Carbon\Carbon::parse($p['published_at'])->format('M j, Y') }}</div>
                    @endif
                  </div>
                </li>
              @endforeach
            </ul>
          @endif
        </div>
        @php
          $base = blogger_base();
          $labelSeg = blogger_label_segment();
          $labels = blogger_labels();
          $months = blogger_archives();
        @endphp

        <div class="p-3 border bg-white small mb-3">
          <h5 class="mb-2">Tags</h5>
          @if(empty($labels))
            <p class="text-muted mb-0">No tags yet.</p>
          @else
            <div class="d-flex flex-wrap gap-2">
              @foreach($labels as $slug => $meta)
                <a href="{{ blogger_label_url($slug) }}" class="btn btn-sm btn-outline-primary rounded-pill">{{ $meta['name'] }} <span class="badge bg-primary-subtle text-primary ms-1">{{ $meta['count'] }}</span></a>
              @endforeach
            </div>
          @endif
        </div>
        <div class="p-3 border bg-white small">
          <h5 class="mb-2">Archive</h5>
          @if(empty($months))
            <p class="text-muted mb-0">No archives yet.</p>
          @else
            <div class="list-group list-group-flush">
              @foreach($months as $ym)
                <a href="{{ blogger_archive_url($ym) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                  <span><i class="bi bi-calendar3 me-2 text-primary"></i>{{ $ym }}</span>
                </a>
              @endforeach
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
