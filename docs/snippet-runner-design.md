# Design: Atlantis Fleet Snippet Runner

**Status:** Draft for review
**Author:** Nick P and Claude Code
**Date:** 2026-07-18
**Repos touched:** `a8csp-atlantis`, `opsoasis`, `team51-cli`

---

## 1. Motivation

When a core/plugin regression breaks something across our fleet coupled with a delayed fix rollout, one of our only remediations today is: write a fix into Atlantis → cut a release → force-update the plugin on hundreds of sites via the WPCOM dashboard (tedious) or the new `jetpack:plugin-update` batch command (better, but still in development and still needs a full release cycle).

We want to deploy a **small unit of remediation code** to every Team 51 site running Atlantis *without* cutting an Atlantis release and *without* SSH-iterating sites — add it in one place, have it land on the whole fleet.

Two prior workstreams establish the pattern we build on:

- **opsoasis#390** — `POST wpcomsp/wpcom/v1/sites/batch/plugin-update`: a central endpoint that fans out concurrently (Amphp) to per-site WPCOM v1.2 REST calls using the shared **blog/partner token**.
- **team51-cli#138** — `jetpack:plugin-update <slug>`: thin CLI that collects a site-id list and delegates one batch call to that endpoint.

This design is the **write-side twin** of the existing read-side
`sites/batch/atlantis-status` → `a8csp-atlantis/v1/status` pattern.

---

## 2. Goals / Non-Goals

### Goals
- Deploy an **arbitrary PHP snippet** (a small mu-plugin-shaped unit of code) to all managed Team 51 sites, on demand, in minutes — no Atlantis release required.
- Snippet content is **only ever delivered to sites OpsOasis already manages** — never exposed to random public installs of the (public) Atlantis repo.
- Snippet is **cryptographically signed**; a site refuses to run anything it can't verify.
- A bad snippet **cannot fatal a site** — it fails closed, and there's a fleet-wide kill switch.
- Full lifecycle (deploy / observe / reconcile / remove) lives in existing Team 51 tooling: OpsOasis batch endpoints, the CLI, and Atlantis wp-cli commands.

### Non-Goals
- Not a general-purpose "run any command everywhere" console — this is scoped to deploying/removing signed remediation snippets.
- Not a replacement for real fixes — snippets are temporary; every active snippet should have a tracking issue and an expiry.
- No arbitrary-PHP paste-from-a-textbox-by-anyone. Deploy is gated to authorized operators and signed by an offline/HSM-ish key held only by OpsOasis.
- Not a pull/poll model (see §3 for why).

---

## 3. Trust Model — the load-bearing decision

**Principle: capability by membership, not by credential.**

Atlantis is a **public repo**. Anyone can install it. That kills the pull model:

- A pull model has the site call OpsOasis and say *"I'm a Team 51 site, give me code."*
  OpsOasis then has to authenticate an untrusted claim. Any secret shipped in the public
  plugin is not secret; a per-site secret in `wp-config` just moves the bootstrap/rotation
  problem upstream.
- Worse, **signing does not give confidentiality.** A signed *pull* manifest would still be
  *readable* by any public install that fetched it — leaking our remediation code and, by
  extension, the vulnerabilities it patches.

A **push** model dissolves both problems:

- **Membership is not claimed by the site — it is known by OpsOasis.** The authoritative roster is the WPCOM partner / Jetpack-connected set (`get_wpcom_jetpack_sites()`). A random Atlantis install is simply never in the push list. There is no door to knock on.
- **Confidentiality comes free:** code is only ever deposited into sites OpsOasis already controls. It is never served to an anonymous caller.

Two orthogonal properties, both required, each from a different mechanism:

| Property | Protects against | Provided by |
| --- | --- | --- |
| **Confidentiality** | non–Team-51 sites *seeing* the code | **push-only delivery** |
| **Authenticity / integrity** | a site running *forged/tampered* code | **Ed25519 signature** |

The site is a **passive recipient**: it never initiates, never asks OpsOasis for anything. It validates a signature on what it is handed, then stores/loads it.

---

## 4. Architecture Overview

```
Operator
  │  team51 wpcom:atlantis-snippet-deploy ./fix.php --canary … --yes
  ▼
team51-cli  (Symfony Console)
  │  collects site-id list (get_wpcom_jetpack_sites, filters)
  │  one batch POST  →  API_Helper::make_wpcom_request()
  ▼
OpsOasis  POST wpcomsp/wpcom/v1/sites/batch/atlantis-snippet-deploy
  │  - signs payload with Ed25519 PRIVATE key (held only here)
  │  - Amphp concurrent fan-out over the managed roster
  │  - per-site call via WPCOM v1.2 REST proxy + blog/partner token
  ▼
Each managed site POST  a8csp-atlantis/v1/snippets (Atlantis REST receiver)
  │  - verify Ed25519 signature against PUBLIC key baked into Atlantis
  │  - store snippet record (custom table)
  │  - materialize as mu-plugin file (or mark active for in-process loader)
  ▼
Atlantis runtime
  - loads active, verified snippets (try/catch per snippet)
  - auto-disables a snippet that fatals; global kill switch respected
```

Nothing in this path lets a non-managed site receive a snippet, and nothing lets a
managed site run an unsigned one.

---

## 5. Delivery Path

### 5.1 CLI — `team51-cli`

New command `commands/WPCOM_Atlantis_Snippet_Deploy.php`
(`#[AsCommand(name: 'wpcom:atlantis-snippet-deploy')]`), mirroring `Jetpack_Plugin_Update`:

```
team51 wpcom:atlantis-snippet-deploy <path-to-snippet.php>
    [--id=<slug>]            # stable id; re-deploying same id = update
    [--site=<id|domain>]     # canary: single site
    [--tag=<sticker>]        # target a cohort (e.g. woocommerce sites)
    [--expires=<iso8601>]    # auto-expiry
    [--dry-run]
    [--yes]
```

- Reads the snippet file locally.
- Resolves target set from `get_wpcom_jetpack_sites()` (+ filters). Exact-match cohorts only — no fuzzy fleet-wide writes (guardrail from #138).
- Sends **one** batch request; the server does the parallelism.
- Prints a `Site | Result | Version | Hash` table; non-zero exit on any failure.

Companion commands (thin, same helper file `includes/functions-wpcom.php`): `wpcom:atlantis-snippet-list`, `wpcom:atlantis-snippet-remove <id>`, `wpcom:atlantis-snippet-status` (which sites have which snippets active — reuses the atlantis-status batch shape).

### 5.2 OpsOasis endpoint

New route in `plugins/opsoasis-endpoints/src/WordPressCOM/Sites_Controller.php`:

```
POST wpcomsp/wpcom/v1/sites/batch/atlantis-snippet-deploy
  body: { sites: [ids], id, code, expires?, notes? }
  - guarded by update_item_permissions_check (authorized operators only)
  - SIGN: signature = sodium_crypto_sign_detached( canonical(payload), PRIVATE_KEY )
      PRIVATE_KEY stored as an encrypted OpsOasis secret (wpcomsp_get_encrypted_option)
  - fan out via opsoasis_make_wpcom_api_concurrent_requests()
      to rest/v1.2/sites/$site/… proxied to a8csp-atlantis/v1/snippets
  - returns per-site { stored, hash, error? }
```

Sibling routes: `…/atlantis-snippet-remove`, `…/batch/atlantis-snippet-status` (the status one can piggyback on the existing atlantis-status batch by adding a `snippets` block to the Atlantis status payload).

Reconciliation (phase 2): an Action Scheduler background task (`opsoasis_schedule_background_task`) that periodically diffs desired-vs-actual snippet state across the roster and re-pushes drift — closes the offline/new-site gap. All OpsOasis-initiated; still zero inbound trust.

### 5.3 Atlantis REST receiver

New `Snippets` module (extends `AbstractModule`) exposing:

```
POST a8csp-atlantis/v1/snippets           # deposit (create/update)
DELETE a8csp-atlantis/v1/snippets/<id>    # remove
GET  a8csp-atlantis/v1/snippets           # list (for status batch)
```

- Auth: same posture as the existing status controller — `manage_options` /  Jetpack-tunneled WPCOM partner calls. **Plus** the signature check below, which is the real gate. (REST auth stops randoms from poking the endpoint; the signature stops a compromised transport from planting code.)
- On deposit: verify signature → persist → materialize (see §7).

---

## 6. Payload Format & Signing

### 6.1 Envelope

```json
{
  "id": "wc-94-checkout-coupon-total",
  "version": 3,
  "code": "<?php add_filter( 'woocommerce_calculated_total', /* … */ );",
  "created": "2026-07-18T14:00:00Z",
  "expires": "2026-08-01T00:00:00Z",
  "notes": "WC 9.4 coupon total regression; tracking: PROJ-1234",
  "sha256": "<hex of code>"
}
```

The **signed message** is a canonical serialization of the envelope *minus* the signature field (stable key order, no incidental whitespace) so the site verifies exactly what was signed.

### 6.2 Keys

- **Ed25519** via libsodium (`sodium_crypto_sign_*`). Atlantis already depends on libsodium (Messages encryption uses `sodium_crypto_secretbox`), so no new dependency.
- **Private key**: generated offline, stored **only** in OpsOasis as an encrypted secret. Never in the plugin, never in the CLI.
- **Public key (verify-only)**: baked into the Atlantis repo as a constant. Public by design — a public key in a public repo is fine. Support a small **array** of public keys to allow rotation (accept old+new during a rotation window).

### 6.3 Why signing, given push already restricts delivery

Defense in depth. Push protects confidentiality and normal-path integrity, but the site's REST receiver is still a network endpoint. Signing means that even if someone reached the receiver (proxy bug, stolen partner token, compromised OpsOasis DB row), they **still** cannot make a site execute code without the offline private key. Auth gates *who can knock*; the signature gates *what can run*.

---

## 7. Execution & Resilience (on-site)

### 7.1 Source of truth vs. materialized artifact

The single most important on-site invariant:

> **The DB row is the durable source of truth. The mu-plugin file is a re-creatable > cache of it.**

- **DB record** (`wp_a8csp_atlantis_snippets`, §8) is what a push actually writes. It lives in the database, which **code deploys do not touch** — so active snippets survive normal DeployHQ deployments, plugin updates, and filesystem resets.
- **mu-plugin file** is a materialization of an `active` DB row, written for the load-timing and audit benefits below. It can be wiped (by a clean deploy sync, a filesystem reset, etc.) and the site self-heals it (§7.3).

Nothing here is committed to any site repo — neither the loader nor the snippet files. See
§7.4.

### 7.2 Delivery form — materialize as an mu-plugin file (recommended default)

- A verified `active` snippet is written to `wp-content/mu-plugins/atlantis-snippets/<id>.php` with a header comment (id, version, hash, expiry, deployed-at).
- A **loader drop-in** (`wp-content/mu-plugins/atlantis-snippets-loader.php`) `require`s each materialized snippet inside a `try/catch(\Throwable)`, so one bad snippet skips itself rather than fataling the site — the exact pattern from commit `f543e18` ("survive mid-update autoload races").
- **Why mu-plugin:** loads *before* regular plugins (can intercept hooks a regular plugin — including Atlantis — would be too late for), always-on (a site owner can't toggle it off), native `include` (real `file:line` in traces, opcache-friendly, greppable/auditable on the box), and file-drop = deploy / file-delete = remove.

### 7.3 The loader is a self-installed drop-in — reconcile on init

We do **not** ship the loader to site repos. Atlantis, which is already installed on every site, **plants its own drop-in** (the same pattern caching plugins use for `object-cache.php`):

```php
// on activation, and defensively on init if missing
if ( ! file_exists( WPMU_PLUGIN_DIR . '/atlantis-snippets-loader.php' ) ) {
    copy( __DIR__ . '/stubs/snippets-loader.php', WPMU_PLUGIN_DIR . '/atlantis-snippets-loader.php' );
}
```

On every init, Atlantis runs a cheap **reconcile pass** that makes the filesystem match the DB:

> for each `active` snippet row: ensure `mu-plugins/atlantis-snippets/<id>.php` exists and its
> hash matches; (re)materialize if missing/stale. For each non-`active`/removed row or orphan
> file: delete the file. Ensure the loader drop-in exists.

Consequences:

- **Survives deploys.** A clean deploy that wipes runtime drop-ins is transparent — the DB rows persist and the next request re-materializes everything. The push had to happen *once*; durability is the DB + reconcile, not anything in a repo.
- **Self-repairing.** Manual tampering, partial filesystem resets, and mid-update races all converge back to the DB-declared state.
- **Clean uninstall.** Atlantis uninstall removes the snippet files *and* the loader drop-in so nothing outlives the plugin.

### 7.4 Repo impact — none for site repos

- The loader drop-in and per-snippet files are **runtime-materialized**, never committed. No edit to any of the hundreds of site repos.
- Deploy hygiene: add the snippets dir + loader to the deploy-side `.gitignore` so runtime-materialized files don't surface as a dirty working tree that blocks a deploy.
- Only the three tool repos (`a8csp-atlantis`, `opsoasis`, `team51-cli`) change, and Atlantis reaches sites via its normal release.

### 7.5 Guardrails (reuse existing resilience philosophy)

1. **Signature re-check** before every materialization; refuse on mismatch/expiry.
2. **`try/catch(\Throwable)` per snippet** — a fatal in one is logged, and that snippet is  quarantined (`status = quarantined`, auto-disabled after N consecutive fatals), never the whole site.
3. **Global kill switch** — a single option / remote flag (piggybacking the autoupdates  settings fetch) that disables the loader entirely. This is the "oh no" button.
4. **Expiry** — expired snippets are inert and cleaned up by the reconcile pass.
5. **Front-end safety** — like Autoupdates skips front-end init, the loader and reconcile pass must be cheap; heavy work stays off the hot path.

### 7.6 Filesystem-writability fallback

mu-plugin materialization needs `wp-content/mu-plugins/` writable by the web process (fine on Pressable / WPCOM Atomic in the common case, but confirm per host). Where it is **not** writable, the same DB row is loaded **in-process** from Atlantis's own bootstrap instead (`eval`/`require` the stored code). This loses the early-load timing and some auditability, so it is a per-host fallback, not the default. The DB-as-source-of-truth model means both paths read the identical record — only the execution surface differs.

---

## 8. Data Model

Custom table `wp_a8csp_atlantis_snippets` (dbDelta, mirrors the Messages table pattern). This table is the **on-site source of truth** (§7.1): a push writes a row here; the mu-plugin file is a materialized cache of `active` rows and is rebuilt from this table by the reconcile pass. Because it lives in the DB, it survives code deploys and filesystem resets.

| column | notes |
| --- | --- |
| `id` (varchar, unique) | stable slug, e.g. `wc-94-checkout-coupon-total` |
| `version` (int) | bumps on redeploy of same id |
| `code` (longtext) | the PHP (optionally stored encrypted at rest) |
| `sha256` (char 64) | integrity check / dedupe |
| `signature` (text) | detached Ed25519 signature (base64) |
| `status` (varchar) | `active` / `quarantined` / `expired` / `removed` |
| `expires_at` (datetime, null) | auto-expiry |
| `deployed_at` / `updated_at` | audit |
| `last_error` (text, null) | last fatal captured by the loader |

Module settings (`a8csp_module_snippets`) hold: enabled flag, global kill switch, public key set, quarantine threshold.

---

## 9. Management Surface

### Atlantis wp-cli (per-site handle without SSH-spelunking)
```
wp atlantis snippet list                 # active snippets + hashes + status
wp atlantis snippet verify [<id>]        # re-check signatures
wp atlantis snippet remove <id>          # local removal
wp atlantis snippet flush                # nuke all (local kill switch)
wp atlantis snippet show <id>            # dump code + metadata for audit
```

### Fleet (CLI → OpsOasis batch)
- `wpcom:atlantis-snippet-deploy` / `-remove` / `-list` / `-status`
- Status view = which sites run which snippet versions (drift detection).

### Audit
Every deploy/remove records operator, timestamp, target set, and hash — on the OpsOasis side (source of truth) and echoed into each site's snippet record + a WP action log.

---

## 10. Rollout & Safety Protocol

1. **Author** the snippet locally; it must be a self-contained, idempotent mu-plugin unit.
2. **Canary** — `--site` (or `--tag=canary`) to a handful of sites first; verify.
3. **Cohort/fleet** — expand by sticker/tag or full roster.
4. **Observe** — `snippet-status` for drift; PHP error monitoring (`pressable_get_php_errors` / WPCOM logs) for regressions.
5. **Expire/Remove** — set `--expires`, or `snippet-remove <id>` when the real fix ships.
6. **Kill switch** — global flag disables all snippets fleet-wide in one action, delivered over the existing autoupdates-settings fetch channel so it applies even if the deploy path is unhappy.

---

## 11. Security Considerations

- **Blast radius:** arbitrary PHP on hundreds of production sites. Treat the OpsOasis private key like a production signing key: encrypted at rest, access-logged, rotatable.
- **Operator gating:** only specific OpsOasis roles can hit the deploy endpoint; the CLI requires the existing OpsOasis credential (1Password app password).
- **No sandbox illusion:** PHP `eval`/mu-plugin code runs with full web-process privileges. We are *not* claiming to sandbox it — safety comes from signing + canary + kill switch + expiry + audit, not from constraining what the code can do.
- **Key rotation:** support an array of accepted public keys so we can roll the signing key without a synchronized flag day.
- **Supply-chain framing:** this endpoint is, by design, a fleet-wide code-execution capability. It deserves the same review rigor as a release-signing pipeline.

---

## 12. Open Questions

1. **mu-plugin file vs. in-process load** — *Decided:* file is the default (early load + audit); in-process is a per-host fallback where `mu-plugins/` isn't writable (§7.6). Left open only: is there any host in our fleet where the file path is unavailable?
2. **Encrypt `code` at rest** in the site DB? Low marginal value (it runs anyway) but cheap given the existing Messages encryption. Decide.
3. **Targeting model** — full roster vs. WPCOM stickers/tags for cohorts (e.g. only WooCommerce sites). Do we already have a clean "WooCommerce sites" sticker?
4. **Fleet reconciliation cadence** — note this is distinct from the *on-site* reconcile (DB→filesystem, §7.3), which is always-on in v1. The open question is the *fleet-level* loop (OpsOasis diffing desired-vs-actual across the roster to catch offline/new sites, §5.2): push-once + manual re-run for v1, or the Action Scheduler loop from day one? (Suggest: v1 push-once, v2 fleet reconcile.)
5. **Approval flow** — does a fleet-wide deploy need a second-operator sign-off, or is canary discipline + audit sufficient?
6. **Parameterized-primitive convenience layer** — optionally allow the simple `{type:add_filter, hook, return}` toggles (no `eval`) as a lower-risk fast path for the easy cases, riding the same delivery/signing rails. Nice-to-have, not required for v1.

---

## 13. Implementation Phases

- **Phase 0 — spike:** generate keypair; hardcode public key in a branch; hand-craft a signed payload; prove one site verifies + materializes + loads + survives a deliberately broken snippet.
- **Phase 1 — Atlantis Snippets module:** custom table (source of truth), REST receiver, signature verify, self-installed loader drop-in, mu-plugin materializer, on-site reconcile-on-init (DB→filesystem), try/catch loader, kill switch, uninstall cleanup, wp-cli commands.
- **Phase 2 — OpsOasis endpoint:** signing, batch fan-out, deploy/remove/status routes, operator gating, audit.
- **Phase 3 — CLI commands:** deploy/remove/status with canary/dry-run/expiry.
- **Phase 4 — fleet reconcile loop + cohort targeting + (optional) primitive convenience layer.**

Phases 1–3 are implemented in the branches this doc ships with (see §15). Phase 4 and the hardening items in §14 are open.

---

## 14. Wire format & signing specification (as built)

The three repos must agree byte-for-byte on the signed message, or every verification fails. This is the normative spec.

### 14.1 Deploy payload (OpsOasis → each site `POST a8csp-atlantis/v1/snippets`)

```json
{
  "snippet_id": "wc-94-checkout-total",
  "version":    1752845200,
  "code":       "<base64 of the raw PHP file bytes>",
  "sha256":     "<lowercase hex sha256 of the raw code bytes>",
  "expires":    "2026-08-01T00:00:00Z",   // or ""
  "notes":      "WC 9.4 coupon total regression; PROJ-1234",
  "signature":  "<base64 detached Ed25519 signature>"
}
```

- **code** is carried as base64 so the PHP survives JSON/HTTP/DB byte-for-byte. The site base64-decodes it and writes the exact bytes to `mu-plugins/atlantis-snippets/<id>.php`.
- The site **recomputes** sha256 over the decoded bytes and rejects on mismatch.

### 14.2 Canonical signed messages

The Ed25519 signature is over these exact strings (LF-joined, no trailing newline). The content is bound via `sha256`, so signing this short header is equivalent to signing the code.

```
deploy:  "a8csp-atlantis-snippet-deploy" LF "v1" LF <snippet_id> LF <version> LF <sha256> LF <expires>
remove:  "a8csp-atlantis-snippet-remove" LF "v1" LF <snippet_id> LF <version>
```

`<version>` is the integer rendered as a decimal string; `<expires>` is the ISO-8601 string or empty. Implemented identically in `Signature::deploy_message()`/`remove_message()` (Atlantis) and `Atlantis_Snippets_Controller::deploy_message()`/`remove_message()` (OpsOasis).

### 14.3 Replay protection

`version` is monotonic per `snippet_id`. The CLI defaults it to the current Unix timestamp. A site rejects a deploy whose version ≤ the stored version, and a removal whose version ≤ the stored version — so an old signed payload cannot be replayed to downgrade or un-remove.

### 14.4 Key provisioning runbook

1. Generate an Ed25519 keypair (once), e.g. via libsodium `sodium_crypto_sign_keypair()`.
2. **Private key** → store on OpsOasis, encrypted, from a non-web context (the secrets registry guard rejects web-context writes): `wp eval "wpcomsp_update_encrypted_option('opsoasis_atlantis_snippet_signing_key', ['secret_key' => '<hex secret key>']);"`
3. **Public key** → add the hex to `Signature::DEFAULT_PUBLIC_KEYS` in Atlantis and ship it in the release. Until this is done, verification fails closed and nothing is deployable (safe default). Sites may also set `A8CSP_ATLANTIS_SNIPPET_PUBLIC_KEYS` in wp-config to add/override keys. The array form supports rotation (accept old+new during a window).

---

## 15. Appendix — File inventory (as built)

**a8csp-atlantis** (branch `add/fleet-snippet-runner`):
- `src/Modules/Snippets/CustomTable.php` — `wp_a8csp_atlantis_snippets` schema (source of truth)
- `src/Modules/Snippets/SnippetStore.php` — data-access layer (upsert/remove/status/failure)
- `src/Modules/Snippets/Signature.php` — verify-only Ed25519 + canonical messages + public keys
- `src/Modules/Snippets/Loader.php` — materialize + reconcile-on-init + can_materialize
- `src/Modules/Snippets/DropIn.php` — self-installed loader drop-in + kill-switch marker
- `src/Modules/Snippets/stubs/snippets-loader.php` — the mu-plugin loader template
- `src/Modules/Snippets/Snippets.php` — the module (extends AbstractModule)
- `src/REST/Snippets_Controller.php` — REST receiver (deploy / remove / list)
- `src/CLI/Snippet_Command.php` — `wp atlantis snippet list|show|verify|remove|flush|reconcile|disable|enable`
- `includes/module-snippets.php` — helper wrappers (incl. loader failure hook)
- wired in `src/Modules.php` (register module) and `src/Plugin.php` (register wp-cli command)
- `docs/snippet-runner-design.md` — this document

**opsoasis** (branch `add/fleet-snippet-runner`):
- `plugins/opsoasis-endpoints/src/WordPressCOM/Atlantis_Snippets_Controller.php` — signing + `sites/batch/atlantis-snippet-deploy` / `-remove` / `-status` fan-out
- wired in `plugins/opsoasis-endpoints/src/Plugin.php`
- signing key stored via existing `wpcomsp_update_encrypted_option()` (no new secret plumbing)

**team51-cli** (branch `add/fleet-snippet-runner`):
- `commands/WPCOM_Atlantis_Snippet_Deploy.php` — `wpcom:atlantis-snippet-deploy <file>` (php -l gate, canary, dry-run, expiry)
- `commands/WPCOM_Atlantis_Snippet_Remove.php` — `wpcom:atlantis-snippet-remove <id>`
- `commands/WPCOM_Atlantis_Snippet_Status.php` — `wpcom:atlantis-snippet-status` (drift/issues)
- `includes/functions-wpcom.php` — `deploy_/remove_/get_..._atlantis_snippet_status_batch()` helpers

**Reference precedents:**
- opsoasis#390 / team51-cli#138 (plugin-update batch) — reach + trust pattern
- `sites/batch/atlantis-status` → `a8csp-atlantis/v1/status` — read-side twin this mirrors
- Autoupdates settings fetch — remote-config + kill-switch channel
- commit `f543e18` — per-unit try/catch resilience pattern
- `Plugin::register_core_compat_filters()` (the `wp_img_tag_add_auto_sizes` mitigation) — the hardcoded, release-gated pattern this feature generalizes
