@extends('templates.main')

@php
    $pageTitle = 'Salesforce Integration';
    $pageDescription = 'Create Salesforce Leads (or any sObject) from form submissions with explicit field mapping and sensible defaults.';
@endphp

@section('content')
<section class="py-5">
  <div class="container">
    <h1 class="mb-3">Salesforce Integration</h1>
    <p class="lead">Create Leads (or any sObject) from form submissions using a simple, explicit field map — no plugin roulette.</p>
    <p class="mb-3">Point a webhook adapter at Salesforce and provide a <code>field_map</code> for required fields. Defaults can fill company if you’re collecting B2C style leads.</p>

    <div class="row g-4">
      <div class="col-md-6">
        <div class="p-4 border bg-white h-100">
          <h5>What’s included</h5>
          <ul class="small mb-0">
            <li>Explicit mapping for Lead fields (e.g., FirstName, LastName, Email, Phone, Company)</li>
            <li>Configurable object + API version</li>
            <li>Small defaults helper for Company (env) and LastName fallback</li>
          </ul>
        </div>
      </div>
      <div class="col-md-6">
        <div class="p-4 border bg-white h-100">
          <h5>Setup</h5>
          <p class="small mb-2">Add a Salesforce webhook and map your fields. See AGENTS.md for end‑to‑end examples.</p>
          <ul class="small mb-0">
            <li><code>options.base_url</code> + <code>options.api_version</code> + <code>options.object</code></li>
            <li><code>options.field_map</code> → map the Lead fields</li>
            <li><code>options.defaults.Company</code> if you don’t collect company</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection

