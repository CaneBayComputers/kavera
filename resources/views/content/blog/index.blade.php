@extends('templates.blog')

@php
    $pageTitle = ($heading ?? 'Blog') . ' — ' . config('app.name');
    $pageDescription = 'Articles and updates from ' . config('app.name') . '.';
@endphp

@section('blog_content')
    <h1 class="mb-4">{{ $heading ?? 'Blog' }}</h1>

    @if(empty($posts))
        <p class="text-muted">No posts yet. Publish on Blogger, then run <code>php artisan app:blogger-import</code> and <code>php artisan app:update-content-list</code>.</p>
    @else
        <div class="row g-4">
            @foreach($posts as $post)
                <div class="col-md-6">
                    <article class="card h-100">
                        @if(!empty($post['thumb']))
                            <img src="{{ $post['thumb'] }}" class="card-img-top" alt="" loading="lazy">
                        @endif
                        <div class="card-body d-flex flex-column">
                            <h2 class="h5 mb-1"><a href="{{ $post['path'] }}" class="text-decoration-none">{{ $post['title'] }}</a></h2>
                            @if(!empty($post['published_at']))
                                <div class="text-muted small mb-2">{{ \Carbon\Carbon::parse($post['published_at'])->format('M j, Y') }}</div>
                            @endif
                            <p class="mb-3 flex-grow-1">{{ $post['summary'] }}</p>
                            <div><a href="{{ $post['path'] }}" class="btn btn-sm btn-outline-secondary">Read more</a></div>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    @endif
@endsection
