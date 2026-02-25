---
name: atlantis-colophon
description: Guides work on Atlantis Colophon module, including credits rendering, shortcodes, and customization filters.
---

# Skill: Atlantis Colophon Module

## When to Use

Use this skill when:

- Working in `src/Modules/Colophon/*`.
- Updating `includes/module-colophon.php`.
- Changing credits output or shortcode behavior.

## Main Components

| File | Responsibility |
| ------ | ---------------- |
| `src/Modules/Colophon/Colophon.php` | Module registration and lifecycle hooks. |
| `includes/module-colophon.php` | Core rendering, action handler, and shortcode callbacks. |
| `src/Modules/Colophon/README.md` | Usage patterns and customization examples. |

## Guardrails

- Preserve action and shortcode contracts (`team51_credits`, `team51-credits`, `team51-current-year`).
- Keep output safely escaped and translatable where user-facing text changes.
- Ensure output buffering returns content and does not double-echo.

## Procedure

1. Update rendering logic and/or shortcode behavior.
2. Validate action and shortcode parity for equivalent options.
3. Update `tests/Integration/ColophonTestCest.php` for behavioral changes.

## Verification

- `composer run lint:php`
- `composer run tests:run:integration`
