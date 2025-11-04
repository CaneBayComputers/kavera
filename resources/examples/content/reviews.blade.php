@extends('templates.main')

@php
    $pageTitle = 'Customer Reviews';
    $pageDescription = 'See what customers are saying — testimonials and ratings that highlight service quality and experience.';
@endphp

@section('content')

<!-- Services Page Content -->

<!-- Reviews Section with Headings -->
<div class="container my-5">
    <div class="row">
        <div class="col-md-12 text-center mb-4">
            <h1>Customer Reviews</h1>
            <h2>See what our customers are saying</h2>
        </div>
    </div>

    <div class="row">
        <!-- Review 1 -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <img src="https://placehold.co/50" class="rounded-circle float-start me-3" alt="Avatar" style="width: 50px;">
                    <h5 class="card-title">John Doe</h5>
                    <p class="text-muted">A month ago</p>
                    <p class="card-text">
                        "Great experience! The service was excellent and very professional."
                    </p>
                    <div>
                        &#11088;&#11088;&#11088;&#11088;&#11088;
                    </div>
                </div>
            </div>
        </div>

        <!-- Review 2 -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <img src="https://placehold.co/50" class="rounded-circle float-start me-3" alt="Avatar" style="width: 50px;">
                    <h5 class="card-title">Jane Smith</h5>
                    <p class="text-muted">A month ago</p>
                    <p class="card-text">
                        "Highly recommend this company. Very friendly staff and efficient service."
                    </p>
                    <div>
                        &#11088;&#11088;&#11088;&#11088;&#11088;
                    </div>
                </div>
            </div>
        </div>

        <!-- Review 3 -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <img src="https://placehold.co/50" class="rounded-circle float-start me-3" alt="Avatar" style="width: 50px;">
                    <h5 class="card-title">Alex Johnson</h5>
                    <p class="text-muted">A month ago</p>
                    <p class="card-text">
                        "Impressive service! They exceeded my expectations."
                    </p>
                    <div>
                        &#11088;&#11088;&#11088;&#11088;&#11088;
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


@endsection
