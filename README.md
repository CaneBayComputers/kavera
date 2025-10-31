# 🚀 Sitekit on Laravel — Build Websites at Agent Speed

Sitekit on Laravel lets you and your AI agent build a real website in minutes, not weeks. No plugins. No theme roulette. Just a clean Laravel app with pages as Blade files, a first‑class forms system, and ready‑to‑flip integrations. Point an agent at it and watch a site come together fast: hero banners, feature grids, galleries, events, and contact flows — all in one go.

Why teams like it:
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
podium clone https://github.com/CaneBayComputers/laravel-flat-file-website.git
podium art app:agent-brief   # optional: generate an “Agent Brief” from your answers
```

Local (PHP 8.3)
```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan serve
```

That’s it. Pages live in `resources/views/content`. Add or remove a page, then refresh the registry (see “Technical Reference”).

---

## 🎁 What’s Included

- 📄 Pages: Blade files under `resources/views/content/`, mapped directly to URLs.
- ✉️ Forms: Email out of the box plus webhooks (Mailchimp, Zapier, Salesforce).
- 🧠 Spam control built‑in: UA/link checks, throttling, and Google reCAPTCHA (prod).
- 🔍 SEO Setup: Title tags on pages; Agent Brief suggests high‑value keywords to pepper through copy and headings.
- 🎟️ Events: Eventbrite page you can flip on with two env keys.
- 🖼️ Images: Pixabay helper for quick stock image pulls.
- 🧭 Agent Brief Wizard: generates a project‑specific prompt to guide any AI agent.
- 🧪 Podium‑ready: one command to clone, run services, and ship.

---

## 🤖 Agent Workflow (Fast Path)

1) Drop images into `public/images/` (optional YAML manifest in `resources/content/<page>.yaml`).
2) Run the Agent Brief wizard: `podium art app:agent-brief`.
3) Let your agent scaffold pages and sections (hero, features, cards, galleries).
4) Wire forms via simple config (email + webhooks).
5) Flip on integrations with env keys. Done.

---

## 🔌 Webhook Integrations

Send form submissions anywhere — Mailchimp, Zapier, Salesforce — with simple, explicit field maps. No plugins, no guesswork.

Setup and code examples live in AGENTS.md → “Webhook Adapters.”

---

## 🧭 Agent Brief Wizard

Run an interactive command to generate a project‑specific prompt that tells any AI agent exactly how to build out your site within Sitekit on Laravel.

```bash
podium art app:agent-brief
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
