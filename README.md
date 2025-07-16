# a8csp-atlantis

**Contributors:** wpcomspecialprojects
**Tags:** auto-updates, tracking, messages, colophon, site-management
**Requires at least:** 6.5
**Tested up to:** 6.5
**Requires PHP:** 8.3
**Stable tag:** 1.0.0
**License:** GPLv3 or later
**License URI:** [http://www.gnu.org/licenses/gpl-3.0.html](http://www.gnu.org/licenses/gpl-3.0.html)



## Description

A comprehensive WordPress plugin developed by the WordPress Special Projects team for managing partner sites. The plugin provides a modular system with four core modules:  

- **Messages**: Admin notification system with location-based filtering
- **AutoUpdate Filter**: Manages WordPress plugin and core auto-updates with sophisticated timing controls, business hour restrictions, and delay periods for stability testing  
- **Tracking**: Analytics integration that opts sites into tracking (disabled in development environments)   
- **Colophon**: Footer attribution system for site credits  
  
The plugin uses a modular architecture where individual modules can be enabled or disabled through the WordPress admin interface.  

## Installation

You may install `a8csp-atlantis` either manually or through your site's plugins page.

### INSTALL FROM WITHIN WORDPRESS

1. Download the plugin from [https://github.com/a8cteam51/a8csp-atlantis](https://github.com/a8cteam51/a8csp-atlantis).
2. Visit the plugins page withing your dashboard and select `Add New`.
3. Click on `Upload Plugin` then `Choose File`, select the `a8csp-atlantis.zip` file and click the `Install Now` button.
4. Click on the `Activate` button.

### INSTALL MANUALLY

1. Download the plugin from [https://github.com/a8cteam51/a8csp-atlantis](https://github.com/a8cteam51/a8csp-atlantis) and unzip the archive.
2. Upload the `atlantis` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the `Plugins` menu in WordPress.

### AFTER ACTIVATION

Settings for the plugin can be found in the wp-admin dashboard under `Atlantis`.

## Frequently Asked Questions

### Why don't I see the Atlantis menu on Wp Admin?

Make sure your user is `automattic.com` or `wordpress.com` email address

### Can I disable specific modules?

Yes, the plugin uses a modular architecture where individual modules can be enabled or disabled through the WordPress admin interface.

### How can I get more information on how to use the plugin?

You can use the chatbot at [https://deepwiki.com/a8cteam51/a8csp-atlantis](https://deepwiki.com/a8cteam51/a8csp-atlantis) for extensive help related to useablity, plugin structure, and development.

## Development

### Prerequisites  
  
Before starting development, ensure you have the following installed:  
  
- **Node.js 20.0+** and **npm 10.0+**
- **PHP 8.3+** or higher
- **Docker** (optional, required for wp-env)
- **Git**



### What to do if I get a 500 error "Uncaught Error: Class "A8C\SpecialProjects\Atlantis\MessagesSchema" not found"

Run `composer generate-autoloader` from the root



