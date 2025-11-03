# 🚀 Kavera on Laravel — Build Websites at Agent Speed

Kavera is a Laravel-native website framework that combines flat-file content with
service-driven dynamic data. Pages are simple Blade templates you can edit by
hand or generate with an AI agent, and connected services like Blogger,
Eventbrite, Flickr, and form webhooks feed structured content into Redis for
fast, predictable rendering.

This allows a complete site to be created in minutes, not weeks: static content
is easy to modify, dynamic content comes from the tools users already know, and
forms include spam controls, validation, email handling, persistence, and
webhook integrations. Kavera includes an Agent Briefing Wizard to generate an
accurate build brief for AI assistants, but using an agent is optional — the
system works just as well with manual workflows.

The result is a website framework that stays simple at its core, scales through
services, and lets developers ship real business sites quickly and reliably.

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

Run an interactive command to generate a project‑specific prompt that tells any AI agent exactly how to build out your site within Kavera on Laravel.

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
