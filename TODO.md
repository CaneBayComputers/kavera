# TODO: Images Manifest (Agent-Only)

Purpose
- Centralize image metadata in `storage/app/images-manifest.yaml`.
- Provide high‑quality alt text and captions for accessibility/SEO.
- Declare intended usage (page/section/role) and relative priority for selection.
- Serve as the AI agent’s source of truth when generating pages. Not consumed by Blade at runtime.

Proposed Schema (YAML)
- images: list of image entries
- entry fields:
  - file: string; path under `public/images/` (e.g., `hero/elephant-1.jpg`).
  - page: string; slug mapped to Blade file in `resources/views/content` (e.g., `index`, `services`).
  - section: string; logical area within page (e.g., `hero`, `features`, `gallery`, `team`).
  - role: string; hint for layout (e.g., `banner`, `card`, `headshot`, `logo`, `gallery`).
  - priority: integer; lower is higher priority for selection/placement.
  - alt: string; human‑authored alt text (primary source).
  - caption: string; optional visible caption.
  - credit: string; attribution/source (e.g., Pixabay user) if required.
  - tags: list<string>; optional curated tags (can include Rekognition/Pixabay tags).
  - aspect: string; optional target ratio (e.g., `16:9`, `1:1`) for cropping.

Implementation Tasks (Agent workflow)
- Agent parser: read `images-manifest.yaml` and validate schema; fail fast on errors.
- Selection: for each target page/section, pick images by `priority` and `role`; ensure diversity and fit (aspect/size heuristics allowed).
- Alt text: prefer manifest `alt`; fallback chain → curated `tags` → Rekognition/Pixabay tags → filename normalization.
- Generation: when creating Blade pages, reference chosen assets by path (e.g., `images('...')`) and embed alt/captions directly in markup.
- Enrichment: optional step to fetch Pixabay candidates and run Rekognition on shortlist to propose `tags` and draft `alt` back into the manifest (manual review flow encouraged).
- CLI support: utilities to generate/update the manifest from `public/images/` (dry‑run by default; `--force` to write) and to validate entries.

Agent Workflow (Outline)
- Validate manifest → shortlist per page/section → optionally enrich with Rekognition → prompt user approval (thumbnails/UI) → write/update manifest fields → generate/update Blade pages using selected images and alt/captions → refresh content registry (`php artisan app:update-content-list`).

Automation Ideas (Optional)
- Pixabay shortlist by query → Rekognition labels/moderation on top N → propose entries with pre‑filled `tags` and draft `alt`.
- Diversity: cluster by labels/embeddings to avoid near‑duplicates in galleries.
- Slot awareness: enforce aspect/size constraints per section (e.g., hero vs card) during selection.

Notes
- Manifest is not read by Blade at runtime; it informs the agent’s generation only.
- Do not overwrite user‑authored `alt`/`caption` once present in manifest.
- Keep a dry‑run path for all write operations; never touch non‑manifest files without `--force`.
- Works with current `resources/views` symlink setup; Agent Brief may materialize views before writing.
