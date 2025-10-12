# 🗂️ Flat File CMS Setup (Laravel Style)

This project uses a **flat-file CMS system** built directly on Laravel 12, with Redis-backed caching for fast page resolution and validation.

---

### 📚 How it Works

* Blade files live in `resources/views/content/`
* A custom Artisan command builds a list of available pages and saves it to Redis
* Middleware (`VerifyContentAccess`) validates every request to ensure only pre-validated pages are accessible
* No database needed — **pure flat-file content**

---

### 🚀 Installation Steps

You can run the project anywhere PHP 8.3 and Redis are available, but it pairs especially well with the [Podium CLI](https://github.com/CaneBayComputers/podium-cli). Once Podium is installed, spin everything up with:

```bash
podium clone https://github.com/CaneBayComputers/laravel-flat-file-website.git
```

Podium isn’t required, yet it provisions Docker, Redis, Mailhog, and project scaffolding automatically, so everything is ready to use as soon as the repo is cloned.

---

### ✅ Example Request Flow

* Request: `/contact`
* Resolves to: `resources/views/content/contact.blade.php`
* Validated by: Redis pre-built list
* Served securely: Only existing pages are accessible, all others 404

---

### ✉️ Web Form Mailing

The contact page ships with a ready-to-send email flow, relaying enquiries through whatever mail host you configure. Point the `.env` mail settings (mailer, host, port, credentials, from address) at your provider—Mailhog for local testing, Amazon SES, or any SMTP service works fine—then set the destination inbox via `CONTACT_FORM_MAIL_TO` to start receiving messages.

---

### 🛡️ Security

* Only alphanumeric, slash, and dash URLs allowed
* No periods, underscores, query strings
* Middleware ensures no invalid paths reach the page handler
* Redis-backed lookup for fast validation without DB hits

---

### 💬 Notes

* After adding or removing `.blade.php` files in `resources/views/content/`, re-run:

  ```bash
  php artisan app:update-content-list
  ```
* If you have Podium CLI installed use `podium art app:update-content-list` to run any Laravel Artisan commands inside the Docker container.

---

### 🤖 Agent‑Ready Workflow (Podium + Flat‑File)

This repo is designed to be “agent‑friendly.” Given a content brief and a folder of images, an AI agent can scaffold a full site using Podium‑managed containers and this flat‑file architecture:

- Drop images into `public/images` and optionally describe sections in `resources/content/<page>.yaml`.
- The agent parses filenames and/or the YAML manifest to assemble pages under `resources/views/content/` (hero, cards, galleries, etc.).
- Forms and email are already wired (see `config/form.php`), so new forms can reuse the same flow.
- Run inside the container with Podium (`podium art`, `podium composer`, `podium php`) for a turn‑key experience.

This enables one‑shot site generation: clone via Podium, place content, and render pages—no database required.

---
