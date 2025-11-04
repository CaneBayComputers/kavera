@extends('templates.main')

@php
    $pageTitle = 'Services';
    $pageDescription = 'Explore our services with a gallery driven by a cached Pixabay search — fast, static, and easy to edit.';
@endphp

@section('content')

<!-- Services Page Content -->

<div class="container my-5">
    <div class="row">
        <!-- Service Details -->
        <div class="col-md-8">
            <h1>Services (Pixabay Example)</h1>
            <p class="lead">This page shows how a cached Pixabay search can drive a service gallery—no API calls at request time.</p>
            <div class="alert alert-info small">
              <div><strong>Search terms used:</strong> <code>abstract geometric memphis pattern</code></div>
              <div><strong>Command:</strong> <code>podium art app:pixabay-search "abstract geometric memphis pattern" --per_page=9 &gt; storage/app/pixabay/services.json</code></div>
            </div>

            @php
              $hits = [];
              $jsonPath = storage_path('app/pixabay/services.json');
              if (file_exists($jsonPath)) {
                $payload = json_decode(file_get_contents($jsonPath), true);
                $hits = $payload['hits'] ?? [];
              }
            @endphp

            <div class="row g-3 mb-4">
              <!-- Bike Repair -->
              <div class="col-6 col-md-4">
                <div class="border bg-white h-100">
                  <img class="img-fluid" src="https://pixabay.com/get/g449dd7dae096c0289a2a6258f34e26fad1ab0c1929f374a13f902e73b0644a5a737a0a89876a79341e469e4c8f95ff50027933a143f3e2b08807613b68658ba4_640.jpg" alt="Bike repair workshop" loading="lazy">
                  <div class="p-2 small text-muted">Bike Repair &amp; Tune‑Ups</div>
                </div>
              </div>
              <!-- Home Cleaning -->
              <div class="col-6 col-md-4">
                <div class="border bg-white h-100">
                  <img class="img-fluid" src="https://pixabay.com/get/g7288ed115cfa84ca14866e58ccd54c42bc88a9ee12f3edc3b10176a257c95c9aeba7fb24ba621f13ac8726166e2a3d42_640.jpg" alt="Vacuum cleaning carpet" loading="lazy">
                  <div class="p-2 small text-muted">Home Cleaning Services</div>
                </div>
              </div>
              <!-- Landscaping -->
              <div class="col-6 col-md-4">
                <div class="border bg-white h-100">
                  <img class="img-fluid" src="https://pixabay.com/get/g929a044e70c1a95d4b72d9bf8df9b4aa31a86078fd5a6d21e1045f4174586562ce98cebb6dfdb2483b62a072db17e506b9fd0148d8f09842377cf9abc7470395_640.jpg" alt="Formal garden landscaping" loading="lazy">
                  <div class="p-2 small text-muted">Landscaping &amp; Yard Care</div>
                </div>
              </div>
              <!-- Pet Grooming -->
              <div class="col-6 col-md-4">
                <div class="border bg-white h-100">
                  <img class="img-fluid" src="https://pixabay.com/get/g86eff68c8a51cb9b35cd74fdae60f15ae63e0f52e8942c30df0ee92a12e2bda0713760d46d2aab28bd54052192d07793_640.jpg" alt="Dog bath grooming" loading="lazy">
                  <div class="p-2 small text-muted">Pet Grooming</div>
                </div>
              </div>
              <!-- Tutoring -->
              <div class="col-6 col-md-4">
                <div class="border bg-white h-100">
                  <img class="img-fluid" src="https://pixabay.com/get/gc0feec739448360cef7c0d847652d0105ed23ab17c7e8a7463b86e8b0faf023ca7deb8137957ad057d9c79c7fa1e5e25d15ed190e0e7c10ded836719752feab8_640.jpg" alt="Volunteer tutoring" loading="lazy">
                  <div class="p-2 small text-muted">Tutoring &amp; Coaching</div>
                </div>
              </div>
            </div>

        </div>

        <!-- Sidebar with Contact Information -->
        <div class="col-md-4">
            <div class="bg-light p-4">
                <h4>Contact Us</h4>
                <p>For inquiries and more information:</p>
                <ul class="list-unstyled">
                    <li>Email: info@example.com</li>
                    <li>Phone: +123 456 7890</li>
                    <li>Address: 123 Street, City, Country</li>
                </ul>
            </div>
        </div>
    </div>
</div>

@endsection
