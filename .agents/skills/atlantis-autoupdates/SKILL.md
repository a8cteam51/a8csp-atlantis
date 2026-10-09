---
name: atlantis-autoupdates
description: Guides Autoupdates module work including schedule windows, rollout delays, per-plugin filter toggles, and legacy Plugin Autoupdate Filter compatibility. Use when changing autoupdate behavior or related admin UI.
---

# Skill: Atlantis Autoupdates

## When to Use

Use this skill when:
- Working in `src/Modules/Autoupdates/*`.
- Updating `auto_update_plugin` / `auto_update_core` behavior.
- Changing per-plugin PAF toggle behavior in wp-admin.
- Adjusting centralized settings or delay cleanup logic.

## Main Components

| File | Responsibility |
|------|----------------|
| `AutoUpdatePluginsFilter.php` | Core hooks and autoupdate decision pipeline. |
| `PluginFilterAdminUI.php` | Plugins-screen UI for per-plugin enable/disable of Atlantis filter rules. |
| `PluginFilterRules.php` | Shared source of truth for disabled plugin list and external callback checks. |
| `Helpers.php` | Delay tracking and cleanup helpers. |

## Key Behaviors

- Uses time/day windows to determine update eligibility.
- Supports temporary no-update holiday windows.
- Adds staged delay behavior per plugin version.
- Supports per-plugin bypass via site option `plugin_autoupdate_filter_disabled_plugins`.
- If external `disable_autoupdate_specific_plugins` exists, Atlantis respects it in UI messaging.
- Centralized settings are optional. There is no built-in endpoint: `get_settings_endpoint()` reads the `a8csp_atlantis_autoupdate_settings_url` option (or the constant/filter). With none configured the module makes no remote request, holds nothing back and never fails closed; fail-closed and last-known-good only apply once an endpoint is set.
- Update emails are only redirected, and only forced on, when the centralized payload carries a valid `notification_email`.

## Guardrails

- Preserve hook priorities and accepted arg counts.
- Keep shared logic centralized in `PluginFilterRules` (avoid duplicate methods).
- Make user-facing admin text translatable and escaped.
- Do not register autoupdate hooks when module is disabled.
- Never hard-code an endpoint URL or an email address. Both come from configuration.
- Team-specific wording in notices goes through `get_manual_update_caution()`, which only names the team on a managed site.

## Procedure: Changing Filter Logic

1. Update shared rule helpers first (`PluginFilterRules`) when logic is cross-class.
2. Update runtime class (`AutoUpdatePluginsFilter`) behavior.
3. Update admin UI class for visibility/toggle behavior if user-facing behavior changes.
4. Update integration tests (`AutoupdatesTestCest`) and restore mutated options in `finally`.

## Verification

- `composer run lint:php:phpstan`
- `composer run tests:run:integration`
- Manual test on Plugins screen: toggle PAF updates and verify admin notice + setting HTML.
