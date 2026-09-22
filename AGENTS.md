## AI Agent Onboarding

### Purpose

This section defines how any AI agent (e.g., Codex, Cursor, Aider, or GPT CLI) should **initialize** and **learn** the project structure before performing any edits, analysis, or code generation.

---

### Framework Overview

Kavera is a Laravel-based website framework that uses flat-file Blade templates
for static pages and retrieves dynamic content from external services. Static
pages are located in `/resources/views/content` and can be created, edited, or
removed directly. Dynamic content can be periodically pulled from integrated
services and stored in the Laravel cache (file store by default; Redis or a
database are optional) so that templates can access it without calling
external APIs at runtime. Kavera needs no database server to run.

Kavera does not use a traditional CMS. There is no admin panel for page
creation. The primary method for defining site structure is creating or editing
Blade files, partial components and templates manually or via an AI agent.

Dynamic content sources included and pre-built in this project:
- Blogger: Used for posts, articles, news, press releases, staff lists, or any
  repeatable content collection. Pulled through the Blogger API into the cache.
- Eventbrite: Event data is fetched and normalized into the cache for display on
  event or calendar pages.
- Flickr: Public albums are synced into the cache so a photo pushed to an album
  from the Flickr phone app appears in the site gallery on the next sync.
- Pixabay, Pexels and Unsplash Search: Images can be pulled via CLI command and
  stored locally for use in page templates.

All static content is edited in Blade templates. All dynamic collections are
read from the cache. Do not attempt to modify content through a CMS interface,
as none exists. Keep HTML structure semantic and rely on existing layout and
utility classes.

**KEY CONCEPT: `resources/views` is the real site, `resources/examples` is reference only.** `resources/views` ships as a small neutral starter tree (`templates/main`, `templates/blog`, `content/index`, `content/contact`, `content/gallery`, `content/blog/index`, `emails/contact`, `jsonld/index.jsonld`) with placeholder copy. Build the user's site by editing and extending that tree. `resources/examples` mirrors the same structure and shows how every feature is wired (forms, JSON-LD, blog listings, Eventbrite, Flickr galleries, stock images); copy patterns from it, never edit it, and never copy its Kavera marketing copy into a real site. Keeping the examples folder intact also avoids merge conflicts when pulling updates from the parent Kavera repo.


---

### Flat-file CMS Architecture / Content Creation

* Blade templates in `resources/views/content` correspond to URL slugs.
* Routes are defined in `routes/web.php`.
* Middleware `VerifyContentAccess` checks the cached page registry (`content_list` in the default cache store).
* Controller: `PageController` renders approved views.
* Refresh the page registry whenever content files are added, removed, or renamed:

  ```bash
  php artisan app:update-content-list
  ```

* All links must use root-scoped anchors (e.g., `/` or `/#contact`) for navigation consistency.
* Custom helpers live in `app/helpers.php`.

**Code Style**

* Blade filenames: lowercase-with-dashes
* PHP: PSR-12 / Laravel defaults

  * 4-space indent
  * PascalCase classes
  * camelCase methods
* Maintain generous blank lines for readability; preserve spacing in edits.


---

### Agent Website Generation and Suggested User Input

When a user says something like “make me a website” (without enough detail), agents **must not** immediately start editing files and copying the example site. Instead:

1) Ask for minimal but targeted context first

- Reply with a short clarification instead of editing right away, for example:

  > “I can do that. To generate a first version that looks like your site (not just the demo), I need a few basics:  
  > 1) Site name  
  > 2) What the site is for (e.g. personal cars, portfolio, business, blog)  
  > 3) Main pages you want (Home, About, Garage, Contact, etc.)  
  > 4) Whether to use the existing images + manifest as the primary visual source.  
  >  
  > You can answer in one short sentence if you like, and I’ll fill in the rest.”

- Only after getting those answers (or an explicit “just pick something reasonable”) should the agent start creating or rewriting views/layouts.

2) Do not clone the examples verbatim

- Treat everything under `resources/examples` as **reference only**:
  - Use them to learn **how** to wire features (blog, JSON‑LD, SEO tags, layout patterns, forms).
  - Do **not** leave Kavera marketing text, demo nav links, or example copy in a site that is meant to be “real” for the user.
- For real sites:
  - Replace or heavily adapt the main layout (`templates.main`) so it reflects the user’s project (site name, nav, footer), not the Kavera starter copy.
  - Do not surface example routes (like `/integrations`, `/features`, etc.) in the primary navigation unless the user explicitly wants them.

3) Use the image manifest and layout images as primary design input

- Always consult `storage/app/private/images/manifest.json` when generating or refactoring content if exists:
  - Prefer `provider = "user"` images for hero and key sections.
  - Use `orientation`, `aspect_ratio`, and `original_path` to decide placement (hero vs. card vs. background).
  - Use the colors the user supplied (or colors sampled from their logo and hero images) as cues for backgrounds, accents, or SVG decorations.
- Also check `storage/app/private/images/layout`:
  - Images here may represent full‑page or section layout concepts (e.g. exports from Photoshop/Illustrator).
  - Directory structure and filenames may hint at intended usage (e.g. `home-hero-layout`, `about-section-02`); respect those hints when mapping to sections.

4) JSON‑LD and SEO are not optional

- JSON‑LD:
  - Implement at least one JSON‑LD block for the home (`index`) page using the pattern from the examples:
    - Author JSON‑LD in `resources/views/jsonld/index.jsonld`.
    - Include it via:

      ```blade
      @section('jsonld')
      {!! file_get_contents(resource_path('views/jsonld/index.jsonld')) !!}
      @endsection
      ```

  - When creating additional important pages (e.g., Garage, About), add JSON‑LD where it makes sense (Person, WebSite, Vehicle, etc.) if the user’s intent is clear enough.

- SEO meta:
  - For **every** new or modified page under `resources/views/content`, set `$pageTitle` and `$pageDescription` at the top as documented earlier so the main layout can render proper head tags.
  - Titles and descriptions must be specific to that page (no leftover example text).

5) Alt text for images

- Every `<img>` tag must have a meaningful `alt`:
  - Derive it from:
    - `original_path` (e.g. `my cars/1989 Chevy Cavalier.png` → “1989 Chevy Cavalier in a driveway”).
    - `content_analysis.description` when present (an AI-written caption from `--describe`), refined with its `subjects`, `text` and `people` fields.
    - Folder structure (e.g. `layout/home-hero-01` suggests “Full‑page hero layout concept for home page”).
  - Never leave empty or generic alts like “image” unless the image is clearly decorative and the layout already conveys the same information.

6) CSS framework and layout styling

- If the user does **not** specify a CSS framework:
  - Default to Bootstrap, using the existing CDN setup in the main layout.
  - Do **not** introduce another framework (Tailwind, Bulma, etc.) without an explicit request.
- Be visually creative, not just functional:
  - Use sectional backgrounds (solid colors, subtle gradients, muted bands) inspired by the image manifest’s foreground/background colors.
  - Consider simple SVG shapes or dividers (e.g., curves, diagonals, soft geometric overlays) that match the site’s theme (cars, roads, motion), while keeping HTML semantic and accessible.

7) Creativity over boilerplate

- Do not generate a site that is just stacked white sections with identical typography.
- Use the project’s data (images, colors, filenames, folder structure) to drive:
  - Section hierarchy (hero, timeline, gallery, stories).
  - Visual emphasis (which cars or images get hero placement vs. card placement).
  - Subtle thematic touches (e.g., horizontal “road” dividers, gauge‑like chips, color swatches).

Following these rules should prevent agents from blindly cloning the example site and instead push them to:
- Ask a minimal set of clarifying questions,
- Use the manifest and layout images as first‑class design input,
- Implement JSON‑LD + SEO + alt text consistently,
- And build a site that actually feels like the user’s project.

---

### Image Ingest, Optimization & Manifest Pipeline

Agents should treat images as a first‑class data source for page generation. This repo ships with an ingest pipeline that collects images from providers, optimizes assets, and builds an AI‑friendly manifest.

**1) Where images live**

- Private originals are stored under `storage/app/private/images` in these provider folders:
  - `pexels/`, `pixabay/`, `unsplash/`, and `user/`.
- Provider folders:
  - Each search run is saved into a subfolder named after the original query terms, e.g. `storage/app/private/images/pixabay/sports-car-night-20251119_151633/...`.
  - The subfolder name (minus trailing timestamp tokens) becomes `original_query_terms` for those images.
- User folder:
  - `storage/app/private/images/user` may contain loose images and `.zip` files.
  - `.zip` files are extracted into a `_zip_<uuid>` subfolder, then deleted; the extracted folder structure is kept to hint at intended usage.

**2) Provider import commands**

- Pixabay search and download:
  - `php artisan app:pixabay-search "<search terms...>"`
  - Writes JPEGs into `storage/app/private/images/pixabay/<slug-timestamp>/...` and merges Pixabay tags into JPEG XMP (keywords/subjects) where possible.
- Pexels search:
  - `php artisan app:pexels-search "<search terms...>"`
  - Saves Pexels images under `storage/app/private/images/pexels/<slug-timestamp>/...` and stores the Pexels `alt` text into XMP dc:description when available.
- Unsplash search:
  - `php artisan app:unsplash-search "<search terms...>"`
  - Saves Unsplash images under `storage/app/private/images/unsplash/<slug-timestamp>/...` and embeds a combined description `(description + alt_description)` into XMP dc:description when present.

**3) Building the image manifest**

- Main command:

  ```bash
  php artisan app:images-manifest
  # Also have an AI vision model describe every image (see section 6):
  # php artisan app:images-manifest --describe
  ```

- What it scans:
  - Recursively walks `storage/app/private/images/{pexels,pixabay,unsplash,user}`.
  - Skips unsupported formats and `*.svg` files.
  - For `user`:
    - Detects `.zip` archives, extracts them once into `_zip_<uuid>` folders, deletes the original `.zip`, and processes all extracted images.

**4) WebP optimization & URLs**

- For each original:
  - If the longest side is `< 480px`:
    - Generates a single WebP at original size under `storage/app/public/images/optimized/small/{id}`.
    - `available_sizes` will contain the original long‑side value (e.g. `[420]`).
  - Otherwise:
    - Generates WebP variants at up to `1920`, `1280`, `768`, and `480` pixels on the long side (never upscaling).
    - Landscape: target is width; portrait: target is height.
    - Each variant is saved under `storage/app/public/images/optimized/{size}/{id}`.
- Filenames & idempotency:
  - `hash = sha1(original file bytes)`.
  - `id = "<hash>.webp"` is used for all optimized variants and as the per‑image manifest id.
  - If manifest entry + optimized files already exist for a given hash, the command skips reprocessing unless `--force` is provided.
- Public URLs:
  - For any manifest entry:

    ```text
    /storage/images/optimized/{available_size}/{id}
    ```

    where `{available_size}` is one of the sizes listed in `available_sizes` (or `small` for very small originals).

**5) Manifest structure (AI‑facing)**

- Manifest file:
  - Location: `storage/app/private/images/manifest.json`.
  - Shape:

    ```json
    {
      "notes": [
        "Use and utilize all pictures where provider = 'user'.",
        "Adhere to any user image directory structure and file naming to infer intended page usage.",
        "Public image URLs are /storage/images/optimized/{available_size}/{id}."
      ],
      "images": [
        {
          "id": "<sha1>.webp",
          "hash": "<sha1_of_original>",
          "provider": "pexels|pixabay|unsplash|user",
          "original_path": "relative/path/under/provider.ext",
          "orientation": "landscape|portrait|square|banner",
          "aspect_ratio": 1.5,
          "original_query_terms": ["sports", "car", "night"],
          "provider_keywords": [...],
          "provider_description": "..." | null,
          "available_sizes": [1920, 1280, 768, 480], // or subset / small-only
          "content_analysis": { // only after --describe, otherwise null
            "description": "A yellow-green BMW coupe parked between silver cars at night.",
            "subjects": ["sports coupe", "parked cars", "parking lot"],
            "colors": ["charcoal black (#111716)", "dark gray (#414a4b)"],
            "text": [],
            "people": 0,
            "provider": "anthropic|openai", "model": "...", "analyzed_at": "..."
          }
        }
      ]
    }
    ```

- Provider‑specific behavior:
  - `original_path` is always relative to `storage/app/private/images/{provider}/` (no absolute paths or provider root).
  - For `pexels`, `pixabay`, and `unsplash`:
    - `original_query_terms` are derived from the search folder name with trailing timestamp‑like tokens stripped.
    - `provider_keywords`/`provider_description` are populated from provider metadata where available (e.g. Pixabay description, Pexels/Unsplash search terms and captions).
  - `user` images:
    - `original_query_terms` is an empty array; rely on folder structure and any embedded metadata for semantic hints.

- Intent:
  - The manifest is not read by Blade at runtime; it exists for AI agents and build tooling to:
    - Discover all available images (especially in `user/`).
    - Understand orientation, aspect ratio, which optimized sizes exist, and (after `--describe`) what each image actually shows.
    - Map images to page sections using folder structure and query terms when generating or refactoring content templates.


**6) AI content descriptions (optional, recommended)**

- Command: `php artisan app:images-manifest --describe`. Options: `--provider=anthropic|openai` (overrides `IMAGE_VISION_PROVIDER`), `--redescribe` (redo images that already have one), `--sheet-size=N` (images per request).
- How it works: after WebP generation, the smallest variant of every image lacking `content_analysis` is tiled onto numbered contact sheets (default 16 per sheet, one API request per sheet) and sent to the vision model, which returns one JSON object per cell. Answers are written to `content_analysis` on each manifest entry and survive later re-ingests, so only new images cost anything on the next run.
- `.env` keys: `IMAGE_VISION_PROVIDER` (`anthropic` default, or `openai`), `IMAGE_VISION_SHEET_SIZE` (16), `ANTHROPIC_API_KEY` + `ANTHROPIC_VISION_MODEL` (`claude-opus-5`), `OPENAI_API_KEY` + `OPENAI_VISION_MODEL` (`gpt-6-astra`). Only the chosen provider's key is required.
- Fields per image: `description` (one or two sentences, alt-text ready), `subjects` (most important first), `colors` (name plus hex, most dominant first), `text` (readable words as written), `people` (count), plus `provider`, `model`, `analyzed_at`.
- SVGs are never analyzed (no raster variant). A failed sheet is logged as a warning, the rest continue, and the command exits non-zero; re-run to fill the gaps.
- Code lives in `app/Services/Vision/`: `ContactSheetBuilder` (GD grid), `VisionPrompt` (shared prompt, JSON schema, parser), `AnthropicVisionProvider`, `OpenAiVisionProvider`, `ImageContentAnalyzer` (batching and cell mapping), `VisionProviderFactory`.

---

### Flickr Galleries

Photos live on Flickr; the site only caches metadata and image URLs. The owner manages the gallery by adding or removing photos in a Flickr album from the phone app, and the site follows on the next sync.

- Configure in `.env`: `FLICKR_API_KEY` (a Flickr app key; public read access needs no OAuth), `FLICKR_USER_ID` (NSID like `12345678@N01`, or a username), optional `FLICKR_MAX_PHOTOS` (500) and `FLICKR_AUTO_SYNC` (true).
- Sync: `php artisan app:flickr-sync` (options `--album=<id or exact title>`, `--max-photos=N`). Every public album on the account is pulled. `routes/console.php` also schedules it every 15 minutes when Flickr is configured and `FLICKR_AUTO_SYNC` is true, which needs the standard Laravel scheduler cron entry (`* * * * * php artisan schedule:run`).
- Cache: one entry, `flickr:albums` → `{synced_at, user_id, albums: {id => album}}`. Only public photos are visible to an API key.
- Helpers: `flickr_enabled()`, `flickr_albums()` (newest update first), `flickr_album($idOrTitle)`, `flickr_synced_at()`.
- Album fields: `id`, `title`, `description`, `count`, `updated_at`, `cover{thumb,medium,large}`, `page_url`, `photos[]`.
- Photo fields: `id`, `title`, `description`, `taken_at`, `uploaded_at`, `tags[]`, `thumb` (150px square), `small` (500px), `medium` (640 or 800px), `large` (up to 1600px), `original` (null unless the account exposes originals), `width`, `height`, `page_url`. Use `description` or `title` for alt text.
- Starter page: `resources/views/content/gallery.blade.php` renders the album grid and `?album=<id>` renders one album. Reference: `resources/examples/content/integrations/flickr.blade.php`. Code: `app/Services/FlickrService.php`, `app/Console/Commands/SyncFlickr.php`.

---

### Web Form Processing

Forms are defined in Blade and configured through `config/form.php`. Form
submissions run through validation, throttling, user-agent checks, optional
Google reCAPTCHA, and message content filters from logic found in
`app/Http/Controllers/Form.php`. Valid submissions can be of one or more:
- Emailed (SMTP / Mailhog)
- Stored in the database
- Sent to external services via webhook adapters (Zapier, Mailchimp, Salesforce)


* Example web form is found in `resources/examples/content/contact.blade.php`.
* Use Laravel validation rules pattern in `config/form.php` when adding fields for corresponding form.
* An unlimited number of forms can exist with each one outlined in the form config.
* POST new HTML forms to `/forms/{form-name}` so they route through `Form::process`.
* Keep response emails simple - Blade templates in `resources/views/emails` just receive `$formData`.
* Update `.env` mail targets (`CONTACT_FORM_MAIL_TO`, `CONTACT_FORM_SUCCESS_PAGE`) for destination changes.

Email template helper
- Use `email_table($formData)` (from `app/helpers.php`) to render a clean, inline‑styled HTML table in email bodies.
- The contact email view at `resources/views/emails/contact.blade.php` is a complete HTML email scaffold that embeds this helper.


---

### JSON‑LD Schema Markup

This project ships with a JSON‑LD validation command and a simple convention for per‑page schema. Schema markup should be generated whenever possible and included at the bottom of the content page. An example can be found in `resources/examples/content/index.blade.php`. Follow these steps to add or update JSON‑LD.

1) Gather the base schema data from the user

- Organization or person name, canonical site URL, logo, address, phone, email, social profile URLs, and business hours where applicable.
- If the user has not supplied these, ask for them before authoring; do not invent contact details.

2) Author the JSON‑LD file

- Location: `resources/views/jsonld/<slug>.jsonld`
- Must include the `<script type="application/ld+json">…</script>` wrapper in the file.
- Use the canonical site domain and IDs.
- Use as much metadata as possible.

3) Implementation

- Example blade section inside `content/<slug>.blade.php`:
  
```
@section('jsonld')
{!! file_get_contents(resource_path('views/jsonld/<slug>.jsonld')) !!}
@endsection
```

4) Validate JSON‑LD

- Validate a single file:

```bash
php artisan schema:validate-jsonld --file=<slug>.jsonld
```

- Or validate all JSON‑LD files:

```bash
php artisan schema:validate-jsonld
```

The validator checks:
- Presence of the `<script type="application/ld+json">` wrapper
- Valid JSON and JSON‑LD expansion (via `ml/json-ld`)
- Detects schema.org items (via `brick/structured-data`); warns if none found


---

### SEO Meta Tags (Per‑Page)

When creating or modifying content pages under `resources/views/content`, set SEO variables at the very top of the file so the main layout can render proper head tags. Use a short `@php` block:

```
@php
    $pageTitle = 'Page Title Here';
    $pageDescription = 'One‑sentence summary used for SEO and link previews.';
    // Optional social share image (absolute URL or relative path)
    // $pageImage = images('hero/example.jpg');
    // $pageImageAlt = 'Accessible description of the hero image';
@endphp
```

The layout consumes these to output:
- `<title>` and meta `description`
- Canonical link (defaults to current URL)
- Open Graph and Twitter tags (title, description, URL, and image if provided)

Keep titles concise (50–60 chars ideal) and descriptions ~155 chars. Provide `$pageImage` only when it meaningfully represents the page.


---

### Blogger / "Blog" System

Kavera treats blogging as "imported flat‑files" plus a lightweight cached index. Agents should understand this model to generate optional, "blog type" pages, lists, and navigation. This project is not intended to be a "blog" per-se however the integration allows the user to use Blogger any means they see fit. This could also be used for, not limited to: press releases, news articles, product pages, general blog, etc.

Key concepts
- Import, don’t fetch at request time: posts import as Blade files under a configurable base (default `blog`).
- Slugs are cleansed by the same regex used for content routing (see `config/content.php`).
- The cache stores only one index document (recent list, label membership, archives, compact previews); NOT full HTML bodies. Any Laravel cache store works.

Configuration (`config/services.php` → `services.blogger`)
- `content_base` (env `BLOGGER_CONTENT_BASE`, default `blog`) – base folder under `resources/views/content` where posts are written
- `post_layout` (env `BLOGGER_POST_LAYOUT`, default `templates.blog`) – Blade layout used by imported posts
- `post_section` (env `BLOGGER_POST_SECTION`, default `blog_content`) – section name posts render into
- `label_segment` (env `BLOGGER_LABEL_SEGMENT`, default `labels`) – URL segment for label listings

Routing (added in `routes/web.php`)
- `/<base>` → recent posts (from the cached index)
- `/<base>/<label_segment>/<label>` → posts with label (newest first)
- `/<base>/<YYYY>/<MM>` → monthly archive
Middleware `VerifyContentAccess` allows these dynamic routes to pass through.

Import command (Blogger → Blade files + cached index)
- Import and overwrite posts as Blade files, never delete old ones:

```bash
php artisan app:blogger-import --per_page=50
php artisan app:update-content-list
```

What it does
- Writes each post to `resources/views/content/<base>/<slug>.blade.php`.
- Strips `.html/.htm` from Blogger URLs before slug cleansing.
- Generates `.gitignore` in `resources/views/content/<base>` so generated posts aren’t committed; keep `index.blade.php` under version control for customization.
- Writes one cache entry, `blogger:index`, holding:
  - `posts` – id → preview {id,title,url,published_at,summary,slug,path,thumb}
  - `order` – post ids, newest first
  - `labels` – slug → {name, count, ids (newest first)}
  - `archives` – `YYYY-MM` → ids (newest month first)
  - `recent` – the top 10 previews, precomputed
  Read it through the helpers below (or `blogger_index()` for the raw document); never assume a Redis-specific structure.

Helpers (use in templates/layouts)
- `blogger_recent($n = 10)` – array of recent previews
- `blogger_label_ids($slug)`, `blogger_archive_ids($ym)`, `blogger_label_name($slug)` – lookups used by `BlogController`
- `blogger_labels()` – [slug => {name,count}] sorted by name
- `blogger_archives()` – ["YYYY-MM", ...] newest first
- `blogger_label_url($slug)`, `blogger_archive_url($ym)` – build links
- `blogger_base()`, `blogger_label_segment()` – current config values

Layouts
- Default post layout `templates.blog` provides:
  - Main content yield `@section('blog_content')`
  - Sidebar with Recent, Tags, Archive using helpers
  - A sample index view at `resources/examples/content/blog/index.blade.php` shows a hero + card grid; copy to your own `resources/views` tree and customize.

Slug and path policy
- Path allow‑list is controlled by `config/content.php` (`content.allowed_path_regex`).
- Slug cleansing: non‑matching chars → space, collapse spaces to one dash, lowercase, trim `-`.

Agent guidance
- Keep `resources/examples` as examples for reference; do not overwrite those when generating a real site.
- `resources/views/content/blog/index.blade.php` and `templates/blog.blade.php` ship in the starter tree; import posts and refresh the registry and the blog is live.
