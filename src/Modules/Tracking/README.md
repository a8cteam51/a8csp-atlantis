# Tracking Module

A module that automatically opts sites into various tracking and analytics systems for better monitoring and data collection. The module is designed to work seamlessly with WooCommerce, Sensei, and Bilmur RUM data collector.

## What's this?

This module helps WordPress Special Projects team monitor and collect usage data from partner sites by automatically enabling tracking features in various systems. It:

1. Automatically enables WooCommerce usage tracking
2. Automatically enables Sensei usage tracking
3. Integrates Bilmur RUM (Real User Monitoring) data collector
4. Automatically disables itself in development and staging environments

## Usage

The module works automatically once enabled. However, you can control various aspects of the tracking through constants:

### WooCommerce Tracking

```php
// Disable WooCommerce tracking
define('WPCOMSP_WC_TRACKING', false);
```

### Sensei Tracking

```php
// Disable Sensei tracking
define('WPCOMSP_SENSEI_TRACKING', false);
```

### Bilmur RUM Configuration

```php
// Enable Bilmur tracking (required)
define('WPCOMSP_BILMUR_TRACKING', true);

// Optional: Configure Bilmur provider
define('WPCOMSP_BILMUR_PROVIDER', 'your-provider');

// Optional: Configure Bilmur service
define('WPCOMSP_BILMUR_SERVICE', 'your-service');

// Optional: Add custom properties
define('WPCOMSP_BILMUR_CUSTOM_PROPERTIES', [
    'property1' => 'value1',
    'property2' => 'value2'
]);
```

## Environment Handling

The module automatically disables itself in non-production environments. Specifically, it will not run if:
- `WP_ENVIRONMENT_TYPE` is set to 'development'
- `WP_ENVIRONMENT_TYPE` is set to 'staging'
- `WP_ENVIRONMENT_TYPE` is set to 'develop'
- `WP_ENVIRONMENT_TYPE` is set to 'local'

## Implementation Notes

### WooCommerce Tracking
- Forces the `woocommerce_allow_tracking` option to 'yes'
- Applied with maximum priority to ensure it overrides other settings

### Sensei Tracking
- Modifies the `sensei-settings` option to enable usage tracking
- Applied with maximum priority to ensure it overrides other settings

### Bilmur RUM
- Loads the Bilmur script from `https://s0.wp.com/wp-content/js/bilmur.min.js`
- Supports custom configuration through constants
- Script is loaded with async attribute for better performance

## Support

If you encounter any issues or have suggestions, please create an issue in the repository at:
https://github.com/a8cteam51/a8csp-atlantis/issues

