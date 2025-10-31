@extends('templates.main')

@section('content')

<!-- Hero: Sitekit on Laravel -->
<section class="py-5 text-white" style="background: linear-gradient(90deg, #0d6efd, #f97316);">
  <div class="container py-4">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <h1 class="display-5 fw-bold mb-3">Sitekit on Laravel: Build Websites at Agent Speed</h1>
        <p class="lead mb-3">A clean Laravel starter so <strong>AI agents can assemble real sites fast</strong> — pages are simple Blade files, routing is pre‑validated for safety, and forms ship with email + webhooks. Add events, galleries, posts, and stock images — <strong>no plugin drama</strong>.</p>
        <p class="mb-4 small">Bonus: sensible SEO defaults — pages set proper title tags, and the Agent Brief suggests keywords you can pepper through copy and headings.</p>
        <a href="/features" class="btn btn-light btn-lg me-2">See Features</a>
        <a href="/#footer" class="btn btn-outline-light btn-lg">Get Updates</a>
      </div>
      <div class="col-lg-5 text-center">
        <svg viewBox="0 0 220 160" width="100%" height="auto" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Decorative graphic">
          <defs>
            <linearGradient id="g1" x1="0" x2="1" y1="0" y2="1">
              <stop offset="0%" stop-color="#22d3ee"/>
              <stop offset="100%" stop-color="#0d6efd"/>
            </linearGradient>
          </defs>
          <rect x="10" y="10" rx="16" ry="16" width="200" height="120" fill="url(#g1)" opacity="0.25"/>
          <circle cx="70" cy="70" r="18" fill="#fff" opacity="0.9"/>
          <rect x="110" y="50" width="70" height="10" rx="5" fill="#fff" opacity="0.9"/>
          <rect x="110" y="68" width="56" height="10" rx="5" fill="#fff" opacity="0.7"/>
          <rect x="110" y="86" width="48" height="10" rx="5" fill="#fff" opacity="0.5"/>
        </svg>
      </div>
    </div>
  </div>
</section>

<!-- Built-in Integrations -->
<section class="py-5">
  <div class="container">
    <h2 class="h1 mb-1 text-center">All‑in‑One, Turn‑Key Stack</h2>
    <p class="text-muted text-center mb-4">Events, galleries, blogging, stock images, agents, and forms—all ready to slot in.</p>
    <div class="row g-4">
      <div class="col-md-6 col-lg-3">
        <div class="h-100 p-3 border bg-white">
          <div class="d-flex align-items-center mb-2"><i class="bi bi-calendar-event me-2 text-primary"></i><strong>Eventbrite Events</strong></div>
          <p class="small mb-0">Fetch and cache organization events and render on any page.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="h-100 p-3 border bg-white">
          <div class="d-flex align-items-center mb-2"><i class="bi bi-images me-2 text-primary"></i><strong>Flickr Galleries</strong></div>
          <p class="small mb-0">Manage albums on Flickr and display as on‑site galleries. Cache results; no runtime API calls.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="h-100 p-3 border bg-white">
          <div class="d-flex align-items-center mb-2"><i class="bi bi-journal-text me-2 text-primary"></i><strong>Google Blogger</strong></div>
          <p class="small mb-0">Use Blogger for posts and pull entries into a flat‑file page. Keep editing where your team already works.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="h-100 p-3 border bg-white">
          <div class="d-flex align-items-center mb-2"><i class="bi bi-stars me-2 text-primary"></i><strong>Pixabay Stock</strong></div>
          <p class="small mb-0">Pick high‑quality stock images via API and embed in pages. Great for fast iteration.</p>
        </div>
      </div>
    </div>
    <!-- Forms + Integrations Row -->
    <div class="row g-4 mt-1">
      <div class="col-md-6 col-lg-3">
        <div class="h-100 p-3 border bg-white">
          <div class="d-flex align-items-center mb-2"><i class="bi bi-envelope-paper me-2 text-primary"></i><strong>Form Emailing</strong></div>
          <p class="small mb-0">Process form submissions and email details using clean HTML templates. Mailhog in dev.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="h-100 p-3 border bg-white">
          <div class="d-flex align-items-center mb-2"><i class="bi bi-cloud-upload me-2 text-primary"></i><strong>Salesforce</strong></div>
          <p class="small mb-0">Create Leads (or any sObject) via a simple field map. No plugin roulette.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="h-100 p-3 border bg-white">
          <div class="d-flex align-items-center mb-2"><i class="bi bi-person-plus me-2 text-primary"></i><strong>Mailchimp</strong></div>
          <p class="small mb-0">Subscribe/upsert with explicit field mapping and optional tags. Audience‑first, no guessing.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="h-100 p-3 border bg-white">
          <div class="d-flex align-items-center mb-2"><i class="bi bi-diagram-3 me-2 text-primary"></i><strong>Zapier</strong></div>
          <p class="small mb-0">Send mapped payloads to Zaps and fan out to thousands of apps with one hook.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Why This Beats WordPress for small sites -->
<section class="py-5">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <h2 class="h1 mb-3">Faster than WordPress. Seriously.</h2>
        <p class="mb-3">WordPress is a great blog engine—but for small marketing sites it’s often <strong>overkill</strong>: database setup, plugin roulette, security patches, and random theme quirks.</p>
        <ul class="list-unstyled small">
          <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><strong>No DB.</strong> Pages are Blade files in <code>resources/views/content</code>.</li>
          <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Fast routing.</strong> Allowed pages are pre‑validated and cached.</li>
          <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Forms included.</strong> Email + optional webhooks (Mailhog in dev).</li>
          <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Zero plugin drama.</strong> All code lives in your repo.</li>
        </ul>
      </div>
      <div class="col-lg-6">
        <div class="p-4 border bg-white">
          <h5 class="mb-2">Request Flow</h5>
          <ol class="small mb-0">
            <li>Request <code>/about</code> → <code>content/about.blade.php</code></li>
            <li>Middleware checks a pre‑built page list; invalid slugs 404 instantly</li>
            <li>Blade renders with your header/footer template</li>
          </ol>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Agent‑Ready Generation -->
<section class="py-5 bg-light">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-5">
        <h2 class="h1 mb-3">Agent‑Ready. One pass to a site.</h2>
        <p class="mb-3">Drop images, describe sections, run the brief wizard, and let your AI agent scaffold pages, forms, and emails—<strong>in a single go</strong>.</p>
        <a href="/features" class="btn btn-brand">Learn How</a>
      </div>
      <div class="col-lg-7">
        <div class="row g-3 small">
          <div class="col-md-6">
            <div class="p-3 border bg-white h-100">
              <h6 class="mb-1">Image‑Driven Pages</h6>
              <p class="mb-0">Put assets in <code>public/images</code> and/or provide a YAML manifest. The agent parses and builds hero/cards/galleries.</p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 border bg-white h-100">
              <h6 class="mb-1">Forms + Webhooks</h6>
              <p class="mb-0">New forms are a config entry. Email templates in <code>resources/views/emails</code>, optional webhook JSON included.</p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 border bg-white h-100">
              <h6 class="mb-1">Podium‑Ready</h6>
              <p class="mb-2">Run everything in containers with <a class="text-decoration-underline" href="https://github.com/CaneBayComputers/podium-cli" target="_blank" rel="noopener">Podium CLI</a>.</p>
              <pre class="small bg-light p-2 mb-0"><code>podium clone https://github.com/CaneBayComputers/laravel-flat-file-website.git
podium art app:agent-brief</code></pre>
              <p class="small mb-0">Clone Sitekit, run the Agent Brief wizard, and start customizing immediately.</p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 border bg-white h-100">
              <h6 class="mb-1">Safety First</h6>
              <p class="mb-0">Only whitelisted slugs resolve. Anything else never reaches your page handler.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

@endsection
