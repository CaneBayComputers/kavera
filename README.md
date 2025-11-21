# 🚀 Kavera on Laravel — Build Websites at Agent Speed and Integrate Features with Ease

Kavera is a Laravel-native website framework that combines flat-file content with service-driven dynamic data. Pages are simple Blade templates you can edit by hand or generate with an AI agent, and connected services like Blogger, Eventbrite, Flickr, and form webhooks feed structured content into Redis for fast, predictable rendering.

This allows a complete site to be created in minutes, not weeks: static content is easy to modify, dynamic content comes from the tools users already know, and forms include spam controls, validation, email handling, persistence, and webhook integrations. Kavera includes an Agent Briefing Wizard to generate an accurate build brief for AI assistants, but using an agent is optional — the system works just as well with manual workflows.

The result is a website framework that stays simple at its core, scales through services, and lets developers ship real business sites quickly and reliably.

- 🧰 Turn‑key: clone and run with Podium CLI (or locally with PHP 8.3). No extra scaffolding.
- 🤖 Agent‑first: content pages are simple files, so agents can add, move, or delete sections without fighting a CMS UI.
- 🔌 Real features: forms with email + webhooks (Mailchimp, Zapier, Salesforce), events, galleries, stock images — zero plugin drama.
- 🛡️ Safe by default: only approved pages resolve; everything else 404s. Forms ship with basic anti‑abuse checks.
- 🔍 SEO‑ready: pages set proper title tags, and the Agent Brief pulls keywords you can pepper through copy and headings.

Get the idea? You’re not wiring a CMS. You’re shipping a site.

---

## ⚡ Quick Start

Using Podium (recommended)
```bash
php artisan app:agent-brief
```

That’s it. Pages live in `resources/views/content`. Add or remove a page, then refresh the registry (see “Technical Reference”).

Manual views setup (without Agent Brief)
- This project ships with `resources/views` as a symlink to `resources/examples` so you can preview the example pages.
- If you plan to manage real views manually (without the Agent Brief CLI making changes), replace the symlink with a real folder structure similar to `resources/examples`:

```bash
# 1) Remove the views symlink
rm resources/views

# 2) Create your own views tree
mkdir -p resources/views/{content,templates,emails,jsonld}

# Optional: seed from examples to start from the templates
rsync -a resources/examples/ resources/views/

# 3) Refresh the content registry so routes resolve
podium art app:update-content-list
```

After this, edits under `resources/views` won’t affect the example set.

---

## 🎁 What’s Included

- 📄 Pages: Simple, file‑based pages you can edit fast — no CMS required.
- ✉️ Forms: Email out of the box plus webhooks (Mailchimp, Zapier, Salesforce).
- 🧠 Spam control built‑in: UA/link checks, throttling, and Google reCAPTCHA (prod).
- 🔍 SEO Setup: Title/description per page plus optional JSON‑LD schema markup with a built‑in validator.
- 🎟️ Events: Eventbrite page you can flip on with two env keys.
- 🖼️ Images: Pixabay helper for quick stock image pulls.
- 🧭 Agent Brief Wizard: generates a project‑specific prompt to guide any AI agent.
- 📝 Blogging: Write in Blogger, publish on your site — posts become simple pages with recents, tags, and archives.

---

## 🤖 Agent Workflow (Fast Path)

1) Drop images into `/storage/app/public/images`.
2) Run the Agent Brief wizard: `php artisan app:agent-brief`.
3) Let your agent scaffold pages and sections (hero, features, cards, galleries).
4) Wire forms via simple config (email + webhooks).
5) Flip on integrations with env keys. Done.

---

## 🔌 Integrations

- Eventbrite
  - Events are synchronized into Redis and read at render via helpers; avoid calling external APIs on page load.
  - Example view: `resources/examples/content/events.blade.php`.

- Blogger
  - Public posts are fetched via the Blogger API (API key only), imported as Blade files for fast, predictable rendering.
  - Lightweight Redis indices power recent posts, labels, and monthly archives.
  - Example: `resources/examples/content/integrations/blogger.blade.php` and the blog layout at `resources/examples/templates/blog.blade.php`.

- Flickr
  - Albums and photo sets are synchronized and cached in Redis for gallery components.
  - Example view: `resources/examples/content/features-flickr.blade.php`.

- Pixabay (+ optional AWS Rekognition)
  - Optionally run downloaded images through AWS Rekognition to auto-tag/filter; requires AWS credentials.
  - Example view: `resources/examples/content/features-pixabay.blade.php`.

Notes
- Dynamic collections are read from Redis by templates; use your sync/job flow to hydrate caches before rendering.
- Example pages live under `resources/examples/content/`; see them for usage patterns and helpers.

---

## 🔌 Webhook Integrations

Send form submissions anywhere — Mailchimp, Zapier, Salesforce — with simple, explicit field maps. No plugins, no guesswork.

Setup and code examples live in AGENTS.md → “Webhook Adapters.”

---

## 🧭 Agent Brief Wizard

Run an interactive command to generate a project‑specific prompt that tells any AI agent exactly how to build out your site within Kavera on Laravel.

```bash
php artisan app:agent-brief
```

It collects pages, forms, tone, business info, and more, then prints a ready‑to‑paste brief.

---

## 📎 Tech & Setup

Looking for code examples, mapping, or integration setup? See AGENTS.md for:
- Webhook Adapters (Mailchimp, Zapier, Salesforce) with full field_map examples
- Forms + validation + spam controls (throttling, link checks, reCAPTCHA)
- Content model, routes, and page registry refresh
- Podium commands and troubleshooting

This keeps the README friendly while making the deeper bits easy to find.

---

## 📝 Blogging Quick Start (Blogger)

1) Enable Blogger API v3 in Google Cloud and create an API key.
2) Set these in your `.env`:

```
BLOGGER_API_KEY=your_api_key
BLOGGER_BLOG_ID=your_blog_id
# Optional: BLOGGER_CONTENT_BASE (defaults to "blog")
```

3) Import posts as Blade files and refresh the registry:

```bash
php artisan app:blogger-import --per_page=50
php artisan app:update-content-list
```

4) View your blog at `/<base>` (default `/blog`). Labels and archives are available at `/<base>/<label-segment>/<label>` and `/<base>/<YYYY>/<MM>`.

Notes
- Posts render as static Blade files (fast); Redis holds only indices (recent, labels, archives).
- Default layout is `templates.blog`; customize via `BLOGGER_POST_LAYOUT` and `BLOGGER_POST_SECTION`.


## 📄 License

Kavera is open source under the MIT License.

- License: see `LICENSE`
- Copyright: © 2024–2025 Cane Bay Computers & Mobile Repair, LLC
- Authors: see `AUTHORS`

Note: “Kavera” and its logos are trademarks of Cane Bay Computers & Mobile Repair, LLC. The MIT license does not grant trademark rights.
