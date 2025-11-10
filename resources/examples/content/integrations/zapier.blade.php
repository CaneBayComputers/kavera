@extends('templates.main')

@php
    $pageTitle = 'Zapier Integration';
    $pageDescription = 'Send mapped form data to Zapier and fan out to connected apps using a single, simple webhook.';
@endphp

@section('content')
<section class="py-5">
  <div class="container">
    <h1 class="mb-3">Zapier Integration</h1>
    <p class="lead">Send mapped form data to a Zap and fan out to thousands of apps — with a single webhook.</p>
    <p class="mb-3">Define a Zapier catcher URL and a <code>field_map</code> (dest → source). You can add static keys and optionally include a minimal <code>_context</code> block (form id + submission id).</p>

    <div class="row g-4">
      <div class="col-md-6">
        <div class="p-4 border bg-white h-100">
          <h5>What’s included</h5>
          <ul class="small mb-0">
            <li>Configurable catcher URL</li>
            <li>Explicit field mapping (dest → form field)</li>
            <li>Optional static constants</li>
            <li>Optional minimal context (form + submission ids)</li>
          </ul>
        </div>
      </div>
      <div class="col-md-6">
        <div class="p-4 border bg-white h-100">
          <h5>Setup</h5>
          <p class="small mb-2">Add a Zapier webhook under your form’s <code>webhooks</code> array. See AGENTS.md for complete examples.</p>
          <ul class="small mb-0">
            <li><code>options.url</code> → your Zap catcher URL</li>
            <li><code>options.field_map</code> → map to Zap payload keys</li>
            <li><code>options.static</code> → always‑on constants (optional)</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection

