# Crawler Files

Appends rules to the site's **robots.txt** and serves a site-root **llms.txt**,
both edited under **Settings → Robots & llms.txt**.

The module ships enabled but does nothing until someone saves content: empty
robots.txt rules append nothing, and an empty llms.txt serves nothing.

## Who can edit

- **Automatticians** (administrators with an `a8c.com`, `automattic.com` or
  `wordpress.com` address) can always open the editor.
- **Other administrators** can open it only when an Automattician ticks
  **Editor access** under **Atlantis → Modules → Crawler Files**. That screen
  saves through the modules settings group, which only Automatticians can
  write, so a client administrator cannot grant themselves access.
- Nobody below administrator can edit.

The editor is a Settings submenu rather than an Atlantis submenu because the
Atlantis menu is hidden from everyone who is not an Automattician.

The content saves through its own settings group, `a8csp_crawler_files_group`,
whose `options.php` capability is pinned to `a8csp_atlantis_edit_crawler_files`.
Without that, core would accept a POST from any `manage_options` user even with
the page hidden from them.

The Automattician check is the same one the rest of Atlantis uses. It stops a
client administrator who only has the profile screen, but not one who can run
code (plugin editor, WP-CLI), who can set their own email directly. Treat it as
a guardrail against accidental edits, not a security boundary. On multisite,
super admins pass every capability check, as everywhere else in WordPress.

## When the files are served

Both files are served only when all of these are true:

- The site is **production** (`wp_get_environment_type()`), the same test the
  Tracking module and Safety Net use. A staging copy that Safety Net is
  protecting never serves these rules. Override with the
  `a8csp_atlantis_crawler_files_serve` filter.
- **Yoast SEO is not active.** Yoast has its own robots.txt editor (Tools →
  File editor, which writes a physical robots.txt) and llms.txt generator (which
  writes a physical llms.txt). While Yoast is loaded the module serves nothing,
  and the editor links to Yoast's tools and shows anything saved earlier
  read-only. Override with the `a8csp_atlantis_crawler_files_defer_to_yoast`
  filter.
- There is **no physical file** of the same name in the web root. The web
  server sends a physical file without asking WordPress; the editor warns when
  one exists.

## robots.txt

Rules are **appended** to whatever WordPress, the host and other plugins put in
robots.txt; nothing is replaced. The `robots_txt` filter runs at `PHP_INT_MAX`
so the rules come last, after Yoast's 99999 filter and anything else.

Because the rules land after everything else, a rule before the first
`User-agent` line would join whichever crawler group happens to end
robots.txt. Saving checks the rules:

| Check | Result |
| --- | --- |
| Allow/Disallow/Crawl-delay before any `User-agent` | Error, not saved |
| Larger than 500 KB (Google ignores the rest) | Error, not saved |
| `Disallow: /` or `/*` for `*`, Googlebot or Bingbot | Not saved until the confirmation box is ticked |
| Unknown directive, malformed line, path not starting `/` or `*` | Warning, saved |
| `Sitemap:` not an absolute URL, or on another host | Warning, saved |

When a save is refused, the submitted text is shown again in the editor so it
is not lost.

## llms.txt

The editor holds the whole file; core has nothing to append to. It is served
early on `init` (priority 0) for `GET`/`HEAD` requests to exactly `/llms.txt`,
so no rewrite rules need flushing. Sites whose address includes a path
(`example.com/blog`) cannot serve the domain root and serve nothing, the same
rule core applies to robots.txt.

Response headers:

```text
Content-Type: text/plain; charset=utf-8
X-Content-Type-Options: nosniff
X-Robots-Tag: noindex, follow
Cache-Control: public, max-age=300
```

Content is stored as typed apart from line endings, control characters and
surrounding whitespace. It is not HTML-escaped or run through
`sanitize_textarea_field()`, which would strip `%20`-style sequences from URLs;
it is safe because it is only ever served as `text/plain` with `nosniff`, and
shown in the editor through `esc_textarea()`.

When llms.txt is empty, the editor offers starter content built from the site
name, tagline and top-level pages. It fills the editor only; nothing is saved
until the user saves.

WP.com and Pressable both pass a missing `/llms.txt` through to WordPress
(checked on live sites on each). The approach is the one an existing
Special Projects site-specific llms.txt editor already uses in production on
WP.com.

Filters:

- `a8csp_atlantis_llms_txt` — the content about to be served. Code can supply
  the whole file here.
- `a8csp_atlantis_llms_txt_cache_max_age` — the `Cache-Control` max-age in
  seconds (default 300).

## Storage

| Option | Autoload | Holds |
| --- | --- | --- |
| `a8csp_module_crawler-files` | yes | `enabled`, `allow_admins` (`'0'`/`'1'`) |
| `a8csp_atlantis_robots_txt_rules` | no | The rules to append |
| `a8csp_atlantis_llms_txt` | no | The llms.txt content |
| `a8csp_atlantis_crawler_files_changes` | no | Who last changed each file, and when |

Disabling the module or deactivating Atlantis keeps the content; it is served
again when the module is re-enabled.

## Status endpoint

`GET /wp-json/a8csp-atlantis/v1/status` reports, under `modules.crawler-files`:

- `allow_admins` — whether every administrator may edit.
- `serving` — whether saved content is served right now (production and no Yoast).
- `deferred_to_yoast` — whether Yoast SEO is active.
- `environment` — the site's environment type.
- `robots_txt_rules`, `llms_txt` — whether any content is saved.
