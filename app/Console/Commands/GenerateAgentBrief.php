<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class GenerateAgentBrief extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:agent-brief
        {--output= : File path to save the generated brief (default: storage/app/private/agent-brief.txt)}';

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

        $userImagesDir = storage_path('app/private/images/user');
        if (File::exists($userImagesDir)) {
            $userImages = File::allFiles($userImagesDir);

            $nonGitignore = array_filter($userImages, static function ($file) {
                return $file->getFilename() !== '.gitignore';
            });

            if (count($nonGitignore) === 0) {
                $this->newLine();
                $this->warn('No user images detected in storage/app/private/images/user (only .gitignore found).');
                $this->line('This folder is reserved for your own images and .zip archives.');
                $this->line('The image manifest and AI agents will treat provider = "user" assets here as first-class visuals for heroes, galleries, and key sections.');
                $this->newLine();
                $this->line('It is recommended to:');
                $this->line('- Add your images (or .zip files) into storage/app/private/images/user');
                $this->line('- Then run: podium art app:images-manifest');
                $this->line('You may press Ctrl+C now to add files, then re-run this wizard; or continue with no user images configured.');
                $this->newLine();
            }
        }

        // Core details
        $projectName = $this->ask('Website name/title', config('app.name', 'LaravelFlatFileWebsite'));
        $brand       = $this->ask('Organization or brand name (optional, can be the same as the website name)', '');

        $websiteOverview = $this->ask(
            'In a sentence or two, what do you want your website to be about?',
            ''
        );

        $goals    = $this->ask('Primary goals (comma-separated)', 'Inform, Generate leads');
        $audience = $this->ask('Target audience (comma-separated)', 'Local customers, Prospects');

        // Content and pages
        $pagesRaw = $this->ask(
            'Main pages (comma-separated, e.g., home, about, services, contact)',
            'home, about, services, contact'
        );
        $pages = $this->splitLines($pagesRaw);

        $formsDefault = in_array('contact', $pages, true) ? 'contact' : '';
        $formsRaw = $this->ask(
            'Forms to enable (comma-separated, e.g., contact, callback, quote, none)',
            $formsDefault
        );
        $forms = $this->splitLines($formsRaw);
        if (in_array('none', $forms, true)) {
            $forms = [];
        }

        $style = $this->ask('Visual style keywords (comma-separated)', 'Clean, Modern');

        $colorChoices = [
            'I will type custom colors' => 'custom',
            'Auto-pick a palette'       => 'auto',
        ];

        $colorChoiceLabel = $this->choice(
            'How should brand colors be chosen?',
            array_keys($colorChoices),
            'I will type custom colors'
        );

        $colorSource = $colorChoices[$colorChoiceLabel] ?? 'custom';

        $colors = '';

        if ($colorSource === 'custom') {
            $colors = $this->ask(
                'Brand colors (comma-separated, e.g., "#0d6efd, #111827, #f3f4f6" or "navy, cream, soft gray")',
                '#0d6efd, #111827, #f3f4f6'
            );
        } else {
            $fallbackPalette = [
                [13, 110, 253],  // #0d6efd
                [17, 24, 39],    // #111827
                [243, 244, 246], // #f3f4f6
                [16, 185, 129],  // #10b981
                [249, 250, 251], // #f9fafb
            ];

            $this->line('Fetching a suggested palette from http://colormind.io ...');

            while ($colors === '') {
                $palette = null;
                $hexColors = [];

                try {
                    $response = Http::timeout(8)->post('http://colormind.io/api/', [
                        'model' => 'default',
                    ]);

                    $palette = $response->json('result');
                } catch (\Throwable $e) {
                    $this->warn('Unable to reach Colormind.io. Using a local fallback palette instead.');
                    $palette = $fallbackPalette;
                }

                if (! is_array($palette)) {
                    $this->warn('Colormind.io did not return a usable palette; using fallback palette.');
                    $palette = $fallbackPalette;
                }

                $this->newLine();
                $this->info('Proposed palette:');

                foreach ($palette as $rgb) {
                    if (! is_array($rgb) || count($rgb) < 3) {
                        continue;
                    }

                    [$r, $g, $b] = $rgb;

                    $bar = sprintf(
                        "\e[48;2;%s;%s;%sm%'-60s\e[0m",
                        $r,
                        $g,
                        $b,
                        ''
                    );

                    $this->line($bar . "  <fg=white>{$r},{$g},{$b}</>");
                    $hexColors[] = sprintf('#%02x%02x%02x', (int) $r, (int) $g, (int) $b);
                }

                if (empty($hexColors)) {
                    $this->warn('Generated palette was empty; falling back to manual entry.');
                    break;
                }

                $proposed = implode(', ', $hexColors);
                $this->newLine();
                $this->line('Suggested hex colors: ' . $proposed);

                if ($this->confirm('Use this palette?', true)) {
                    $colors = $proposed;
                    $this->line('Using suggested palette: ' . $colors);
                    break;
                }

                if (! $this->confirm('Try another palette?', true)) {
                    $this->line('Okay, switching to manual color entry.');
                    break;
                }
            }

            if ($colors === '') {
                $colors = $this->ask(
                    'Brand colors (comma-separated, e.g., "#0d6efd, #111827, #f3f4f6" or "navy, cream, soft gray")',
                    '#0d6efd, #111827, #f3f4f6'
                );
            }
        }

        // Images (layout strategy is manifest-first per AGENTS.md)
        $imageStrategy = 'manifest';

        $imagesPath = 'auto (use manifest + storage/app/private/images)';
        $references = $this->ask('Example websites/pages (comma-separated or one per line)', '');

        // Simplified image plan
        $usePlaceholders = $this->confirm('Do you want to just use image placeholders?', false);
        $useStockBackgrounds = false;

        if (! $usePlaceholders) {
            $useStockBackgrounds = $this->confirm('Do you want to use stock images for backgrounds?', true);
        }

        $useSubjectStock = $this->confirm('Do you want to pull subject relevant stock images?', true);

        // SEO and CTAs
        $ctas = $this->ask('Primary Calls to Action (comma-separated)', 'Contact Us, Get a Quote');
        $seo  = $this->ask('SEO keywords (comma-separated)', 'brand, services, location');

        // Mail/Contact
        $mailTo = $this->ask(
            'Contact form recipient email (for .env CONTACT_FORM_MAIL_TO)',
            (string) env('CONTACT_FORM_MAIL_TO', '')
        );

        // Commands section is covered by AGENTS.md; no need to ask/include here.

        // Business profile & online presence
        $businessName = $this->ask('Business name (optional)', '');
        $phone        = $this->ask('Business phone (optional)', '');
        $address      = $this->ask('Business address (optional)', '');
        $cityRegion   = $this->ask('Business city, state, zip (optional, one line)', '');
        $hours        = $this->ask('Business hours (optional)', '');

        $demoAge    = $this->ask('Target age range (optional, e.g., 25-45 or "all")', '');
        $demoSex    = $this->ask('Target genders (optional, e.g., all / women / men)', '');
        $demoRegion = $this->ask('Target geography (optional, e.g., city name or "anywhere")', '');

        $socialLinkedIn = $this->ask(
            'LinkedIn profile (username, handle, or URL, optional)',
            ''
        );
        $socialFacebook = $this->ask(
            'Facebook page/profile (username, handle, or URL, optional)',
            ''
        );
        $socialInstagram = $this->ask(
            'Instagram account (username, handle, or URL, optional)',
            ''
        );
        $socialX = $this->ask(
            'X/Twitter account (username, handle, or URL, optional)',
            ''
        );
        $socialYouTube = $this->ask(
            'YouTube channel (name, handle, or URL, optional)',
            ''
        );

        $legalRaw = $this->ask(
            'Generate legal pages? (comma-separated choices: privacy, terms, or leave blank)',
            ''
        );
        $wantLegal = $this->splitLines($legalRaw);

        $brief = $this->buildPrompt([
            'projectName' => $projectName,
            'brand'       => $brand,
            'overview'    => $websiteOverview,
            'goals'       => $goals,
            'audience'    => $audience,
            'pages'       => $pages,
            'forms'       => $forms,
            'style'       => $style,
            'colors'      => $colors,
            'imageStrategy' => $imageStrategy,
            'imagesPath'  => $imagesPath,
            'references'  => $this->splitLines($references),
            'ctas'        => $ctas,
            'seo'         => $seo,
            'mailTo'      => $mailTo,
            'imagePlan'    => [
                'placeholders'      => $usePlaceholders,
                'stock_backgrounds' => $useStockBackgrounds,
                'subject_stock'     => $useSubjectStock,
            ],

            // business profile
            'businessName' => $businessName,
            'phone'        => $phone,
            'address'      => $address,
            'cityRegion'   => $cityRegion,
            'hours'        => $hours,
            'demo'         => [
                'age'    => $demoAge,
                'sex'    => $demoSex,
                'region' => $demoRegion,
            ],
            'social' => [
                'linkedin'  => $socialLinkedIn,
                'facebook'  => $socialFacebook,
                'instagram' => $socialInstagram,
                'x'         => $socialX,
                'youtube'   => $socialYouTube,
            ],
            'legal'  => $wantLegal,
        ]);

        $this->newLine();
        $this->info('--- BEGIN AGENT PROMPT ---');
        $this->line($brief);
        $this->info('--- END AGENT PROMPT ---');

        // Clipboard integration disabled per project preference.

        $path = $this->option('output') ?: storage_path('app/private/agent-brief.txt');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $brief);
        $this->newLine();
        $this->comment('Saved to: ' . $path);

        return self::SUCCESS;
    }

    private function buildPrompt(array $d): string
    {
        $pagesList = empty($d['pages']) ? 'home, about, services, contact' : implode(', ', $d['pages']);
        $formsList = empty($d['forms']) ? 'contact' : implode(', ', $d['forms']);

        $imageNotes = match ($d['imageStrategy']) {
            'manifest'   => "Use resources/content/<page>.yaml manifests to drive layout. If missing, fall back to filename/aspect heuristics.",
            default      => "Infer roles from filenames (e.g., home-hero-1.jpg, services-card-1.jpg) and aspect ratios.",
        };

        // Override with simplified plan if provided
        $plan = $d['imagePlan'] ?? null;
        if (is_array($plan)) {
            if (!empty($plan['placeholders'])) {
                $imageNotes = 'Use blank placeholders from placehold.co for all images; no real photos are required.';
            } else {
                $extras = [];

                if (!empty($plan['stock_backgrounds'])) {
                    $extras[] = 'Use stock photos for large section backgrounds where they clearly help the design.';
                }

                if (!empty($plan['subject_stock'])) {
                    $extras[] = 'Pull subject-relevant stock images (Pixabay, Pexels, Unsplash) before building the image manifest.';
                }

                if (!empty($extras)) {
                    $imageNotes .= ' ' . implode(' ', $extras);
                }
            }
        }

        $refs = '';
        if (!empty($d['references'])) {
            $list = implode("\n- ", $d['references']);
            $refs = "\nReference websites/pages\n- {$list}\n";
        }

        $biz = '';
        $biz .= ($d['businessName'] ?? '') !== '' ? "- Business: {$d['businessName']}\n" : '';
        $biz .= ($d['phone'] ?? '') !== '' ? "- Phone: {$d['phone']}\n" : '';
        $biz .= ($d['address'] ?? '') !== '' ? "- Address: {$d['address']}\n" : '';
        $biz .= ($d['cityRegion'] ?? '') !== '' ? "- City/Region: {$d['cityRegion']}\n" : '';
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
        $social = $d['social'] ?? [];
        $socialLines = [];
        foreach (['linkedin','facebook','instagram','x','youtube'] as $k) {
            if (!empty($social[$k])) {
                $socialLines[] = ucfirst($k) . ': ' . $social[$k];
            }
        }
        $socialBlock = empty($socialLines) ? '' : ("Social links\n- " . implode("\n- ", $socialLines) . "\n");

        // Detailed image sources block / stock workflow guidance
        $imageSources = '';
        if (is_array($plan)) {
            $imageSources .= "\nImage sources & stock workflow\n";

            if (!empty($plan['placeholders'])) {
                $imageSources .= "- Use placehold.co placeholders for all images.\n";
            } else {
                $useBackgrounds = !empty($plan['stock_backgrounds']);
                $useSubject     = !empty($plan['subject_stock']);

                if ($useBackgrounds && $useSubject) {
                    $imageSources .= "- Fetch background relevant images via:\n";
                    $imageSources .= "  - php artisan app:pixabay-search \"<background keywords>\"\n";
                    $imageSources .= "  - php artisan app:pexels-search \"<background keywords>\"\n";
                    $imageSources .= "  - php artisan app:unsplash-search \"<background keywords>\"\n";
                    $imageSources .= "  (or the equivalent podium art commands in this environment)\n";
                    $imageSources .= "- Fetch subject relevant images via:\n";
                    $imageSources .= "  - php artisan app:pixabay-search \"<subject keywords>\"\n";
                    $imageSources .= "  - php artisan app:pexels-search \"<subject keywords>\"\n";
                    $imageSources .= "  - php artisan app:unsplash-search \"<subject keywords>\"\n";
                    $imageSources .= "  (or the equivalent podium art commands in this environment)\n";
                    $imageSources .= "- After fetching stock images, run php artisan app:images-manifest --rekog (or podium art app:images-manifest --rekog) so the manifest includes them before generating or updating Blade templates.\n";
                } else {
                    if ($useBackgrounds) {
                        $imageSources .= "- Use stock images primarily for large section backgrounds (heroes, full-width bands). Fetch them via:\n";
                        $imageSources .= "  - php artisan app:pixabay-search \"<background keywords>\"\n";
                        $imageSources .= "  - php artisan app:pexels-search \"<background keywords>\"\n";
                        $imageSources .= "  - php artisan app:unsplash-search \"<background keywords>\"\n";
                        $imageSources .= "  (or the equivalent podium art commands in this environment)\n";
                    } else {
                        $imageSources .= "- Prefer user and local images for most sections; use stock sparingly.\n";
                    }

                    if ($useSubject) {
                        $imageSources .= "- When stock is needed for key subjects, fetch subject-relevant images via:\n";
                        $imageSources .= "  - php artisan app:pixabay-search \"<subject keywords>\"\n";
                        $imageSources .= "  - php artisan app:pexels-search \"<subject keywords>\"\n";
                        $imageSources .= "  - php artisan app:unsplash-search \"<subject keywords>\"\n";
                        $imageSources .= "  (or the equivalent podium art commands in this environment)\n";
                        $imageSources .= "- After fetching subject stock images, run php artisan app:images-manifest --rekog (or podium art app:images-manifest --rekog) so the manifest includes them before generating or updating Blade templates.\n";
                    }
                }
            }
        }

        if ($imageSources !== '') {
            $imageSources .= "- For additional image usage guidance, follow the manifest located at storage/app/private/images/manifest.json.\n";
        }

        return <<<PROMPT
You are operating on the Kavera flat-file Laravel project.

Operational rules (must obey)
- Before changing anything, you must:
  - Read AGENTS.md and follow every rule.
  - Treat resources/examples as reference only; never clone or modify it.
- Treat this brief as the authoritative project profile and do not re-ask the core onboarding questions (site name, purpose, main pages, image strategy).
- Only ask additional questions if something critical is missing or ambiguous and not covered by this brief or AGENTS.md.
- All generated content must:
  - Set \$pageTitle and \$pageDescription at the top of each page per AGENTS.md instructions.
  - Create a JSON-LD on the home/index page at a minimum and follow implementation instructions found in AGENTS.md.
  - Use the image manifest for picking images and writing alt text.
  - Use the existing Bootstrap-based layout and utility classes; do not introduce a different CSS framework unless explicitly requested.
  - Never change routing logic in routes/web.php unless the user explicitly requests routing changes.

Follow the AI Agent Onboarding Protocol in AGENTS.md. Read and summarize each file listed under Step 1 before continuing. Confirm adherence to conventions, then proceed.

Context
- Project: {$d['projectName']} (Brand: {$d['brand']})
- Website overview: {$d['overview']}
- Goals: {$d['goals']}
- Audience: {$d['audience']}
- Pages to scaffold: {$pagesList}
- Forms to enable: {$formsList}
- Visual style: {$d['style']}
- Color palette: {$d['colors']}
- Image strategy: {$imageNotes}
{$imageSources}
- Primary CTAs: {$d['ctas']}
- SEO keywords: {$d['seo']}
- Contact recipient: {$d['mailTo']}
{$refs}
Business profile
{$biz}{$socialBlock}
PROMPT;
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

    // Clipboard/agent integration removed
}
