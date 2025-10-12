## 🧠 AI Agent Onboarding Protocol

### Purpose

This section defines how any AI agent (e.g., Codex, Cursor, Aider, or GPT CLI) should **initialize** and **learn** the project structure before performing any edits, analysis, or code generation.

---

### Context Acquisition

**Read and summarize the following files** in the given order to become acquainted with the project’s architecture, dependencies, and conventions:

1. `README.md` – overall purpose, setup, and deployment notes
2. `composer.json` – PHP dependencies and autoloading configuration
3. `.env` – environment variables and active service settings
4. `app/helpers.php` – global helper functions and environment logic

After reading, summarize each file’s purpose in a few sentences to confirm comprehension before proceeding.

---

### Project Overview

**Features**

* Flat-file content system
* Built-in form processing
* Web form mailing

**About**

* Flat-file content system
* Built-in form processing

**Tech Stack**

* PHP 8.3
* Laravel 12
* Bootstrap 5.3

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

### Project Conventions

* Blade templates in `resources/views/content` correspond to URL slugs.
* Routes are defined in `routes/web.php`.
* Middleware `VerifyContentAccess` checks the Redis page registry.
* Controller: `PageController` renders approved views.
* Refresh Redis content list:

  ```bash
  php artisan app:update-content-list
  ```

  or

  ```bash
  podium art app:update-content-list
  ```

  Run this whenever content files are added, removed, or modified.
* Use `placehold.co` for image placeholders.
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

## 🔁 Agent Behavior

When first initializing or joining the project, the AI agent must:

1. Read and summarize the files listed above.
2. Review the web form stack before customizing or creating form flows:
   * `.env`
   * `config/form.php`
   * `app/Http/Controllers/Form.php`
   * `routes/web.php`
   * `resources/views/content/contact.blade.php`
   * `resources/views/emails/contact.blade.php`
3. Confirm understanding of the project structure and conventions.
4. Only then proceed to perform edits, explanations, or refactors.

---

## 🧰 Podium‑Aware Ops (Container vs Local)

This project is typically run under Podium CLI, but should remain turn‑key outside of it. Prefer containerized commands when Podium is present; otherwise fall back to local equivalents.

- Detect Podium
  - If `podium status` works and shows this project running, use `podium` wrappers below.
  - Otherwise, run the local commands in the right PHP/Laravel environment.

- Common Commands
  - Refresh content list (required after adding/removing files under `resources/views/content/`):
    - With Podium: `podium art app:update-content-list`
    - Locally: `php artisan app:update-content-list`
  - Clear Laravel caches (config/routes/views):
    - With Podium: `podium cache-refresh`
    - Locally: `php artisan optimize:clear`
  - Composer/npm in container vs local:
    - With Podium: `podium composer install`, `podium php -v`
    - Locally: `composer install`, `php -v`
  - Service access (dev): Redis `redis`, Mailhog `mailhog`, MariaDB `mariadb` are available via `/etc/hosts` when Podium is up.

- URLs (from `/etc/hosts`/Podium):
  - App: `http://laravel-flat-file-website`
  - LAN access is also mapped by Podium (see `podium status`).

---

## 🖼️ Image‑Driven Page Scaffolding (Optional)

Agents can bootstrap pages from images dropped into `public/images` using lightweight conventions and an optional manifest. This helps rapidly assemble hero banners, feature cards, galleries, and team sections.

- Filename hints (fallback parsing)
  - Suggested pattern: `<page>-<section>-<role>-<order>.<ext>` (e.g., `home-hero-banner-1.jpg`, `services-card-webdev-1.png`).
  - Role heuristics: hero/banner (very wide), card/feature (rectangular), headshot/logo (square), gallery (default).

- Preferred: sidecar manifest
  - Create `resources/content/<page>.yaml` describing sections, order, alt text, and captions. The Blade template should read this file and render with Bootstrap components.
  - If no manifest is present, parse filenames and image aspect ratio to build a first‑pass layout.

- Suggested workflow
  1) Scan `public/images` and build a manifest grouped by page slug.
  2) Generate `resources/views/content/<page>.blade.php` with sections: hero, features/cards grid, gallery, and optional team/logos.
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

## 🧪 PHP Syntax and Code Quality Checks

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

## 🧭 Troubleshooting Notes (for Agents)

- PHPCS
  - User ruleset lives at `~/.config/phpcs-ruleset.xml` (LineLength warnings disabled by policy).
  - Always pass the user ruleset when available: `phpcs --standard=~/.config/phpcs-ruleset.xml -w <file>`.

- PHPMD
  - User ruleset lives at `~/.config/phpmd.xml` and relaxes naming/complexity noise for helpers.
  - Use: `phpmd <path> text ~/.config/phpmd.xml`. If not present, fall back to `codesize,unusedcode,naming`.

- PDepend cache warnings
  - If running in a sandbox that blocks `$HOME/.pdepend`, set a temporary HOME:
    - `HOME=$PWD/.codex-cache-home phpmd <path> text ~/.config/phpmd.xml`

- Redis content registry
  - Middleware (`VerifyContentAccess`) checks `content_list` in Redis. If a new page 404s, run the content refresh.

- Environment toggles
  - `is_dev()` controls Recaptcha bypass and logging volume; ensure `.env` values are correct for the target environment.

- Podium TTY requirement
  - Some automation environments lack a TTY, causing `podium` commands to print `the input device is not a TTY`.
  - Workaround (allocate a pseudo‑TTY) from the project directory:
    - `script -q -c "podium art app:update-content-list" /dev/null`
    - `script -q -c "podium php -v" /dev/null`
    - `script -q -c "podium composer -V" /dev/null`
  - On a normal interactive terminal, run Podium commands directly from the project directory without `script`.

### ✅ Example initialization prompt

When you start a new session or resume an unfamiliar project, run:

```
Follow the AI Agent Onboarding Protocol in AGENTS.md.
Read and summarize each file listed under Step 1 before continuing.
```

Once it finishes summarizing, follow up with:

```
Now that you're acquainted, confirm adherence to the coding conventions
in the 'Project Conventions' section and await my next instruction.
```
