# Bot Protection

Team 51's control plane for **WP Cloud Bot Protection** (the external name for
"blackbox") — the login and password-reset gating shipped as the
`wpcloud-bot-protection` mu-plugin on WP Cloud sites.

The mu-plugin resolves enablement from a set of tiers — the per-site
`WPC_BOT_PROTECTION_ENABLED` constant, a platform define, and a client-level
percentage rollout — and exposes the `wpcloud_bot_protection_enable` filter
(evaluated late on `plugins_loaded`) as a per-request override. In the
currently deployed loader that override reliably **disables** but does **not
enable** (verified in a live WP Cloud environment). So this module is a
per-site **off-switch / override**, not an enabler.

## Enforcement states

The module is mandatory (always loaded) and exposes a single `state` setting
under **Atlantis → Modules → Bot Protection**:

- `inherit` (default) — register nothing; WP Cloud's own tiers decide. Shipping
  the module changes no behavior.
- `off` — force disabled (`__return_false`); a hard override for a site where
  protection is causing problems, even against a client-level rollout.

There is deliberately no `on`: because the filter cannot enable a site that no
tier has armed, an `on` would be indistinguishable from `inherit` in every real
case and only invite confusion. To **enable** a site, arm a tier — set
`WPC_BOT_PROTECTION_ENABLED = true` (e.g. a one-off or a bulk `wp-config`
script), or have the client enabled via the platform percentage rollout.

The `off` filter is registered at `PHP_INT_MAX` priority so Atlantis's verdict
is the final say. Registration happens during the plugin's `plugins_loaded`
init (priority 10), before the mu-plugin loader evaluates the filter at
`PHP_INT_MAX`.

Verified matrix:

| `WPC_BOT_PROTECTION_ENABLED` | Atlantis `state` | Result                    |
| ---------------------------- | ---------------- | ------------------------- |
| `true`                       | `inherit`        | on                        |
| `true`                       | `off`            | **off** (override wins)   |
| absent / `false`             | `inherit`        | off (no arming tier)      |
| absent / `false`             | `off`            | off (nothing to override) |

## Environments

The `state` applies uniformly across environments — staging and development
sites `inherit` by default just like production, so their protection tracks
whatever WP Cloud's tiers decide. A non-production site that runs login
automation (CI/e2e suites, uptime and synthetic-login monitors, scripted
provisioning) and must stay clear is set to `off` explicitly, not gated on the
environment type.

## WP Cloud precondition

The mu-plugin only loads where the WP Cloud credentials `ATOMIC_SITE_ID` and
`ATOMIC_SITE_API_KEY` are defined. On any other site — or on a WP Cloud site
that has not yet received the mu-plugin — nothing listens on the filter, so
`off` is a no-op. The settings screen surfaces a notice in that case.

## WP-CLI

```sh
wp atlantis bot-protection status
wp atlantis bot-protection set <inherit|off>
```

## Status endpoint

`GET /wp-json/a8csp-atlantis/v1/status` reports, under `modules.bot-protection`:

- `state` — `inherit` / `off`. The authoritative enforcement signal.
- `wp_cloud` — whether the WP Cloud credentials are present.
- `mu_plugin_present` — whether the mu-plugin is loaded.
- `environment` — the site's environment type (informational; enforcement is
  driven by `state`, not the environment).

The shared `enabled` field is always `true` for this mandatory module and does
not indicate enforcement; read `state` instead.
