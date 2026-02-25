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
- Helping with build/lint/test setup or troubleshooting.

## Prerequisites

| Requirement | Version |
| ----------- | ------- |
| PHP | 8.3+ |
| Node.js | 20+ |
| npm | 10+ |
| Docker | Required for integration and e2e tests |

## Build

**Must:** Run `npm run build` before testing or deploying. JS and CSS assets are built from source; the plugin loads from `assets/js/build/` and `assets/css/build/`. Without a build, the Messages form and other UI may fail. For development with live rebuild: `npm run start`.

## Test & QA Stack

| Area | Command |
|------|---------|
| PHP lint stack | `composer run lint:php` |
| PHPStan only | `composer run lint:php:phpstan` |
| JS and CSS lint | `npm run lint` (or `lint:scripts`, `lint:styles`) |
| Integration tests | `composer run tests:run:integration` (inside wp-env) |
| Full tests | `npm run tests:run` (starts wp-env + Selenium, runs integration + e2e) |

**Must:** Docker must be running for tests. `npm run tests:run` starts wp-env and Selenium automatically.

## Current Test Convention

- Integration tests use Codeception Cest format.
- File naming: `tests/Integration/*TestCest.php`.
- Use `PHPUnit\Framework\Assert` static assertions.
- Restore modified options/state in tests to avoid leakage.

## Critical Checks Before Commit

1. **Build:** Run `npm run build` so JS/CSS assets exist.
2. **Docker:** Start Docker before `npm run tests:run`.
3. **Autoloader:** If you see `Class "A8C\SpecialProjects\Atlantis\MessagesSchema" not found`, run `composer generate-autoloader`.
4. **Lint:** Run `composer run lint:php` and `npm run lint` before committing.
5. **Lockfiles:** Commit `composer.lock` and `package-lock.json`; CI and release builds depend on them.

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

- `npm run build`
- `composer run lint:php`
- `npm run lint`
- `npm run tests:run` (or `composer run tests:run:integration` if wp-env already running)
- If workflow changed, re-run affected GitHub checks.
