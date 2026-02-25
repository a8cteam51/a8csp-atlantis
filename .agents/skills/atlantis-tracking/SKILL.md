---
name: atlantis-tracking
description: Guides work on Atlantis Tracking module for WooCommerce, Sensei, and Bilmur integrations, including environment-based activation behavior.
---

# Skill: Atlantis Tracking Module

## When to Use

Use this skill when:

- Working in `src/Modules/Tracking/*`.
- Changing tracking defaults or integration enable/disable behavior.
- Adjusting environment gates for development/staging/local.

## Main Components

| File | Responsibility |
| ------ | ---------------- |
| `src/Modules/Tracking/Tracking.php` | Module orchestration and environment checks. |
| `src/Modules/Tracking/Integrations/WooCommerce.php` | WooCommerce tracking integration behavior. |
| `src/Modules/Tracking/Integrations/Sensei.php` | Sensei tracking integration behavior. |
| `src/Modules/Tracking/Integrations/Bilmur.php` | Bilmur RUM integration and script behavior. |

## Guardrails

- Keep non-production environment disable behavior intact unless explicitly changed.
- Preserve existing constant-based feature toggles.
- Avoid regressions in default opt-in behavior for integrations.

## Procedure

1. Update module or specific integration class.
2. Validate constant gates and environment checks.
3. Update `tests/Integration/TrackingTestCest.php` for behavior changes.

## Verification

- `composer run lint:php`
- `composer run tests:run:integration`
