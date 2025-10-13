@extends('templates.main')
@section('content')
<section class="py-5"><div class="container">
  <h1 class="mb-3">Eventbrite Events</h1>
  <p class="text-muted">List upcoming events from your Eventbrite organization, cached in Redis for speed.</p>
  <h5>Setup</h5>
  <ul>
    <li>Set <code>EVENTBRITE_PRIVATE_TOKEN</code> and <code>EVENTBRITE_ORGANIZATION_ID</code> in <code>.env</code>.</li>
    <li>Fetch events: <code>podium art app:update-eventbrite</code></li>
    <li>Visit <code>/events</code> to see the listing.</li>
  </ul>
</div></section>
@endsection

