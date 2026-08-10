---
name: atlantis-architecture
description: Guides work on Atlantis plugin PHP architecture including bootstrap flow, module lifecycle, settings model, and activation behavior. Use when adding/changing modules, plugin initialization, or shared helper patterns.
---

# Skill: Atlantis Plugin Architecture

## When to Use

Use this skill when:
- Adding or modifying modules in `src/Modules/`.
- Changing plugin bootstrap/initialization flow.
- Updating module settings behavior in `AbstractModule`.
- Adding compatibility logic during activation.

## Bootstrap Flow

1. `a8csp-atlantis.php` defines constants and loads `functions-bootstrap.php`.
2. Activation hook is registered for activation-time compatibility checks.
3. `functions.php` loads `includes/*.php` helper files.
4. `plugins_loaded` triggers `a8csp_atlantis_get_plugin_instance()->maybe_initialize()`.
5. `Plugin::initialize()` wires `Encryption`, `Modules`, and `Settings`.

## Module Pattern

Atlantis modules follow `AbstractModule`:

- `is_active()` reads `a8csp_module_{slug}` settings (`enabled` flag).
- `maybe_initialize()` returns early if module is disabled.
- `initialize()` registers module runtime hooks only when active.
- Default module setting is created via `maybe_set_default_settings()`.

Current module registry:
- `messages`
- `colophon`
- `tracking`
- `autoupdates`

## Settings Model

- Module option key helper: `a8csp_atlantis_generate_module_settings_key()`.
- Module settings getter: `a8csp_atlantis_get_module_settings()`.
- Admin menus are restricted to probable Automatticians (`a8csp_atlantis_is_automattician()`).

## Activation Compatibility Behavior

Activation hook currently performs legacy PAF compatibility checks:
- If `plugin-autoupdate-filter/plugin-autoupdate-filter.php` is installed and inactive, Atlantis disables the Autoupdates module.
- If not installed, Atlantis leaves module settings unchanged.

## Procedure: Adding a New Module

1. Create module class in `src/Modules/{ModuleName}/`.
2. Extend `AbstractModule` and implement `get_name()`, `get_description()`, `initialize()`.
3. Register module in `src/Modules.php`.
4. Add helper wrappers under `includes/module-{name}.php` if needed.
5. Add `tests/Integration/{ModuleName}TestCest.php`.

## Verification

- `composer run lint:php`
- `composer run tests:run:integration`
- Validate module can be toggled in Atlantis Modules settings.
