@extends('templates.main')

@section('content')
<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-8">
                @yield('blog_content')
            </div>
            <aside class="col-lg-4">
                @php
                    $recent = blogger_recent(5);
                    $labels = blogger_labels();
                    $months = blogger_archives();
                @endphp

                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h6 text-uppercase text-muted mb-3">Recent</h2>
                        @if(empty($recent))
                            <p class="small text-muted mb-0">No posts yet.</p>
                        @else
                            <ul class="list-unstyled small mb-0">
                                @foreach($recent as $post)
                                    <li class="mb-2">
                                        <a href="{{ $post['path'] }}" class="text-decoration-none">{{ $post['title'] }}</a>
                                        @if(!empty($post['published_at']))
                                            <div class="text-muted">{{ \Carbon\Carbon::parse($post['published_at'])->format('M j, Y') }}</div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                @if(!empty($labels))
                    <div class="card mb-3">
                        <div class="card-body">
                            <h2 class="h6 text-uppercase text-muted mb-3">Tags</h2>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($labels as $slug => $meta)
                                    <a href="{{ blogger_label_url($slug) }}" class="btn btn-sm btn-outline-secondary rounded-pill">{{ $meta['name'] }} <span class="text-muted">({{ $meta['count'] }})</span></a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                @if(!empty($months))
                    <div class="card">
                        <div class="card-body">
                            <h2 class="h6 text-uppercase text-muted mb-3">Archive</h2>
                            <ul class="list-unstyled small mb-0">
                                @foreach($months as $ym)
                                    <li class="mb-1"><a href="{{ blogger_archive_url($ym) }}" class="text-decoration-none">{{ \Carbon\Carbon::createFromFormat('Y-m', $ym)->format('F Y') }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </aside>
        </div>
    </div>
</section>
@endsection
