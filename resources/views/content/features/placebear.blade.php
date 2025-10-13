@extends('templates.main')

@section('content')
<style>
  .wave-hero {
    --svg: url('data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIj8+PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4MDAiIGhlaWdodD0iMjAwIiB2aWV3Qm94PSIwIDAgODAwIDIwMCI+PGRlZnM+PGxpbmVhckdyYWRpZW50IGlkPSJnIiB4MT0iMCIgeTE9IjAiIHgyPSIxIiB5Mj0iMSI+PHN0b3Agb2Zmc2V0PSIwIiBzdG9wLWNvbG9yPSIjZmRiYTc0Ii8+PHN0b3Agb2Zmc2V0PSIxIiBzdG9wLWNvbG9yPSIjZmI3MTg1Ii8+PC9saW5lYXJHcmFkaWVudD48L2RlZnM+PHJlY3Qgd2lkdGg9IjgwMCIgaGVpZ2h0PSIyMDAiIGZpbGw9InVybCgjZykiLz48cGF0aCBkPSJNMCAxMjAgQyAxNTAgMTgwLCAzMDAgNjAsIDQ1MCAxMjAgQyA2MDAgMTgwLCA3NTAgNjAsIDgwMCAxMjAgTDgwMCAyMDAgTDAgMjAwIFoiIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4zNSIvPjwvc3ZnPg==');
    background-image: var(--svg);
    background-size: cover; background-position: center;
  }
  .bear-filters img { filter: contrast(1.15) saturate(1.35) hue-rotate(-10deg) drop-shadow(0 .5rem 1rem rgba(0,0,0,.15)); transition: transform .2s ease, filter .2s ease; border-radius: 1rem; }
  .bear-filters img:hover { transform: rotate(1.5deg) scale(1.03); filter: contrast(1.3) saturate(1.5) hue-rotate(-25deg); }
  .sketch { filter: grayscale(100%) contrast(1.3) brightness(1.1); }
  .honey { filter: sepia(70%) saturate(130%) hue-rotate(330deg); }
  .icy { filter: grayscale(60%) hue-rotate(180deg) saturate(120%); }
</style>

<div class="container my-5">
  <div class="p-5 rounded-4 text-dark wave-hero shadow-sm">
    <h1 class="fw-bold">Placebear 🐻</h1>
    <p class="lead mb-0">Rawr means “nice placeholder” in bear. Probably.</p>
  </div>

  <div class="row g-4 mt-1 bear-filters">
    @foreach([[600,350,'sketch','Bear with me'],[600,350,'honey','Bee‑lieve in yourself'],[600,350,'icy','Brrrilliant UI'] ] as $item)
      <div class="col-md-4">
        <div class="card h-100 shadow-sm">
          <img class="card-img-top {{ $item[2] }}" src="https://placebear.com/{{ $item[0] }}/{{ $item[1] }}" alt="Bear {{ $item[0] }}x{{ $item[1] }}">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">{{ $item[3] }}</h5>
            <p class="card-text">Dimensions: {{ $item[0] }}×{{ $item[1] }} — bear‑y stylish.</p>
            <a class="btn btn-warning mt-auto" href="https://placebear.com/200/300" target="_blank" rel="noopener">Summon a bear</a>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="row g-4 mt-1">
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header">Bear Necessities</div>
        <div class="card-body">
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><span class="badge bg-warning text-dark me-2">1</span>Honey‑powered rendering engine</li>
            <li class="list-group-item"><span class="badge bg-warning text-dark me-2">2</span>Hibernate‑friendly caching strategy</li>
            <li class="list-group-item"><span class="badge bg-warning text-dark me-2">3</span>Claw‑some developer experience</li>
          </ul>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header">Fun Facts</div>
        <div class="card-body">
          <div class="progress" role="progressbar" aria-label="Honey meter" aria-valuenow="72" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar bg-warning text-dark" style="width: 72%">72% Honey</div>
          </div>
          <p class="mt-2 mb-0">Side effects may include unbearable puns.</p>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
