<html>
    <head>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <style>
          :root {
            --brand-primary: #0d6efd;
            --brand-dark: #0b1220;
            --brand-accent: #f97316; /* burnt orange */
            --brand-muted: #94a3b8;
          }

          .header-gradient { background: linear-gradient(105deg, var(--brand-primary), var(--brand-accent)); }
          /* Lighter, playful footer */
          .footer-gradient {
            background:
              radial-gradient(1000px 480px at 10% -10%, rgba(13,110,253,.18), transparent 60%),
              radial-gradient(800px 360px at 100% 0%, rgba(249,115,22,.18), transparent 60%),
              linear-gradient(180deg, #ffffff 0%, #faf5ff 55%, #eff6ff 100%);
            color: #0b1220;
          }
          .btn-brand { background: var(--brand-accent); border-color: var(--brand-accent); }
          .btn-brand:hover { background: #dc2748; border-color: #dc2748; }
          .wave-top svg { display:block; width:100%; height:64px; }
          .link-muted { color: var(--brand-muted); }
          /* Navbar links: brighter and legible over gradient */
          .navbar .nav-link { color: rgba(255,255,255,.95) !important; font-weight: 600; letter-spacing:.2px; }
          .navbar .nav-link:hover { color:#fff !important; text-decoration: underline; text-underline-offset: 3px; }
          .navbar .dropdown-menu { border-radius: .5rem; overflow: hidden; }

          /* 80's throwback shapes */
          .shape { position:absolute; pointer-events:none; opacity:.2; }
          .shape.triangle { width: 80px; height: 80px; transform: rotate(18deg); }
          .shape.circle { width: 90px; height: 90px; border-radius: 50%; background: radial-gradient(circle at 30% 30%, #fff, transparent 60%); }
          .shape.squiggle { width: 160px; height: 40px; }
          .footer-shapes { position: relative; overflow:hidden; }
          .footer-shapes .triangle { top: -30px; left: 8%; background: linear-gradient(135deg, var(--brand-accent), var(--brand-primary)); clip-path: polygon(50% 0%, 0% 100%, 100% 100%); }
          .footer-shapes .circle { top: -10px; right: 8%; background: radial-gradient(circle at 30% 30%, var(--brand-primary), transparent 60%); }
          .footer-shapes .squiggle { bottom: -12px; left: 35%; background:
            repeating-linear-gradient(135deg, var(--brand-accent) 0 6px, transparent 6px 12px);
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 40"><path d="M0,20 C20,0 40,40 60,20 C80,0 100,40 120,20 C140,0 160,40 180,20" stroke="black" stroke-width="14" fill="none"/></svg>') center/contain no-repeat;
          }
          /* Extra 80s lines */
          .footer-shapes::before { content:""; position:absolute; top:-20px; left:55%; width:220px; height:120px; opacity:.12;
            background: repeating-linear-gradient(45deg, var(--brand-primary) 0 4px, transparent 4px 12px);
            transform: rotate(-8deg); border-radius: 12px; }
          .footer-shapes::after { content:""; position:absolute; bottom:-30px; right:40%; width:260px; height:140px; opacity:.12;
            background: repeating-linear-gradient(-45deg, var(--brand-accent) 0 4px, transparent 4px 12px);
            transform: rotate(6deg); border-radius: 12px; }
          .footer-gradient h5, .footer-gradient a, .footer-gradient p, .footer-gradient li { color:#0b1220; }
          .footer-gradient a:hover { color: var(--brand-primary); }
        </style>
    </head>
    <body>

        <!-- Navigation Bar -->
        <nav class="navbar navbar-expand-lg navbar-dark header-gradient shadow-sm position-relative">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center gap-2" href="/" aria-label="Home" style="line-height:1;">
                    <svg width="260" height="54" viewBox="0 0 360 90" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Sitekit on Laravel" style="display:block;">
                      <defs>
                        <linearGradient id="lg" x1="0" x2="1" y1="0" y2="1">
                          <stop offset="0%" stop-color="#0d6efd"/>
                          <stop offset="100%" stop-color="#f97316"/>
                        </linearGradient>
                      </defs>
                      <!-- Abstract badge -->
                      <rect x="6" y="10" rx="12" ry="12" width="100" height="40" fill="url(#lg)" opacity="0.92"/>
                      <g opacity="0.95">
                        <circle cx="30" cy="32" r="8" fill="#fff"/>
                        <rect x="50" y="24" width="42" height="9" rx="5" fill="#fff"/>
                        <rect x="50" y="38" width="34" height="7" rx="4" fill="#ffedd5"/>
                      </g>
                      <!-- Wordmark -->
                      <text x="116" y="36" font-family="'Segoe UI', Tahoma, Arial, sans-serif" font-weight="900" font-size="30" fill="#ffffff" letter-spacing=".5">Sitekit</text>
                      <text x="116" y="56" font-family="'Segoe UI', Tahoma, Arial, sans-serif" font-weight="700" font-size="16" fill="#ffedd5" letter-spacing=".4">on Laravel</text>
                    </svg>
                    <span class="visually-hidden">Sitekit on Laravel</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="/">Home</a></li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="integrationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Integrations</a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="integrationsDropdown">
                                <li><a class="dropdown-item" href="/features-eventbrite">Eventbrite Events</a></li>
                                <li><a class="dropdown-item" href="/features-flickr">Flickr Galleries</a></li>
                                <li><a class="dropdown-item" href="/features-blogger">Google Blogger</a></li>
                                <li><a class="dropdown-item" href="/features-pixabay">Pixabay Images</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/features-forms">Forms &amp; Webhooks</a></li>
                                <li><a class="dropdown-item" href="/features-mailchimp">Mailchimp</a></li>
                                <li><a class="dropdown-item" href="/features-zapier">Zapier</a></li>
                                <li><a class="dropdown-item" href="/features-salesforce">Salesforce</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="examplesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Examples</a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="examplesDropdown">
                                <li><a class="dropdown-item" href="/events">Events</a></li>
                                <li><a class="dropdown-item" href="/reviews">Reviews</a></li>
                                <li><a class="dropdown-item" href="/services">Services</a></li>
                            </ul>
                        </li>
                        <!-- Removed standalone Events; available under Examples dropdown -->
                        <li class="nav-item"><a class="nav-link" href="/contact">Contact</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        
        @yield('content')
        
        <!-- Call to Action Section -->
        <div class="text-white py-5 text-center" style="background: linear-gradient(90deg, var(--brand-primary), #22d3ee);">
            <div class="container">
                <h2 class="mb-2">Ready to get started?</h2>
                <p class="mb-3">Use <a class="text-white text-decoration-underline" href="https://github.com/CaneBayComputers/podium-cli" target="_blank" rel="noopener">Podium&nbsp;CLI</a> to clone Sitekit, then run the Agent Brief wizard to generate a complete build prompt.</p>
                <div class="d-inline-block text-start bg-dark bg-opacity-10 border border-light-subtle rounded px-3 py-2 small mb-3">
<pre class="m-0"><code>podium clone https://github.com/CaneBayComputers/laravel-flat-file-website.git
cd laravel-flat-file-website
podium art app:agent-brief</code></pre>
                </div>
                <div class="mt-2">
                  <a href="/features" class="btn btn-lg btn-light me-2">See Features</a>
                  <a href="/contact" class="btn btn-lg btn-outline-light">Get in Touch</a>
                </div>
            </div>
        </div>
        <div class="wave-top" aria-hidden="true">
          <svg viewBox="0 0 1440 80" preserveAspectRatio="none"><path d="M0,32 C240,80 480,-16 720,16 C960,48 1200,16 1440,32 L1440,0 L0,0 Z" fill="var(--brand-primary)" opacity=".2"/></svg>
        </div>
        <!-- Footer -->
        <footer id="footer" class="text-white pt-5 pb-4 footer-gradient footer-shapes">
            <div class="shape triangle"></div>
            <div class="shape circle"></div>
            <div class="shape squiggle"></div>
            <div class="container">
                <div class="row g-4">
                    <div class="col-md-6 col-lg-4">
                        <h5 class="text-uppercase mb-2">About</h5>
                        <div class="mb-3" style="width: 64px; border-bottom: 2px solid var(--brand-accent);"></div>
                        <p class="small mb-3">A flat‑file Laravel starter with forms, email, and Redis‑backed content validation. Easily adapt for landing pages and small sites.</p>
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-geo-alt-fill me-2"></i>
                            <span>123 Main St, Anytown, USA</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-envelope-fill me-2"></i>
                            <a class="text-decoration-none" href="mailto:hello@example.com">hello@example.com</a>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-telephone-fill me-2"></i>
                            <span>(555) 123-4567</span>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <h5 class="text-uppercase mb-2">Quick Links</h5>
                        <div class="mb-3" style="width: 64px; border-bottom: 2px solid var(--brand-accent);"></div>
                        <ul class="list-unstyled small mb-0">
                            <li class="mb-2"><a href="/" class="text-decoration-none">Home</a></li>
                            <li class="mb-2"><a href="/features" class="text-decoration-none">Features</a></li>
                            <li class="mb-2"><a href="/services" class="text-decoration-none">Services</a></li>
                            <li class="mb-2"><a href="/reviews" class="text-decoration-none">Reviews</a></li>
                            <li class="mb-2"><a href="/contact" class="text-decoration-none">Contact</a></li>
                            <li class="mb-2"><a href="/terms-and-conditions" class="text-decoration-none">Terms &amp; Conditions</a></li>
                            <li><a href="/privacy-policy" class="text-decoration-none">Privacy Policy</a></li>
                        </ul>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <h5 class="text-uppercase mb-2">Stay in the loop</h5>
                        <div class="mb-3" style="width: 64px; border-bottom: 2px solid var(--brand-accent);"></div>
                        @if(session('success'))
                          <div class="alert alert-success py-2" role="alert">Thanks! We received your submission.</div>
                        @elseif(session('errors') && session('errors')->any())
                          <div class="alert alert-danger py-2">
                            <ul class="mb-0">
                              @foreach (session('errors')->all() as $error)
                                <li>{{ $error }}</li>
                              @endforeach
                            </ul>
                          </div>
                        @endif
                        <form class="row gy-3" action="/forms/signup" method="post" id="footer-signup-form">
                            @csrf
                            <div class="col-12">
                                <input type="email" name="email" maxlength="100" class="form-control" placeholder="Email address" required>
                            </div>
                            <input type="hidden" id="recaptcha-signup" name="recaptcha" value="">
                            <div class="col-12">
                                <button class="btn btn-light w-100" type="submit">Sign Up</button>
                            </div>
                        </form>
                        @push('script')
                        @if(!is_dev())
                        <script src="https://www.google.com/recaptcha/api.js?render={!! _c('form.recaptcha.site_key') !!}"></script>
                        <script>
                          document.getElementById('footer-signup-form').onsubmit = function(e) {
                            grecaptcha.ready(function() {
                              grecaptcha.execute('{!! _c('form.recaptcha.site_key') !!}', {action: 'submit'}).then(function(token) {
                                document.getElementById('recaptcha-signup').value = token;
                                e.target.submit();
                              });
                            });
                            return false;
                          };
                        </script>
                        @endif
                        @endpush
                    </div>
                </div>

                <hr class="border-secondary mt-4">
                <div class="text-center small link-muted">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</div>
            </div>
        </footer>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        @stack('script')
        
    </body>
</html>
