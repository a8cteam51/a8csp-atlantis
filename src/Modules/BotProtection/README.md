# Bot Protection

Team 51's control plane for **WP Cloud Bot Protection** (the external name for
"blackbox") — the login and password-reset gating shipped as the
`wpcloud-bot-protection` mu-plugin on WP Cloud sites.

The mu-plugin resolves enablement through the `wpcloud_bot_protection_enable`
filter, evaluated late on `plugins_loaded`. That filter overrides every other
tier: the `WPC_BOT_PROTECTION_ENABLED` constant, the platform define, and the
client-level percentage rollout. This module drives that filter.

## Enforcement states

The module is mandatory (always loaded) and exposes a single `state` setting
under **Atlantis → Modules → Bot Protection**:

- `inherit` (default) — register nothing; WP Cloud's own tiers decide. Shipping
  the module changes no behavior.
- `on` — force enabled (`__return_true`).
- `off` — force disabled (`__return_false`); a hard override for a site where
  protection is causing problems, even against a client-level rollout.

The filter is registered at `PHP_INT_MAX` priority so Atlantis's verdict is the
final say, and only on the `on` / `off` states. Registration happens during the
plugin's `plugins_loaded` init (priority 10), before the mu-plugin loader
evaluates the filter at `PHP_INT_MAX`.

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

The shared `enabled` field is always `true` for this mandatory module and does
not indicate enforcement; read `state` instead.
