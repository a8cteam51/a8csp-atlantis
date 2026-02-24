<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Autoupdates;

defined( 'ABSPATH' ) || exit;

/**
 * Shared rules and storage for per-plugin autoupdate filter controls.
 *
 * @since   1.0.3
 * @version 1.0.3
 */
class PluginFilterRules {

	/**
	 * Option key that stores plugins for which this module's filters are disabled.
	 *
	 * @var string
	 */
	public const string DISABLED_PLUGIN_FILTERS_OPTION = 'plugin_autoupdate_filter_disabled_plugins';

	/**
	 * Get a normalized list of plugin files for which filter logic is disabled.
	 *
	 * @return array<int, string>
	 */
	public static function get_filter_disabled_plugins(): array {
		$disabled_plugins = get_site_option( self::DISABLED_PLUGIN_FILTERS_OPTION, array() );

		if ( ! is_array( $disabled_plugins ) ) {
			return array();
		}

		return array_values(
			array_unique(
				array_map(
					'plugin_basename',
					array_filter( $disabled_plugins, 'is_string' )
				)
			)
		);
	}

	/**
	 * Determine whether filter logic is disabled for a plugin file.
	 *
	 * @param string $plugin_file Plugin basename or file path.
	 *
	 * @return bool
	 */
	public static function is_filter_disabled_for_plugin_file( string $plugin_file ): bool {
		return in_array( plugin_basename( $plugin_file ), self::get_filter_disabled_plugins(), true );
	}

	/**
	 * Evaluate external plugin-level autoupdate rules.
	 *
	 * @param \stdClass $plugin_obj Plugin-like object containing a slug property.
	 *
	 * @return bool
	 */
	public static function is_plugin_allowed_to_autoupdate( \stdClass $plugin_obj ): bool {
		if ( ! function_exists( 'disable_autoupdate_specific_plugins' ) ) {
			return true;
		}

		// @phpstan-ignore-next-line Optional external callback may be unavailable in some installs.
		return (bool) call_user_func( '\disable_autoupdate_specific_plugins', true, $plugin_obj );
	}
}
