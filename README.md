# 🚀 Kavera

**A Laravel website framework built for AI agents.**

Every page is a flat Blade file. Pages need no database, no admin panel and no CMS to fight. An agent reads a folder, writes a template and the page is live. Dynamic content like posts, events and stock images is pulled from services you already use and cached in Redis, so templates render fast and never call an API at request time.

WordPress was built for humans clicking through a dashboard. Kavera was built for agents editing files. If you want an AI to build and maintain a real business site, this is the faster, cleaner and safer place to start.

---

## 🥊 Why Kavera over WordPress

- 📁 Flat file, no database: pages are plain Blade templates, so an agent can create, edit, move or delete them with ordinary file operations. Nothing to migrate, nothing to back up, nothing to get hacked through a login page.
- 🤖 Agent first: `AGENTS.md` tells any AI agent exactly how the project is laid out and how to build on it. No plugin archaeology, no theme editor, no wp_options table.
- 🧰 Turn key: clone it and run it on PHP 8.3. No extra scaffolding, no installer wizard.
- 🔌 Real features without plugins: forms with email and webhooks, events, blogging, stock images and SEO are all in the box. Zero plugin drama, zero plugin updates.
- 🛡️ Safe by default: only approved pages resolve and everything else returns 404. Forms ship with spam controls out of the box.
- 🔍 SEO ready: every page sets its own title, description and social tags, with optional schema markup and a validator to prove it.
- 🧾 Version control is the CMS: every page is a file in git. Diff it, review it, roll it back.

---

## ⚡ Quick Start

```bash
git clone https://github.com/CaneBayComputers/kavera.git my-site
cd my-site
```

Then tell your AI agent:

> Read AGENTS.md and build me a website for ...

That is the whole workflow. `AGENTS.md` contains everything the agent needs: setup, page structure, forms, integrations, images, SEO and code standards.

---

## 🎁 What’s Included

- 📄 Pages: Simple, file‑based pages you can edit fast — no CMS required.
- ✉️ Forms: Email out of the box plus webhooks (Mailchimp, Zapier, Salesforce).
- 🧠 Spam control built‑in: UA/link checks, throttling, and Google reCAPTCHA (prod).
- 🔍 SEO Setup: Title/description per page plus optional JSON‑LD schema markup with a built‑in validator.
- 🎟️ Events: Eventbrite page you can flip on with two env keys.
- 🖼️ Images: Pixabay helper for quick stock image pulls.
- 📝 Blogging: Write in Blogger, publish on your site — posts become simple pages with recents, tags, and archives.

---

## 🔌 Integrations

Everything below syncs into Redis or local storage ahead of time. Templates read the cache and never call an external API on page load.

- 📝 Blogger: public posts import as Blade files with recent, label and monthly archive listings.
- 🎟️ Eventbrite: events sync into Redis and render through simple helpers.
- 🖼️ Pixabay, Pexels and Unsplash: search and download stock images from the command line, then build an image manifest that agents use to pick images and write alt text.
- 👁️ AWS Rekognition: optional image analysis that tags objects, colors, faces and text for the manifest.
- ✉️ Form email: submissions are validated, filtered and emailed through SMTP.
- 🗄️ Form storage: optionally save submissions to a database for later review. This is the only feature that touches a database at all.
- 🔗 Form webhooks: send submissions to Mailchimp, Zapier or Salesforce with explicit field maps.
- 🧠 Google reCAPTCHA: drop in spam protection for any form.

Setup and field mapping for each integration lives in `AGENTS.md`.

---

## 📄 License

Kavera is open source under the MIT License.

- License: see `LICENSE`
- Copyright: © 2024–2025 Cane Bay Computers & Mobile Repair, LLC
- Authors: see `AUTHORS`

Note: “Kavera” and its logos are trademarks of Cane Bay Computers & Mobile Repair, LLC. The MIT license does not grant trademark rights.
