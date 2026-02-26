| :exclamation:  This is a public repository |
|--------------------------------------------|

# a8csp-atlantis

- **Contributors:** wpcomspecialprojects
- **Tags:** auto-updates, tracking, messages, colophon, site-management
- **Requires at least:** 6.5
- **Tested up to:** 6.8.1
- **Requires PHP:** 8.3
- **Stable tag:** 1.0.5
- **License:** GPLv3 or later
- **License URI:** [http://www.gnu.org/licenses/gpl-3.0.html](http://www.gnu.org/licenses/gpl-3.0.html)



## Description

A collection of utilities developed by the WordPress Special Projects team for managing partner sites. The plugin provides a modular system with the following core modules:  

- **Messages**: Admin notification system with location-based filtering ([Readme](./src/Modules/Messages/README.md))
- **AutoUpdate Filter**: Manages WordPress plugin and core auto-updates with sophisticated timing controls, business hour restrictions, and delay periods for stability testing ([Readme](./src/Modules/Autoupdates/README.md))
- **Tracking**: Analytics integration that opts sites into tracking (disabled in development environments) ([Readme](./src/Modules/Tracking/README.md))
- **Colophon**: Footer attribution system for site credits ([Readme](./src/Modules/Colophon/README.md))
  
The plugin uses a modular architecture where individual modules can be enabled or disabled through the WordPress admin interface.  

### Centralized autoupdate settings

The Autoupdates module reads centralized settings from:

- `https://opsoasis.wpspecialprojects.com/wp-json/wpcomsp/autoupdate-plugin/v1/settings/`

The payload supports:

- `disable_all` to block all automatic updates.
- `canary_sites` to bypass delay logic on selected sites.
- `disabled_plugins` to block automatic updates for specific plugins across connected sites.

## Installation

You may install `a8csp-atlantis` either manually or through your site's plugins page.

### INSTALL FROM WITHIN WORDPRESS

1. Download the plugin from [https://github.com/a8cteam51/a8csp-atlantis](https://github.com/a8cteam51/a8csp-atlantis).
2. Visit the plugins page within your dashboard and select `Add New`.
3. Click on `Upload Plugin` then `Choose File`, select the `a8csp-atlantis.zip` file and click the `Install Now` button.
4. Click on the `Activate` button.

### INSTALL MANUALLY

1. Download the plugin from [https://github.com/a8cteam51/a8csp-atlantis](https://github.com/a8cteam51/a8csp-atlantis) and unzip the archive.
2. Upload the `a8csp-atlantis` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the `Plugins` menu in WordPress.

### AFTER ACTIVATION

Settings for the plugin can be found in the wp-admin dashboard under `Atlantis`.

## Frequently Asked Questions

### Why don't I see the Atlantis menu on WP Admin?

Ensure your user is an admin with an `@automattic.com` or `@wordpress.com` email address.

### Can I disable specific modules?

Yes, the plugin uses a modular architecture where individual modules can be enabled or disabled through the WordPress admin interface.

### How can I get more information on how to use the plugin?

You can use the chatbot at [https://deepwiki.com/a8cteam51/a8csp-atlantis](https://deepwiki.com/a8cteam51/a8csp-atlantis) for extensive help related to usability, plugin structure, and development.

## Development

### Setup

1. Install dependencies:
   ```bash
   composer install
   npm install
   ```

2. Build assets:
   ```bash
   npm run build
   ```

   This will build all JavaScript and CSS assets. The build process includes:
   - Building block editor assets
   - Compiling JavaScript files
   - Processing SCSS to CSS

3. For development, you can use watch mode:
   ```bash
   npm run start
   ```
   This will automatically rebuild assets when files change.

### Testing

Run all tests:
```bash
npm run tests:run
```

This includes both integration and end-to-end tests. Make sure Docker is running for the test environment.

### Creating a Release on GitHub

When creating a new release, follow these steps:

1. Update version numbers in:
   - `package.json` ("version" field)
   - `a8csp-atlantis.php` ("Version" in plugin header)
   - Update "Tested up to" in both README.md and a8csp-atlantis.php if WordPress compatibility was tested

2. Build a production release (if needed):
   ```bash
   composer install --no-dev
   npm install
   npm run build
   ```
   Commit and merge to trunk via a new feature branch.

3. Create a new release on GitHub:
   - Go to the Releases page at `https://github.com/a8cteam51/a8csp-atlantis/releases` and create a new release.
   - Create a new tag following semantic versioning (e.g., v1.0.1)
   - Title the release and add a description, or, click on the "Generate release notes" button. Edit as needed.
   - Click on the "Publish release" button.
   - After a few minutes the new plugin `zip` file will be available for download.

### Development FAQs

#### What to do if I get the 500 error `"Uncaught Error: Class "A8C\SpecialProjects\Atlantis\MessagesSchema" not found"` during development?

Run `composer generate-autoloader` from the root.



