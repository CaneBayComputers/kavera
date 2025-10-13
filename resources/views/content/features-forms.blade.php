@extends('templates.main')
@section('content')
<section class="py-5"><div class="container">
  <h1 class="mb-3">Forms &amp; Webhooks</h1>
  <p class="text-muted">Config‑driven forms with email templates and an optional JSON webhook for integrations.</p>
  <h5>Highlights</h5>
  <ul>
    <li>Add forms in <code>config/form.php</code> with validation rules.</li>
    <li>Point to <code>/forms/{name}</code> to submit. See <code>resources/views/emails</code> for templates.</li>
    <li>Use <code>success_page</code> for smart redirects (supports <code>#fragments</code> back to the originating page).</li>
  </ul>
</div></section>
@endsection

