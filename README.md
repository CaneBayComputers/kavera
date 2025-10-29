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

### 🧩 Integrations

Turn‑key add‑ons you can flip on with env keys and Podium commands: Eventbrite events, stock images via Pixabay, agent brief generator, and form mailer with webhook. Check `.env.example` for what’s available.

---

### 🔌 Webhook Adapters (Pluggable)

Web form submissions can be forwarded to external services via a flexible adapter system.

- Built‑in adapters:
  - Mailchimp (list subscribe/upsert, with optional tags)
  - Zapier (catch hook, passthrough fields)
  - Salesforce (create sObject, e.g., Lead)
- Per‑form configuration uses `webhooks` (array). Backward compatible with the original `CONTACT_FORM_WEBHOOK_URL`.
- Supports HTTP method, headers, and adapter options per webhook.

Quick start (examples shown for the `contact` form in `config/form.php`):

1) Zapier (via `webhooks`)

```php
'webhooks' => [[
    'adapter' => App\FormAdapters\ZapierAdapter::class,
    'options' => [
        'url' => 'https://hooks.zapier.com/hooks/catch/XXXX/YYYY/',
        // Map contact form fields to Zapier payload keys
        'field_map' => [
            'firstName' => 'first_name',
            'lastName'  => 'last_name',
            'company'   => 'company',
            'email'     => 'email',
            'phone'     => 'phone',
            'message'   => 'message',
        ],
        // Optional constants
        'static' => [ 'source' => 'website', 'form' => 'contact' ],
        // 'include_context' => true,
    ],
]]
```

2) Mailchimp (upsert + optional tags)

```php
'webhooks' => [[
    // Leave url empty to let the adapter compute it from options
    'adapter' => App\FormAdapters\MailchimpAdapter::class,
    'options' => [
        'api_key'     => env('MAILCHIMP_API_KEY'),
        'audience_id' => env('MAILCHIMP_AUDIENCE_ID'),
        'status'      => env('MAILCHIMP_STATUS', 'subscribed'),
        // Optional: add tags after upsert
        // 'tags'       => env('MAILCHIMP_CONTACT_TAGS'), // comma-separated
        // Or set per-form env (comma-separated), e.g. MAILCHIMP_CONTACT_TAGS
        // Optional: map form fields to Mailchimp merge fields (consistent with Salesforce)
        // 'field_map' => [
        //     'FNAME'   => 'first_name',
        //     'LNAME'   => 'last_name',
        //     'COMPANY' => 'company',
        //     'PHONE'   => 'phone',
        //     // Example address mapping (requires these fields to exist in your form)
        //     'ADDRESS' => [
        //         'addr1'   => 'address1',
        //         'addr2'   => 'address2',
        //         'city'    => 'city',
        //         'state'   => 'state',
        //         'zip'     => 'zip',
        //         'country' => 'country',
        //     ],
        // ],
    ],
]]

Note: To add tags, pass a simple `tags` array to the Mailchimp options. The adapter will send a follow‑up request to the tags endpoint automatically with `status: active`.

3) Salesforce (sObject create)

```php
'webhooks' => [[
    // Let adapter compute from base URL, API version, and object
    'adapter' => App\FormAdapters\SalesforceAdapter::class,
    'options' => [
        'base_url'    => env('SALESFORCE_BASE_URL'),            // e.g. https://myinstance.my.salesforce.com
        'api_version' => env('SALESFORCE_API_VERSION', 'v59.0'),
        'object'      => env('SALESFORCE_OBJECT', 'Lead'),
        // 'access_token' => env('SALESFORCE_ACCESS_TOKEN'),    // or pass headers via webhook
        // Optional mapping (Salesforce field => form key); defaults map LastName, Company, Email, Phone, Description
        // 'field_map' => [ 'LastName' => 'name', 'Company' => 'company', 'Email' => 'email' ],
        'defaults' => [ 'Company' => env('SALESFORCE_DEFAULT_COMPANY', 'Unknown') ],
    ],
]]
```
```

Notes
- The Mailchimp adapter computes the member upsert URL by deriving the data center from your API key suffix (e.g., `abcd-us21` → `us21`), plus Audience ID and the submitted `email` (md5 hash). It sends Authorization: Basic with your API key. `status` can be set via `MAILCHIMP_STATUS`.
- Tags: you can set `'tags' => env('MAILCHIMP_CONTACT_TAGS')` (comma-separated), or pass a `tags` array in config.
- Prefer `webhooks: [ ... ]` for multiple destinations; each can use a different adapter and headers.

### 🧰 Content Page Prompt Creator (Agent Brief Wizard)

Use an interactive Artisan command to generate a complete AI Agent prompt tailored to your site and content. It asks about pages, forms, tone, sections, business profile, demographics, social links, and more—then prints and saves a ready‑to‑paste brief that instructs agents to follow AGENTS.md.

Run from the project directory:

```bash
podium art app:agent-brief
```

What you get:
- Structured prompt with page‑by‑page section hints
- Color palette (Coolors.co URL supported) and style notes
- References and existing links for inspiration
- Form targets, success redirects, and webhook notes


---

### 🛡️ Security

* Only alphanumeric, slash, and dash URLs allowed
* No periods, underscores, query strings
* Middleware ensures no invalid paths reach the page handler
* Redis-backed lookup for fast validation without DB hits

---

### 💬 Notes

After adding or removing `.blade.php` files in `resources/views/content/`, refresh the content list with Podium:

```bash
podium art app:update-content-list
```

---

### 🤖 Agent‑Ready Workflow (Podium + Flat‑File)

Agent‑friendly by design. Drop images (and an optional `resources/content/<page>.yaml` manifest) and let your automation assemble pages under `resources/views/content/` — all inside Podium (`podium art`, `podium composer`, `podium php`).

Clone with Podium, place content, and render pages — no database required.

---

### 🎟️ Eventbrite

Built‑in events page. Add your private token and organization ID to `.env`, sync with Podium, and visit `/events`.

- Sync events cache: `podium art app:update-eventbrite`
- Discover org ID: `podium art app:eventbrite-organizations`
- Refresh app caches after `.env` changes: `podium cache-refresh`

---
