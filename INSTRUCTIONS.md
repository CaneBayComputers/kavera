# INSTRUCTIONS.md — fixes found while deploying a Kavera site to production (2026-09-22)

Context: a real client site (luxornyc, built from a Website Manifestor brief) was taken from
`zeltro` dev to the CBC prod box today. Four things in Kavera itself got in the way. Each one
below says what happened, where it lives, and the fix that was applied to the site's own copy
so it can be ported back. Nothing here touches the site repo; that is already patched.

Ordered by how much damage it does in production.

---

## 1. Production with no reCAPTCHA keys rejects every form submission (high)

**What happens.** `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY` are documented as "required for
production" but nothing enforces or checks that. When they are blank and `APP_ENV=production`:

- `resources/views/content/contact.blade.php:62` (and `resources/examples/content/contact.blade.php:101`)
  load `https://www.google.com/recaptcha/api.js?render=` with an empty key. `grecaptcha.execute('')`
  never resolves, the `onsubmit` handler has already returned `false`, so the form **never submits**.
  The visitor clicks Send and nothing happens.
- Even a direct POST fails: `app/Http/Controllers/Form.php:194` runs `recaptchaMessage()` whenever
  `!is_dev()`, `siteverify` is called with an empty secret, `score` is missing, and the request is
  rejected with "Recaptcha score returned blank".

So a site deployed without keys looks fine and silently loses every lead. This is almost certainly
why signageoperationscommand.com on prod has an empty `RECAPTCHA_SITE_KEY` and nobody noticed.

**Fix applied on the site (port this).** Gate on the keys, not just on the environment:

```php
// Form.php, replace the `if (!is_dev()) {` around the reCAPTCHA check
if (!is_dev() && _c('form.recaptcha.site_key') && _c('form.recaptcha.secret_key')) {
```

```blade
{{-- contact.blade.php (starter and example), replace @if(!is_dev()) --}}
@if(!is_dev() && _c('form.recaptcha.site_key'))
```

Optional but worth it: log a warning once per request in `Form::process` when production has no
keys, so it shows up in `laravel.log` instead of being invisible. And a one-line note in AGENTS.md
under "Web Form Processing": *"Without keys the form still works; reCAPTCHA is simply skipped."*

---

## 2. Manifest images never reach production through git (high)

**What happens.** `app:website-manifest-import` (`app/Console/Commands/ImportWebsiteManifest.php:43`)
copies the optimized images into `storage/app/public/images/<size>/`. Laravel's default
`storage/app/public/.gitignore` ignores everything there, and `.website-manifest/` is not committed
either (it is the client's intake and got gitignored in the site repo). The production deploy
pattern on the CBC box is a plain `git pull`, so the checkout arrives with **no images at all**.
Today they had to be rsynced by hand, plus the `public/storage` symlink created manually.

**Pick one:**

- **(a) Commit the imported images.** Replace `storage/app/public/.gitignore` with rules that keep
  `images/` tracked (e.g. `*`, `!.gitignore`, `!images/`, `!images/**`). Simple, and the sizes are
  already web-optimized WebP so the repo stays small. The site still needs `php artisan storage:link`
  (or the symlink) on the server; document that in the deploy notes.
- **(b) Import into `public/images/manifest/<size>/` instead.** No symlink, no gitignore, URL becomes
  `/images/manifest/<size>/<id>`. Update the AGENTS.md "Public URL of an image" line and the
  `notes` rule the import command appends to `manifest.json`.

(a) is the smaller change and keeps every existing template path working.

---

## 3. Any cache clear unregisters every page (medium)

**What happens.** `app/Http/Middleware/VerifyContentAccess.php` reads `content_list` from the cache
and 404s anything not in it. `app:update-content-list` writes it with `Cache::forever`. But
`php artisan cache:clear` (and `zeltro cache-refresh`, which runs it) wipes that key, and from that
moment every content page except `/` returns 404 until someone remembers to re-run the command.
Today `/contact` 404ed for exactly this reason.

**Fix.** Make the registry self-healing. When the key is missing, rebuild it inline instead of
denying:

```php
$content_list = Cache::get('content_list');
if ($content_list === null) {
    Artisan::call('app:update-content-list');
    $content_list = Cache::get('content_list', []);
}
```

Or move the scan into a small service that both the command and the middleware call, and have the
middleware use `Cache::rememberForever('content_list', fn () => $service->scan())`. Either way, a
cleared cache should cost one directory scan, not a dead site. Also worth mentioning in AGENTS.md
that `app:update-content-list` must run **after** any cache clear, until this lands.

---

## 4. `schema:validate-jsonld` needs dev dependencies (low)

**What happens.** `ml/json-ld` and `brick/structured-data` are in `require-dev`
(`composer.json:19,24`). Production installs run `composer install --no-dev` (that is what
`/var/www/production_site_setup.sh` does), so `php artisan schema:validate-jsonld` dies there with
`Class "ML\JsonLD\JsonLD" not found`. AGENTS.md presents it as a normal command with no caveat.

**Fix.** Either move the two packages to `require` (they are small), or have the command check
`class_exists(\ML\JsonLD\JsonLD::class)` and print "install dev dependencies to validate" instead of
crashing. Add "(dev dependencies only)" next to the command in AGENTS.md.

---

## Not Kavera, but hit the same day (for the deploy script, not this repo)

- `php artisan storage:link` run as `www-data` fails because `public/` is owned by `ubuntu`. The
  setup script should create the link as the checkout owner, or `chown` `public/` first.
- A `.env` created as `ubuntu` with mode 640 is unreadable by php-fpm, and artisan then silently falls
  back to sqlite defaults. The script should end with `chown www-data:www-data .env && chmod 640 .env`.

Both belong in `production_site_setup.sh` on the prod box, not in Kavera.
