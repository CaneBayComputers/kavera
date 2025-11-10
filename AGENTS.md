## AI Agent Onboarding Protocol

### Purpose

This section defines how any AI agent (e.g., Codex, Cursor, Aider, or GPT CLI) should **initialize** and **learn** the project structure before performing any edits, analysis, or code generation.

---

### Initial Context Acquisition

**Read the following files** in the given order to become acquainted with the project’s architecture, dependencies, and conventions:

1. `README.md` – overall purpose, setup, and deployment notes
2. `composer.json` – PHP dependencies and autoloading configuration
3. `.env` – environment variables and active service settings
4. `app/helpers.php` – global helper functions and environment logic
5. `TODO.md` - Planned features and updates

---

### Project Overview

Kavera is a Laravel-based website framework that uses flat-file Blade templates
for static pages and retrieves dynamic content from external services. Static
pages are located in /resources/views/content and can be created, edited, or
removed directly. Dynamic content is periodically pulled from integrated
services and stored in Redis so that templates can access it without calling
external APIs at runtime.

Kavera does not use a traditional CMS. There is no admin panel for page
creation. The primary method for defining site structure is creating or editing
Blade files and partial components.

Dynamic content sources include:
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

Forms are defined in Blade and configured through Kavera’s form system. Form
submissions run through validation, throttling, user-agent checks, optional
Google reCAPTCHA, and message content filters. Valid submissions can be:
- Emailed (SMTP / Mailhog in development)
- Stored in the database
- Sent to external services via webhook adapters (Zapier, Mailchimp, Salesforce)

The "Agent Briefing Wizard" is a CLI command that gathers project details,
colors, pages, keywords, organization information, and design references. The
output is a structured prompt for an AI assistant to use when generating page
content. Use of an AI agent is optional; manual editing is fully supported.

All static content is edited in Blade templates. All dynamic collections are
accessed from Redis. Do not attempt to modify content through a CMS interface,
as none exists. Keep HTML structure semantic and rely on existing layout and
utility classes rather than inline styling.


**Tech Stack**

* PHP 8.3
* Laravel 12

**Services**

* **Database:** MariaDB
* **Cache:** Redis (default), Memcached optional
* **Queue:** Redis
* **Session driver:** Redis
* **Mail:** Mailhog
* **File storage:** Local
* **Broadcast:** Log driver
* **AWS S3:** Not used in dev (helpers switch assets between AWS and `/images` folder)

---

### Flat-file CMS Architecture / Content Creation

* Blade templates in `resources/views/content` correspond to URL slugs.
* Routes are defined in `routes/web.php`.
* Middleware `VerifyContentAccess` checks the Redis page registry.
* Controller: `PageController` renders approved views.
* Refresh Redis content list:

  ```bash
  podium art app:update-content-list
  ```

  Run this whenever content files are added, removed, or name is modified.
  
  If you are running Podium commands from a non-interactive agent or in an environment without a TTY (e.g., this AI harness), wrap the command with `script` so Podium receives a pseudo‑TTY:

  ```bash
  script -q -c "podium art app:update-content-list" /dev/null
  ```
  
  General pattern for Podium via agents/CI:
  
  ```bash
  script -q -c "podium art <command> [options]" /dev/null
  ```
* All links must use root-scoped anchors (e.g., `/` or `/#contact`) for navigation consistency.
* Custom helpers live in `app/helpers.php` and include:

  * `is_dev()` / `is_prod()` – environment checks
  * `cdn()`, `scripts()`, `styles()` – asset path generators
  * `_c()` – shorthand config access
  * `_l()` – structured logging with type handling

**Code Style**

* Blade filenames: lowercase-with-dashes
* PHP: PSR-12 / Laravel defaults

  * 4-space indent
  * PascalCase classes
  * camelCase methods
* Maintain generous blank lines for readability; preserve spacing in edits.

---

### Image‑Driven Page Scaffolding (Optional)

Agents can bootstrap pages from images dropped into `public/images` using lightweight conventions and an optional manifest. This helps rapidly assemble hero banners, feature cards, galleries, and team sections.

- Filename hints (fallback parsing)
  - Suggested pattern: `<page>-<section>-<role>-<order>.<ext>` (e.g., `home-hero-banner-1.jpg`, `services-card-webdev-1.png`).
  - Role heuristics: hero/banner (very wide), card/feature (rectangular), headshot/logo (square), gallery (default).

- Preferred: sidecar manifest
  - Create `storage/app/images-manifest.yaml` describing sections, order, alt text, captions, and placement hints.
  - The manifest is intended for the AI agent to read during generation; Blade templates do not read it at runtime.
  - If no manifest is present, the agent can parse filenames and image aspect ratios to build a first‑pass layout.

- Suggested workflow
  1) Scan `public/images` and build a manifest grouped by page slug.
  2) Agent generates `resources/views/content/<page>.blade.php` with sections: hero, features/cards grid, gallery, and optional team/logos, embedding chosen images and alt text.
  3) Use helpers for assets (`images('...')`) so S3 vs local switching works.
  4) Refresh routes: `podium art app:update-content-list`.

Agents should provide a dry‑run and avoid overwriting existing content without `--force`.

### Web Form Mailing Quickstart

* Use the files listed above to mirror the existing contact form flow.
* Reuse the Laravel validation rules pattern in `config/form.php` when adding fields.
* Point new form submissions to `/forms/{form-name}` so they route through `Form::process`.
* Keep response emails simple - Blade templates in `resources/views/emails` just receive `$formData`.
* Update `.env` mail targets (`CONTACT_FORM_MAIL_TO`, `CONTACT_FORM_SUCCESS_PAGE`) for destination changes.

Email template helper
- Use `email_table($formData)` (from `app/helpers.php`) to render a clean, inline‑styled HTML table in email bodies.
- The contact email view at `resources/views/emails/contact.blade.php` is a complete HTML email scaffold that embeds this helper.

---

### JSON‑LD Schema Markup

This project ships with a JSON‑LD validation command and a simple convention for per‑page schema. Follow these steps to add or update JSON‑LD.

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
podium art schema:validate-jsonld --file=<slug>.jsonld
```

- Or validate all JSON‑LD files:

```bash
podium art schema:validate-jsonld
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
