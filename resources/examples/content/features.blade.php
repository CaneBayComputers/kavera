@extends('templates.main')

@php
    $pageTitle = 'Features Showcase';
    $pageDescription = 'A playful showcase of UI effects and placeholder image providers, including carousels, cards, and fun styling.';
@endphp

@section('content')

<style>
  .hero-svg-bg {
      --svg: url('data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIj8+PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4MDAiIGhlaWdodD0iMjAwIiB2aWV3Qm94PSIwIDAgODAwIDIwMCI+PGRlZnM+PGxpbmVhckdyYWRpZW50IGlkPSJnIiB4MT0iMCIgeTE9IjAiIHgyPSIxIiB5Mj0iMSI+PHN0b3Agb2Zmc2V0PSIwIiBzdG9wLWNvbG9yPSIjYTViNGZjIi8+PHN0b3Agb2Zmc2V0PSIxIiBzdG9wLWNvbG9yPSIjOTNjNWZkIi8+PC9saW5lYXJHcmFkaWVudD48L2RlZnM+PHJlY3Qgd2lkdGg9IjgwMCIgaGVpZ2h0PSIyMDAiIGZpbGw9InVybCgjZykiLz48cGF0aCBkPSJNMCAxMjAgQyAxNTAgMTgwLCAzMDAgNjAsIDQ1MCAxMjAgQyA2MDAgMTgwLCA3NTAgNjAsIDgwMCAxMjAgTDgwMCAyMDAgTDAgMjAwIFoiIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4zNSIvPjwvc3ZnPg==');
      background-image: var(--svg);
      background-size: cover;
      background-position: center;
  }
  .funky img { filter: contrast(1.1) saturate(1.4) hue-rotate(12deg) drop-shadow(0 0.5rem 1rem rgba(0,0,0,.15)); transition: transform .2s ease, filter .2s ease; }
  .funky img:hover { transform: rotate(-1.5deg) scale(1.02); filter: contrast(1.25) saturate(1.6) hue-rotate(25deg); }
  .duotone img { filter: grayscale(100%) contrast(1.2) sepia(60%) hue-rotate(300deg) saturate(140%); }
  .blob-mask img { -webkit-mask-image: radial-gradient(circle at 30% 30%, #000 60%, transparent 61%); mask-image: radial-gradient(circle at 30% 30%, #000 60%, transparent 61%); border-radius: 24px; }
</style>

<div class="container my-5">
  <div class="p-5 rounded-4 text-white hero-svg-bg shadow-sm">
    <h1 class="fw-bold">Feature Buffet: All‑You‑Can‑LOL 🍿</h1>
    <p class="lead mb-0">A smorgasbord of extremely serious, enterprise‑ready placeholder image providers. Bring your appetite (and a napkin).</p>
  </div>

  <div class="row g-4 mt-1">
    <div class="col-lg-8">
      <div id="featCarousel" class="carousel slide shadow-sm rounded-3 overflow-hidden" data-bs-ride="carousel">
        <div class="carousel-inner">
          <div class="carousel-item active">
            <img src="https://placebear.com/900/400" class="d-block w-100" alt="Placebear">
            <div class="carousel-caption d-none d-md-block">
              <span class="badge bg-warning text-dark">Placebear</span>
              <h5>Un‑bear‑ably good placeholders</h5>
              <p>We’re not lion. That’s the other section.</p>
            </div>
          </div>
          <div class="carousel-item">
            <img src="https://placebeard.it/900x400" class="d-block w-100" alt="Placebeard.it">
            <div class="carousel-caption d-none d-md-block">
              <span class="badge bg-dark">Placebeard.it</span>
              <h5>Beard‑driven development</h5>
              <p>Add 10x seniority to your interface instantly.</p>
            </div>
          </div>
          <div class="carousel-item">
            <img src="https://baconmockup.com/900/400" class="d-block w-100" alt="BaconMockup">
            <div class="carousel-caption d-none d-md-block">
              <span class="badge bg-danger">BaconMockup</span>
              <h5>Sizzle in every sprint</h5>
              <p>Agile… but make it crispy.</p>
            </div>
          </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#featCarousel" data-bs-slide="prev">
          <span class="carousel-control-prev-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#featCarousel" data-bs-slide="next">
          <span class="carousel-control-next-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Next</span>
        </button>
      </div>

      <div class="row g-4 mt-1">
        <div class="col-md-6">
          <div class="card h-100 shadow-sm funky">
            <img src="https://placebear.com/600/350" class="card-img-top" alt="Placebear fun">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title">Placebear 🐻</h5>
              <p class="card-text">Perfect for apps with claws. Warning: may cause hibernation in QA.</p>
              <a href="/features/placebear" class="btn btn-outline-primary mt-auto">Bear with us</a>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card h-100 shadow-sm duotone">
            <img src="https://placebeard.it/600x350" class="card-img-top" alt="Placebeard fun">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title">Placebeard.it 🧔</h5>
              <p class="card-text">Whisker your users away to a world of tasteful UI growth.</p>
              <a href="/features/placebeard-it" class="btn btn-outline-dark mt-auto">Beard more</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="accordion shadow-sm" id="jokesAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="h1"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#c1">Why placeholders?</button></h2>
          <div id="c1" class="accordion-collapse collapse show" data-bs-parent="#jokesAccordion"><div class="accordion-body">Because shipping pixels is faster than shipping features. Sssh, don’t tell PM.</div></div>
        </div>
        <div class="accordion-item">
          <h2 class="accordion-header" id="h2"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c2">Which is best?</button></h2>
          <div id="c2" class="accordion-collapse collapse" data-bs-parent="#jokesAccordion"><div class="accordion-body">All of them. Together. In a tastefully over‑engineered carousel.</div></div>
        </div>
        <div class="accordion-item">
          <h2 class="accordion-header" id="h3"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c3">Accessibility?</button></h2>
          <div id="c3" class="accordion-collapse collapse" data-bs-parent="#jokesAccordion"><div class="accordion-body">Alt text included. Puns optional. Semantics embraced.</div></div>
        </div>
      </div>

      <div class="card mt-3 shadow-sm blob-mask">
        <img src="https://baconmockup.com/600/300" alt="Blob masked bacon" class="card-img-top">
        <div class="card-body">
          <span class="badge bg-danger">Chef’s Special</span>
          <p class="mb-0">Bacon bits sprinkled over CSS variables for maximum umami.</p>
          <a href="/features/baconmockup" class="btn btn-danger btn-sm mt-2">Bring home the bacon</a>
        </div>
      </div>
    </div>
  </div>

  <div class="text-center mt-5">
    <a href="/features/baconmockup" class="btn btn-danger me-2">BaconMockup</a>
    <a href="/features/placebear" class="btn btn-warning me-2">Placebear</a>
    <a href="/features/placebeard-it" class="btn btn-dark">Placebeard.it</a>
  </div>

  <div class="text-center mt-4">
    <a href="/features/warrior-cats" class="btn btn-secondary">Bonus: Warrior Cats 🐾</a>
  </div>
</div>

@endsection
