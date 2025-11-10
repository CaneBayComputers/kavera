@extends('templates.main')

@php
    $pageTitle = 'Integrations';
    $pageDescription = 'Connect services like Eventbrite, Blogger, Flickr, Pixabay, and webhooks to power dynamic content.';
@endphp

@section('content')
<section class="py-5">
  <div class="container">
    <h1 class="mb-3">Integrations</h1>
    <p class="text-muted">Kavera reads from external services and caches data in Redis for reliable, fast display. Configure via environment variables and run the sync commands to hydrate content.</p>

    <div class="row g-4 mt-1">
      <div class="col-md-6">
        <div class="card h-100 shadow-sm">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">Eventbrite</h5>
            <p class="card-text flex-grow-1">List upcoming events from your organization. Data is fetched and cached, then rendered in views without external API calls at request time.</p>
            <a href="/integrations/eventbrite" class="stretched-link">Learn more</a>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card h-100 shadow-sm">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">Google Blogger</h5>
            <p class="card-text flex-grow-1">Fetch public posts with an API key (no OAuth). Posts are normalized and cached for simple template rendering.</p>
            <a href="/integrations/blogger" class="stretched-link">Learn more</a>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card h-100 shadow-sm">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">Flickr</h5>
            <p class="card-text flex-grow-1">Synchronize albums and photo sets, then display them in gallery components without runtime API calls.</p>
            <a href="/integrations/flickr" class="stretched-link">Learn more</a>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card h-100 shadow-sm">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">Pixabay</h5>
            <p class="card-text flex-grow-1">Pull stock images for use in templates; optionally process with AWS Rekognition for tagging.</p>
            <a href="/integrations/pixabay" class="stretched-link">Learn more</a>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card h-100 shadow-sm">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">Forms & Webhooks</h5>
            <p class="card-text flex-grow-1">Validated forms with email delivery and optional webhooks (Mailchimp, Zapier, Salesforce).</p>
            <a href="/integrations/forms" class="stretched-link">Learn more</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
