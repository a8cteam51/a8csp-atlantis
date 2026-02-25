# Atlantis WordPress Plugin — Agent Instructions

This file provides AI coding assistants with the context they need to work effectively on the Atlantis WordPress plugin.

## Plugin Overview

**Atlantis** (`a8csp-atlantis`) is a modular operational plugin for partner sites. It provides:

- A module framework with per-module enable/disable settings.
- Messages management with custom DB-backed admin notifications.
- Auto-update control (timing windows, rollout delays, centralized settings, per-plugin filter toggles).
- Tracking integrations (WooCommerce, Sensei, Bilmur).
- Colophon utilities (credits action + shortcodes).

**Text domain:** `a8csp-atlantis`  
**Namespace:** `A8C\SpecialProjects\Atlantis\`  
**Requires:** WordPress 6.8+, PHP 8.3+

---

## Directory Structure

```text
a8csp-atlantis/
├── a8csp-atlantis.php            ← Bootstrap and activation hook registration
├── functions-bootstrap.php       ← Bootstrap helpers and activation-time compatibility logic
├── functions.php                 ← Loads include helper files
├── AGENTS.md                     ← You are here
├── CLAUDE.md                     ← Points to this file
├── .agents/
│   ├── README.md
│   └── skills/
│       ├── atlantis-architecture/SKILL.md
│       ├── atlantis-autoupdates/SKILL.md
│       ├── atlantis-messages/SKILL.md
│       ├── atlantis-tracking/SKILL.md
│       ├── atlantis-colophon/SKILL.md
│       └── atlantis-testing/SKILL.md
│
├── includes/                     ← Global helper wrappers and utility functions
│   ├── module-messages.php
│   ├── module-colophon.php
│   ├── module-tracking.php
│   ├── module-autoupdates.php
│   ├── settings.php
│   ├── encryption.php
│   └── miscellaneous.php
│
├── src/
│   ├── Plugin.php                ← Main singleton
│   ├── Modules.php               ← Module registry
│   ├── Settings.php              ← Admin menu and settings pages
│   ├── Encryption.php            ← Encryption setup helpers
│   └── Modules/
│       ├── AbstractModule.php    ← Module lifecycle/settings base class
│       ├── Messages/
│       ├── Autoupdates/
│       ├── Tracking/
│       └── Colophon/
│
└── tests/Integration/            ← Module and core integration Cest tests
```

---

## Architecture

### Bootstrap

`a8csp-atlantis.php` loads bootstrap helpers and plugin entry points, then initializes on `plugins_loaded` through `Plugin::get_instance()->maybe_initialize()`.

### Module Lifecycle

All modules extend `AbstractModule`:

- settings key generation via `a8csp_atlantis_generate_module_settings_key()`
- active/disabled checks in `is_active()` and `is_disabled()`
- runtime hook registration only in `initialize()` after `maybe_initialize()` gates

### Activation Compatibility

On activation, Atlantis checks legacy `plugin-autoupdate-filter` state:

- if legacy plugin is installed and inactive -> disable Atlantis Autoupdates module
- if legacy plugin is active or not installed -> leave module settings unchanged

---

## Coding Standards

- Follow WordPress PHP coding standards used in this repository.
- Use tabs for indentation.
- Sanitize input and escape output.
- Use strict comparisons (`===`, `!==`) and Yoda conditions.
- Keep module behavior behind module activation checks.

---

## Prerequisites

| Requirement | Version |
| ----------- | ------- |
| PHP | 8.3+ |
| Node.js | 20+ |
| npm | 10+ |
| Docker | Required for integration and end-to-end tests |

---

## Build

```bash
# Install dependencies
composer install
npm install

# Build assets (block editor, JS, CSS) — MUST run before testing or deploying
npm run build
```

**Must:** JS and CSS assets are built from source (`assets/js/src/`, `assets/css/src/`, `blocks/src/`). The plugin loads from `assets/js/build/` and `assets/css/build/`. Always run `npm run build` before running tests or deploying.

For development with live rebuild on file changes:

```bash
npm run start
```

---

## Lint

```bash
# PHP (phpcs, phpmd, phpstan)
composer run lint:php

# PHPStan only
composer run lint:php:phpstan

# JS and CSS
npm run lint
# Or individually:
# npm run lint:scripts
# npm run lint:styles
```

**Must:** Run `composer run lint:php` and `npm run lint` before committing. CI runs these on `trunk` and PRs.

---

## Test

```bash
# Full test suite (starts wp-env + Selenium, runs integration + e2e)
# Requires Docker to be running
npm run tests:run

# Integration tests only (run inside wp-env)
# Either: start wp-env first, then:
npm run tests:run:integration

# Or run directly via composer (uses local Codeception; requires wp-env running separately)
composer run tests:run:integration
```

**Must:** Docker must be running. The full `npm run tests:run` starts wp-env and Selenium automatically. Integration tests require a WordPress environment (wp-env provides this).

---

## Critical / Must

1. **Build before tests or deploy:** Run `npm run build` so JS/CSS assets exist. Without it, Messages form and other UI may fail.
2. **Docker for tests:** Integration and e2e tests require Docker. Start Docker Desktop (or equivalent) before `npm run tests:run`.
3. **Regenerate autoloader:** If you see `Class "A8C\SpecialProjects\Atlantis\MessagesSchema" not found`, run `composer generate-autoloader`.
4. **Lint before commit:** Run `composer run lint:php` and `npm run lint` to avoid CI failures.
5. **Lockfiles:** Commit `composer.lock` and `package-lock.json`; CI and release builds depend on them.

---

## Skills

Plugin-specific skills are in `.agents/skills/`:

| Skill | When to Use |
| ------- | ------------- |
| `atlantis-architecture` | Plugin internals, module lifecycle, bootstrap, settings model. |
| `atlantis-autoupdates` | Autoupdate filter behavior, admin toggle UI, PAF compatibility. |
| `atlantis-messages` | Messages CRUD, storage model, list table/admin flows. |
| `atlantis-tracking` | Tracking integration behavior and environment gates. |
| `atlantis-colophon` | Credits rendering, actions, and shortcode behavior. |
| `atlantis-testing` | Integration tests, lint/CI failures, workflow stability. |

See `.agents/README.md` for structure and routing guidance.

---

## AGENTS.md Maintenance Rule

Update this file when a change affects agent decision-making, including:

- Plugin/bootstrap architecture or module lifecycle behavior.
- Module inventory, responsibilities, or integration boundaries.
- Development workflows (lint/test/build/CI commands).
- Project-specific coding constraints or guardrails.

Do not update this file for minor bug fixes or refactors that do not change architecture, workflows, or guidance.
