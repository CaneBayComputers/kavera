@extends('templates.main')

@php
    // Every content page sets these two; the layout turns them into <title>, meta description and social tags.
    $pageTitle = config('app.name') . ' — Home';
    $pageDescription = 'A short, specific one-sentence summary of this site for search results and link previews.';
    // Optional social share image:
    // $pageImage = '/storage/images/optimized/1280/<id>.webp';
    // $pageImageAlt = 'What the image shows';
@endphp

{{--
    STARTER PAGE. Replace every section below with the real site.
    Reference implementations for heroes, cards, forms, galleries, events and blog listings
    live in resources/examples (do not edit those; copy patterns from them).
--}}

@section('content')

<section class="bg-brand text-white py-5">
    <div class="container py-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <h1 class="display-5 fw-bold mb-3">{{ config('app.name') }}</h1>
                <p class="lead mb-4">One clear sentence about who this site is for and what it offers.</p>
                <a href="/contact" class="btn btn-accent btn-lg">Get in touch</a>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <h2 class="h5">First highlight</h2>
                <p class="text-muted mb-0">A short paragraph about the first thing visitors should know.</p>
            </div>
            <div class="col-md-4">
                <h2 class="h5">Second highlight</h2>
                <p class="text-muted mb-0">A short paragraph about the second thing visitors should know.</p>
            </div>
            <div class="col-md-4">
                <h2 class="h5">Third highlight</h2>
                <p class="text-muted mb-0">A short paragraph about the third thing visitors should know.</p>
            </div>
        </div>
    </div>
</section>

@endsection

@section('jsonld')
{!! file_get_contents(resource_path('views/jsonld/index.jsonld')) !!}
@endsection
