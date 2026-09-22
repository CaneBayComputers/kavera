# INSTRUCTIONS.md — undo the images-in-git change; images are served from S3 (2026-09-22)

Context: fix #2 from the earlier handoff ("manifest images never reach production through git")
was solved by tracking `storage/app/public/images/` in git. That was the wrong direction. Shawn's
standing rule for every site he hosts is: **images and other static media are served from the
site's `<site>.cdn` S3 bucket, never from the web server's disk, and never committed to git.**
luxornyc has already been moved to that model and its copy of the gitignore overrides yours.
Please make Kavera itself follow the rule.

The other three fixes (key-gated reCAPTCHA, self-healing `content_list`, JSON-LD validator
message) are good and deployed. Leave them.

---

## 1. Revert the gitignore change (required)

`storage/app/public/.gitignore` is back to Laravel's default:

```
*
!.gitignore
```

## 2. Document the S3 route in AGENTS.md (required)

Replace the sentence added under "Images and the Website Manifest" step 2 ("The imported images
under `storage/app/public/images/` are tracked in git on purpose…") with the S3 route. The pieces
already exist in Kavera; they just aren't documented together:

- `cdn($path)` in `app/helpers.php` returns `https://s3.<region>.amazonaws.com/<bucket>/<path>`
  when `AWS_BUCKET` is set (path-style, which the `.cdn` bucket names require because of the dot)
  and the plain local path when it is empty. `images()`, `img()`, `css()` and friends wrap it.
- So a template written as `{{ cdn('/storage/images/1280/<id>.webp') }}` serves from local
  storage in dev and from S3 in prod with no code change. Only the `.env` differs.

Suggested text for AGENTS.md, in that step:

> Production serves images from S3, not from the server. Every image reference in a template
> must go through `cdn()` (or `images()`), e.g. `{{ cdn('/storage/images/1280/<id>.webp') }}`,
> never a literal `/storage/images/...` path. In `.env` set `AWS_BUCKET=<site>.cdn`,
> `AWS_DEFAULT_REGION=us-east-1` and `AWS_USE_PATH_STYLE_ENDPOINT=true`; leave `AWS_BUCKET`
> empty in dev to serve from local storage. Upload after every import or regeneration:
>
> ```bash
> aws s3 sync storage/app/public/images/ s3://<site>.cdn/storage/images/ \
>   --content-type image/webp --cache-control 'public, max-age=604800'
> ```
>
> The key prefix mirrors the local path so `cdn()` resolves to the same file either way.
> `storage/app/public/` stays gitignored; the images live in S3 and, for regenerating variants,
> on the dev box.

Also add the same `cdn()` rule to the "Agent Website Generation" section (item 5, alt text, or a
new item) so agents building a site never emit literal image paths. Today's luxornyc build did,
and it had to be rewritten afterwards.

## 3. Make the import command S3-aware (nice to have)

Give `app:website-manifest-import` an `--s3` flag (or read `AWS_BUCKET` and just do it) that runs
the equivalent of the `aws s3 sync` above through the `s3` filesystem disk after copying the files
locally, using `--content-type` from the file extension and a one-week `CacheControl`. Then the
whole image path is one command in both dev and prod. The `league/flysystem-aws-s3-v3` package is
already in `composer.json`, and `config/filesystems.php` has the `s3` disk, so it's mostly
`Storage::disk('s3')->put($key, $stream, ['CacheControl' => ..., 'ContentType' => ...])`.

If you do this, note in AGENTS.md that the bucket must already exist with Shawn's standard CDN
config (public `s3:GetObject` on `<bucket>/*`, all four Public Access Block flags off, Object
Ownership `BucketOwnerEnforced`). The command shouldn't create buckets.

## 4. `.env.example` (small)

Set `AWS_USE_PATH_STYLE_ENDPOINT=true` as the example default, with a comment that the `.cdn`
bucket names need path style, and a comment on `AWS_BUCKET` saying "leave empty in dev, set to
`<site>.cdn` in production".

---

Reference: the standing rule is written up in Shawn's global `~/.claude/CLAUDE.md` under
"S3 CDN buckets", and luxornyc's `BUILD-NOTES.md` shows the finished shape (templates through
`cdn()`, site-level gitignore of `/storage/app/public/images/`, the sync command).
