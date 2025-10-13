@extends('templates.main')

@section('content')
<style>
  .dots-bg {
    --svg: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2MDAiIGhlaWdodD0iNjAwIiB2aWV3Qm94PSIwIDAgNjAgNjAiPGRlZnM+PHBhdHRlcm4gaWQ9InAiIHdpZHRoPSIxMCIgaGVpZ2h0PSIxMCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PGNpcmNsZSBjeD0iMSIgY3k9IjEiIHI9IjEiIGZpbGw9IiNlNWU3ZWIiIC8+PC9wYXR0ZXJuPjwvZGVmcz48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJ1cmwoI3ApIi8+PC9zdmc+');
    background-image: var(--svg);
  }
  .beard-tabs img { border-radius: 1rem; filter: contrast(1.1) saturate(1.2) drop-shadow(0 .5rem 1rem rgba(0,0,0,.15)); }
  .beard-card img { -webkit-mask-image: radial-gradient(circle at 70% 30%, #000 58%, transparent 60%); mask-image: radial-gradient(circle at 70% 30%, #000 58%, transparent 60%); border-radius: 1rem; }
</style>

<div class="container my-5">
  <div class="p-5 rounded-4 dots-bg shadow-sm">
    <h1 class="fw-bold">Placebeard.it 🧔</h1>
    <p class="lead mb-0">Beard‑driven development. Adds instant gravitas to any layout.</p>
  </div>

  <ul class="nav nav-tabs mt-3" id="beardTab" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="tab-a" data-bs-toggle="tab" data-bs-target="#pane-a" type="button" role="tab">Moustache</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-b" data-bs-toggle="tab" data-bs-target="#pane-b" type="button" role="tab">Goatee</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-c" data-bs-toggle="tab" data-bs-target="#pane-c" type="button" role="tab">Wizard</button>
    </li>
  </ul>
  <div class="tab-content beard-tabs" id="beardTabContent">
    <div class="tab-pane fade show active p-3" id="pane-a" role="tabpanel">
      <img src="https://placebeard.it/900x350" class="img-fluid" alt="Beard type A">
    </div>
    <div class="tab-pane fade p-3" id="pane-b" role="tabpanel">
      <img src="https://placebeard.it/900x350" class="img-fluid" alt="Beard type B">
    </div>
    <div class="tab-pane fade p-3" id="pane-c" role="tabpanel">
      <img src="https://placebeard.it/900x350" class="img-fluid" alt="Beard type C">
    </div>
  </div>

  <div class="row g-4 mt-1">
    <div class="col-lg-6">
      <div class="card shadow-sm beard-card">
        <img src="https://placebeard.it/600x350" class="card-img-top" alt="Masked beard">
        <div class="card-body">
          <h5 class="card-title">The Beard‑o‑Meter</h5>
          <p class="mb-2">Measure beardiness with precision engineering.</p>
          <div class="progress" role="progressbar" aria-label="Beardiness" aria-valuenow="86" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar bg-dark" style="width: 86%">86% Beardy</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header">Patch Notes</div>
        <div class="card-body">
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><span class="badge text-bg-dark me-2">v1.0</span> Added glorious whiskers</li>
            <li class="list-group-item"><span class="badge text-bg-secondary me-2">v1.1</span> Improved chin coverage</li>
            <li class="list-group-item"><span class="badge text-bg-primary me-2">v2.0</span> Full wizard mode enabled</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
