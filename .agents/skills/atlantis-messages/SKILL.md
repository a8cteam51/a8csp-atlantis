---
name: atlantis-messages
description: Guides work on Atlantis Messages module, including CRUD, storage, admin list/form flows, and location-based notice rendering.
---

# Skill: Atlantis Messages Module

## When to Use

Use this skill when:

- Working in `src/Modules/Messages/*`.
- Updating helpers in `includes/module-messages.php`.
- Changing message CRUD, status transitions, or rendering logic.

## Main Components

| File | Responsibility |
| ------ | ---------------- |
| `src/Modules/Messages/Messages.php` | Module hooks, admin integrations, and orchestration. |
| `src/Modules/Messages/CustomTable.php` | DB schema and custom table lifecycle. |
| `src/Modules/Messages/ListTable.php` | Admin list table actions and UI flow. |
| `includes/module-messages.php` | Public helper APIs for CRUD and retrieval. |
| `models/Message.php`, `models/Message_Query.php` | Message model and query behavior. |

## Guardrails

- Use `$wpdb` with proper formats/placeholders for DB writes.
- Keep message helper API signatures backward compatible.
- Sanitize admin inputs and escape output.
- Preserve status semantics (`active`/`inactive`) and location filters.

## Procedure

1. Implement data/schema or logic changes in module classes.
2. Update helper wrappers in `includes/module-messages.php` if API behavior changes.
3. Update integration tests in `tests/Integration/MessagesTestCest.php`.
4. Validate no cross-test option/table state leakage.

## Verification

- `composer run lint:php`
- `composer run tests:run:integration`
