<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\{text, textarea, select, multiselect, confirm};

class GenerateAgentBrief extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:agent-brief
        {--output= : File path to save the generated brief (default: storage/app/agent-brief.txt)}
        {--no-file : Do not write a file, print only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactive wizard that gathers website details and generates an AI Agent prompt aligned with AGENTS.md.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Website Brief Wizard');
        $this->line('Answer a few questions to generate a turnkey AI Agent prompt.');

        // Core details
        $projectName = text('Project name', default: config('app.name', 'LaravelFlatFileWebsite'));
        $brand       = text('Brand/Company name', default: $projectName);
        $tagline     = text('Tagline (optional)', default: '');
        $goals       = textarea('Primary goals (comma-separated)', default: 'Inform, Generate leads');
        $audience    = textarea('Target audience (comma-separated)', default: 'Local customers, Prospects');

        // Content and pages
        $pages = multiselect(
            label: 'Pages to scaffold',
            options: [
                'home'     => 'Home',
                'about'    => 'About',
                'services' => 'Services',
                'reviews'  => 'Reviews/Testimonials',
                'contact'  => 'Contact',
            ],
            default: ['home', 'about', 'services', 'contact']
        );

        $pagesExtraRaw = text('Additional pages (comma-separated, e.g., blog, pricing)', default: '');
        $pages = array_values(array_unique(array_filter(array_merge($pages, $this->splitLines($pagesExtraRaw)))));

        $forms = multiselect(
            label: 'Forms to enable',
            options: [
                'contact'  => 'Contact (built-in)',
                'callback' => 'Callback request',
                'quote'    => 'Quote request',
            ],
            default: ['contact']
        );

        $formsExtraRaw = text('Additional form names (comma-separated, e.g., feedback, apply)', default: '');
        $forms = array_values(array_unique(array_filter(array_merge($forms, $this->splitLines($formsExtraRaw)))));

        $style = textarea('Visual style keywords (comma-separated)', default: 'Clean, Modern');
        // Color palette helper (Coolors-first)
        $this->line('Build a palette at https://coolors.co (Export → URL), then paste below.');
        $paletteUrl = text('Coolors palette URL (optional)', default: '');

        // If no Coolors URL, allow presets or custom hex list
        $colors = '';
        if ($paletteUrl === '') {
            $paletteChoice = select(
                label: 'Choose a color palette',
                options: [
                    'bootstrap' => 'Bootstrap Primary + Slate (#0d6efd, #111827, #f3f4f6)',
                    'emerald'   => 'Emerald + Slate (#10b981, #111827, #f9fafb)',
                    'indigo'    => 'Indigo + Rose + Slate (#6366f1, #ef4444, #111827, #f3f4f6)',
                    'amber'     => 'Amber + Charcoal (#f59e0b, #111827, #ffffff)',
                    'custom'    => 'Custom (enter hex list)',
                ],
                default: 'bootstrap'
            );

            $paletteMap = [
                'bootstrap' => '#0d6efd, #111827, #f3f4f6',
                'emerald'   => '#10b981, #111827, #f9fafb',
                'indigo'    => '#6366f1, #ef4444, #111827, #f3f4f6',
                'amber'     => '#f59e0b, #111827, #ffffff',
            ];

            if ($paletteChoice === 'custom') {
                $colors = text('Enter hex colors (comma-separated, e.g., #0d6efd, #111827, #f3f4f6)', default: $paletteMap['bootstrap']);
                $colors = $this->normalizeHexList($colors);
            } else {
                $colors = $paletteMap[$paletteChoice];
            }
        }

        // Images
        $imageStrategy = select(
            label: 'Image strategy',
            options: [
                'manifest' => 'Use YAML manifest (preferred)',
                'filenames' => 'Infer from filenames/aspect ratio',
                'placeholders' => 'Use placeholders (placehold.co)'
            ],
            default: 'manifest'
        );

        $imagesPath = text('Images directory (relative to project root)', default: 'public/images');
        $references = textarea('Example websites/pages (one per line or comma-separated)', default: '');

        // Simplified image plan
        $usePlaceholders = confirm('Use blank placeholders (placehold.co) for all images?', default: false);

        $localImages = [];
        $localCount = 0;
        $mixStock = false;
        $pixabayKeywords = [];
        $pixabayResults = [];

        if (! $usePlaceholders) {
            [$localImages, $localCount] = $this->findLocalImages($imagesPath);
            $mixStock = confirm("Found {$localCount} local image(s) in {$imagesPath}. Mix in stock photos from Pixabay?", default: false);

            if ($mixStock) {
                $pixabayEnabled = trim((string) config('services.pixabay.key')) !== '';
                if (! $pixabayEnabled) {
                    $this->warn('PIXABAY_API_KEY not configured; skipping Pixabay search.');
                } else {
                    $kwRaw = textarea('Pixabay search keywords (one per line)', default: "hero\nproduct\nteam");
                    $pixabayKeywords = $this->splitLines($kwRaw);
                    foreach ($pixabayKeywords as $kw) {
                        $urls = $this->pixabayFetchUrls($kw, 6);
                        if (!empty($urls)) {
                            $pixabayResults[] = [
                                'keyword' => $kw,
                                'urls' => $urls,
                            ];
                        }
                    }
                }
            }
        }

        // SEO and CTAs
        $ctas = textarea('Primary Calls to Action (comma-separated)', default: 'Contact Us, Get a Quote');
        $seo  = textarea('SEO keywords (comma-separated)', default: 'brand, services, location');

        // Mail/Contact
        $mailTo = text('Contact form recipient email (for .env CONTACT_FORM_MAIL_TO)', default: env('CONTACT_FORM_MAIL_TO', ''));

        // Commands section is covered by AGENTS.md; no need to ask/include here.

        // Business profile & online presence
        $phone   = text('Business phone (optional)', default: '');
        $address = textarea('Business address (optional)', default: '');
        $hours   = textarea('Business hours (optional)', default: '');

        $demoAge    = text('Target age range (optional, e.g., 25-45)', default: '');
        $demoSex    = text('Target genders (optional, e.g., all / women / men)', default: '');
        $demoRegion = text('Target geography (optional, e.g., city/region/country)', default: '');

        $hasExisting = confirm('Do you have an existing working website?', default: false);
        $siteExisting = '';
        if ($hasExisting) {
            $siteExisting = textarea('Existing website URLs (one per line or comma-separated)', default: '');
        }
        $socialLinkedIn  = text('LinkedIn URL (optional)', default: '');
        $socialFacebook  = text('Facebook URL (optional)', default: '');
        $socialInstagram = text('Instagram URL (optional)', default: '');
        $socialX         = text('X/Twitter URL (optional)', default: '');
        $socialYouTube   = text('YouTube URL (optional)', default: '');

        $wantLegal = multiselect(
            label: 'Generate legal pages?',
            options: [ 'privacy' => 'Privacy Policy', 'terms' => 'Terms & Conditions' ],
            default: []
        );

        // Per-page content strategy (auto-fill tone from Home if available)
        $pageBriefs = [];
        $homeTone = null;
        if (in_array('home', $pages, true)) {
            $pageBriefs['home'] = $this->askPageBrief('home');
            $homeTone = $pageBriefs['home']['tone'] ?? null;
        }
        foreach ($pages as $p) {
            if ($p === 'home') {
                continue;
            }
            $pageBriefs[$p] = $this->askPageBrief($p, $homeTone);
        }

        $brief = $this->buildPrompt([
            'projectName' => $projectName,
            'brand'       => $brand,
            'tagline'     => $tagline,
            'goals'       => $goals,
            'audience'    => $audience,
            'pages'       => $pages,
            'forms'       => $forms,
            'style'       => $style,
            'colors'      => $colors,
            'paletteUrl'  => $paletteUrl,
            'imageStrategy' => $imageStrategy,
            'imagesPath'  => $imagesPath,
            'references'  => $this->splitLines($references),
            'ctas'        => $ctas,
            'seo'         => $seo,
            'mailTo'      => $mailTo,
            'imagePlan'   => [
                'placeholders' => $usePlaceholders,
                'local'        => [
                    'path'    => $imagesPath,
                    'count'   => $localCount,
                    'samples' => array_slice($localImages, 0, 10),
                ],
                'mixStock'     => $mixStock,
                'pixabay'      => $pixabayResults,
            ],

            // business profile
            'phone'       => $phone,
            'address'     => $address,
            'hours'       => $hours,
            'demo'        => [ 'age' => $demoAge, 'sex' => $demoSex, 'region' => $demoRegion ],
            'existing'    => $hasExisting ? $this->splitLines($siteExisting) : [],
            'social'      => [
                'linkedin'  => $socialLinkedIn,
                'facebook'  => $socialFacebook,
                'instagram' => $socialInstagram,
                'x'         => $socialX,
                'youtube'   => $socialYouTube,
            ],
            'legal'       => $wantLegal,
            'pageBriefs'  => $pageBriefs,
        ]);

        $this->newLine();
        $this->info('--- BEGIN AGENT PROMPT ---');
        $this->line($brief);
        $this->info('--- END AGENT PROMPT ---');

        // Clipboard integration disabled per project preference.

        if (! $this->option('no-file')) {
            $path = $this->option('output') ?: storage_path('app/agent-brief.txt');
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $brief);
            $this->newLine();
            $this->comment('Saved to: ' . $path);
        }

        return self::SUCCESS;
    }

    private function buildPrompt(array $d): string
    {
        $pagesList = empty($d['pages']) ? 'home, about, services, contact' : implode(', ', $d['pages']);
        $formsList = empty($d['forms']) ? 'contact' : implode(', ', $d['forms']);

        $imageNotes = match ($d['imageStrategy']) {
            'manifest'   => "Use resources/content/<page>.yaml manifests to drive layout. If missing, fall back to filename/aspect heuristics.",
            'filenames'  => "Infer roles from filenames (e.g., home-hero-1.jpg, services-card-1.jpg) and aspect ratios.",
            default      => "Use placehold.co placeholders sized appropriately and wire helpers for future swap-in.",
        };

        // Override with simplified plan if provided
        $plan = $d['imagePlan'] ?? null;
        if (is_array($plan)) {
            if (!empty($plan['placeholders'])) {
                $imageNotes = 'Use blank placeholders from placehold.co for all images.';
            } else {
                $localCount = (int) ($plan['local']['count'] ?? 0);
                $imageNotes = "Use local images from {$d['imagesPath']} (found {$localCount}).";
                if (!empty($plan['mixStock']) && !empty($plan['pixabay'])) {
                    $imageNotes .= ' Mix in select stock images from Pixabay based on provided keywords.';
                }
            }
        }

        $tagline = trim((string) $d['tagline']) !== '' ? "Tagline: {$d['tagline']}\n" : '';
        $paletteUrl = trim((string) ($d['paletteUrl'] ?? ''));
        $refs = '';
        if (!empty($d['references'])) {
            $list = implode("\n- ", $d['references']);
            $refs = "\nReference websites/pages\n- {$list}\n";
        }

        $biz = '';
        $biz .= ($d['phone'] ?? '') !== '' ? "- Phone: {$d['phone']}\n" : '';
        $biz .= ($d['address'] ?? '') !== '' ? "- Address: {$d['address']}\n" : '';
        $biz .= ($d['hours'] ?? '') !== '' ? "- Hours: {$d['hours']}\n" : '';
        $demo = $d['demo'] ?? [];
        if (!empty($demo['age']) || !empty($demo['sex']) || !empty($demo['region'])) {
            $biz .= "- Demographics: ";
            $bits = array_filter([
                ($demo['age']   ?? '') !== '' ? 'Age ' . $demo['age'] : null,
                ($demo['sex']   ?? '') !== '' ? 'Genders ' . $demo['sex'] : null,
                ($demo['region'] ?? '') !== '' ? 'Region ' . $demo['region'] : null,
            ]);
            $biz .= implode(', ', $bits) . "\n";
        }
        $exist = '';
        if (!empty($d['existing'])) {
            $exist = "Existing site(s)\n- " . implode("\n- ", $d['existing']) . "\n";
        }

        $social = $d['social'] ?? [];
        $socialLines = [];
        foreach (['linkedin','facebook','instagram','x','youtube'] as $k) {
            if (!empty($social[$k])) {
                $socialLines[] = ucfirst($k) . ': ' . $social[$k];
            }
        }
        $socialBlock = empty($socialLines) ? '' : ("Social links\n- " . implode("\n- ", $socialLines) . "\n");

        // Page briefs as compact YAML-ish hints (supports one level nesting)
        $pagesYaml = '';
        if (!empty($d['pageBriefs'])) {
            $pagesYaml = "\nPages Detail (hints)\n";
            foreach ($d['pageBriefs'] as $slug => $pb) {
                $pagesYaml .= "- {$slug}:\n";
                foreach ($pb as $kk => $vv) {
                    if (is_array($vv)) {
                        if (!empty($vv)) {
                            $pagesYaml .= "  {$kk}:\n";
                            foreach ($vv as $subKey => $subVal) {
                                if (is_array($subVal)) {
                                    if (!empty($subVal)) {
                                        $pagesYaml .= "    {$subKey}:\n";
                                        foreach ($subVal as $item) {
                                            $item = is_scalar($item) ? (string) $item : json_encode($item);
                                            $pagesYaml .= "      - {$item}\n";
                                        }
                                    }
                                } else {
                                    $subVal = trim((string) $subVal);
                                    if ($subVal !== '') {
                                        $pagesYaml .= "    {$subKey}: {$subVal}\n";
                                    }
                                }
                            }
                        }
                    } else {
                        $vv = trim((string) $vv);
                        if ($vv !== '') {
                            $pagesYaml .= "  {$kk}: {$vv}\n";
                        }
                    }
                }
            }
        }

        // Detailed image sources block
        $imageSources = '';
        if (is_array($plan)) {
            if (!empty($plan['placeholders'])) {
                $imageSources .= "\nImage sources\n- Placeholders: placehold.co (blank placeholders)\n";
            } else {
                $samples = $plan['local']['samples'] ?? [];
                $imageSources .= "\nImage sources\n- Local images path: {$d['imagesPath']} (" . ((int)($plan['local']['count'] ?? 0)) . ")\n";
                if (!empty($samples)) {
                    foreach ($samples as $s) {
                        $imageSources .= "  - {$s}\n";
                    }
                }
                if (!empty($plan['pixabay'])) {
                    foreach ($plan['pixabay'] as $row) {
                        $kw = $row['keyword'] ?? '';
                        $urls = $row['urls'] ?? [];
                        if (!empty($urls)) {
                            $imageSources .= "- Pixabay [{$kw}]\n";
                            foreach ($urls as $u) {
                                $imageSources .= "  - {$u}\n";
                            }
                        }
                    }
                }
            }
        }

        return <<<PROMPT
Follow the AI Agent Onboarding Protocol in AGENTS.md. Read and summarize each file listed under Step 1 before continuing. Confirm adherence to conventions, then proceed.

Context
- Project: {$d['projectName']} (Brand: {$d['brand']})
{$tagline}- Goals: {$d['goals']}
- Audience: {$d['audience']}
- Pages to scaffold: {$pagesList}
- Forms to enable: {$formsList}
- Visual style: {$d['style']}
- Color palette: {$d['colors']}
 - Color palette: {$d['colors']}
 - Palette URL: {$paletteUrl}
- Images: {$d['imagesPath']} – Strategy: {$imageNotes}
{$imageSources}
- Primary CTAs: {$d['ctas']}
- SEO keywords: {$d['seo']}
- Contact recipient: {$d['mailTo']}
{$refs}
Business profile
{$biz}{$exist}{$socialBlock}{$pagesYaml}

Requirements
- Use Blade files under resources/views/content mapping to URL slugs (root-scoped links).
- Use flat-file routing and VerifyContentAccess backed by Redis.
- Respect helpers in app/helpers.php (cdn(), images(), is_dev(), etc.).
- For forms, reuse config/form.php patterns; POST to /forms/{name} via Form::process.
- Emails: use resources/views/emails/<name>.blade.php; multipart with text_view if available.
- After creating/removing pages, refresh content list.

Image-driven scaffolding
- If manifests exist (resources/content/<page>.yaml), render sections accordingly.
- Otherwise, parse filenames and aspect ratio to place images as hero, cards, headshots, or gallery.
- Keep alt text from tokens; use Title Case for human readability.

Deliverables
- Pages: {$pagesList} in resources/views/content/ with clear sections (hero, features/cards, gallery).
- Optional manifests in resources/content/ to allow non-technical edits.
- Updated config/form.php for any new forms; matching email views.
- Readme notes for any new commands or steps.
Quality
- Verify syntax: php -l on changed files.
- PHPCS with ~/.config/phpcs-ruleset.xml; PHPMD with ~/.config/phpmd.xml when available.
- Use placehold.co where images are missing.
- Keep edits minimal and consistent with project conventions.
PROMPT;
    }

    private function normalizeHexList(string $input): string
    {
        $parts = array_filter(array_map('trim', explode(',', $input)), fn ($v) => $v !== '');
        $valid = [];
        foreach ($parts as $p) {
            // Allow #RGB or #RRGGBB
            if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $p)) {
                $valid[] = strtolower($p);
            }
        }
        return implode(', ', $valid ?: ['#0d6efd', '#111827', '#f3f4f6']);
    }

    private function splitLines(string $input): array
    {
        $raw = preg_split('/[\n,]+/', $input) ?: [];
        $out = [];
        foreach ($raw as $r) {
            $r = trim($r);
            if ($r !== '') {
                $out[] = $r;
            }
        }
        return $out;
    }

    /**
     * Return [list, count] of local image files under the given relative path.
     */
    private function findLocalImages(string $relativePath): array
    {
        $abs = base_path(trim($relativePath, '/'));
        $list = [];
        if (File::exists($abs)) {
            $files = File::allFiles($abs);
            foreach ($files as $f) {
                $ext = strtolower($f->getExtension());
                if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg'], true)) {
                    // make path relative for readability in briefs
                    $list[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $f->getPathname());
                }
            }
        }
        return [$list, count($list)];
    }

    /**
     * Run the internal Pixabay search command and return a list of image URLs.
     */
    private function pixabayFetchUrls(string $keyword, int $perPage = 6): array
    {
        try {
            $code = Artisan::call('app:pixabay-search', [
                'query' => [$keyword],
                '--per_page' => $perPage,
                '--safesearch' => '1',
            ]);
            if ($code !== 0) {
                return [];
            }
            $raw = (string) Artisan::output();
            $json = json_decode($raw, true);
            if (!is_array($json) || !isset($json['hits'])) {
                return [];
            }
            $urls = [];
            foreach ($json['hits'] as $hit) {
                $u = $hit['largeImageURL'] ?? ($hit['webformatURL'] ?? null);
                if (is_string($u) && $u !== '') {
                    $urls[] = $u;
                }
            }
            return $urls;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function askPageBrief(string $slug, ?string $defaultTone = null): array
    {
        $pretty = ucwords(str_replace(['-', '_'], ' ', $slug));
        $this->newLine();
        $this->info("Page brief: {$pretty} ({$slug})");

        // 1) Choose sections first
        $sections = multiselect(
            label: "Sections to include on {$pretty}",
            options: [
                'hero' => 'Hero',
                'features' => 'Features / Cards',
                'gallery' => 'Gallery',
                'testimonials' => 'Testimonials',
                'faq' => 'FAQ',
                'cta' => 'Call to Action',
                'contact' => 'Contact block',
            ],
            default: ['hero','features','cta']
        );

        // 2) Ask for details by section (all optional)
        $tone = text("Desired tone (e.g., friendly, expert, high-energy) for {$pretty}", default: $defaultTone ?? '');
        $summary = textarea("General description/purpose for {$pretty} (optional, freeform)", default: '');

        $hero = [];
        if (in_array('hero', $sections, true)) {
            $hero['headline'] = text("Hero headline for {$pretty} (optional)", default: '');
            $hero['subhead']  = text("Hero subheadline for {$pretty} (optional)", default: '');
        }

        $features = [];
        if (in_array('features', $sections, true)) {
            $features['highlights'] = $this->splitLines(
                textarea("Top highlights/value props for {$pretty} (one per line, optional)", default: '')
            );
        }

        $gallery = [];
        if (in_array('gallery', $sections, true)) {
            $gallery['notes'] = text("Gallery notes (optional, e.g., theme or sources)", default: '');
        }

        $testimonials = [];
        if (in_array('testimonials', $sections, true)) {
            $testimonials['items'] = $this->splitLines(
                textarea("Testimonials (one per line, optional)", default: '')
            );
        }

        $faq = [];
        if (in_array('faq', $sections, true)) {
            $faq['qas'] = $this->splitLines(
                textarea("FAQs (one per line, format: Question - Answer, optional)", default: '')
            );
        }

        $ctaText = '';
        if (in_array('cta', $sections, true)) {
            $ctaText = text("Primary Call-to-Action text for {$pretty} (optional)", default: '');
        }

        return array_filter([
            'sections'     => $sections,
            'tone'         => $tone,
            'summary'      => $summary,
            'hero'         => $hero,
            'features'     => $features,
            'gallery'      => $gallery,
            'testimonials' => $testimonials,
            'faq'          => $faq,
            'cta'          => $ctaText,
        ], function ($v) {
            if (is_array($v)) {
                return !empty($v);
            }
            return trim((string) $v) !== '';
        });
    }

    // Clipboard/agent integration removed
}
