@extends('templates.main')

@section('content')
<section class="py-5">
  <div class="container">
    <h1 class="mb-3">Terms and Conditions</h1>
    <p class="text-muted">Last updated: {{ date('F j, Y') }}</p>
    <p>Welcome to {{ config('app.name') }}. By accessing or using our website, you agree to be bound by these Terms and Conditions. If you do not agree, please do not use the site.</p>
    <h5>Use of Site</h5>
    <p>You agree to use this website only for lawful purposes and in a way that does not infringe the rights of, restrict, or inhibit anyone else’s use and enjoyment of the site.</p>
    <h5>Intellectual Property</h5>
    <p>All content, trademarks, and data on this site are the property of {{ config('app.name') }} or its licensors. You may not reproduce or distribute any content without permission.</p>
    <h5>Limitation of Liability</h5>
    <p>{{ config('app.name') }} is not liable for any direct, indirect, incidental, or consequential damages arising from your use of the site.</p>
    <h5>Changes</h5>
    <p>We may update these Terms from time to time. Continued use of the site constitutes acceptance of the updated Terms.</p>
    <p class="mt-4"><a href="/#contact" class="btn btn-primary">Contact Us</a></p>
  </div>
  </section>
@endsection

