<!DOCTYPE html>
<html lang="en">
    <head>
        @php
            // Content pages set these in an @php block at the top of the file.
            $__title = $pageTitle ?? $page_title ?? $title ?? config('app.name');
            $__description = $pageDescription ?? $page_description ?? $description ?? '';
            $__canonical = url()->current();
            $__imgCandidate = $pageImage ?? $ogImage ?? $twitterImage ?? null;
            $__image = null;
            if (is_string($__imgCandidate) && trim($__imgCandidate) !== '') {
                $__image = preg_match('/^https?:\/\//i', $__imgCandidate) ? $__imgCandidate : url($__imgCandidate);
            }
            $__imageAlt = $pageImageAlt ?? null;
            $__siteName = config('app.name');
        @endphp

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ trim($__title) !== '' ? $__title : $__siteName }}</title>
        @if(trim($__description) !== '')
            <meta name="description" content="{{ e($__description) }}">
        @endif
        <link rel="canonical" href="{{ $__canonical }}">
        <meta name="robots" content="index,follow">

        <meta property="og:site_name" content="{{ e($__siteName) }}">
        <meta property="og:title" content="{{ e(trim($__title) !== '' ? $__title : $__siteName) }}">
        @if(trim($__description) !== '')
            <meta property="og:description" content="{{ e($__description) }}">
        @endif
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ $__canonical }}">
        @if(!empty($__image))
            <meta property="og:image" content="{{ $__image }}">
            @if(!empty($__imageAlt))
                <meta property="og:image:alt" content="{{ e($__imageAlt) }}">
            @endif
        @endif

        <meta name="twitter:card" content="{{ !empty($__image) ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ e(trim($__title) !== '' ? $__title : $__siteName) }}">
        @if(trim($__description) !== '')
            <meta name="twitter:description" content="{{ e($__description) }}">
        @endif
        @if(!empty($__image))
            <meta name="twitter:image" content="{{ $__image }}">
        @endif

        @yield('head')

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <style>
            /* Site palette. Replace these with the client's colors; every section below uses them. */
            :root {
                --brand-primary: #1f2937;
                --brand-accent: #2563eb;
                --brand-light: #f8fafc;
                --brand-muted: #64748b;
            }
            .bg-brand { background: var(--brand-primary); }
            .bg-brand-light { background: var(--brand-light); }
            .text-accent { color: var(--brand-accent); }
            .btn-accent { background: var(--brand-accent); border-color: var(--brand-accent); color: #fff; }
            .btn-accent:hover { filter: brightness(0.9); color: #fff; }
            .navbar-brand { font-weight: 700; letter-spacing: .02em; }
        </style>
        @yield('jsonld')
    </head>
    <body class="d-flex flex-column min-vh-100">

        <header>
            <nav class="navbar navbar-expand-lg navbar-dark bg-brand">
                <div class="container">
                    <a class="navbar-brand" href="/">{{ $__siteName }}</a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="mainNav">
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item"><a class="nav-link" href="/">Home</a></li>
                            @if(blogger_enabled())
                                <li class="nav-item"><a class="nav-link" href="/{{ blogger_base() }}">Blog</a></li>
                            @endif
                            @if(flickr_enabled())
                                <li class="nav-item"><a class="nav-link" href="/gallery">Gallery</a></li>
                            @endif
                            <li class="nav-item"><a class="nav-link" href="/contact">Contact</a></li>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>

        <main class="flex-grow-1">
            @yield('content')
        </main>

        <footer class="bg-brand-light border-top py-4 mt-5">
            <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 small text-muted">
                <span>&copy; {{ date('Y') }} {{ $__siteName }}. All rights reserved.</span>
                <span><a href="/contact" class="text-decoration-none">Contact</a></span>
            </div>
        </footer>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        @stack('script')
    </body>
</html>
