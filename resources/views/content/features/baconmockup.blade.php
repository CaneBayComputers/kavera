@extends('templates.main')

@section('content')
<style>
  .bacon-hero {
    --svg: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZGVmcz48bGluZWFyR3JhZGllbnQgaWQ9ImIiIHgxPSIwIiB5MT0iMCIgeDI9IjEiIHkyPSIwIj48c3RvcCBvZmZzZXQ9IjAiIHN0b3AtY29sb3I9IiNmM2Y0ZjYiLz48c3RvcCBvZmZzZXQ9IjEiIHN0b3AtY29sb3I9IiNmZWU3ZTAiLz48L2xpbmVhckdyYWRpZW50PjwvZGVmcz48cmVjdCB3aWR0aD0iODAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0idXJsKCNiKSIvPjxwYXRoIGQ9Ik0wIDEwIEMgNDAgMzAsIDgwIDAsIDEyMCAxMCBDIDE2MCAyMCwgMjAwIDAsIDI0MCAxMCBDIDI4MCAyMCwgMzIwIDAsIDM2MCAxMCBDIDQwMCAyMCwgNDQwIDAsIDQ4MCAxMCBDIDUyMCAyMCwgNTYwIDAsIDYwMCAxMCBDIDY0MCAyMCwgNjgwIDAsIDcyMCAxMCBMIDgwMCAzMCBMIDgwMCAwIEwgMCAwIFoiIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4zNSIvPjwvc3ZnPg==');
    background-image: var(--svg); background-size: cover; background-position: center;
  }
  .duo img { filter: grayscale(100%) contrast(1.1) sepia(70%) hue-rotate(330deg) saturate(140%); border-radius: 1rem; }
  .tilt img { transform: rotate(-1.5deg); border-radius: 1rem; }
  .tilt img:hover { transform: rotate(0deg) scale(1.02); transition: transform .15s ease; }
</style>

<div class="container my-5">
  <div class="p-5 rounded-4 text-dark bacon-hero shadow-sm">
    <h1 class="fw-bold">BaconMockup 🥓</h1>
    <p class="lead mb-0">For designs that need more sizzle. Serve with a side of Bootstrap.</p>
  </div>

  <div class="row g-4 mt-1">
    <div class="col-md-6">
      <div class="card h-100 shadow-sm duo">
        <img src="https://baconmockup.com/600/350" class="card-img-top" alt="Bacon duotone">
        <div class="card-body">
          <h5 class="card-title">Crispy Duotone</h5>
          <p class="mb-0">A tasteful filter to impress even the most seasoned designers.</p>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm tilt">
        <img src="https://baconmockup.com/600/350" class="card-img-top" alt="Bacon tilt">
        <div class="card-body">
          <h5 class="card-title">Chef’s Kiss</h5>
          <p class="mb-0">Slight tilt. Big flavor. Michelin‑starred mockups only.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mt-1">
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header">Recipe (Offcanvas)</div>
        <div class="card-body">
          <p class="mb-2">Open for a sizzling lorem ipsum recipe that’s totally not edible.</p>
          <button class="btn btn-danger" type="button" data-bs-toggle="offcanvas" data-bs-target="#baconRecipe">Open Recipe</button>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <span class="badge bg-danger">Hot Tip</span>
          <p class="mb-0">Cache your images like a pro: crispy outside, tender inside.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="offcanvas offcanvas-end" tabindex="-1" id="baconRecipe">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title">Bacon Ipsum à la Bootstrap</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
      <p>Bacon ipsum dolor amet flank brisket prosciutto jowl landjaeger ball tip. Kielbasa shankle bresaola chuck pork loin pastrami porchetta tail frankfurter.</p>
      <p>Short ribs meatloaf pancetta capicola tenderloin salami shoulder ham. Corned beef tri‑tip kevin chislic burgdoggen turkey.</p>
      <a class="btn btn-outline-danger" href="https://baconmockup.com/200/300" target="_blank" rel="noopener">Bring home the bacon</a>
    </div>
  </div>
</div>
@endsection
