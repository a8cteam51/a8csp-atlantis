# A8CSP Atlantis

> This is a public repository.

A8CSP Atlantis is a WordPress plugin from the Automattic Special Projects team
for operational management of partner sites. It provides a module framework for
admin messages, automatic update controls, tracking integrations, and footer
credit utilities.

Plugin metadata from `a8csp-atlantis.php`:

- Plugin name: `A8CSP Atlantis`
- Text domain: `a8csp-atlantis`
- Version: `1.2.0`
- Requires WordPress: `6.8+`
- Tested up to WordPress: `7.0`
- Requires PHP: `8.2+`
- License: GPL v3 or later

## Repository Layout

- `a8csp-atlantis.php` bootstraps the plugin, validates requirements, loads
  translations, registers the activation hook, and wires GitHub release update
  checks.
- `functions-bootstrap.php` contains bootstrap helpers, requirement notices, and
  activation-time compatibility with the legacy `plugin-autoupdate-filter`
  plugin.
- `functions.php` loads global helper wrappers from `includes/`.
- `src/` contains the PSR-4 plugin classes, module registry, settings UI,
  encryption component, REST controller, and WP-CLI commands.
- `src/Modules/` contains the Messages, Autoupdates, Tracking, and Colophon
  modules. Each module has a nested README with more detailed behavior notes.
- `models/` contains the DB-backed message model and query/list-table support.
- `templates/` contains admin templates, including the message form.
- `assets/js/src/` and `assets/css/src/` are the editable JS and SCSS sources.
  Built assets are committed under `assets/js/build/` and `assets/css/build/`.
- `languages/` contains translation assets.
- `tests/` contains Codeception integration and end-to-end suites.
- `.agents/` and `AGENTS.md` contain assistant-facing project guidance.

## Modules

### Messages

The Messages module is mandatory. It creates the
`{$wpdb->prefix}a8csp_atlantis_messages` custom table and exposes an Atlantis
admin submenu for creating, editing, activating, deactivating, and deleting
messages. Message content is encrypted before storage. Messages can target or
exclude admin locations and render as admin notices for Automattician users.

More detail: `src/Modules/Messages/README.md`.

### Autoupdates

The Autoupdates module manages WordPress core, plugin, and theme automatic
updates through WordPress update filters. It applies allowed update windows,
holiday windows, plugin release delays, per-plugin filter toggles, and global
disable rules.

Centralized settings are fetched from:

```text
https://opsoasis.wpspecialprojects.com/wp-json/wpcomsp/autoupdate-plugin/v1/settings/
```

The payload supports:

- `disable_all` to block all automatic updates.
- `canary_sites` to bypass plugin delay logic for selected hostnames.
- `disabled_plugins` to block specific plugins across connected sites.

On activation, if `plugin-autoupdate-filter/plugin-autoupdate-filter.php` is
installed but inactive, Atlantis disables the Autoupdates module to avoid
unexpectedly taking over legacy update behavior.

More detail: `src/Modules/Autoupdates/README.md`.

### Tracking

The Tracking module only runs in production environments. It automatically opts
supported integrations into usage or real-user monitoring:

- WooCommerce via `option_woocommerce_allow_tracking`.
- Sensei via the `sensei-settings` option.
- Bilmur via `https://s0.wp.com/wp-content/js/bilmur.min.js` and related RUM
  metadata.

More detail: `src/Modules/Tracking/README.md`.

### Colophon

The Colophon module registers a `team51_credits` action, the
`[team51-credits]` shortcode, and the `[team51-current-year]` shortcode for
standard footer credits. Output links can be adjusted with the
`team51_credit_links` filter.

More detail: `src/Modules/Colophon/README.md`.

## Runtime Interfaces

Atlantis registers an `Atlantis` wp-admin menu for users who pass
`a8csp_atlantis_is_automattician()` and have the required capabilities. Module
enablement is managed from the `Atlantis > Modules` submenu.

The status REST endpoint is available to users who can `manage_options`:

```text
GET /wp-json/a8csp-atlantis/v1/status
```

The payload includes the plugin version, registered module states, and the
stored message count when the Messages table exists.

When WP-CLI is available, the plugin registers:

```sh
wp atlantis module list
wp atlantis module status <key>
wp atlantis module activate <key>...
wp atlantis module deactivate <key>...
wp atlantis message list
wp atlantis message get <id>
```

## Development Requirements

- PHP `8.2+`; CI currently runs PHP QA on `8.3` and syntax/tests across
  supported PHP versions.
- Composer.
- Node.js `20+`.
- npm `10+`.
- Docker for wp-env, integration tests, and end-to-end tests.

Install dependencies and build assets:

```sh
composer run-script packages-install
npm ci
npm run build
```

Run watch mode while editing assets:

```sh
npm run start
```

Start the wp-env environment:

```sh
npm run wp-env:start
```

The current `.wp-env.json` maps this repository into WordPress as
`wp-content/plugins/a8csp-plugin-scaffold`; the npm test scripts use that same
path.

## Quality Checks

Run the project lint commands before committing code changes:

```sh
composer run lint:php
npm run lint
```

The root README has an explicit markdown check:

```sh
npm run lint:readme-md
```

For README-only changes, also run:

```sh
git diff --check
```

## Tests

The Codeception suites are configured in `codeception.dist.yml`,
`tests/Integration.suite.yml`, and `tests/EndToEnd.suite.yml`. Local setup notes
are in `tests/README.md`.

Prepare the local test environment:

```sh
docker run -d --shm-size="2g" --net=host --name="selenium-chromium" selenium/standalone-chromium:latest
cp tests/.dist.env tests/.env
npm run wp-env:start
npm run tests:export-db
```

Run all tests:

```sh
npm run tests:run
```

Integration and end-to-end tests require Docker. On macOS, the test README notes
that Docker host networking must be enabled.

## Releases

GitHub releases trigger `.github/workflows/build-release.yml`. The workflow
validates Composer files, installs production PHP dependencies, runs `npm ci`
and `npm run build`, copies the plugin runtime files into an
`a8csp-atlantis/` release directory, and uploads a zip asset to the release.

Before publishing a release, update the version in:

- `a8csp-atlantis.php`
- `package.json`

Run `composer generate-autoloader` if local development reports missing
classmap-backed classes after changing generated/autoloaded PHP symbols.

## Maintenance Notes

- Edit source assets in `assets/js/src/` and `assets/css/src/`, then run
  `npm run build` so the committed build files stay current.
- Keep `composer.lock` and `package-lock.json` with dependency changes; CI and
  releases use both lockfiles.
- Do not edit generated dependency directories such as `vendor/` or
  `node_modules/`.
- Update `AGENTS.md` when architecture, module behavior, workflow commands, or
  assistant-facing project rules change.

## License

The plugin header and bundled `LICENSE` file identify this project as GPL v3 or
later.


