# Plugin Autoupdate Filter
Filters whether autoupdates are on based on day/time and other settings.

## What's this?
This is a plugin that the WordPress Special Projects team uses on many of their partner sites in order to help manage autoupdates in a responsible way. For example:
1. It defaults autoupdates to be on. Keeping plugins up-to-date is one of the the first lines of defense against malicious attacks and technical debt.
2. It provides various mechanisms by which we can turn off autoupdates, such as during specific days/times, for specific plugins, or centralized settings which can turn off all autoupdates.

## Usage

### Notes on functionality

This plugin filters the core `auto_update_plugin` functionality to always run autoupdates during specific hours. It doesn't respect any toggle settings prior to activating this plugin, and is also respected by Jetpack autoupdate settings (the Jetpack autoupdate toggles may still reflect something different, but are not meaningful if this plugin is activated).

By default, the plugin always returns `true` for autoupdates Mon-Thu 6am-7pm Eastern, and Fri 6am-3pm Eastern. The 13 hour days are because the cron event which checks for autoupdates only runs every 12 hours, and so if the window isn't more than 12 hours at least once during the week, we run the risk of missing updates completely.

### Centralized settings

The module ships with no centralized settings endpoint. Without one it makes no remote request and runs on its local rules alone: the update windows, the release delay, the holiday windows and the per-plugin toggles.

To have several sites follow shared settings, run an endpoint of your own and give each site its URL:

```
wp atlantis site settings-url https://example.com/wp-json/example/v1/settings/
```

The URL is stored in the `a8csp_atlantis_autoupdate_settings_url` option. The `A8CSP_ATLANTIS_AUTOUPDATE_SETTINGS_URL` constant overrides it when defined, and the `a8csp_atlantis_autoupdate_settings_url` filter can change it. There is no need to edit the plugin's code.

The endpoint answers a `GET` with a JSON object. Every key is optional:

| Key | Type | Effect |
| --- | --- | --- |
| `disable_all` | `true` | Stops all plugin, theme and core autoupdates. |
| `disabled_plugins` | list of plugin files or slugs | Stops autoupdates for those plugins. |
| `canary_sites` | list of hostnames | Those sites skip the release delay. |
| `notification_email` | email address | Autoupdate emails go to this address instead of the site's own, and are forced on even where the host turned them off. |

**Once an endpoint is configured, the module fails closed.** If the endpoint cannot be reached, the last payload it fetched is reused for 24 hours (filterable with `a8csp_atlantis_autoupdate_settings_grace`). After that, all autoupdates stop until the endpoint answers again. Only configure an endpoint you intend to keep running.

### Update emails

With no `notification_email` in the centralized settings, the module does not touch autoupdate emails: WordPress sends them to the site's admin address, and your host decides whether they are sent at all.

## Support

**This plugin is unsupported; use at your own discretion**

If you have a problem or suggestion, please make an issue in the repo here: https://github.com/a8cteam51/a8csp-atlantis/issues

Feel free to fork and/or create a PR!

## Filters
### Set your own hours/days
If you'd like to customize the times and days, you can filter them. e.g.:
```
function custom_autoupdate_hours( $hours ) {
  return array(
    start      => '10', // 6am Eastern
    end        => '23', // 7pm Eastern
    friday_end => '20', // 4pm Eastern on Fridays
  );
}
add_filter( 'plugin_autoupdate_filter_hours', 'custom_autoupdate_hours' );
```
```
function custom_autoupdate_days_off( $days_off ) {
  // if you don't want updates to run on Fri, Sat, or Sun at all
  return array(
    Fri,
    Sat,
    Sun,
  );
}
add_filter( 'plugin_autoupdate_filter_days_off', 'custom_autoupdate_days_off' );
```
### Set holidays
If you'd like to set windows of time for no updates, you can filter them. e.g.:
```
$holidays = array(
  'christmas' => array(
    'start' => '2021-12-23 00:00:00',
    'end'   => '2021-12-26 00:00:00'
  ),
);
add_filter( 'plugin_autoupdate_filter_holidays', 'custom_autoupdate_holidays' );
```

### Disable autoupdate completely for specific plugins
If you still need to turn off autoupdates for a specific plugin, you can filter `auto_update_plugin` at a priority greater than 10, and prevent specific plugins from updating.

**NOTE: If you do this, please name your function `disable_autoupdate_specific_plugins`**, so that we can add appropriate notices in wp-admin, e.g.

```
function disable_autoupdate_specific_plugins ( $update, $item ) {
    // Array of plugin slugs to never auto-update
    $plugins = array (
        'akismet',
        'buddypress',
    );
    if ( in_array( $item->slug, $plugins ) ) {
         // Never update plugins in this array
        return false;
    } else {
        // Else, do whatever it was going to do before
        return $update;
    }
}
add_filter( 'auto_update_plugin', 'disable_autoupdate_specific_plugins', 11, 2 );
```
