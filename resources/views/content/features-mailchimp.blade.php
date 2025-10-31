@extends('templates.main')

@section('content')
<section class="py-5">
  <div class="container">
    <h1 class="mb-3">Mailchimp Integration</h1>
    <p class="lead">Subscribe or upsert contacts with explicit field mapping and optional tags — no plugins, no guessing.</p>
    <p class="mb-3">Forms post to your Mailchimp audience via a simple webhook adapter. You control exactly which form fields map to Mailchimp merge tags using a <code>field_map</code>, and you can add tags automatically after a successful subscribe.</p>

    <div class="row g-4">
      <div class="col-md-6">
        <div class="p-4 border bg-white h-100">
          <h5>What’s included</h5>
          <ul class="small mb-0">
            <li>Idempotent upsert (PUT) by subscriber hash</li>
            <li>Explicit <code>field_map</code> for merge fields (e.g., EMAIL, FNAME, LNAME, PHONE)</li>
            <li>Optional tags via config or env (comma‑separated)</li>
            <li>Dev‑friendly logs for non‑2xx responses</li>
          </ul>
        </div>
      </div>
      <div class="col-md-6">
        <div class="p-4 border bg-white h-100">
          <h5>Setup</h5>
          <p class="small mb-2">Add a webhook entry for your form and map your fields. See AGENTS.md → “Webhook Adapters” for full examples.</p>
          <ul class="small mb-0">
            <li>Audience ID + API key in <code>.env</code></li>
            <li><code>field_map['EMAIL']</code> must resolve to your form email field</li>
            <li>Optional: <code>MAILCHIMP_CONTACT_TAGS</code> for tags</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
  </section>
@endsection

