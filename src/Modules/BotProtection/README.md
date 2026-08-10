# Bot Protection

Team 51's control plane for **WP Cloud Bot Protection** (the external name for
"blackbox") — the login and password-reset gating shipped as the
`wpcloud-bot-protection` mu-plugin on WP Cloud sites.

The mu-plugin resolves enablement from a set of tiers — the per-site
`WPC_BOT_PROTECTION_ENABLED` constant, a platform define, and a client-level
percentage rollout — and exposes the `wpcloud_bot_protection_enable` filter
(evaluated late on `plugins_loaded`) as a per-request override. This module
drives that filter. See **Enable vs. disable** below for an important
limitation of the currently deployed loader.

## Enforcement states

The module is mandatory (always loaded) and exposes a single `state` setting
under **Atlantis → Modules → Bot Protection**:

- `inherit` (default) — register nothing; WP Cloud's own tiers decide. Shipping
  the module changes no behavior.
- `on` — force enabled (`__return_true`). **Caveat:** in the deployed loader
  this only forces on where a tier has already armed the loader; it does not
  enable a site that has no enabling tier (see **Enable vs. disable**).
- `off` — force disabled (`__return_false`); a hard override for a site where
  protection is causing problems, even against a client-level rollout.

The filter is registered at `PHP_INT_MAX` priority so Atlantis's verdict is the
final say, and only on the `on` / `off` states. Registration happens during the
plugin's `plugins_loaded` init (priority 10), before the mu-plugin loader
evaluates the filter at `PHP_INT_MAX`.

## Enable vs. disable (important)

WP Cloud's *enablement* comes from the loader's tiers (the constant, a platform
define, and the client-level percentage rollout). The
`wpcloud_bot_protection_enable` filter this module drives is an **override**
layered on top — and in the currently deployed loader that override reliably
**disables** but does **not enable**. Verified in a live WP Cloud environment:

| `WPC_BOT_PROTECTION_ENABLED` | Atlantis `state` | Result                  |
| ---------------------------- | ---------------- | ----------------------- |
| `true`                       | `inherit`        | on                      |
| `true`                       | `on`             | on                      |
| `true`                       | `off`            | **off** (override wins) |
| absent / `false`             | `on`             | **off** (no arming tier)|

So:

- **To disable a site — use `off`.** This is the module's primary, reliable
  job: the per-site "escape hatch", including exempting automation/test sites.
- **To enable a site — set `WPC_BOT_PROTECTION_ENABLED = true`** (or have the
  client enabled via the platform percentage rollout). `on` alone cannot arm a
  site that no tier has enabled.

In short: `off` is the dependable lever, `inherit` respects the platform
decision, and `on` means "force-on where the loader is already armed."

## Non-production environments

On any environment where `wp_get_environment_type()` is not `production`,
protection is **forced off** unless the state is explicitly `on`. Staging and
development sites routinely run login automation — CI/e2e suites, uptime and
synthetic-login monitors, scripted provisioning — that bot protection would
challenge or block, so this keeps those sites clear by default (mirroring the
Tracking module's production gating).

- non-production + `inherit` or `off` → forced off
- non-production + `on` → on (deliberate opt-in, e.g. to test protection)
- production → states behave as described above

The determination is filterable via `a8csp_atlantis_bot_protection_is_production`
(return `true`/`false`) for sites where the environment type isn't set reliably.

## WP Cloud precondition

The mu-plugin only loads where the WP Cloud credentials `ATOMIC_SITE_ID` and
`ATOMIC_SITE_API_KEY` are defined. On any other site — or on a WP Cloud site
that has not yet received the mu-plugin — nothing listens on the filter, so the
setting is a no-op. The settings screen surfaces a notice in that case.

## WP-CLI

```sh
wp atlantis bot-protection status
wp atlantis bot-protection set <inherit|on|off>
```

## Status endpoint

`GET /wp-json/a8csp-atlantis/v1/status` reports, under `modules.bot-protection`:

- `state` — `inherit` / `on` / `off`. The authoritative enforcement signal.
- `wp_cloud` — whether the WP Cloud credentials are present.
- `mu_plugin_present` — whether the mu-plugin is loaded.
- `environment` — the site's environment type; non-production forces protection
  off unless `state` is `on`.

The shared `enabled` field is always `true` for this mandatory module and does
not indicate enforcement; read `state` instead.
