## AI Agent Onboarding Protocol

### Purpose

This section defines how any AI agent (e.g., Codex, Cursor, Aider, or GPT CLI) should **initialize** and **learn** the project structure before performing any edits, analysis, or code generation.

---

### Initial Context Acquisition

**Read the following files** in the given order to become acquainted with the project’s architecture, dependencies, and conventions:

1. `README.md` – overall purpose, setup, and deployment notes
2. `composer.json` – PHP dependencies and autoloading configuration
3. `.env` – environment variables, active service settings and tech stack
4. `app/helpers.php` – global helper functions and environment logic
5. `resources/examples/content/index.blade.php` - meta tags and schema markup (json-ld) integration
6. `routes/web.php` - Custom web URL routing for static pages, forms and "blog"

---

### Project Overview

Kavera is a Laravel-based website framework that uses flat-file Blade templates
for static pages and retrieves dynamic content from external services. Static
pages are located in `/resources/views/content` and can be created, edited, or
removed directly. Dynamic content can be periodically pulled from integrated
services and stored in Redis so that templates can access it without calling
external APIs at runtime.

Kavera does not use a traditional CMS. There is no admin panel for page
creation. The primary method for defining site structure is creating or editing
Blade files, partial components and templates manually or via an AI agent.

Dynamic content sources included and pre-built in this project:
- Blogger: Used for posts, articles, news, press releases, staff lists, or any
  repeatable content collection. Pulled through the Blogger API into Redis.
- Eventbrite: Event data is fetched and normalized into Redis for display on
  event or calendar pages.
- Flickr: Albums and photo sets are synchronized and cached in Redis for use in
  gallery components.
- Pixabay Search: Images can be pulled via CLI command and stored locally for
  use in page templates.
- Local public/images directory: Images placed here may be referenced directly
  or described in an optional YAML manifest to assist with semantic placement.

The "Agent Briefing Wizard" is a CLI command that gathers project details,
colors, pages, keywords, organization information, and design references. The
output is a structured prompt for an AI assistant to use when generating page
content or the entire site. Use of an AI agent is optional; manual editing is
fully supported.

All static content is edited in Blade templates. All dynamic collections are
accessed from Redis. Do not attempt to modify content through a CMS interface,
as none exists. Keep HTML structure semantic and rely on existing layout and
utility classes.

**KEY CONCEPT FOR REAL, CUSTOM SITES**, replace the `resources/views` symlink with a regular `views` folder and generate your own content tree similar to the `examples` folder. Examples live in `resources/examples` and should remain sample‑only. It is probably best to simply copy the entire `examples` folder tree into the normal `resources/views` folder as a starting point. It is advised to NOT delete the example folder so that AI agents are able to use the examples to generate real content. Also, there will not be merge conflicts when updating from parent Kavera repo. 


---

### Flat-file CMS Architecture / Content Creation

* Blade templates in `resources/views/content` correspond to URL slugs.
* Routes are defined in `routes/web.php`.
* Middleware `VerifyContentAccess` checks the Redis page registry.
* Controller: `PageController` renders approved views.
* Refresh Redis content list whenever content files are added, removed, or name is modified:

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

### Image‑Driven Page Scaffolding

Agents can bootstrap pages from images dropped into `storage/app/public/images` using lightweight conventions and an optional manifest. This helps rapidly assemble hero banners, feature cards, galleries, and team sections. Use Laravel's built-in `Storage::url()` helper.

- Filename hints (fallback parsing)
  - Suggested pattern: `<page>-<section>-<role>-<order>.<ext>` (e.g., `home-hero-banner-1.jpg`, `services-card-webdev-1.png`).
  - Role heuristics: hero/banner (very wide), card/feature (rectangular), headshot/logo (square), gallery (default).

- Image manifest yaml
  - Create `storage/app/private/images-manifest.yaml` describing sections, order, alt text, captions, and placement hints.
  - This will also provide image visual hint details key words optionally provided by AWS Rekognition.
  - The manifest is intended for the AI agent to read during generation; Blade templates do not read it at runtime.
  - Agent generates `resources/views/content/<page>.blade.php` with sections: hero, features/cards grid, gallery, and optional team/logos, embedding chosen images and alt text based on structured data for each image found in the yaml file.


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

1) Base schema data from `storage/app/agent-brief.txt`

- If this file does not exist demand for user to run `php artisan app:agent-brief`

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

### PHP Syntax and Code Quality Checks

After updating any PHP file, the agent **must** verify syntax, formatting, and overall code health using the following commands on the specific file changed:

```bash
# 0. Quick syntax check (lint only)
php -l <file_path>

# 1. Auto-fix formatting issues (PSR-12)
phpcbf --standard=~/.config/phpcs-ruleset.xml <file_path>

# 2. Verify style and report remaining issues
phpcs --standard=~/.config/phpcs-ruleset.xml -w <file_path>

# 3. Detect unused variables and code smells
phpmd <file_path> text ~/.config/phpmd.xml
# If no user ruleset exists, fall back to categories:
# phpmd <file_path> text codesize,unusedcode,naming
```


---

### Blogger / "Blog" System

Kavera treats blogging as "imported flat‑files" plus lightweight Redis indices. Agents should understand this model to generate optional, "blog type" pages, lists, and navigation. This project is not intended to be a "blog" per-se however the integration allows the user to use Blogger any means they see fit. This could also be used for, not limited to: press releases, news articles, product pages, general blog, etc.

Key concepts
- Import, don’t fetch at request time: posts import as Blade files under a configurable base (default `blog`).
- Slugs are cleansed by the same regex used for content routing (see `config/content.php`).
- Redis stores only indices (recent list, label membership, archives) and compact previews; NOT full HTML bodies.

Configuration (`config/services.php` → `services.blogger`)
- `content_base` (env `BLOGGER_CONTENT_BASE`, default `blog`) – base folder under `resources/views/content` where posts are written
- `post_layout` (env `BLOGGER_POST_LAYOUT`, default `templates.blog`) – Blade layout used by imported posts
- `post_section` (env `BLOGGER_POST_SECTION`, default `blog_content`) – section name posts render into
- `label_segment` (env `BLOGGER_LABEL_SEGMENT`, default `labels`) – URL segment for label listings

Routing (added in `routes/web.php`)
- `/<base>` → recent posts (from Redis)
- `/<base>/<label_segment>/<label>` → posts with label (newest first)
- `/<base>/<YYYY>/<MM>` → monthly archive
Middleware `VerifyContentAccess` allows these dynamic routes to pass through.

Import command (Blogger → Blade files + Redis indices)
- Import and overwrite posts as Blade files, never delete old ones:

```bash
php artisan app:blogger-import --per_page=50
php artisan app:update-content-list
```

What it does
- Writes each post to `resources/views/content/<base>/<slug>.blade.php`.
- Strips `.html/.htm` from Blogger URLs before slug cleansing.
- Generates `.gitignore` in `resources/views/content/<base>` so generated posts aren’t committed; keep `index.blade.php` under version control for customization.
- Builds Redis indices:
  - `blogger:post:<id>` – JSON preview {id,title,url,published_at,summary,slug,path,thumb}
  - `blogger:posts:by_published` – ZSET
  - `blogger:label:<slug>:ids` – SET membership
  - `blogger:labels` – HASH counts, `blogger:labels_display` – display names
  - `blogger:archive:YYYY-MM` – ZSET per month, `blogger:archives` – ZSET of months
  - `blogger:recent` – precomputed JSON array (top 10)

Helpers (use in templates/layouts)
- `blogger_recent($n = 10)` – array of recent previews
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
- For actual sites, replace the `resources/views` symlink with a real folder, then import posts and refresh the registry.
- When running Podium in non‑interactive environments, wrap with `script` to provide a pseudo‑TTY (see earlier note).


---

### Podium Usage

If Podium is installed use the `podium art` command instead of `php artisan` as this runs artisan inside of the Docker container for a more version targeted execution.

If you are running Podium commands from a non-interactive agent or in an environment without a TTY (e.g., this AI harness), wrap the command with `script` so Podium receives a pseudo‑TTY:

```bash
script -q -c "podium art app:update-content-list" /dev/null
```

General pattern for Podium via agents/CI:

```bash
script -q -c "podium art <command> [options]" /dev/null
```