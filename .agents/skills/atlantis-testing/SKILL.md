---
name: atlantis-testing
description: Guides writing/running Atlantis tests and fixing CI issues across Codeception integration tests, PHP QA tools, and npm/composer workflow stability.
---

# Skill: Atlantis Testing

## When to Use

Use this skill when:
- Writing or modifying integration tests in `tests/Integration/`.
- Debugging CI failures (`phpcs`, `phpmd`, `phpstan`, `npm ci`, Codeception).
- Updating GitHub workflow behavior.

## Test & QA Stack

| Area | Command |
|------|---------|
| PHP lint stack | `composer run lint:php` |
| PHPStan only | `composer run lint:php:phpstan` |
| Integration tests | `composer run tests:run:integration` |
| Full tests | `npm run tests:run` |

## Current Test Convention

- Integration tests use Codeception Cest format.
- File naming: `tests/Integration/*TestCest.php`.
- Use `PHPUnit\Framework\Assert` static assertions.
- Restore modified options/state in tests to avoid leakage.

## CI Workflow Guidance

- Workflows running `npm ci` should pin Node major version for stability.
- Lockfile drift issues often come from running old SHA or runtime mismatch.
- Ensure `package-lock.json` is regenerated consistently when needed.

## Procedure: Adding/Changing Tests

1. Add or update module-specific Cest test.
2. Cover primary behavior + edge cases + option cleanup.
3. Run local integration tests and lint.
4. If CI fails, verify workflow runtime and commit SHA before changing code.

## Verification

- `composer run lint:php`
- `composer run tests:run:integration`
- If workflow changed, re-run affected GitHub checks.
