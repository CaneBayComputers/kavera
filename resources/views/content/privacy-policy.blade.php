@extends('templates.main')

@section('content')
<section class="py-5">
  <div class="container">
    <h1 class="mb-3">Privacy Policy</h1>
    <p class="text-muted">Last updated: {{ date('F j, Y') }}</p>
    <p>This Privacy Policy explains how {{ config('app.name') }} collects, uses, and protects your personal information when you use our website.</p>
    <h5>Information We Collect</h5>
    <p>We may collect information you provide directly (e.g., form submissions) and data collected automatically (e.g., IP address, browser).</p>
    <h5>How We Use Information</h5>
    <p>To respond to inquiries, improve our services, and communicate updates. We do not sell your personal information.</p>
    <h5>Cookies</h5>
    <p>We may use cookies to enhance your experience. You can disable cookies in your browser settings.</p>
    <h5>Contact</h5>
    <p>If you have questions, please <a href="/#contact">contact us</a>.</p>
  </div>
</section>
@endsection

